<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
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
        // Carbon locale → diffForHumans() trả "1 phút trước" thay vì "1 minute ago"
        Carbon::setLocale(config('app.locale', 'vi'));

        // Force scheme HTTPS khi APP_URL là https — tránh Mixed Content khi chạy sau reverse proxy.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Đăng ký route /broadcasting/auth cho private channel auth (Laravel 11/12 cần khai báo thủ công)
        Broadcast::routes(['middleware' => ['web', 'auth']]);

        // Blade directive @assetVer('css/app.css') — emit asset URL với cache-busting version
        // Dùng filemtime nếu file tồn tại (cache hợp lý — bust khi file đổi),
        // fallback time() (always bust) nếu file chưa có (vd vào trang trước khi build CSS).
        Blade::directive('assetVer', function ($expression) {
            return "<?php
                \$__path = {$expression};
                \$__full = public_path(\$__path);
                \$__ver  = file_exists(\$__full) ? filemtime(\$__full) : time();
                echo asset(\$__path) . '?v=' . \$__ver;
            ?>";
        });

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
