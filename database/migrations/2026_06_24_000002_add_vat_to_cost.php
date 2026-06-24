<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VAT cho chi phí:
 *  - cost_lines.vat: % VAT từng khoản chi (số tiền điền GỒM VAT → chi phí net = số tiền ÷ (1+vat/100)).
 *  - cost_items.vat: % VAT mặc định của khoản (tự fill khi chọn ở popup, vẫn sửa được).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_cost_lines', function (Blueprint $table) {
            $table->decimal('vat', 5, 2)->default(0)->after('amount');
        });
        Schema::table('trucking_cost_items', function (Blueprint $table) {
            $table->decimal('vat', 5, 2)->nullable()->after('auto');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_cost_lines', function (Blueprint $table) {
            $table->dropColumn('vat');
        });
        Schema::table('trucking_cost_items', function (Blueprint $table) {
            $table->dropColumn('vat');
        });
    }
};
