<?php

namespace App\Services;

use App\Models\Provider;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class SlotGeneratorService
{
    /**
     * Generate time slots for a specific provider over a date range.
     */
    public function generateForProvider(Provider $provider, Carbon $startDate, Carbon $endDate): int
    {
        if (!$provider->is_active) {
            return 0;
        }

        $schedules = $provider->schedules()->get();
        if ($schedules->isEmpty()) {
            return 0;
        }

        $period = CarbonPeriod::create($startDate->copy()->startOfDay(), $endDate->copy()->startOfDay());
        $slotsToInsert = [];
        $now = now();

        foreach ($period as $date) {
            $dateString = $date->toDateString();

            // 1. Check if there is an exception schedule for this specific date
            $dateExceptions = $schedules->where('is_exception', true)
                ->filter(fn ($s) => $s->exception_date && $s->exception_date->toDateString() === $dateString);

            if ($dateExceptions->isNotEmpty()) {
                $activeSchedules = $dateExceptions;
            } else {
                // 2. Otherwise, fall back to recurring weekly schedules if enabled
                if (!$provider->is_recurring) {
                    continue;
                }
                $dayOfWeek = $date->dayOfWeek; // 0 (Sunday) to 6 (Saturday)
                $activeSchedules = $schedules->where('is_exception', false)
                    ->where('day_of_week', $dayOfWeek);
            }

            foreach ($activeSchedules as $schedule) {
                // If start_time == end_time in an exception, it represents a full day off
                if ($schedule->start_time === $schedule->end_time || $schedule->slot_duration_minutes <= 0) {
                    continue;
                }

                $slotStart = Carbon::parse($dateString . ' ' . $schedule->start_time);
                $shiftEnd = Carbon::parse($dateString . ' ' . $schedule->end_time);
                $duration = $schedule->slot_duration_minutes;

                while ($slotStart->copy()->addMinutes($duration)->lte($shiftEnd)) {
                    $slotEnd = $slotStart->copy()->addMinutes($duration);

                    $slotsToInsert[] = [
                        'provider_id' => $provider->id,
                        'date' => $dateString,
                        'start_time' => $slotStart->format('H:i:s'),
                        'end_time' => $slotEnd->format('H:i:s'),
                        'status' => 'available',
                        'available_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $slotStart->addMinutes($duration);
                }
            }
        }

        if (empty($slotsToInsert)) {
            return 0;
        }

        // insertOrIgnore respects the unique(['provider_id', 'date', 'start_time']) constraint
        // so existing booked/cooldown slots are never overwritten.
        $insertedCount = 0;
        foreach (array_chunk($slotsToInsert, 500) as $chunk) {
            $insertedCount += TimeSlot::insertOrIgnore($chunk);
        }

        return $insertedCount;
    }

    /**
     * Generate slots for all active providers for the next N days.
     */
    public function generateForAllActiveProviders(int $daysAhead = 30): int
    {
        $startDate = now()->startOfDay();
        $endDate = now()->addDays($daysAhead)->endOfDay();
        $total = 0;

        Provider::where('is_active', true)->chunk(50, function ($providers) use ($startDate, $endDate, &$total) {
            foreach ($providers as $provider) {
                $total += $this->generateForProvider($provider, $startDate, $endDate);
            }
        });

        return $total;
    }
}