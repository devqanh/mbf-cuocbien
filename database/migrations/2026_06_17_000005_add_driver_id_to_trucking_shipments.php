<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Link cứng `driver_id` cho lô (tương tự customer_id/vehicle_id). Giữ cột `driver`
 * (snapshot tên) để hiển thị/lịch sử; báo cáo theo lái xe dùng driver_id cho chuẩn
 * (không lệch khi đổi tên trong danh mục). Backfill theo tên hiện có.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('trucking_shipments', 'driver_id')) {
                $table->unsignedBigInteger('driver_id')->nullable()->after('driver');
            }
            if (! $this->indexExists('trucking_shipments', 'trucking_shipments_driver_id_index')) {
                $table->index('driver_id');
            }
        });

        // Backfill theo tên (lowercase trim) — không đặt FK constraint vì lịch sử có
        // thể tham chiếu driver đã xóa; cứ giữ NULL khi không match.
        $map = DB::table('trucking_drivers')->get(['id', 'name'])
            ->mapWithKeys(fn ($d) => [mb_strtolower(trim((string) $d->name)) => (int) $d->id])
            ->all();
        if ($map) {
            DB::table('trucking_shipments')->whereNull('driver_id')->whereNotNull('driver')
                ->orderBy('id')->chunkById(500, function ($rows) use ($map) {
                    foreach ($rows as $r) {
                        $k = mb_strtolower(trim((string) $r->driver));
                        if ($k !== '' && isset($map[$k])) {
                            DB::table('trucking_shipments')->where('id', $r->id)->update(['driver_id' => $map[$k]]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            if ($this->indexExists('trucking_shipments', 'trucking_shipments_driver_id_index')) {
                $table->dropIndex(['driver_id']);
            }
            if (Schema::hasColumn('trucking_shipments', 'driver_id')) {
                $table->dropColumn('driver_id');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$index]);
        return ! empty($rows);
    }
};
