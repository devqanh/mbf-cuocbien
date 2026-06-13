<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role'              => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'        => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission'=> \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        // Tin tưởng reverse proxy (Nginx/Cloudflare) → nhận đúng X-Forwarded-Proto
        // để route()/url() sinh URL https khi site chạy sau SSL termination.
        $middleware->trustProxies(at: '*', headers:
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB
        );

        // Chặn browser cache HTML pages — fix bug bấm Back về login (cached login HTML).
        $middleware->web(append: [
            \App\Http\Middleware\NoCacheHeaders::class,
        ]);

        // Gỡ header X-Socket-ID rỗng/sai định dạng trước khi toOthers() đọc nó,
        // tránh Pusher "Invalid socket ID" làm chết job broadcast. Prepend để chạy
        // trước controller (socket được capture lúc dispatch event trong request).
        $middleware->web(prepend: [
            \App\Http\Middleware\NormalizeSocketId::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Prune snapshot history cũ hơn 30 ngày, giữ tối thiểu 10 version gần nhất per key.
        // Chạy 2h sáng hàng ngày.
        $schedule->command('snapshots:prune --days=30 --keep=10')
            ->dailyAt('02:00')
            ->withoutOverlapping();

        // OPTIMIZE TABLE hàng tháng — reclaim InnoDB fragmentation từ overwrite BLOB.
        // Chạy 3h sáng ngày 1 hàng tháng.
        $schedule->command('snapshots:prune --days=30 --keep=10 --optimize')
            ->monthlyOn(1, '03:00')
            ->withoutOverlapping();

        // Reminder cho task tới hạn — chạy mỗi phút.
        // Cần crontab dòng: * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
        $schedule->command('tasks:remind')
            ->everyMinute()
            ->withoutOverlapping(5);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
