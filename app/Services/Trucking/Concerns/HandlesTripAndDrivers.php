<?php

namespace App\Services\Trucking\Concerns;

use App\Models\TruckingContType;
use App\Models\TruckingCostItem;
use App\Models\TruckingCostLine;
use App\Models\TruckingChohoItem;
use App\Models\TruckingCustomer;
use App\Models\TruckingDriver;
use App\Models\TruckingLocation;
use App\Models\TruckingPayer;
use App\Models\TruckingPriceRow;
use App\Models\TruckingFuelPrice;
use App\Models\TruckingRevenueItem;
use App\Models\TruckingRouteFee;
use App\Models\TruckingSalaryItem;
use App\Models\TruckingTripCostBatch;
use App\Models\TruckingTripCostLine;
use App\Models\TruckingVehicleCost;
use App\Models\TruckingVehicleDepreciation;
use App\Models\TruckingVehicleUsage;
use App\Models\TruckingSetting;
use App\Models\TruckingAttachment;
use App\Models\TruckingPlanLink;
use App\Models\TruckingShipment;
use App\Models\TruckingShipmentWarehouse;
use App\Models\TruckingVehicleCostType;
use App\Models\TruckingAssetCategory;
use App\Support\Hashid;
use App\Models\TruckingStatement;
use App\Models\TruckingVehicle;
use App\Models\TruckingWarehouse;
use App\Models\User;
use App\Notifications\SpendRequestCreatedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/** Tach tu TruckingV2Service - nhom HandlesTripAndDrivers. */
trait HandlesTripAndDrivers
{
    // ===================================================================
    // PHÍ XE NỘI BỘ (trip cost) — gom phí tuyến + dầu + phí khác theo từng lô
    // ===================================================================

