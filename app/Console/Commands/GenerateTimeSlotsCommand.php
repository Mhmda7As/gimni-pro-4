<?php

namespace App\Console\Commands;

use App\Services\SlotGeneratorService;
use Illuminate\Console\Command;

class GenerateTimeSlotsCommand extends Command
{
    protected $signature = 'slots:generate {--days=30 : Number of days ahead to generate}';
    protected $description = 'Generate future time slots for all active providers';

    public function handle(SlotGeneratorService $generator): int
    {
        $days = (int) $this->option('days');
        $count = $generator->generateForAllActiveProviders($days);

        $this->info("Successfully generated {$count} new time slots for the next {$days} days.");
        return self::SUCCESS;
    }
}