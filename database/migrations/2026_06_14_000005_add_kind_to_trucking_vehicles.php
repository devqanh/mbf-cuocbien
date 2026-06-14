<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tách "Quản lý xe" thành "Quản lý tài sản": thêm cột `kind` để phân biệt
 * 'vehicle' (xe — giữ nguyên mọi hành vi cũ, vẫn link phí xe nội bộ) và
 * 'asset' (tài sản khác). Mọi bản ghi cũ mặc định 'vehicle' → KHÔNG đổi gì.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_vehicles', function (Blueprint $table) {
            $table->string('kind', 12)->default('vehicle')->after('type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('trucking_vehicles', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
