<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dọn XE TRÙNG: nhiều bản ghi trucking_vehicles cùng MỘT biển khi chuẩn hóa
 * (vd "29E72123" và "29E-72123", hoặc khác hoa/thường/khoảng trắng).
 *
 * Trùng phát sinh khi gõ nhanh biển ở Lô hàng (tạo bản "Ngoài") trong khi đã có
 * xe MBF cùng biển. Lúc lưu danh mục, reconcileVehicles cố đổi format biển xe này
 * thành biển xe kia → vi phạm unique `trucking_vehicles_plate_unique` (lỗi 1062).
 *
 * Chiến lược KHÔNG MẤT DỮ LIỆU:
 *  - Gom xe theo dạng CHUẨN HÓA (bỏ dấu cách/gạch/chấm, viết hoa).
 *  - Mỗi nhóm >1 xe: GIỮ 1 xe (ưu tiên type=MBF → có gps_ref → id nhỏ nhất),
 *    repoint mọi tham chiếu (Lô hàng vehicle_id/bks_vao/bks_ra, Phí tuyến vehicle_id/bks)
 *    sang xe giữ, rồi XÓA các bản dư. Xóa bản dư TRƯỚC khi đổi biển xe giữ để
 *    giải phóng biển có-gạch (tránh đụng unique).
 *  - Đổi biển xe giữ về dạng CÓ GẠCH chuẩn (29E72123 → 29E-72123).
 *
 * Idempotent: chạy lại khi không còn trùng = no-op.
 */
return new class extends Migration
{
    /** Chuẩn hóa để SO TRÙNG: bỏ mọi dấu cách/gạch/chấm/phẩy, viết hoa. */
    private function normKey(?string $p): string
    {
        return preg_replace('/[\s\-.,]/u', '', mb_strtoupper(trim((string) $p))) ?? '';
    }

    /** Dạng biển CHUẨN có gạch: 29E72123 → 29E-72123 (giống normalizePlate trong service). */
    private function canonical(?string $p): string
    {
        $p = mb_strtoupper(preg_replace('/[.\s]+/u', '', trim((string) $p)) ?? '');
        if ($p !== '' && ! str_contains($p, '-') && preg_match('/^(\d{2}[A-Z]{1,2})(\d+)$/', $p, $m)) {
            $p = $m[1] . '-' . $m[2];
        }
        return $p;
    }

    public function up(): void
    {
        if (! Schema::hasTable('trucking_vehicles')) return;

        $hasShipments = Schema::hasTable('trucking_shipments');
        $hasRoutePays = Schema::hasTable('trucking_route_pays');

        DB::transaction(function () use ($hasShipments, $hasRoutePays) {
            $vehicles = DB::table('trucking_vehicles')->orderBy('id')->get(['id', 'plate', 'type', 'gps_ref']);

            // Gom theo dạng chuẩn hóa
            $byNorm = [];
            foreach ($vehicles as $v) {
                $k = $this->normKey($v->plate);
                if ($k === '') continue;            // biển rỗng → bỏ qua
                $byNorm[$k][] = $v;
            }

            foreach ($byNorm as $list) {
                if (count($list) < 2) continue;     // không trùng

                // Chọn xe GIỮ: điểm = (MBF ? 2 : 0) + (có gps ? 1 : 0); cao hơn thắng, hòa thì id nhỏ
                usort($list, function ($a, $b) {
                    $sa = (($a->type === 'MBF') ? 2 : 0) + ($a->gps_ref ? 1 : 0);
                    $sb = (($b->type === 'MBF') ? 2 : 0) + ($b->gps_ref ? 1 : 0);
                    return $sa !== $sb ? ($sb <=> $sa) : ($a->id <=> $b->id);
                });

                $keep      = $list[0];
                $keepId    = $keep->id;
                $canon     = $this->canonical($keep->plate);
                $allPlates = array_values(array_unique(array_map(fn ($v) => $v->plate, $list)));
                $dupIds    = [];
                for ($i = 1; $i < count($list); $i++) $dupIds[] = $list[$i]->id;

                if ($hasShipments) {
                    // repoint vehicle_id của các bản dư → xe giữ
                    if ($dupIds) {
                        DB::table('trucking_shipments')->whereIn('vehicle_id', $dupIds)->update(['vehicle_id' => $keepId]);
                    }
                    // đồng bộ chuỗi biển (mọi format trong nhóm) → biển chuẩn có gạch
                    DB::table('trucking_shipments')->whereIn('bks_vao', $allPlates)->update(['bks_vao' => $canon]);
                    DB::table('trucking_shipments')->whereIn('bks_ra', $allPlates)->update(['bks_ra' => $canon]);
                }

                if ($hasRoutePays) {
                    if ($dupIds) {
                        DB::table('trucking_route_pays')->whereIn('vehicle_id', $dupIds)->update(['vehicle_id' => $keepId]);
                    }
                    DB::table('trucking_route_pays')->whereIn('bks', $allPlates)->update(['bks' => $canon]);
                }

                // Xóa bản dư TRƯỚC (giải phóng biển), rồi đổi biển xe giữ về dạng chuẩn
                if ($dupIds) DB::table('trucking_vehicles')->whereIn('id', $dupIds)->delete();
                if ($keep->plate !== $canon) {
                    DB::table('trucking_vehicles')->where('id', $keepId)->update(['plate' => $canon]);
                }
            }
        });
    }

    public function down(): void
    {
        // Không thể khôi phục bản ghi đã gộp/xóa — no-op.
    }
};
