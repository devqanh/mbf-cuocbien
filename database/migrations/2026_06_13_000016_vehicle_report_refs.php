<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tham chiếu/số liệu báo cáo cho Quản lý xe:
 *  - vehicle_usages.driver_id  : khóa lái xe (gán theo tên) → báo cáo lái xe theo xe/kỳ bền.
 *  - vehicle_depreciations.monthly_amount/daily_amount : chốt khấu hao (orig_price/months) → báo cáo khấu hao theo tháng = SUM.
 *  - danh mục trucking_vehicle_cost_types + vehicle_costs.cost_type_id : nhóm chi phí bảo dưỡng theo loại chuẩn.
 * Giữ nguyên cột chuỗi (driver/name) — id là khóa join thêm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_vehicle_usages', function (Blueprint $table) {
            $table->unsignedBigInteger('driver_id')->nullable()->after('driver')->index();
        });
        Schema::table('trucking_vehicle_depreciations', function (Blueprint $table) {
            $table->decimal('monthly_amount', 15, 2)->default(0)->after('months');  // khấu hao / tháng
            $table->decimal('daily_amount', 15, 4)->default(0)->after('monthly_amount'); // khấu hao / ngày
        });
        Schema::create('trucking_vehicle_cost_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
        Schema::table('trucking_vehicle_costs', function (Blueprint $table) {
            $table->unsignedBigInteger('cost_type_id')->nullable()->after('name')->index();
        });

        foreach (['Bảo dưỡng định kỳ', 'Sửa chữa', 'Thay lốp/vỏ', 'Đăng kiểm', 'Bảo hiểm', 'Phí đường/vé tháng', 'Dầu nhớt', 'Phụ tùng', 'Khác'] as $i => $name) {
            DB::table('trucking_vehicle_cost_types')->insert(['name' => $name, 'sort' => $i, 'created_at' => now(), 'updated_at' => now()]);
        }

        // Backfill
        $driverId = DB::table('trucking_drivers')->pluck('id', 'name');
        foreach (DB::table('trucking_vehicle_usages')->whereNotNull('driver')->get() as $u) {
            if (isset($driverId[$u->driver])) DB::table('trucking_vehicle_usages')->where('id', $u->id)->update(['driver_id' => $driverId[$u->driver]]);
        }
        $typeId = DB::table('trucking_vehicle_cost_types')->pluck('id', 'name');
        foreach (DB::table('trucking_vehicle_costs')->whereNotNull('name')->get() as $c) {
            if (isset($typeId[$c->name])) DB::table('trucking_vehicle_costs')->where('id', $c->id)->update(['cost_type_id' => $typeId[$c->name]]);
        }
        foreach (DB::table('trucking_vehicle_depreciations')->get() as $d) {
            $m = (int) $d->months;
            DB::table('trucking_vehicle_depreciations')->where('id', $d->id)->update([
                'monthly_amount' => $m > 0 ? round((float) $d->orig_price / $m, 2) : 0,
                'daily_amount'   => $m > 0 ? round((float) $d->orig_price / (30 * $m), 4) : 0,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('trucking_vehicle_costs', fn (Blueprint $t) => $t->dropColumn('cost_type_id'));
        Schema::dropIfExists('trucking_vehicle_cost_types');
        Schema::table('trucking_vehicle_depreciations', fn (Blueprint $t) => $t->dropColumn(['monthly_amount', 'daily_amount']));
        Schema::table('trucking_vehicle_usages', fn (Blueprint $t) => $t->dropColumn('driver_id'));
    }
};
