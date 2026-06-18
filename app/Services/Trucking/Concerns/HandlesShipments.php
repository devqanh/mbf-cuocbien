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

/** Tách từ TruckingV2Service — nhóm HandlesShipments. */
trait HandlesShipments
{
    /** @var array<string,int>|null  lowercase(code|name) => warehouse_id — memoize / request. */
    private ?array $whIdMapCache = null;

    /** @var array<string,int>|null  lowercase(name) => driver_id — memoize / request. */
    private ?array $driverIdMapCache = null;

    /** @var array<string,int>|null  lowercase(plate) => vehicle_id — memoize / request. */
    private ?array $vehicleIdMapCache = null;

    /** @var array<string,int>|null  lowercase(name) => customer_id — memoize / request. */
    private ?array $customerIdMapCache = null;

    /**
     * Mỗi danh mục = 1 bảng riêng. Map: cfgKey => [modelClass, priced?, coded?, colored?].
     * priced  = có đơn giá mặc định;
     * coded   = false HOẶC tên khóa map ký hiệu trong cfg (vd 'locationCode', 'warehouseCode');
     * colored = có màu "theo dõi" cấp danh mục (dùng cho filter lọc khoản chưa điền tiền).
     */
    private function lookups(): array
    {
        return [
            'locations'  => [TruckingLocation::class,    false, 'locationCode',  false],
            'payers'     => [TruckingPayer::class,       false, false,           false],
            'contTypes'  => [TruckingContType::class,    false, false,           false],
            'warehouses' => [TruckingWarehouse::class,   false, 'warehouseCode', false],
            'drivers'    => [TruckingDriver::class,      false, false,           false],
            'costItems'  => [TruckingCostItem::class,    true,  false,           true],
            'choHoItems' => [TruckingChohoItem::class,   true,  false,           false],
            'revItems'   => [TruckingRevenueItem::class, true,  false,           false],
            'salaryItems' => [TruckingSalaryItem::class, false, false,           false],
            'vehicleCostTypes' => [TruckingVehicleCostType::class, false, false,  false],
            'assetCategories'  => [TruckingAssetCategory::class,   false, false,  false],
        ];
    }

    // ===================================================================
    // BOOTSTRAP — tải toàn bộ dữ liệu cho 1 lần khởi tạo app
    // ===================================================================
    public function bootstrap(): array
    {
        return [
            'hph' => $this->shipments(TruckingShipment::SHEET_HPH),
            'icd' => $this->shipments(TruckingShipment::SHEET_ICD),
            'cfg' => $this->config(),
            'ke'  => $this->statements(),
        ];
    }

    /** @return array<int,array> Danh sách lô của 1 sheet, đã serialize. */
    public function shipments(string $sheet): array
    {
        return TruckingShipment::ofSheet($sheet)
            ->with(['customer', 'costLines', 'revenueLines', 'payments'])
            ->orderBy('id')
            ->get()
            ->map(fn ($s) => $this->shipmentToArray($s))
            ->all();
    }

