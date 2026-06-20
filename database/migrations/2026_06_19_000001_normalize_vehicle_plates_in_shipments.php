<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sửa biển kiểm soát CŨ (thiếu gạch, khác format) trong Lô hàng + Route pays
 * để khớp với biển số ĐÃ CHUẨN HÓA trong Cài đặt → Biển số xe.
 *
 * Ví dụ: Cài đặt = "29E-72123" → lô hàng còn ghi "29E72123" → sửa thành "29E-72123" + gán vehicle_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        $vehicles = DB::table('trucking_vehicles')->get(['id', 'plate']);
        $norm = fn ($p) => preg_replace('/[\s\-.,]/u', '', mb_strtoupper(trim((string) $p)));
        $byNorm = [];
        foreach ($vehicles as $v) { $n = $norm($v->plate); if ($n !== '') $byNorm[$n] = $v; }

        $fixedVao = 0; $fixedRa = 0; $fixedPay = 0;

        // 1) bks_vao + vehicle_id
        foreach (DB::table('trucking_shipments')->whereNotNull('bks_vao')->where('bks_vao', '!=', '')->get(['id', 'bks_vao', 'vehicle_id']) as $s) {
            $n = $norm($s->bks_vao);
            $match = $byNorm[$n] ?? null;
            if (! $match) continue;
            $upd = [];
            if ($s->bks_vao !== $match->plate) $upd['bks_vao'] = $match->plate;
            if ((int) ($s->vehicle_id ?? 0) !== (int) $match->id) $upd['vehicle_id'] = $match->id;
            if ($upd) { DB::table('trucking_shipments')->where('id', $s->id)->update($upd); $fixedVao++; }
        }

        // 2) bks_ra (text only, nhiều xe dùng bks_ra text — update nếu khớp)
        foreach (DB::table('trucking_shipments')->whereNotNull('bks_ra')->where('bks_ra', '!=', '')->get(['id', 'bks_ra']) as $s) {
            $n = $norm($s->bks_ra);
            $match = $byNorm[$n] ?? null;
            if ($match && $s->bks_ra !== $match->plate) {
                DB::table('trucking_shipments')->where('id', $s->id)->update(['bks_ra' => $match->plate]);
                $fixedRa++;
            }
        }

        // 3) route_pays.bks + vehicle_id
        if (DB::getSchemaBuilder()->hasTable('trucking_route_pays')) {
            foreach (DB::table('trucking_route_pays')->whereNotNull('bks')->where('bks', '!=', '')->get(['id', 'bks', 'vehicle_id']) as $rp) {
                $n = $norm($rp->bks);
                $match = $byNorm[$n] ?? null;
                if (! $match) continue;
                $upd = [];
                if ($rp->bks !== $match->plate) $upd['bks'] = $match->plate;
                if ((int) ($rp->vehicle_id ?? 0) !== (int) $match->id) $upd['vehicle_id'] = $match->id;
                if ($upd) { DB::table('trucking_route_pays')->where('id', $rp->id)->update($upd); $fixedPay++; }
            }
        }

        Log::info("normalize_vehicle_plates: bks_vao={$fixedVao} bks_ra={$fixedRa} route_pays={$fixedPay}");
    }

    public function down(): void
    {
        // Không rollback (dữ liệu text đã đúng format, không cần quay lại dạng thiếu gạch).
    }
};
