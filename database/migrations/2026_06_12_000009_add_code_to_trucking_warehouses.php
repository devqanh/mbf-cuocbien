<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kho (warehouses) có thêm KÝ HIỆU (code) như Địa điểm — để hiển thị kho
 * dễ hiểu (tên đầy đủ + ký hiệu viết tắt).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_warehouses', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_warehouses', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
