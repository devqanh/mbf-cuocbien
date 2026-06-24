<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VAT cho bảng kê khách hàng (cấp statement):
 *  - vat_rate: % VAT áp lên NỀN vận chuyển (cước+dầu+sà lan); chi hộ KHÔNG chịu VAT. Mặc định 0
 *    → backward-compatible (total cũ = nền + chi hộ giữ nguyên).
 *  - base_amount: NỀN = Σ(cuoc+dau+bargeCuoc+bargeDau) các dòng (chưa VAT, không gồm chi hộ) — lưu sẵn
 *    để trang danh sách hiện 4 cột mà KHÔNG phải nạp toàn bộ statement_lines.
 *  - choho_amount: Σ chi hộ các dòng (không VAT).
 *  total = base_amount + round(base_amount × vat_rate/100) + choho_amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_statements', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->default(0)->after('total');
            $table->decimal('base_amount', 14, 2)->default(0)->after('vat_rate');
            $table->decimal('choho_amount', 14, 2)->default(0)->after('base_amount');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_statements', function (Blueprint $table) {
            $table->dropColumn(['vat_rate', 'base_amount', 'choho_amount']);
        });
    }
};
