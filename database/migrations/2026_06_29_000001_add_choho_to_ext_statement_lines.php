<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng kê xe ngoài: thêm cột CHI HỘ + GHI CHÚ DANH MỤC CHI HỘ cho mỗi dòng lô.
 *
 * Ngoài cước thuê xe (fee = ext_fee), nhà xe ngoài còn ứng/chi hộ một số khoản
 * (nâng hạ, lưu cont…) ở mục "Chi phí lô hàng" (các dòng tích "Chi hộ khách",
 * billable = true). Ta kéo tổng chi hộ đó vào bảng kê để TỔNG phải trả nhà xe
 * = cước + chi hộ. `choho_note` chụp lại breakdown từng danh mục ("Nâng 2.916.000…")
 * để bảng kê hiện rõ chi hộ gồm những khoản gì. Tổng bảng kê đã là số tổng nên
 * không cần đổi schema bảng cha.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trucking_ext_statement_lines')) return;

        Schema::table('trucking_ext_statement_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('trucking_ext_statement_lines', 'choho')) {
                $table->decimal('choho', 15, 2)->default(0)->after('fee'); // chi hộ (tổng khoản billable của lô)
            }
            if (! Schema::hasColumn('trucking_ext_statement_lines', 'choho_note')) {
                $table->string('choho_note', 1000)->nullable()->after('choho'); // breakdown danh mục chi hộ
            }
        });
    }

    public function down(): void
    {
        Schema::table('trucking_ext_statement_lines', function (Blueprint $table) {
            foreach (['choho', 'choho_note'] as $col) {
                if (Schema::hasColumn('trucking_ext_statement_lines', $col)) $table->dropColumn($col);
            }
        });
    }
};
