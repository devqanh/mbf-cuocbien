<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phiếu chi xe (trucking_vehicle_costs): thêm cờ `material` = chi phí này là VẬT TƯ
 * (lốp, ắc quy, lọc dầu…) → để lọc riêng danh sách vật tư của xe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_vehicle_costs', function (Blueprint $table) {
            $table->boolean('material')->default(false)->after('payer');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_vehicle_costs', function (Blueprint $table) {
            $table->dropColumn('material');
        });
    }
};
