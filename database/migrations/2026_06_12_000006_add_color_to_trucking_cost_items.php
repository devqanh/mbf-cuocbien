<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gán màu "theo dõi" cấp danh mục cho Khoản chi phí — thay vì chọn màu
 * cho từng dòng phí ở từng lô (không đồng nhất, khó lọc).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_cost_items', function (Blueprint $table) {
            $table->string('color', 16)->nullable()->after('default_price');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_cost_items', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
