<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DỌN ĐỊA ĐIỂM: lô hàng (from_loc/to_loc/barge_drop) lỡ lưu TÊN địa điểm (có dấu TV,
 * vd "TÂN VŨ", "HATECO", "ICD Quế Võ") thay vì KÝ HIỆU ("HPP"/"LHP"/"ICDQV").
 * Sinh ra do registerLocationCode tạo bản code==name khi chưa có alias.
 *
 * KHÔNG MẤT VẾT:
 *  1) MIGRATE LÔ trước: đổi giá trị TÊN -> KÝ HIỆU SẠCH theo bản đồ name->code (chỉ
 *     dùng location có code KHÔNG dấu). "ICD Quế Võ" -> "ICDQV" (đã chốt với user).
 *  2) Sau khi lô không còn tham chiếu, XÓA các địa điểm LỖI (code có dấu, hoặc code==name
 *     trùng tên với 1 alias ký hiệu sạch khác). Giữ nguyên các alias hợp lệ (name->code).
 *
 * Data-driven (không hardcode id) → chạy đúng trên mọi môi trường. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trucking_locations') || ! Schema::hasTable('trucking_shipments')) return;

        $diac = fn ($s) => (bool) preg_match('/[\x{00C0}-\x{1EF9}]/u', (string) $s);
        $U    = fn ($s) => mb_strtoupper(trim((string) $s));
        $cols = ['from_loc', 'to_loc', 'barge_drop'];

        DB::transaction(function () use ($diac, $U, $cols) {
            $locs = DB::table('trucking_locations')->orderBy('id')->get(['id', 'name', 'code']);

            // 1) Bản đồ TÊN -> KÝ HIỆU SẠCH (chỉ code không dấu). Alias thực (code!=name) được ưu tiên.
            $nameToCode = [];
            foreach ($locs as $l) {
                $code = trim((string) $l->code); $name = trim((string) $l->name);
                if ($code === '' || $diac($code)) continue;     // code bẩn (có dấu) → bỏ qua
                $k = $U($name);
                if (! isset($nameToCode[$k]) || $code !== $name) $nameToCode[$k] = $code;
            }
            // Override đã chốt: "ICD Quế Võ" gom về ICDQV (không có alias sạch sẵn).
            if (DB::table('trucking_locations')->where('code', 'ICDQV')->exists()) {
                $nameToCode[$U('ICD Quế Võ')] = 'ICDQV';
            }

            // 2) MIGRATE LÔ: đổi value TÊN -> KÝ HIỆU.
            foreach ($cols as $col) {
                $vals = DB::table('trucking_shipments')->whereNotNull($col)->where($col, '!=', '')->distinct()->pluck($col);
                foreach ($vals as $v) {
                    $new = $nameToCode[$U($v)] ?? null;
                    if ($new !== null && $new !== trim((string) $v)) {
                        DB::table('trucking_shipments')->where($col, $v)->update([$col => $new]);
                    }
                }
            }

            // 3) XÓA địa điểm LỖI (sau khi lô đã repoint) — chỉ khi KHÔNG còn lô/bảng giá tham chiếu.
            $hasPriceRows = Schema::hasTable('trucking_price_rows');
            foreach ($locs as $l) {
                $code = trim((string) $l->code); $name = trim((string) $l->name);
                if ($code === '') continue;

                $dirty = $diac($code);
                if (! $dirty && $code === $name) {
                    foreach ($locs as $x) {
                        if ($x->id !== $l->id && $U($x->name) === $U($name)
                            && trim((string) $x->code) !== '' && ! $diac($x->code) && trim((string) $x->code) !== $name) {
                            $dirty = true; break;   // trùng tên với 1 alias ký hiệu sạch khác
                        }
                    }
                }
                if (! $dirty) continue;

                $used = 0;
                foreach ($cols as $col) $used += DB::table('trucking_shipments')->where($col, $code)->count();
                $usedPrice = $hasPriceRows ? DB::table('trucking_price_rows')->where('loc', $code)->count() : 0;
                if ($used === 0 && $usedPrice === 0) {
                    DB::table('trucking_locations')->where('id', $l->id)->delete();
                }
            }
        });
    }

    public function down(): void
    {
        // Không khôi phục được dữ liệu đã gộp/xóa — no-op.
    }
};
