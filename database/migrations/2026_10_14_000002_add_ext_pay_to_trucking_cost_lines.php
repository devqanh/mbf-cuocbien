<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chi phí lô hàng: cờ "Chi cho xe ngoài" (ext_pay) từng khoản — khoản tích sẽ được kéo vào
 * Bảng kê xe ngoài (phải trả nhà xe). Trước đây bảng kê tự lấy theo "Chi hộ khách" (billable),
 * nên backfill: khoản chi hộ của lô thuê xe ngoài → tích sẵn ext_pay để không lô nào rơi khỏi bảng kê.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trucking_cost_lines')) return;
        if (Schema::hasColumn('trucking_cost_lines', 'ext_pay')) return;

        Schema::table('trucking_cost_lines', function (Blueprint $table) {
            $table->boolean('ext_pay')->default(false)->after('billable');
        });

        DB::table('trucking_cost_lines')
            ->where('billable', true)
            ->where(fn ($q) => $q->whereNull('src')->orWhere('src', '<>', 'extTruck'))
            ->whereIn('shipment_id', DB::table('trucking_shipments')->whereNotNull('ext_vendor')->where('ext_vendor', '<>', '')->select('id'))
            ->update(['ext_pay' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('trucking_cost_lines', 'ext_pay')) {
            Schema::table('trucking_cost_lines', fn (Blueprint $table) => $table->dropColumn('ext_pay'));
        }
    }
};
