<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cột BÁO CÁO chốt sẵn trên từng dòng phí xe (snapshot lúc lưu) → query báo cáo bằng SQL thuần:
 *   - salary_total : lương nhân sự dòng (tổng khoản đánh dấu + khoản lương khác; luong gated theo cru)
 *   - cost_total   : chi phí vận hành dòng (= line_total − salary_total)
 *   - fuel_amount  : tiền dầu (lít × đơn giá)
 *   - driver_id / vehicle_id : khóa master (best-effort theo tên/biển số) để group bền vững
 * Kèm index theo ngày + lái xe + xe để báo cáo theo kỳ nhanh.
 */
return new class extends Migration
{
    private const COL = ['veTram' => 've_tram', 'tienDuong' => 'tien_duong', 'troCap' => 'tro_cap', 'phiKhac' => 'phi_khac', 'luong' => 'luong'];

    public function up(): void
    {
        Schema::table('trucking_trip_cost_lines', function (Blueprint $table) {
            $table->decimal('salary_total', 15, 2)->default(0)->after('line_total');
            $table->decimal('cost_total', 15, 2)->default(0)->after('salary_total');
            $table->decimal('fuel_amount', 15, 2)->default(0)->after('cost_total');
            $table->unsignedBigInteger('driver_id')->nullable()->after('driver');
            $table->unsignedBigInteger('vehicle_id')->nullable()->after('bks');
            $table->index('date');
            $table->index('driver_id');
            $table->index('vehicle_id');
        });

        // Backfill từ dữ liệu hiện có
        $driverIds = DB::table('trucking_drivers')->pluck('id', 'name');
        $vehIds    = DB::table('trucking_vehicles')->pluck('id', 'plate');
        foreach (DB::table('trucking_trip_cost_lines')->get() as $l) {
            $parts = json_decode($l->salary_parts ?? '[]', true) ?: [];
            $cru   = (bool) $l->cru;
            $salary = 0;
            foreach ($parts as $k) {
                $col = self::COL[$k] ?? null;
                if (! $col) continue;
                $v = (float) ($l->$col ?? 0);
                $salary += ($k === 'luong' && ! $cru) ? 0 : $v;
            }
            foreach ((json_decode($l->salary_extras ?? '[]', true) ?: []) as $e) {
                $salary += (float) ($e['amount'] ?? 0);
            }
            $fuel = round((float) $l->fuel_liters * (float) $l->fuel_price);
            DB::table('trucking_trip_cost_lines')->where('id', $l->id)->update([
                'salary_total' => $salary,
                'fuel_amount'  => $fuel,
                'cost_total'   => (float) $l->line_total - $salary,
                'driver_id'    => $driverIds[$l->driver] ?? null,
                'vehicle_id'   => $vehIds[$l->bks] ?? null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('trucking_trip_cost_lines', function (Blueprint $table) {
            $table->dropIndex(['date']);
            $table->dropIndex(['driver_id']);
            $table->dropIndex(['vehicle_id']);
            $table->dropColumn(['salary_total', 'cost_total', 'fuel_amount', 'driver_id', 'vehicle_id']);
        });
    }
};
