<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill chuẩn hóa name (trim + collapse whitespace) cho 4 danh mục:
 * trucking_customers / trucking_drivers / trucking_locations / trucking_warehouses.
 *
 * Cần thiết vì observer chỉ chạy khi save; record cũ trong DB có thể còn "Cty  ABC"
 * (double-space) — sẽ KHÔNG match với payload đã chuẩn hóa từ FE → reconcileCustomers
 * có thể xóa nhầm. Migration này chỉ chạy UPDATE, idempotent (chạy lại không ảnh hưởng).
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = ['trucking_customers', 'trucking_drivers', 'trucking_locations', 'trucking_warehouses'];
        foreach ($tables as $t) {
            if (! Schema::hasTable($t) || ! Schema::hasColumn($t, 'name')) continue;
            // Chunk theo id để tránh nuốt RAM trên bảng lớn (50k+ row).
            DB::table($t)->select(['id', 'name'])->orderBy('id')->chunkById(1000, function ($rows) use ($t) {
                foreach ($rows as $r) {
                    if ($r->name === null) continue;
                    $clean = preg_replace('/\s+/u', ' ', trim((string) $r->name)) ?? '';
                    if ($clean !== $r->name) {
                        DB::table($t)->where('id', $r->id)->update(['name' => $clean]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Không revert (chuẩn hóa whitespace là cải thiện chất lượng dữ liệu, không có path ngược).
    }
};
