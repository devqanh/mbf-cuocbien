<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lô hàng: ghi chú tự do (textarea) ở Thông tin lô — tách khỏi "ghi chú kế toán" (ghi_chu). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->text('info_note')->nullable()->after('ghi_chu');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->dropColumn('info_note');
        });
    }
};
