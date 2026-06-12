<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dòng bảng kê: snapshot thêm các cột để xuất phiếu Excel chính thức
 * (mẫu "STATEMENT ACCOUNT ..." — XUẤT-HPH):
 *   decl_no   — Tờ khai
 *   cont_type — Loại Conts
 *   inv       — CVN Invoice no
 *   cont_no   — Số Conts
 *   bks       — Biển kiểm soát / số hiệu sà lan
 *   cuoc      — Cước vận chuyển (chưa VAT) = J
 *   thanh_ly  — Phí thanh lý tờ khai = N
 *   note      — Ghi chú = P
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_statement_lines', function (Blueprint $table) {
            $table->string('decl_no')->nullable()->after('io');
            $table->string('cont_type', 32)->nullable()->after('decl_no');
            $table->string('inv')->nullable()->after('cont_type');
            $table->string('cont_no')->nullable()->after('inv');
            $table->string('bks')->nullable()->after('cont_no');
            $table->decimal('cuoc', 15, 2)->default(0)->after('phai_thu');
            $table->decimal('thanh_ly', 15, 2)->default(0)->after('cuoc');
            $table->text('note')->nullable()->after('thanh_ly');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_statement_lines', function (Blueprint $table) {
            $table->dropColumn(['decl_no', 'cont_type', 'inv', 'cont_no', 'bks', 'cuoc', 'thanh_ly', 'note']);
        });
    }
};
