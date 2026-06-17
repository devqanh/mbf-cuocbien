<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * MỘT LẦN DUY NHẤT (migration chỉ chạy 1 lần/DB): dọn bộ vai trò mặc định cũ.
 *
 * Bộ vai trò mới (super_admin, admin, ke_toan, chung_tu) được TẠO + GÁN QUYỀN
 * mặc định ở DatabaseSeeder (gán 1 lần, cờ sys.roles_initialized) để sau này admin
 * tự bật/tắt quyền ở /roles mà không bị deploy ghi đè.
 *
 * Migration này chỉ lo phần KHÔNG idempotent / mang tính phá hủy:
 *   - Bỏ 2 vai trò cũ 'editor' và 'user'.
 *   - User đang giữ 2 vai trò đó → chuyển sang 'chung_tu' để không mất vai trò.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Đảm bảo có 'chung_tu' làm đích chuyển (quyền do seeder gán)
        $chungTu = Role::firstOrCreate(['name' => 'chung_tu', 'guard_name' => 'web'], ['display_name' => 'Chứng từ']);
        Role::firstOrCreate(['name' => 'ke_toan', 'guard_name' => 'web'], ['display_name' => 'Kế toán']);

        foreach (['editor', 'user'] as $old) {
            $role = Role::where('name', $old)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }
            foreach ($role->users()->get() as $u) {
                $u->syncRoles([$chungTu->name]);
            }
            $role->delete();
        }

        // Cột legacy users.role (chỉ hiển thị fallback) — đồng bộ theo
        DB::table('users')->whereIn('role', ['editor', 'user'])->update(['role' => 'chung_tu']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Khôi phục 2 vai trò cũ (rỗng quyền) để rollback không mất chỗ tham chiếu
        Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web'], ['display_name' => 'Biên tập viên']);
        Role::firstOrCreate(['name' => 'user',   'guard_name' => 'web'], ['display_name' => 'Nhân viên xem']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
