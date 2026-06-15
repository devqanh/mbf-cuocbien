<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link cứng khoản chi → lái xe: `driver_id` (resolve theo tên khi lưu). Giữ cột `driver`
 * (tên snapshot) để hiển thị/lịch sử; báo cáo lương lái xe gom theo driver_id cho chuẩn
 * (không lệch khi đổi tên trong danh mục).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_shipment_spends', function (Blueprint $table) {
            $table->unsignedBigInteger('driver_id')->nullable()->after('driver');
            $table->index(['driver_id', 'spend_date']);   // tổng hợp lương theo lái xe + kỳ
        });
    }

    public function down(): void
    {
        Schema::table('trucking_shipment_spends', function (Blueprint $table) {
            $table->dropIndex(['driver_id', 'spend_date']);
            $table->dropColumn('driver_id');
        });
    }
};
