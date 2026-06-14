<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phiếu chi xe: ghi NGƯỜI YÊU CẦU + HỦY phiếu.
 *  - created_by   : user gửi yêu cầu (audit "ai tạo phiếu chi").
 *  - cancelled_at/cancelled_by : hủy phiếu (tài xế hủy khi chưa duyệt, admin hủy khi chưa chi).
 * Phiếu đã hủy giữ lại để audit nhưng LOẠI khỏi tổng chi phí/báo cáo (lọc cancelled_at IS NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_vehicle_costs', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('vehicle_id')->index();
            $table->timestamp('cancelled_at')->nullable()->after('approved');
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_vehicle_costs', fn (Blueprint $t) => $t->dropColumn(['created_by', 'cancelled_at', 'cancelled_by']));
    }
};
