<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng kê — CỘT CHI PHÍ RIÊNG theo từng bảng kê (không khai trong danh mục Khoản chi phí):
 *  - extra_cols: JSON [{key, label, vat}] — key ổn định (FE sinh), vat = khoản này chịu VAT% của bảng kê.
 *    Số tiền từng lô ghi trong statement_lines.detail.extras = {key: số}; cột đã xóa thì key bị bỏ qua.
 *  - extra_amount: Σ phí riêng các lô TRONG PHẠM VI (cột dẫn xuất cho trang danh sách, như base/choho).
 *  total = nền + VAT(nền + phí riêng có vat) + chi hộ + extra_amount. Mặc định null/0 → bảng kê cũ y nguyên.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_statements', function (Blueprint $table) {
            $table->json('extra_cols')->nullable()->after('io_scope');
            $table->decimal('extra_amount', 14, 2)->default(0)->after('choho_amount');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_statements', function (Blueprint $table) {
            $table->dropColumn(['extra_cols', 'extra_amount']);
        });
    }
};
