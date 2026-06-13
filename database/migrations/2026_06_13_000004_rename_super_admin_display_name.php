<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Đổi tên hiển thị vai trò super_admin: "Quản trị tối cao" → "Quản trị toàn quyền".
 * Chạy trên cả local & VPS qua `php artisan migrate` (cập nhật cột roles.display_name).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->where('name', 'super_admin')
            ->update(['display_name' => 'Quản trị toàn quyền']);
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'super_admin')
            ->update(['display_name' => 'Quản trị tối cao']);
    }
};
