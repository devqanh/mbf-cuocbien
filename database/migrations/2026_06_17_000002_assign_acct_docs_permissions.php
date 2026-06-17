<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gán quyền cho 2 vai trò Kế toán (ke_toan) & Chứng từ (chung_tu).
 *
 * Trên VPS 2 vai trò này đang RỖNG quyền (seed-if-empty chưa ăn vì cache/chưa pull).
 * Migration chạy 1 lần khi `php artisan migrate` nên gán dứt điểm, không phụ thuộc seeder.
 * Dùng syncPermissions để set ĐÚNG bộ quyền dự kiến (đang rỗng nên không lo mất chỉnh tay).
 */
return new class extends Migration
{
    private array $keToan = [
        'dashboard.view',
        'prices.view', 'prices.update',
        'statements.view', 'statements.create', 'statements.update', 'statements.delete',
        'tripCost.view', 'tripCost.create', 'tripCost.update', 'tripCost.delete',
        'spend.request',
        'fleet.view', 'fleet.manage',
    ];

    private array $chungTu = [
        'dashboard.view',
        'shipments.view', 'shipments.create', 'shipments.update', 'shipments.delete',
        'tracking.view', 'tracking.manage',
        'settings.view',
        'tasks.view', 'tasks.create', 'tasks.update',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Đảm bảo đủ permission (an toàn nếu DB thiếu)
        foreach (array_unique(array_merge($this->keToan, $this->chungTu)) as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // Đảm bảo 2 vai trò tồn tại
        $keToan  = Role::firstOrCreate(['name' => 'ke_toan',  'guard_name' => 'web'], ['display_name' => 'Kế toán']);
        $chungTu = Role::firstOrCreate(['name' => 'chung_tu', 'guard_name' => 'web'], ['display_name' => 'Chứng từ']);

        $keToan->syncPermissions($this->keToan);
        $chungTu->syncPermissions($this->chungTu);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['ke_toan', 'chung_tu'] as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            if ($role) {
                $role->syncPermissions([]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
