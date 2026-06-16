<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tách quyền riêng cho Phí xe (tripCost.*), Quản lý tài sản/đội xe (fleet.*) và Tasks (update/delete).
 * GÁN giữ nguyên quyền HIỆU LỰC hiện tại (trước đây các route gate bằng shipments / settings / tasks.create):
 *   tripCost.view  = ai có shipments.view   → super/admin/editor/user
 *   tripCost.create/update = shipments.create/update → super/admin/editor
 *   tripCost.delete = shipments.delete      → super/admin
 *   fleet.view = settings.view              → super/admin/editor/user
 *   fleet.manage = settings.update          → super/admin
 *   tasks.update/delete = tasks.create      → super/admin/editor/user
 * givePermissionTo KHÔNG xóa quyền cũ. Admin có thể tinh chỉnh thêm ở /roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $perms = [
            'tripCost.view', 'tripCost.create', 'tripCost.update', 'tripCost.delete',
            'fleet.view', 'fleet.manage',
            'tasks.update', 'tasks.delete',
        ];
        foreach ($perms as $p) Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);

        $give = function (string $role, array $p) {
            $r = Role::where('name', $role)->where('guard_name', 'web')->first();
            if ($r) $r->givePermissionTo($p);
        };

        $give('super_admin', $perms);
        $give('admin', ['tripCost.view', 'tripCost.create', 'tripCost.update', 'tripCost.delete', 'fleet.view', 'fleet.manage', 'tasks.update', 'tasks.delete']);
        $give('editor', ['tripCost.view', 'tripCost.create', 'tripCost.update', 'fleet.view', 'tasks.update', 'tasks.delete']);
        $give('user', ['tripCost.view', 'fleet.view', 'tasks.update', 'tasks.delete']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'tripCost.view', 'tripCost.create', 'tripCost.update', 'tripCost.delete',
            'fleet.view', 'fleet.manage', 'tasks.update', 'tasks.delete',
        ])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
