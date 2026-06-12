<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dòng chi phí: thêm `src` (nguồn liên kết, vd "extTruck" = thuê xe ngoài từ
 * popup Thông tin lô — khóa xóa) và `note` (ghi chú, vd thông tin nhà xe).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_cost_lines', function (Blueprint $table) {
            $table->string('src', 32)->nullable()->after('color');
            $table->string('note')->nullable()->after('src');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_cost_lines', function (Blueprint $table) {
            $table->dropColumn(['src', 'note']);
        });
    }
};
