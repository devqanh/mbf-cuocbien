<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng kê xe ngoài: đánh dấu ĐÃ CHI TIỀN cho từng dòng lô.
 * "Đã chi" suy từ paid_at (null = chưa chi) — ngày tích đã chi cho nhà xe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trucking_ext_statement_lines')) return;
        if (Schema::hasColumn('trucking_ext_statement_lines', 'paid_at')) return;

        Schema::table('trucking_ext_statement_lines', function (Blueprint $table) {
            $table->date('paid_at')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('trucking_ext_statement_lines', 'paid_at')) {
            Schema::table('trucking_ext_statement_lines', fn (Blueprint $table) => $table->dropColumn('paid_at'));
        }
    }
};
