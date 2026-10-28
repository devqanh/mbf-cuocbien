<?php

namespace App\Services\Trucking\Concerns;

use App\Models\TruckingContType;
use App\Models\TruckingCostItem;
use App\Models\TruckingCostLine;
use App\Models\TruckingChohoItem;
use App\Models\TruckingCustomer;
use App\Models\TruckingDriver;
use App\Models\TruckingExtVendor;
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
use App\Support\ShipmentColumns;
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
            'assetCostTypes'   => [\App\Models\TruckingAssetCostType::class, false, false, false],
            'officeCostTypes'  => [\App\Models\TruckingOfficeCostType::class, false, false, false],
            'payMethods'       => [\App\Models\TruckingPayMethod::class, false, false, false],
            'extVendors'  => [TruckingExtVendor::class,  false, false,           false],
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
            ->with(['customer', 'costLines', 'revenueLines', 'payments', 'raOther:id,cont_no,bks_ra,gio_xe_ra'])
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
        // Số lô / trang: cho người dùng chọn (whitelist chống lạm dụng), mặc định 20.
        $pp = (int) ($p['perPage'] ?? 20);
        $perPage = in_array($pp, [20, 50, 100, 200], true) ? $pp : 20;
        $page    = max(1, (int) ($p['page'] ?? 1));
        $q       = trim((string) ($p['q'] ?? ''));
        $filter  = (string) ($p['filter'] ?? 'all');
        $filter  = in_array($filter, ['all', 'out', 'notout'], true) ? $filter : 'all';
        $tl      = (string) ($p['tl'] ?? 'all');                                        // lọc thanh lý tờ khai
        $tl      = in_array($tl, ['all', 'done', 'pending'], true) ? $tl : 'all';
        $hc      = (string) ($p['hc'] ?? 'all');                                        // lọc hạ cont (đã / chưa hạ)
        $hc      = in_array($hc, ['all', 'done', 'pending'], true) ? $hc : 'all';
        $price   = (string) ($p['price'] ?? 'all');                                     // lọc khớp bảng giá (lô đã ra)
        $price   = in_array($price, ['all', 'unmatched', 'matched'], true) ? $price : 'all';
        $canRevenue = ShipmentColumns::can('revenue');   // không được xem cột Thu phí → không định giá, không lọc theo giá
        $follow  = (string) ($p['follow'] ?? 'all');
        $sortKey = (string) ($p['sort'] ?? 'default');
        $sortKey = in_array($sortKey, ['default', 'customer', 'cost'], true) ? $sortKey : 'default';
        $dir     = ((int) ($p['dir'] ?? 1)) < 0 ? 'desc' : 'asc';
        $all     = ! empty($p['all']);
        // Lọc theo NƠI HẠ theo KÝ HIỆU (vd HPP) — CHỌN NHIỀU = OR. Map to_loc → ký hiệu để gom option + lọc.
        $toLocSel = array_values(array_filter(array_map(fn ($x) => trim((string) $x), (array) ($p['toLoc'] ?? [])), fn ($x) => $x !== ''));
        $codeMap = $this->normalizedCodeMap();
        $normLoc = fn ($v) => mb_strtoupper(preg_replace('/\s+/u', '', trim(\Illuminate\Support\Str::ascii((string) $v))) ?? '');
        $rawByCode = [];   // KÝ HIỆU => [các giá trị to_loc thực có]
        foreach (TruckingShipment::ofSheet($sheet)->whereNotNull('to_loc')->where('to_loc', '!=', '')->distinct()->pluck('to_loc') as $raw) {
            $code = $codeMap[$normLoc($raw)] ?? $normLoc($raw);
            $rawByCode[$code][] = $raw;
        }
        $toMode = (($p['toMode'] ?? 'include') === 'exclude') ? 'exclude' : 'include';   // Nơi hạ: GỒM/LOẠI TRỪ
        $toLocCodes = array_keys($rawByCode); sort($toLocCodes);
        $toLocRaw = [];   // ký hiệu đã chọn → các giá trị to_loc thực để whereIn
        foreach ($toLocSel as $code) foreach ($rawByCode[$code] ?? [$code] as $raw) $toLocRaw[] = $raw;

        // Lọc theo NƠI LẤY theo KÝ HIỆU — có chế độ GỒM / LOẠI TRỪ (kế toán: lọc nơi hạ + trừ nơi lấy).
        $fromSel  = array_values(array_filter(array_map(fn ($x) => trim((string) $x), (array) ($p['fromLoc'] ?? [])), fn ($x) => $x !== ''));
        $fromMode = (($p['fromMode'] ?? 'exclude') === 'include') ? 'include' : 'exclude';
        $fromByCode = [];
        foreach (TruckingShipment::ofSheet($sheet)->whereNotNull('from_loc')->where('from_loc', '!=', '')->distinct()->pluck('from_loc') as $raw) {
            $code = $codeMap[$normLoc($raw)] ?? $normLoc($raw);
            $fromByCode[$code][] = $raw;
        }
        $fromCodes = array_keys($fromByCode); sort($fromCodes);
        $fromRaw = [];
        foreach ($fromSel as $code) foreach ($fromByCode[$code] ?? [$code] as $raw) $fromRaw[] = $raw;

        // Lọc theo KHÁCH HÀNG — chọn nhiều = OR. Option = TÊN khách thực có trong lô của sheet;
        // lọc quy về customer_id (nhiều khách có thể trùng tên → gom mọi id cùng tên).
        $custSel = array_values(array_filter(array_map(fn ($x) => trim((string) $x), (array) ($p['cust'] ?? [])), fn ($x) => $x !== ''));
        $custOptions = [];
        $custIdsByName = [];
        $custRows = TruckingCustomer::whereIn('id', TruckingShipment::ofSheet($sheet)->whereNotNull('customer_id')->distinct()->select('customer_id'))
            ->orderBy('name')->get(['id', 'name']);
        foreach ($custRows as $c) {
            $n = trim((string) $c->name);
            if ($n === '') continue;
            if (! in_array($n, $custOptions, true)) $custOptions[] = $n;
            $custIdsByName[$n][] = (int) $c->id;
        }
        $custIds = [];
        foreach ($custSel as $n) foreach ($custIdsByName[$n] ?? [] as $id) $custIds[] = $id;

        // Lọc theo GIỜ ĐẾN KẾ HOẠCH (gio_den_du_kien) — chọn 1 NGÀY.
        $denDate = trim((string) ($p['denDate'] ?? ''));
        // KHOẢNG ngày đóng hàng (từ / đến, gồm 2 đầu) — dùng cho Xuất chi phí lô. Sai định dạng thì bỏ qua.
        $isYmd = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1;
        $denFrom = trim((string) ($p['denFrom'] ?? '')); if (! $isYmd($denFrom)) $denFrom = '';
        $denTo   = trim((string) ($p['denTo'] ?? ''));   if (! $isYmd($denTo)) $denTo = '';

        // Lọc theo NHÃN (tags) — chọn nhiều = OR. Danh sách nhãn thực có để đổ vào bộ lọc.
        $tagSel = array_values(array_filter(array_map(fn ($x) => trim((string) $x), (array) ($p['tags'] ?? [])), fn ($x) => $x !== ''));
        $tagOptions = [];
        foreach (TruckingShipment::ofSheet($sheet)->whereNotNull('tags')->pluck('tags') as $arr) {
            foreach ((array) $arr as $t) { $t = trim((string) $t); if ($t !== '' && ! in_array($t, $tagOptions, true)) $tagOptions[] = $t; }
        }
        sort($tagOptions);

        // Khoản chi phí "theo dõi" (có màu trong danh mục) → id + tên + hex.
        // Lọc theo cost_item_id (FK ổn định) thay vì item text (đổi tên sẽ sót dòng cũ).
        $followItems = TruckingCostItem::whereNotNull('color')->where('color', '!=', '')->get(['id', 'name', 'color', 'auto']);
        $followIds   = $followItems->pluck('id')->all();
        $followNames = $followItems->pluck('name')->all();        // giữ tên cho followStats display
        $nameHex     = $followItems->mapWithKeys(fn ($i) => [$i->name => $this->colorHex($i->color)])->all();
        $idHex       = $followItems->mapWithKeys(fn ($i) => [$i->id => $this->colorHex($i->color)])->all();
        // Khoản "auto" (tự hiện) + có màu → expected cho MỌI lô (nhắc "chưa điền" kể cả lô chưa thêm dòng).
        $autoHexes   = $followItems->filter(fn ($i) => $i->auto)->map(fn ($i) => $this->colorHex($i->color))->filter()->unique()->values()->all();
        $autoSet     = array_flip($autoHexes);
        $hasAutoFollow = ! empty($autoHexes);
        $nonAutoColoredIds = $followItems->filter(fn ($i) => ! $i->auto)->pluck('id')->all();
        $autoHexItems = [];   // hex => [item ids auto của màu đó]
        foreach ($followItems->filter(fn ($i) => $i->auto) as $i) { $autoHexItems[$this->colorHex($i->color)][] = $i->id; }
        $hexItems = [];       // hex => [tất cả item ids của màu đó]
        foreach ($followItems as $i) { $hexItems[$this->colorHex($i->color)][] = $i->id; }

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
        // Lọc địa điểm theo ký hiệu, có chế độ GỒM/LOẠI TRỪ. exclude giữ lô KHÔNG có địa điểm (orWhereNull).
        $applyLoc = function ($b, $col, $sel, $raw, $mode) {
            if (! $sel) return;
            if ($mode === 'exclude') {
                if ($raw) $b->where(fn ($w) => $w->whereNotIn($col, $raw)->orWhereNull($col));
            } else {
                $b->whereIn($col, $raw ?: ['\\0__none__']);
            }
        };
        $searched = function () use ($sheet, $applySearch, $applyLoc, $toLocSel, $toLocRaw, $toMode, $fromSel, $fromRaw, $fromMode, $custSel, $custIds, $denDate, $denFrom, $denTo, $tagSel) {
            $b = TruckingShipment::ofSheet($sheet);
            $applySearch($b);
            $applyLoc($b, 'to_loc', $toLocSel, $toLocRaw, $toMode);
            $applyLoc($b, 'from_loc', $fromSel, $fromRaw, $fromMode);
            // Khách hàng = OR trên customer_id; tên chọn không còn khách nào → không lô nào khớp.
            if ($custSel) $b->whereIn('customer_id', $custIds ?: [0]);
            if ($denDate !== '') $b->whereDate('gio_den_du_kien', $denDate);       // lô có Giờ đến KH = ngày chọn
            if ($denFrom !== '') $b->whereDate('gio_den_du_kien', '>=', $denFrom);
            if ($denTo !== '')   $b->whereDate('gio_den_du_kien', '<=', $denTo);
            // Lọc nhãn = OR. Dùng JSON_SEARCH (khớp được trên MariaDB + JSON lưu unicode escaped, whereJsonContains fail).
            if ($tagSel) $b->where(function ($w) use ($tagSel) { foreach ($tagSel as $t) $w->orWhereRaw('JSON_SEARCH(tags, \'one\', ?) IS NOT NULL', [$t]); });
            return $b;
        };

        // --- Aggregate toàn cục trên tập đã tìm ---
        // Tổng chi phí (header) = số NET: SUM(ROUND(amount / (1 + vat/100))).
        // Không có quyền xem cột Chi phí → không truy vấn, không gửi con số ra client.
        $totalCost = ShipmentColumns::can('cost')
            ? (int) round((float) TruckingCostLine::whereIn('shipment_id', $searched()->select('id'))
                ->sum(DB::raw('ROUND(amount / (1 + COALESCE(vat,0)/100))')))
            : 0;

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

        // "Đã thanh lý (tờ khai)" = lô CÓ Ngày thanh lý (thanh_ly_date) — cùng kiểu suy diễn với
        // "đã ra" = có Giờ xe ra: 1 mốc ngày vừa là cờ đã/chưa, vừa cho biết thanh lý hôm nào.
        $applyTl    = fn ($q) => $q->whereNotNull('thanh_ly_date');
        $applyNotTl = fn ($q) => $q->whereNull('thanh_ly_date');
        $tlDone = $applyTl($searched())->count();
        $tlCounts = ['all' => $allCount, 'done' => $tlDone, 'pending' => $allCount - $tlDone];
        // "Đã hạ cont" = lô CÓ Ngày hạ cont (ha_cont_date) — cùng cơ chế cờ-theo-ngày như thanh lý.
        $applyHc    = fn ($q) => $q->whereNotNull('ha_cont_date');
        $applyNotHc = fn ($q) => $q->whereNull('ha_cont_date');
        $hcDone = $applyHc($searched())->count();
        $hcCounts = ['all' => $allCount, 'done' => $hcDone, 'pending' => $allCount - $hcDone];

        $followStats = $this->followStats($searched(), $autoHexes);

        // --- Danh sách hiển thị: q + lọc + follow + sort + phân trang ---
        $list = $searched();
        if ($filter === 'out') {
            $applyOut($list);
        } elseif ($filter === 'notout') {
            $applyNotOut($list);
        }
        if ($tl === 'done')         $applyTl($list);
        elseif ($tl === 'pending')  $applyNotTl($list);
        if ($hc === 'done')         $applyHc($list);
        elseif ($hc === 'pending')  $applyNotHc($list);
        if ($followIds) {
            // "đã điền" = có dòng khoản đó CÓ số hóa đơn. Khoản AUTO expected cho mọi lô (thiếu dòng = chưa điền).
            $filledOf = fn ($ids) => fn ($c) => $c->whereIn('cost_item_id', $ids ?: [0])->whereNotNull('invoice_no')->where('invoice_no', '!=', '');
            $emptyInv = fn ($ids) => fn ($c) => $c->whereIn('cost_item_id', $ids ?: [0])->where(fn ($x) => $x->whereNull('invoice_no')->orWhere('invoice_no', ''));
            if ($follow === 'any') {
                // Có theo dõi: nếu có khoản auto → mọi lô đều expected (không lọc); nếu không, lọc lô có dòng theo dõi.
                if (! $hasAutoFollow) $list->whereHas('costLines', fn ($c) => $c->whereIn('cost_item_id', $followIds));
            } elseif ($follow === 'missing') {
                // Chưa điền: (lô có dòng khoản KHÔNG-auto trống số HĐ) HOẶC (thiếu dòng-đã-điền cho 1 màu auto bất kỳ).
                $list->where(function ($w) use ($nonAutoColoredIds, $autoHexItems, $emptyInv, $filledOf) {
                    if ($nonAutoColoredIds) $w->whereHas('costLines', $emptyInv($nonAutoColoredIds));
                    foreach ($autoHexItems as $ids) $w->orWhereDoesntHave('costLines', $filledOf($ids));
                });
            } elseif (str_starts_with($follow, '#')) {
                $ids = $hexItems[$follow] ?? [0];
                if (isset($autoSet[$follow])) {
                    // Màu auto: lô CHƯA điền màu này (chưa có dòng điền số HĐ) — kể cả lô chưa thêm dòng.
                    $list->whereDoesntHave('costLines', $filledOf($ids));
                } else {
                    // Màu thường: lô có dòng màu này (như cũ).
                    $list->whereHas('costLines', fn ($c) => $c->whereIn('cost_item_id', $ids));
                }
            }
        }

        // Lọc KHỚP GIÁ: chỉ xét lô ĐÃ RA (chưa ra chưa có ngày cont ra để chọn bảng giá). Bảng giá không lọc được
        // bằng SQL → định giá trong bộ nhớ tập đã lọc (context bảng giá memoize theo khách/bảng) rồi whereIn id.
        // Chỉ chạy khi người dùng chọn — mặc định không tốn thêm gì.
        if ($price !== 'all' && $canRevenue) {
            $wantMatched = ($price === 'matched');
            $ids = [];
            foreach ($applyOut(clone $list)->with(['costLines', 'customer:id,name', 'raOther:id,gio_xe_ra'])->get() as $s) {
                if ((bool) $this->priceOut($s)['matched'] === $wantMatched) $ids[] = $s->id;
            }
            $list->whereIn('id', $ids ?: [0]);
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

        $list->with(['customer', 'costLines', 'revenueLines', 'payments', 'raOther:id,cont_no,bks_ra,gio_xe_ra']);
        if (! $all) $list->forPage($page, $perPage);
        // "Thu phí (cước+dầu)" CHO LÔ ĐÃ RA — DÙNG CHUNG priceShipment với bảng kê (1 nguồn công thức,
        // sửa 1 chỗ áp cả 2). Chỉ tính cho trang đang xem (không tính khi $all=export) để nhẹ query.
        $data = $list->get()->map(function ($s) use ($all, $canRevenue) {
            $arr = $this->shipmentToArray($s);
            if (! $canRevenue) return $arr;   // không được xem cột Thu phí → khỏi định giá, khỏi gửi
            $out = ! empty($s->gio_xe_ra);   // "đã ra" = có Giờ xe ra
            if (! $all && $out) {
                $pr = $this->priceOut($s);
                // Nền cước+dầu (+ sà lan) — KHỚP cột "Phải thu (cước+dầu)" của bảng kê.
                $arr['cuocDau']      = (int) $pr['cuoc'] + (int) $pr['dau'] + (int) ($pr['bargeCuoc'] ?? 0) + (int) ($pr['bargeDau'] ?? 0);
                $arr['priceMatched'] = (bool) $pr['matched'];
            } else {
                $arr['cuocDau'] = null;   // chưa ra → chưa tính thu phí
            }
            return $arr;
        })->all();

        return [
            'data'         => $data,
            'page'         => $page,
            'perPage'      => $perPage,
            'total'        => $total,
            'lastPage'     => $lastPage,
            'totalCost'    => $totalCost,
            'filterCounts' => $filterCounts,
            'tlCounts'     => $tlCounts,
            'hcCounts'     => $hcCounts,
            'followStats'  => $followStats,
            'sibs'         => $this->siblingsList($sheet),
            // Danh sách KÝ HIỆU nơi hạ / nơi lấy thực có để đổ vào bộ lọc — gom theo ký hiệu, ổn định.
            'toLocs'       => $toLocCodes,
            'fromLocs'     => $fromCodes,
            'custOptions'  => $custOptions,
            'tagOptions'   => $tagOptions,
        ];
    }

    /**
     * Định giá lô ĐÃ RA theo bảng giá phủ NGÀY ĐỊNH GIÁ (ngày vận hành 08:00 của giờ xe ra, HPH fallback
     * ngày tàu — xem pricingDateOf) — 1 công thức dùng chung cho cột "Thu phí" và bộ lọc khớp giá
     * (cùng priceShipment với bảng kê).
     */
    private function priceOut(TruckingShipment $s): array
    {
        return $this->priceShipment($s, $this->pricingContextForShipment($s));
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
            ->toBase()->get(['id', 'cont_no', 'booking', 'gio_xe_ra', 'bks_ra', 'ra_mode', 'ra_other_id'])   // không hydrate model — chỉ cột cần
            ->map(fn ($s) => [
                'id' => $s->id, 'contNo' => $s->cont_no ?? '', 'booking' => $s->booking ?? '',
                // datetime thô "Y-m-d H:i:s" → "Y-m-dTH:i" cho DTField; bksRa giữ chuỗi
                'gioXeRa' => $s->gio_xe_ra ? str_replace(' ', 'T', substr((string) $s->gio_xe_ra, 0, 16)) : '',
                'bksRa'   => $s->bks_ra ?? '',
                // Cont ra hộ lô này đang chọn — picker đánh dấu/cảnh báo "1 cont chỉ ra 1 lần" khi lô khác chọn trùng.
                'raOtherId' => $s->ra_mode === 'other' ? ($s->ra_other_id ?: null) : null,
            ])
            ->all();
    }

    /**
     * LỘ TRÌNH 1 NGÀY VẬN HÀNH (08:00 ngày $date → 08:00 hôm sau, tức 07:59 hôm sau là hết),
     * gom theo biển số XE VÀO (bks_vao) — mỗi xe 1 lộ trình liền mạch trong ngày.
     * Mỗi lô = 1 hoạt động dưới bks_vao, xếp theo GIỜ XE RA:
     *  - self  : "Lấy cont [X] ra" tại gio_xe_ra.
     *  - none  : "Ra xe (không kéo cont)" tại gio_xe_ra_xe.
     *  - other : "Kéo cont khác ([Y]) ra" tại gio_xe_ra của lô ra_other_id (cont X chờ);
     *            tuyến = Nơi lấy + Kho của X (cont kéo vào) → Nơi hạ của Y (cont kéo ra).
     * Chỉ tính hoạt động có giờ ra TRONG khung [08:00, 08:00 hôm sau).
     * $withPlanned (chỉ trang Lộ trình): thêm `planned` = chuyến đã gán xe nhưng CHƯA ra, để xem — không vào legs/payGroups.
     */
    public function routeTripByDate(string $date, bool $withPlanned = false): array
    {
        try { $start = Carbon::parse($date)->setTime(self::OPS_DAY_START_HOUR, 0); }
        catch (\Throwable) { $start = Carbon::today()->setTime(self::OPS_DAY_START_HOUR, 0); }
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
        // Tuyến xe THỰC SỰ chạy: Nơi lấy + Kho theo cont KÉO VÀO ($s); Nơi hạ theo cont KÉO RA ($out —
        // self/none = chính nó; other = lô bị kéo ra hộ: xe đưa cont vào kho, cắt móc rồi kéo cont kia ra).
        $mk = function (TruckingShipment $s, Carbon $t, string $mode, ?TruckingShipment $out = null, array $extra = []) {
            $out = $out ?: $s;
            // Chuỗi điểm hành trình: Nơi lấy → các Kho → Nơi hạ (bỏ điểm trùng liền kề).
            $pts = [];
            $add = function ($label, $kind) use (&$pts) {
                $label = trim((string) $label);
                if ($label === '' || ($pts && end($pts)['label'] === $label)) return;
                $pts[] = ['label' => $label, 'kind' => $kind];
            };
            // Lộ trình = kế toán theo dõi → Nơi lấy / Kho / Nơi hạ đều hiện KÝ HIỆU (gọn, dễ hiểu) thay vì tên hiển thị.
            $add($this->locCode($s->from_loc), 'pickup');
            foreach ($this->khoCodePoints($s->kho) as $kp) $add($kp, 'kho');
            $add($this->locCode($out->to_loc), 'drop');

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
                'route'    => $this->khoRouteDisplay($s->kho) ?: ($s->kho ?? ''),
                'kho'      => $s->kho ?? '', 'cru' => (bool) $out->cru,   // cho tính "chi theo ngày" (khớp phí tuyến + lương CRU)
                'from'     => $s->from_loc ?? '', 'to' => $out->to_loc ?? '',
                'customer' => $s->customer?->name ?? '',
                'customerId' => $s->customer_id ? (int) $s->customer_id : null,   // chọn phí tuyến riêng của khách
                // Cont VÀO có hàng = lô Nhập (đưa hàng tới kho); cont RA có hàng = lô Xuất (kéo hàng về cảng) → Hàng–Hàng / Hàng–Rỗng.
                'io'       => (string) $s->io, 'ioOut' => (string) $out->io,   // Nhập / Xuất của cont vào · cont ra (hiện nhãn ở Lộ trình)
                'loadIn'   => mb_stripos((string) $s->io, 'nh') !== false,
                'loadOut'  => mb_stripos((string) $out->io, 'xu') !== false,
                // Loại chuyến theo quy chế lương (đánh dấu ở lô): kẹp 2 cont 20' / kết hợp nhập → xuất
                'kepCont'  => (bool) ($s->kep_cont || $out->kep_cont),
                'ketHop'   => (bool) ($s->ket_hop || $out->ket_hop),
                'booking'  => $s->booking ?? '',
                'hashid'   => \App\Support\Hashid::encode($s->id),
                // Khóa CHỈNH TAY của chuyến (trip_overrides / chi khác) theo LÔ — 1 cont có thể chạy nhiều chuyến trong ngày.
                'trip'     => '#' . $s->id,
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

        // CHUYẾN CHƯA HOÀN THÀNH (chỉ trang Lộ trình): xe đã gán (bks_vao) nhưng chưa ra → hiện ngay khi gán xe,
        // xếp theo Giờ xe đến (chưa đến → Giờ đến dự kiến). Ra rồi thì thành hoạt động hoàn thành ở trên (ngày theo giờ ra).
        // Để RIÊNG khỏi $legs → không vào chi cho lái / kỳ lương / báo cáo / chốt ngày.
        $planned = [];
        if ($withPlanned) {
            $pend = TruckingShipment::with(['customer', 'raOther'])
                ->whereNotNull('bks_vao')->where('bks_vao', '!=', '')
                ->where(fn ($q) => $q->whereBetween('gio_xe_den', [$start, $end])
                    ->orWhere(fn ($w) => $w->whereNull('gio_xe_den')->whereBetween('gio_den_du_kien', [$start, $end])))
                ->get();
            // Cont được lô khác kéo ra hộ: chuyến ra của nó thuộc XE KÉO (leg other của lô kia) → không tính ở đây.
            $pullTargets = $pend->isNotEmpty()
                ? array_flip(TruckingShipment::whereIn('ra_other_id', $pend->pluck('id'))->pluck('ra_other_id')->all())
                : [];
            foreach ($pend as $s) {
                $at = $s->gio_xe_den ?: $s->gio_den_du_kien;
                if (isset($pullTargets[$s->id]) || ! $inWin($at)) continue;
                $mode = in_array($s->ra_mode, ['none', 'other'], true) ? $s->ra_mode : 'self';
                $t = $mode === 'other' ? $s->raOther : null;
                $out = match ($mode) {
                    'none'  => $s->gio_xe_ra_xe,
                    'other' => $t?->gio_xe_ra,
                    default => $s->gio_xe_ra,
                };
                if ($out) continue;   // đã ra → là hoạt động hoàn thành (theo giờ ra), không còn là chuyến chờ
                $planned[] = $mk($s, $at, $mode, $t, array_filter([
                    'planned'   => true,
                    'refCont'   => $t?->cont_no,
                    'refBksVao' => $t ? trim((string) $t->bks_vao) : null,
                ], fn ($v) => $v !== null));
            }
        }

        // Gom theo bks_vao + map xe hệ thống QUA vehicle_id (Tier-2 ref đã chốt khi lưu) —
        // bền hơn so với match plate string (khử rủi ro typo/whitespace). Fallback theo plate
        // cho lô legacy chưa có vehicle_id.
        $allLegs = array_merge($legs, $planned);   // xe chỉ có chuyến chưa xong vẫn cần loại xe / số cầu
        $vehIds = array_values(array_unique(array_filter(array_map(fn ($l) => $l['vehicleId'] ?? null, $allLegs))));
        $vehById = $vehIds ? TruckingVehicle::whereIn('id', $vehIds)->get(['id', 'plate', 'type', 'axle'])->keyBy('id') : collect();
        $legacyPlates = array_values(array_unique(array_map(fn ($l) => $l['bks'], array_filter($allLegs, fn ($l) => empty($l['vehicleId'])))));
        $vehByPlate = $legacyPlates ? TruckingVehicle::whereIn('plate', $legacyPlates)->get(['plate', 'type', 'axle'])->keyBy('plate') : collect();
        // Tiền chuyến tính theo QUY CHẾ LƯƠNG CHUYẾN của bảng áp cho ngày vận hành (legPayGroup) + giá dầu + chi đã lưu.
        $dateStr = $start->format('Y-m-d');
        $fuels = TruckingFuelPrice::orderByDesc('from_date')->orderByDesc('id')->get();
        $paysByBks = \App\Models\TruckingRoutePay::whereDate('work_date', $dateStr)->get()->keyBy('bks');
        // Trợ cấp THEO NGÀY của quy chế áp cho ngày (sửa ở Quy chế › Trợ cấp; quy chế cũ chưa khai → mức mặc định 100.000):
        //  - quay đầu tuyến ngoài Hải Phòng · chuyến thứ 3 trong ngày.
        $dayReg = $this->regulationOf($this->pickRouteFeeBook($dateStr));
        $turnaroundAllowance = (int) ($dayReg['allowances']['turnaround'] ?? TruckingSetting::get('turnaround_allowance', '100000'));
        $thirdTripAllowance  = (int) ($dayReg['allowances']['thirdTrip'] ?? 100000);

        // Gắn chi khác phát sinh THỦ CÔNG vào 1 nhóm chuyến — perDay→chi theo ngày, không→gom lương; choHo→chi hộ;
        // khac→CHI PHÍ KHÁC = chi phí CÔNG TY (VETC, sửa xe, rửa xe, gửi xe…) — như dầu: KHÔNG tính lương và KHÔNG cộng
        // vào chi cho lái (sub/payTotal); chỉ ghi khacSub để thống kê + báo cáo chi phí.
        $addManual = function (array &$g, array $ms): void {
            $g['manual'] = [];
            foreach ($ms as $m) {
                $amt = (int) round((float) ($m['amount'] ?? 0));
                $perDay = ! isset($m['perDay']) || ! empty($m['perDay']);
                $khac = $perDay && ! empty($m['khac']);
                $choHo = $perDay && ! $khac && ! empty($m['choHo']);
                $g['manual'][] = ['name' => (string) ($m['name'] ?? ''), 'amount' => $amt, 'perDay' => $perDay, 'choHo' => $choHo, 'khac' => $khac,
                    'note' => $khac ? (string) ($m['note'] ?? '') : ''];
                if ($amt <= 0) continue;
                if ($khac) { $g['khacSub'] += $amt; continue; }
                if ($perDay) $g['sub'] += $amt; else $g['payrollSub'] += $amt;
                if ($choHo) $g['chohoSub'] += $amt;
            }
        };

        $byBks = [];
        foreach ($legs as $l) { $byBks[$l['bks']][] = $l; }
        $plannedByBks = [];
        foreach ($planned as $l) { $plannedByBks[$l['bks']][] = $l; $byBks[$l['bks']] ??= []; }   // kể cả xe chỉ có chuyến chưa xong
        // Xe có chi khác đã lưu cho ngày nhưng không còn chuyến nào (lô bị sửa sau khi nhập) — vẫn hiện để tiền không bị rơi.
        foreach ($paysByBks as $pb => $pay) { if (! empty($pay->extra_items)) $byBks[(string) $pb] ??= []; }
        $trucks = [];
        foreach ($byBks as $bks => $ls) {
            $bks = (string) $bks;
            usort($ls, fn ($a, $b) => $a['sortTs'] <=> $b['sortTs']);
            $pl = $plannedByBks[$bks] ?? [];
            usort($pl, fn ($a, $b) => $a['sortTs'] <=> $b['sortTs']);
            $vid = ($ls[0] ?? $pl[0] ?? [])['vehicleId'] ?? null;
            if (! $ls && ! $pl && ! $vehByPlate->has($bks) && ($v = TruckingVehicle::where('plate', $bks)->first(['plate', 'type', 'axle']))) $vehByPlate[$bks] = $v;
            if ($vid && $vehById->has($vid)) {
                $type = $vehById[$vid]->type ?? null;
                $axle = $vehById[$vid]->axle ?? null;
                $matched = true;
            } else {
                $type = $vehByPlate[$bks]->type ?? null;
                $axle = $vehByPlate[$bks]->axle ?? null;
                $matched = $vehByPlate->has($bks);
            }
            $pay = $paysByBks->get($bks);
            // Chi khác phát sinh THỦ CÔNG (đã lưu) — gom theo chuyến (trip = #id lô) để gắn vào đúng chuyến;
            // dòng cũ chưa có trip gom theo cont, chỉ gắn vào chuyến ĐẦU TIÊN của cont đó (tránh cộng trùng).
            $manualByTrip = []; $manualByCont = [];
            foreach ((array) ($pay?->extra_items ?? []) as $m) {
                if (! is_array($m)) continue;
                $tk = (string) ($m['trip'] ?? '');
                if ($tk !== '') $manualByTrip[$tk][] = $m; else $manualByCont[(string) ($m['cont'] ?? '')][] = $m;
            }
            // CHỈNH TAY theo chuyến — route_pays.trip_overrides {#id lô | cont (cũ): {liters?, choho?, amounts?}}.
            $tripOv = is_array($pay?->trip_overrides) ? $pay->trip_overrides : [];
            // MỖI chuyến (leg) = 1 nhóm (kể cả không khớp phí tuyến → cảnh báo cho kế toán).
            // payTotal = chi theo ngày; payrollTotal = khoản gom trả 1 đợt (lương) cho kỳ lương.
            $payGroups = []; $payTotal = 0; $payrollTotal = 0; $payWarn = 0;
            $prevOutHp = false;   // chuyến kéo cont TRƯỚC của xe trong ngày có đi kho ngoài Hải Phòng (tỉnh cũ) không
            $tripNo = 0;          // số thứ tự chuyến KÉO CONT của xe trong ngày vận hành (08:00 → 08:00)
            foreach ($ls as $l) {
                $g = $this->legPayGroup($l, $axle, $fuels, $dateStr);
                // Lít dầu sửa tay ghi đè định mức quy chế của chuyến (làm tròn số nguyên); tiền = lít × giá dầu theo ngày.
                $ov = $this->tripOvGet($tripOv, (string) $g['trip'], (string) $g['cont']);
                $fl = $ov['liters'] ?? null;
                if ($fl !== null && $fl !== '') {
                    $fl = round((float) $fl);
                    $unit = $g['fuel']['unitPrice'] ?? (int) round((float) $this->fuelPriceForDate($fuels, $dateStr));
                    $g['fuel'] = ['axle' => $g['fuel']['axle'] ?? ($axle === '2' ? 2 : 1), 'liters' => (float) $fl, 'unitPrice' => $unit,
                        'amount' => (int) round((float) $fl * $unit), 'baseLiters' => $g['fuel']['liters'] ?? null, 'edited' => true];
                }
                // SỐ TIỀN NHẬP TAY cho khoản cho phép sửa (tiền đường chuyến khác nhóm cảng; phụ cấp + lương chuyến xe ra
                // không kéo cont) — trip_overrides[cont].amounts; áp cho cả khoản chi trong ngày lẫn khoản kỳ lương.
                $amts = is_array($ov['amounts'] ?? null) ? $ov['amounts'] : [];
                if ($amts) {
                    foreach (['items', 'payrollItems'] as $bucket) {
                        foreach ($g[$bucket] as &$it) {
                            if (empty($it['editable']) || ! array_key_exists($it['key'], $amts)) continue;
                            $it['amount'] = (int) $amts[$it['key']];
                            $it['edited'] = true;
                        }
                        unset($it);
                    }
                    $g['sub'] = array_sum(array_column($g['items'], 'amount'));
                    $g['payrollSub'] = array_sum(array_column($g['payrollItems'], 'amount'));
                    // Đã nhập đủ mọi khoản BẮT BUỘC nhập → hết cảnh báo "nhập tay"
                    if (! empty($g['needInput']) && ! array_filter(array_merge($g['items'], $g['payrollItems']), fn ($x) => ! empty($x['required']) && empty($x['edited']))) {
                        $g['needInput'] = false;
                        $g['note'] = '';
                        $g['issues'] = array_values(array_filter($g['issues'] ?? [], fn ($x) => $x['type'] !== 'needInput'));
                    }
                }
                // Lít dầu sửa tay → không còn vướng "thiếu km chặng" (đã có lít)
                if (! empty($g['fuel']['edited'])) $g['issues'] = array_values(array_filter($g['issues'] ?? [], fn ($x) => $x['type'] !== 'kmMissing'));
                // CHI HỘ: khoản chi trong ngày được chọn "Chi hộ" ở popup Chi lái — vẫn chi cho lái hôm nay (nằm trong sub)
                // nhưng KHÔNG tính vào lương; chohoSub = phần chi hộ của chuyến (kỳ lương trừ ra khỏi "đã chi tính lương").
                $chohoKeys = array_flip(array_map('strval', (array) ($ov['choho'] ?? [])));
                $g['chohoSub'] = 0;
                foreach ($g['items'] as &$it) {
                    $fixed = (string) ($it['fixed'] ?? '');   // khoản loại cố định theo quy chế (vé trạm = chi hộ, tiền đường = lương)
                    $it['choHo'] = $fixed !== '' ? $fixed === 'choho' : isset($chohoKeys[(string) ($it['ck'] ?? $it['key'] ?? '')]);
                    if ($it['choHo']) $g['chohoSub'] += (int) $it['amount'];
                }
                unset($it);
                // Gắn chi khác phát sinh của chuyến (theo trip; dòng cũ theo cont → chuyến đầu của cont).
                $g['khacSub'] = 0;
                $ms = $manualByTrip[(string) $g['trip']] ?? [];
                unset($manualByTrip[(string) $g['trip']]);
                if (isset($manualByCont[(string) $g['cont']])) { $ms = array_merge($ms, $manualByCont[(string) $g['cont']]); unset($manualByCont[(string) $g['cont']]); }
                $addManual($g, $ms);
                // có tiền (kể cả thủ công) → hết cảnh báo; trừ khoản còn chờ NHẬP TAY (vd tiền đường khác nhóm cảng)
                if ($g['sub'] > 0 && $g['note'] !== '' && empty($g['needInput'])) $g['note'] = '';
                // TRỢ CẤP THEO NGÀY (quy chế; kế toán chốt 09/10/2026) — chi trong ngày, tính lương. Thêm sau dòng xóa cảnh báo
                // (chuyến chưa tính được theo quy chế vẫn giữ cảnh báo). Xe ra không kéo cont không tính là chuyến.
                //  - QUAY ĐẦU ngoài HP: chuyến tra BẢNG VÙNG đi kho có tỉnh CŨ khác HP mà chuyến kéo cont trước đó cũng vậy
                //    (vd Hải Dương rồi Bắc Ninh). Chuyến ngắn trên Bắc Ninh (ICD / Tri Phương — bảng KCN) tính như chuyến ngắn
                //    Hải Phòng → KHÔNG xét quay đầu.
                //  - TỪ CHUYẾN THỨ 3 trong ngày: chuyến kéo cont thứ 3, 4, 5… của xe (bất kể đi đâu) → MỖI chuyến +100k.
                if (($l['mode'] ?? '') !== 'none') {
                    $tripNo++;
                    $outHp = ($g['tripMode'] ?? '') === 'zone' && $this->legOutsideHaiPhong($l);
                    $add = function ($key, $label, $amount) use (&$g) {
                        $g['items'][] = ['key' => $key, 'ck' => $key, 'label' => $label, 'amount' => $amount, 'perDay' => true, 'fixed' => 'luong', 'choHo' => false];
                        $g['sub'] += $amount;
                    };
                    if ($outHp && $prevOutHp && $turnaroundAllowance > 0) $add('quayDau', 'Trợ cấp quay đầu ngoài HP', $turnaroundAllowance);
                    if ($tripNo >= 3 && $thirdTripAllowance > 0) $add('chuyen3', 'Trợ cấp chuyến thứ ' . $tripNo . ' trong ngày', $thirdTripAllowance);
                    $prevOutHp = $outHp;
                }
                $payGroups[] = $g;
                $payTotal += $g['sub'];
                $payrollTotal += $g['payrollSub'];
                if ($g['note'] !== '') $payWarn++;   // chuyến chưa ra tiền (không khớp / chưa tick / =0)
            }
            // Chi khác đã lưu nhưng cont KHÔNG còn khớp chuyến nào trong ngày (lô bị sửa cont/xe/giờ sau khi nhập) →
            // KHÔNG bỏ rơi tiền: gom thành nhóm "chưa gắn chuyến" (orphan, không tính là chuyến) + cảnh báo để kế toán xử lý.
            // (đã gắn thì bị unset ở trên → phần còn lại là chi khác không còn chuyến)
            $orphans = [];
            foreach ($manualByTrip as $tk => $ms) $orphans[] = [(string) $tk, (string) ($ms[0]['cont'] ?? ''), $ms];
            foreach ($manualByCont as $cont => $ms) $orphans[] = ['', (string) $cont, $ms];
            foreach ($orphans as [$tk, $cont, $ms]) {
                $g = ['route' => 'Chi khác chưa gắn chuyến', 'cont' => $cont, 'trip' => $tk, 'mode' => '', 'orphan' => true,
                      'items' => [], 'sub' => 0, 'payrollItems' => [], 'payrollSub' => 0, 'chohoSub' => 0, 'khacSub' => 0, 'fuel' => null,
                      'matched' => false, 'note' => 'Cont ' . ($cont !== '' ? $cont : '(trống)') . ' không còn là chuyến của xe trong ngày — kiểm tra lại lô hoặc chi khác',
                      'issues' => []];
                $addManual($g, $ms);
                $payGroups[] = $g;
                $payTotal += $g['sub'];
                $payrollTotal += $g['payrollSub'];
                $payWarn++;
            }
            // ĐÓNG BĂNG (chốt): nếu xe/ngày đã chốt → dùng số ĐÃ CHỐT (không đổi dù sửa quy chế).
            $frozen = (bool) ($pay?->frozen);
            if ($frozen && is_array($pay->frozen_data)) {
                $fd = $pay->frozen_data;
                $payGroups    = $fd['payGroups'] ?? $payGroups;
                $payTotal     = $fd['payTotal'] ?? $payTotal;
                $payrollTotal = $fd['payrollTotal'] ?? $payrollTotal;
                $payWarn      = $fd['payWarn'] ?? $payWarn;
                // Snapshot chốt TRƯỚC khi tách chi phí khác khỏi chi lái (chưa có cờ khacOut) → trừ khacSub ra khỏi sub/payTotal.
                if (empty($fd['khacOut'])) {
                    foreach ($payGroups as &$fg) {
                        $k = (int) ($fg['khacSub'] ?? 0);
                        if ($k > 0) { $fg['sub'] = (int) ($fg['sub'] ?? 0) - $k; $payTotal -= $k; }
                    }
                    unset($fg);
                }
            }
            // Dầu = chi phí CÔNG TY (tách khỏi tiền lái) — tổng lít + tiền theo các chuyến trong ngày.
            $fuelTotal = 0; $fuelLiters = 0.0; $chohoTotal = 0; $khacTotal = 0;
            foreach ($payGroups as $g) {
                if (! empty($g['fuel'])) { $fuelTotal += (int) ($g['fuel']['amount'] ?? 0); $fuelLiters += (float) ($g['fuel']['liters'] ?? 0); }
                $chohoTotal += (int) ($g['chohoSub'] ?? 0);   // phần chi hộ trong chi theo ngày (không tính lương)
                $khacTotal  += (int) ($g['khacSub'] ?? 0);    // phần chi phí khác trong chi theo ngày (không tính lương)
            }
            $trucks[] = ['bks' => $bks, 'vehicleId' => $vid, 'matched' => $matched, 'type' => $type, 'axle' => $axle, 'legs' => $ls, 'planned' => $pl,
                'payGroups' => $payGroups, 'payTotal' => $payTotal, 'payrollTotal' => $payrollTotal, 'payWarn' => $payWarn, 'chohoTotal' => $chohoTotal, 'khacTotal' => $khacTotal,
                'fuelTotal' => $fuelTotal, 'fuelLiters' => round($fuelLiters, 1),
                'frozen' => $frozen,
                'payDriver' => $pay?->driver ?? '', 'payNote' => $pay?->note ?? '', 'paid' => (bool) ($pay?->paid ?? false), 'paidDate' => $pay ? $this->outDate($pay->paid_date) : ''];
        }
        usort($trucks, fn ($a, $b) => count($b['legs']) + count($b['planned']) <=> count($a['legs']) + count($a['planned']) ?: strcmp($a['bks'], $b['bks']));
        $frozenCount = count(array_filter($trucks, fn ($t) => $t['frozen']));

        return [
            'date'  => $start->format('Y-m-d'),
            'start' => (int) ($start->getTimestamp() * 1000),
            'end'   => (int) ($end->getTimestamp() * 1000),
            'startLabel' => $start->format('d/m'),   // format sẵn theo tz ứng dụng (tránh lệch ngày trên trình duyệt)
            'endLabel'   => $end->format('d/m'),
            'trucks' => $trucks,
            'totalLegs' => count($legs),
            'totalPlanned' => count($planned),   // chuyến chưa hoàn thành (chỉ khi $withPlanned)
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

    /**
     * 1 CHUYẾN (leg) → nhóm chi cho lái, tính theo QUY CHẾ LƯƠNG CHUYẾN của bảng áp cho ngày (book.regulation). Luôn trả
     * nhóm (kể cả chưa tính được) để Lộ trình hiện ĐỦ mọi chuyến + cảnh báo cho kế toán; matched/note cho biết lý do = 0.
     * Khoản: Vé trạm (cộng chặng, chi hộ hằng ngày) · Phụ cấp đi đường (chi hằng ngày, tính lương) · Lương chuyến (kỳ lương).
     * Km: khứ hồi bảng KCN (lấy & hạ cùng nhóm) → cộng chặng; dầu = km × định mức (số cầu, Hàng–Hàng / Hàng–Rỗng).
     */
    private function legPayGroup(array $leg, ?string $axle, $fuels, string $date, ?array $regOverride = null): array
    {
        // Chuỗi node thực tế của chuyến — đúng như Lộ trình hiển thị:
        //  - KHÔNG kéo cont ra (mode none): xe chỉ tới NƠI LẤY rồi ra → chỉ hiện điểm pickup.
        //  - Có kéo cont ra (self/other): đủ Nơi lấy (cảng) → các Kho → Nơi hạ (cảng).
        $noPull = ($leg['mode'] ?? '') === 'none';
        if ($noPull) {
            $pts = array_values(array_filter($leg['points'] ?? [], fn ($p) => ($p['kind'] ?? '') === 'pickup'));
            $nodes = $pts ? array_map(fn ($p) => $p['label'] ?? '', $pts) : [$leg['from'] ?? ''];
        } else {
            $nodes = array_merge([$leg['from'] ?? ''], $this->khoPoints($leg['kho'] ?? ''), [$leg['to'] ?? '']);
        }
        // Hiển thị lộ trình thực tế (bỏ điểm rỗng + trùng liền kề).
        $disp = [];
        foreach ($nodes as $n) { $n = trim((string) $n); if ($n === '' || (count($disp) && end($disp) === $n)) continue; $disp[] = $n; }
        $routeDisp = implode(' → ', $disp) ?: ($this->khoRouteDisplay($leg['kho'] ?? '') ?: '—');
        $g = ['route' => $routeDisp, 'cont' => $leg['cont'] ?? '', 'trip' => $leg['trip'] ?? '', 'mode' => $leg['mode'] ?? '',
              'items' => [], 'sub' => 0, 'payrollItems' => [], 'payrollSub' => 0, 'fuel' => null, 'matched' => false, 'note' => '',
              'issues' => []];   // [{type, refs[]}] — khung "Kiểm tra dữ liệu" ở Lộ trình gom theo loại để chỉ chỗ cần nhập

        $feeBook = $this->pickRouteFeeBook($date);   // memoize/request → rẻ
        $reg = $regOverride ?? $this->regulationOf($feeBook);   // [] = chưa cấu hình; override = bản nháp (Thử tính 1 chuyến)
        if (! $reg) {
            $g['note'] = 'Chưa cấu hình Quy chế lương chuyến cho ngày này (Cài đặt › Quy chế lương chuyến)';
            $g['issues'][] = ['type' => 'noRegulation', 'refs' => [$feeBook ? ($feeBook->label ?: 'bảng ' . ($this->outDate($feeBook->from_date) ?: 'mặc định')) : 'chưa có bảng']];
            return $g;
        }
        // Xe vào nhưng ra KHÔNG kéo cont: quy chế chưa có mức → kế toán NHẬP TAY phụ cấp đi đường (chi trong ngày) + lương
        // chuyến (kỳ lương) ở popup Chi lái (trip_overrides[cont].amounts); giữ cảnh báo tới khi nhập (nhập 0 = không trả).
        if ($noPull) {
            $hint = 'Xe ra không kéo cont — quy chế chưa có mức, nhập tay';
            $g['matched'] = true; $g['feeSource'] = 'regulation'; $g['feeRule'] = 'xe ra không kéo cont · nhập tay';
            $g['feeFrom'] = $this->outDate($feeBook?->from_date); $g['feeBook'] = $feeBook?->label ?? '';
            $g['items'] = [['key' => 'tienDuong', 'ck' => 'tienDuong', 'label' => 'Phụ cấp đi đường', 'amount' => 0, 'perDay' => true, 'fixed' => 'luong', 'editable' => true, 'required' => true]];
            $g['payrollItems'] = [['key' => 'luong', 'ck' => 'luong', 'label' => 'Lương chuyến', 'amount' => 0, 'perDay' => false, 'editable' => true, 'required' => true]];
            $g['needInput'] = true; $g['inputHint'] = $hint;
            $g['note'] = $hint . ' ở Chi lái';
            $g['issues'][] = ['type' => 'needInput', 'refs' => ['Xe ra không kéo cont']];
            return $g;
        }
        $q = $this->regulationTripRate($leg, $reg);
        if (! $q['ok']) { $g['note'] = $q['note']; if (! empty($q['issue'])) $g['issues'][] = $q['issue']; return $g; }
        $g['matched']   = true;
        $g['feeSource'] = 'regulation';
        $g['feeRule']   = $q['rule'];
        $g['tripMode']  = $q['mode'];   // zone = tra bảng vùng (HP) · kcn = chuyến ngắn trên Bắc Ninh (ICD / Tri Phương)
        $g['feeFrom']   = $this->outDate($feeBook?->from_date);   // quy chế áp dụng từ ngày ('' = bảng mặc định)
        $g['feeBook']   = $feeBook?->label ?? '';

        // Đi chặng (1 lần): km cộng chặng + vé trạm cộng chặng.
        $walk = $this->segmentWalk($leg, $reg);
        // items = chi trong ngày (hiện ở Lộ trình); payrollItems = gom trả kỳ lương. Loại cố định (fixed) — popup không đổi.
        $item = fn ($key, $label, $amount, array $extra = []) => ['key' => $key, 'ck' => $key, 'label' => $label, 'amount' => (int) $amount, 'perDay' => true] + $extra;
        // Chuyến KHÁC nhóm cảng: tiền đường không có định mức → 0 + cho NHẬP TAY ở popup Chi lái (gợi ý = mức nhóm lấy);
        // giữ cảnh báo (needInput) tới khi nhập (routeTripByDate áp trip_overrides[cont].amounts).
        $cross = $q['cross'] ?? '';
        $items = array_values(array_filter([
            // Vé trạm (chi hộ) sửa tay được từng chuyến khi thực tế khác vé cộng chặng (base = mức quy chế).
            $item('veTram', 'Vé trạm', $walk['toll'], ['fixed' => 'choho', 'editable' => true, 'base' => (int) $walk['toll']]),
            // Phụ cấp đi đường + lương chuyến: kế toán SỬA TAY được từng chuyến khi cần (base = mức quy chế; trống = theo quy chế).
            // required = BẮT BUỘC nhập (quy chế không có định mức) → giữ cảnh báo tới khi nhập.
            $cross
                ? $item('tienDuong', 'Phụ cấp đi đường', 0, ['fixed' => 'luong', 'editable' => true, 'required' => true, 'suggest' => (int) $q['phuCap']])
                : $item('tienDuong', 'Phụ cấp đi đường', $q['phuCap'], ['fixed' => 'luong', 'editable' => true, 'base' => (int) $q['phuCap']]),
        ], fn ($x) => $x['amount'] > 0 || $x['key'] === 'tienDuong'));   // phụ cấp luôn hiện (để nhập tay); vé trạm chỉ khi chặng có vé
        if ($cross) {
            $g['cross'] = $cross;
            $g['needInput'] = true;
            $g['inputHint'] = "Khác nhóm cảng ($cross) — không có định mức, nhập tay";
            $g['issues'][] = ['type' => 'needInput', 'refs' => ['Khác nhóm cảng: ' . $cross]];
        }
        $payroll = [['key' => 'luong', 'ck' => 'luong', 'label' => 'Lương chuyến', 'amount' => (int) $q['luong'], 'perDay' => false, 'editable' => true, 'base' => (int) $q['luong']]];

        // KM: khứ hồi bảng KCN (lấy & hạ cùng nhóm) → cộng chặng theo lộ trình thực tế; thiếu chặng → báo ở Lộ trình.
        $km = null; $kmSrc = '';
        if (! empty($q['km'])) { $km = (float) $q['km']; $kmSrc = 'quy chế KCN'; }
        elseif ($walk['km'] !== null) { $km = $walk['km']; $kmSrc = 'chặng: ' . $walk['path']; }
        elseif ($walk['missing']) { $g['kmMissing'] = $walk['missing']; $g['issues'][] = ['type' => 'kmMissing', 'refs' => $walk['missing']]; }
        if ($km !== null) $g['km'] = $km;
        // DẦU = CHI PHÍ CÔNG TY (KHÔNG chi cho lái): km × định mức theo SỐ CẦU xe và Hàng–Hàng / Hàng–Rỗng (cont vào có
        // hàng (Nhập) VÀ cont ra có hàng (Xuất), hoặc chuyến KẾT HỢP nhập → xuất). Lít sửa tay ở Lộ trình ghi đè sau.
        $is2 = ($axle === '2');
        $hh = (! empty($leg['loadIn']) && ! empty($leg['loadOut'])) || ! empty($leg['ketHop']);
        $rate = (float) ($reg['fuel'][$is2 ? 'axle2' : 'axle1'][$hh ? 'hh' : 'hr'] ?? 0);
        if ($km && $rate > 0) {
            $liters = round($km * $rate / 100);   // lít dầu làm tròn số nguyên (không lẻ phẩy) — tiền dầu tính theo số lít đã tròn
            $unit = (float) $this->fuelPriceForDate($fuels, $date);   // đơn giá dầu theo NGÀY của chuyến
            $g['fuel'] = ['axle' => $is2 ? 2 : 1, 'liters' => $liters, 'unitPrice' => (int) round($unit), 'amount' => (int) round($liters * $unit),
                'km' => $km, 'kmSrc' => $kmSrc, 'rate' => $rate, 'load' => $hh ? 'Hàng–Hàng' : 'Hàng–Rỗng'];
            if ($unit <= 0) $g['issues'][] = ['type' => 'noFuelPrice', 'refs' => [$date]];
        }
        $g['items'] = $items;
        $g['sub']   = array_sum(array_column($items, 'amount'));
        $g['payrollItems'] = $payroll;
        $g['payrollSub']   = array_sum(array_column($payroll, 'amount'));
        if ($cross) $g['note'] = "Khác nhóm cảng ($cross): quy chế không có định mức tiền đường — nhập tay ở Chi lái";
        elseif (! $items) $g['note'] = 'Quy chế: phụ cấp đi đường = 0';
        return $g;
    }

    /**
     * THỬ TÍNH 1 CHUYẾN theo quy chế ĐANG SỬA (chưa lưu) — trang Quy chế lương chuyến, để người mới thấy hệ thống tra
     * từng bước thế nào. Dùng đúng legPayGroup như Lộ trình (không tính riêng) + thông tin giải thích: nhóm cảng của
     * Nơi lấy / Nơi hạ, tỉnh cũ + vùng của từng kho.
     * $in = {from, to: tên/ký hiệu cảng, kho: [tên kho], axle: '1'|'2', ioIn: 'Nhập'|'Xuất', ioOut: 'Nhập'|'Xuất'}
     */
    public function previewRegulationTrip($regulation, array $in): array
    {
        $reg = \App\Support\SalaryZones::cleanRegulation($regulation);
        $khos = array_values(array_filter(array_map(fn ($k) => trim((string) $k), (array) ($in['kho'] ?? [])), fn ($k) => $k !== ''));
        $leg = ['from' => trim((string) ($in['from'] ?? '')), 'to' => trim((string) ($in['to'] ?? '')), 'kho' => implode(', ', $khos),
            'mode' => 'self', 'cont' => '', 'points' => [], 'ketHop' => false,
            'loadIn' => ($in['ioIn'] ?? '') === 'Nhập', 'loadOut' => ($in['ioOut'] ?? '') === 'Xuất'];
        $axle = ($in['axle'] ?? '1') === '2' ? '2' : '1';
        // "Tỉnh cũ thử": kho CHƯA gán tỉnh cũ → tạm coi như tỉnh này (chỉ trong lần thử, không lưu) để xem trọn kết quả.
        $tried = [];
        if (($tp = trim((string) ($in['tryProvince'] ?? ''))) !== '') {
            $norm = fn ($v) => mb_strtoupper(preg_replace('/\s+/u', '', trim(\Illuminate\Support\Str::ascii((string) $v))) ?? '');
            foreach ($this->khoPoints($leg['kho']) as $k) {
                if ($this->khoOldProvince($k) !== null) continue;
                $this->zoneByKhoCache['N:' . $norm($k)] = $tp;
                $tried[$k] = true;
            }
        }
        $fuels = TruckingFuelPrice::orderByDesc('from_date')->orderByDesc('id')->get();
        $g = $this->legPayGroup($leg, $axle, $fuels, now()->toDateString(), $reg);
        // Giải thích: nhóm cảng của 2 đầu + tỉnh cũ / vùng của từng kho
        $grp = function (string $label) use ($reg) {
            $c = $this->pointCode($label);
            foreach ($reg['originGroups'] ?? [] as $gr) if ($c !== '' && in_array($c, $gr['codes'], true)) return ['code' => $c, 'group' => $gr['name'], 'mode' => $gr['mode']];
            return ['code' => $c, 'group' => null, 'mode' => null];
        };
        $zoneOf = $reg ? \App\Support\SalaryZones::zoneIndex($reg) : [];
        $khoInfo = array_map(function ($k) use ($zoneOf, $reg, $tried) {
            $p = $this->khoOldProvince($k);
            $zi = $p !== null ? ($zoneOf[$p] ?? null) : null;
            return ['name' => $k, 'code' => $this->pointCode($k), 'oldProvince' => $p, 'tried' => isset($tried[$k]),
                'zone' => $zi !== null ? 'V' . ($zi + 1) . ' · ' . $reg['zones'][$zi]['name'] : null];
        }, $this->khoPoints($leg['kho']));
        return ['ok' => true, 'from' => $grp($leg['from']), 'to' => $grp($leg['to']), 'khos' => $khoInfo, 'axle' => $axle, 'trip' => $g];
    }

    /** @var array<int,array> quy chế đã chuẩn hóa theo id bảng phí — memoize / request. */
    private array $regCache = [];

    /** Quy chế lương chuyến của bảng phí áp cho ngày ([] = chưa cấu hình). */
    private function regulationOf(?\App\Models\TruckingRouteFeeBook $book): array
    {
        if (! $book) return [];
        return $this->regCache[(int) $book->id] ??= \App\Support\SalaryZones::cleanRegulation($book->regulation);
    }

    /** Mã điểm (cảng / kho) chuẩn hóa IN HOA từ tên hoặc ký hiệu — dùng so bảng KCN, nhóm cảng, bảng km. */
    private function pointCode(?string $label): string
    {
        $norm = fn ($v) => mb_strtoupper(preg_replace('/\s+/u', '', trim(\Illuminate\Support\Str::ascii((string) $v))) ?? '');
        $n = $norm($label);
        return $n === '' ? '' : mb_strtoupper((string) ($this->normalizedCodeMap()[$n] ?? $n));
    }

    /**
     * Mức QUY CHẾ cho 1 chuyến kéo cont: nhóm xuất phát = nhóm có Nơi lấy (không có → nhóm có Nơi hạ). Nhóm & vùng là
     * dữ liệu tùy chỉnh của bảng phí (book.regulation):
     *  - nhóm tra "bảng KCN" → theo ký hiệu kho (nhiều kho → mức lương cao nhất); km khứ hồi chỉ khi hạ cùng nhóm.
     *  - nhóm tra "bảng vùng" → vùng chứa TỈNH CŨ của kho (nhiều kho → vùng lương cao nhất = "mức chuyến xa nhất").
     * @return array{ok:bool, luong?:int, phuCap?:int, km?:?float, rule?:string, note?:string}
     */
    private function regulationTripRate(array $leg, array $reg): array
    {
        $grpOf = function (string $c) use ($reg) { if ($c === '') return null; foreach ($reg['originGroups'] as $g) if (in_array($c, $g['codes'], true)) return $g; return null; };
        $from = $this->pointCode($leg['from'] ?? ''); $to = $this->pointCode($leg['to'] ?? '');
        $gFrom = $grpOf($from); $gTo = $grpOf($to);
        $g = $gFrom ?? $gTo;
        // issue = lỗi có CẤU TRÚC {type, refs} cho khung "Kiểm tra dữ liệu" ở Lộ trình (gom theo loại, chỉ chỗ sửa).
        if (! $g) return ['ok' => false, 'note' => "Quy chế: Nơi lấy / Nơi hạ ($from → $to) chưa thuộc nhóm cảng xuất phát nào (Cài đặt › Quy chế lương chuyến)",
            'issue' => ['type' => 'noGroup', 'refs' => [($from ?: '(trống)') . ' → ' . ($to ?: '(trống)')]]];
        // Lấy & hạ KHÁC nhóm cảng (kế toán chốt 08/10/2026):
        //  - lấy nhóm "bảng KCN" (ICD / Tri Phương) → hạ nhóm "bảng vùng" (Hải Phòng): = 1 chuyến của nhóm hạ (lái về HP rồi
        //    bắt buộc lên lại kho) → tra BẢNG VÙNG theo tỉnh cũ của kho, tự động.
        //  - lấy nhóm "bảng vùng" (HP) → hạ nhóm "bảng KCN" (vd HP → QV → Tri Phương): quy chế không có định mức tiền đường
        //    → lương theo nhóm lấy, tiền đường kế toán NHẬP TAY.
        $cross = []; $via = '';
        if ($gFrom && $gTo && $gFrom['key'] !== $gTo['key']) {
            if ($gFrom['mode'] === 'kcn' && $gTo['mode'] === 'zone') { $g = $gTo; $via = $gFrom['name'] . ' → ' . $gTo['name'] . ' (tính như chuyến ' . $gTo['name'] . ') · '; }
            elseif ($gFrom['mode'] === 'zone' && $gTo['mode'] === 'kcn') $cross = ['cross' => $gFrom['name'] . ' → ' . $gTo['name']];
        }
        if ($g['mode'] === 'kcn') {
            $khoCodes = array_map(fn ($k) => $this->pointCode($k), $this->khoCodePoints($leg['kho'] ?? ''));
            $best = null;
            foreach ($reg['kcn'] as $row) if ($row['group'] === $g['key'] && in_array($row['kho'], $khoCodes, true) && (! $best || $row['luong'] > $best['luong'])) $best = $row;
            if (! $khoCodes) return ['ok' => false, 'note' => 'Lô chưa có Kho — không tra được bảng KCN', 'issue' => ['type' => 'noKho', 'refs' => [(string) ($leg['cont'] ?? '') ?: '(không số cont)']]];
            if (! $best) return ['ok' => false, 'note' => 'Quy chế ' . $g['name'] . ': chưa có KCN ' . implode(', ', $khoCodes) . ' trong bảng KCN',
                'issue' => ['type' => 'noKcn', 'refs' => array_map(fn ($c) => $g['name'] . ': ' . $c, $khoCodes)]];
            return ['ok' => true, 'mode' => 'kcn', 'luong' => $best['luong'], 'phuCap' => $best['phuCap'], 'km' => ($gTo['key'] ?? null) === $g['key'] ? $best['km'] : null, 'rule' => $g['name'] . ' · KCN ' . $best['kho']] + $cross;
        }
        $zoneOf = \App\Support\SalaryZones::zoneIndex($reg);
        $zones = $reg['zones'];
        $best = null; $noProv = []; $noZone = [];
        $khoNames = $this->khoPoints($leg['kho'] ?? '');
        if (! $khoNames) return ['ok' => false, 'note' => 'Lô chưa có Kho — không tra được vùng', 'issue' => ['type' => 'noKho', 'refs' => [(string) ($leg['cont'] ?? '') ?: '(không số cont)']]];
        foreach ($khoNames as $k) {
            $p = $this->khoOldProvince($k);
            $i = $p !== null ? ($zoneOf[$p] ?? null) : null;
            if ($p === null) { $noProv[] = $k; continue; }
            if ($i === null) { $noZone[] = $p . ' (kho ' . $k . ')'; continue; }
            if ($best === null || [$zones[$i]['luong'], $zones[$i]['phuCap']] > [$zones[$best]['luong'], $zones[$best]['phuCap']]) $best = $i;
        }
        if ($best === null) return $noProv
            ? ['ok' => false, 'note' => 'Quy chế ' . $g['name'] . ': kho ' . implode(', ', $noProv) . ' chưa gán tỉnh cũ (Cài đặt › Kho)', 'issue' => ['type' => 'khoNoProvince', 'refs' => $noProv]]
            : ['ok' => false, 'note' => 'Quy chế ' . $g['name'] . ': tỉnh cũ ' . implode(', ', $noZone) . ' chưa thuộc vùng nào (Quy chế › Bước 3)', 'issue' => ['type' => 'provinceNoZone', 'refs' => $noZone]];
        $z = $zones[$best];
        return ['ok' => true, 'mode' => 'zone', 'luong' => $z['luong'], 'phuCap' => $z['phuCap'], 'km' => null, 'rule' => ($via ?: $g['name'] . ' · ') . 'V' . ($best + 1) . ' ' . $z['name']] + $cross;
    }

    /**
     * Đi CHẶNG theo lộ trình thực tế (Nơi lấy → các kho → Nơi hạ) trên bảng chặng của quy chế. Bảng theo CHIỀU: tra
     * A → B trước, không có thì dùng dòng B → A.
     *  - km = cộng km các chặng; thiếu km chặng nào → km null + danh sách chặng thiếu.
     *  - toll = cộng VÉ TRẠM các chặng có khai (chặng không khai vé = 0).
     * @return array{km:?float, toll:int, path:string, missing:string[]}
     */
    private function segmentWalk(array $leg, array $reg): array
    {
        $pts = [];
        foreach (array_merge([$leg['from'] ?? ''], $this->khoCodePoints($leg['kho'] ?? ''), [$leg['to'] ?? '']) as $p) {
            $c = $this->pointCode($p);
            if ($c !== '' && end($pts) !== $c) $pts[] = $c;
        }
        if (count($pts) < 2) return ['km' => null, 'toll' => 0, 'path' => '', 'missing' => []];
        $map = [];
        foreach ($reg['distances'] as $d) $map[$this->pointCode($d['from']) . '>' . $this->pointCode($d['to'])] = $d;
        $sum = 0.0; $toll = 0; $missing = []; $parts = [];
        for ($i = 0; $i + 1 < count($pts); $i++) {
            $a = $pts[$i]; $b = $pts[$i + 1];
            $d = $map["$a>$b"] ?? $map["$b>$a"] ?? null;
            $toll += (int) ($d['toll'] ?? 0);
            $km = $d && $d['km'] > 0 ? (float) $d['km'] : null;
            if ($km === null) { $missing[] = "$a → $b"; continue; }
            $sum += $km; $parts[] = rtrim(rtrim(number_format($km, 1, ',', ''), '0'), ',');
        }
        return $missing ? ['km' => null, 'toll' => $toll, 'path' => '', 'missing' => $missing]
            : ['km' => round($sum, 1), 'toll' => $toll, 'path' => implode(' → ', $pts) . ' = ' . implode(' + ', $parts), 'missing' => []];
    }

    /** @var array<string,string>|null [tên/ký hiệu kho chuẩn hóa => tỉnh cũ] — memoize / request. */
    private ?array $zoneByKhoCache = null;

    /**
     * Chuyến có đi kho NGOÀI Hải Phòng không — theo TỈNH CŨ của kho (quy chế lương xét tỉnh trước sáp nhập:
     * Hải Dương là ngoài HP). Kho chưa gán tỉnh cũ → không xác định → coi như không (để không cộng nhầm).
     * So theo TÊN kho, không có thì theo ký hiệu.
     */
    private function legOutsideHaiPhong(array $leg): bool
    {
        if ($this->zoneByKhoCache === null) {
            $norm = fn ($v) => mb_strtoupper(preg_replace('/\s+/u', '', trim(\Illuminate\Support\Str::ascii((string) $v))) ?? '');
            $m = [];
            foreach (TruckingWarehouse::whereNotNull('old_province')->where('old_province', '!=', '')->get(['name', 'code', 'old_province']) as $w) {
                if ($w->name) $m['N:' . $norm($w->name)] = $w->old_province;
                if ($w->code) $m['C:' . $norm($w->code)] ??= $w->old_province;
            }
            $this->zoneByKhoCache = $m;
        }
        foreach ($this->khoPoints($leg['kho'] ?? '') as $k) {
            if (\App\Support\SalaryZones::isOutsideHaiPhong($this->khoOldProvince($k))) return true;
        }
        return false;
    }

    /** Tỉnh cũ (quy chế) của 1 kho theo TÊN, không có thì theo ký hiệu; null = chưa gán. */
    private function khoOldProvince(string $kho): ?string
    {
        if ($this->zoneByKhoCache === null) $this->legOutsideHaiPhong([]);   // nạp map
        $norm = fn ($v) => mb_strtoupper(preg_replace('/\s+/u', '', trim(\Illuminate\Support\Str::ascii((string) $v))) ?? '');
        return $this->zoneByKhoCache['N:' . $norm($kho)] ?? $this->zoneByKhoCache['C:' . $norm($kho)] ?? null;
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
        // Chi khác phát sinh THỦ CÔNG theo từng chuyến (trip = #id lô; cont để hiển thị) — {trip, cont, name, amount, perDay}.
        $extraItems = [];
        foreach ((array) ($data['extraItems'] ?? []) as $m) {
            if (! is_array($m)) continue;
            $name = $this->str($m['name'] ?? null) ?? '';
            $amount = $this->inMoney($m['amount'] ?? null) ?? 0;
            if ($name === '' && $amount <= 0) continue;
            $extraItems[] = [
                'trip'   => $this->str($m['trip'] ?? null) ?? '',
                'cont'   => $this->str($m['cont'] ?? null) ?? '',
                'name'   => $name,
                'amount' => $amount,
                'perDay' => ! isset($m['perDay']) || ! empty($m['perDay']),   // mặc định chi theo ngày
                'choHo'  => empty($m['khac']) && ! empty($m['choHo']),       // chi hộ — không tính lương
                'khac'   => ! empty($m['khac']),                              // chi phí khác — không tính lương, thống kê riêng
                'note'   => ! empty($m['khac']) ? ($this->str($m['note'] ?? null) ?? '') : '',   // ghi chú chi phí khác (để biết chi gì)
            ];
        }
        // Khoản phí tuyến chọn "Chi hộ" theo từng chuyến: chỉ ghi đè choho của các cont có trong payload, giữ lít dầu sửa tay.
        $existing = \App\Models\TruckingRoutePay::whereDate('work_date', $date)->where('bks', $bks)->first();
        $tripOv = is_array($existing?->trip_overrides) ? $existing->trip_overrides : [];
        foreach ((array) ($data['chohoItems'] ?? []) as $c) {
            if (! is_array($c)) continue;
            $cont = (string) ($c['cont'] ?? ''); $trip = (string) ($c['trip'] ?? '');
            $keys = array_values(array_unique(array_filter(array_map('strval', (array) ($c['keys'] ?? [])), fn ($k) => $k !== '')));
            $cur = $this->tripOvGet($tripOv, $trip, $cont);
            if ($keys) $cur['choho'] = $keys; else unset($cur['choho']);
            $this->tripOvPut($tripOv, $trip, $cont, $cur);
        }
        // Số tiền NHẬP TAY theo chuyến (tiền đường chuyến khác nhóm cảng): [{trip, cont, key, amount}] — trống = bỏ nhập tay.
        foreach ((array) ($data['amountItems'] ?? []) as $a) {
            if (! is_array($a) || ($key = (string) ($a['key'] ?? '')) === '') continue;
            $cont = (string) ($a['cont'] ?? ''); $trip = (string) ($a['trip'] ?? '');
            $cur = $this->tripOvGet($tripOv, $trip, $cont);
            $amts = is_array($cur['amounts'] ?? null) ? $cur['amounts'] : [];
            $raw = $a['amount'] ?? null;
            if ($raw === null || trim((string) $raw) === '') unset($amts[$key]);
            else $amts[$key] = $this->inMoney($raw) ?? 0;
            if ($amts) $cur['amounts'] = $amts; else unset($cur['amounts']);
            $this->tripOvPut($tripOv, $trip, $cont, $cur);
        }
        \App\Models\TruckingRoutePay::updateOrCreate(
            ['work_date' => $date, 'bks' => $bks],
            [
                'vehicle_id' => $veh?->id,
                'driver'     => $driver ?: null,
                'driver_id'  => $dkey !== '' ? ($this->driverIdMap()[$dkey] ?? null) : null,
                'paid'       => $paid,
                'paid_date'  => $paid ? ($this->inDate($data['paidDate'] ?? null) ?: $date) : null,
                'note'       => $this->str($data['note'] ?? null),
                'extra_items' => $extraItems,
                'trip_overrides' => $tripOv ?: null,
                'updated_by' => auth()->id(),
            ]
        );
        return ['ok' => true];
    }

    /**
     * Chỉnh tay của 1 chuyến trong trip_overrides: khóa theo LÔ ($trip = '#id') vì 1 cont có thể chạy nhiều chuyến / ngày.
     * Dữ liệu cũ khóa theo cont → chuyến chưa có khóa riêng thì đọc theo cont.
     */
    private function tripOvGet(array $map, string $trip, string $cont): array
    {
        $v = ($trip !== '' && array_key_exists($trip, $map)) ? $map[$trip] : ($map[$cont] ?? null);
        return is_array($v) ? $v : [];
    }

    /** Ghi chỉnh tay của 1 chuyến; còn khóa cont cũ thì giữ cả mục rỗng để chuyến này không rơi về giá trị cũ của cont. */
    private function tripOvPut(array &$map, string $trip, string $cont, array $cur): void
    {
        $k = $trip !== '' ? $trip : $cont;
        if ($cur || ($trip !== '' && array_key_exists($cont, $map))) $map[$k] = $cur; else unset($map[$k]);
    }

    /**
     * Sửa LÍT DẦU của 1 chuyến ở Lộ trình — chỉ ghi route_pays.trip_overrides[trip].liters, không đụng lái nhận / chi khác.
     * $liters null = bỏ sửa tay, về định mức phí tuyến. Ngày đã chốt thì không cho sửa (số đã đóng băng).
     */
    public function saveRouteFuel(string $date, string $bks, string $cont, ?float $liters, string $trip = ''): array
    {
        $bks = trim($bks); $date = trim($date);
        if ($bks === '' || $date === '') return ['ok' => false, 'message' => 'Thiếu ngày/biển số'];
        $row = \App\Models\TruckingRoutePay::whereDate('work_date', $date)->where('bks', $bks)->first()
            ?? new \App\Models\TruckingRoutePay(['work_date' => $date, 'bks' => $bks]);
        if ($row->frozen) return ['ok' => false, 'message' => 'Ngày đã chốt — bỏ chốt ngày để sửa lít dầu'];
        $map = is_array($row->trip_overrides) ? $row->trip_overrides : [];
        $cur = $this->tripOvGet($map, $trip, $cont);
        if ($liters === null || $liters < 0) unset($cur['liters']); else $cur['liters'] = round($liters);   // lít nguyên, không lẻ
        $this->tripOvPut($map, $trip, $cont, $cur);
        $row->trip_overrides = $map ?: null;
        if (! $row->vehicle_id) $row->vehicle_id = TruckingVehicle::where('plate', $bks)->value('id');
        $row->updated_by = auth()->id();
        $row->save();
        return ['ok' => true];
    }

    /**
     * Sửa tay SỐ TIỀN 1 khoản của 1 chuyến ngay trên Lộ trình (phụ cấp đi đường / lương chuyến) — chỉ ghi
     * trip_overrides[trip].amounts[key], không đụng lái nhận / chi khác. $amount null = về mức quy chế.
     */
    public function saveRouteAmount(string $date, string $bks, string $cont, string $key, ?int $amount, string $trip = ''): array
    {
        $bks = trim($bks); $date = trim($date);
        if ($bks === '' || $date === '') return ['ok' => false, 'message' => 'Thiếu ngày/biển số'];
        if (! in_array($key, ['tienDuong', 'luong', 'veTram'], true)) return ['ok' => false, 'message' => 'Khoản không cho sửa'];
        $row = \App\Models\TruckingRoutePay::whereDate('work_date', $date)->where('bks', $bks)->first()
            ?? new \App\Models\TruckingRoutePay(['work_date' => $date, 'bks' => $bks]);
        if ($row->frozen) return ['ok' => false, 'message' => 'Ngày đã chốt — bỏ chốt ngày để sửa'];
        $map = is_array($row->trip_overrides) ? $row->trip_overrides : [];
        $cur = $this->tripOvGet($map, $trip, $cont);
        $amts = is_array($cur['amounts'] ?? null) ? $cur['amounts'] : [];
        if ($amount === null) unset($amts[$key]); else $amts[$key] = max(0, $amount);
        if ($amts) $cur['amounts'] = $amts; else unset($cur['amounts']);
        $this->tripOvPut($map, $trip, $cont, $cur);
        $row->trip_overrides = $map ?: null;
        if (! $row->vehicle_id) $row->vehicle_id = TruckingVehicle::where('plate', $bks)->value('id');
        $row->updated_by = auth()->id();
        $row->save();
        return ['ok' => true];
    }

    /**
     * ĐÓNG BĂNG (chốt) cả ngày: snapshot số tiền đã tính cho MỌI xe ngày đó vào route_pays
     * (frozen_data) để KHÔNG đổi khi sau này sửa Phí tuyến. $frozen=false → bỏ chốt.
     */
    public function freezeDay(string $date, bool $frozen): array
    {
        $date = trim($date);
        if ($date === '') return ['ok' => false, 'message' => 'Thiếu ngày'];
        if (! $frozen) {
            \App\Models\TruckingRoutePay::whereDate('work_date', $date)
                ->update(['frozen' => false, 'frozen_at' => null, 'frozen_data' => null]);
            return ['ok' => true, 'frozen' => false];
        }
        $day = $this->routeTripByDate($date);
        $n = 0;
        foreach ($day['trucks'] as $t) {
            $bks = $t['bks'];
            if ($bks === '') continue;
            $veh = TruckingVehicle::where('plate', $bks)->first();
            $row = \App\Models\TruckingRoutePay::firstOrNew(['work_date' => $date, 'bks' => $bks]);
            if (! $row->vehicle_id) $row->vehicle_id = $veh?->id;
            $row->frozen = true;
            $row->frozen_at = now();
            $row->frozen_data = [
                'payGroups'    => $t['payGroups'],
                'payTotal'     => $t['payTotal'],
                'payrollTotal' => $t['payrollTotal'],
                'payWarn'      => $t['payWarn'],
                'khacOut'      => true,   // chi phí khác đã tách khỏi sub/payTotal (snapshot cũ không có cờ → đọc sẽ tự trừ)
            ];
            $row->updated_by = auth()->id();
            $row->save();
            $n++;
        }
        return ['ok' => true, 'frozen' => true, 'count' => $n];
    }

    /**
     * Kỳ LƯƠNG lái xe — gom theo BIỂN SỐ XE qua khoảng ngày [from..to]:
     *  - payroll  = Σ khoản CHƯA "chi theo ngày" (lương gom trả 1 đợt) = TIỀN PHẢI TRẢ.
     *  - paidDaily = Σ khoản "chi theo ngày" TÍNH LƯƠNG (đã thanh toán theo ngày) — tham khảo.
     *  - chiHo    = Σ khoản chi theo ngày chọn "Chi hộ" — đã đưa lái nhưng KHÔNG tính vào lương.
     *  - chiKhac  = Σ chi khác phát sinh chọn "Chi phí khác" — đã đưa lái, KHÔNG tính lương, thống kê riêng.
     * Lái xe TỰ GÁN theo thời điểm (lấy từ route_pays.driver các ngày trong kỳ).
     */
    public function computePayroll(?string $from, ?string $to): array
    {
        $start = Carbon::parse($from ?: now()->format('Y-m-d'))->startOfDay();
        $end   = Carbon::parse($to ?: ($from ?: now()->format('Y-m-d')))->startOfDay();
        if ($end->lt($start)) { $tmp = $start; $start = $end; $end = $tmp; }

        $byBks = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $ds  = $d->format('Y-m-d');
            $day = $this->routeTripByDate($ds);
            foreach ($day['trucks'] as $t) {
                $bks = $t['bks'];
                if (! isset($byBks[$bks])) {
                    $byBks[$bks] = ['bks' => $bks, 'vehicleId' => $t['vehicleId'] ?? null, 'type' => $t['type'], 'axle' => $t['axle'],
                        'drivers' => [], 'days' => 0, 'trips' => 0, 'paidDaily' => 0, 'chiHo' => 0, 'chiKhac' => 0, 'payroll' => 0, 'lines' => []];
                }
                $byBks[$bks]['days']++;
                $byBks[$bks]['trips']     += count(array_filter($t['payGroups'], fn ($g) => empty($g['orphan'])));
                $ch = (int) ($t['chohoTotal'] ?? 0);
                $kh = (int) ($t['khacTotal'] ?? 0);
                $byBks[$bks]['paidDaily'] += $t['payTotal'] - $ch;   // payTotal đã không gồm chi phí khác (chi phí công ty)
                $byBks[$bks]['chiHo']     += $ch;
                $byBks[$bks]['chiKhac']   += $kh;
                $byBks[$bks]['payroll']   += $t['payrollTotal'];
                if (! empty($t['payDriver'])) $byBks[$bks]['drivers'][$t['payDriver']] = true;
                $byBks[$bks]['lines'][] = ['date' => $ds, 'driver' => $t['payDriver'] ?? '',
                    'paidDaily' => $t['payTotal'] - $ch, 'chiHo' => $ch, 'chiKhac' => $kh, 'payroll' => $t['payrollTotal'], 'groups' => $t['payGroups']];
            }
        }

        $rows = [];
        foreach ($byBks as $r) {
            $driverNames = array_keys($r['drivers']);
            $r['driver'] = implode(', ', $driverNames);   // lái auto theo thời điểm
            $r['driverId'] = count($driverNames) === 1 ? $this->resolveDriverId($driverNames[0]) : null;
            unset($r['drivers']);
            $r['total'] = $r['payroll'];                               // lương phải trả đợt
            $rows[] = $r;
        }
        usort($rows, fn ($a, $b) => strcmp($a['bks'], $b['bks']));

        return [
            'from' => $start->format('Y-m-d'), 'to' => $end->format('Y-m-d'),
            'rows' => $rows,
            'grandPayroll'   => array_sum(array_column($rows, 'payroll')),
            'grandPaidDaily' => array_sum(array_column($rows, 'paidDaily')),
            'grandChiHo'     => array_sum(array_column($rows, 'chiHo')),
            'grandChiKhac'   => array_sum(array_column($rows, 'chiKhac')),
            'drivers' => $this->driversOut(),
        ];
    }

    /**
     * Thống kê cờ theo dõi trên tập lô (builder) — gom theo màu, đếm lô có cờ &
     * lô có cờ nhưng CHƯA điền tiền. Chỉ nạp đúng các dòng chi phí thuộc khoản
     * "theo dõi" (không nạp toàn bộ dòng con) → nhẹ.
     */
    private function followStats($shipQuery, array $autoHexes = []): array
    {
        // Dùng cost_item_id (FK) để thống kê, không phụ thuộc item text (đổi tên vẫn đúng).
        $items = TruckingCostItem::whereNotNull('color')->where('color', '!=', '')->get(['id', 'color']);
        if ($items->isEmpty()) return ['anyShips' => 0, 'missShips' => 0, 'byColor' => []];
        $idToHex   = $items->mapWithKeys(fn ($i) => [$i->id => $this->colorHex($i->color)])->all();
        $followIds = $items->pluck('id')->all();
        $autoSet   = array_flip($autoHexes);

        // ids lô trong phạm vi — cần cho khoản AUTO (mọi lô đều "expected", kể cả lô chưa có dòng).
        $shipIds = $shipQuery->pluck('id')->all();
        if (empty($shipIds)) return ['anyShips' => 0, 'missShips' => 0, 'byColor' => []];

        // per-ship: hex => ['present'=>có dòng, 'filled'=>có dòng ĐÃ điền số HĐ]
        $lines = TruckingCostLine::whereIn('shipment_id', $shipIds)
            ->whereIn('cost_item_id', $followIds)
            ->get(['shipment_id', 'cost_item_id', 'invoice_no'])
            ->groupBy('shipment_id');
        $perShip = [];
        foreach ($lines as $sid => $shipLines) {
            $m = [];
            foreach ($shipLines as $l) {
                $hex = $idToHex[$l->cost_item_id] ?? '';
                if ($hex === '') continue;
                $m[$hex] ??= ['present' => false, 'filled' => false];
                $m[$hex]['present'] = true;
                if (trim((string) $l->invoice_no) !== '') $m[$hex]['filled'] = true;
            }
            $perShip[$sid] = $m;
        }

        $allHexes = array_values(array_unique(array_values($idToHex)));
        $buckets = [];
        foreach ($allHexes as $hex) $buckets[$hex] = ['hex' => $hex, 'total' => 0, 'miss' => 0];
        $anyShips = 0; $missShips = 0;

        foreach ($shipIds as $sid) {
            $m = $perShip[$sid] ?? [];
            $shipExpected = false; $shipMiss = false;
            foreach ($allHexes as $hex) {
                $isAuto  = isset($autoSet[$hex]);
                $present = ! empty($m[$hex]['present']);
                $filled  = ! empty($m[$hex]['filled']);
                $expected = $isAuto ? true : $present;   // auto: mọi lô; thường: chỉ lô có dòng màu đó
                if (! $expected) continue;
                $buckets[$hex]['total']++;
                $shipExpected = true;
                if (! $filled) { $buckets[$hex]['miss']++; $shipMiss = true; }   // chưa điền số HĐ (hoặc chưa có dòng)
            }
            if ($shipExpected) $anyShips++;
            if ($shipMiss) $missShips++;
        }

        $byColor = array_values(array_filter($buckets, fn ($b) => $b['total'] > 0));
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
            ->with(['customer', 'costLines', 'revenueLines', 'payments', 'raOther:id,cont_no,bks_ra,gio_xe_ra'])
            ->get()
            ->map(fn ($s) => $this->shipmentToArray($s, false))
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
    /**
     * $columnPerms = false: trả nguyên dữ liệu, không cắt theo quyền cột của bảng Lô hàng.
     * Dùng cho module có quyền riêng và cần số liệu để tính (vd bảng kê cần số chi hộ).
     */
    public function shipmentToArray(TruckingShipment $s, bool $columnPerms = true): array
    {
        $arr = [
            'id'           => $s->id,
            'hashid'       => Hashid::encode($s->id),
            'customer'     => $s->customer?->name ?? '',
            'booking'      => $s->booking ?? '',
            'inv'          => $s->inv ?? '',
            'io'           => $s->io ?? '',
            'cru'          => (bool) $s->cru,
            'kepCont'      => (bool) $s->kep_cont,   // quy chế lương: kẹp 2 cont 20'
            'ketHop'       => (bool) $s->ket_hop,    // quy chế lương: kết hợp (cont nhập → đóng xuất nhà máy khác)
            'isBarge'      => (bool) $s->is_barge,
            'bargeCont'    => $s->barge_cont ?? '',
            'bargeDrop'    => $s->barge_drop ?? '',
            'qty'          => $s->qty,
            'contType'     => $s->cont_type ?? '',
            'contNo'       => $s->cont_no ?? '',
            'declNo'       => $s->declaration_no ?? '',   // chuỗi gộp — giữ cho bảng kê / tìm kiếm
            // Nguồn chân lý của tờ khai: mỗi tờ khai 1 số + 1 phí mở. Phí mở + phí thanh lý = THU KHÁCH qua Bảng kê.
            'declarations' => array_map(fn ($d) => [
                'no'  => (string) ($d['no'] ?? ''),
                'fee' => $this->outMoney($d['fee'] ?? 0),
            ], (array) ($s->declarations ?? [])),
            'thanhLyFee'   => $this->outMoney($s->thanh_ly_fee ?? 0),
            'declNote'     => $s->declaration_note ?? '',
            'thanhLy'      => $this->outDate($s->thanh_ly_date),
            'haCont'       => $this->outDate($s->ha_cont_date),   // có ngày = đã hạ cont
            'cshtNote'     => $s->csht_note ?? '',
            'kho'          => $s->kho ?? '',
            'from'         => $s->from_loc ?? '',
            'to'           => $s->to_loc ?? '',
            'fromCode'     => $this->locCode($s->from_loc),   // ký hiệu nơi lấy/hạ — file Xuất chi phí lô ghi "Tên — Ký hiệu"
            'toCode'       => $this->locCode($s->to_loc),
            // Chuỗi KÝ HIỆU tuyến: Nơi lấy → các Kho → Nơi hạ (vd HPP → QV → HPP) cho kế toán rà soát nhanh
            // từng lô. Rỗng (ẩn dòng) nếu y hệt chuỗi tên hiển thị (không có kho, ký hiệu = tên).
            'routeCodes'   => (function () use ($s) {
                $codes = array_values(array_filter(array_merge(
                    [$this->locCode($s->from_loc)],
                    $this->khoCodePoints($s->kho),
                    [$this->locCode($s->to_loc)],
                ), fn ($x) => trim((string) $x) !== ''));
                $names = array_values(array_filter([
                    trim((string) ($s->from_loc ?? '')),
                    trim((string) ($s->to_loc ?? '')),
                ], fn ($x) => $x !== ''));
                return $codes === $names ? [] : $codes;
            })(),
            'bksVao'       => $s->bks_vao ?? '',
            'bksRa'        => $s->bks_ra ?? '',
            'raMode'       => $s->ra_mode ?? 'self',
            'raOtherId'    => $s->ra_other_id,
            // Số cont của lô RA HỘ đi kèm luôn: lô đó thường ĐÃ RA nên không nằm trong tập đang xem
            // (vd xuất "chỉ lô chưa ra") — tra theo id ở client sẽ trượt, cột Excel thành trống.
            'raOtherContNo' => $s->raOther?->cont_no ?? '',
            'raOtherBksRa'  => $s->raOther?->bks_ra ?? '',    // BKS xe kéo cont ra hộ — cột BKS RA của file Excel khi Cont khác ra
            'extVendor'    => $s->ext_vendor ?? '',
            'extFee'       => $this->outMoney($s->ext_fee),
            'sailDate'     => $this->outDate($s->sail_date),
            'cutOff'       => $s->cut_off ?? '',
            'infoNote'     => $s->info_note ?? '',
            'tags'         => is_array($s->tags) ? array_values($s->tags) : [],
            'contDen'      => $this->outDate($s->cont_den),
            'contRa'       => $this->outDate($s->cont_ra),
            'gioDenDuKien' => $this->outDateTime($s->gio_den_du_kien),
            'gioXeDen'     => $this->outDateTime($s->gio_xe_den),
            'gioXeRa'      => $this->outDateTime($s->gio_xe_ra),
            'gioXeRaXe'    => $this->outDateTime($s->gio_xe_ra_xe),   // giờ XE ra (khi không kéo cont) — cột riêng
            // Giờ xe ra HIỆU LỰC cho Free time theo ra_mode: self→cont này ra; none→xe (đầu kéo) ra; other→cont KHÁC thực sự ra.
            'gioXeRaEff'   => $this->outDateTime(match ($s->ra_mode ?? 'self') {
                'none'  => $s->gio_xe_ra_xe,
                'other' => $s->raOther?->gio_xe_ra,
                default => $s->gio_xe_ra,
            }),
            'cost'         => [
                'items' => $s->costLines->map(fn ($c) => [
                    'id'       => $c->id,
                    'item'     => $c->item ?? '',
                    'amount'   => $this->outMoney($c->amount),
                    'vat'      => $this->outNum($c->vat),
                    'invoiceNo' => $c->invoice_no ?? '',
                    'payer'    => $c->payer ?? '',
                    'date'     => $this->outDate($c->date),
                    'billable' => (bool) $c->billable,
                    'extPay'   => (bool) $c->ext_pay,
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

        return $columnPerms ? ShipmentColumns::strip($arr) : $arr;
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
            // GIỮ NGUYÊN tên địa điểm cụ thể (vd "SITC ĐÌNH VŨ") — KHÔNG gộp về ký hiệu khi lưu;
            // ký hiệu (HPP) chỉ dùng để khớp giá/gộp báo cáo (qua codeMap), tên cụ thể vẫn hiển thị ở lô.
            $cols = [
                'booking'      => ['booking', $this->str($data['booking'] ?? null)],
                'inv'          => ['inv', $this->str($data['inv'] ?? null)],
                'io'           => ['io', $this->canonIo($data['io'] ?? null)],
                'cru'          => ['cru', ! empty($data['cru'])],
                'kepCont'      => ['kep_cont', ! empty($data['kepCont'])],
                'ketHop'       => ['ket_hop', ! empty($data['ketHop'])],
                'isBarge'      => ['is_barge', ! empty($data['isBarge'])],
                'bargeCont'    => ['barge_cont', $this->str($data['bargeCont'] ?? null)],
                'bargeDrop'    => ['barge_drop', $this->str($data['bargeDrop'] ?? null)],
                'qty'          => ['qty', isset($data['qty']) && $data['qty'] !== '' ? (int) $data['qty'] : null],
                'contType'     => ['cont_type', $this->str($data['contType'] ?? null)],
                'contNo'       => ['cont_no', $this->str($data['contNo'] ?? null)],
                'declNo'       => ['declaration_no', $this->str($data['declNo'] ?? null)],
                'declNote'     => ['declaration_note', $this->str($data['declNote'] ?? null)],
                'thanhLy'      => ['thanh_ly_date', $this->inDate($data['thanhLy'] ?? null)],
                'thanhLyFee'   => ['thanh_ly_fee', ($v = (int) round((float) $this->inMoney($data['thanhLyFee'] ?? null))) > 0 ? $v : null],
                'haCont'       => ['ha_cont_date', $this->inDate($data['haCont'] ?? null)],
                'cshtNote'     => ['csht_note', $this->str($data['cshtNote'] ?? null)],
                'kho'          => ['kho', $this->str($data['kho'] ?? null)],
                'from'         => ['from_loc', $this->str($data['from'] ?? null)],
                'to'           => ['to_loc', $this->str($data['to'] ?? null)],
                'bksVao'       => ['bks_vao', $this->str($data['bksVao'] ?? null)],
                'bksRa'        => ['bks_ra', $this->str($data['bksRa'] ?? null)],
                'driver'       => ['driver', $this->str($data['driver'] ?? null)],
                'raMode'       => ['ra_mode', $data['raMode'] ?? 'self'],
                'raOtherId'    => ['ra_other_id', $data['raOtherId'] ?? null],
                'extVendor'    => ['ext_vendor', $this->str($data['extVendor'] ?? null)],
                'sailDate'     => ['sail_date', $this->inDate($data['sailDate'] ?? null)],
                'cutOff'       => ['cut_off', $this->str($data['cutOff'] ?? null)],
                'infoNote'     => ['info_note', $this->str($data['infoNote'] ?? null)],
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
            if ($apply('kho')) $s->kho = $this->canonKhoNames($s->kho);
            // SÀ LAN: CÓ Nơi hạ sà lan = đi sà lan; loại DRY/NOR SUY TỪ Loại cont (reefer RF/RHC→NOR, còn lại→DRY).
            // Không còn cờ "Đi sà lan" / chọn DRur/NOR thủ công — derive khi sửa bargeDrop hoặc contType.
            if ($apply('bargeDrop') || $apply('contType')) {
                $hasBarge = trim((string) $s->barge_drop) !== '';
                $s->is_barge   = $hasBarge;
                $s->barge_cont = $hasBarge ? (preg_match('/R(F|HC|EEF)/i', (string) $s->cont_type) ? 'NOR' : 'DRY') : null;
            }
            // THUÊ XE NGOÀI bắt buộc chọn nhà xe: nếu lưu cost có dòng src=extTruck mà chưa có Nhà xe ngoài → chặn.
            if ($apply('cost')) {
                $hasExt = false;
                foreach (($data['cost']['items'] ?? []) as $c) if (($c['src'] ?? '') === 'extTruck') { $hasExt = true; break; }
                if ($hasExt && trim((string) $s->ext_vendor) === '') {
                    throw new \RuntimeException('Lô "Thuê xe ngoài" cần chọn Nhà xe ngoài.');
                }
            }
            // TỜ KHAI — 1 lô nhiều tờ khai, mỗi tờ khai 1 phí: [{no, fee}].
            // `declaration_no` vẫn được sinh ra (danh sách số cách nhau ", ") để tìm kiếm / bảng kê /
            // xuất Excel dùng như trước. Tổng phí mở là khoản THU KHÁCH — lên Bảng kê, KHÔNG thành chi phí lô.
            if ($apply('declarations')) {
                $s->declarations = $this->normDeclarations($data['declarations'] ?? []);
                $s->declaration_no = implode(', ', array_column($s->declarations, 'no')) ?: null;
            }
            // Nhãn (tags) — mảng chuỗi, chuẩn hóa: trim + bỏ rỗng + bỏ trùng (giữ thứ tự).
            if ($apply('tags')) {
                $tags = [];
                foreach ((array) ($data['tags'] ?? []) as $t) { $t = trim((string) $t); if ($t !== '' && ! in_array($t, $tags, true)) $tags[] = $t; }
                $s->tags = $tags;
            }
            // Đăng ký KÝ HIỆU địa điểm mới gõ tay vào danh mục — bảng kê khớp giá qua codeMap
            // (port từ registerLocationCode dùng cho import bảng giá). Idempotent: đã có → no-op.
            if ($apply('from')) $this->registerLocationCode($data['from'] ?? null);
            if ($apply('to'))   $this->registerLocationCode($data['to'] ?? null);
            if ($apply('bargeDrop')) $this->registerLocationCode($data['bargeDrop'] ?? null);
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
            // KHÔNG xóa-tạo-lại: dòng chi phí lô còn được import CSHT, cước xe ngoài (extTruck) và tờ khai ghi vào.
            // Popup mở sẵn rồi lưu chi phí theo danh sách đã cũ là mất/đè các dòng import vừa thêm.
            //  - Dòng có id thuộc lô này → cập nhật tại chỗ (giữ cost_item_id; recompute sẽ chốt lại).
            //  - Dòng không có id (id tạm phía client) → tạo mới.
            //  - Xóa CHỈ dòng client đã thấy lúc mở popup (cost.loadedIds) mà giờ không gửi lại = người dùng bấm xóa.
            //    Không có loadedIds (client cũ / gọi nội bộ) → không xóa gì.
            $existing  = $s->costLines()->get()->keyBy('id');
            $loadedIds = array_key_exists('loadedIds', (array) ($data['cost'] ?? []))
                ? array_map('intval', array_filter((array) $data['cost']['loadedIds'], 'is_numeric')) : null;
            $sentIds = [];
            foreach (($data['cost']['items'] ?? []) as $i => $c) {
                $id  = $c['id'] ?? null;
                $row = (is_numeric($id) && (string) (int) $id === (string) $id && isset($existing[(int) $id])) ? $existing[(int) $id] : null;
                // "Bỏ trống không lưu": khoản auto/khoản trống (không tiền + không số HĐ + không người chi/ghi chú/nguồn)
                // thì KHÔNG tạo dòng → tránh rác 0đ khi auto-hiện sẵn ở popup. Dòng cũ bị xóa trắng nội dung → xóa dòng.
                $amt = (int) round((float) $this->inMoney($c['amount'] ?? null));
                $hasContent = $amt !== 0
                    || trim((string) ($c['invoiceNo'] ?? '')) !== ''
                    || trim((string) ($c['note'] ?? '')) !== ''
                    || trim((string) ($c['payer'] ?? '')) !== ''
                    || trim((string) ($c['src'] ?? '')) !== '';
                if (! $hasContent) { if ($row) $row->delete(); continue; }
                $attrs = [
                    'item'     => $this->str($c['item'] ?? null),
                    'amount'   => $this->inMoney($c['amount'] ?? null),
                    'vat'      => $this->inNum($c['vat'] ?? null) ?? 0,
                    'invoice_no' => $this->str($c['invoiceNo'] ?? null),
                    'payer'    => $this->str($c['payer'] ?? null),
                    'date'     => $this->inDate($c['date'] ?? null),
                    'billable' => ! empty($c['billable']),
                    'color'    => $this->str($c['color'] ?? null),
                    'src'      => $this->str($c['src'] ?? null),
                    'note'     => $this->str($c['note'] ?? null),
                    'sort'     => $i,
                ];
                // Cờ "Chi cho xe ngoài" chỉ ghi khi client gửi key — client/import cũ không gửi thì giữ nguyên.
                if (array_key_exists('extPay', $c)) $attrs['ext_pay'] = ! empty($c['extPay']);
                if ($row) { $row->fill($attrs)->save(); $sentIds[] = $row->id; }
                else       { $sentIds[] = $s->costLines()->create($attrs)->id; }
            }
            if ($loadedIds !== null) {
                $gone = array_values(array_diff(array_intersect($loadedIds, $existing->keys()->all()), $sentIds));
                if ($gone) $s->costLines()->whereIn('id', $gone)->delete();
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

    /**
     * Ô Kho (danh sách TÊN kho, ", ") → đúng chữ hoa-thường của tên trong danh mục: gõ / import "ts", "Nakawa"
     * thành "TS", "NAKAWA" để mọi chỗ so theo tên (phí tuyến theo kho, báo cáo) không coi là kho khác. Đoạn không
     * khớp tên nào giữ nguyên.
     */
    public function canonKhoNames(?string $kho): ?string
    {
        if ($kho === null || trim($kho) === '') return $kho;
        $byUp = [];
        foreach (TruckingWarehouse::pluck('name') as $n) if (trim((string) $n) !== '') $byUp[mb_strtoupper(trim($n))] ??= trim($n);
        $segs = array_map(fn ($p) => $byUp[mb_strtoupper(trim($p))] ?? trim($p), preg_split('/\s*,\s*/u', trim($kho)));
        return implode(', ', array_filter($segs, fn ($p) => $p !== ''));
    }

    /**
     * Chuẩn hóa danh sách tờ khai → [{no, fee}]: bỏ dòng không có số, gộp khoảng trắng,
     * phí là số nguyên ≥ 0, bỏ số trùng (giữ dòng đầu). Nhận cả mảng chuỗi (chỉ số tờ khai).
     */
    private function normDeclarations($raw): array
    {
        $out = []; $seen = [];
        foreach ((array) $raw as $d) {
            if (! is_array($d)) $d = ['no' => $d, 'fee' => 0];
            $no = preg_replace('/\s+/u', ' ', trim((string) ($d['no'] ?? ''))) ?? '';
            if ($no === '') continue;
            $k = mb_strtolower($no);
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $out[] = ['no' => $no, 'fee' => max(0, (int) round((float) $this->inMoney($d['fee'] ?? null)))];
        }
        return $out;
    }

    /**
     * Mọi lô CÙNG BOOKING (cùng khách) — import "số lượng cont = N" đẻ ra N lô trống cont,
     * danh sách này để điền số cont cho cả nhóm trong 1 lần, không phải tìm từng lô.
     *
     * @return array<int,array<string,mixed>>
     */
    public function shipmentsOfBooking(string $sheet, string $booking, ?string $customer = null): array
    {
        $booking = trim($booking);
        if ($booking === '') return [];

        $q = TruckingShipment::ofSheet($sheet)->with('customer')->where('booking', $booking);
        // Booking có thể trùng giữa 2 khách → lọc thêm theo khách khi biết.
        if ($customer !== null && trim($customer) !== '') {
            $cname = preg_replace('/\s+/u', ' ', trim($customer)) ?? '';
            $q->whereHas('customer', fn ($c) => $c->where('name', $cname));
        }

        return $q->orderBy('id')->get()->map(fn ($s) => [
            'id'       => $s->id,
            'contNo'   => $s->cont_no ?? '',
            'contType' => $s->cont_type ?? '',
            'io'       => $s->io ?? '',
            'kho'      => $s->kho ?? '',
            'customer' => $s->customer->name ?? '',
            'booking'  => $s->booking ?? '',
        ])->all();
    }

    /**
     * Gán SỐ CONT cho từng lô theo cặp [id => số cont] (điền cont cho cả booking một lần).
     * Đi qua saveShipment với $only=['contNo'] → giữ nguyên mọi field khác và vẫn recompute
     * các cột dẫn xuất như khi sửa trong popup.
     *
     * @param  array<int,array{id:int|string,contNo:string}>  $pairs
     * @return int số lô đã ghi
     */
    public function assignContNos(string $sheet, array $pairs): int
    {
        $want = [];
        foreach ($pairs as $p) {
            $id = (int) ($p['id'] ?? 0);
            if ($id > 0) $want[$id] = $this->str($p['contNo'] ?? '');
        }
        if (empty($want)) return 0;

        $n = 0;
        foreach (TruckingShipment::ofSheet($sheet)->whereIn('id', array_keys($want))->get() as $s) {
            $new = $want[$s->id];
            if ((string) ($s->cont_no ?? '') === (string) ($new ?? '')) continue;   // không đổi → khỏi ghi
            $this->saveShipment(['contNo' => $new], $s->sheet ?: $sheet, $s, ['contNo']);
            $n++;
        }
        return $n;
    }

    /**
     * Cập nhật HÀNG LOẠT các lô đã chọn — booking, Nơi lấy/hạ, KH đến, Nơi hạ sà lan, cắt máng, thanh lý, hạ cont.
     * Tái dùng saveShipment với $only = các field CÓ giá trị (bỏ trống = KHÔNG đụng field đó),
     * nhờ vậy hưởng cùng logic: đăng ký ký hiệu địa điểm, suy is_barge/barge_cont, recompute.
     * @return int số lô đã cập nhật
     */
    public function bulkUpdateShipments(array $ids, array $data): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) return 0;

        // Chỉ áp field NGƯỜI DÙNG nhập (khác rỗng) → tránh xóa nhầm dữ liệu lô đang chọn.
        $only = [];
        if (isset($data['booking']) && trim((string) $data['booking']) !== '')      $only[] = 'booking';
        if (isset($data['from']) && trim((string) $data['from']) !== '')            $only[] = 'from';
        if (isset($data['gioDenDuKien']) && trim((string) $data['gioDenDuKien']) !== '') $only[] = 'gioDenDuKien';
        if (isset($data['to']) && trim((string) $data['to']) !== '')               $only[] = 'to';
        if (isset($data['bargeDrop']) && trim((string) $data['bargeDrop']) !== '')  $only[] = 'bargeDrop';
        if (isset($data['cutOff']) && trim((string) $data['cutOff']) !== '')        $only[] = 'cutOff';
        // Thanh lý tờ khai xét theo CÓ MẶT khóa (không theo "khác rỗng") vì bỏ đánh dấu = gửi null.
        if (array_key_exists('thanhLy', $data))                                    $only[] = 'thanhLy';
        if (array_key_exists('haCont', $data))                                     $only[] = 'haCont';   // hạ cont: cùng quy tắc
        // BKS vào: ô "gán xe nhanh" ngoài bảng Lô hàng. Cũng xét theo CÓ MẶT khóa vì bỏ gán = gửi ''.
        // Popup "Gán hàng loạt" không gửi khóa này nên không có đường xóa nhầm biển số cả loạt lô.
        if (array_key_exists('bksVao', $data))                                     $only[] = 'bksVao';
        if (empty($only)) return 0;

        $n = 0;
        foreach (TruckingShipment::whereIn('id', $ids)->get() as $s) {
            $this->saveShipment($data, $s->sheet ?: 'icd', $s, $only);
            $n++;
        }
        return $n;
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

    /**
     * Biển số → vehicle_id. Khớp CHUỖI CHÍNH XÁC trước; không thấy thì so theo DẠNG CHUẨN HÓA
     * (bỏ gạch/chấm/cách) — ô BKS ở lô giữ nguyên chuỗi người dùng gõ ("15H05578"), trong khi danh
     * mục xe lưu dạng chuẩn ("15H-05578"), nếu chỉ so chính xác thì lô mất liên kết xe (vehicle_id
     * NULL) và biến mất khỏi mọi báo cáo/lộ trình tính theo xe.
     */
    private function vehicleIdByPlate(?string $plate): ?int
    {
        $p = trim((string) $plate);
        if ($p === '') return null;
        if ($id = TruckingVehicle::where('plate', $p)->value('id')) return (int) $id;
        $n = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($p)) ?? '';
        if ($n === '') return null;
        $id = TruckingVehicle::whereRaw("UPPER(REPLACE(REPLACE(REPLACE(plate,'-',''),'.',''),' ','')) = ?", [$n])->value('id');
        return $id ? (int) $id : null;
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
        $needTier2   = $touches(['cost', 'rev', 'declarations']);
        $needTotals  = $touches(['cost', 'rev', 'declarations']);
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
            // Chi phí dùng số NET (đã trừ VAT): net = số tiền (gồm VAT) ÷ (1 + vat/100).
            $netOf        = fn ($c) => $c->netAmount();
            $costTotal    = (float) $s->costLines->sum($netOf);
            $costBillable = (float) $s->costLines->where('billable', true)->sum($netOf);
            $costCompany  = $costTotal - $costBillable;
            // Cước thuê xe ngoài (chốt) = tổng dòng chi phí src=extTruck — để Bảng kê xe ngoài query nhanh.
            $extFee       = (float) $s->costLines->where('src', 'extTruck')->sum('amount');
            $updates += [
                'rev_base' => $revBase, 'vat_amount' => $vat, 'choho_revenue' => $chohoRev,
                'phai_thu' => $phaiThu, 'da_thu' => $daThu, 'con_no' => $phaiThu - $daThu,
                'cost_total' => $costTotal, 'cost_billable' => $costBillable, 'cost_company' => $costCompany,
                'profit' => $revBase - $costCompany, 'ext_fee' => $extFee,
            ];
        }
        if ($needVehicle) {
            $updates['vehicle_id'] = $this->vehicleIdByPlate($s->bks_vao);
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