    /**
     * Chuẩn hóa 1 tuyến/danh sách kho thành KHÓA tuyến (UPPER, đúng thứ tự, nối "|").
     * Khoan dung dấu nối: dấu phẩy, " - " (gạch có khoảng trắng), mũi tên → cùng 1 khóa.
     * Nhờ vậy kho lô "TL, TS, QV" và phí tuyến "TL - TS - QV" khớp nhau dù khác dấu/khoảng trắng/hoa-thường.
     * Vẫn GIỮ thứ tự (TL→TS→QV ≠ TL→QV→TS) vì chiều tuyến có ý nghĩa.
     */
    /**
     * Hiển thị TUYẾN KHO: mỗi điểm "tên hiển thị (ký hiệu)" (nếu tên ≠ ký hiệu), nối bằng " → ".
     * Phí xe khớp theo TUYẾN KHO (cột `kho`) — KHÔNG quan tâm nơi lấy/nơi hạ.
     */
    private function khoRouteDisplay(?string $kho): string
    {
        $kho = trim((string) $kho);
        if ($kho === '') return '';
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (TruckingWarehouse::get(['name', 'code']) as $w) {
                if ($w->code) $map[mb_strtoupper(trim((string) $w->code))] = ['name' => $w->name, 'code' => $w->code];
                if ($w->name) $map[mb_strtoupper(trim((string) $w->name))] = ['name' => $w->name, 'code' => $w->code];
            }
        }
        $segs = preg_split('/\s*(?:,|→|->|–|—|\s-\s)\s*/u', $kho) ?: [];
        $out = [];
        foreach ($segs as $seg) {
            $seg = trim($seg);
            if ($seg === '') continue;
            $w = $map[mb_strtoupper($seg)] ?? null;
            if ($w && $w['name'] && $w['code'] && trim((string) $w['name']) !== trim((string) $w['code'])) {
                $out[] = $w['name'] . ' (' . $w['code'] . ')';
            } else {
                $out[] = $w['name'] ?? $seg;
            }
        }
        return implode(' → ', $out);
    }

    private function routeKey(string $s): string
    {
        $parts = preg_split('/\s*(?:,|→|->|–|—|\s-\s)\s*/u', trim($s)) ?: [];
        $parts = array_values(array_filter(array_map(function ($x) {
            return mb_strtoupper(preg_replace('/\s+/u', ' ', trim($x)));
        }, $parts), fn ($x) => $x !== ''));
        return implode('|', $parts);
    }

    /** Giá dầu (đồng/lít) áp dụng cho 1 ngày Y-m-d (dò bảng giá dầu trong RAM). */
    private function fuelPriceForDate($fuels, ?string $d): float
    {
        if (! $d) return 0;
        foreach ($fuels as $f) {
            $fd = $f->from_date?->format('Y-m-d');
            $td = $f->to_date?->format('Y-m-d');
            if ($fd && $fd <= $d && (! $td || $td >= $d)) return (float) $f->price;
        }
        return 0;
    }

    /** Nạp config nhỏ 1 lần vào RAM (route_fees/xe/giá dầu/lịch dùng xe) → tính từng lô 0 query. */
    private function tripConfigBundle(): array
    {
        // Khóa tuyến TÍNH LẠI từ text (không tin route_key đã lưu — có thể cũ/rỗng nếu chưa lưu lại).
        $routeByKey = [];
        foreach (TruckingRouteFee::all() as $rf) { $k = $this->routeKey((string) $rf->route); if ($k !== '') $routeByKey[$k] = $rf; }
        return [
            'routeByKey'  => $routeByKey,
            'vehByPlate'  => TruckingVehicle::get()->keyBy('plate'),
            'fuels'       => TruckingFuelPrice::orderByDesc('from_date')->orderByDesc('id')->get(),
            'usagesByVeh' => TruckingVehicleUsage::get()->groupBy('vehicle_id'),
        ];
    }

    /** Gợi ý phí cho 1 lô: dò route_fee theo kho, dầu theo cầu xe, giá dầu theo ngày, lái xe theo lịch dùng xe. */
    private function tripSuggest($s, array $bundle): array
    {
        $date = $s->gio_xe_ra?->format('Y-m-d');
        $rf   = $bundle['routeByKey'][$this->routeKey((string) $s->kho)] ?? null;
        $veh  = $bundle['vehByPlate'][$s->bks_vao] ?? null;
        $axle = $veh?->axle;
        $liters = $rf ? (float) ($axle === '2' ? $rf->dau_2cau : $rf->dau_1cau) : 0;
        $price  = $this->fuelPriceForDate($bundle['fuels'], $date);

        $driver = '';
        $hasUsage = isset($bundle['usagesByVeh'][$veh?->id]);
        foreach (($bundle['usagesByVeh'][$veh?->id] ?? []) as $u) {
            $uf = $u->from_date?->format('Y-m-d');
            $ut = $u->to_date?->format('Y-m-d');
            if ($date && $uf && $uf <= $date && (! $ut || $ut >= $date)) { $driver = $u->driver ?? ''; break; }
        }

        // Chẩn đoán từng mắt xích để cảnh báo đúng nguyên nhân (kế toán rà soát).
        $diag = [
            'hasBks'      => trim((string) $s->bks_vao) !== '',
            'vehFound'    => (bool) $veh,            // BKS có thuộc xe MBF nội bộ?
            'hasAxle'     => $veh && $axle !== null && $axle !== '',
            'routeFound'  => (bool) $rf,
            'driverFound' => $driver !== '',         // có lịch dùng xe phủ ngày?
            'hasUsage'    => $hasUsage,              // xe có lịch dùng xe nào chưa?
            'fuelFound'   => $price > 0,            // có bảng giá dầu phủ ngày?
        ];

        return [
            'axle'        => $axle ?? '',
            'matched'     => (bool) $rf,
            'diag'        => $diag,
            'salaryParts' => $rf ? $this->cleanSalaryParts($rf->salary_parts) : ['troCap', 'luong'],
            'sug'         => [
                'driver'     => $driver,
                'veTram'     => $rf ? $this->outMoney($rf->ve_tram) : '0',
                'tienDuong'  => $rf ? $this->outMoney($rf->tien_duong) : '0',
                'troCap'     => $rf ? $this->outMoney($rf->tro_cap) : '0',
                'phiKhac'    => $rf ? $this->outMoney($rf->phi_khac) : '0',
                'cru'        => (bool) $s->cru,
                'luong'      => ($s->cru && $rf) ? $this->outMoney($rf->luong) : '0',
                'fuelLiters' => $this->outNum($liters),
                'fuelPrice'  => $this->outMoney($price),
                'extras'     => [],
                'salaryExtras' => [],
            ],
        ];
    }

    /** Bảng giá tuyến (để chọn/áp tay khi không khớp tự động). */
    private function routeFeesOut(): array
    {
        return TruckingRouteFee::orderBy('sort')->get()->map(fn ($r) => [
            'route' => $r->route, 'routeKey' => $r->route_key,
            'veTram' => $this->outMoney($r->ve_tram), 'tienDuong' => $this->outMoney($r->tien_duong),
            'troCap' => $this->outMoney($r->tro_cap), 'phiKhac' => $this->outMoney($r->phi_khac),
            'luong' => $this->outMoney($r->luong), 'dau1' => $this->outNum($r->dau_1cau), 'dau2' => $this->outNum($r->dau_2cau),
            'salaryParts' => $this->cleanSalaryParts($r->salary_parts),
        ])->all();
    }

    /** Tùy chọn lái xe cho dropdown: value=tên (giữ tương thích), label="Tên · SĐT" để phân biệt trùng tên. */
    private function driverOptions(): array
    {
        return TruckingDriver::orderBy('sort')->orderBy('name')->get()->map(function ($d) {
            $phone = (is_array($d->phones) && count($d->phones)) ? $d->phones[0] : null;
            return ['value' => $d->name, 'label' => $phone ? ($d->name . ' · ' . $phone) : $d->name];
        })->all();
    }

    private function driversOut(): array
    {
        return $this->driverOptions();
    }

    /** Danh mục khoản cho Phí xe: chi phí khác (costItems) + khoản lương thêm (salaryItems). */
    private function tripItemOptions(): array
    {
        return [
            'costItems'   => TruckingCostItem::orderBy('sort')->orderBy('name')->pluck('name')->all(),
            'salaryItems' => TruckingSalaryItem::orderBy('sort')->orderBy('name')->pluck('name')->all(),
        ];
    }

    // ===================================================================
    // HỒ SƠ LÁI XE (rich: SĐT/ngày/tài khoản/tài liệu)
    // ===================================================================

    /** Tài liệu của 1 lái xe (từ bảng attachments tập trung). */
    private function driverDocsOut(TruckingDriver $d): array
    {
        return $this->listAttachments(TruckingDriver::class, $d->id, 'doc');
    }

    /** Danh sách lái xe đầy đủ hồ sơ (cho tab Cài đặt). */
    private function driversManaged(): array
    {
        return TruckingDriver::orderBy('sort')->orderBy('name')->get()->map(fn ($d) => [
            'id'         => $d->id,
            'hashid'     => Hashid::encode($d->id),
            'name'       => $d->name,
            'phones'     => is_array($d->phones) ? array_values($d->phones) : [],
            'birthday'   => $this->outDate($d->birthday),
            'joinedDate' => $this->outDate($d->joined_date),
            'banks'      => is_array($d->bank_accounts) ? array_values($d->bank_accounts) : [],
            'docs'       => $this->driverDocsOut($d),
        ])->all();
    }

    private function cleanList($arr): array
    {
        if (! is_array($arr)) return [];
        return array_values(array_filter(array_map(fn ($x) => trim((string) $x), $arr), fn ($x) => $x !== ''));
    }

    private function cleanBanks($arr): array
    {
        if (! is_array($arr)) return [];
        $out = [];
        foreach ($arr as $b) {
            if (! is_array($b)) continue;
            $bank = $this->str($b['bank'] ?? null); $num = $this->str($b['number'] ?? null); $holder = $this->str($b['holder'] ?? null);
            if ($bank === null && $num === null && $holder === null) continue;
            $out[] = ['bank' => $bank ?? '', 'number' => $num ?? '', 'holder' => $holder ?? ''];
        }
        return $out;
    }

    /** Lưu hồ sơ lái xe (reconcile theo id; KHÔNG đụng tài liệu — quản lý qua upload riêng). */
    public function saveDrivers(array $rows): void
    {
        DB::transaction(function () use ($rows) {
            $keepIds = [];
            foreach (array_values($rows) as $i => $r) {
                $id = $r['id'] ?? null;
                $data = [
                    'name'          => $this->str($r['name'] ?? null) ?: 'Lái xe',
                    'sort'          => $i,
                    'phones'        => $this->cleanList($r['phones'] ?? []),
                    'birthday'      => $this->inDate($r['birthday'] ?? null),
                    'joined_date'   => $this->inDate($r['joinedDate'] ?? null),
                    'bank_accounts' => $this->cleanBanks($r['banks'] ?? []),
                ];
                if (is_numeric($id) && ($d = TruckingDriver::find($id))) {
                    $d->fill($data)->save();
                } else {
                    $d = TruckingDriver::create($data + ['documents' => []]);
                }
                $keepIds[] = $d->id;
            }
            foreach (TruckingDriver::whereNotIn('id', $keepIds ?: [0])->get() as $gone) {
                $this->deleteDriverFiles($gone);
                $gone->delete();
            }
        });
    }

    /** Tải tài liệu (nhiều file) cho 1 lái xe → trả danh sách tài liệu mới. */
    public function uploadDriverDocs(TruckingDriver $d, array $files, string $type): array
    {
        $this->storeAttachments(TruckingDriver::class, $d->id, 'doc', $files, $type ?: 'Khác', "trucking/drivers/{$d->id}");
        return $this->driverDocsOut($d);
    }

    /** Xóa tài liệu lái xe theo ID attachment. */
    public function deleteDriverDoc(TruckingDriver $d, int $attachmentId): array
    {
        $this->deleteAttachment($attachmentId, TruckingDriver::class, $d->id);
        return $this->driverDocsOut($d);
    }

    private function deleteDriverFiles(TruckingDriver $d): void
    {
        foreach (TruckingAttachment::where(['owner_type' => TruckingDriver::class, 'owner_id' => $d->id])->get() as $a) {
            try { $this->disk($a->disk)->delete($a->path); } catch (\Throwable $e) {}
            $a->delete();
        }
    }

    /**
     * Trang TẠO kỳ: gom lô có "giờ xe ra" trong [from,to] + gợi ý phí.
     * Kèm "usedIn" (các kỳ đã chứa lô) để cảnh báo cộng trùng lương/dầu.
     */
    public function computeTripCosts(?string $from, ?string $to): array
    {
        $q = TruckingShipment::query()->whereNotNull('gio_xe_ra');
        if ($from) $q->whereDate('gio_xe_ra', '>=', $from);
        if ($to)   $q->whereDate('gio_xe_ra', '<=', $to);
        $ships = $q->orderBy('gio_xe_ra')->get();

        $bundle = $this->tripConfigBundle();
        $inBatch = [];
        foreach (TruckingTripCostLine::with('batch')->whereIn('shipment_id', $ships->pluck('id'))->get() as $l) {
            if ($l->shipment_id) $inBatch[$l->shipment_id][] = $l->batch?->no ?? ('#' . $l->batch_id);
        }

        $rows = [];
        foreach ($ships as $s) {
            $sg = $this->tripSuggest($s, $bundle);
            $rows[] = [
                'shipmentId' => $s->id,
                'booking'    => $s->booking ?? '',
                'route'      => trim(($s->from_loc ?? '') . ' → ' . ($s->to_loc ?? ''), ' →'),
                'kho'        => $s->kho ?? '',
                'khoRoute'   => $this->khoRouteDisplay($s->kho),   // tuyến KHO (tên + ký hiệu) — phí xe khớp theo cái này

                'bks'        => $s->bks_vao ?? '',
                'axle'       => $sg['axle'],
                'date'       => $this->outDate($s->gio_xe_ra),
                'matched'     => $sg['matched'],
                'diag'        => $sg['diag'],
                'salaryParts' => $sg['salaryParts'],
                'usedIn'      => $inBatch[$s->id] ?? [],
                'cur'        => $sg['sug'],
                'sug'        => $sg['sug'],
            ];
        }

        return ['rows' => $rows, 'routeFees' => $this->routeFeesOut(), 'drivers' => $this->driversOut()] + $this->tripItemOptions();
    }

    /** Số kỳ kế tiếp (PX-0001…) nếu người dùng không nhập. */
    private function nextTripNo(): string
    {
        return 'PX-' . str_pad((string) (TruckingTripCostBatch::max('id') + 1), 4, '0', STR_PAD_LEFT);
    }

    /** Danh sách kỳ phí xe (tóm tắt) cho trang chủ. */
    public function tripBatchesForList(): array
    {
        return TruckingTripCostBatch::withCount('lines')->orderByDesc('id')->get()->map(fn ($b) => [
            'id'    => $b->id,
            'hashid' => Hashid::encode($b->id),
            'no'    => $b->no,
            'name'  => $b->name ?? '',
            'date'  => $this->outDate($b->date),
            'from'  => $this->outDate($b->period_from),
            'to'    => $this->outDate($b->period_to),
            'count' => $b->lines_count,
            'total' => (int) round((float) $b->total),
        ])->all();
    }

    /** Snapshot 1 kỳ (cho trang Xem/Sửa). */
    public function tripBatchToArray(TruckingTripCostBatch $b): array
    {
        return [
            'id'    => $b->id,
            'hashid' => Hashid::encode($b->id),
            'no'    => $b->no,
            'name'  => $b->name ?? '',
            'date'  => $this->outDate($b->date),
            'from'  => $this->outDate($b->period_from),
            'to'    => $this->outDate($b->period_to),
            'note'  => $b->note ?? '',
            'total' => (int) round((float) $b->total),
            'rows'  => $b->lines->map(fn ($l) => [
                'lineId'     => $l->id,
                'shipmentId' => $l->shipment_id,
                'booking'    => $l->booking ?? '',
                'route'      => $l->route ?? '',
                'kho'        => $l->kho ?? '',
                'khoRoute'   => $this->khoRouteDisplay($l->kho),   // tuyến KHO (tên + ký hiệu)

                'bks'        => $l->bks ?? '',
                'axle'        => $l->axle ?? '',
                'date'        => $this->outDate($l->date),
                'matched'     => true,
                'salaryParts' => $this->cleanSalaryParts($l->salary_parts),
                'usedIn'      => [],
                'cur'        => [
                    'driver'     => $l->driver ?? '',
                    'veTram'     => $this->outMoney($l->ve_tram),
                    'tienDuong'  => $this->outMoney($l->tien_duong),
                    'troCap'     => $this->outMoney($l->tro_cap),
                    'phiKhac'    => $this->outMoney($l->phi_khac),
                    'cru'        => (bool) $l->cru,
                    'luong'      => $this->outMoney($l->luong),
                    'fuelLiters' => $this->outNum($l->fuel_liters),
                    'fuelPrice'  => $this->outMoney($l->fuel_price),
                    'extras'     => is_array($l->extras) ? $l->extras : [],
                    'salaryExtras' => is_array($l->salary_extras) ? $l->salary_extras : [],
                ],
            ])->all(),
        ];
    }

    /**
     * Ngữ cảnh "Tính lại" cho kỳ đã lưu (tải lazy): gợi ý mới theo cấu hình hiện tại,
     * keyed theo shipment_id; báo "missing" những lô đã bị xóa khỏi hệ thống.
     */
    public function tripBatchContext(TruckingTripCostBatch $b): array
    {
        $ids   = $b->lines->pluck('shipment_id')->filter()->values()->all();
        $ships = TruckingShipment::whereIn('id', $ids)->get()->keyBy('id');
        $bundle = $this->tripConfigBundle();
        $sug = [];
        foreach ($ships as $s) {
            $sg = $this->tripSuggest($s, $bundle);
            $sug[$s->id] = $sg['sug'] + ['axle' => $sg['axle'], 'matched' => $sg['matched'], 'diag' => $sg['diag'], 'salaryParts' => $sg['salaryParts']];
        }
        return [
            'sug'       => $sug,
            'missing'   => array_values(array_diff($ids, $ships->keys()->all())),
            'routeFees' => $this->routeFeesOut(),
            'drivers'   => $this->driversOut(),
        ] + $this->tripItemOptions();
    }

    /** Lưu kỳ phí xe (snapshot): xóa & tạo lại dòng, tính lại tổng từng dòng + tổng kỳ. */
    public function saveTripBatch(array $data, ?TruckingTripCostBatch $b = null): TruckingTripCostBatch
    {
        return DB::transaction(function () use ($data, $b) {
            $b ??= new TruckingTripCostBatch();
            $b->fill([
                'no'          => $this->str($data['no'] ?? null) ?: ($b->no ?? $this->nextTripNo()),
                'name'        => $this->str($data['name'] ?? null),
                'date'        => $this->inDate($data['date'] ?? null),
                'period_from' => $this->inDate($data['from'] ?? null),
                'period_to'   => $this->inDate($data['to'] ?? null),
                'note'        => $this->str($data['note'] ?? null),
            ]);
            $b->save();

            $b->lines()->delete();
            $driverIds = TruckingDriver::pluck('id', 'name');     // map tên→id (best-effort, group báo cáo bền)
            $vehIds    = TruckingVehicle::pluck('id', 'plate');
            $total = 0;
            foreach (($data['rows'] ?? []) as $i => $r) {
                $c = $r['cur'] ?? $r;
                $cleanExtra = fn ($arr) => is_array($arr) ? array_values(array_map(fn ($e) => [
                    'name'   => $this->str($e['name'] ?? null),
                    'amount' => $this->inMoney($e['amount'] ?? null) ?? 0,
                    'note'   => $this->str($e['note'] ?? null),
                ], $arr)) : [];
                $extras       = $cleanExtra($c['extras'] ?? null);
                $salaryExtras = $cleanExtra($c['salaryExtras'] ?? null);

                $veTram    = $this->inMoney($c['veTram'] ?? null) ?? 0;
                $tienDuong = $this->inMoney($c['tienDuong'] ?? null) ?? 0;
                $troCap    = $this->inMoney($c['troCap'] ?? null) ?? 0;
                $phiKhac   = $this->inMoney($c['phiKhac'] ?? null) ?? 0;
                $cru       = ! empty($c['cru']);
                $luong     = $this->inMoney($c['luong'] ?? null) ?? 0;
                $liters    = $this->inNum($c['fuelLiters'] ?? null) ?? 0;
                $price     = $this->inMoney($c['fuelPrice'] ?? null) ?? 0;
                $fuelAmount = (int) round($liters * $price);
                $extrasSum  = array_sum(array_map(fn ($e) => $e['amount'], $extras));
                $salExSum   = array_sum(array_map(fn ($e) => $e['amount'], $salaryExtras));

                // Lương nhân sự dòng = các khoản đánh dấu (luong gated CRU) + khoản lương khác
                $parts   = $this->cleanSalaryParts($r['salaryParts'] ?? null);
                $compVal = ['veTram' => $veTram, 'tienDuong' => $tienDuong, 'troCap' => $troCap, 'phiKhac' => $phiKhac, 'luong' => ($cru ? $luong : 0)];
                $salaryTotal = $salExSum;
                foreach ($parts as $k) $salaryTotal += $compVal[$k] ?? 0;

                $lineTotal = $veTram + $tienDuong + $troCap + $phiKhac + ($cru ? $luong : 0) + $fuelAmount + $extrasSum + $salExSum;
                $costTotal = $lineTotal - $salaryTotal;     // chi phí vận hành dòng
                $total += $lineTotal;

                $b->lines()->create([
                    'shipment_id' => is_numeric($r['shipmentId'] ?? null) ? $r['shipmentId'] : null,
                    'booking'     => $this->str($r['booking'] ?? null),
                    'route'       => $this->str($r['route'] ?? null),
                    'kho'         => $this->str($r['kho'] ?? null),
                    'bks'         => $this->str($r['bks'] ?? null),
                    'vehicle_id'  => $vehIds[$this->str($r['bks'] ?? null)] ?? null,
                    'axle'        => $this->str($r['axle'] ?? null),
                    'date'        => $this->inDate($r['date'] ?? null),
                    'driver'      => $this->str($c['driver'] ?? null),
                    'driver_id'   => $driverIds[$this->str($c['driver'] ?? null)] ?? null,
                    've_tram'     => $veTram,
                    'tien_duong'  => $tienDuong,
                    'tro_cap'     => $troCap,
                    'phi_khac'    => $phiKhac,
                    'cru'         => $cru,
                    'luong'       => $luong,
                    'salary_parts' => $parts,
                    'fuel_liters' => $liters,
                    'fuel_price'  => $price,
                    'fuel_amount' => $fuelAmount,
                    'extras'      => $extras,
                    'salary_extras' => $salaryExtras,
                    'line_total'  => $lineTotal,
                    'salary_total' => $salaryTotal,
                    'cost_total'  => $costTotal,
                    'note'        => $this->str($c['note'] ?? null),
                    'sort'        => $i,
                ]);
            }
            $b->total = $total;
            $b->save();
            return $b;
        });
    }

    /** Bảng giá dầu — danh sách (mới nhất trước). */
    public function fuelPrices(): array
    {
        return TruckingFuelPrice::orderByDesc('from_date')->orderByDesc('id')->get()->map(fn ($r) => [
            'id'    => $r->id,
            'from'  => $this->outDate($r->from_date),
            'to'    => $this->outDate($r->to_date),
            'price' => $this->outMoney($r->price),
            'note'  => $r->note ?? '',
        ])->all();
    }

    /** Lưu bảng giá dầu — xóa sạch & tạo lại (bảng nhỏ, không FK). */
    public function saveFuelPrices(array $rows): void
    {
        DB::transaction(function () use ($rows) {
            TruckingFuelPrice::query()->delete();
            foreach (array_values($rows) as $i => $r) {
                $from = $this->inDate($r['from'] ?? null);
                if (! $from) continue;   // bắt buộc có "từ ngày"
                TruckingFuelPrice::create([
                    'from_date' => $from,
                    'to_date'   => $this->inDate($r['to'] ?? null),
                    'price'     => $this->inMoney($r['price'] ?? null) ?? 0,
                    'note'      => $this->str($r['note'] ?? null),
                    'sort'      => $i,
                ]);
            }
        });
    }

    /** Phí tuyến đường — danh sách đã serialize. */
    public function routeFees(): array
    {
        return TruckingRouteFee::orderBy('sort')->orderBy('id')->get()->map(fn ($r) => [
            'id'        => $r->id,
            'route'     => $r->route ?? '',
            'veTram'    => $this->outMoney($r->ve_tram),
            'tienDuong' => $this->outMoney($r->tien_duong),
            'troCap'    => $this->outMoney($r->tro_cap),
            'phiKhac'   => $this->outMoney($r->phi_khac),
            'cru'         => (bool) $r->cru,
            'luong'       => $this->outMoney($r->luong),
            'salaryParts' => $this->cleanSalaryParts($r->salary_parts),
            'km'        => $this->outNum($r->km),
            'dau2'      => $this->outNum($r->dau_2cau),
            'dau1'      => $this->outNum($r->dau_1cau),
        ])->all();
    }

    /** Các key khoản phí hợp lệ có thể tính vào lương nhân sự. */
    private const SALARY_KEYS = ['veTram', 'tienDuong', 'troCap', 'phiKhac', 'luong'];

    /** Lọc danh sách khoản lương nhân sự về các key hợp lệ; null → mặc định Trợ cấp + Lương. */
    private function cleanSalaryParts($parts): array
    {
        if ($parts === null) return ['troCap', 'luong'];
        if (! is_array($parts)) return [];
        return array_values(array_intersect(self::SALARY_KEYS, array_map('strval', $parts)));
    }

    /** Lưu phí tuyến đường — xóa sạch & tạo lại (không có FK liên kết). */
    public function saveRouteFees(array $rows): void
    {
        DB::transaction(function () use ($rows) {
            TruckingRouteFee::query()->delete();
            foreach (array_values($rows) as $i => $r) {
                TruckingRouteFee::create([
                    'route'      => $this->str($r['route'] ?? null),
                    'route_key'  => $this->routeKey((string) ($r['route'] ?? '')),
                    've_tram'    => $this->inMoney($r['veTram'] ?? null) ?? 0,
                    'tien_duong' => $this->inMoney($r['tienDuong'] ?? null) ?? 0,
                    'tro_cap'    => $this->inMoney($r['troCap'] ?? null) ?? 0,
                    'phi_khac'   => $this->inMoney($r['phiKhac'] ?? null) ?? 0,
                    'cru'        => ! empty($r['cru']),
                    'luong'      => $this->inMoney($r['luong'] ?? null) ?? 0,
                    'salary_parts' => $this->cleanSalaryParts($r['salaryParts'] ?? null),
                    'km'         => $this->inNum($r['km'] ?? null) ?? 0,
                    'dau_2cau'   => $this->inNum($r['dau2'] ?? null) ?? 0,
                    'dau_1cau'   => $this->inNum($r['dau1'] ?? null) ?? 0,
                    'sort'       => $i,
                ]);
            }
        });
    }

}
