<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phí tuyến đường có thêm "Lương không CRU": lô KHÔNG tích CRU tính lương theo
 * cột này; lô tích CRU vẫn theo `luong`. Cột `phi_khac` được GIỮ NGUYÊN để không
 * làm sai tổng các kỳ phí xe đã lưu (24/25 dòng cũ có phí khác) — chỉ bỏ khỏi form
 * cấu hình tuyến + ngừng gợi ý cho dòng mới.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_route_fees', function (Blueprint $table) {
            $table->decimal('luong_no_cru', 14, 2)->default(0)->after('luong');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_route_fees', function (Blueprint $table) {
            $table->dropColumn('luong_no_cru');
        });
    }
};
