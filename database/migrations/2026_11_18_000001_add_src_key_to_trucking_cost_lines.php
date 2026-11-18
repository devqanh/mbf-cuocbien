<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Khóa NGUỒN của dòng chi phí lô do hệ thống tự ghi (src_key) — vd khoản "Chi cho lái" ở Lộ trình được tích
 * "Thu khách" ghi thành dòng chi hộ của lô với src = 'routePay', src_key = "rp|{ngày}|{bks}|{#lô}|{khoản}" để lần lưu
 * sau cập nhật / gỡ đúng dòng đó (không đụng dòng người dùng tự nhập). Chỉ thêm cột null — không đổi dữ liệu cũ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_cost_lines', function (Blueprint $table) {
            $table->string('src_key', 160)->nullable()->after('src')->index();
        });
    }

    public function down(): void
    {
        Schema::table('trucking_cost_lines', function (Blueprint $table) {
            $table->dropIndex(['src_key']);
            $table->dropColumn('src_key');
        });
    }
};
