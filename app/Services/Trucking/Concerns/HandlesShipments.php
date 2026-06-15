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
            ->with(['customer', 'costLines', 'revenueLines', 'payments', 'spends'])
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

        // "Đã ra" = đã có GIỜ XE RA (hoặc Biển số ra). Ưu tiên giờ xe ra vì xe thuê ngoài
        // nhiều khi không cập nhật được biển số → chỉ cần có giờ ra là coi như đã ra.
        $applyOut = function ($q) {
            return $q->where(function ($w) {
                $w->where(fn ($a) => $a->whereNotNull('gio_xe_ra')->where('gio_xe_ra', '!=', ''))
                  ->orWhere(fn ($a) => $a->whereNotNull('bks_ra')->where('bks_ra', '!=', ''));
            });
        };
        $applyNotOut = function ($q) {
            return $q->where(fn ($a) => $a->whereNull('gio_xe_ra')->orWhere('gio_xe_ra', ''))
                     ->where(fn ($a) => $a->whereNull('bks_ra')->orWhere('bks_ra', ''));
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
            $list->orderBy('id');
        }

        $list->with(['customer', 'costLines', 'revenueLines', 'payments', 'spends']);
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
            ->with(['customer', 'costLines', 'revenueLines', 'payments', 'spends'])
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
            // Duyệt chi theo lô (theo biển kiểm soát) — các khoản THỰC CHI đã lưu.
            'spends' => $s->spends->map(fn ($x) => [
                'id'        => $x->id,
                'vehicleId' => $x->vehicle_id,
                'bks'       => $x->bks ?? '',
                'driver'    => $x->driver ?? '',
                'source'    => $x->source ?? 'other',
                'kind'      => $x->kind ?? 'company',
                'name'      => $x->name ?? '',
                'amount'    => $this->outMoney($x->amount),
                'spendDate' => $this->outDate($x->spend_date),
                'paid'      => (bool) $x->paid,
                'paidDate'  => $this->outDate($x->paid_date),
                'note'      => $x->note ?? '',
            ])->all(),
        ];
    }

    /**
     * Gợi ý các khoản DUYỆT CHI cho 1 lô từ Phí tuyến đường — reuse tripSuggest
     * (khớp route theo kho + CRU + số cầu + lái xe theo lịch). Trả các dòng chi
     * (vé trạm/tiền đường/trợ cấp/lương/dầu) đã phân loại kind salary|company.
     */
    public function shipmentSpendSuggest(TruckingShipment $s): array
    {
        $bundle = $this->tripConfigBundle();
        $sg     = $this->tripSuggest($s, $bundle);   // dùng chung logic với Phí xe
        $sug    = $sg['sug'];
        $parts  = is_array($sg['salaryParts']) ? $sg['salaryParts'] : [];
        $num    = fn ($v) => (float) preg_replace('/[^\d.-]/', '', (string) $v);

        $bks    = (string) ($s->bks_vao ?? '');
        $vehId  = optional($bundle['vehByPlate'][$bks] ?? null)->id;
        $driver = (string) ($sug['driver'] ?? '');
        $today  = now()->format('Y-m-d');

        $mk = fn ($source, $kind, $name, $amount) => [
            'source'    => $source,
            'kind'      => $kind,
            'name'      => $name,
            'amount'    => $this->outMoney($amount),
            'driver'    => $kind === 'salary' ? $driver : '',
            'bks'       => $bks,
            'vehicleId' => $vehId,
            'spendDate' => $today,
            'paid'      => false,   // mới gợi ý = CHƯA chi; tick "Đã chi" mới ghi nhận vào Phí xe
        ];

        $lines = [];
        // Các khoản phí cố định của tuyến (lương đã áp theo CRU sẵn trong sug['luong'])
        foreach (['veTram' => 'Vé trạm', 'tienDuong' => 'Tiền đường', 'troCap' => 'Trợ cấp', 'luong' => 'Lương'] as $key => $label) {
            $amt = $num($sug[$key] ?? 0);
            if ($amt <= 0) continue;
            $kind = in_array($key, $parts, true) ? 'salary' : 'company';
            $lines[] = $mk($key, $kind, $label, $amt);
        }
        // Dầu = lít × đơn giá (chi phí công ty)
        $fuel = (int) round($num($sug['fuelLiters'] ?? 0) * $num($sug['fuelPrice'] ?? 0));
        if ($fuel > 0) $lines[] = $mk('dau', 'company', 'Dầu', $fuel);

        return [
            'matched'   => (bool) $sg['matched'],
            'driver'    => $driver,
            'bks'       => $bks,
            'vehicleId' => $vehId,
            'spendDate' => $today,
            'lines'     => $lines,
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
                    $customerId = TruckingCustomer::firstOrCreate(['name' => trim($data['customer'])])->id;
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
            // rev scalars (VAT / hạn TT / ghi chú) đi cùng nhóm 'rev'
            if ($apply('rev')) {
                $s->vat_rate = $this->inNum($data['rev']['vatRate'] ?? null);
                $s->han_tt   = $this->inDate($data['rev']['hanTT'] ?? null);
                $s->ghi_chu  = $this->str($data['rev']['ghiChu'] ?? null);
            }
            $s->save();

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

            // Duyệt chi theo lô — reconcile (xóa & tạo lại) khi nhóm 'spends' được sửa.
            if ($apply('spends')) {
                $s->spends()->delete();
                $uid = auth()->id();
                // map tên lái xe → id (link cứng driver_id cho báo cáo lương; tên vẫn giữ snapshot)
                $driverIds = \App\Models\TruckingDriver::pluck('id', 'name');
                foreach (($data['spends'] ?? []) as $i => $sp) {
                    $kind = ($sp['kind'] ?? 'company') === 'salary' ? 'salary' : 'company';
                    $paid = ! empty($sp['paid']);
                    $spendDate = $this->inDate($sp['spendDate'] ?? null);
                    // paid_date = ngày tick "Đã chi" (paidDate nếu gửi, không thì lấy Ngày chi)
                    $paidDate = $paid ? ($this->inDate($sp['paidDate'] ?? null) ?: $spendDate) : null;
                    $driverName = $this->str($sp['driver'] ?? null);
                    $s->spends()->create([
                        'vehicle_id' => is_numeric($sp['vehicleId'] ?? null) ? (int) $sp['vehicleId'] : null,
                        'bks'        => $this->str($sp['bks'] ?? null),
                        'driver'     => $driverName,
                        'driver_id'  => $driverName ? ($driverIds[$driverName] ?? null) : null,
                        'source'     => $this->str($sp['source'] ?? null) ?: 'other',
                        'kind'       => $kind,
                        'name'       => $this->str($sp['name'] ?? null) ?: 'Khoản chi',
                        'amount'     => $this->inMoney($sp['amount'] ?? null) ?? 0,
                        'spend_date' => $spendDate,
                        'paid'       => $paid,
                        'paid_date'  => $paidDate,
                        'note'       => $this->str($sp['note'] ?? null),
                        'created_by' => $uid,
                        'sort'       => $i,
                    ]);
                }
            }

            $this->recomputeShipmentDerived($s);
            return $s->fresh(['customer', 'costLines', 'revenueLines', 'payments', 'spends']);
        });
    }

    /** Bản đồ tên/ký hiệu kho → id (cho tách kho theo lô). */
    private function warehouseIdMap(): array
    {
        $map = [];
        foreach (TruckingWarehouse::get(['id', 'name', 'code']) as $w) {
            if ($w->name) $map[mb_strtolower(trim($w->name))] = $w->id;
            if ($w->code) $map[mb_strtolower(trim($w->code))] = $w->id;
        }
        return $map;
    }

    /**
     * Chốt số liệu + tham chiếu BÁO CÁO cho 1 lô (gọi sau khi lưu / để backfill):
     * - Tier 2: gán cost_item_id/payer_id (cost), item_id (revenue) theo tên hiện tại.
     * - Tier 1: tổng doanh thu/VAT/chi hộ/phải thu/đã thu/còn nợ/chi phí/lợi nhuận.
     * - Tier 3: vehicle_id, from/to_location_id + bảng kho theo lô.
     * Giữ nguyên cột chuỗi (lịch sử); id chỉ là khóa join.
     */
    public function recomputeShipmentDerived(TruckingShipment $s): void
    {
        $s->load(['costLines', 'revenueLines', 'payments']);   // luôn nạp TƯƠI (tránh quan hệ cũ trong RAM)

        // Tier 2 — gán id khoản theo tên hiện tại
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

        // Tier 3 — khóa xe / địa điểm
        $vehicleId = $s->bks_vao ? TruckingVehicle::where('plate', $s->bks_vao)->value('id') : null;

        $s->forceFill([
            'rev_base'      => $revBase, 'vat_amount' => $vat, 'choho_revenue' => $chohoRev,
            'phai_thu'      => $phaiThu, 'da_thu' => $daThu, 'con_no' => $phaiThu - $daThu,
            'cost_total'    => $costTotal, 'cost_billable' => $costBillable, 'cost_company' => $costCompany,
            'profit'        => $revBase - $costCompany,
            'vehicle_id'    => $vehicleId,
            'from_location_id' => $this->resolveLocationId($s->from_loc),
            'to_location_id'   => $this->resolveLocationId($s->to_loc),
        ])->save();

        // Tier 3 — kho theo lô (mỗi kho 1 dòng)
        $whMap = $this->warehouseIdMap();
        $s->warehouses()->delete();
        $parts = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', (string) $s->kho)), fn ($x) => $x !== ''));
        foreach ($parts as $i => $name) {
            $s->warehouses()->create(['warehouse_id' => $whMap[mb_strtolower($name)] ?? null, 'name' => $name, 'sort' => $i]);
        }
    }

}
