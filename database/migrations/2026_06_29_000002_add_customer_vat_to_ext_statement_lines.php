<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng kê xe ngoài: thêm TÊN KHÁCH HÀNG + % VAT + NHẬP/XUẤT (snapshot) cho mỗi dòng lô.
 *
 * - customer: tên khách của lô (hiển thị gộp trong ô Lô cho gọn cột).
 * - vat_rate: % VAT chọn cho từng lô (0/8/10). VAT chỉ áp lên CƯỚC (thuê xe ngoài);
 *   chi hộ KHÔNG chịu VAT (như bảng kê khách). Tổng dòng = cước + VAT(cước) + chi hộ.
 * - io: loại cont Nhập/Xuất/Khác của lô (badge trong ô Lô).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trucking_ext_statement_lines')) return;

        Schema::table('trucking_ext_statement_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('trucking_ext_statement_lines', 'customer')) {
                $table->string('customer')->nullable()->after('booking');
            }
            if (! Schema::hasColumn('trucking_ext_statement_lines', 'io')) {
                $table->string('io', 16)->nullable()->after('customer');
            }
            if (! Schema::hasColumn('trucking_ext_statement_lines', 'vat_rate')) {
                $table->decimal('vat_rate', 5, 2)->default(0)->after('choho_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trucking_ext_statement_lines', function (Blueprint $table) {
            foreach (['customer', 'io', 'vat_rate'] as $col) {
                if (Schema::hasColumn('trucking_ext_statement_lines', $col)) $table->dropColumn($col);
            }
        });
    }
};
