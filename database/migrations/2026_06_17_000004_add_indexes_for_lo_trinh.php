<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Index phục vụ routeTripByDate (trang Lộ trình):
 *  - gio_xe_ra_xe: ra_mode='none' lọc theo giờ XE ra (OR cùng gio_xe_ra).
 *  - ra_mode: bộ ứng viên "kéo cont khác ra" (ra_mode='other') khi data lớn.
 * Idempotent: kiểm tra trước khi thêm để re-run an toàn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            if (! $this->indexExists('trucking_shipments', 'trucking_shipments_gio_xe_ra_xe_index')) {
                $table->index('gio_xe_ra_xe');
            }
            if (! $this->indexExists('trucking_shipments', 'trucking_shipments_ra_mode_index')) {
                $table->index('ra_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            if ($this->indexExists('trucking_shipments', 'trucking_shipments_gio_xe_ra_xe_index')) {
                $table->dropIndex(['gio_xe_ra_xe']);
            }
            if ($this->indexExists('trucking_shipments', 'trucking_shipments_ra_mode_index')) {
                $table->dropIndex(['ra_mode']);
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$index]);
        return ! empty($rows);
    }
};
