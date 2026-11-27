<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\Provider;
use App\Models\Specialty;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class RoleAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $secretary;
    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@clinic.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->secretary = User::create([
            'name' => 'Secretary User',
            'email' => 'secretary@clinic.test',
            'password' => Hash::make('password123'),
            'role' => 'secretary',
            'is_active' => true,
        ]);

        $this->accountant = User::create([
            'name' => 'Accountant User',
            'email' => 'accountant@clinic.test',
            'password' => Hash::make('password123'),
            'role' => 'accountant',
            'is_active' => true,
        ]);
    }

    public function test_secretary_can_access_desk_but_not_finance_or_admin(): void
    {
        $this->actingAs($this->secretary)
            ->get('/daily-desk')
            ->assertOk();

        $this->actingAs($this->secretary)
            ->get('/archive')
            ->assertOk();

        $this->actingAs($this->secretary)
            ->get('/clinic-setup')
            ->assertOk();

        $this->actingAs($this->secretary)
            ->get('/schedules')
            ->assertOk();

        $this->actingAs($this->secretary)
            ->get('/finance')
            ->assertForbidden();

        $this->actingAs($this->secretary)
            ->get('/finance/export')
            ->assertForbidden();

        $this->actingAs($this->secretary)
            ->get('/admin/settings')
            ->assertForbidden();

        $this->actingAs($this->secretary)
            ->get('/admin/audit-logs')
            ->assertForbidden();
    }

    public function test_accountant_can_access_finance_but_not_desk_setup_or_admin(): void
    {
        $this->actingAs($this->accountant)
            ->get('/finance')
            ->assertOk();

        $this->actingAs($this->accountant)
            ->get('/daily-desk')
            ->assertForbidden();

        $this->actingAs($this->accountant)
            ->post('/daily-desk/quick-book', [])
            ->assertForbidden();

        $this->actingAs($this->accountant)
            ->get('/clinic-setup')
            ->assertForbidden();

        $this->actingAs($this->accountant)
            ->get('/admin/settings')
            ->assertForbidden();
    }

    public function test_admin_can_access_all_modules(): void
    {
        $this->actingAs($this->admin)->get('/daily-desk')->assertOk();
        $this->actingAs($this->admin)->get('/finance')->assertOk();
        $this->actingAs($this->admin)->get('/admin/settings')->assertOk();
        $this->actingAs($this->admin)->get('/admin/audit-logs')->assertOk();
    }

    public function test_inactive_user_cannot_login_or_access_protected_routes(): void
    {
        $this->secretary->update(['is_active' => false]);

        $this->post('/login', [
            'email' => 'secretary@clinic.test',
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->actingAs($this->secretary)
            ->get('/daily-desk')
            ->assertRedirect(route('login'));
    }

    public function test_sanctum_api_role_boundaries(): void
    {
        $secToken = $this->secretary->createToken('test')->plainTextToken;
        $accToken = $this->accountant->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $secToken)
            ->getJson('/api/excel/appointments/daily')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer ' . $secToken)
            ->getJson('/api/excel/finance')
            ->assertForbidden();

        $this->withHeader('Authorization', 'Bearer ' . $secToken)
            ->getJson('/api/excel/dashboard/stats')
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer ' . $accToken)
            ->getJson('/api/excel/finance')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer ' . $accToken)
            ->getJson('/api/excel/appointments/daily')
            ->assertForbidden();
    }

    public function test_finance_masks_patient_names_and_phones_on_web_export_and_api(): void
    {
        $specialty = Specialty::create(['name' => 'Cardiology', 'is_booking_enabled' => true]);
        $provider = Provider::create([
            'specialty_id' => $specialty->id,
            'name' => 'Dr. Ali',
            'type' => 'doctor',
            'is_recurring' => true,
            'is_active' => true,
        ]);
        $patient = Patient::create([
            'phone_number' => '905559876543',
            'last_registered_name' => 'محمد الأحمد',
            'kvkk_accepted' => true,
        ]);
        $slot = TimeSlot::create([
            'provider_id' => $provider->id,
            'date' => now()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => 'booked',
        ]);
        Appointment::create([
            'patient_id' => $patient->id,
            'provider_id' => $provider->id,
            'time_slot_id' => $slot->id,
            'appointment_date' => now()->toDateString(),
            'start_time' => '10:00:00',
            'status' => 'attended',
            'payment_amount' => 500.00,
        ]);

        // Web Finance view must NOT contain raw name or raw phone
        $webResponse = $this->actingAs($this->accountant)->get('/finance');
        $webResponse->assertOk();
        $webResponse->assertDontSee('محمد الأحمد');
        $webResponse->assertDontSee('905559876543');
        $webResponse->assertSee('مح***');
        $webResponse->assertSee('******6543');

        // CSV Export must NOT contain raw name or raw phone
        $exportResponse = $this->actingAs($this->accountant)->get('/finance/export');
        $exportResponse->assertOk();
        $csv = $exportResponse->streamedContent();
        $this->assertStringNotContainsString('محمد الأحمد', $csv);
        $this->assertStringNotContainsString('905559876543', $csv);
        $this->assertStringContainsString('مح***', $csv);
        $this->assertStringContainsString('******6543', $csv);

        // API Finance must NOT contain raw name or raw phone
        $accToken = $this->accountant->createToken('excel')->plainTextToken;
        $apiResponse = $this->withHeader('Authorization', 'Bearer ' . $accToken)
            ->getJson('/api/excel/finance');
        $apiResponse->assertOk();
        $json = $apiResponse->getContent();
        $this->assertStringNotContainsString('محمد الأحمد', $json);
        $this->assertStringNotContainsString('905559876543', $json);
    }

    public function test_audit_logs_are_immutable(): void
    {
        $log = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'test_action',
            'model_type' => User::class,
            'model_id' => $this->admin->id,
            'new_values' => ['key' => 'value'],
            'created_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_audit_logs_cannot_be_deleted(): void
    {
        $log = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'test_action',
            'model_type' => User::class,
            'model_id' => $this->admin->id,
            'created_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $log->delete();
    }
}
