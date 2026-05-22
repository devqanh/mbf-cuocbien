<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Ngưỡng cảnh báo slow query (ms). Set qua APP_SLOW_QUERY_MS env. */
    private const SLOW_QUERY_THRESHOLD_MS = 500;

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Đăng ký route /broadcasting/auth cho private channel auth (Laravel 11/12 cần khai báo thủ công)
        Broadcast::routes(['middleware' => ['web', 'auth']]);

        // 4.6 — Metrics/telemetry: log slow query để dễ debug perf.
        // Chỉ bật ngoài production để tránh log spam; production bật riêng qua env nếu cần.
        $threshold = (int) env('APP_SLOW_QUERY_MS', self::SLOW_QUERY_THRESHOLD_MS);
        if ($threshold > 0 && ! app()->isProduction()) {
            DB::listen(function ($query) use ($threshold) {
                if ($query->time >= $threshold) {
                    Log::channel('single')->warning('SLOW QUERY', [
                        'time_ms' => $query->time,
                        'sql'     => $query->sql,
                        'bindings'=> $query->bindings,
                    ]);
                }
            });
        }
    }
}
