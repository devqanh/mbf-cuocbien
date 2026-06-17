<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Chuẩn hóa lô hàng: from_loc / to_loc / kho → KÝ HIỆU (code) duy nhất.
 * Lý do: link/tham chiếu bảng giá theo ký hiệu (bền khi đổi tên); tên chỉ để báo cáo.
 * Khớp KHÔNG phân biệt hoa/thường + bỏ dấu ("LẠCH HUYỆN" = "LACH HUYEN" → "LHP").
 * Giá trị không khớp danh mục (ghi chú tự do) → giữ nguyên. Idempotent (chạy lại = no-op).
 */
return new class extends Migration
{
    public function up(): void
    {
        $norm = fn ($v) => mb_strtoupper(trim(Str::ascii((string) $v)));

        $loc = [];
        foreach (DB::table('trucking_locations')->get(['name', 'code']) as $l) {
            if ($l->code) { $loc[$norm($l->code)] = $l->code; if ($l->name) $loc[$norm($l->name)] = $l->code; }
        }
        $wh = [];
        foreach (DB::table('trucking_warehouses')->get(['name', 'code']) as $w) {
            if ($w->code) { $wh[$norm($w->code)] = $w->code; if ($w->name) $wh[$norm($w->name)] = $w->code; }
        }

        $mapLoc = function ($v) use ($loc, $norm) {
            if ($v === null || trim((string) $v) === '') return $v;
            return $loc[$norm($v)] ?? $v;   // không khớp → giữ nguyên
        };
        $mapKho = function ($v) use ($wh, $norm) {
            if ($v === null || trim((string) $v) === '') return $v;
            $parts = preg_split('/\s*(?:,|→|->|–|—|\s-\s)\s*/u', (string) $v) ?: [];
            $out = [];
            foreach ($parts as $p) { $p = trim($p); if ($p === '') continue; $out[] = $wh[$norm($p)] ?? $p; }
            return implode(', ', $out);
        };

        DB::table('trucking_shipments')->orderBy('id')->chunkById(500, function ($rows) use ($mapLoc, $mapKho) {
            foreach ($rows as $s) {
                $nf = $mapLoc($s->from_loc); $nt = $mapLoc($s->to_loc); $nk = $mapKho($s->kho);
                if ($nf !== $s->from_loc || $nt !== $s->to_loc || $nk !== $s->kho) {
                    DB::table('trucking_shipments')->where('id', $s->id)->update([
                        'from_loc' => $nf, 'to_loc' => $nt, 'kho' => $nk,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Không thể khôi phục tên gốc từ ký hiệu — no-op.
    }
};
