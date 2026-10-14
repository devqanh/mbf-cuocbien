<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng phí tuyến (theo "áp dụng từ ngày") — QUY CHẾ LƯƠNG CHUYẾN (Quy chế 03/2026/QC-MBF) đi kèm bảng:
 * regulation = JSON {originGroups, zones[7], kcn[], fuel, distances[]} — xem App\Support\SalaryZones::defaultRegulation().
 * Chuyến không có tuyến riêng khớp → tra bảng KCN (xuất phát ICD Quế Võ / Tri Phương) hoặc bảng vùng theo tỉnh cũ
 * của kho (xuất phát Hải Phòng); km = khứ hồi bảng KCN hoặc cộng chặng; dầu = km × định mức theo cầu + Hàng/Rỗng.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('trucking_route_fee_books', 'regulation')) return;
        Schema::table('trucking_route_fee_books', function (Blueprint $table) {
            $table->json('regulation')->nullable()->after('from_date');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_route_fee_books', function (Blueprint $table) {
            $table->dropColumn('regulation');
        });
    }
};
