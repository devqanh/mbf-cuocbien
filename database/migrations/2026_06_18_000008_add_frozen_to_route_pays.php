<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chi cho lái (route_pays): cờ ĐÓNG BĂNG (chốt) — snapshot số tiền lúc chốt để KHÔNG đổi
 * khi sau này sửa Phí tuyến. frozen_data lưu payGroups + tổng đã tính tại thời điểm chốt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_route_pays', function (Blueprint $table) {
            $table->boolean('frozen')->default(false)->after('extra_items');
            $table->timestamp('frozen_at')->nullable()->after('frozen');
            $table->json('frozen_data')->nullable()->after('frozen_at');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_route_pays', function (Blueprint $table) {
            $table->dropColumn(['frozen', 'frozen_at', 'frozen_data']);
        });
    }
};
