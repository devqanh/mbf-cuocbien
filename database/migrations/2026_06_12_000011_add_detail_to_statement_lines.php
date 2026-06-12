<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dòng bảng kê: snapshot CHI TIẾT khoản (cước, dầu, danh sách chi hộ, chi phí)
 * tại thời điểm lập — để hiển thị đối soát TĨNH mà không cần query realtime.
 * Bảng kê đã chốt giữ nguyên lịch sử kể cả khi lô hàng bị sửa/xóa bên ngoài.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_statement_lines', function (Blueprint $table) {
            $table->json('detail')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_statement_lines', function (Blueprint $table) {
            $table->dropColumn('detail');
        });
    }
};
