<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng giá dầu theo ngày: đơn giá (đồng/lít) hiệu lực trong 1 khoảng ngày.
 *   from_date — từ ngày (bắt buộc) | to_date — đến ngày (rỗng = mở, hiệu lực từ from trở đi)
 *   price     — đồng/lít
 * Khớp giá cho ngày d: chọn dòng có from_date <= d <= (to_date | +∞); nhiều dòng trùng → from mới nhất.
 * Bảng nhỏ → khi tính phí hàng loạt: nạp toàn bộ 1 lần, dò trong RAM (không query theo từng lô).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucking_fuel_prices', function (Blueprint $table) {
            $table->id();
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->decimal('price', 15, 2)->default(0);   // đồng / lít
            $table->string('note')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['from_date', 'to_date']);        // dò theo khoảng ngày
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_fuel_prices');
    }
};
