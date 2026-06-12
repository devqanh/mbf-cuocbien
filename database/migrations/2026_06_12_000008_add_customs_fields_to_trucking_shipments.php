<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lô hàng — nhóm "Hải Quan" (toggle ở Thông tin lô):
 *   declaration_note — ghi chú tờ khai (đi kèm declaration_no đã có)
 *   thanh_ly_date    — ngày thanh lý
 *   csht_note        — cơ sở hạ tầng (ghi chú)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->text('declaration_note')->nullable()->after('declaration_no');
            $table->date('thanh_ly_date')->nullable()->after('declaration_note');
            $table->text('csht_note')->nullable()->after('thanh_ly_date');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->dropColumn(['declaration_note', 'thanh_ly_date', 'csht_note']);
        });
    }
};
