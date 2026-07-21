<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:send-expiry-reminders')->dailyAt('00:00');
Schedule::command('subscriptions:send-expired-notices')->dailyAt('00:05');
Schedule::command('expenses:process-recurring')->dailyAt('00:00');
Schedule::command('messages:prune-attachments')->hourly();

Schedule::command('duels:expire-pending')->hourly();
Schedule::command('goals:daily-encouragement')->dailyAt('09:00');
Schedule::command('alerts:recheck-low-stock')->dailyAt('08:00');
