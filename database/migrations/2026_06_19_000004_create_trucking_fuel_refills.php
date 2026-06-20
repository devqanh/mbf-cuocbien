<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phiếu đổ dầu — ghi nhận lượng dầu thực tế nạp vào xe theo ngày.
 * Kết hợp với lượng tiêu thụ lý thuyết từ Lộ trình (routeTripByDate.fuelLiters)
 * để ước tính dầu còn lại = đổ vào − Σ tiêu thụ tích lũy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucking_fuel_refills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('trucking_vehicles')->cascadeOnDelete();
            $table->date('refill_date');
            $table->decimal('liters', 10, 2);                       // số lít đổ
            $table->decimal('unit_price', 14, 2)->nullable();        // đơn giá (đ/lít)
            $table->decimal('total_cost', 14, 2)->nullable();        // thành tiền (tự tính)
            $table->decimal('odometer_km', 12, 2)->nullable();       // số km đồng hồ lúc đổ
            $table->string('station')->nullable();                   // trạm / nơi đổ
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'refill_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_fuel_refills');
    }
};
