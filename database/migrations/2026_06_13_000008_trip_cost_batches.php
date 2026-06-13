<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phí xe nội bộ — chuyển sang mô hình KỲ/ĐỢT (snapshot), giống Bảng kê:
 *  - trucking_trip_cost_batches: mỗi kỳ phí xe (số kỳ, khoảng "ngày xe ra", tổng).
 *  - trucking_trip_cost_lines: từng lô trong kỳ, CHỤP LẠI (snapshot) phí lúc lập
 *    (tuyến/BKS/cầu/lái xe + các khoản phí + phí khác repeater) → in/đối soát ổn định.
 *
 * Bảng cũ trucking_trip_costs (per-lô) bị thay thế hoàn toàn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('trucking_trip_costs');

        Schema::create('trucking_trip_cost_batches', function (Blueprint $table) {
            $table->id();
            $table->string('no')->index();                 // số kỳ, VD: PX-2606-01
            $table->string('name')->nullable();            // tên kỳ (tùy chọn)
            $table->date('date')->nullable();              // ngày lập
            $table->date('period_from')->nullable();       // ngày xe ra: từ
            $table->date('period_to')->nullable();         // ngày xe ra: đến
            $table->decimal('total', 15, 2)->default(0);   // tổng phí kỳ
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('trucking_trip_cost_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('trucking_trip_cost_batches')->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()
                  ->constrained('trucking_shipments')->nullOnDelete();
            $table->string('booking')->nullable();         // snapshot tên lô
            $table->string('route')->nullable();           // snapshot tuyến (Nơi lấy → … → Nơi hạ)
            $table->string('kho')->nullable();             // snapshot kho (link tuyến)
            $table->string('bks')->nullable();             // snapshot BKS vào
            $table->string('axle', 4)->nullable();         // '1' | '2' cầu (để tính lít dầu)
            $table->date('date')->nullable();              // giờ xe ra (ngày)
            $table->string('driver')->nullable();          // lái xe
            $table->decimal('ve_tram', 15, 2)->default(0);
            $table->decimal('tien_duong', 15, 2)->default(0);
            $table->decimal('tro_cap', 15, 2)->default(0);
            $table->decimal('phi_khac', 15, 2)->default(0);    // phí khác của TUYẾN
            $table->boolean('cru')->default(false);
            $table->decimal('luong', 15, 2)->default(0);       // lương chạy CRU
            $table->decimal('fuel_liters', 12, 2)->default(0);
            $table->decimal('fuel_price', 15, 2)->default(0);
            $table->json('extras')->nullable();                // [{name,amount,note}]
            $table->decimal('line_total', 15, 2)->default(0);  // snapshot tổng dòng
            $table->string('note')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index('shipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_trip_cost_lines');
        Schema::dropIfExists('trucking_trip_cost_batches');
    }
};
