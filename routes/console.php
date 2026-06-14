<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sao lưu MySQL hằng ngày lúc 02:00 — giữ tối đa 15 bản gần nhất.
// Cần cron chạy `php artisan schedule:run` mỗi phút trên server.
Schedule::command('db:backup')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();
