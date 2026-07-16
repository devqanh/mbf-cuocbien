<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phiếu chi xe (trucking_vehicle_costs): CHI PHÍ PHÂN BỔ.
 * - `alloc`        : cờ "chi phí này là chi phí phân bổ" (chia đều cho nhiều tháng).
 * - `alloc_months` : số tháng phân bổ → chi phí/tháng = Số tiền ÷ số tháng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_vehicle_costs', function (Blueprint $table) {
            $table->boolean('alloc')->default(false)->after('material');
            $table->unsignedSmallInteger('alloc_months')->nullable()->after('alloc');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_vehicle_costs', function (Blueprint $table) {
            $table->dropColumn(['alloc', 'alloc_months']);
        });
    }
};
