<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đơn vị xe ngoài (nhà xe thuê) + tham chiếu trên lô.
 *  - trucking_ext_vendors: danh mục nhà xe (Cài đặt).
 *  - shipments.ext_vendor_id: nhà xe đã thuê cho lô (bắt buộc khi "Thuê xe ngoài").
 *  - shipments.ext_fee: cước thuê xe ngoài (chốt từ dòng chi phí src=extTruck) — để Bảng kê xe ngoài query nhanh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucking_ext_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('note')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('trucking_shipments', function (Blueprint $table) {
            // Tên nhà xe ngoài (khớp danh mục extVendors) — name-based như cột khách/chi phí.
            $table->string('ext_vendor')->nullable()->after('ra_other_id');
            $table->decimal('ext_fee', 15, 2)->default(0)->after('ext_vendor');   // cước thuê xe ngoài (chốt)
            $table->index(['ext_vendor', 'gio_xe_den']);   // lọc bảng kê xe ngoài theo nhà xe + Giờ xe đến
        });
    }

    public function down(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->dropIndex(['ext_vendor', 'gio_xe_den']);
            $table->dropColumn(['ext_vendor', 'ext_fee']);
        });
        Schema::dropIfExists('trucking_ext_vendors');
    }
};
