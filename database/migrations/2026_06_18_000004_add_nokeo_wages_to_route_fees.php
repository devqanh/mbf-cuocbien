<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phí tuyến: lương tách theo 2 chiều = (CÓ/KHÔNG kéo cont ra) × (CRU/không CRU).
 * Cột cũ luong/luong_no_cru = nhóm CÓ kéo cont ra; thêm 2 cột cho nhóm KHÔNG kéo cont ra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_route_fees', function (Blueprint $table) {
            $table->decimal('luong_nokeo', 14, 2)->default(0)->after('luong_no_cru');        // không kéo cont + CRU
            $table->decimal('luong_nokeo_no_cru', 14, 2)->default(0)->after('luong_nokeo');  // không kéo cont + không CRU
        });
    }

    public function down(): void
    {
        Schema::table('trucking_route_fees', function (Blueprint $table) {
            $table->dropColumn(['luong_nokeo', 'luong_nokeo_no_cru']);
        });
    }
};
