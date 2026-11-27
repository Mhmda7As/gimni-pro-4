<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Provider;
use App\Models\Setting;
use App\Models\Specialty;
use App\Models\TimeSlot;
use App\Services\WhatsAppCloudService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WhatsAppWebhookAndBotTest extends TestCase
{
    use RefreshDatabase;

    private Specialty $specialty;
    private Provider $provider;
    private TimeSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.verify_token' => 'test_verify_token',
            'services.whatsapp.app_secret' => 'test_app_secret',
            'services.whatsapp.sandbox' => true,
        ]);

        $this->specialty = Specialty::create([
            'name' => 'Dentistry',
            'is_booking_enabled' => true,
        ]);

        $this->provider = Provider::create([
            'specialty_id' => $this->specialty->id,
            'name' => 'Dr. Omar',
            'title' => 'Dr.',
            'type' => 'doctor',
            'is_recurring' => true,
            'is_active' => true,
        ]);

        $this->slot = TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '14:00:00',
            'end_time' => '14:30:00',
            'status' => 'available',
        ]);
    }

    public function test_meta_webhook_verification(): void
    {
        $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=test_verify_token&hub.challenge=123456')
            ->assertOk()
            ->assertSeeText('123456');

        $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=wrong_token&hub.challenge=123456')
            ->assertForbidden();
    }

    public function test_meta_webhook_signature_verification(): void
    {
        $payload = json_encode(['entry' => []]);
        $validSig = 'sha256=' . hash_hmac('sha256', $payload, 'test_app_secret');

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Hub-Signature-256' => 'sha256=invalid',
        ], $payload)->assertStatus(401);

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Hub-Signature-256' => $validSig,
        ], $payload)->assertOk();
    }

    public function test_banned_patient_is_blocked_at_start_of_flow(): void
    {
        Patient::create([
            'phone_number' => '905559998877',
            'last_registered_name' => 'Banned User',
            'kvkk_accepted' => true,
            'no_show_count' => 3,
            'is_banned' => true,
            'banned_until' => now()->addDays(30),
        ]);

        $mock = Mockery::mock(WhatsAppCloudService::class);
        $mock->shouldReceive('sendText')
            ->once()
            ->with('905559998877', Mockery::on(fn ($msg) => str_contains($msg, 'إيقاف إمكانية الحجز')))
            ->andReturn(true);
        $this->app->instance(WhatsAppCloudService::class, $mock);

        $this->sendWebhookMessage('905559998877', ['type' => 'text', 'text' => ['body' => 'مرحبا']]);
    }

    public function test_global_kill_switch_blocks_bot(): void
    {
        Setting::setValue('global_booking_enabled', '0');

        $mock = Mockery::mock(WhatsAppCloudService::class);
        $mock->shouldReceive('sendText')
            ->once()
            ->with('905551234567', Mockery::on(fn ($msg) => str_contains($msg, 'متوقف مؤقتاً')))
            ->andReturn(true);
        $this->app->instance(WhatsAppCloudService::class, $mock);

        $this->sendWebhookMessage('905551234567', ['type' => 'text', 'text' => ['body' => 'hi']]);
    }

    public function test_full_whatsapp_booking_and_cancellation_flow(): void
    {
        $mock = Mockery::mock(WhatsAppCloudService::class);
        $mock->shouldReceive('sendButtons')->andReturn(true);
        $mock->shouldReceive('sendList')->andReturn(true);
        $mock->shouldReceive('sendText')->andReturn(true);
        $this->app->instance(WhatsAppCloudService::class, $mock);

        $phone = '905553334455';

        // 1. Greeting -> MAIN_MENU
        $this->sendWebhookMessage($phone, ['type' => 'text', 'text' => ['body' => 'مرحبا']]);

        // 2. Click Book -> KVKK_CONSENT
        $this->sendWebhookMessage($phone, [
            'type' => 'interactive',
            'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'ACT_BOOK', 'title' => 'حجز موعد جديد']],
        ]);

        // 3. Accept KVKK -> SELECT_SPECIALTY
        $this->sendWebhookMessage($phone, [
            'type' => 'interactive',
            'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'KVKK_YES', 'title' => 'أوافق وأتابع']],
        ]);

        // 4. Select Specialty -> SELECT_PROVIDER
        $this->sendWebhookMessage($phone, [
            'type' => 'interactive',
            'interactive' => ['type' => 'list_reply', 'list_reply' => ['id' => 'SPEC_' . $this->specialty->id, 'title' => 'Dentistry']],
        ]);

        // 5. Select Provider -> SELECT_DATE
        $this->sendWebhookMessage($phone, [
            'type' => 'interactive',
            'interactive' => ['type' => 'list_reply', 'list_reply' => ['id' => 'PROV_' . $this->provider->id, 'title' => 'Dr. Omar']],
        ]);

        // 6. Select Date -> SELECT_SLOT
        $this->sendWebhookMessage($phone, [
            'type' => 'interactive',
            'interactive' => ['type' => 'list_reply', 'list_reply' => ['id' => 'DATE_' . Carbon::tomorrow()->toDateString(), 'title' => Carbon::tomorrow()->toDateString()]],
        ]);

        // 7. Select Slot -> ENTER_NEW_NAME
        $this->sendWebhookMessage($phone, [
            'type' => 'interactive',
            'interactive' => ['type' => 'list_reply', 'list_reply' => ['id' => 'SLOT_' . $this->slot->id, 'title' => '14:00']],
        ]);

        // 8. Enter Patient Name -> CONFIRM_BOOKING
        $this->sendWebhookMessage($phone, ['type' => 'text', 'text' => ['body' => 'يوسف المنصور']]);

        // 9. Confirm Booking -> Appointment created
        $this->sendWebhookMessage($phone, [
            'type' => 'interactive',
            'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'CONFIRM_YES', 'title' => 'تأكيد الحجز']],
        ]);

        $appointment = Appointment::where('time_slot_id', $this->slot->id)->first();
        $this->assertNotNull($appointment);
        $this->assertEquals('scheduled', $appointment->status);
        $this->assertEquals('booked', $this->slot->fresh()->status);

        // 10. View Active Appointments -> MY_APPOINTMENTS
        $this->sendWebhookMessage($phone, [
            'type' => 'interactive',
            'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'ACT_MY_APTS', 'title' => 'مواعيدي / إلغاء']],
        ]);

        // 11. Select Appointment to cancel -> CONFIRM_CANCEL
        $this->sendWebhookMessage($phone, [
            'type' => 'interactive',
            'interactive' => ['type' => 'list_reply', 'list_reply' => ['id' => 'CANCEL_APT_' . $appointment->id, 'title' => 'Cancel']],
        ]);
        $this->assertEquals('scheduled', $appointment->fresh()->status);

        // 12. Confirm cancellation -> cancelled + 10-min cooldown
        $this->sendWebhookMessage($phone, [
            'type' => 'interactive',
            'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'CANCEL_YES', 'title' => 'نعم، تأكيد الإلغاء']],
        ]);

        $this->assertEquals('cancelled', $appointment->fresh()->status);
        $this->assertEquals('cooldown', $this->slot->fresh()->status);
        $this->assertNotNull($this->slot->fresh()->available_at);
    }

    private function sendWebhookMessage(string $from, array $message): void
    {
        $body = json_encode([
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [array_merge(['from' => $from], $message)],
                    ],
                ]],
            ]],
        ]);

        $sig = 'sha256=' . hash_hmac('sha256', $body, 'test_app_secret');

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Hub-Signature-256' => $sig,
        ], $body)->assertOk();
    }
}
