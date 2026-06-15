<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Thêm "Địa chỉ kho" cho danh mục Kho (ngoài tên + ký hiệu) — phục vụ hiển thị + sau này geocode tọa độ. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_warehouses', function (Blueprint $table) {
            $table->string('address')->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_warehouses', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }
};
