<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lịch sử xe GHÉ KHO (geofence visit) — 1 dòng = 1 lần ghé (đến → đi).
 * Phát hiện qua command trucking:scan-warehouse-visits (cron 5 phút):
 *  - VÀO ≤ enter (400m) → mở visit (arrived_at)
 *  - giữ trong vùng đệm tới khi RA > exit (2km) → đóng visit (departed_at)
 *  - dwell < ngưỡng (10') → coi là tạt ngang, không tính.
 * Lưu snapshot biển số xe HỆ THỐNG (vehicle_plate) khi GPS khớp xe → sau dùng tính
 * thời gian lô hàng được chở tới bởi xe nào.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucking_warehouse_visits', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);                       // viettel | dvbk
            $table->string('gps_ref')->index();                   // provider:deviceId (định danh xe GPS)
            $table->string('gps_plate')->nullable();              // biển số theo GPS
            $table->unsignedBigInteger('vehicle_id')->nullable()->index();   // xe hệ thống (nếu khớp)
            $table->string('vehicle_plate')->nullable();          // SNAPSHOT biển số xe hệ thống
            $table->string('driver')->nullable();
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->string('warehouse_name');                     // snapshot tên kho
            $table->dateTime('arrived_at')->index();              // giờ ĐẾN kho
            $table->dateTime('last_inside_at');                   // lần cuối còn trong vùng kho
            $table->dateTime('departed_at')->nullable();          // giờ RỜI kho (null = đang ở kho)
            $table->boolean('confirmed')->default(false);         // đã đủ dwell tối thiểu
            $table->integer('min_dist_m')->nullable();            // khoảng cách gần nhất (m)
            $table->timestamps();
            $table->index(['gps_ref', 'departed_at']);            // tìm visit đang mở của 1 xe
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_warehouse_visits');
    }
};
