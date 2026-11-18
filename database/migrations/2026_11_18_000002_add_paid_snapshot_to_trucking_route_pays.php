<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * SỐ ĐÃ CHI cho lái (paid_snapshot) — chụp lúc tích "Đã chi" để Lộ trình so với số tính lại theo lô HIỆN TẠI và báo
 * chi thừa / thiếu khi lô bị sửa sau khi đã chi. {total, trips: {"#lô": {cont, route, sub}}, at, src}.
 * Xe-ngày ĐÃ CHI từ trước (chưa có số chụp) → lấy số tính được lúc chạy migration làm mốc (src = 'backfill'); chỉ ghi
 * vào cột mới, không đổi dữ liệu cũ. down() bỏ cột.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_route_pays', function (Blueprint $table) {
            $table->json('paid_snapshot')->nullable()->after('paid_date');
        });

        $svc = app(\App\Services\TruckingV2Service::class);
        $n = 0;
        $dates = DB::table('trucking_route_pays')->where('paid', true)->whereNull('paid_snapshot')
            ->selectRaw('DATE(work_date) d')->distinct()->orderBy('d')->pluck('d');
        foreach ($dates as $d) {
            try { $n += $svc->snapshotPaidDay((string) $d, 'backfill'); }
            catch (\Throwable $e) { Log::warning("paid_snapshot backfill $d: " . $e->getMessage()); }
        }
        Log::info("paid_snapshot backfill: $n xe-ngày");
    }

    public function down(): void
    {
        Schema::table('trucking_route_pays', function (Blueprint $table) {
            $table->dropColumn('paid_snapshot');
        });
    }
};
