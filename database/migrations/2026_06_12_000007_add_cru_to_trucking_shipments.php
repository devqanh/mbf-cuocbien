<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lô hàng: cờ `cru` — hàng CRU. Quyết định KIND khi dò bảng giá:
 *   CRU + Xuất → "External CRU transportation"
 *   CRU + Nhập → "Internal CRU transportation"
 *   không CRU  → "Transportation 1 way of Import/Export"
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->boolean('cru')->default(false)->after('io');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->dropColumn('cru');
        });
    }
};
