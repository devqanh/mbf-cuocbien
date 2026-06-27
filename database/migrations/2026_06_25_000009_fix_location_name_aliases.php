<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DỌN CATALOG ĐỊA ĐIỂM (KHÔNG đụng lô hàng).
 *
 * Bug: registerLocationCode tạo bản LỖI `code == name` có dấu TV (vd "TÂN VŨ"→"TÂN VŨ",
 * "ICD Quế Võ"→"ICD Quế Võ") song song với alias đúng ("TÂN VŨ"→"HPP"). Khiến ký hiệu của
 * 1 tên không nhất quán → khớp giá / hiển thị ký hiệu sai.
 *
 * QUAN TRỌNG: LÔ HÀNG GIỮ NGUYÊN tên cảng cụ thể (vd "SITC ĐÌNH VŨ", "HATECO"). Ta CHỈ
 * sửa danh mục để mỗi TÊN map về đúng KÝ HIỆU (HPP/LHP/ICDQV) — ký hiệu chỉ dùng để khớp
 * giá / gộp báo cáo / hiển thị ở Lộ trình; tên cụ thể vẫn hiện ở lô.
 *
 *  - Bản lỗi CÓ alias sạch cùng tên (TÂN VŨ→HPP, HATECO→LHP…) → XÓA bản lỗi (alias đúng giữ lại).
 *  - Bản lỗi KHÔNG có alias sạch (ICD Quế Võ) → SỬA code = ký hiệu đúng (ICDQV), GIỮ tên.
 *
 * Data-driven, idempotent. KHÔNG sửa from_loc/to_loc/barge_drop của lô.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trucking_locations')) return;

        $diac = fn ($s) => (bool) preg_match('/[\x{00C0}-\x{1EF9}]/u', (string) $s);
        $U    = fn ($s) => mb_strtoupper(trim((string) $s));

        DB::transaction(function () use ($diac, $U) {
            $locs = DB::table('trucking_locations')->orderBy('id')->get(['id', 'name', 'code']);

            // Bản đồ TÊN -> KÝ HIỆU SẠCH (chỉ code KHÔNG dấu; alias thực code!=name ưu tiên).
            $nameToCode = [];
            foreach ($locs as $l) {
                $code = trim((string) $l->code); $name = trim((string) $l->name);
                if ($code === '' || $diac($code)) continue;
                $k = $U($name);
                if (! isset($nameToCode[$k]) || $code !== $name) $nameToCode[$k] = $code;
            }
            // Override đã chốt với user: "ICD Quế Võ" -> ICDQV (không có alias sạch sẵn).
            if (DB::table('trucking_locations')->where('code', 'ICDQV')->exists()) {
                $nameToCode[$U('ICD Quế Võ')] = 'ICDQV';
            }

            // DỌN bản LỖI (code có dấu, HOẶC code==name mà tên đó có ký hiệu sạch khác).
            foreach ($locs as $l) {
                $code = trim((string) $l->code); $name = trim((string) $l->name);
                if ($code === '') continue;

                $canonical = $nameToCode[$U($name)] ?? null;
                $dirty = $diac($code) || ($code === $name && $canonical !== null && $canonical !== $name);
                if (! $dirty) continue;

                $hasSibling = $canonical !== null && $locs->first(fn ($x) => $x->id !== $l->id
                    && $U($x->name) === $U($name) && trim((string) $x->code) === $canonical) !== null;

                if ($hasSibling) {
                    DB::table('trucking_locations')->where('id', $l->id)->delete();           // alias đúng giữ lại
                } elseif ($canonical !== null && $canonical !== $code) {
                    DB::table('trucking_locations')->where('id', $l->id)->update(['code' => $canonical]);   // sửa code, GIỮ tên
                }
            }

            // Đảm bảo alias "ICD Quế Võ" -> ICDQV tồn tại (môi trường lỡ xóa vẫn nhận khi import).
            if (DB::table('trucking_locations')->where('code', 'ICDQV')->exists()
                && ! DB::table('trucking_locations')->whereRaw('UPPER(TRIM(name)) = ?', [$U('ICD Quế Võ')])->exists()) {
                DB::table('trucking_locations')->insert(['name' => 'ICD Quế Võ', 'code' => 'ICDQV', 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        // Không khôi phục được bản đã xóa/sửa — no-op.
    }
};
