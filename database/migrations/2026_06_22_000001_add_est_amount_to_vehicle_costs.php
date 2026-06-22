<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * est_amount = số tiền DỰ KIẾN (lái xe gửi qua "yêu cầu chi"); amount = số tiền THỰC TẾ (kế toán nhập khi duyệt/chi).
 * Phiếu kế toán tạo trực tiếp không có dự kiến → est_amount NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_vehicle_costs', function (Blueprint $table) {
            $table->decimal('est_amount', 14, 2)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_vehicle_costs', function (Blueprint $table) {
            $table->dropColumn('est_amount');
        });
    }
};
