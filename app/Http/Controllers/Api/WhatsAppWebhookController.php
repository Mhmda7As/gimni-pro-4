<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppBotEngine;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private readonly WhatsAppBotEngine $botEngine
    ) {}

    /**
     * Meta Webhook Verification (GET).
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.verify_token')) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * Incoming WhatsApp Messages & Interactive Replies (POST).
     */
    public function handle(Request $request): Response
    {
        // Verify Meta Payload Signature
        $appSecret = config('services.whatsapp.app_secret');
        if (app()->environment('production') && (empty($appSecret) || $appSecret === 'your_meta_app_secret')) {
            Log::error('WhatsApp Webhook App Secret is not configured in production.');
            return response('Webhook secret not configured', 500);
        }

        if (!empty($appSecret) && $appSecret !== 'your_meta_app_secret') {
            $signature = $request->header('X-Hub-Signature-256');
            $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);

            if (!$signature || !hash_equals($expected, $signature)) {
                Log::warning('Invalid WhatsApp Webhook Signature');
                return response('Invalid signature', 401);
            }
        }

        $payload = $request->all();
        $entry = $payload['entry'][0] ?? null;
        $changes = $entry['changes'][0]['value'] ?? null;
        $messages = $changes['messages'] ?? [];

        foreach ($messages as $message) {
            $from = $message['from'] ?? null;
            if (!$from) {
                continue;
            }

            $type = $message['type'] ?? 'text';
            $inputId = '';
            $inputText = '';

            if ($type === 'text') {
                $inputText = $message['text']['body'] ?? '';
                $inputId = trim($inputText);
            } elseif ($type === 'interactive') {
                $interactiveType = $message['interactive']['type'] ?? '';
                if ($interactiveType === 'button_reply') {
                    $inputId = $message['interactive']['button_reply']['id'] ?? '';
                    $inputText = $message['interactive']['button_reply']['title'] ?? '';
                } elseif ($interactiveType === 'list_reply') {
                    $inputId = $message['interactive']['list_reply']['id'] ?? '';
                    $inputText = $message['interactive']['list_reply']['title'] ?? '';
                }
            }

            if ($inputId !== '' || $inputText !== '') {
                $this->botEngine->handleIncoming($from, $inputId, $inputText);
            }
        }

        // Meta requires an immediate 200 OK response
        return response('EVENT_RECEIVED', 200);
    }
}