    /**
     * Trang Lô hàng — phân trang SERVER-SIDE (20 lô/trang) + tìm kiếm + lọc + sắp xếp.
     * Aggregate (tổng chi phí, đếm bộ lọc, follow theo màu) tính TOÀN CỤC trên tập đã
     * tìm (q), độc lập với lựa chọn lọc/follow đang chọn — để con số luôn đúng dù chỉ
     * hiển thị 1 trang. $p: page,q,filter(all|out|notout),follow(all|any|missing|#hex),
     * sort(default|customer|cost),dir(1|-1),all(xuất Excel = bỏ phân trang).
     *
     * @return array{data:array,page:int,perPage:int,total:int,lastPage:int,totalCost:int,filterCounts:array,followStats:array}
     */
    public function pagedShipments(string $sheet, array $p): array
    {
        $perPage = 20;
        $page    = max(1, (int) ($p['page'] ?? 1));
        $q       = trim((string) ($p['q'] ?? ''));
        $filter  = (string) ($p['filter'] ?? 'all');
        $filter  = in_array($filter, ['all', 'out', 'notout'], true) ? $filter : 'all';
        $follow  = (string) ($p['follow'] ?? 'all');
        $sortKey = (string) ($p['sort'] ?? 'default');
        $sortKey = in_array($sortKey, ['default', 'customer', 'cost'], true) ? $sortKey : 'default';
        $dir     = ((int) ($p['dir'] ?? 1)) < 0 ? 'desc' : 'asc';
        $all     = ! empty($p['all']);

        // Khoản chi phí "theo dõi" (có màu trong danh mục) → tên + hex
        $followItems = TruckingCostItem::whereNotNull('color')->where('color', '!=', '')->get(['name', 'color']);
        $followNames = $followItems->pluck('name')->all();
        $nameHex     = $followItems->mapWithKeys(fn ($i) => [$i->name => $this->colorHex($i->color)])->all();

        // Áp tìm kiếm (khách / container / booking / invoice / tờ khai) lên 1 builder lô.
        $applySearch = function ($b) use ($q) {
            if ($q === '') return;
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $b->where(function ($w) use ($like) {
                $w->where('cont_no', 'like', $like)
                  ->orWhere('booking', 'like', $like)
                  ->orWhere('inv', 'like', $like)
                  ->orWhere('declaration_no', 'like', $like)
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like));
            });
        };
        // Builder lô của tập "đã tìm" (chỉ áp q) — dùng cho aggregate.
        $searched = function () use ($sheet, $applySearch) {
            $b = TruckingShipment::ofSheet($sheet);
            $applySearch($b);
            return $b;
        };

        // --- Aggregate toàn cục trên tập đã tìm ---
        $totalCost = (int) round((float) TruckingCostLine::whereIn('shipment_id', $searched()->select('id'))->sum('amount'));

        // "Đã ra" = CONT này có GIỜ XE RA (gio_xe_ra) của chính nó. CHỈ xét gio_xe_ra — không xét bks_ra
        // (BKS có thể chỉ là xe kéo, chưa chắc cont đã ra) cũng không xét việc xe kéo cont KHÁC ra.
        $applyOut = function ($q) {
            return $q->whereNotNull('gio_xe_ra')->where('gio_xe_ra', '!=', '');
        };
        $applyNotOut = function ($q) {
            return $q->where(fn ($a) => $a->whereNull('gio_xe_ra')->orWhere('gio_xe_ra', ''));
        };

        $allCount = $searched()->count();
        $outCount = $applyOut($searched())->count();
        $filterCounts = ['all' => $allCount, 'out' => $outCount, 'notout' => $allCount - $outCount];

        $followStats = $this->followStats($searched(), $followNames, $nameHex);

        // --- Danh sách hiển thị: q + lọc + follow + sort + phân trang ---
        $list = $searched();
        if ($filter === 'out') {
            $applyOut($list);
        } elseif ($filter === 'notout') {
            $applyNotOut($list);
        }
        if ($followNames) {
            if ($follow === 'any') {
                $list->whereHas('costLines', fn ($c) => $c->whereIn('item', $followNames));
            } elseif ($follow === 'missing') {
                $list->whereHas('costLines', fn ($c) => $c->whereIn('item', $followNames)
                    ->where(fn ($x) => $x->whereNull('amount')->orWhere('amount', 0)));
            } elseif (str_starts_with($follow, '#')) {
                $names = array_keys(array_filter($nameHex, fn ($h) => $h === $follow));
                $list->whereHas('costLines', fn ($c) => $c->whereIn('item', $names ?: ['']));
            }
        }

        $total    = $all ? $allCount : $list->count();
        $lastPage = max(1, (int) ceil(($total ?: 1) / $perPage));
        $page     = min($page, $lastPage);

        if ($sortKey === 'customer') {
            $list->leftJoin('trucking_customers', 'trucking_customers.id', '=', 'trucking_shipments.customer_id')
                 ->orderBy('trucking_customers.name', $dir)
                 ->select('trucking_shipments.*');
        } elseif ($sortKey === 'cost') {
            $list->withSum('costLines as cost_total', 'amount')->orderBy('cost_total', $dir);
        } else {
            $list->orderBy('id', 'desc');   // mặc định: lô MỚI NHẬP lên đầu (id giảm dần)
        }

        $list->with(['customer', 'costLines', 'revenueLines', 'payments']);
        if (! $all) $list->forPage($page, $perPage);
        $data = $list->get()->map(fn ($s) => $this->shipmentToArray($s))->all();

        return [
            'data'         => $data,
            'page'         => $page,
            'perPage'      => $perPage,
            'total'        => $total,
            'lastPage'     => $lastPage,
            'totalCost'    => $totalCost,
            'filterCounts' => $filterCounts,
            'followStats'  => $followStats,
            'sibs'         => $this->siblingsList($sheet),
        ];
    }

    /**
     * Danh sách rút gọn mọi lô (id + cont + booking) cho picker "ra hộ" — KHÔNG kèm
     * dòng con nên rất nhẹ; đủ để chọn lô khác cầm container ra dù lô đó ở trang khác.
     *
     * @return array<int,array{id:int,contNo:string,booking:string}>
     */
    public function siblingsList(string $sheet): array
    {
        return TruckingShipment::ofSheet($sheet)->orderBy('id')
            ->toBase()->get(['id', 'cont_no', 'booking', 'gio_xe_ra', 'bks_ra'])   // không hydrate model — chỉ cột cần
            ->map(fn ($s) => [
                'id' => $s->id, 'contNo' => $s->cont_no ?? '', 'booking' => $s->booking ?? '',
                // datetime thô "Y-m-d H:i:s" → "Y-m-dTH:i" cho DTField; bksRa giữ chuỗi
                'gioXeRa' => $s->gio_xe_ra ? str_replace(' ', 'T', substr((string) $s->gio_xe_ra, 0, 16)) : '',
                'bksRa'   => $s->bks_ra ?? '',
            ])
            ->all();
    }

    /**
     * LỘ TRÌNH 1 NGÀY VẬN HÀNH (08:00 ngày $date → 08:00 hôm sau, tức 07:59 hôm sau là hết),
     * gom theo biển số XE VÀO (bks_vao) — mỗi xe 1 lộ trình liền mạch trong ngày.
     * Mỗi lô = 1 hoạt động dưới bks_vao, xếp theo GIỜ XE RA:
     *  - self  : "Lấy cont [X] ra" tại gio_xe_ra.
     *  - none  : "Ra xe (không kéo cont)" tại gio_xe_ra_xe.
     *  - other : "Kéo cont khác ([Y]) ra" tại gio_xe_ra của lô ra_other_id (cont X chờ).
     * Chỉ tính hoạt động có giờ ra TRONG khung [08:00, 08:00 hôm sau).
     */
    public function routeTripByDate(string $date): array
    {
        try { $start = Carbon::parse($date . ' 08:00:00'); }
        catch (\Throwable) { $start = Carbon::today()->setTime(8, 0); }
        $end = (clone $start)->addDay();   // 08:00 sáng hôm sau (07:59 là hết khung)
        $inWin = fn ($dt) => $dt && $dt->gte($start) && $dt->lt($end);

        // Lô có giờ ra cont HOẶC giờ ra xe trong khung (self + none) — nới biên 1 ngày 2 đầu cho chắc.
        $cand = TruckingShipment::with('customer')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('gio_xe_ra', [$start, $end])
                  ->orWhereBetween('gio_xe_ra_xe', [$start, $end]);
            })->get();

        // Lô "kéo cont khác ra" (other) — CHỈ load lô có target nằm TRONG cand (giờ ra trong khung).
        // Tránh load toàn bảng ra_mode='other' khi data lớn (10k–50k lô).
        $candIds = $cand->pluck('id')->all();
        $others  = $candIds
            ? TruckingShipment::with('customer')->where('ra_mode', 'other')->whereIn('ra_other_id', $candIds)->get()
            : collect();
        $targets = $cand->keyBy('id');   // target chắc chắn nằm trong cand (gio_xe_ra ∈ window)
        $targetIds = $others->pluck('ra_other_id')->filter()->unique()->values()->all();

        $legs = [];
        // $rs = lô có TUYẾN mà xe THỰC SỰ chạy (self/none = chính nó; other = lô bị kéo ra hộ).
        $mk = function (TruckingShipment $s, Carbon $t, string $mode, ?TruckingShipment $rs = null, array $extra = []) {
            $rs = $rs ?: $s;
            // Chuỗi điểm hành trình: Nơi lấy → các Kho → Nơi hạ (bỏ điểm trùng liền kề).
            $pts = [];
            $add = function ($label, $kind) use (&$pts) {
                $label = trim((string) $label);
                if ($label === '' || ($pts && end($pts)['label'] === $label)) return;
                $pts[] = ['label' => $label, 'kind' => $kind];
            };
            $add($rs->from_loc, 'pickup');
            foreach ($this->khoPoints($rs->kho) as $kp) $add($kp, 'kho');
            $add($rs->to_loc, 'drop');

            return array_merge([
                'bks'        => trim((string) $s->bks_vao),
                'vehicleId'  => $s->vehicle_id ?: null,            // ưu tiên id để map xe (bền hơn match plate string)
                'sortTs'     => $t->getTimestamp(),
                'time'       => (int) ($t->getTimestamp() * 1000),
                'timeLabel'  => $t->format('H:i'),                  // giờ ra (format sẵn — tránh lệch tz trình duyệt)
                'gioDen'     => $s->gio_xe_den ? (int) ($s->gio_xe_den->getTimestamp() * 1000) : null,   // giờ xe đến (vào kho lấy/giao)
                'gioDenLabel'=> $s->gio_xe_den ? $s->gio_xe_den->format('H:i') : null,
                'mode'     => $mode,                              // self | none | other
                'cont'     => $s->cont_no ?? '',
                'bksRa'    => $s->bks_ra ?? '',
                'points'   => $pts,                               // hành trình điểm
                'route'    => $this->khoRouteDisplay($rs->kho) ?: ($rs->kho ?? ''),
                'kho'      => $rs->kho ?? '', 'cru' => (bool) $rs->cru,   // cho tính "chi theo ngày" (khớp phí tuyến + lương CRU)
                'from'     => $rs->from_loc ?? '', 'to' => $rs->to_loc ?? '',
                'customer' => $s->customer?->name ?? '',
                'booking'  => $s->booking ?? '',
                'hashid'   => \App\Support\Hashid::encode($s->id),
            ], $extra);
        };

        // Cont bị "kéo khác ra" (là target của 1 lô other): EXIT của nó thuộc XE KÉO (qua leg other),
        // KHÔNG phải xe vào nó → BỎ self-leg để khỏi gắn nhầm xe / đếm 2 lần.
        $targetSet = array_flip(array_map('intval', $targetIds));
        foreach ($cand as $s) {
            if (trim((string) $s->bks_vao) === '') continue;
            $mode = $s->ra_mode ?? 'self';
            $isSelf = ! in_array($mode, ['none', 'other'], true);   // self + null/'' (legacy)
            if ($isSelf && ! isset($targetSet[(int) $s->id]) && $inWin($s->gio_xe_ra)) {
                $legs[] = $mk($s, $s->gio_xe_ra, 'self');
            }
            if ($mode === 'none' && $inWin($s->gio_xe_ra_xe)) $legs[] = $mk($s, $s->gio_xe_ra_xe, 'none');
        }
        foreach ($others as $s) {
            if (trim((string) $s->bks_vao) === '') continue;
            $t = $targets->get($s->ra_other_id);
            if ($t && $inWin($t->gio_xe_ra)) {
                // refCont = cont KHÁC bị kéo ra; refBksVao = xe đã đưa cont đó VÀO trước đó; refBksRa = xe kéo cont đó ra (= xe hiện tại).
                $legs[] = $mk($s, $t->gio_xe_ra, 'other', $t, [
                    'refCont'    => $t->cont_no ?? '',
                    'refBksVao'  => trim((string) $t->bks_vao),
                    'refBksRa'   => trim((string) ($t->bks_ra ?: $s->bks_vao)),
                ]);
            }
        }

        // Gom theo bks_vao + map xe hệ thống QUA vehicle_id (Tier-2 ref đã chốt khi lưu) —
        // bền hơn so với match plate string (khử rủi ro typo/whitespace). Fallback theo plate
        // cho lô legacy chưa có vehicle_id.
        $vehIds = array_values(array_unique(array_filter(array_map(fn ($l) => $l['vehicleId'] ?? null, $legs))));
        $vehById = $vehIds ? TruckingVehicle::whereIn('id', $vehIds)->get(['id', 'plate', 'type', 'axle'])->keyBy('id') : collect();
        $legacyPlates = array_values(array_unique(array_map(fn ($l) => $l['bks'], array_filter($legs, fn ($l) => empty($l['vehicleId'])))));
        $vehByPlate = $legacyPlates ? TruckingVehicle::whereIn('plate', $legacyPlates)->get(['plate', 'type', 'axle'])->keyBy('plate') : collect();
        // Phí tuyến (khớp theo TẬP node Cảng+Kho — không thứ tự) + giá dầu + chi đã lưu cho ngày này.
        $dateStr = $start->format('Y-m-d');
        $rfBySet = [];
        foreach (\App\Models\TruckingRouteFee::all() as $rf) { $k = $this->routeNodeKey($this->routeStringNodes((string) $rf->route)); if ($k !== '') $rfBySet[$k] = $rf; }
        $fuels = TruckingFuelPrice::orderByDesc('from_date')->orderByDesc('id')->get();
        $paysByBks = \App\Models\TruckingRoutePay::whereDate('work_date', $dateStr)->get()->keyBy('bks');

        $byBks = [];
        foreach ($legs as $l) { $byBks[$l['bks']][] = $l; }
        $trucks = [];
        foreach ($byBks as $bks => $ls) {
            usort($ls, fn ($a, $b) => $a['sortTs'] <=> $b['sortTs']);
            $vid = $ls[0]['vehicleId'] ?? null;
            if ($vid && $vehById->has($vid)) {
                $type = $vehById[$vid]->type ?? null;
                $axle = $vehById[$vid]->axle ?? null;
                $matched = true;
            } else {
                $type = $vehByPlate[$bks]->type ?? null;
                $axle = $vehByPlate[$bks]->axle ?? null;
                $matched = $vehByPlate->has($bks);
            }
            // "Chi theo ngày": mỗi chuyến (leg) khớp 1 phí tuyến theo TẬP kho → cộng các khoản tick chi theo ngày.
            $payItems = []; $payTotal = 0;
            foreach ($ls as $l) {
                foreach ($this->legDailyCharge($l, $axle, $rfBySet, $fuels, $dateStr) as $it) {
                    $payItems[] = $it; $payTotal += $it['amount'];
                }
            }
            $pay = $paysByBks->get($bks);
            $trucks[] = ['bks' => $bks, 'matched' => $matched, 'type' => $type, 'axle' => $axle, 'legs' => $ls,
                'payItems' => $payItems, 'payTotal' => $payTotal,
                'payDriver' => $pay?->driver ?? '', 'paid' => (bool) ($pay?->paid ?? false), 'paidDate' => $pay ? $this->outDate($pay->paid_date) : ''];
        }
        usort($trucks, fn ($a, $b) => count($b['legs']) <=> count($a['legs']) ?: strcmp($a['bks'], $b['bks']));

        return [
            'date'  => $start->format('Y-m-d'),
            'start' => (int) ($start->getTimestamp() * 1000),
            'end'   => (int) ($end->getTimestamp() * 1000),
            'startLabel' => $start->format('d/m'),   // format sẵn theo tz ứng dụng (tránh lệch ngày trên trình duyệt)
            'endLabel'   => $end->format('d/m'),
            'trucks' => $trucks,
            'totalLegs' => count($legs),
        ];
    }

    /** Tách 1 chuỗi tuyến (Cảng/Kho) thành các node (cùng dấu phân tách với route fee). */
    private function routeStringNodes(string $s): array
    {
        $parts = preg_split('/\s*(?:,|→|->|–|—|\s-\s)\s*/u', trim($s)) ?: [];
        return array_values(array_filter(array_map('trim', $parts), fn ($x) => $x !== ''));
    }

    /**
     * Khóa tuyến theo TẬP node (Cảng + Kho) — KHÔNG quan tâm thứ tự, chuẩn hóa mỗi node
     * về KÝ HIỆU qua normalizedCodeMap (khớp cả tên lẫn mã, bỏ dấu/dấu cách):
     * "ICD Quế Võ"="ICDQV"; A→B→C ≡ A→C→B.
     */
    private function routeNodeKey(array $labels): string
    {
        $codeMap = $this->normalizedCodeMap();
        $norm = fn ($v) => mb_strtoupper(preg_replace('/\s+/u', '', trim(\Illuminate\Support\Str::ascii((string) $v))) ?? '');
        $set = [];
        foreach ($labels as $l) { $c = $codeMap[$norm($l)] ?? $norm($l); if ($c !== '') $set[$c] = true; }
        $set = array_keys($set);
        sort($set);
        return implode('|', $set);
    }

    /** Các khoản "chi theo ngày" của 1 chuyến (leg): Phí tuyến khớp (TẬP Cảng+Kho) + dầu × giá dầu theo ngày. */
    private function legDailyCharge(array $leg, ?string $axle, array $rfBySet, $fuels, string $date): array
    {
        // Chuỗi node thực tế của chuyến: Nơi lấy (cảng) → các Kho → Nơi hạ (cảng).
        $nodes = array_merge([$leg['from'] ?? ''], $this->khoPoints($leg['kho'] ?? ''), [$leg['to'] ?? '']);
        $rf = $rfBySet[$this->routeNodeKey($nodes)] ?? null;
        if (! $rf) return [];
        $parts = $this->cleanSalaryParts($rf->salary_parts);
        if (! $parts) return [];
        $route = trim((string) $rf->route) ?: ($this->khoRouteDisplay($leg['kho'] ?? '') ?: ($leg['kho'] ?? ''));
        $items = [];
        $push = function ($key, $label, $amount) use (&$items, $parts, $route, $leg) {
            $amount = (int) round((float) $amount);
            if (! in_array($key, $parts, true) || $amount <= 0) return;
            $items[] = ['key' => $key, 'label' => $label, 'amount' => $amount, 'route' => $route, 'cont' => $leg['cont'] ?? ''];
        };
        $push('veTram', 'Vé trạm', $rf->ve_tram);
        $push('tienDuong', 'Tiền đường', $rf->tien_duong);
        $push('troCap', 'Trợ cấp', $rf->tro_cap);
        // Lương: xe KHÔNG kéo cont ra (mode none) → "Lương không CRU"; có kéo cont ra (self/other) → "Lương CRU".
        $noPull = ($leg['mode'] ?? '') === 'none';
        $push('luong', $noPull ? 'Lương (không kéo cont)' : 'Lương', $noPull ? $rf->luong_no_cru : $rf->luong);
        $is2 = ($axle === '2');                                  // chọn lít dầu theo SỐ CẦU xe
        $dauKey = $is2 ? 'dau2' : 'dau1';
        if (in_array($dauKey, $parts, true)) {
            $liters = (float) ($is2 ? $rf->dau_2cau : $rf->dau_1cau);
            $push($dauKey, 'Dầu ' . ($is2 ? '2 cầu' : '1 cầu'), $liters * $this->fuelPriceForDate($fuels, $date));
        }
        return $items;
    }

    /** Lưu chi cho lái xe theo ngày + xe (chỉ lưu lái nhận + đã chi; tiền tự tính từ phí tuyến). */
    public function saveRoutePay(string $date, string $bks, array $data): array
    {
        $bks = trim($bks);
        if ($bks === '' || trim($date) === '') return ['ok' => false, 'message' => 'Thiếu ngày/biển số'];
        $driver = $this->str($data['driver'] ?? null);
        $dkey = $driver ? mb_strtolower(preg_replace('/\s+/u', ' ', trim($driver)) ?? '') : '';
        $paid = ! empty($data['paid']);
        $veh = TruckingVehicle::where('plate', $bks)->first();
        \App\Models\TruckingRoutePay::updateOrCreate(
            ['work_date' => $date, 'bks' => $bks],
            [
                'vehicle_id' => $veh?->id,
                'driver'     => $driver ?: null,
                'driver_id'  => $dkey !== '' ? ($this->driverIdMap()[$dkey] ?? null) : null,
                'paid'       => $paid,
                'paid_date'  => $paid ? ($this->inDate($data['paidDate'] ?? null) ?: $date) : null,
                'note'       => $this->str($data['note'] ?? null),
                'updated_by' => auth()->id(),
            ]
        );
        return ['ok' => true];
    }

    /**
     * Thống kê cờ theo dõi trên tập lô (builder) — gom theo màu, đếm lô có cờ &
     * lô có cờ nhưng CHƯA điền tiền. Chỉ nạp đúng các dòng chi phí thuộc khoản
     * "theo dõi" (không nạp toàn bộ dòng con) → nhẹ.
     */
    private function followStats($shipQuery, array $followNames, array $nameHex): array
    {
        if (! $followNames) return ['anyShips' => 0, 'missShips' => 0, 'byColor' => []];

        $lines = TruckingCostLine::whereIn('shipment_id', $shipQuery->select('id'))
            ->whereIn('item', $followNames)
            ->get(['shipment_id', 'item', 'amount'])
            ->groupBy('shipment_id');

        $anyShips = 0; $missShips = 0; $buckets = [];
        foreach ($lines as $shipLines) {
            $anyShips++;
            $shipHasMiss = false;
            $seen = [];   // hex => có-dòng-thiếu-tiền-trong-lô-này
            foreach ($shipLines as $l) {
                $hex = $nameHex[$l->item] ?? '';
                if ($hex === '') continue;
                $miss = ((int) round((float) $l->amount)) === 0;
                if ($miss) $shipHasMiss = true;
                if (! isset($seen[$hex])) $seen[$hex] = $miss;
                elseif ($miss) $seen[$hex] = true;
            }
            if ($shipHasMiss) $missShips++;
            foreach ($seen as $hex => $miss) {
                $buckets[$hex] ??= ['hex' => $hex, 'total' => 0, 'miss' => 0];
                $buckets[$hex]['total']++;
                if ($miss) $buckets[$hex]['miss']++;
            }
        }
        $byColor = array_values($buckets);
        usort($byColor, fn ($a, $b) => ($b['miss'] - $a['miss']) ?: ($b['total'] - $a['total']));

        return ['anyShips' => $anyShips, 'missShips' => $missShips, 'byColor' => $byColor];
    }

    /** Chuẩn hóa id màu cũ (amber/blue/...) → hex; đã là hex thì giữ. Khớp colorHex() phía client. */
    private function colorHex(?string $c): string
    {
        if (! $c) return '';
        return ['amber' => '#e0a92e', 'blue' => '#2a6fdb', 'green' => '#1f8a5b', 'red' => '#d64545'][$c] ?? $c;
    }

    /** Chỉ các lô theo id (cho trang Xem bảng kê — tránh nạp toàn bộ lô). */
    public function shipmentsByIds(array $ids): array
    {
        $ids = array_values(array_filter($ids, 'is_numeric'));
        if (! $ids) return [];

        return TruckingShipment::whereIn('id', $ids)
            ->with(['customer', 'costLines', 'revenueLines', 'payments'])
            ->get()
            ->map(fn ($s) => $this->shipmentToArray($s))
            ->all();
    }

    /**
     * Cấu hình TỐI THIỂU để định giá (priceFor) phía client — chỉ ký hiệu địa điểm,
     * ngưỡng free time và bảng giá của ĐÚNG 1 khách. Tránh nạp full config()
     * (mọi khách + mọi danh mục) cho trang Xem bảng kê.
     */
    public function pricingCfg(?string $customer): array
    {
        $cfg = [
            'locationCode'  => TruckingLocation::whereNotNull('code')->pluck('code', 'name')->all(),
            'freeTimeHours' => TruckingSetting::get('free_time_hours', '4'),
            'customerInfo'  => [],
        ];
        if ($customer && ($cust = TruckingCustomer::with('priceRows')->where('name', trim($customer))->first())) {
            $cfg['customerInfo'][$cust->name] = [
                'priceList' => $cust->priceRows->map(fn ($p) => $this->priceRowToArray($p))->all(),
            ];
        }
        return $cfg;
    }

    // ===================================================================
    // SHIPMENT — serialize & persist
    // ===================================================================
    public function shipmentToArray(TruckingShipment $s): array
    {
        return [
            'id'           => $s->id,
            'hashid'       => Hashid::encode($s->id),
            'customer'     => $s->customer?->name ?? '',
            'booking'      => $s->booking ?? '',
            'inv'          => $s->inv ?? '',
            'io'           => $s->io ?? '',
            'cru'          => (bool) $s->cru,
            'qty'          => $s->qty,
            'contType'     => $s->cont_type ?? '',
            'contNo'       => $s->cont_no ?? '',
            'declNo'       => $s->declaration_no ?? '',
            'declNote'     => $s->declaration_note ?? '',
            'thanhLy'      => $this->outDate($s->thanh_ly_date),
            'cshtNote'     => $s->csht_note ?? '',
            'kho'          => $s->kho ?? '',
            'from'         => $s->from_loc ?? '',
            'to'           => $s->to_loc ?? '',
            'bksVao'       => $s->bks_vao ?? '',
            'bksRa'        => $s->bks_ra ?? '',
            'raMode'       => $s->ra_mode ?? 'self',
            'raOtherId'    => $s->ra_other_id,
            'sailDate'     => $this->outDate($s->sail_date),
            'cutOff'       => $s->cut_off ?? '',
            'contDen'      => $this->outDate($s->cont_den),
            'contRa'       => $this->outDate($s->cont_ra),
            'gioDenDuKien' => $this->outDateTime($s->gio_den_du_kien),
            'gioXeDen'     => $this->outDateTime($s->gio_xe_den),
            'gioXeRa'      => $this->outDateTime($s->gio_xe_ra),
            'gioXeRaXe'    => $this->outDateTime($s->gio_xe_ra_xe),   // giờ XE ra (khi không kéo cont) — cột riêng
            'cost'         => [
                'items' => $s->costLines->map(fn ($c) => [
                    'id'       => $c->id,
                    'item'     => $c->item ?? '',
                    'amount'   => $this->outMoney($c->amount),
                    'payer'    => $c->payer ?? '',
                    'date'     => $this->outDate($c->date),
                    'billable' => (bool) $c->billable,
                    'color'    => $c->color ?? '',
                    'src'      => $c->src ?? '',
                    'note'     => $c->note ?? '',
                ])->all(),
            ],
            'rev' => [
                'vatRate'  => $this->outNum($s->vat_rate),
                'doanhThu' => $this->revLines($s, 'doanhThu'),
                'choHo'    => $this->revLines($s, 'choHo'),
                'hanTT'    => $this->outDate($s->han_tt),
                'payments' => $s->payments->map(fn ($p) => [
                    'id'     => $p->id,
                    'amount' => $this->outMoney($p->amount),
                    'date'   => $this->outDate($p->date),
                    'note'   => $p->note ?? '',
                ])->all(),
                'ghiChu'   => $s->ghi_chu ?? '',
            ],
        ];
    }

    private function revLines(TruckingShipment $s, string $kind): array
    {
        return $s->revenueLines->where('kind', $kind)->values()->map(fn ($r) => [
            'id'     => $r->id,
            'item'   => $r->item ?? '',
            'amount' => $this->outMoney($r->amount),
        ])->all();
    }

    /**
     * Upsert 1 lô + đồng bộ dòng con. $data theo shape frontend.
     *
     * $only = danh sách FIELD (key client) được phép ghi — dùng cho LƯU TỪNG PHẦN:
     * chỉ field người dùng vừa sửa mới ghi đè, field khác giữ nguyên giá trị trong DB
     * → tránh ghi đè thay đổi của người khác (lost update) khi 2 người sửa 2 field khác nhau.
     * $only = null → ghi toàn bộ (lô mới / import).
     */
    public function saveShipment(array $data, string $sheet, ?TruckingShipment $s = null, ?array $only = null): TruckingShipment
    {
        return DB::transaction(function () use ($data, $sheet, $s, $only) {
            $apply = fn (string $k) => $only === null || in_array($k, $only, true);

            $s ??= new TruckingShipment(['sheet' => $sheet]);
            $s->sheet = $sheet;

            if ($apply('customer')) {
                $customerId = null;
                if (! empty($data['customer'])) {
                    // Chuẩn hóa: trim + collapse khoảng trắng (kể cả Unicode) — tránh "Cty  ABC"
                    // và "Cty ABC" tạo 2 customer khác nhau → bảng kê khớp customer_id sai khách.
                    $cname = preg_replace('/\s+/u', ' ', trim((string) $data['customer'])) ?? '';
                    if ($cname !== '') {
                        $customerId = TruckingCustomer::firstOrCreate(['name' => $cname])->id;
                    }
                }
                $s->customer_id = $customerId;
            }

            // Map field client → [cột, giá trị]. Chỉ ghi cột khi field đó được phép (đã sửa).
            $cols = [
                'booking'      => ['booking', $this->str($data['booking'] ?? null)],
                'inv'          => ['inv', $this->str($data['inv'] ?? null)],
                'io'           => ['io', $this->str($data['io'] ?? null)],
                'cru'          => ['cru', ! empty($data['cru'])],
                'qty'          => ['qty', isset($data['qty']) && $data['qty'] !== '' ? (int) $data['qty'] : null],
                'contType'     => ['cont_type', $this->str($data['contType'] ?? null)],
                'contNo'       => ['cont_no', $this->str($data['contNo'] ?? null)],
                'declNo'       => ['declaration_no', $this->str($data['declNo'] ?? null)],
                'declNote'     => ['declaration_note', $this->str($data['declNote'] ?? null)],
                'thanhLy'      => ['thanh_ly_date', $this->inDate($data['thanhLy'] ?? null)],
                'cshtNote'     => ['csht_note', $this->str($data['cshtNote'] ?? null)],
                'kho'          => ['kho', $this->str($data['kho'] ?? null)],
                'from'         => ['from_loc', $this->str($data['from'] ?? null)],
                'to'           => ['to_loc', $this->str($data['to'] ?? null)],
                'bksVao'       => ['bks_vao', $this->str($data['bksVao'] ?? null)],
                'bksRa'        => ['bks_ra', $this->str($data['bksRa'] ?? null)],
                'driver'       => ['driver', $this->str($data['driver'] ?? null)],
                'raMode'       => ['ra_mode', $data['raMode'] ?? 'self'],
                'raOtherId'    => ['ra_other_id', $data['raOtherId'] ?? null],
                'sailDate'     => ['sail_date', $this->inDate($data['sailDate'] ?? null)],
                'cutOff'       => ['cut_off', $this->str($data['cutOff'] ?? null)],
                'contDen'      => ['cont_den', $this->inDate($data['contDen'] ?? null)],
                'contRa'       => ['cont_ra', $this->inDate($data['contRa'] ?? null)],
                'gioDenDuKien' => ['gio_den_du_kien', $this->inDateTime($data['gioDenDuKien'] ?? null)],
                'gioXeDen'     => ['gio_xe_den', $this->inDateTime($data['gioXeDen'] ?? null)],
                'gioXeRa'      => ['gio_xe_ra', $this->inDateTime($data['gioXeRa'] ?? null)],
                'gioXeRaXe'    => ['gio_xe_ra_xe', $this->inDateTime($data['gioXeRaXe'] ?? null)],
            ];
            foreach ($cols as $key => [$col, $val]) {
                if ($apply($key)) $s->{$col} = $val;
            }
            // Đăng ký KÝ HIỆU địa điểm mới gõ tay vào danh mục — bảng kê khớp giá qua codeMap
            // (port từ registerLocationCode dùng cho import bảng giá). Idempotent: đã có → no-op.
            if ($apply('from')) $this->registerLocationCode($data['from'] ?? null);
            if ($apply('to'))   $this->registerLocationCode($data['to'] ?? null);
            // rev scalars (VAT / hạn TT / ghi chú) đi cùng nhóm 'rev'
            if ($apply('rev')) {
                $s->vat_rate = $this->inNum($data['rev']['vatRate'] ?? null);
                $s->han_tt   = $this->inDate($data['rev']['hanTT'] ?? null);
                $s->ghi_chu  = $this->str($data['rev']['ghiChu'] ?? null);
            }
            $s->save();

            // "Cont khác ra": đẩy GIỜ RA + BKS đã nhập ở popup sang đúng cont ĐƯỢC CHỌN (ra_other_id) — theo id,
            // nên cập nhật được CẢ KHI cont đó không nằm trong trang đang xem (sửa bug giờ ra không xuống cont kia).
            if ($s->ra_mode === 'other' && $s->ra_other_id) {
                $hasGio = $apply('raOtherGioXeRa') && array_key_exists('raOtherGioXeRa', $data);
                $hasBks = $apply('raOtherBksRa') && array_key_exists('raOtherBksRa', $data);
                if ($hasGio || $hasBks) {
                    $o = TruckingShipment::find($s->ra_other_id);
                    if ($o) {
                        if ($hasGio) $o->gio_xe_ra = $this->inDateTime($data['raOtherGioXeRa']);
                        if ($hasBks) $o->bks_ra   = $this->str($data['raOtherBksRa']);
                        $o->save();
                        $this->recomputeShipmentDerived($o, null);
                    }
                }
            }

            // Dòng con chỉ đồng bộ khi nhóm tương ứng được sửa (cost / rev)
            if ($apply('cost')) {
            $s->costLines()->delete();
            foreach (($data['cost']['items'] ?? []) as $i => $c) {
                $s->costLines()->create([
                    'item'     => $this->str($c['item'] ?? null),
                    'amount'   => $this->inMoney($c['amount'] ?? null),
                    'payer'    => $this->str($c['payer'] ?? null),
                    'date'     => $this->inDate($c['date'] ?? null),
                    'billable' => ! empty($c['billable']),
                    'color'    => $this->str($c['color'] ?? null),
                    'src'      => $this->str($c['src'] ?? null),
                    'note'     => $this->str($c['note'] ?? null),
                    'sort'     => $i,
                ]);
            }
            }   // end if apply('cost')

            if ($apply('rev')) {
            $s->revenueLines()->delete();
            foreach (['doanhThu', 'choHo'] as $kind) {
                foreach (($data['rev'][$kind] ?? []) as $i => $r) {
                    $s->revenueLines()->create([
                        'kind'   => $kind,
                        'item'   => $this->str($r['item'] ?? null),
                        'amount' => $this->inMoney($r['amount'] ?? null),
                        'sort'   => $i,
                    ]);
                }
            }

            $s->payments()->delete();
            foreach (($data['rev']['payments'] ?? []) as $i => $p) {
                $s->payments()->create([
                    'amount' => $this->inMoney($p['amount'] ?? null),
                    'date'   => $this->inDate($p['date'] ?? null),
                    'note'   => $this->str($p['note'] ?? null),
                    'sort'   => $i,
                ]);
            }
            }   // end if apply('rev')

            $this->recomputeShipmentDerived($s, $only);
            return $s->fresh(['customer', 'costLines', 'revenueLines', 'payments']);
        });
    }

    /** Bản đồ tên/ký hiệu kho → id (cho tách kho theo lô). Memoize / request. */
    private function warehouseIdMap(): array
    {
        if ($this->whIdMapCache !== null) return $this->whIdMapCache;
        $map = [];
        foreach (TruckingWarehouse::toBase()->get(['id', 'name', 'code']) as $w) {
            if ($w->name) $map[mb_strtolower(trim($w->name))] = (int) $w->id;
            if ($w->code) $map[mb_strtolower(trim($w->code))] = (int) $w->id;
        }
        return $this->whIdMapCache = $map;
    }

    /** Bản đồ tên lái xe → id (lowercase). Memoize / request. */
    private function driverIdMap(): array
    {
        if ($this->driverIdMapCache !== null) return $this->driverIdMapCache;
        $map = [];
        foreach (TruckingDriver::toBase()->get(['id', 'name']) as $d) {
            $k = mb_strtolower(trim((string) $d->name));
            if ($k !== '') $map[$k] = (int) $d->id;
        }
        return $this->driverIdMapCache = $map;
    }

    /** Bản đồ plate → vehicle_id (lowercase+trim+collapse). Memoize / request. */
    private function vehicleIdMap(): array
    {
        if ($this->vehicleIdMapCache !== null) return $this->vehicleIdMapCache;
        $map = [];
        foreach (TruckingVehicle::toBase()->get(['id', 'plate']) as $v) {
            $k = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $v->plate)) ?? '');
            if ($k !== '') $map[$k] = (int) $v->id;
        }
        return $this->vehicleIdMapCache = $map;
    }

    /** Bản đồ tên khách → id (lowercase+trim+collapse). Memoize / request. */
    private function customerIdMap(): array
    {
        if ($this->customerIdMapCache !== null) return $this->customerIdMapCache;
        $map = [];
        foreach (TruckingCustomer::toBase()->get(['id', 'name']) as $c) {
            $k = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $c->name)) ?? '');
            if ($k !== '') $map[$k] = (int) $c->id;
        }
        return $this->customerIdMapCache = $map;
    }

    /** Lookup customer_id theo tên — qua cache memoized (same rule với observer). */
    private function customerIdByName(?string $name): ?int
    {
        $n = trim((string) $name);
        if ($n === '') return null;
        $k = mb_strtolower(preg_replace('/\s+/u', ' ', $n) ?? '');
        return $this->customerIdMap()[$k] ?? null;
    }

    /**
     * Chốt số liệu + tham chiếu BÁO CÁO cho 1 lô (gọi sau khi lưu / để backfill):
     * - Tier 2: gán cost_item_id/payer_id (cost), item_id (revenue) theo tên hiện tại.
     * - Tier 1: tổng doanh thu/VAT/chi hộ/phải thu/đã thu/còn nợ/chi phí/lợi nhuận.
     * - Tier 3: vehicle_id, from/to_location_id + bảng kho theo lô.
     * Giữ nguyên cột chuỗi (lịch sử); id chỉ là khóa join.
     *
     * $only = field client vừa sửa (xem saveShipment). null → backfill toàn phần (CLI / import).
     * Có $only → CHỈ chạy phần liên quan: tránh đụng DB khi user sửa 1 field trung tính
     * (note / driver / bks*) trong popup.
     */
    public function recomputeShipmentDerived(TruckingShipment $s, ?array $only = null): void
    {
        // Field nào kéo theo phần recompute nào (giữ đồng bộ với map $cols ở saveShipment).
        $touches = fn (array $keys) => $only === null || array_intersect($keys, $only) !== [];
        $needTier2   = $touches(['cost', 'rev']);
        $needTotals  = $touches(['cost', 'rev']);
        $needVehicle = $touches(['bksVao']);
        $needDriver  = $touches(['driver']);
        $needLoc     = $touches(['from', 'to']);
        $needWh      = $touches(['kho']);

        if (! $needTier2 && ! $needTotals && ! $needVehicle && ! $needDriver && ! $needLoc && ! $needWh) return;

        if ($needTier2 || $needTotals) {
            $s->load(['costLines', 'revenueLines', 'payments']);   // nạp TƯƠI khi cần totals/tier2
        }

        if ($needTier2) {
            $costItemId = TruckingCostItem::pluck('id', 'name');
            $payerId    = TruckingPayer::pluck('id', 'name');
            $revItemId  = TruckingRevenueItem::pluck('id', 'name');
            $chohoId    = TruckingChohoItem::pluck('id', 'name');
            foreach ($s->costLines as $c) {
                $ci = $c->item !== null ? ($costItemId[$c->item] ?? null) : null;
                $pi = $c->payer !== null ? ($payerId[$c->payer] ?? null) : null;
                if ((int) $c->cost_item_id !== (int) $ci || (int) $c->payer_id !== (int) $pi) {
                    $c->cost_item_id = $ci; $c->payer_id = $pi; $c->save();
                }
            }
            foreach ($s->revenueLines as $r) {
                $map = $r->kind === 'choHo' ? $chohoId : $revItemId;
                $ii = $r->item !== null ? ($map[$r->item] ?? null) : null;
                if ((int) $r->item_id !== (int) $ii) { $r->item_id = $ii; $r->save(); }
            }
        }

        $updates = [];
        if ($needTotals) {
            // Tier 1 — tổng tiền (khớp calcRev/calcCost ở frontend)
            $revBase  = (float) $s->revenueLines->where('kind', 'doanhThu')->sum('amount');
            $chohoRev = (float) $s->revenueLines->where('kind', 'choHo')->sum('amount');
            $rate     = (float) ($s->vat_rate ?? 0);
            $vat      = round($revBase * $rate / 100);
            $phaiThu  = $revBase + $vat + $chohoRev;
            $daThu    = (float) $s->payments->sum('amount');
            $costTotal    = (float) $s->costLines->sum('amount');
            $costBillable = (float) $s->costLines->where('billable', true)->sum('amount');
            $costCompany  = $costTotal - $costBillable;
            $updates += [
                'rev_base' => $revBase, 'vat_amount' => $vat, 'choho_revenue' => $chohoRev,
                'phai_thu' => $phaiThu, 'da_thu' => $daThu, 'con_no' => $phaiThu - $daThu,
                'cost_total' => $costTotal, 'cost_billable' => $costBillable, 'cost_company' => $costCompany,
                'profit' => $revBase - $costCompany,
            ];
        }
        if ($needVehicle) {
            $updates['vehicle_id'] = $s->bks_vao ? TruckingVehicle::where('plate', $s->bks_vao)->value('id') : null;
        }
        if ($needDriver) {
            $dname = preg_replace('/\s+/u', ' ', trim((string) ($s->driver ?? ''))) ?? '';
            $updates['driver_id'] = $dname !== '' ? ($this->driverIdMap()[mb_strtolower($dname)] ?? null) : null;
        }
        if ($needLoc) {
            $updates['from_location_id'] = $this->resolveLocationId($s->from_loc);
            $updates['to_location_id']   = $this->resolveLocationId($s->to_loc);
        }
        if ($updates) {
            $s->forceFill($updates)->save();
        }

        if ($needWh) {
            // Tier 3 — kho theo lô (mỗi kho 1 dòng). Tách theo cùng bộ ngăn cách với
            // khoPoints/khoRouteDisplay ( , → -> – — " - " ) để khớp warehouse_id dù tuyến dùng mũi tên.
            $whMap = $this->warehouseIdMap();
            $s->warehouses()->delete();
            $parts = array_values(array_filter(array_map('trim', preg_split('/\s*(?:,|→|->|–|—|\s-\s)\s*/u', (string) $s->kho) ?: []), fn ($x) => $x !== ''));
            foreach ($parts as $i => $name) {
                $s->warehouses()->create(['warehouse_id' => $whMap[mb_strtolower($name)] ?? null, 'name' => $name, 'sort' => $i]);
            }
        }
    }

}
