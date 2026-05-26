<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily midnight: generate next instances for all due recurring tasks
Schedule::command('recurring-tasks:generate')->dailyAt('00:00');

// Daily 8am: check due-date automation triggers
Schedule::command('automations:check-due-dates')->dailyAt('08:00');

// Hourly: send digest emails to users whose preferred hour matches
Schedule::command('digest:send')->hourly();
