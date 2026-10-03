<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kho — Tỉnh/thành (province): chọn ở Cài đặt → Kho (cạnh Ký hiệu). Dùng để khớp Phí tuyến dạng
 * "Cảng → Tỉnh → Cảng": lộ trình Cảng → Kho → Cảng không có tuyến theo kho cụ thể thì rơi về tuyến theo tỉnh của kho.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_warehouses', function (Blueprint $table) {
            $table->string('province', 100)->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_warehouses', function (Blueprint $table) {
            $table->dropColumn('province');
        });
    }
};
