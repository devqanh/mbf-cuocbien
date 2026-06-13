<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phí xe nội bộ:
 *  - route_fees.route_key: khóa chuẩn hóa của tuyến (tên kho UPPER, đúng thứ tự, nối "|") + index
 *    → khớp lô↔tuyến nhanh & ổn định (đổi tên kho không vỡ vì cùng cách chuẩn hóa).
 *  - trucking_trip_costs: lưu kết quả phí chuyến theo TỪNG LÔ (sửa được, có "Tính lại").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_route_fees', function (Blueprint $table) {
            $table->string('route_key')->nullable()->after('route')->index();
        });

        Schema::create('trucking_trip_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->unique()->constrained('trucking_shipments')->cascadeOnDelete();
            $table->string('driver')->nullable();
            $table->decimal('ve_tram', 15, 2)->default(0);
            $table->decimal('tien_duong', 15, 2)->default(0);
            $table->decimal('tro_cap', 15, 2)->default(0);
            $table->decimal('phi_khac', 15, 2)->default(0);     // phí khác của TUYẾN (từ route_fee, sửa được)
            $table->boolean('cru')->default(false);             // lô có chạy CRU?
            $table->decimal('luong', 15, 2)->default(0);        // lương chạy CRU (nếu cru)
            $table->decimal('fuel_liters', 12, 2)->default(0);  // số lít dầu (theo cầu xe)
            $table->decimal('fuel_price', 15, 2)->default(0);   // đơn giá dầu (đồng/lít, theo ngày xe ra)
            $table->json('extras')->nullable();                 // phí khác phát sinh (repeater): [{name,amount,note}]
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_trip_costs');
        Schema::table('trucking_route_fees', function (Blueprint $table) {
            $table->dropIndex(['route_key']);
            $table->dropColumn('route_key');
        });
    }
};
