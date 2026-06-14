<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index cho truy vấn lớn (10k–50k lô):
 *  - cont_ra: lọc ứng viên bảng kê (statementCandidates: customer_id + cont_ra trong khoảng).
 *  - gio_den_du_kien: link kế hoạch (planShipmentsQuery lọc theo khoảng giờ đến dự kiến).
 * (customer_id, gio_xe_ra, sheet… đã có index từ trước.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->index('cont_ra');
            $table->index('gio_den_du_kien');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->dropIndex(['cont_ra']);
            $table->dropIndex(['gio_den_du_kien']);
        });
    }
};
