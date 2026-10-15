<?php

use App\Models\TruckingShipment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Chuẩn hóa hoa-thường để kho không bị tính thành 2 (vd ký hiệu "NAKaWA" vs quy chế lương "NAKAWA"):
 *  - ký hiệu Kho / Địa điểm → VIẾT HOA (quy chế lương, bảng giá đều so theo chữ hoa; từ nay lưu cũng tự viết hoa);
 *  - ô Kho của lô ("ts", "wolong", …) → đúng tên kho trong danh mục, rồi tính lại liên kết kho của lô đó.
 * Chỉ đổi kiểu chữ — không đổi ký hiệu / tên khác, nên không đứt link bảng giá, phí tuyến.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['trucking_warehouses', 'trucking_locations'] as $t) {
            foreach (DB::table($t)->whereNotNull('code')->where('code', '!=', '')->get(['id', 'code']) as $r) {
                $up = mb_strtoupper(trim($r->code));
                if ($up !== $r->code) DB::table($t)->where('id', $r->id)->update(['code' => $up]);
            }
        }

        $svc = app(\App\Services\TruckingV2Service::class);
        TruckingShipment::whereNotNull('kho')->where('kho', '!=', '')->orderBy('id')->chunkById(500, function ($rows) use ($svc) {
            foreach ($rows as $s) {
                $kho = $svc->canonKhoNames($s->kho);
                if ($kho === $s->kho) continue;
                $s->kho = $kho;
                $s->saveQuietly();
                $svc->recomputeShipmentDerived($s, ['kho']);
            }
        });
    }

    /** Không đảo lại: kiểu chữ cũ lệch nhau chính là lỗi cần sửa. */
    public function down(): void
    {
    }
};
