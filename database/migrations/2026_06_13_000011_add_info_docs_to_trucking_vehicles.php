<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thông tin xe mở rộng: mã khung/số máy/hãng/năm/nơi mua… (gói trong JSON `info` cho linh hoạt)
 * + tài liệu đính kèm (ảnh/PDF/Word/Excel) trong JSON `documents` (giống hồ sơ tài xế).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_vehicles', function (Blueprint $table) {
            $table->json('info')->nullable()->after('axle');           // {chassisNo, engineNo, brand, year, purchasePlace, ...}
            $table->json('documents')->nullable()->after('info');      // [{type,name,path,mime,size}]
        });
    }

    public function down(): void
    {
        Schema::table('trucking_vehicles', function (Blueprint $table) {
            $table->dropColumn(['info', 'documents']);
        });
    }
};
