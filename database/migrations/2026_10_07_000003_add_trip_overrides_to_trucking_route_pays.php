<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lộ trình — CHỈNH TAY THEO TỪNG CHUYẾN của 1 xe / ngày: trip_overrides = JSON {cont: {liters?, choho?}}.
 *  - liters: lít dầu sửa tay (vd 12 → 12,4), ghi đè định mức phí tuyến; tiền dầu = lít × giá dầu theo ngày.
 *  - choho:  khóa các khoản phí tuyến chi trong ngày được chọn "Chi hộ" (vẫn chi cho lái nhưng KHÔNG tính vào lương).
 * Không có = theo phí tuyến.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_route_pays', function (Blueprint $table) {
            $table->json('trip_overrides')->nullable()->after('extra_items');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_route_pays', function (Blueprint $table) {
            $table->dropColumn('trip_overrides');
        });
    }
};
