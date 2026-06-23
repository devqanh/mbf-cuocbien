<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lô hàng: cờ SÀ LAN + loại cont (DRY/NOR) — định giá theo nhóm "Non" DRY/NOR CONTAINER ở bảng giá. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->boolean('is_barge')->default(false)->after('cru');   // có đi sà lan?
            $table->string('barge_cont', 8)->nullable()->after('is_barge'); // DRY | NOR
        });
    }

    public function down(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->dropColumn(['is_barge', 'barge_cont']);
        });
    }
};
