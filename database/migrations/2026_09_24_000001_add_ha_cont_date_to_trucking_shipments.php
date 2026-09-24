<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lô hàng — "Hạ cont": ha_cont_date = ngày cont đã hạ tại nơi hạ. Cùng kiểu suy diễn với thanh_ly_date:
 * CÓ ngày = đã hạ, NULL = chưa hạ (tích trên bảng /lo-hang ghi ngày hôm nay, lọc Đã/Chưa hạ, xuất Excel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->date('ha_cont_date')->nullable()->after('thanh_ly_date');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->dropColumn('ha_cont_date');
        });
    }
};
