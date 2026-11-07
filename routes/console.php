<?php

use App\Services\MaintenanceAlerts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('alerts:maintenance-digest', function (MaintenanceAlerts $alerts) {
    $notified = $alerts->sendDailyDigest();

    $this->info("Digest sent to {$notified} user(s).");
})->purpose('Send each user a push-notification summary of maintenance and faults');

// Runs every morning in the app's timezone (APP_TIMEZONE).
Schedule::command('alerts:maintenance-digest')->dailyAt('07:00');
