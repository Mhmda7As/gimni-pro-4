<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppCloudService
{
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $version = config('services.whatsapp.version', 'v21.0');
        $phoneId = config('services.whatsapp.phone_number_id');
        $this->token = (string) config('services.whatsapp.access_token');
        $this->baseUrl = "https://graph.facebook.com/{$version}/{$phoneId}/messages";
    }

    /**
     * Send a plain text message.
     */
    public function sendText(string $to, string $body): bool
    {
        return $this->dispatch([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $body,
            ],
        ]);
    }

    /**
     * Send interactive reply buttons (max 3 buttons, title max 20 chars).
     *
     * @param array<int, array{id: string, title: string}> $buttons
     */
    public function sendButtons(string $to, string $bodyText, array $buttons, ?string $headerText = null): bool
    {
        $formattedButtons = array_map(fn ($btn) => [
            'type' => 'reply',
            'reply' => [
                'id' => $btn['id'],
                'title' => mb_substr($btn['title'], 0, 20),
            ],
        ], array_slice($buttons, 0, 3));

        $interactive = [
            'type' => 'button',
            'body' => ['text' => $bodyText],
            'action' => ['buttons' => $formattedButtons],
        ];

        if ($headerText) {
            $interactive['header'] = [
                'type' => 'text',
                'text' => mb_substr($headerText, 0, 60),
            ];
        }

        return $this->dispatch([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => $interactive,
        ]);
    }

    /**
     * Send an interactive list menu (max 10 rows, row title max 24 chars).
     *
     * @param array<int, array{id: string, title: string, description?: string}> $rows
     */
    public function sendList(
        string $to,
        string $bodyText,
        string $buttonLabel,
        string $sectionTitle,
        array $rows
    ): bool {
        $formattedRows = array_map(function ($row) {
            $item = [
                'id' => $row['id'],
                'title' => mb_substr($row['title'], 0, 24),
            ];
            if (!empty($row['description'])) {
                $item['description'] = mb_substr($row['description'], 0, 72);
            }
            return $item;
        }, array_slice($rows, 0, 10));

        return $this->dispatch([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => $bodyText],
                'action' => [
                    'button' => mb_substr($buttonLabel, 0, 20),
                    'sections' => [
                        [
                            'title' => mb_substr($sectionTitle, 0, 24),
                            'rows' => $formattedRows,
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function dispatch(array $payload): bool
    {
        if (config('services.whatsapp.sandbox', true) || empty($this->token)) {
            Log::info('WhatsApp Dry-Run Dispatch:', $payload);
            return true;
        }

        $response = Http::withToken($this->token)
            ->timeout(10)
            ->acceptJson()
            ->post($this->baseUrl, $payload);

        if ($response->failed()) {
            Log::error('WhatsApp Cloud API Error', [
                'status' => $response->status(),
                'body' => $response->json(),
                'payload' => $payload,
            ]);
            return false;
        }

        return true;
    }
}