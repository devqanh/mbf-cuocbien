<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Quyền XEM TỪNG CỘT của bảng Lô hàng (/trucking-v2/lo-hang) — ẩn cột theo vai trò.
 * Cột Khách hàng + Cont không có quyền: ẩn đi thì bảng vô nghĩa.
 *
 * Dự án KHÔNG có Gate::before → quyền mới mà không cấp là MẤT NGAY. Để deploy không làm ai
 * mất cột đang thấy, cấp đủ cho mọi vai trò hiện đang xem được lô hàng; admin vào /roles gỡ bớt.
 */
return new class extends Migration
{
    private array $perms = [
        'shipments.view_id',
        'shipments.view_customs',
        'shipments.view_route',
        'shipments.view_plate',
        'shipments.view_schedule',
        'shipments.view_cost',
        'shipments.view_revenue',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->perms as $p) Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);

        // Đọc quyền qua quan hệ đã nạp (không dùng hasPermissionTo để khỏi ném lỗi nếu thiếu quyền gốc).
        Role::with('permissions')->where('guard_name', 'web')->get()
            ->filter(fn ($r) => in_array($r->name, ['super_admin', 'admin'], true)
                || $r->permissions->pluck('name')->contains('shipments.view'))
            ->each(fn ($r) => $r->givePermissionTo($this->perms));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->perms)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
