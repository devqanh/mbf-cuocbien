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

// Quét GPS ghi lịch sử xe đến/rời kho — mỗi 5 phút (cần cron schedule:run mỗi phút).
// runInBackground: command gọi HTTP provider (có thể chậm) → chạy nền, KHÔNG chặn các task khác trong tick.
// withoutOverlapping(10): hạn khóa 10' (mặc định 24h) → nếu 1 lần treo thì tự nhả, không kẹt lịch.
Schedule::command('trucking:scan-warehouse-visits')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();
