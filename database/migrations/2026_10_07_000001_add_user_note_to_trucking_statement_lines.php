<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng kê — cột GHI CHÚ người dùng gõ cho từng lô (trang tạo + trang sửa, in, Excel cột Note).
 * Tách khỏi `note` (snapshot tự sinh: ghi chú kế toán của lô, trống thì tuyến + Connect/Disconnect) để
 * bảng kê cũ giữ nguyên; Excel: có user_note thì in user_note, không thì in note như cũ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_statement_lines', function (Blueprint $table) {
            $table->text('user_note')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_statement_lines', function (Blueprint $table) {
            $table->dropColumn('user_note');
        });
    }
};
