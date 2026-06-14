<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Quyền "spend.request" — gửi yêu cầu chi (mobile). Cấp sẵn cho super_admin + admin. */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'spend.request', 'guard_name' => 'web']);
        foreach (['super_admin', 'admin'] as $r) {
            $role = Role::where('name', $r)->first();
            if ($role) $role->givePermissionTo('spend.request');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::where('name', 'spend.request')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
