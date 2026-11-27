<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('slots:release-cooldowns')->everyMinute();
Schedule::command('slots:generate --days=30')->dailyAt('01:00');