<?php

use App\Console\Commands\NotifyTodayBookings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Notify both client and worker at midnight PHT (UTC+8 = 16:00 UTC previous day)
Schedule::command(NotifyTodayBookings::class)->dailyAt('00:00')->timezone('Asia/Manila');
