<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liên kết xe hệ thống ↔ xe GPS: gps_ref = "provider:deviceId" (vd "viettel:6adae1cc-…",
 * "dvbk:90003946"). Gán ở Cài đặt → Đội xe (chỉ xe MBF). Dùng để khớp vị trí GPS chắc chắn
 * hơn dò theo biển số → phục vụ tracking lô hàng (xe nào hoàn thành).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_vehicles', function (Blueprint $table) {
            $table->string('gps_ref')->nullable()->after('axle');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_vehicles', function (Blueprint $table) {
            $table->dropColumn('gps_ref');
        });
    }
};
