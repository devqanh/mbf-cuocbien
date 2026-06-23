<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lô hàng: NƠI HẠ SÀ LAN (ký hiệu địa điểm) — điểm đến của sà lan, dùng tra phí sà lan ở bảng giá. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->string('barge_drop', 64)->nullable()->after('barge_cont'); // ký hiệu nơi hạ sà lan
        });
    }

    public function down(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->dropColumn('barge_drop');
        });
    }
};
