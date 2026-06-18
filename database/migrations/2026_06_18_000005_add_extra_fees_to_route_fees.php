<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phí tuyến: thêm danh sách "Chi khác" tùy chỉnh (repeater) — mỗi dòng {name, amount, perDay}.
 * Không cần migration cho từng loại phí mới: nhập trực tiếp ở giao diện, tự gom vào Lộ trình.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_route_fees', function (Blueprint $table) {
            $table->json('extra_fees')->nullable()->after('dau_1cau');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_route_fees', function (Blueprint $table) {
            $table->dropColumn('extra_fees');
        });
    }
};
