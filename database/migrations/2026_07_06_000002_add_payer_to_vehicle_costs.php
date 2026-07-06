<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phiếu chi xe (trucking_vehicle_costs): thêm cột `payer` (Người chi) — chọn từ danh mục
 * Người chi hoặc gõ mới; lưu chuỗi tên trên từng phiếu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_vehicle_costs', function (Blueprint $table) {
            $table->string('payer')->nullable()->after('supplier');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_vehicle_costs', function (Blueprint $table) {
            $table->dropColumn('payer');
        });
    }
};
