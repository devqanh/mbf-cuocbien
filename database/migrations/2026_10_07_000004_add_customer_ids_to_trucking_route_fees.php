<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phí tuyến — ÁP DỤNG CHO KHÁCH: customer_ids = JSON [id khách] (null/[] = mọi khách).
 * Cùng 1 tuyến được khai nhiều bản: 1 bản chung + bản riêng cho nhóm khách. Chuyến của khách có tuyến riêng
 * dùng tuyến riêng (ưu tiên trước tuyến chung, kể cả tuyến chung theo kho), không có thì dùng tuyến chung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_route_fees', function (Blueprint $table) {
            $table->json('customer_ids')->nullable()->after('route_key');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_route_fees', function (Blueprint $table) {
            $table->dropColumn('customer_ids');
        });
    }
};
