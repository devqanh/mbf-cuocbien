<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Quyền cho module Bảng kê xe ngoài (phải trả nhà xe thuê).
 * Dự án KHÔNG có Gate::before → quyền mới phải gán tường minh cho role.
 * Gán theo nghiệp vụ payable (giống bảng kê khách): super_admin + admin (toàn quyền),
 * ke_toan (mảng tài chính: tạo/sửa, không xóa). Admin có thể tinh chỉnh ở /roles.
 */
return new class extends Migration
{
    private array $perms = [
        'extStatements.view', 'extStatements.create', 'extStatements.update', 'extStatements.delete',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->perms as $p) Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);

        $give = function (string $role, array $p) {
            $r = Role::where('name', $role)->where('guard_name', 'web')->first();
            if ($r) $r->givePermissionTo($p);
        };

        $give('super_admin', $this->perms);
        $give('admin', $this->perms);
        $give('ke_toan', ['extStatements.view', 'extStatements.create', 'extStatements.update']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->perms)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
