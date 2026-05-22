<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Đăng ký route /broadcasting/auth cho private channel auth (Laravel 11/12 cần khai báo thủ công)
        Broadcast::routes(['middleware' => ['web', 'auth']]);
    }
}
