<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cấu hình PHÍ TUYẾN ĐƯỜNG: mỗi tuyến = tập kho (route) + các khoản phí/định mức cho chuyến.
 *   route      — danh sách kho của tuyến (nối bằng " - "), vd "Kho 1 - Kho 2"
 *   ve_tram    — vé trạm | tien_duong — tiền đường | tro_cap — trợ cấp | phi_khac — phí khác
 *   cru        — chạy tuyến CRU (cờ) | luong — thêm tiền lương (khi chạy CRU)
 *   km         — số km | dau_2cau — số lít dầu (xe 2 cầu) | dau_1cau — số lít dầu (xe 1 cầu)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucking_route_fees', function (Blueprint $table) {
            $table->id();
            $table->string('route')->nullable();          // tập kho nối bằng " - "
            $table->decimal('ve_tram', 15, 2)->default(0);
            $table->decimal('tien_duong', 15, 2)->default(0);
            $table->decimal('tro_cap', 15, 2)->default(0);
            $table->decimal('phi_khac', 15, 2)->default(0);
            $table->boolean('cru')->default(false);
            $table->decimal('luong', 15, 2)->default(0);
            $table->decimal('km', 10, 2)->default(0);
            $table->decimal('dau_2cau', 10, 2)->default(0);   // lít dầu xe 2 cầu
            $table->decimal('dau_1cau', 10, 2)->default(0);   // lít dầu xe 1 cầu
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_route_fees');
    }
};
