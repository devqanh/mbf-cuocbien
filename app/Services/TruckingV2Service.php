<?php

namespace App\Services;

use App\Models\TruckingContType;
use App\Models\TruckingCostItem;
use App\Models\TruckingCostLine;
use App\Models\TruckingChohoItem;
use App\Models\TruckingCustomer;
use App\Models\TruckingDriver;
use App\Models\TruckingLocation;
use App\Models\TruckingPayer;
use App\Models\TruckingPriceRow;
use App\Models\TruckingRevenueItem;
use App\Models\TruckingSetting;
use App\Models\TruckingShipment;
use App\Models\TruckingStatement;
use App\Models\TruckingVehicle;
use App\Models\TruckingVehItem;
use App\Models\TruckingWarehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Trucking v2 — serialize DB ⇄ shape mà prototype (dev/trucking.html) dùng,
 * và persist (upsert lồng nhau). Mọi số tiền giao tiếp với frontend là chuỗi
 * chữ số (VND, không phần lẻ) khớp helper onlyDigits/groupVND bên client.
 */
class TruckingV2Service
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
            'vehItems'   => [TruckingVehItem::class,     true,  false,           false],
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

        $allCount = $searched()->count();
        $outCount = $searched()->whereNotNull('bks_ra')->where('bks_ra', '!=', '')->count();
        $filterCounts = ['all' => $allCount, 'out' => $outCount, 'notout' => $allCount - $outCount];

        $followStats = $this->followStats($searched(), $followNames, $nameHex);

        // --- Danh sách hiển thị: q + lọc + follow + sort + phân trang ---
        $list = $searched();
        if ($filter === 'out') {
            $list->whereNotNull('bks_ra')->where('bks_ra', '!=', '');
        } elseif ($filter === 'notout') {
            $list->where(fn ($w) => $w->whereNull('bks_ra')->orWhere('bks_ra', ''));
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
            ->toBase()->get(['id', 'cont_no', 'booking'])   // không hydrate model — chỉ cần 3 cột
            ->map(fn ($s) => ['id' => $s->id, 'contNo' => $s->cont_no ?? '', 'booking' => $s->booking ?? ''])
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

            return $s->fresh(['customer', 'costLines', 'revenueLines', 'payments']);
        });
    }

    // ===================================================================
    // CONFIG (master data) — serialize & persist
    // ===================================================================
    public function config(bool $withPrices = true, bool $priceCounts = false): array
    {
        $cfg = ['locationCode' => [], 'warehouseCode' => [], 'prices' => [], 'costColors' => []];

        // Mỗi danh mục một bảng riêng
        foreach ($this->lookups() as $key => [$cls, $priced, $coded, $colored]) {
            $rows = $cls::orderBy('sort')->orderBy('name')->get();
            $cfg[$key] = $rows->pluck('name')->all();
            if ($coded) {
                $cfg[$coded] = $rows->filter(fn ($r) => $r->code)
                    ->mapWithKeys(fn ($r) => [$r->name => $r->code])->all();
            }
            if ($priced) {
                foreach ($rows as $r) {
                    if ($r->default_price !== null) $cfg['prices'][$r->name] = $this->outMoney($r->default_price);
                }
            }
            if ($colored) {
                foreach ($rows as $r) {
                    if (!empty($r->color)) $cfg['costColors'][$r->name] = $r->color;
                }
            }
        }

        // Địa điểm đang được LINK (price_rows tham chiếu) → khóa, không cho sửa/xóa
        $lockedIds = TruckingPriceRow::query()->whereNotNull('location_id')->distinct()->pluck('location_id');
        $cfg['locationLocked'] = TruckingLocation::whereIn('id', $lockedIds)->pluck('name')->all();

        // Khách hàng + thông tin + bảng giá.
        //  - $withPrices=true  : kèm full priceList (trang dùng priceFor: Bảng kê / Tạo bảng kê).
        //  - $priceCounts=true : chỉ kèm priceCount cho badge (trang Bảng giá lazy-load từng khách).
        //  - cả hai false      : không bảng giá (trang Lô hàng / Cài đặt).
        // Lưu ý: KHÔNG set key 'priceList' ở chế độ count — để save (reconcileCustomers) không
        // tưởng nhầm "gửi danh sách rỗng" rồi xóa sạch bảng giá của khách chưa mở.
        $customers = TruckingCustomer::query()
            ->when($withPrices, fn ($q) => $q->with('priceRows'))
            ->when($priceCounts, fn ($q) => $q->withCount('priceRows'))
            ->orderBy('name')->get();
        $cfg['customers'] = $customers->pluck('name')->all();
        $cfg['customerInfo'] = $customers->mapWithKeys(function ($c) use ($withPrices, $priceCounts) {
            $entry = [
                'shortName' => $c->short_name ?? '',
                'taxCode'   => $c->tax_code ?? '',
                'phone'     => $c->phone ?? '',
                'contact'   => $c->contact ?? '',
                'email'     => $c->email ?? '',
                'termDays'  => $c->term_days !== null ? (string) $c->term_days : '',
                'address'   => $c->address ?? '',
                'note'      => $c->note ?? '',
            ];
            if ($withPrices)  $entry['priceList']  = $c->priceRows->map(fn ($p) => $this->priceRowToArray($p))->all();
            if ($priceCounts) $entry['priceCount'] = (int) ($c->price_rows_count ?? 0);
            return [$c->name => $entry];
        })->all();

        // Đội xe + loại
        $vehicles = TruckingVehicle::orderBy('plate')->get();
        $cfg['vehicles'] = $vehicles->pluck('plate')->all();
        $cfg['vehicleType'] = $vehicles->mapWithKeys(fn ($v) => [$v->plate => $v->type])->all();

        // Settings
        $cfg['vatDefault'] = [
            'hph' => TruckingSetting::get('vat_default_hph', '8'),
            'icd' => TruckingSetting::get('vat_default_icd', '0'),
        ];
        $cfg['freeTimeHours'] = TruckingSetting::get('free_time_hours', '4');

        return $cfg;
    }

    /** Đếm số mục mỗi danh mục — cho badge sidebar Cài đặt (không hydrate, rất nhẹ). */
    public function catalogCounts(): array
    {
        $c = [];
        foreach ($this->lookups() as $key => [$cls]) $c[$key] = $cls::count();
        $c['customers'] = TruckingCustomer::count();
        $c['vehicles']  = TruckingVehicle::count();
        return $c;
    }

    /**
     * Dữ liệu của ĐÚNG 1 tab Cài đặt (lazy-load khi click tab) — tránh nạp toàn bộ danh mục
     * cùng lúc (nguy hiểm khi 2 người cùng cấu hình + nặng). Mỗi lần mở tab lấy dữ liệu TƯƠI.
     */
    public function catalogData(string $key): array
    {
        $lk = $this->lookups();
        if (isset($lk[$key])) {
            [$cls, $priced, $coded, $colored] = $lk[$key];
            $rows = $cls::orderBy('sort')->orderBy('name')->get();
            $out = [$key => $rows->pluck('name')->all()];
            if ($coded) {
                $out[$coded] = $rows->filter(fn ($r) => $r->code)->mapWithKeys(fn ($r) => [$r->name => $r->code])->all();
                if ($key === 'locations') {
                    $lockedIds = TruckingPriceRow::query()->whereNotNull('location_id')->distinct()->pluck('location_id');
                    $out['locationLocked'] = TruckingLocation::whereIn('id', $lockedIds)->pluck('name')->all();
                }
            }
            if ($priced) {
                $out['prices'] = [];
                foreach ($rows as $r) if ($r->default_price !== null) $out['prices'][$r->name] = $this->outMoney($r->default_price);
            }
            if ($colored) {
                $out['costColors'] = [];
                foreach ($rows as $r) if (! empty($r->color)) $out['costColors'][$r->name] = $r->color;
            }
            return $out;
        }
        if ($key === 'customers') {
            $customers = TruckingCustomer::orderBy('name')->get();
            return [
                'customers'    => $customers->pluck('name')->all(),
                'customerInfo' => $customers->mapWithKeys(fn ($c) => [$c->name => [
                    'shortName' => $c->short_name ?? '', 'taxCode' => $c->tax_code ?? '', 'phone' => $c->phone ?? '',
                    'contact' => $c->contact ?? '', 'email' => $c->email ?? '',
                    'termDays' => $c->term_days !== null ? (string) $c->term_days : '', 'address' => $c->address ?? '', 'note' => $c->note ?? '',
                ]])->all(),
            ];
        }
        if ($key === 'vehicles') {
            $v = TruckingVehicle::orderBy('plate')->get();
            return ['vehicles' => $v->pluck('plate')->all(), 'vehicleType' => $v->mapWithKeys(fn ($x) => [$x->plate => $x->type])->all()];
        }
        if ($key === '__vat') {
            return ['vatDefault' => ['hph' => TruckingSetting::get('vat_default_hph', '8'), 'icd' => TruckingSetting::get('vat_default_icd', '0')]];
        }
        if ($key === '__freetime') {
            return ['freeTimeHours' => TruckingSetting::get('free_time_hours', '4')];
        }
        return [];
    }

    /**
     * Bảng giá của ĐÚNG 1 khách (lazy-load cho trang Bảng giá). Dùng toBase() để KHÔNG
     * hydrate model Eloquent — chỉ đọc + serialize nên stdClass đủ; bảng giá lớn (hàng
     * trăm dòng) nhờ vậy nhẹ RAM/CPU hơn nhiều.
     */
    public function customerPriceList(string $name): array
    {
        $name = trim($name);
        if ($name === '') return [];
        $custId = TruckingCustomer::where('name', $name)->value('id');
        if (! $custId) return [];

        return TruckingPriceRow::where('customer_id', $custId)->orderBy('sort')
            ->toBase()->get()
            ->map(fn ($p) => $this->priceRowToArray($p))->all();
    }

    /**
     * Cfg TỐI THIỂU cho bảng danh sách Lô hàng (không có popup): chỉ màu theo dõi (chip
     * "chưa điền" trên dòng), ngưỡng free time (cột lịch trình) và VAT mặc định (thêm lô).
     * Toàn bộ danh mục dropdown (địa điểm, kho, khách, loại cont...) lazy-load khi mở popup.
     */
    public function shipmentBoardConfig(): array
    {
        return [
            'costColors'    => TruckingCostItem::whereNotNull('color')->where('color', '!=', '')
                                  ->pluck('color', 'name')->all(),
            'freeTimeHours' => TruckingSetting::get('free_time_hours', '4'),
            'vatDefault'    => [
                'hph' => TruckingSetting::get('vat_default_hph', '8'),
                'icd' => TruckingSetting::get('vat_default_icd', '0'),
            ],
        ];
    }

    /**
     * Cfg cho trang Bảng giá: chỉ khách hàng (+ đếm dòng giá cho badge) và địa điểm
     * (autocomplete trong PriceList). KHÔNG nạp các danh mục khác (kho/payer/loại cont...)
     * vì trang Bảng giá không dùng.
     */
    public function priceBookConfig(): array
    {
        $locations = TruckingLocation::orderBy('sort')->orderBy('name')->get();
        $customers = TruckingCustomer::withCount('priceRows')->orderBy('name')->get();

        return [
            'locations'    => $locations->pluck('name')->all(),
            'locationCode' => $locations->filter(fn ($l) => $l->code)->mapWithKeys(fn ($l) => [$l->name => $l->code])->all(),
            'customers'    => $customers->pluck('name')->all(),
            'customerInfo' => $customers->mapWithKeys(fn ($c) => [$c->name => [
                'shortName' => $c->short_name ?? '',
                'taxCode'   => $c->tax_code ?? '',
                'phone'     => $c->phone ?? '',
                'contact'   => $c->contact ?? '',
                'email'     => $c->email ?? '',
                'termDays'  => $c->term_days !== null ? (string) $c->term_days : '',
                'address'   => $c->address ?? '',
                'note'      => $c->note ?? '',
                'priceCount' => (int) ($c->price_rows_count ?? 0),
            ]])->all(),
        ];
    }

    /** Lưu toàn bộ cfg (tương thích cũ) — gọi reconcile cho mọi key có mặt. */
    public function saveConfig(array $cfg): void
    {
        DB::transaction(function () use ($cfg) {
            foreach ($this->lookups() as $key => [$cls, $priced, $coded, $colored]) {
                if (array_key_exists($key, $cfg)) $this->reconcileLookup($cls, $priced, $coded, $colored, $cfg, $key);
            }
            if (array_key_exists('customers', $cfg)) $this->reconcileCustomers($cfg);
            if (array_key_exists('vehicles', $cfg))  $this->reconcileVehicles($cfg);
            $this->reconcileSettings($cfg);
        });
    }

    /** Lưu RIÊNG 1 danh mục lookup (mỗi tab Cài đặt = 1 bảng). */
    public function saveCatalog(string $cfgKey, array $cfg): void
    {
        $lk = $this->lookups();
        if (! isset($lk[$cfgKey])) {
            throw new \InvalidArgumentException("Danh mục không hợp lệ: {$cfgKey}");
        }
        [$cls, $priced, $coded, $colored] = $lk[$cfgKey];
        DB::transaction(fn () => $this->reconcileLookup($cls, $priced, $coded, $colored, $cfg, $cfgKey));
    }

    public function saveCustomers(array $cfg): void { DB::transaction(fn () => $this->reconcileCustomers($cfg)); }

    /** Đổi TÊN khách hàng (update theo id — giữ nguyên liên kết lô hàng & bảng giá). */
    public function renameCustomer(string $old, string $new): array
    {
        $old = trim($old); $new = trim($new);
        if ($new === '') return ['ok' => false, 'message' => 'Tên mới không được trống'];
        $cust = TruckingCustomer::where('name', $old)->first();
        if (! $cust) return ['ok' => false, 'message' => 'Không tìm thấy khách hàng'];
        if ($new !== $old && TruckingCustomer::where('name', $new)->exists()) {
            return ['ok' => false, 'message' => 'Tên khách hàng đã tồn tại'];
        }
        $cust->update(['name' => $new]);
        return ['ok' => true];
    }
    public function saveVehicles(array $cfg): void  { DB::transaction(fn () => $this->reconcileVehicles($cfg)); }
    public function saveSettings(array $cfg): void  { DB::transaction(fn () => $this->reconcileSettings($cfg)); }

    /** @return string[] danh sách cfgKey lookup hợp lệ (cho validate route). */
    public function catalogKeys(): array { return array_keys($this->lookups()); }

    // --- reconcile từng bảng (dùng chung cho saveConfig & endpoint riêng) ---
    private function reconcileLookup(string $cls, bool $priced, $coded, bool $colored, array $cfg, string $key): void
    {
        $names = array_values(array_filter(array_map('trim', $cfg[$key] ?? []), fn ($v) => $v !== ''));
        $cls::whereNotIn('name', $names ?: [''])->delete();
        foreach ($names as $i => $name) {
            $attrs = ['sort' => $i];
            if ($coded)   $attrs['code']  = $cfg[$coded][$name] ?? null;
            if ($priced)  $attrs['default_price'] = isset($cfg['prices'][$name]) ? $this->inMoney($cfg['prices'][$name]) : null;
            if ($colored) $attrs['color'] = $cfg['costColors'][$name] ?? null;
            $cls::updateOrCreate(['name' => $name], $attrs);
        }
    }

    private function reconcileCustomers(array $cfg): void
    {
        $names = array_values(array_filter(array_map('trim', $cfg['customers'] ?? []), fn ($v) => $v !== ''));
        TruckingCustomer::whereNotIn('name', $names ?: [''])->delete();
        $info = $cfg['customerInfo'] ?? [];
        foreach ($names as $name) {
            $d = $info[$name] ?? [];
            $cust = TruckingCustomer::updateOrCreate(['name' => $name], [
                'short_name' => $d['shortName'] ?? null,
                'tax_code'   => $d['taxCode'] ?? null,
                'phone'      => $d['phone'] ?? null,
                'contact'    => $d['contact'] ?? null,
                'email'      => $d['email'] ?? null,
                'term_days'  => isset($d['termDays']) && $d['termDays'] !== '' ? (int) $d['termDays'] : null,
                'address'    => $d['address'] ?? null,
                'note'       => $d['note'] ?? null,
            ]);
            // Chỉ động vào bảng giá khi payload có 'priceList' (trang Bảng giá);
            // trang Cài đặt > Khách hàng không gửi priceList → giữ nguyên giá.
            if (array_key_exists('priceList', $d)) {
                $cust->priceRows()->delete();
                foreach (($d['priceList'] ?? []) as $i => $p) {
                    $cust->priceRows()->create($this->priceRowAttrs($p, $i));
                }
            }
        }
    }

    private function reconcileVehicles(array $cfg): void
    {
        $plates = array_values(array_filter(array_map('trim', $cfg['vehicles'] ?? []), fn ($v) => $v !== ''));
        TruckingVehicle::whereNotIn('plate', $plates ?: [''])->delete();
        foreach ($plates as $plate) {
            TruckingVehicle::updateOrCreate(['plate' => $plate], ['type' => $cfg['vehicleType'][$plate] ?? 'MBF']);
        }
    }

    private function reconcileSettings(array $cfg): void
    {
        if (isset($cfg['vatDefault']['hph'])) TruckingSetting::put('vat_default_hph', $cfg['vatDefault']['hph']);
        if (isset($cfg['vatDefault']['icd'])) TruckingSetting::put('vat_default_icd', $cfg['vatDefault']['icd']);
        if (array_key_exists('freeTimeHours', $cfg)) TruckingSetting::put('free_time_hours', $cfg['freeTimeHours']);
    }

    /** Map lower(name|code) ⇒ tên địa điểm chuẩn (để validate + chuẩn hóa NƠI LẤY/HẠ). */
    private function locationNameMap(): array
    {
        $map = [];
        foreach (TruckingLocation::get(['name', 'code']) as $l) {
            if ($l->name) $map[mb_strtolower(trim($l->name))] = $l->name;
            if ($l->code) $map[mb_strtolower(trim($l->code))] = $l->name ?: $l->code;
        }
        return $map;
    }

    /**
     * Kiểm tra TRƯỚC toàn bộ dòng import (không ghi DB). Trả danh sách lỗi rõ
     * ràng theo từng dòng/booking. Quy tắc: khách hàng + NƠI LẤY + NƠI HẠ đều
     * phải có sẵn trong hệ thống.
     */
    public function validateShipmentRows(array $rows): array
    {
        $custSet = array_flip(TruckingCustomer::pluck('name')->map(fn ($n) => mb_strtolower(trim($n)))->all());
        $locMap = $this->locationNameMap();
        $errors = [];

        foreach ($rows as $i => $row) {
            $reasons = [];
            $name = trim((string) ($row['customer'] ?? ''));
            if ($name === '')                                     $reasons[] = 'Thiếu khách hàng';
            elseif (! isset($custSet[mb_strtolower($name)]))      $reasons[] = "Khách hàng “{$name}” chưa có trong hệ thống";

            $from = trim((string) ($row['from'] ?? ''));
            if ($from === '')                                     $reasons[] = 'Thiếu nơi lấy';
            elseif (! isset($locMap[mb_strtolower($from)]))       $reasons[] = "Nơi lấy “{$from}” chưa có trong danh mục địa điểm";

            $to = trim((string) ($row['to'] ?? ''));
            if ($to === '')                                       $reasons[] = 'Thiếu nơi hạ';
            elseif (! isset($locMap[mb_strtolower($to)]))         $reasons[] = "Nơi hạ “{$to}” chưa có trong danh mục địa điểm";

            if ($reasons) {
                $errors[] = ['line' => $i + 1, 'customer' => $name, 'booking' => (string) ($row['booking'] ?? ''), 'reasons' => $reasons];
            }
        }
        return $errors;
    }

    /** Dry-run: chỉ kiểm tra, không import. */
    public function validateShipments(array $rows): array
    {
        $errors = $this->validateShipmentRows($rows);
        return ['valid' => empty($errors), 'total' => count($rows), 'errors' => $errors];
    }

    /**
     * Import lô hàng — ALL-OR-NOTHING: chỉ cần 1 dòng lỗi là KHÔNG import gì cả.
     * NƠI LẤY/HẠ được chuẩn hóa về tên địa điểm chuẩn.
     */
    public function importShipments(string $sheet, array $rows): array
    {
        $errors = $this->validateShipmentRows($rows);
        if ($errors) {
            return ['valid' => false, 'created' => 0, 'ships' => [], 'errors' => $errors, 'total' => count($rows)];
        }

        $vat = (string) TruckingSetting::get($sheet === 'hph' ? 'vat_default_hph' : 'vat_default_icd', '0');
        $locMap = $this->locationNameMap();
        $norm = fn ($v) => $locMap[mb_strtolower(trim((string) $v))] ?? $v;

        return DB::transaction(function () use ($sheet, $rows, $vat, $norm) {
            $ships = [];
            foreach ($rows as $row) {
                $base = [
                    'customer'     => $row['customer'] ?? null,
                    'booking'      => $row['booking'] ?? null,
                    'inv'          => $row['inv'] ?? null,
                    'io'           => $row['io'] ?? null,
                    'qty'          => 1,
                    'contType'     => $row['contType'] ?? null,
                    'cutOff'       => $row['cutOff'] ?? null,
                    'from'         => $norm($row['from'] ?? null),
                    'to'           => $norm($row['to'] ?? null),
                    'kho'          => $row['kho'] ?? null,
                    'gioDenDuKien' => $row['gioDenDuKien'] ?? null,
                    'cost'         => ['items' => []],
                    'rev'          => ['vatRate' => $vat, 'doanhThu' => [], 'choHo' => [], 'payments' => []],
                ];
                // Ưu tiên 1: Tên container có nhiều số (mỗi dòng 1 số) → tách mỗi số 1 lô
                $conts = array_values(array_filter(array_map('trim', preg_split('/[\r\n;,]+/', (string) ($row['contNo'] ?? ''))), fn ($v) => $v !== ''));
                if (count($conts)) {
                    foreach ($conts as $cn) {
                        $ships[] = $this->shipmentToArray($this->saveShipment($base + ['contNo' => $cn], $sheet));
                    }
                    continue;
                }
                // Ưu tiên 2: cont trống → nhân bản theo SỐ LƯỢNG (để điền số cont sau)
                $qd = preg_replace('/[^\d]/', '', (string) ($row['qty'] ?? ''));
                $n = $qd === '' ? 1 : max(1, (int) $qd);
                for ($k = 0; $k < $n; $k++) {
                    $ships[] = $this->shipmentToArray($this->saveShipment($base + ['contNo' => null], $sheet));
                }
            }
            return ['valid' => true, 'created' => count($ships), 'ships' => $ships, 'errors' => [], 'total' => count($rows)];
        });
    }

    // ===================================================================
    // PRICE ROWS (bảng giá) — serialize, persist, import
    // ===================================================================
    /** 1 dòng bảng giá → shape frontend. */
    private function priceRowToArray($p): array
    {
        return [
            'id'         => $p->id,
            'locationId' => $p->location_id,
            'loc'        => $p->loc ?? '',
            'conn'       => $p->conn ?? 'Connect',
            'kind'       => $p->kind ?? '',
            'from'       => $p->from ?? '',
            'to1'        => $p->to1 ?? '',
            'to2'        => $p->to2 ?? '',
            'to3'        => $p->to3 ?? '',
            'to4'        => $p->to4 ?? '',
            'distance'   => $p->distance ?? '',
            'transFee40' => $this->outMoney($p->trans_fee_40),
            'transFee20' => $this->outMoney($p->trans_fee_20),
            'fuelFee40'  => $this->outMoney($p->fuel_fee_40),
            'fuelFee20'  => $this->outMoney($p->fuel_fee_20),
        ];
    }

    /** Thuộc tính DB của 1 dòng bảng giá (dùng cho cả lưu tay & import). */
    private function priceRowAttrs(array $p, int $i): array
    {
        return [
            'location_id'  => $this->resolveLocationId($p['loc'] ?? null),
            'loc'          => $this->str($p['loc'] ?? null),
            'conn'         => $p['conn'] ?? 'Connect',
            'kind'         => $this->str($p['kind'] ?? null),
            'from'         => $this->str($p['from'] ?? null),
            'to1'          => $this->str($p['to1'] ?? null),
            'to2'          => $this->str($p['to2'] ?? null),
            'to3'          => $this->str($p['to3'] ?? null),
            'to4'          => $this->str($p['to4'] ?? null),
            'distance'     => $this->str($p['distance'] ?? null),
            'trans_fee_40' => $this->inMoney($p['transFee40'] ?? null),
            'trans_fee_20' => $this->inMoney($p['transFee20'] ?? null),
            'fuel_fee_40'  => $this->inMoney($p['fuelFee40'] ?? null),
            'fuel_fee_20'  => $this->inMoney($p['fuelFee20'] ?? null),
            'sort'         => $i,
        ];
    }

    /**
     * "Điểm Hạ" → location_id: CHỈ khớp địa điểm đã có (theo code rồi name),
     * KHÔNG tự tạo địa điểm mới từ điểm hạ. Danh mục Địa điểm được nạp từ
     * cột FROM + TO (xem registerLocationCode). Chưa khớp → null.
     */
    private function resolveLocationId(?string $code): ?int
    {
        $code = trim((string) $code);
        if ($code === '') return null;

        $loc = TruckingLocation::where('code', $code)->first()
            ?? TruckingLocation::where('name', $code)->first();

        return $loc?->id;
    }

    /**
     * Đăng ký 1 KÝ HIỆU (FROM / TO) vào danh mục Địa điểm.
     * Đã có code → giữ; có name trùng nhưng thiếu code → gán code;
     * chưa có → tạo mới (name = code = ký hiệu) để user đặt tên sau.
     */
    private function registerLocationCode(?string $code): void
    {
        $code = trim((string) $code);
        if ($code === '') return;

        $loc = TruckingLocation::where('code', $code)->first()
            ?? TruckingLocation::where('name', $code)->first();

        if (! $loc) {
            TruckingLocation::create(['name' => $code, 'code' => $code]);
        } elseif (! $loc->code) {
            $loc->update(['code' => $code]);
        }
    }

    /**
     * Import bảng giá cho 1 khách từ các dòng đã parse (client).
     * Khóa định danh = (conn, loc, kind, from, to1..to4). Trùng → cập nhật
     * khoảng cách + phí; chưa có → tạo mới.
     */
    public function importPriceRows(string $customerName, array $rows, bool $replace = false): array
    {
        return DB::transaction(function () use ($customerName, $rows, $replace) {
            $cust = TruckingCustomer::firstOrCreate(['name' => trim($customerName)]);
            if ($replace) $cust->priceRows()->delete();   // xóa sạch bảng giá cũ trước khi nạp lại
            $created = 0; $updated = 0;
            $sort = (int) ($cust->priceRows()->max('sort') ?? 0);

            foreach ($rows as $p) {
                $attrs = $this->priceRowAttrs($p, 0);

                $q = $cust->priceRows();
                foreach (['conn', 'loc', 'kind', 'from', 'to1', 'to2', 'to3', 'to4'] as $k) {
                    $v = $attrs[$k];
                    $v === null ? $q->whereNull($k) : $q->where($k, $v);
                }
                $existing = $q->first();

                if ($existing) {
                    $existing->update([
                        'location_id'  => $attrs['location_id'],
                        'distance'     => $attrs['distance'],
                        'trans_fee_40' => $attrs['trans_fee_40'],
                        'trans_fee_20' => $attrs['trans_fee_20'],
                        'fuel_fee_40'  => $attrs['fuel_fee_40'],
                        'fuel_fee_20'  => $attrs['fuel_fee_20'],
                    ]);
                    $updated++;
                } else {
                    $attrs['sort'] = ++$sort;
                    $cust->priceRows()->create($attrs);
                    $created++;
                }
            }

            // Ký hiệu FROM + TO → danh mục Địa điểm; đồng thời TO → danh mục Kho.
            // (Điểm Hạ KHÔNG dùng để tạo địa điểm nữa.)
            $whNames = [];
            foreach ($rows as $p) {
                $this->registerLocationCode($p['from'] ?? null);
                foreach (['to1', 'to2', 'to3', 'to4'] as $k) {
                    $v = trim((string) ($p[$k] ?? ''));
                    if ($v === '') continue;
                    $this->registerLocationCode($v);
                    $whNames[$v] = true;
                }
            }
            foreach (array_keys($whNames) as $name) {
                TruckingWarehouse::firstOrCreate(['name' => $name], ['code' => $name]);
            }

            $cust->load('priceRows');
            return [
                'created'   => $created,
                'updated'   => $updated,
                'imported'  => $created + $updated,
                'priceList' => $cust->priceRows->map(fn ($p) => $this->priceRowToArray($p))->all(),
            ];
        });
    }

    // ===================================================================
    // STATEMENT (bảng kê) — serialize & persist
    // ===================================================================
    public function statements(): array
    {
        return TruckingStatement::with(['lines', 'payments'])->orderBy('id')->get()
            ->map(fn ($st) => $this->statementToArray($st))->all();
    }

    /**
     * Danh sách bảng kê cho trang Bảng kê (KePage) — CHỈ tóm tắt + payments, KHÔNG nạp
     * lines (snapshot từng lô) vì trang danh sách không hiển thị. Tránh hydrate hàng trăm
     * dòng statement_lines vô ích.
     */
    public function statementsForList(): array
    {
        return TruckingStatement::with(['payments', 'customer'])->orderBy('id')->get()
            ->map(fn ($st) => [
                'id'       => $st->id,
                'no'       => $st->no,
                'customer' => $st->customer_name ?? $st->customer?->name ?? '',
                'date'     => $this->outDate($st->date),
                'from'     => $this->outDate($st->period_from),
                'to'       => $this->outDate($st->period_to),
                'tongThu'  => (int) round((float) $st->total),
                'payments' => $st->payments->map(fn ($p) => [
                    'id'     => $p->id,
                    'date'   => $this->outDate($p->date),
                    'amount' => $this->outMoney($p->amount),
                    'note'   => $p->note ?? '',
                ])->all(),
            ])->all();
    }

    public function statementToArray(TruckingStatement $st): array
    {
        return [
            'id'        => $st->id,
            'no'        => $st->no,
            'customer'  => $st->customer_name ?? $st->customer?->name ?? '',
            'info'      => $st->info ?? [],
            'date'      => $this->outDate($st->date),
            'from'      => $this->outDate($st->period_from),
            'to'        => $this->outDate($st->period_to),
            'tongThu'   => (int) round((float) $st->total),
            'lines'     => $st->lines->map(fn ($l) => [
                'id'        => $l->shipment_id ?? $l->id,
                'booking'   => $l->booking ?? '',
                'sheet'     => $l->sheet ?? '',
                'io'        => $l->io ?? '',
                'declNo'    => $l->decl_no ?? '',
                'contType'  => $l->cont_type ?? '',
                'inv'       => $l->inv ?? '',
                'contNo'    => $l->cont_no ?? '',
                'bks'       => $l->bks ?? '',
                'from'      => $l->from_loc ?? '',
                'to'        => $l->to_loc ?? '',
                'date'      => $this->outDate($l->date),
                'contLabel' => $l->cont_label ?? '',
                'phaiThu'   => (int) round((float) $l->phai_thu),
                'cuoc'      => (int) round((float) $l->cuoc),
                'thanhLy'   => (int) round((float) $l->thanh_ly),
                'note'      => $l->note ?? '',
                'detail'    => $l->detail,
            ])->all(),
            'payments'  => $st->payments->map(fn ($p) => [
                'id'     => $p->id,
                'date'   => $this->outDate($p->date),
                'amount' => $this->outMoney($p->amount),
                'note'   => $p->note ?? '',
            ])->all(),
        ];
    }

    public function saveStatement(array $data, ?TruckingStatement $st = null): TruckingStatement
    {
        return DB::transaction(function () use ($data, $st) {
            $customerId = null;
            if (! empty($data['customer'])) {
                $customerId = TruckingCustomer::where('name', trim($data['customer']))->value('id');
            }

            $st ??= new TruckingStatement();
            $st->fill([
                'no'            => $data['no'] ?? $st->no,
                'customer_id'   => $customerId,
                'customer_name' => $this->str($data['customer'] ?? null),
                'info'          => $data['info'] ?? null,
                'date'          => $this->inDate($data['date'] ?? null),
                'period_from'   => $this->inDate($data['from'] ?? null),
                'period_to'     => $this->inDate($data['to'] ?? null),
                'total'         => $this->inMoney($data['tongThu'] ?? null) ?? 0,
            ]);
            $st->save();

            $st->lines()->delete();
            foreach (($data['lines'] ?? []) as $i => $l) {
                $st->lines()->create([
                    'shipment_id' => $l['id'] ?? null,
                    'booking'     => $this->str($l['booking'] ?? null),
                    'sheet'       => $this->str($l['sheet'] ?? null),
                    'io'          => $this->str($l['io'] ?? null),
                    'decl_no'     => $this->str($l['declNo'] ?? null),
                    'cont_type'   => $this->str($l['contType'] ?? null),
                    'inv'         => $this->str($l['inv'] ?? null),
                    'cont_no'     => $this->str($l['contNo'] ?? null),
                    'bks'         => $this->str($l['bks'] ?? null),
                    'from_loc'    => $this->str($l['from'] ?? null),
                    'to_loc'      => $this->str($l['to'] ?? null),
                    'date'        => $this->inDate($l['date'] ?? null),
                    'cont_label'  => $this->str($l['contLabel'] ?? null),
                    'phai_thu'    => $this->inMoney($l['phaiThu'] ?? null) ?? 0,
                    'cuoc'        => $this->inMoney($l['cuoc'] ?? null) ?? 0,
                    'thanh_ly'    => $this->inMoney($l['thanhLy'] ?? null) ?? 0,
                    'note'        => $this->str($l['note'] ?? null),
                    'detail'      => is_array($l['detail'] ?? null) ? $l['detail'] : null,
                    'sort'        => $i,
                ]);
            }

            $st->payments()->delete();
            foreach (($data['payments'] ?? []) as $i => $p) {
                $st->payments()->create([
                    'date'   => $this->inDate($p['date'] ?? null),
                    'amount' => $this->inMoney($p['amount'] ?? null),
                    'note'   => $this->str($p['note'] ?? null),
                    'sort'   => $i,
                ]);
            }

            return $st->fresh(['lines', 'payments']);
        });
    }

    // ===================================================================
    // Helpers — chuyển đổi giá trị in/out
    // ===================================================================
    private function str(?string $v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;
        return ($v === '' || $v === null) ? null : $v;
    }

    /** Chuỗi chữ số/tiền (frontend) → số nguyên DB (null nếu rỗng). */
    private function inMoney(mixed $v): ?int
    {
        if ($v === null || $v === '') return null;
        $digits = preg_replace('/[^\d]/', '', (string) $v);
        return $digits === '' ? null : (int) $digits;
    }

    private function outMoney(mixed $v): string
    {
        return ($v === null) ? '' : (string) (int) round((float) $v);
    }

    /** VAT %: giữ dạng gọn ("8" thay vì "8.00"). */
    private function inNum(mixed $v): ?float
    {
        if ($v === null || $v === '') return null;
        $n = preg_replace('/[^\d.]/', '', (string) $v);
        return $n === '' ? null : (float) $n;
    }

    private function outNum(mixed $v): string
    {
        if ($v === null) return '';
        $f = (float) $v;
        return floor($f) == $f ? (string) (int) $f : (string) $f;
    }

    private function inDate(?string $v): ?string
    {
        if (! $v) return null;
        try { return Carbon::parse($v)->format('Y-m-d'); } catch (\Throwable) { return null; }
    }

    private function outDate(mixed $v): string
    {
        if (! $v) return '';
        return $v instanceof Carbon ? $v->format('Y-m-d') : (string) $v;
    }

    private function inDateTime(?string $v): ?string
    {
        if (! $v) return null;
        try { return Carbon::parse($v)->format('Y-m-d H:i:s'); } catch (\Throwable) { return null; }
    }

    /** datetime-local: "YYYY-MM-DDTHH:MM". */
    private function outDateTime(mixed $v): string
    {
        if (! $v) return '';
        return $v instanceof Carbon ? $v->format('Y-m-d\TH:i') : (string) $v;
    }
}
