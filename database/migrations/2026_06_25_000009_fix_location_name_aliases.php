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

            // 3) DỌN địa điểm LỖI (sau khi lô đã repoint). Mỗi TÊN chỉ nên còn 1 alias name->ký hiệu sạch.
            //    - Có alias sạch CÙNG TÊN khác → XÓA bản lỗi (nếu không còn lô/bảng giá dùng).
            //    - KHÔNG có alias sạch (vd "ICD Quế Võ") → CHUYỂN code = ký hiệu canonical (GIỮ tên
            //      để validate/import sau này vẫn nhận; saveShipment sẽ canon -> ký hiệu).
            $hasPriceRows = Schema::hasTable('trucking_price_rows');
            $refCount = function (string $code) use ($cols, $hasPriceRows) {
                $n = 0;
                foreach ($cols as $col) $n += DB::table('trucking_shipments')->where($col, $code)->count();
                if ($hasPriceRows) $n += DB::table('trucking_price_rows')->where('loc', $code)->count();
                return $n;
            };
            foreach ($locs as $l) {
                $code = trim((string) $l->code); $name = trim((string) $l->name);
                if ($code === '') continue;

                $dirty = $diac($code) || ($code === $name && ($nameToCode[$U($name)] ?? null) !== null && $nameToCode[$U($name)] !== $name);
                if (! $dirty) continue;

                $canonical = $nameToCode[$U($name)] ?? null;
                $hasSibling = $canonical !== null && $locs->first(fn ($x) => $x->id !== $l->id && $U($x->name) === $U($name) && trim((string) $x->code) === $canonical) !== null;

                if ($hasSibling) {
                    if ($refCount($code) === 0) DB::table('trucking_locations')->where('id', $l->id)->delete();
                } elseif ($canonical !== null && $canonical !== $code) {
                    DB::table('trucking_locations')->where('id', $l->id)->update(['code' => $canonical]);   // chuyển thành alias name->ký hiệu
                }
            }

            // 4) Đảm bảo alias "ICD Quế Võ" -> ICDQV tồn tại (môi trường lỡ xóa bản cũ vẫn nhận khi import).
            if (DB::table('trucking_locations')->where('code', 'ICDQV')->exists()
                && ! DB::table('trucking_locations')->whereRaw('UPPER(TRIM(name)) = ?', [$U('ICD Quế Võ')])->exists()) {
                DB::table('trucking_locations')->insert(['name' => 'ICD Quế Võ', 'code' => 'ICDQV', 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        // Không khôi phục được dữ liệu đã gộp/xóa — no-op.
    }
};
