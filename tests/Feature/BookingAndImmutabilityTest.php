<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Provider;
use App\Models\ProviderSchedule;
use App\Models\Specialty;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\BookingService;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BookingAndImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $bookingService;
    private User $secretary;
    private Provider $provider;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bookingService = app(BookingService::class);

        $this->secretary = User::create([
            'name' => 'Secretary',
            'email' => 'sec@clinic.test',
            'password' => Hash::make('secret'),
            'role' => 'secretary',
            'is_active' => true,
        ]);

        $specialty = Specialty::create([
            'name' => 'Dermatology',
            'is_booking_enabled' => true,
        ]);

        $this->provider = Provider::create([
            'specialty_id' => $specialty->id,
            'name' => 'Dr. Sara',
            'type' => 'doctor',
            'is_recurring' => true,
            'is_active' => true,
        ]);

        $this->patient = Patient::create([
            'phone_number' => '905551112233',
            'last_registered_name' => 'Ahmad Test',
            'kvkk_accepted' => true,
        ]);
    }

    public function test_double_booking_same_slot_is_prevented(): void
    {
        $tomorrow = Carbon::tomorrow()->toDateString();
        $slot = TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => $tomorrow,
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => 'available',
        ]);

        $secondPatient = Patient::create([
            'phone_number' => '905554445566',
            'last_registered_name' => 'Second Patient',
            'kvkk_accepted' => true,
        ]);

        $apt = $this->bookingService->bookSlot($this->patient->id, $slot->id);
        $this->assertEquals('scheduled', $apt->status);
        $this->assertEquals('booked', $slot->fresh()->status);

        $this->expectException(DomainException::class);
        $this->bookingService->bookSlot($secondPatient->id, $slot->id);
    }

    public function test_cancellation_triggers_10_minute_cooldown_and_releases_after_10_minutes(): void
    {
        $tomorrow = Carbon::tomorrow()->toDateString();
        $slot = TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => $tomorrow,
            'start_time' => '11:00:00',
            'end_time' => '11:30:00',
            'status' => 'available',
        ]);

        $appointment = $this->bookingService->bookSlot($this->patient->id, $slot->id);

        // Cancel at T=0
        $this->bookingService->cancelAppointment($appointment->id, 'Patient requested');

        $slot->refresh();
        $this->assertEquals('cooldown', $slot->status);
        $this->assertNotNull($slot->available_at);
        $this->assertTrue($slot->available_at->greaterThan(now()->addMinutes(9)));

        // Booking during cooldown must fail
        $otherPatient = Patient::create([
            'phone_number' => '905557778899',
            'last_registered_name' => 'Other Patient',
            'kvkk_accepted' => true,
        ]);

        try {
            $this->bookingService->bookSlot($otherPatient->id, $slot->id);
            $this->fail('Expected DomainException during cooldown.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('no longer available', $e->getMessage());
        }

        // Travel 11 minutes into the future and run release-cooldowns command
        $this->travel(11)->minutes();
        $this->artisan('slots:release-cooldowns')->assertExitCode(0);

        $slot->refresh();
        $this->assertEquals('available', $slot->status);
        $this->assertNull($slot->available_at);

        // Now booking succeeds
        $newApt = $this->bookingService->bookSlot($otherPatient->id, $slot->id);
        $this->assertEquals('scheduled', $newApt->status);
    }

    public function test_three_no_shows_automatically_bans_patient(): void
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        for ($i = 0; $i < 3; $i++) {
            $slot = TimeSlot::create([
                'provider_id' => $this->provider->id,
                'date' => $tomorrow,
                'start_time' => sprintf('%02d:00:00', 10 + $i),
                'end_time' => sprintf('%02d:30:00', 10 + $i),
                'status' => 'available',
            ]);
            $apt = $this->bookingService->bookSlot($this->patient->id, $slot->id);
            $this->bookingService->markAsNoShow($apt->id, $this->secretary->id);
        }

        $this->patient->refresh();
        $this->assertEquals(3, $this->patient->no_show_count);
        $this->assertTrue($this->patient->is_banned);
        $this->assertTrue($this->patient->isCurrentlyBanned());

        // Booking a 4th slot must be rejected
        $fourthSlot = TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => $tomorrow,
            'start_time' => '15:00:00',
            'end_time' => '15:30:00',
            'status' => 'available',
        ]);

        $this->expectException(DomainException::class);
        $this->bookingService->bookSlot($this->patient->id, $fourthSlot->id);
    }

    public function test_past_date_appointment_cannot_be_edited_via_web_api_or_service(): void
    {
        $yesterday = Carbon::yesterday()->toDateString();
        $slot = TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => $yesterday,
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'status' => 'booked',
        ]);

        $pastAppointment = Appointment::create([
            'patient_id' => $this->patient->id,
            'provider_id' => $this->provider->id,
            'time_slot_id' => $slot->id,
            'appointment_date' => $yesterday,
            'start_time' => '09:00:00',
            'status' => 'scheduled',
            'payment_amount' => 0,
        ]);

        // Web Daily Desk status & payment edit must return 403
        $this->actingAs($this->secretary)
            ->patchJson("/daily-desk/appointments/{$pastAppointment->id}/status", ['status' => 'attended'])
            ->assertForbidden();

        $this->actingAs($this->secretary)
            ->patchJson("/daily-desk/appointments/{$pastAppointment->id}/payment", ['payment_amount' => 200])
            ->assertForbidden();

        // API status & payment edit must return 403
        $token = $this->secretary->createToken('excel')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson("/api/excel/appointments/{$pastAppointment->id}/status", ['status' => 'cancelled'])
            ->assertForbidden();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/excel/appointments/{$pastAppointment->id}/payment", ['amount' => 200])
            ->assertForbidden();

        // Service-level cancel must throw DomainException
        $this->expectException(DomainException::class);
        $this->bookingService->cancelAppointment($pastAppointment->id);
    }

    public function test_daily_desk_quick_book_and_archive_work_with_patient_schema(): void
    {
        $today = Carbon::today()->toDateString();
        $slot = TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => $today,
            'start_time' => '23:55:00',
            'end_time' => '23:59:00',
            'status' => 'available',
        ]);

        $this->actingAs($this->secretary)
            ->post('/daily-desk/quick-book', [
                'name' => 'خالد العلي',
                'phone' => '+90 555 000 1122',
                'time_slot_id' => $slot->id,
            ])
            ->assertRedirect(route('desk.index'));

        $this->assertDatabaseHas('patients', [
            'phone_number' => '905550001122',
            'last_registered_name' => 'خالد العلي',
        ]);

        $this->actingAs($this->secretary)
            ->get('/daily-desk')
            ->assertOk()
            ->assertSee('خالد العلي');

        // Archive search by patient name & phone
        $this->actingAs($this->secretary)
            ->get('/archive?search=خالد')
            ->assertOk();
    }

    public function test_slot_generator_respects_durations_recurrence_and_exceptions(): void
    {
        $targetDate = Carbon::today()->addDays(2);

        ProviderSchedule::create([
            'provider_id' => $this->provider->id,
            'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'slot_duration_minutes' => 60,
            'is_exception' => false,
        ]);

        $exceptionDate = Carbon::today()->addDays(9);
        // Same day of week, 1 week later, marked as day off
        ProviderSchedule::create([
            'provider_id' => $this->provider->id,
            'day_of_week' => $exceptionDate->dayOfWeek,
            'start_time' => '00:00:00',
            'end_time' => '00:00:00',
            'slot_duration_minutes' => 30,
            'is_exception' => true,
            'exception_date' => $exceptionDate->toDateString(),
        ]);

        $generator = app(SlotGeneratorService::class);
        $count = $generator->generateForProvider($this->provider, $targetDate, $exceptionDate);

        // 09:00-10:00 and 10:00-11:00 on targetDate (2 slots), 0 on exceptionDate
        $this->assertEquals(2, $count);
        $this->assertEquals(2, TimeSlot::where('provider_id', $this->provider->id)->whereDate('date', $targetDate)->count());
        $this->assertEquals(0, TimeSlot::where('provider_id', $this->provider->id)->whereDate('date', $exceptionDate)->count());
    }
}
