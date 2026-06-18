<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chi cho lái xe theo NGÀY + XE (lộ trình). Tiền KHÔNG lưu — luôn tính lại từ Phí tuyến đường
 * (khoản "chi theo ngày") + giá dầu theo ngày. Bảng này chỉ lưu: lái xe NÀO nhận + đã chi chưa.
 * Khóa duy nhất (work_date, bks) — mỗi xe/ngày 1 bản ghi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucking_route_pays', function (Blueprint $table) {
            $table->id();
            $table->date('work_date');                 // ngày vận hành (khung 08:00→08:00 của Lộ trình)
            $table->string('bks');                     // biển số xe (gom theo bks_vao như Lộ trình)
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->string('driver')->nullable();      // tên lái xe nhận tiền
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->boolean('paid')->default(false);   // đã chi cho lái chưa
            $table->date('paid_date')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['work_date', 'bks']);
            $table->index('driver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_route_pays');
    }
};
