<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng kê xe ngoài: tích "đã chi" TÁCH THEO TỪNG PHẦN của dòng lô.
 *  - choho_items: snapshot từng khoản chi hộ [{key, item, amount}] (key = id dòng chi phí lô).
 *  - paid_parts : {phần => ngày chi}; phần = 'fee' (cước + VAT) hoặc 'i:<key>' (1 khoản chi hộ).
 * paid_at giữ nguyên ý nghĩa "đã chi ĐỦ" = mọi phần đã tích (ngày = ngày chi muộn nhất).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trucking_ext_statement_lines')) return;

        Schema::table('trucking_ext_statement_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('trucking_ext_statement_lines', 'choho_items')) {
                $table->json('choho_items')->nullable()->after('choho_note');
            }
            if (! Schema::hasColumn('trucking_ext_statement_lines', 'paid_parts')) {
                $table->json('paid_parts')->nullable()->after('paid_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trucking_ext_statement_lines', function (Blueprint $table) {
            foreach (['choho_items', 'paid_parts'] as $col) {
                if (Schema::hasColumn('trucking_ext_statement_lines', $col)) $table->dropColumn($col);
            }
        });
    }
};
