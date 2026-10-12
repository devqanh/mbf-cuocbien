<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kho — TỈNH CŨ (trước sáp nhập 2025) theo Quy chế lương 03/2026/QC-MBF. Quy chế tính lương / phụ cấp theo tỉnh cũ
 * (Hải Dương ≠ Hải Phòng, Thái Bình ≠ Hưng Yên; Hưng Yên tách "chưa qua trạm" / "qua trạm") → vùng lương suy từ đây
 * (App\Support\SalaryZones). Dùng cho trợ cấp "Quay đầu tuyến ngoài Hải Phòng" và tra lương chuyến theo vùng sau này.
 * Khác cột `province` (34 tỉnh MỚI — khớp phí tuyến "Cảng → Tỉnh → Cảng").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_warehouses', function (Blueprint $table) {
            $table->string('old_province', 80)->nullable()->after('province');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_warehouses', function (Blueprint $table) {
            $table->dropColumn('old_province');
        });
    }
};
