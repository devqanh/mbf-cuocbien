<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Chi phí lô hàng: thêm cột Số hóa đơn cho từng khoản chi (kế toán đối chiếu). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_cost_lines', function (Blueprint $table) {
            $table->string('invoice_no')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_cost_lines', function (Blueprint $table) {
            $table->dropColumn('invoice_no');
        });
    }
};
