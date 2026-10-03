<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng kê — PHẠM VI lô: io_scope = all | nhap | xuat. Chọn "Xuất"/"Nhập" thì chỉ các lô đúng loại
 * được TÍNH TIỀN (nền/VAT/chi hộ/tổng), in và xuất Excel; lô khác loại vẫn nằm trong bảng kê nhưng
 * mờ, không tính. Mặc định 'all' = bảng kê cũ chạy y nguyên.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_statements', function (Blueprint $table) {
            $table->string('io_scope', 8)->default('all')->after('vat_rate');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_statements', function (Blueprint $table) {
            $table->dropColumn('io_scope');
        });
    }
};
