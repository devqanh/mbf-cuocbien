<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bỏ "duyệt chi theo lô" — chi cho lái xe nay quản lý ở Lộ trình (trucking_route_pays).
 * Xóa bảng trucking_shipment_spends; phần tính lương lái xe sẽ làm lại sau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('trucking_shipment_spends');
    }

    public function down(): void
    {
        // Tái tạo theo cấu trúc cũ để rollback an toàn (dữ liệu không khôi phục).
        if (Schema::hasTable('trucking_shipment_spends')) return;
        Schema::create('trucking_shipment_spends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('trucking_shipments')->cascadeOnDelete();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->string('bks')->nullable();
            $table->string('driver')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->string('source')->default('other');
            $table->string('kind')->default('company');
            $table->string('name');
            $table->decimal('amount', 14, 2)->default(0);
            $table->date('spend_date')->nullable();
            $table->boolean('paid')->default(true);
            $table->date('paid_date')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->index('shipment_id');
            $table->index('spend_date');
            $table->index(['vehicle_id', 'spend_date']);
            $table->index(['kind', 'spend_date']);
        });
    }
};
