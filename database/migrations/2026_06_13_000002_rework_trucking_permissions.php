<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Làm lại phân quyền Trucking: tách shipments.* (gộp 4 trang) thành 4 nhóm theo tính năng
 * (Lô hàng / Bảng giá / Bảng kê / Cài đặt) và XÓA nhóm Báo cáo (reports.*).
 *
 * An toàn cho production: suy quyền granular MỚI từ quyền shipments cũ của TỪNG role
 * (giữ nguyên hành vi hiện tại), không đụng tới các role/quyền tùy biến khác.
 */
return new class extends Migration
{
    private array $newPerms = [
        'prices.view', 'prices.update',
        'statements.view', 'statements.create', 'statements.update', 'statements.delete',
        'settings.view', 'settings.update',
    ];

    public function up(): void
    {
        $guard = 'web';
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->newPerms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]);
        }

        // 4 role mặc định: gán ĐÚNG bộ quyền granular mới (theo thiết kế đã duyệt).
        $defaults = [
            'super_admin' => $this->newPerms,
            'admin'       => $this->newPerms,
            'editor'      => ['prices.view', 'statements.view', 'statements.create', 'statements.update', 'settings.view'],
            'user'        => ['prices.view', 'statements.view', 'settings.view'],
        ];

        foreach (Role::with('permissions')->get() as $role) {
            if (array_key_exists($role->name, $defaults)) {
                $want = $defaults[$role->name];
                if ($want) $role->givePermissionTo($want);
                $revoke = array_values(array_diff($this->newPerms, $want));   // gỡ quyền trucking-mới ngoài bộ
                if ($revoke) $role->revokePermissionTo($revoke);
            } else {
                // Role tùy biến: suy từ quyền shipments cũ → giữ nguyên hành vi hiện tại
                $has  = $role->permissions->pluck('name')->all();
                $give = [];
                if (in_array('shipments.view', $has, true))   $give = array_merge($give, ['prices.view', 'statements.view', 'settings.view']);
                if (in_array('shipments.create', $has, true)) $give[] = 'statements.create';
                if (in_array('shipments.update', $has, true)) $give = array_merge($give, ['prices.update', 'statements.update', 'settings.update']);
                if (in_array('shipments.delete', $has, true)) $give[] = 'statements.delete';
                if ($give) $role->givePermissionTo(array_values(array_unique($give)));
            }
        }

        // Xóa nhóm Báo cáo — tự gỡ khỏi mọi role/user qua FK pivot
        Permission::whereIn('name', ['reports.view', 'reports.create', 'reports.delete'])
            ->where('guard_name', $guard)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $guard = 'web';
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['reports.view', 'reports.create', 'reports.delete'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]);
        }
        Permission::whereIn('name', $this->newPerms)->where('guard_name', $guard)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
