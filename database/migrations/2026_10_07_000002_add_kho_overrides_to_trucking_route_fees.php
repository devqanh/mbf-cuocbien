<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phí tuyến — CHI TIẾT THEO KHO trong 1 tuyến (thường là tuyến theo tỉnh "HPP → Hải Phòng → HPP" gom nhiều kho):
 * kho_overrides = JSON [{kho (tên kho), km, dau2, dau1}] — số null = theo tuyến. Chuyến có kho khai ở đây
 * dùng km / lít dầu 2 cầu / 1 cầu riêng của kho đó thay vì số chung của tuyến. Các khoản khác vẫn theo tuyến.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_route_fees', function (Blueprint $table) {
            $table->json('kho_overrides')->nullable()->after('extra_fees');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_route_fees', function (Blueprint $table) {
            $table->dropColumn('kho_overrides');
        });
    }
};
