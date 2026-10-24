<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled billing and router jobs
|--------------------------------------------------------------------------
|
| Every job runs through the cron-driven scheduler, so no long-lived queue
| worker is required on shared hosting.
|
*/

Schedule::command('billing:generate-invoices')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('billing:suspend-due')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('billing:mark-overdue')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('mikrotik:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
