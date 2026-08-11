<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use App\Console\Commands\SendReminderNotifications;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run three times daily: 9:00 AM, 2:00 PM (14:00), and 8:00 PM (20:00)
Schedule::command(SendReminderNotifications::class)->cron('0 9,14,20 * * *');
