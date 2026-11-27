<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Default Admin Account
        User::updateOrCreate(
            ['email' => 'admin@clinic.local'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('Admin@123456'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        // 2. Seed Default Clinic Settings
        $defaultSettings = [
            'clinic_name' => 'Al-Shifa Medical Clinic',
            'clinic_phone' => '+905550000000',
            'global_booking_enabled' => '1',
            'auto_ban_no_show_limit' => '3',
            'auto_ban_days' => '30',
            'cancellation_cooldown_minutes' => '10',
        ];

        foreach ($defaultSettings as $key => $value) {
            Setting::setValue($key, $value);
        }
        // 3. Seed Demo Specialties, Providers, Schedules & Slots
        $cardiology = \App\Models\Specialty::firstOrCreate(
            ['name' => 'القلبية والأوعية الدموية'],
            ['is_booking_enabled' => true]
        );

        $radiology = \App\Models\Specialty::firstOrCreate(
            ['name' => 'قسم الأشعة والتصوير'],
            ['is_booking_enabled' => true]
        );

        $doctor = \App\Models\Provider::firstOrCreate(
            ['name' => 'أحمد المنصور', 'specialty_id' => $cardiology->id],
            [
                'title' => 'د.',
                'type' => 'doctor',
                'is_recurring' => true,
                'is_active' => true,
            ]
        );

        // Weekly schedule: Everyday from 09:00 to 17:00 (30-minute slots)
        for ($day = 0; $day <= 6; $day++) {
            \App\Models\ProviderSchedule::firstOrCreate(
                [
                    'provider_id' => $doctor->id,
                    'day_of_week' => $day,
                    'is_exception' => false,
                ],
                [
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'slot_duration_minutes' => 30,
                ]
            );
        }

        // Generate slots for the next 7 days
        $generator = app(\App\Services\SlotGeneratorService::class);
        $generator->generateForProvider($doctor, now()->startOfDay(), now()->addDays(7));

        // Create a demo patient & book the first available slot
        $patient = \App\Models\Patient::firstOrCreate(
            ['phone_number' => '905559876543'],
            [
                'last_registered_name' => 'سامر العلي',
                'kvkk_accepted' => true,
            ]
        );

        $firstSlot = \App\Models\TimeSlot::where('provider_id', $doctor->id)
            ->where('status', 'available')
            ->whereDate('date', '>=', now()->addDay()->toDateString())
            ->first();

        if ($firstSlot && \App\Models\Appointment::count() === 0) {
            app(\App\Services\BookingService::class)->bookSlot(
                $patient->id,
                $firstSlot->id,
                'حجز تجريبي لاختبار الإكسل'
            );
        }
    }
}