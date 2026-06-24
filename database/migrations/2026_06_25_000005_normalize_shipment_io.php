<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Chuẩn hóa cột io (Nhập/Xuất) của lô hàng đã có về ĐÚNG "Nhập" / "Xuất" / "Khác" (NFC).
 * Lý do: import Excel lưu nguyên văn ô (vd "XUẤT" chữ HOA, "nhập", hoặc Unicode NFD) → nút chọn
 * Nhập/Xuất ở popup so khớp value === option nên KHÔNG tô đậm dù danh sách vẫn hiện nhãn.
 *
 * LƯU Ý: cột io dùng collation case-insensitive → KHÔNG dùng DISTINCT/where('io', ...) để gom
 * (sẽ coi "XUẤT" == "Xuất" và bỏ sót). Phải duyệt từng dòng theo id và so BYTE chính xác.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Dò bằng tiền tố ASCII (nh/xu/kh) → nhận mọi hoa/thường, có/không dấu, NFC/NFD. Trả literal NFC.
        $canon = function (string $v): ?string {
            $l = mb_strtolower(trim($v));
            $hit = (str_starts_with($l, 'nh') || str_contains($l, 'import')) ? 'Nhập'
                : ((str_starts_with($l, 'xu') || str_contains($l, 'export')) ? 'Xuất'
                : ((str_starts_with($l, 'kh') || str_contains($l, 'other')) ? 'Khác' : null));
            if ($hit === null) return null;
            return class_exists('\Normalizer') ? \Normalizer::normalize($hit, \Normalizer::FORM_C) : $hit;
        };

        DB::table('trucking_shipments')->select('id', 'io')->orderBy('id')
            ->chunkById(500, function ($rows) use ($canon) {
                foreach ($rows as $r) {
                    $raw = (string) ($r->io ?? '');
                    if (trim($raw) === '') continue;
                    $canonical = $canon($raw);
                    if ($canonical === null) continue;             // giá trị lạ → giữ nguyên
                    if ($canonical !== $raw) {                      // so BYTE — chỉ update khi thực sự khác
                        DB::table('trucking_shipments')->where('id', $r->id)->update(['io' => $canonical]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Không khôi phục: dữ liệu gốc (hoa/thường lẫn lộn) không có giá trị tham chiếu.
    }
};
