<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Khách DÙNG CHUNG bảng giá của khách khác (vd Canon Thăng Long / Tiên Sơn dùng giá Canon Quế Võ):
 * định giá lô / bảng kê / báo cáo lấy bảng giá theo ngày của khách nguồn. Xóa khách nguồn → về bảng giá riêng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_customers', function (Blueprint $t) {
            $t->foreignId('price_source_id')->nullable()->after('note')
                ->constrained('trucking_customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trucking_customers', function (Blueprint $t) {
            $t->dropConstrainedForeignId('price_source_id');
        });
    }
};
