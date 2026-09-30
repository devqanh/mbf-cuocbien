<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tách 2 quyền tiền bạc khỏi quyền nghiệp vụ chung:
 * - driverPay.manage: chi cho lái xe + chốt ngày ở Lộ trình (trước dùng shipments.update).
 * - reports.view: Báo cáo chi phí (lãi lỗ) + Báo cáo tài sản (trước dùng tripCost.view).
 *
 * Dự án KHÔNG có Gate::before → quyền mới mà không cấp là MẤT NGAY. Cấp cho đúng các vai trò
 * đang có quyền cũ (+ Kế toán được thêm quyền chi cho lái) để deploy không ai mất tính năng;
 * admin vào /roles gỡ bớt (vd tắt chi cho lái của Chứng từ).
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'driverPay.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'reports.view', 'guard_name' => 'web']);

        // Đọc quyền qua quan hệ đã nạp (không dùng hasPermissionTo để khỏi ném lỗi nếu thiếu quyền gốc).
        foreach (Role::with('permissions')->where('guard_name', 'web')->get() as $r) {
            $has = $r->permissions->pluck('name');
            $full = in_array($r->name, ['super_admin', 'admin'], true);
            if ($full || $r->name === 'ke_toan' || $has->contains('shipments.update')) $r->givePermissionTo('driverPay.manage');
            if ($full || $has->contains('tripCost.view')) $r->givePermissionTo('reports.view');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', ['driverPay.manage', 'reports.view'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
