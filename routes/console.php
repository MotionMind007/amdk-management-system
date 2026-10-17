<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('finance:send-due-reminders')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('system:prune-data-imports --hours=24')->hourly()->withoutOverlapping();
Schedule::command('system:backup-database --verify --retention-days=30')
    ->dailyAt('01:00')
    ->environments(['production'])
    ->onOneServer()
    ->withoutOverlapping(180);
