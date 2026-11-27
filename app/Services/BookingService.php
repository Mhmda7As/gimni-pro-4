<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Setting;
use App\Models\TimeSlot;
use DomainException;
use Illuminate\Support\Facades\DB;

class BookingService
{
    /**
     * Atomically book a time slot for a patient with row-level locking.
     */
    public function bookSlot(
        int $patientId,
        int $timeSlotId,
        ?string $notes = null,
        ?int $actorUserId = null
    ): Appointment {
        // 1. Check global booking switch
        if (Setting::getValue('global_booking_enabled', '1') !== '1') {
            throw new DomainException('Booking is currently disabled globally.');
        }

        return DB::transaction(function () use ($patientId, $timeSlotId, $notes, $actorUserId) {
            $patient = Patient::lockForUpdate()->findOrFail($patientId);

            // 2. Verify patient ban & KVKK status
            if ($patient->isCurrentlyBanned()) {
                throw new DomainException('Patient is currently banned from booking.');
            }

            if (!$patient->kvkk_accepted) {
                throw new DomainException('KVKK consent must be accepted before booking.');
            }

            // 3. Lock the TimeSlot row to prevent double-booking under high concurrency
            $slot = TimeSlot::with('provider.specialty')
                ->lockForUpdate()
                ->findOrFail($timeSlotId);

            if (!$slot->provider || !$slot->provider->is_active) {
                throw new DomainException('Selected provider is inactive.');
            }

            if (!$slot->provider->specialty || !$slot->provider->specialty->is_booking_enabled) {
                throw new DomainException('Booking is disabled for this specialty.');
            }

            // 4. Validate slot availability (either 'available' or expired 'cooldown')
            $isAvailable = $slot->status === 'available';
            $isCooldownExpired = $slot->status === 'cooldown'
                && $slot->available_at !== null
                && $slot->available_at->lte(now());

            if (!$isAvailable && !$isCooldownExpired) {
                throw new DomainException('This time slot is no longer available.');
            }

            // 5. Prevent booking past slots
            $slotDateTime = $slot->date->copy()->setTimeFromTimeString($slot->start_time);
            if ($slotDateTime->isPast()) {
                throw new DomainException('Cannot book a time slot in the past.');
            }

            // 6. Update slot to booked
            $slot->update([
                'status' => 'booked',
                'available_at' => null,
            ]);

            // 7. Create appointment record
            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'provider_id' => $slot->provider_id,
                'time_slot_id' => $slot->id,
                'appointment_date' => $slot->date->toDateString(),
                'start_time' => $slot->start_time,
                'status' => 'scheduled',
                'payment_amount' => 0.00,
                'is_archived' => false,
                'notes' => $notes,
            ]);

            AuditLoggerService::log(
                'appointment_booked',
                $appointment,
                null,
                $appointment->toArray(),
                $actorUserId
            );

            return $appointment;
        });
    }

    /**
     * Cancel an appointment and place its time slot into a 10-minute cooldown.
     */
    public function cancelAppointment(
        int $appointmentId,
        ?string $reason = null,
        ?int $actorUserId = null
    ): Appointment {
        return DB::transaction(function () use ($appointmentId, $reason, $actorUserId) {
            $appointment = Appointment::lockForUpdate()->findOrFail($appointmentId);

            if ($appointment->appointment_date->lt(now()->startOfDay())) {
                throw new DomainException('Cannot cancel a past-date appointment.');
            }

            if ($appointment->status !== 'scheduled') {
                throw new DomainException('Only scheduled appointments can be cancelled.');
            }

            $oldValues = $appointment->toArray();
            $cooldownMinutes = (int) Setting::getValue('cancellation_cooldown_minutes', 10);

            $appointment->update([
                'status' => 'cancelled',
                'notes' => $reason ? trim(($appointment->notes ? $appointment->notes . ' | ' : '') . "Cancel reason: {$reason}") : $appointment->notes,
            ]);

            if ($appointment->time_slot_id) {
                $slot = TimeSlot::lockForUpdate()->find($appointment->time_slot_id);
                if ($slot) {
                    $slot->update([
                        'status' => 'cooldown',
                        'available_at' => now()->addMinutes($cooldownMinutes),
                    ]);
                }
            }

            AuditLoggerService::log(
                'appointment_cancelled',
                $appointment,
                $oldValues,
                $appointment->fresh()->toArray(),
                $actorUserId
            );

            return $appointment;
        });
    }

    /**
     * Mark an appointment as no-show and enforce automatic ban threshold.
     */
    public function markAsNoShow(int $appointmentId, ?int $actorUserId = null): Appointment
    {
        return DB::transaction(function () use ($appointmentId, $actorUserId) {
            $appointment = Appointment::lockForUpdate()->findOrFail($appointmentId);

            if ($appointment->appointment_date->lt(now()->startOfDay())) {
                throw new DomainException('Cannot cancel a past-date appointment.');
            }

            if ($appointment->status !== 'scheduled') {
                throw new DomainException('Only scheduled appointments can be marked as no-show.');
            }

            $oldValues = $appointment->toArray();

            $appointment->update([
                'status' => 'no_show',
            ]);

            $patient = Patient::lockForUpdate()->findOrFail($appointment->patient_id);
            $newNoShowCount = $patient->no_show_count + 1;

            $banLimit = (int) Setting::getValue('auto_ban_no_show_limit', 3);
            $banDays = (int) Setting::getValue('auto_ban_days', 30);

            $patientUpdate = [
                'no_show_count' => $newNoShowCount,
            ];

            if ($newNoShowCount >= $banLimit && !$patient->isCurrentlyBanned()) {
                $patientUpdate['is_banned'] = true;
                $patientUpdate['banned_until'] = $banDays > 0 ? now()->addDays($banDays) : null;
            }

            $patient->update($patientUpdate);

            AuditLoggerService::log(
                'appointment_no_show',
                $appointment,
                $oldValues,
                [
                    'appointment' => $appointment->fresh()->toArray(),
                    'patient_no_show_count' => $patient->no_show_count,
                    'patient_is_banned' => $patient->is_banned,
                    'patient_banned_until' => $patient->banned_until?->toDateTimeString(),
                ],
                $actorUserId
            );

            return $appointment;
        });
    }
}