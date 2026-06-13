<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tối ưu LINK cho module "Phí xe nội bộ":
 *  - gio_xe_ra: cột LỌC kỳ (chọn khoảng Ngày xe ra) → index để range-scan nhanh khi dữ liệu lớn.
 *  - bks_vao:   link xe (→ số cầu / lái xe) → index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->index('gio_xe_ra');
            $table->index('bks_vao');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->dropIndex(['gio_xe_ra']);
            $table->dropIndex(['bks_vao']);
        });
    }
};
