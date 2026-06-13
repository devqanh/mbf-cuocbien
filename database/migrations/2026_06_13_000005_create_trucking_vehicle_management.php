<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quản lý xe (xe MBF nội bộ) — 3 bảng con của trucking_vehicles:
 *  - usages:        thời gian lái xe nào dùng xe (gán thủ công) → sau tính lương theo phí tuyến.
 *  - costs:         chi phí xe (cố định / định kỳ tháng-năm) + thanh toán/duyệt.
 *  - depreciations: hạng mục khấu hao = nguyên giá/(30×số tháng) × số ngày dùng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucking_vehicle_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('trucking_vehicles')->cascadeOnDelete();
            $table->string('driver')->nullable();      // tên lái xe (danh mục Lái xe)
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->string('note')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index('vehicle_id');
        });

        Schema::create('trucking_vehicle_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('trucking_vehicles')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('kind', 12)->default('fixed');   // fixed | monthly | yearly
            $table->date('spend_date')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('current_km', 12, 2)->nullable();
            $table->string('supplier')->nullable();
            $table->string('note')->nullable();
            $table->boolean('paid')->default(false);        // đã thanh toán?
            $table->boolean('approved')->default(false);    // đã duyệt?
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index('vehicle_id');
        });

        Schema::create('trucking_vehicle_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('trucking_vehicles')->cascadeOnDelete();
            $table->string('name')->nullable();             // tên hạng mục khấu hao
            $table->decimal('orig_price', 18, 2)->default(0); // nguyên giá
            $table->date('start_date')->nullable();         // ngày bắt đầu sử dụng
            $table->unsignedInteger('months')->default(0);  // số tháng khấu hao
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index('vehicle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_vehicle_depreciations');
        Schema::dropIfExists('trucking_vehicle_costs');
        Schema::dropIfExists('trucking_vehicle_usages');
    }
};
