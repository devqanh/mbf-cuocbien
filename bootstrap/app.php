<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
