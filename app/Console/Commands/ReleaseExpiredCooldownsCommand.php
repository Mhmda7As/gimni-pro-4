<?php

namespace App\Console\Commands;

use App\Models\TimeSlot;
use Illuminate\Console\Command;

class ReleaseExpiredCooldownsCommand extends Command
{
    protected $signature = 'slots:release-cooldowns';
    protected $description = 'Transition time slots with expired cooldowns back to available status';

    public function handle(): int
    {
        $released = TimeSlot::where('status', 'cooldown')
            ->whereNotNull('available_at')
            ->where('available_at', '<=', now())
            ->update([
                'status' => 'available',
                'available_at' => null,
            ]);

        $this->info("Released {$released} cooldown time slots.");
        return self::SUCCESS;
    }
}