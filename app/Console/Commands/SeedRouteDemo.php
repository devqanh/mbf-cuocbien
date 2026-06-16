<?php

namespace App\Console\Commands;

use App\Models\TruckingCustomer;
use App\Models\TruckingLocation;
use App\Models\TruckingShipment;
use App\Models\TruckingVehicle;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Seed lô hàng DEMO để test trang Lộ trình (NGÀY VẬN HÀNH 08:00 ngày D → 08:00 ngày D+1).
 * Phủ đủ 3 ca ra_mode (self/none/other) + qua nửa đêm + biên 08:00 + ngoài khung (09:00 hôm sau).
 * Đánh dấu bằng booking prefix "RT-TEST-" để xóa sạch bằng --clear (KHÔNG đụng lô thật).
 */
class SeedRouteDemo extends Command
{
    protected $signature = 'trucking:seed-routes {date? : Ngày vận hành Y-m-d (mặc định hôm nay)} {--clear : Xóa dữ liệu demo lộ trình}';
    protected $description = 'Seed/xóa lô demo cho trang Lộ trình lái xe (ngày vận hành 08:00→08:00)';

    private const PREFIX = 'RT-TEST-';

    public function handle(): int
    {
        if ($this->option('clear')) {
            $n  = TruckingShipment::where('booking', 'like', self::PREFIX . '%')->delete();
            $nv = TruckingVehicle::where('info->demo_route', true)->delete();   // chỉ xóa xe DEMO do seed tạo
            $this->info("Đã xóa {$n} lô demo lộ trình" . ($nv ? " + {$nv} xe demo" : '') . '.');
            return self::SUCCESS;
        }

        $date = (string) ($this->argument('date') ?: now()->format('Y-m-d'));
        try { $D = Carbon::parse($date . ' 00:00:00'); } catch (\Throwable) { $this->error('Ngày không hợp lệ'); return self::FAILURE; }

        // dọn demo cũ trước khi seed lại (idempotent)
        TruckingShipment::where('booking', 'like', self::PREFIX . '%')->delete();

        // QUAN TRỌNG: lấy biển số xe ĐÃ CẤU HÌNH trong trucking (cai-dat#vehicles) để khớp "Xe MBF / matched".
        // Plate ảo sẽ không khớp xe hệ thống → demo nên dùng plate thật.
        $fleet = TruckingVehicle::where('kind', 'vehicle')->orderBy('id')->pluck('plate')
            ->map(fn ($p) => trim((string) $p))->filter()->values()->all();
        if (empty($fleet)) {
            // Chưa có xe → TẠO đội xe DEMO (đánh dấu info.demo_route để --clear xóa, không đụng xe thật).
            $demoPlates = ['15C-200.01', '15C-200.02', '15C-200.03', '15C-200.04', '15C-200.05'];
            foreach ($demoPlates as $k => $pl) {
                TruckingVehicle::create([
                    'plate' => $pl, 'kind' => 'vehicle', 'type' => 'MBF',
                    'axle'  => $k % 2 === 0 ? '3' : '2',
                    'info'  => ['demo_route' => true],
                ]);
            }
            $fleet = $demoPlates;
            $this->warn('Chưa có xe cấu hình → đã tạo ' . count($demoPlates) . ' xe DEMO (đánh dấu demo_route; --clear sẽ xóa). Nên thêm xe THẬT + ánh xạ GPS cho production.');
        }
        // cycle qua các plate thật — nếu ít xe, các chặng sẽ dồn vào xe có sẵn (vẫn đúng để test matched).
        $p = fn ($n) => $fleet[$n % count($fleet)];
        $this->line('Dùng ' . count($fleet) . ' biển số xe: ' . implode(', ', $fleet));

        // Dùng KHÁCH HÀNG thật đã cấu hình (vd Canon); chưa có thì tạo KH demo.
        $cust = TruckingCustomer::orderBy('id')->first() ?: TruckingCustomer::create(['name' => 'KH Test Lộ Trình']);

        // Địa điểm đầu/cuối phải là MÃ ĐỊA ĐIỂM đã cấu hình (cai-dat#locations) để khớp from/to_location_id.
        // ICDQV (ICD Quế Võ) → HPP (Hải Phòng). Fallback nếu chưa có mã đó.
        $loc = fn (string $code, string $alt) => TruckingLocation::where('code', $code)->exists() ? $code
            : (optional(TruckingLocation::where('code', $alt)->first())->code ?? optional(TruckingLocation::orderBy('id')->first())->code ?? $code);
        $fromCode = $loc('ICDQV', 'ICDTP');
        $toCode   = $loc('HPP', 'LHP');

        $svc = app(\App\Services\TruckingV2Service::class);
        $i = 0;
        $mk = function (array $a) use ($cust, $fromCode, $toCode, $svc, &$i) {
            $i++;
            $s = TruckingShipment::create(array_merge([
                'sheet'       => 'icd',
                'customer_id' => $cust->id,
                'booking'     => self::PREFIX . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'cont_type'   => '40HC',
                'io'          => 'Nhập',
                'ra_mode'     => 'self',
                'from_loc'    => $fromCode,
                'to_loc'      => $toCode,
                'vat_rate'    => '0',
                'ghi_chu'     => 'DEMO lộ trình',
            ], $a));
            // ÁNH XẠ danh mục: from/to_location_id, vehicle_id, kho pivot, tổng tiền.
            $svc->recomputeShipmentDerived($s);
            return $s->refresh();
        };
        // giờ trong NGÀY VẬN HÀNH [08:00 D, 08:00 D+1): h>=8 là ngày D, h<8 là HÔM SAU (qua nửa đêm).
        $at = fn ($h, $m = 0) => ($h >= 8 ? (clone $D) : (clone $D)->addDay())->setTime($h, $m);

        // ===== Xe A (plate thật #0): 2 hoạt động, qua nửa đêm =====
        $mk(['bks_vao' => $p(0), 'cont_no' => 'TEST1110001', 'kho' => 'TL → TS', 'gio_xe_ra' => $at(21, 0), 'gio_xe_den' => $at(20, 10)]);
        $mk(['bks_vao' => $p(0), 'cont_no' => 'TEST1110002', 'kho' => 'QV → TL', 'gio_xe_ra' => $at(2, 30), 'gio_xe_den' => $at(1, 0)]);

        // ===== Xe B (plate thật #1): RA XE KHÔNG KÉO CONT (none) + 1 self sáng =====
        // Có gio_xe_den để hiển thị "đã vào nhà máy TS lúc 21:30" rồi 22:15 ra xe không cont.
        $mk(['bks_vao' => $p(1), 'cont_no' => 'TEST2220003', 'kho' => 'TS', 'ra_mode' => 'none', 'gio_xe_den' => $at(21, 30), 'gio_xe_ra_xe' => $at(22, 15)]);
        $mk(['bks_vao' => $p(1), 'cont_no' => 'TEST2220004', 'kho' => 'QV → TS', 'gio_xe_ra' => $at(5, 0), 'gio_xe_den' => $at(4, 0)]);

        // ===== KÉO CONT KHÁC RA (other) — diễn tả rõ "xe ra kéo cont khác lúc mấy giờ" =====
        // Cont B do XE #2 mang vào, nhưng khi RA thì XE #0 kéo B ra lúc 23:45 (B.bks_ra = xe #0; B.gio_xe_ra = 23:45).
        $b = $mk(['bks_vao' => $p(2), 'cont_no' => 'TESTB00B001', 'kho' => 'TL → QV', 'gio_xe_ra' => $at(23, 45), 'bks_ra' => $p(0), 'gio_xe_den' => $at(22, 0)]);
        // XE #0 vào với cont A (A chờ lại, KHÔNG có giờ ra) — khi ra kéo cont KHÁC (B) ra.
        // ra_mode=other + ra_other_id=B ⇒ lộ trình XE #0 có thêm mốc "23:45 · Kéo cont khác B ra".
        $mk(['bks_vao' => $p(0), 'cont_no' => 'TESTA00A001', 'kho' => 'TS → TL', 'ra_mode' => 'other', 'ra_other_id' => $b->id, 'gio_xe_den' => $at(23, 0)]);
        // XE #2 vẫn có lộ trình riêng (1 self exit của chính nó) — nó chỉ MANG B vào, không tự kéo B ra
        // (B là target → self-leg của B bị bỏ, exit của B chỉ thuộc xe kéo #0).
        $mk(['bks_vao' => $p(2), 'cont_no' => 'TEST2220C01', 'kho' => 'QV → TS', 'gio_xe_ra' => $at(19, 0), 'gio_xe_den' => $at(18, 0)]);

        // ===== Biên 08:00 (đúng đầu ngày vận hành — TÍNH) =====
        $mk(['bks_vao' => $p(4), 'cont_no' => 'TEST6660007', 'kho' => 'QV', 'gio_xe_ra' => $at(8, 0), 'gio_xe_den' => (clone $D)->setTime(7, 30)]);

        // ===== Ban ngày trong khung: ra 14:00 (vẫn trong [08:00,08:00) — TÍNH) =====
        $mk(['bks_vao' => $p(0), 'cont_no' => 'TEST5550008', 'kho' => 'TL', 'gio_xe_ra' => $at(14, 0), 'gio_xe_den' => $at(13, 0)]);

        // ===== NGOÀI khung: ra 09:00 HÔM SAU (≥ 08:00 D+1 — sang ngày kế, KHÔNG tính) =====
        $mk(['bks_vao' => $p(0), 'cont_no' => 'TEST7770010', 'kho' => 'TL', 'gio_xe_ra' => (clone $D)->addDay()->setTime(9, 0)]);

        // ===== Xe NGOÀI hệ thống (plate ảo, KHÔNG khớp cấu hình) — test nhãn "(ngoài hệ thống)" =====
        $mk(['bks_vao' => '99X-000.00', 'cont_no' => 'TEST9990009', 'kho' => 'TS', 'gio_xe_ra' => $at(23, 0), 'gio_xe_den' => $at(22, 30)]);

        $this->info("Đã seed {$i} lô demo cho NGÀY VẬN HÀNH {$D->format('d/m/Y')} 08:00 → " . (clone $D)->addDay()->format('d/m') . " 08:00.");
        $this->line('Mở /trucking-v2/lo-trinh, chọn ngày ' . $D->format('d/m/Y') . ' để xem. Xóa: php artisan trucking:seed-routes --clear');
        return self::SUCCESS;
    }
}
