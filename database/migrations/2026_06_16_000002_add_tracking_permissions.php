<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Quyền cho tính năng Theo dõi xe (GPS):
 *  - tracking.view   : xem bản đồ + danh sách + lịch sử đến kho.
 *  - tracking.manage : ghim tọa độ kho + cấu hình kết nối GPS.
 * Gán: super_admin/admin = cả 2; editor/user = view (giữ nguyên quyền xem như trước,
 * khi các route này còn gate bằng shipments.view). givePermissionTo KHÔNG xóa quyền cũ.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['tracking.view', 'tracking.manage'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $give = function (string $role, array $perms) {
            $r = Role::where('name', $role)->where('guard_name', 'web')->first();
            if ($r) $r->givePermissionTo($perms);
        };
        $give('super_admin', ['tracking.view', 'tracking.manage']);
        $give('admin',       ['tracking.view', 'tracking.manage']);
        $give('editor',      ['tracking.view']);
        $give('user',        ['tracking.view']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', ['tracking.view', 'tracking.manage'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
