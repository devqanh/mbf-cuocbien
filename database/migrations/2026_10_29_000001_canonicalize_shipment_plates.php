<?php

use App\Support\PlateNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Đưa BKS vào / ra của Lô hàng (và bks chi lái theo ngày) về CÙNG 1 format với danh mục xe:
 * "15C 15877" / "15c-158.77" / "15C15877" → "15C-15877" (PlateNormalizer::canonical — model
 * TruckingShipment cũng tự chuẩn hóa khi lưu từ nay).
 *
 * Ghi thẳng bằng query (không qua model) nên không đẻ nhật ký thao tác cho hàng trăm lô.
 * route_pays unique (work_date, bks): nếu dạng chuẩn đã có dòng cùng ngày thì GIỮ NGUYÊN dòng đó
 * (không gộp tiền tự động) và ghi log để xử lý tay. Idempotent: chạy lại = no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        $fixed = ['bks_vao' => 0, 'bks_ra' => 0, 'route_pays' => 0];

        DB::transaction(function () use (&$fixed) {
            foreach (['bks_vao', 'bks_ra'] as $col) {
                $vals = DB::table('trucking_shipments')->whereNotNull($col)->distinct()->pluck($col);
                foreach ($vals as $v) {
                    $c = PlateNormalizer::canonical($v);
                    if ($c === $v) continue;
                    $fixed[$col] += DB::table('trucking_shipments')->where($col, $v)->update([$col => $c]);
                }
            }

            if (Schema::hasTable('trucking_route_pays')) {
                foreach (DB::table('trucking_route_pays')->whereNotNull('bks')->get(['id', 'work_date', 'bks']) as $rp) {
                    $c = PlateNormalizer::canonical($rp->bks);
                    if ($c === $rp->bks) continue;
                    $taken = DB::table('trucking_route_pays')->where('work_date', $rp->work_date)->where('bks', $c)->where('id', '!=', $rp->id)->exists();
                    if ($taken) { Log::warning("canonicalize_shipment_plates: route_pay #{$rp->id} ({$rp->bks}) trùng ngày với {$c} — giữ nguyên"); continue; }
                    DB::table('trucking_route_pays')->where('id', $rp->id)->update(['bks' => $c]);
                    $fixed['route_pays']++;
                }
            }
        });

        Log::info('canonicalize_shipment_plates: ' . json_encode($fixed));
    }

    public function down(): void
    {
        // Không khôi phục: format cũ (thiếu gạch, có chấm/cách) không mang thông tin gì thêm.
    }
};
