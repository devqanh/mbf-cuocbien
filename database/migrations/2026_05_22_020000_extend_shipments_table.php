<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Thêm cột mới
        Schema::table('shipments', function (Blueprint $t) {
            $t->string('period', 7)->after('id');                    // YYYY-MM
            $t->enum('direction', ['import', 'export'])->after('period');
            $t->string('container_type', 32)->nullable()->after('vol');
            $t->string('line', 64)->nullable()->after('vessel_name');

            $t->boolean('vgm')->default(false);
            $t->boolean('si')->default(false);
            $t->boolean('bl_draft')->default(false);
            $t->boolean('bl_confirm')->default(false);
            $t->boolean('obl')->default(false);
            $t->boolean('tlx')->default(false);
            $t->boolean('swb')->default(false);
            $t->boolean('shipment_done')->default(false);
        });

        // 2) Backfill data cũ vào tháng hiện tại + direction từ cột type cũ
        $currentPeriod = now()->format('Y-m');
        DB::statement("UPDATE shipments SET period = ?, direction = CASE WHEN type='IMPORT' THEN 'import' ELSE 'export' END", [$currentPeriod]);

        // 3) Drop cột `type` cũ (giá trị EXPORT/IMPORT đã chuyển sang `direction`).
        //    TYPE trong Excel của user = container type sẽ dùng cột mới `container_type`.
        Schema::table('shipments', function (Blueprint $t) {
            $t->dropColumn('type');
        });

        // 4) Thêm index sau khi backfill xong
        Schema::table('shipments', function (Blueprint $t) {
            $t->index(['period', 'direction']);
        });

        // 5) Xoá snapshot cũ — cấu trúc workbook sẽ khác hoàn toàn
        DB::table('sheet_snapshots')->where('key', 'like', 'shipments_grid%')->delete();
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $t) {
            $t->string('type', 32)->nullable();
        });
        DB::statement("UPDATE shipments SET type = UPPER(direction)");

        Schema::table('shipments', function (Blueprint $t) {
            $t->dropIndex(['period', 'direction']);
            $t->dropColumn([
                'period', 'direction', 'container_type', 'line',
                'vgm', 'si', 'bl_draft', 'bl_confirm', 'obl', 'tlx', 'swb', 'shipment_done',
            ]);
        });
    }
};
