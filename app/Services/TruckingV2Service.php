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

            $this->recomputeShipmentDerived($s);
            return $s->fresh(['customer', 'costLines', 'revenueLines', 'payments']);
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
                // Map tên→mã (cho định giá; tên trùng thì lấy mã cuối — chấp nhận được)
                $cfg[$coded] = $rows->filter(fn ($r) => $r->code)
                    ->mapWithKeys(fn ($r) => [$r->name => $r->code])->all();
                // Mảng mã theo CHỈ SỐ dòng (định danh thực = mã; tên được phép trùng)
                $cfg[$coded . 'Arr'] = $rows->map(fn ($r) => $r->code ?? '')->all();
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
        $cfg['vehicleAxle'] = $vehicles->filter(fn ($v) => $v->axle)->mapWithKeys(fn ($v) => [$v->plate => $v->axle])->all();

        // Settings
        $cfg['vatDefault'] = [
            'hph' => TruckingSetting::get('vat_default_hph', '8'),
            'icd' => TruckingSetting::get('vat_default_icd', '0'),
        ];
        $cfg['freeTimeHours'] = TruckingSetting::get('free_time_hours', '4');
        $cfg['dueWarnDays']   = TruckingSetting::get('due_warn_days', '30');

        return $cfg;
    }

    /** Đếm số mục mỗi danh mục — cho badge sidebar Cài đặt (không hydrate, rất nhẹ). */
    public function catalogCounts(): array
    {
        $c = [];
        foreach ($this->lookups() as $key => [$cls]) $c[$key] = $cls::count();
        $c['customers'] = TruckingCustomer::count();
        $c['vehicles']  = TruckingVehicle::count();
        $c['routeFees'] = TruckingRouteFee::count();
        $c['fuelPrices'] = TruckingFuelPrice::count();
        return $c;
    }

    /**
     * Dữ liệu của ĐÚNG 1 tab Cài đặt (lazy-load khi click tab) — tránh nạp toàn bộ danh mục
     * cùng lúc (nguy hiểm khi 2 người cùng cấu hình + nặng). Mỗi lần mở tab lấy dữ liệu TƯƠI.
     */
    public function catalogData(string $key): array
    {
        $lk = $this->lookups();
        if ($key === 'drivers') {            // hồ sơ lái xe đầy đủ (không chỉ tên)
            return ['drivers' => $this->driversManaged()];
        }
        if (isset($lk[$key])) {
            [$cls, $priced, $coded, $colored] = $lk[$key];
            $rows = $cls::orderBy('sort')->orderBy('name')->get();
            $out = [$key => $rows->pluck('name')->all()];
            if ($coded) {
                $out[$coded] = $rows->filter(fn ($r) => $r->code)->mapWithKeys(fn ($r) => [$r->name => $r->code])->all();
                $out[$coded . 'Arr'] = $rows->map(fn ($r) => $r->code ?? '')->all();   // mã theo chỉ số dòng
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
            return [
                'vehicles'    => $v->pluck('plate')->all(),
                'vehicleType' => $v->mapWithKeys(fn ($x) => [$x->plate => $x->type])->all(),
                'vehicleAxle' => $v->filter(fn ($x) => $x->axle)->mapWithKeys(fn ($x) => [$x->plate => $x->axle])->all(),
            ];
        }
        if ($key === '__general') {   // cấu hình chung: VAT mặc định + Free time + cảnh báo hạn (+ mở rộng sau)
            return [
                'vatDefault'    => ['hph' => TruckingSetting::get('vat_default_hph', '8'), 'icd' => TruckingSetting::get('vat_default_icd', '0')],
                'freeTimeHours' => TruckingSetting::get('free_time_hours', '4'),
                'dueWarnDays'   => TruckingSetting::get('due_warn_days', '30'),
            ];
        }
        if ($key === 'routeFees') {
            // kèm danh sách kho để chọn tuyến (MultiCombo)
            return ['routeFees' => $this->routeFees(), 'warehouses' => TruckingWarehouse::orderBy('sort')->orderBy('name')->pluck('name')->all()];
        }
        if ($key === 'fuelPrices') {
            return ['fuelPrices' => $this->fuelPrices()];
        }
        return [];
    }

    // ===================================================================
    // QUẢN LÝ XE (xe MBF nội bộ)
    // ===================================================================

    /** Danh sách xe MBF + đếm để hiện badge. */
    public function mbfVehicles(): array
    {
        return TruckingVehicle::where('type', 'MBF')
            ->withCount(['vehicleUsages', 'vehicleCosts', 'vehicleDepreciations'])
            ->orderBy('plate')->get()->map(function ($v) {
                $info = is_array($v->info) ? $v->info : [];
                return [
                    'id'              => $v->id,
                    'hashid'          => Hashid::encode($v->id),
                    'plate'           => $v->plate,
                    'axle'            => $v->axle ?? '',
                    'registrationDue' => $info['registrationDue'] ?? '',   // YYYY-MM-DD (hạn đăng kiểm)
                    'insuranceDue'    => $info['insuranceDue'] ?? '',       // YYYY-MM-DD (hạn bảo hiểm)
                    'docCount'        => is_array($v->documents) ? count($v->documents) : 0,
                    'usageCount'      => (int) $v->vehicle_usages_count,
                    'costCount'       => (int) $v->vehicle_costs_count,
                    'depCount'        => (int) $v->vehicle_depreciations_count,
                ];
            })->all();
    }

    /**
     * Chi phí ĐỊNH KỲ của xe MBF hết hạn / sắp hết (≤30 ngày) — để cảnh báo + popup.
     * Mỗi khoản (theo xe + TÊN) chỉ xét PHIẾU CHI MỚI NHẤT (due_date lớn nhất): khi tạo
     * phiếu mới hạn xa hơn, phiếu cũ tự hết nhắc. Sắp xếp hết hạn lâu nhất lên đầu.
     */
    public function expiringVehicleCosts(): array
    {
        $plates = TruckingVehicle::where('type', 'MBF')->pluck('plate', 'id');   // id => biển số
        if ($plates->isEmpty()) return [];

        $latest = [];   // "vehId|tên" => phiếu có due_date mới nhất
        foreach (TruckingVehicleCost::whereIn('vehicle_id', $plates->keys())
            ->where('kind', 'recurring')->whereNotNull('due_date')->get() as $c) {
            $key = $c->vehicle_id . '|' . mb_strtolower(trim((string) $c->name));
            if (! isset($latest[$key]) || $c->due_date->gt($latest[$key]->due_date)) $latest[$key] = $c;
        }

        $today = Carbon::today();
        $warnDays = (int) TruckingSetting::get('due_warn_days', '30') ?: 30;
        $out = [];
        foreach ($latest as $c) {
            $days = (int) round(($c->due_date->copy()->startOfDay()->getTimestamp() - $today->getTimestamp()) / 86400);
            if ($days > $warnDays) continue;   // còn hạn xa → không nhắc
            $out[] = [
                'vehicleId' => $c->vehicle_id,
                'plate'     => $plates[$c->vehicle_id] ?? '',
                'name'      => $c->name ?: '(chi phí)',
                'dueDate'   => $this->outDate($c->due_date),
                'amount'    => (int) round((float) $c->amount),
                'status'    => $days < 0 ? 'expired' : 'soon',
                'days'      => $days,
            ];
        }
        usort($out, fn ($a, $b) => $a['days'] <=> $b['days']);
        return $out;
    }

    /** Phiếu chi cần xử lý: chưa duyệt, hoặc đã duyệt nhưng chưa thanh toán (toàn đội xe MBF). */
    public function pendingVehicleCosts(): array
    {
        $plates = TruckingVehicle::where('type', 'MBF')->pluck('plate', 'id');
        if ($plates->isEmpty()) return [];
        $out = [];
        foreach (TruckingVehicleCost::whereIn('vehicle_id', $plates->keys())
            ->where(fn ($q) => $q->where('approved', false)->orWhere('paid', false))
            ->orderByDesc('spend_date')->orderByDesc('id')->get() as $c) {
            $out[] = [
                'vehicleId' => $c->vehicle_id,
                'plate'     => $plates[$c->vehicle_id] ?? '',
                'name'      => $c->name ?: '(phiếu chi)',
                'invoiceNo' => $c->invoice_no ?? '',
                'spendDate' => $this->outDate($c->spend_date),
                'amount'    => (int) round((float) $c->amount),
                'approved'  => (bool) $c->approved,
                'paid'      => (bool) $c->paid,
            ];
        }
        return $out;
    }

    /** Danh mục Khoản chi phí (dùng cho Combo tên phiếu chi xe + báo cáo theo khoản). */
    public function costItemNames(): array
    {
        return TruckingCostItem::orderBy('sort')->orderBy('name')->pluck('name')->all();
    }

    /** Tạo nhanh 1 khoản chi phí vào danh mục (KHÔNG đụng giá/màu) → trả danh sách mới. */
    public function addCostItem(string $name): array
    {
        $name = trim($name);
        if ($name !== '') {
            TruckingCostItem::firstOrCreate(['name' => $name], ['sort' => (int) (TruckingCostItem::max('sort') ?? 0) + 1]);
        }
        return $this->costItemNames();
    }

    // ===================================================================
    // QUẢN LÝ TÀI SẢN (kind='asset') — dùng chung bảng trucking_vehicles
    // (tái dùng tab Chi phí/Khấu hao/Tài liệu); KHÔNG đụng phí xe (type='asset' ≠ 'MBF').
    // ===================================================================

    /** Danh mục loại tài sản (Combo "Loại tài sản"). */
    public function assetCategories(): array
    {
        return TruckingAssetCategory::orderBy('sort')->orderBy('name')->pluck('name')->all();
    }

    /** Thêm nhanh 1 loại tài sản → trả danh sách mới. */
    public function addAssetCategory(string $name): array
    {
        $name = trim($name);
        if ($name !== '') {
            TruckingAssetCategory::firstOrCreate(['name' => $name], ['sort' => (int) (TruckingAssetCategory::max('sort') ?? 0) + 1]);
        }
        return $this->assetCategories();
    }

    /** 1 tài sản → shape danh sách (kèm đếm + hạn để cảnh báo như xe). docCount đếm từ attachments (group='doc'). */
    private function assetListRow(TruckingVehicle $v, int $docCount = 0): array
    {
        $info = is_array($v->info) ? $v->info : [];
        return [
            'id'            => $v->id,
            'hashid'        => Hashid::encode($v->id),
            'code'          => $v->plate,                       // mã tài sản (cột unique)
            'name'          => $info['name'] ?? '',
            'category'      => $info['category'] ?? '',
            'status'        => $info['status'] ?? '',
            'location'      => $info['location'] ?? '',
            'warrantyDue'   => $info['warrantyDue'] ?? '',      // YYYY-MM-DD
            'inspectionDue' => $info['inspectionDue'] ?? '',    // YYYY-MM-DD
            'docCount'      => $docCount,
            'costCount'     => (int) ($v->vehicle_costs_count ?? 0),
            'depCount'      => (int) ($v->vehicle_depreciations_count ?? 0),
        ];
    }

    /** Danh sách tài sản (kind='asset'). */
    public function assetList(): array
    {
        $rows = TruckingVehicle::where('kind', 'asset')
            ->withCount(['vehicleCosts', 'vehicleDepreciations'])
            ->orderBy('plate')->get();
        // Đếm tài liệu (attachments group='doc') 1 query gộp → tránh N+1
        $docCounts = TruckingAttachment::where('owner_type', TruckingVehicle::class)->where('group', 'doc')
            ->whereIn('owner_id', $rows->pluck('id'))->selectRaw('owner_id, COUNT(*) c')->groupBy('owner_id')->pluck('c', 'owner_id');
        return $rows->map(fn ($v) => $this->assetListRow($v, (int) ($docCounts[$v->id] ?? 0)))->all();
    }

    /** Tạo tài sản mới (tên + loại + mã tự sinh nếu trống) → trả dòng danh sách. */
    public function createAsset(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $n = TruckingVehicle::where('kind', 'asset')->count() + 1;
            do { $code = 'TS-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT); $n++; } while (TruckingVehicle::where('plate', $code)->exists());
        }
        $info = [
            'name'     => $name,
            'category' => trim((string) ($data['category'] ?? '')),
        ];
        $v = TruckingVehicle::create(['plate' => $code, 'type' => 'asset', 'kind' => 'asset', 'info' => $info]);
        return $this->assetListRow($v->loadCount(['vehicleCosts', 'vehicleDepreciations']));
    }

    /** Xóa tài sản (CHỈ kind='asset' — chặn xóa nhầm xe vì xe còn link phí xe). */
    public function destroyAsset(TruckingVehicle $v): bool
    {
        if ($v->kind !== 'asset') return false;
        $v->delete();   // cascade các bảng con (costs/depreciations/usages)
        return true;
    }

    // ===================================================================
    // LINK KẾ HOẠCH — lái xe cập nhật giờ xe đến/ra (công khai, mobile)
    // ===================================================================

    /** Builder lô trong khoảng "giờ đến dự kiến" của 1 link. */
    private function planShipmentsQuery(TruckingPlanLink $link)
    {
        return TruckingShipment::whereNotNull('gio_den_du_kien')
            ->whereBetween('gio_den_du_kien', [$link->from_date->copy()->startOfDay(), $link->to_date->copy()->endOfDay()]);
    }

    /** Danh sách link kế hoạch (admin). */
    public function planLinksForList(): array
    {
        return TruckingPlanLink::orderByDesc('id')->get()->map(function ($l) {
            return [
                'id'     => $l->id,
                'hashid' => Hashid::encode($l->id),
                'token'  => $l->token,
                'title'  => $l->title ?? '',
                'from'   => $this->outDate($l->from_date),
                'to'     => $this->outDate($l->to_date),
                'active' => (bool) $l->active,
                'count'  => $this->planShipmentsQuery($l)->count(),
                'url'    => url('/ke-hoach/' . $l->token),
            ];
        })->all();
    }

    public function createPlanLink(array $in, ?int $userId): array
    {
        $from = $this->inDate($in['from'] ?? null);
        $to   = $this->inDate($in['to'] ?? null);
        if (! $from || ! $to) return ['ok' => false, 'message' => 'Vui lòng chọn khoảng ngày.'];
        if ($to < $from) [$from, $to] = [$to, $from];
        $l = TruckingPlanLink::create([
            'token' => TruckingPlanLink::newToken(),
            'title' => trim((string) ($in['title'] ?? '')) ?: null,
            'from_date' => $from, 'to_date' => $to, 'active' => true, 'created_by' => $userId,
        ]);
        return ['ok' => true, 'link' => collect($this->planLinksForList())->firstWhere('id', $l->id)];
    }

    public function setPlanLinkActive(TruckingPlanLink $l, bool $active): void
    {
        $l->update(['active' => $active]);
    }

    public function deletePlanLink(TruckingPlanLink $l): void
    {
        $l->delete();
    }

    /** Lô cho lái xe xem/cập nhật (CHỈ field cần thiết — không lộ tài chính). */
    private function planShipmentView(TruckingShipment $s): array
    {
        return [
            'hashid'   => Hashid::encode($s->id),
            'customer' => $s->customer?->name ?? '',
            'booking'  => $s->booking ?? '',
            'contNo'   => $s->cont_no ?? '',
            'contType' => $s->cont_type ?? '',
            'kho'      => $s->kho ?? '',
            'from'     => $s->from_loc ?? '',
            'to'       => $s->to_loc ?? '',
            'bksVao'   => $s->bks_vao ?? '',
            'bksRa'    => $s->bks_ra ?? '',
            'gioDenDuKien' => $this->outDateTime($s->gio_den_du_kien),
            'gioXeDen' => $this->outDateTime($s->gio_xe_den),
            'gioXeRa'  => $this->outDateTime($s->gio_xe_ra),
            'driverNote' => $s->driver_note ?? '',
            'photos'   => $this->listAttachments(TruckingShipment::class, $s->id, 'shipmentPhoto'),
        ];
    }

    /** Dữ liệu trang công khai: thông tin link + danh sách lô trong khoảng. */
    public function planPublicData(TruckingPlanLink $link): array
    {
        $ships = $this->planShipmentsQuery($link)->with('customer:id,name')
            ->orderBy('gio_den_du_kien')->get()->map(fn ($s) => $this->planShipmentView($s))->all();
        return [
            'title' => $link->title ?? '',
            'from'  => $this->outDate($link->from_date),
            'to'    => $this->outDate($link->to_date),
            'ships' => $ships,
        ];
    }

    /** Lái xe cập nhật 1 lô qua link (chỉ giờ xe đến/ra + ghi chú + ảnh; phải trong khoảng). */
    public function planUpdateShipment(TruckingPlanLink $link, string $shipHashid, array $in, array $files = []): array
    {
        $id = Hashid::decode($shipHashid);
        if ($id === null) return ['ok' => false, 'message' => 'Lô không hợp lệ.'];
        $s = $this->planShipmentsQuery($link)->where('id', $id)->first();
        if (! $s) return ['ok' => false, 'message' => 'Lô không thuộc kế hoạch này (đã đổi giờ kế hoạch?).'];

        if (array_key_exists('gioXeDen', $in)) $s->gio_xe_den = $this->inDateTime($in['gioXeDen'] ?: null);
        if (array_key_exists('gioXeRa', $in))  $s->gio_xe_ra  = $this->inDateTime($in['gioXeRa'] ?: null);
        if (array_key_exists('driverNote', $in)) $s->driver_note = trim((string) $in['driverNote']) ?: null;
        $s->save();

        if ($files) $this->storeAttachments(TruckingShipment::class, $s->id, 'shipmentPhoto', $files, null, "trucking/shipments/{$s->id}");

        return ['ok' => true, 'ship' => $this->planShipmentView($s->fresh('customer'))];
    }

    /** Xóa 1 ảnh lô qua link (chỉ ảnh thuộc lô trong khoảng). */
    public function planDeletePhoto(TruckingPlanLink $link, string $shipHashid, int $attId): array
    {
        $id = Hashid::decode($shipHashid);
        if ($id === null || ! $this->planShipmentsQuery($link)->where('id', $id)->exists()) return ['ok' => false];
        $this->deleteAttachment($attId, TruckingShipment::class, $id);
        return ['ok' => true, 'photos' => $this->listAttachments(TruckingShipment::class, $id, 'shipmentPhoto')];
    }

    /** # hóa đơn kế tiếp (PC-XXXX) toàn hệ thống. */
    public function nextCostInvoiceNo(): string
    {
        $maxN = 0;
        foreach (TruckingVehicleCost::where('invoice_no', 'like', 'PC-%')->pluck('invoice_no') as $no) {
            if (preg_match('/^PC-(\d+)$/', trim((string) $no), $m)) $maxN = max($maxN, (int) $m[1]);
        }
        return 'PC-' . str_pad((string) ($maxN + 1), 4, '0', STR_PAD_LEFT);
    }

    /** KM của phiếu ĐÃ DUYỆT gần nhất cùng loại (theo tên) của xe — để check định mức. */
    public function lastApprovedKm(int $vehicleId, string $costItem): ?float
    {
        $name = mb_strtolower(trim($costItem));
        $row = TruckingVehicleCost::where('vehicle_id', $vehicleId)->where('approved', true)->whereNotNull('current_km')
            ->get()->filter(fn ($c) => mb_strtolower(trim((string) $c->name)) === $name)
            ->sortByDesc(fn ($c) => (float) $c->current_km)->first();
        return $row ? (float) $row->current_km : null;
    }

    /** Dữ liệu cho trang PUBLIC gửi yêu cầu chi (xe MBF + tài sản + danh mục khoản chi). */
    public function publicRequestData(): array
    {
        return [
            'vehicles'  => TruckingVehicle::where('type', 'MBF')->orderBy('plate')->get(['id', 'plate'])
                ->map(fn ($v) => ['id' => $v->id, 'plate' => $v->plate])->all(),
            'assets'    => TruckingVehicle::where('kind', 'asset')->orderBy('plate')->get(['id', 'plate', 'info'])
                ->map(fn ($v) => ['id' => $v->id, 'code' => $v->plate, 'name' => (is_array($v->info) ? ($v->info['name'] ?? '') : '') ?: $v->plate])->all(),
            'costItems' => $this->costItemNames(),
        ];
    }

    /** Tạo YÊU CẦU CHI (phiếu chi chờ duyệt) từ trang public — xe (CHECK định mức km) hoặc tài sản. */
    public function createSpendRequest(array $in, array $files = []): array
    {
        $v = TruckingVehicle::where('id', (int) ($in['vehicleId'] ?? 0))
            ->where(fn ($q) => $q->where('type', 'MBF')->orWhere('kind', 'asset'))->first();
        if (! $v) return ['ok' => false, 'message' => 'Đối tượng không hợp lệ.'];
        $item = trim((string) ($in['costItem'] ?? ''));
        if ($item === '' || ! in_array($item, $this->costItemNames(), true)) return ['ok' => false, 'message' => 'Loại chi phí không hợp lệ.'];
        $amount = $this->inMoney($in['amount'] ?? null) ?? 0;
        if ($amount <= 0) return ['ok' => false, 'message' => 'Vui lòng nhập số tiền.'];
        $km = (isset($in['km']) && $in['km'] !== '') ? (float) preg_replace('/[^\d.]/', '', (string) $in['km']) : null;

        // Định mức km của xe cho loại này
        $allow = 0;
        foreach ((is_array($v->allowances) ? $v->allowances : []) as $a) {
            if (mb_strtolower(trim((string) ($a['costItem'] ?? ''))) === mb_strtolower($item)) { $allow = (int) ($a['km'] ?? 0); break; }
        }
        if ($allow > 0) {
            if ($km === null) return ['ok' => false, 'message' => "Khoản “{$item}” có định mức {$allow} km — vui lòng nhập KM hiện tại."];
            $lastKm = $this->lastApprovedKm($v->id, $item);
            if ($lastKm !== null && ($km - $lastKm) < $allow) {
                $g = fn ($n) => number_format((float) $n, 0, '.', '.');
                return ['ok' => false, 'message' => "Chưa đủ định mức: “{$item}” cần đi thêm ≥ {$g($allow)} km kể từ lần trước (km {$g($lastKm)}). Hiện mới +{$g(max(0, $km - $lastKm))} km."];
            }
        }

        $sort = (int) ($v->vehicleCosts()->max('sort') ?? -1) + 1;
        $cost = $v->vehicleCosts()->create([
            'name' => $item, 'created_by' => auth()->id(), 'invoice_no' => $this->nextCostInvoiceNo(), 'kind' => 'fixed',
            'spend_date' => $this->inDate($in['date'] ?? null) ?? now()->toDateString(),
            'amount' => $amount, 'current_km' => $km, 'note' => trim((string) ($in['note'] ?? '')),
            'approved' => false, 'paid' => false, 'sort' => $sort,
        ]);
        if ($files) { $cost->photos = array_map(fn ($p) => $p['id'], $this->storeCostPhotos($v, $files)); $cost->save(); }

        // Thông báo cho người duyệt chi (quyền settings.update) — best-effort, không chặn việc gửi.
        try {
            $approvers = User::permission('settings.update')->get();
            if ($approvers->isNotEmpty()) {
                Notification::send($approvers, new SpendRequestCreatedNotification($cost, $v));
            }
        } catch (\Throwable $e) {
            Log::channel('single')->warning('Notify spend request failed', [
                'cost_id' => $cost->id, 'vehicle_id' => $v->id, 'error' => $e->getMessage(),
            ]);
        }

        $label = $v->kind === 'asset'
            ? 'tài sản ' . ((is_array($v->info) ? ($v->info['name'] ?? '') : '') ?: $v->plate)
            : 'xe ' . $v->plate;
        return ['ok' => true, 'message' => "Đã gửi yêu cầu chi “{$item}” cho {$label}. Kế toán sẽ duyệt sau."];
    }

    /** Trạng thái phiếu chi: cancelled | paid | approved | pending (+ nhãn VN). */
    public function vehicleCostStatus(TruckingVehicleCost $c): array
    {
        if ($c->cancelled_at) return ['code' => 'cancelled', 'label' => 'Đã hủy'];
        if ($c->paid)         return ['code' => 'paid',      'label' => 'Đã chi'];
        if ($c->approved)     return ['code' => 'approved',  'label' => 'Đã duyệt'];
        return ['code' => 'pending', 'label' => 'Chờ duyệt'];
    }

    /** Lịch sử yêu cầu chi CỦA 1 user (mobile) — phiếu do chính họ gửi. */
    public function spendRequestHistory(int $userId): array
    {
        return TruckingVehicleCost::with('vehicle:id,plate,kind,info')
            ->where('created_by', $userId)->orderByDesc('id')->limit(100)->get()
            ->map(function ($c) {
                $st = $this->vehicleCostStatus($c);
                $isAsset = ($c->vehicle?->kind ?? 'vehicle') === 'asset';
                $vinfo = is_array($c->vehicle?->info) ? $c->vehicle->info : [];
                return [
                    'id' => $c->id, 'hashid' => Hashid::encode($c->id), 'vehicleId' => $c->vehicle_id, 'plate' => $c->vehicle?->plate ?? '', 'name' => $c->name ?? '',
                    'kind' => $isAsset ? 'asset' : 'vehicle',
                    'targetName' => $isAsset ? (($vinfo['name'] ?? '') ?: ($c->vehicle?->plate ?? '')) : ($c->vehicle?->plate ?? ''),
                    'note' => $c->note ?? '',
                    'invoiceNo' => $c->invoice_no ?? '', 'amount' => $this->outMoney($c->amount),
                    'date' => $this->outDate($c->spend_date), 'km' => $this->outNum($c->current_km),
                    'status' => $st['code'], 'statusLabel' => $st['label'],
                    'canCancel' => $st['code'] === 'pending', 'canEdit' => $st['code'] === 'pending',   // chưa duyệt mới sửa/hủy được
                    'photos' => $this->costPhotosOut(is_array($c->photos) ? $c->photos : [], $c->vehicle_id),
                ];
            })->all();
    }

    /** Tài xế hủy phiếu CỦA MÌNH — chỉ khi đang "Chờ duyệt". */
    public function cancelSpendRequestByOwner(int $userId, int $costId): array
    {
        $c = TruckingVehicleCost::where('id', $costId)->where('created_by', $userId)->first();
        if (! $c) return ['ok' => false, 'message' => 'Không tìm thấy phiếu của bạn.'];
        if ($c->cancelled_at) return ['ok' => false, 'message' => 'Phiếu đã hủy trước đó.'];
        if ($c->approved || $c->paid) return ['ok' => false, 'message' => 'Phiếu đã được duyệt/chi — không thể tự hủy. Liên hệ kế toán.'];
        $c->forceFill(['cancelled_at' => now(), 'cancelled_by' => $userId])->save();
        return ['ok' => true, 'message' => 'Đã hủy phiếu.'];
    }

    /** Tài xế SỬA phiếu CỦA MÌNH — chỉ khi "Chờ duyệt" (giữ ảnh cũ theo keep + thêm ảnh mới). */
    public function updateSpendRequestByOwner(int $userId, int $costId, array $in, array $files = []): array
    {
        $c = TruckingVehicleCost::with('vehicle')->where('id', $costId)->where('created_by', $userId)->first();
        if (! $c) return ['ok' => false, 'message' => 'Không tìm thấy phiếu của bạn.'];
        if ($c->cancelled_at) return ['ok' => false, 'message' => 'Phiếu đã hủy.'];
        if ($c->approved || $c->paid) return ['ok' => false, 'message' => 'Phiếu đã được duyệt/chi — không thể sửa.'];
        $v = $c->vehicle;
        if (! $v) return ['ok' => false, 'message' => 'Xe không hợp lệ.'];

        $item = trim((string) ($in['costItem'] ?? ''));
        if ($item === '' || ! in_array($item, $this->costItemNames(), true)) return ['ok' => false, 'message' => 'Loại chi phí không hợp lệ.'];
        $amount = $this->inMoney($in['amount'] ?? null) ?? 0;
        if ($amount <= 0) return ['ok' => false, 'message' => 'Vui lòng nhập số tiền.'];
        $km = (isset($in['km']) && $in['km'] !== '') ? (float) preg_replace('/[^\d.]/', '', (string) $in['km']) : null;

        $allow = 0;
        foreach ((is_array($v->allowances) ? $v->allowances : []) as $a) {
            if (mb_strtolower(trim((string) ($a['costItem'] ?? ''))) === mb_strtolower($item)) { $allow = (int) ($a['km'] ?? 0); break; }
        }
        if ($allow > 0) {
            if ($km === null) return ['ok' => false, 'message' => "Khoản “{$item}” có định mức {$allow} km — vui lòng nhập KM hiện tại."];
            $lastKm = $this->lastApprovedKm($v->id, $item);
            if ($lastKm !== null && ($km - $lastKm) < $allow) {
                $g = fn ($n) => number_format((float) $n, 0, '.', '.');
                return ['ok' => false, 'message' => "Chưa đủ định mức: “{$item}” cần đi thêm ≥ {$g($allow)} km kể từ lần trước (km {$g($lastKm)})."];
            }
        }

        // Ảnh (theo ID attachment): giữ lại id trong "keep" + thêm ảnh mới; xóa attachment bị bỏ.
        $keep = is_array($in['keep'] ?? null) ? array_map('intval', $in['keep']) : [];
        $cur = is_array($c->photos) ? array_map('intval', $c->photos) : [];
        foreach ($cur as $id) {
            if (! in_array($id, $keep, true)) $this->deleteAttachment($id, TruckingVehicle::class, $v->id);
        }
        $keptIds = array_values(array_filter($cur, fn ($id) => in_array($id, $keep, true)));
        $newIds = $files ? array_map(fn ($p) => $p['id'], $this->storeCostPhotos($v, $files)) : [];

        $c->forceFill([
            'name' => $item, 'amount' => $amount, 'current_km' => $km, 'note' => trim((string) ($in['note'] ?? '')),
            'spend_date' => $this->inDate($in['date'] ?? null) ?? $c->spend_date,
            'photos' => array_merge($keptIds, $newIds),
        ])->save();
        return ['ok' => true, 'message' => 'Đã cập nhật phiếu.'];
    }

    /** Admin hủy phiếu — khi CHƯA thanh toán. */
    public function cancelVehicleCost(TruckingVehicleCost $c, int $byUserId): array
    {
        if ($c->cancelled_at) return ['ok' => false, 'message' => 'Phiếu đã hủy.'];
        if ($c->paid) return ['ok' => false, 'message' => 'Phiếu đã chi — không thể hủy.'];
        $c->forceFill(['cancelled_at' => now(), 'cancelled_by' => $byUserId])->save();
        return ['ok' => true];
    }

    /** Thông tin nền 1 xe (tab Thông tin) — KHÔNG kèm 3 nhóm con (lazy-load riêng từng tab). */
    public function vehicleBase(TruckingVehicle $v): array
    {
        return [
            'id'      => $v->id,
            'hashid'  => Hashid::encode($v->id),
            'plate'   => $v->plate,
            'axle'    => $v->axle ?? '',
            'info'    => is_array($v->info) ? $v->info : [],
            'docs'    => $this->vehicleDocsOut($v),
            'allowances' => array_values(array_filter(is_array($v->allowances) ? $v->allowances : [], 'is_array')),
            'drivers' => $this->driverOptions(),
        ];
    }

    private function usagesOut(TruckingVehicle $v): array
    {
        return $v->vehicleUsages()->orderBy('sort')->get()->map(fn ($u) => [
            'id' => $u->id, 'driver' => $u->driver ?? '', 'driverId' => $u->driver_id,
            'from' => $this->outDate($u->from_date), 'to' => $this->outDate($u->to_date), 'note' => $u->note ?? '',
        ])->all();
    }

    private function costsOut(TruckingVehicle $v): array
    {
        return $v->vehicleCosts()->with('creator:id,name')->orderBy('sort')->get()->map(function ($c) use ($v) {
            $st = $this->vehicleCostStatus($c);
            return [
            'id' => $c->id, 'hashid' => Hashid::encode($c->id), 'name' => $c->name ?? '', 'costTypeId' => $c->cost_type_id, 'invoiceNo' => $c->invoice_no ?? '', 'kind' => ($c->kind === 'fixed' ? 'fixed' : 'recurring'),
            'spendDate' => $this->outDate($c->spend_date), 'dueDate' => $this->outDate($c->due_date), 'amount' => $this->outMoney($c->amount),
            'currentKm' => $this->outNum($c->current_km), 'supplier' => $c->supplier ?? '', 'note' => $c->note ?? '',
            'paid' => (bool) $c->paid, 'approved' => (bool) $c->approved,
            'paidDate' => $this->outDate($c->paid_date), 'paidMethod' => $c->paid_method ?? '', 'paidRef' => $c->paid_ref ?? '', 'paidNote' => $c->paid_note ?? '',
            'requester' => $c->creator?->name ?? '', 'status' => $st['code'], 'statusLabel' => $st['label'],
            'cancelled' => (bool) $c->cancelled_at, 'canCancel' => (! $c->cancelled_at && ! $c->paid),   // admin hủy khi chưa chi
            'photos' => $this->costPhotosOut(is_array($c->photos) ? $c->photos : [], $v->id),
            ];
        })->all();
    }

    /**
     * Thông tin công ty (header bảng kê trên màn hình + bản in) — cấu hình ở Cài đặt hệ thống.
     * 1 query gộp 3 key; có default để hệ thống cũ/chưa cấu hình vẫn hiển thị đúng.
     */
    public function companyInfo(): array
    {
        $r = TruckingSetting::whereIn('key', ['sys.company_name', 'sys.company_website', 'sys.company_phone'])
            ->pluck('value', 'key');
        return [
            'name'    => ($r['sys.company_name']    ?? '') ?: 'MBF JOINT STOCK COMPANY',
            'website' => ($r['sys.company_website'] ?? '') ?: 'http://mbf.com.vn',
            'phone'   => ($r['sys.company_phone']   ?? '') ?: '84-24-39449616',
        ];
    }

    /**
     * Thông tin BÊN BÁN cho file Excel bảng kê — cấu hình ở Cài đặt hệ thống.
     * Tách riêng với companyInfo() vì tên pháp lý (tiếng Việt) khác tên hiển thị (tiếng Anh).
     * Default = thông tin MBF như template cũ → chưa cấu hình vẫn xuất đúng.
     */
    public function sellerInfo(): array
    {
        $r = TruckingSetting::whereIn('key', ['sys.seller_name', 'sys.seller_address', 'sys.seller_tax', 'sys.seller_rep', 'sys.seller_title'])
            ->pluck('value', 'key');
        return [
            'name'    => ($r['sys.seller_name']    ?? '') ?: 'CÔNG TY CỔ PHẦN MBF',
            'address' => ($r['sys.seller_address'] ?? '') ?: 'Số 58 Xóm Giếng, Thôn Cổ Điển A, Xã Thanh Trì, Thành phố Hà Nội, Việt Nam',
            'tax'     => ($r['sys.seller_tax']     ?? '') ?: '0105040296',
            'rep'     => (string) ($r['sys.seller_rep']   ?? ''),
            'title'   => (string) ($r['sys.seller_title'] ?? ''),
        ];
    }

    // ===================================================================
    // FILE TẬP TRUNG (trucking_attachments) — disk theo config, dễ migrate S3
    // ===================================================================
    private function uploadDisk(): string { return TruckingSetting::get('sys.upload_disk') ?: (string) config('trucking.upload_disk', 'local'); }

    /** Nạp cấu hình S3 từ DB (Cài đặt hệ thống) vào disk 's3' — gọi LAZY trước khi đụng file s3 (không query khi dùng local). */
    public function applyS3Config(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        $key = TruckingSetting::get('sys.s3_key');
        if (! $key) return;   // chưa cấu hình → dùng env mặc định trong config/filesystems
        $secret = TruckingSetting::get('sys.s3_secret');
        try { $secret = $secret ? \Illuminate\Support\Facades\Crypt::decryptString($secret) : ''; } catch (\Throwable $e) { $secret = ''; }
        config(['filesystems.disks.s3' => array_merge((array) config('filesystems.disks.s3', []), array_filter([
            'driver'   => 's3', 'key' => $key, 'secret' => $secret,
            'region'   => TruckingSetting::get('sys.s3_region'), 'bucket' => TruckingSetting::get('sys.s3_bucket'),
            'url'      => TruckingSetting::get('sys.s3_url') ?: null,
            'endpoint' => TruckingSetting::get('sys.s3_endpoint') ?: null,
            'use_path_style_endpoint' => (bool) TruckingSetting::get('sys.s3_endpoint'),
        ], fn ($v) => $v !== null && $v !== ''))]);
    }

    private function disk(string $name)
    {
        if ($name === 's3') $this->applyS3Config();
        return Storage::disk($name);
    }

    /** 1 attachment → shape client (URL stream disk-agnostic qua route). */
    private function attachmentOut(TruckingAttachment $a): array
    {
        return [
            'id' => $a->id, 'name' => $a->name ?? '', 'type' => $a->type ?? '',
            'mime' => $a->mime ?? '', 'size' => (int) $a->size, 'isImage' => $a->isImage(),
            'url' => route('trucking2.attachment', ['attachment' => $a->hashid()]),
        ];
    }

    /** Danh sách file của 1 owner/group. */
    public function listAttachments(string $ownerType, int $ownerId, string $group): array
    {
        return TruckingAttachment::where(['owner_type' => $ownerType, 'owner_id' => $ownerId, 'group' => $group])
            ->orderBy('sort')->orderBy('id')->get()->map(fn ($a) => $this->attachmentOut($a))->all();
    }

    /** Lưu nhiều file → tạo attachment rows (disk theo config). Trả về collection model. */
    public function storeAttachments(string $ownerType, int $ownerId, string $group, array $files, ?string $type, string $dir): array
    {
        $disk = $this->uploadDisk();
        if ($disk === 's3') $this->applyS3Config();
        $sort = (int) (TruckingAttachment::where(['owner_type' => $ownerType, 'owner_id' => $ownerId, 'group' => $group])->max('sort') ?? -1) + 1;
        $created = [];
        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) continue;
            if ($group === 'costPhoto' && ! str_starts_with((string) $file->getMimeType(), 'image/')) continue;
            $path = $file->store($dir, $disk);
            $created[] = TruckingAttachment::create([
                'owner_type' => $ownerType, 'owner_id' => $ownerId, 'group' => $group, 'disk' => $disk, 'path' => $path,
                'name' => $file->getClientOriginalName(), 'type' => $this->str($type), 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'sort' => $sort++,
            ]);
        }
        return $created;
    }

    /** Xóa 1 attachment (kiểm tra đúng owner) → xóa file trên disk + dòng. */
    public function deleteAttachment(int $id, string $ownerType, int $ownerId): bool
    {
        $a = TruckingAttachment::where(['id' => $id, 'owner_type' => $ownerType, 'owner_id' => $ownerId])->first();
        if (! $a) return false;
        try { $this->disk($a->disk)->delete($a->path); } catch (\Throwable $e) {}
        $a->delete();
        return true;
    }

    // --- Ảnh phiếu chi: owner = XE (id ổn định), cost.photos = MẢNG ID attachment ---
    /** Ảnh phiếu chi từ mảng id → shape client. */
    private function costPhotosOut($ids, int $vehicleId): array
    {
        $ids = array_values(array_filter(array_map('intval', is_array($ids) ? $ids : [])));
        if (! $ids) return [];
        $rows = TruckingAttachment::whereIn('id', $ids)->where('group', 'costPhoto')->get()->keyBy('id');
        $out = [];
        foreach ($ids as $id) if ($rows->has($id)) $out[] = $this->attachmentOut($rows[$id]);
        return $out;
    }

    /** Upload ảnh phiếu chi → trả shape client (kèm id) để client gắn vào phiếu. */
    public function storeCostPhotos(TruckingVehicle $v, array $files): array
    {
        $m = $this->storeAttachments(TruckingVehicle::class, $v->id, 'costPhoto', $files, null, "trucking/cost-photos/{$v->id}");
        return array_map(fn ($a) => $this->attachmentOut($a), $m);
    }

    /** cost.photos lưu MẢNG ID (từ client gửi: mảng object {id} hoặc mảng id). */
    private function cleanCostPhotos($photos): array
    {
        if (! is_array($photos)) return [];
        $ids = [];
        foreach ($photos as $p) { $id = is_array($p) ? (int) ($p['id'] ?? 0) : (int) $p; if ($id > 0) $ids[] = $id; }
        return array_values(array_unique($ids));
    }

    /** Dọn ảnh phiếu chi MỒ CÔI của 1 xe (không còn phiếu nào tham chiếu). */
    private function pruneOrphanCostPhotos(int $vehicleId): void
    {
        $used = [];
        foreach (TruckingVehicleCost::where('vehicle_id', $vehicleId)->pluck('photos') as $ph) {
            foreach ((is_array($ph) ? $ph : []) as $id) $used[(int) $id] = true;
        }
        foreach (TruckingAttachment::where(['owner_type' => TruckingVehicle::class, 'owner_id' => $vehicleId, 'group' => 'costPhoto'])->get() as $a) {
            if (empty($used[$a->id])) { try { $this->disk($a->disk)->delete($a->path); } catch (\Throwable $e) {} $a->delete(); }
        }
    }

    private function deprOut(TruckingVehicle $v): array
    {
        return $v->vehicleDepreciations()->orderBy('sort')->get()->map(fn ($d) => [
            'id' => $d->id, 'name' => $d->name ?? '', 'origPrice' => $this->outMoney($d->orig_price),
            'startDate' => $this->outDate($d->start_date), 'months' => $d->months ?? 0,
            'monthly' => $this->outMoney($d->monthly_amount), 'daily' => $this->outNum($d->daily_amount),
        ])->all();
    }

    /** 1 nhóm con (lazy-load theo tab): usages | costs | depreciations. */
    public function vehicleSection(TruckingVehicle $v, string $section): array
    {
        return match ($section) {
            'usages'        => ['usages' => $this->usagesOut($v), 'drivers' => $this->driverOptions()],
            'costs'         => ['costs' => $this->costsOut($v), 'costTypes' => $this->vehicleCostTypesOut()],
            'depreciations' => ['depreciations' => $this->deprOut($v)],
            default         => [],
        };
    }

    private function vehicleCostTypesOut(): array
    {
        return TruckingVehicleCostType::orderBy('sort')->orderBy('name')->pluck('name')->all();
    }

    /** Chi tiết đầy đủ (base + 3 nhóm) — khi cần tất cả. */
    public function vehicleDetail(TruckingVehicle $v): array
    {
        return $this->vehicleBase($v) + [
            'usages'        => $this->usagesOut($v),
            'costs'         => $this->costsOut($v),
            'costTypes'     => $this->vehicleCostTypesOut(),
            'depreciations' => $this->deprOut($v),
        ];
    }

    /**
     * Lưu xe — CHỈ ĐỘNG các phần CÓ trong $data (an toàn cho lazy-load: phần chưa tải
     * không gửi lên → không bị xóa). Mỗi nhóm = delete + recreate; trả về các phần đã lưu.
     */
    public function saveVehicleManagement(TruckingVehicle $v, array $data): array
    {
        return DB::transaction(function () use ($v, $data) {
            if (array_key_exists('info', $data) || array_key_exists('allowances', $data)) {
                if (array_key_exists('info', $data)) {
                    $v->info = is_array($data['info']) ? array_map(fn ($x) => is_string($x) ? trim($x) : $x, $data['info']) : null;
                }
                if (array_key_exists('allowances', $data)) {
                    // [{costItem, km}] — chỉ giữ dòng có tên khoản
                    $v->allowances = array_values(array_filter(array_map(fn ($a) => [
                        'costItem' => trim((string) ($a['costItem'] ?? '')),
                        'km'       => (int) preg_replace('/[^\d]/', '', (string) ($a['km'] ?? '')),
                    ], is_array($data['allowances']) ? $data['allowances'] : []), fn ($a) => $a['costItem'] !== ''));
                }
                $v->save();
            }
            if (array_key_exists('usages', $data)) {
                $driverId = TruckingDriver::pluck('id', 'name');
                $v->vehicleUsages()->delete();
                foreach (array_values($data['usages'] ?? []) as $i => $u) {
                    $dn = $this->str($u['driver'] ?? null);
                    $v->vehicleUsages()->create([
                        'driver' => $dn,
                        'driver_id' => $dn !== null ? ($driverId[$dn] ?? null) : null,
                        'from_date' => $this->inDate($u['from'] ?? null),
                        'to_date' => $this->inDate($u['to'] ?? null),
                        'note' => $this->str($u['note'] ?? null),
                        'sort' => $i,
                    ]);
                }
            }
            if (array_key_exists('costs', $data)) {
                $costRows = array_values($data['costs'] ?? []);
                // # hóa đơn TỰ SINH (PC-XXXX): giữ số đã có, cấp số mới cho phiếu chưa có.
                $usedN = [];
                $scan = function ($no) use (&$usedN) { if (preg_match('/^PC-(\d+)$/', trim((string) $no), $m)) $usedN[] = (int) $m[1]; };
                foreach ($costRows as $c) $scan($c['invoiceNo'] ?? '');
                foreach (TruckingVehicleCost::where('vehicle_id', '!=', $v->id)->where('invoice_no', 'like', 'PC-%')->pluck('invoice_no') as $no) $scan($no);
                $nextN = $usedN ? max($usedN) : 0;

                $typeId = TruckingVehicleCostType::pluck('id', 'name');
                // GIỮ LẠI người yêu cầu + trạng thái hủy qua delete+recreate (khớp theo id dòng cũ)
                $preserve = $v->vehicleCosts()->get(['id', 'created_by', 'cancelled_at', 'cancelled_by', 'created_at'])->keyBy('id');
                $v->vehicleCosts()->delete();
                foreach ($costRows as $i => $c) {
                    $inv = trim((string) ($c['invoiceNo'] ?? ''));
                    if ($inv === '') { $nextN++; $inv = 'PC-' . str_pad((string) $nextN, 4, '0', STR_PAD_LEFT); }
                    $cn = $this->str($c['name'] ?? null);
                    $old = (isset($c['id']) && $preserve->has($c['id'])) ? $preserve[$c['id']] : null;
                    $v->vehicleCosts()->create([
                        'name' => $cn,
                        'cost_type_id' => $cn !== null ? ($typeId[$cn] ?? null) : null,
                        'created_by'   => $old?->created_by,
                        'cancelled_at' => $old?->cancelled_at,
                        'cancelled_by' => $old?->cancelled_by,
                        'invoice_no' => $inv,
                        'kind' => (($c['kind'] ?? '') === 'recurring') ? 'recurring' : 'fixed',
                        'spend_date' => $this->inDate($c['spendDate'] ?? null),
                        'due_date' => $this->inDate($c['dueDate'] ?? null),
                        'amount' => $this->inMoney($c['amount'] ?? null) ?? 0,
                        'current_km' => $this->inNum($c['currentKm'] ?? null),
                        'supplier' => $this->str($c['supplier'] ?? null),
                        'note' => $this->str($c['note'] ?? null),
                        'paid' => ! empty($c['paid']),
                        'paid_date' => $this->inDate($c['paidDate'] ?? null),
                        'paid_method' => $this->str($c['paidMethod'] ?? null),
                        'paid_ref' => $this->str($c['paidRef'] ?? null),
                        'paid_note' => $this->str($c['paidNote'] ?? null),
                        'approved' => ! empty($c['approved']),
                        'photos' => $this->cleanCostPhotos($c['photos'] ?? []),
                        'sort' => $i,
                    ]);
                }
                $this->pruneOrphanCostPhotos($v->id);   // dọn ảnh không còn phiếu nào dùng
            }
            if (array_key_exists('depreciations', $data)) {
                $v->vehicleDepreciations()->delete();
                foreach (array_values($data['depreciations'] ?? []) as $i => $d) {
                    $orig = $this->inMoney($d['origPrice'] ?? null) ?? 0;
                    $months = (int) ($d['months'] ?? 0);
                    $v->vehicleDepreciations()->create([
                        'name' => $this->str($d['name'] ?? null),
                        'orig_price' => $orig,
                        'start_date' => $this->inDate($d['startDate'] ?? null),
                        'months' => $months,
                        'monthly_amount' => $months > 0 ? round($orig / $months, 2) : 0,
                        'daily_amount'   => $months > 0 ? round($orig / (30 * $months), 4) : 0,
                        'sort' => $i,
                    ]);
                }
            }

            // Trả về base + CHỈ các nhóm vừa lưu (để client refresh đúng phần, lấy id mới)
            $v->refresh();
            $echo = $this->vehicleBase($v);
            if (array_key_exists('usages', $data))        $echo['usages'] = $this->usagesOut($v);
            if (array_key_exists('costs', $data))         $echo['costs'] = $this->costsOut($v);
            if (array_key_exists('depreciations', $data)) $echo['depreciations'] = $this->deprOut($v);
            return $echo;
        });
    }

    // --- Tài liệu xe (ảnh/PDF/Word/Excel) — giống hồ sơ tài xế ---
    private function vehicleDocsOut(TruckingVehicle $v): array
    {
        return $this->listAttachments(TruckingVehicle::class, $v->id, 'doc');
    }

    public function uploadVehicleDocs(TruckingVehicle $v, array $files, string $type): array
    {
        $this->storeAttachments(TruckingVehicle::class, $v->id, 'doc', $files, $type ?: 'Khác', "trucking/vehicles/{$v->id}");
        return $this->vehicleDocsOut($v);
    }

    /** Xóa tài liệu xe theo ID attachment. */
    public function deleteVehicleDoc(TruckingVehicle $v, int $attachmentId): array
    {
        $this->deleteAttachment($attachmentId, TruckingVehicle::class, $v->id);
        return $this->vehicleDocsOut($v);
    }

    // ===================================================================
    // PHÍ XE NỘI BỘ (trip cost) — gom phí tuyến + dầu + phí khác theo từng lô
    // ===================================================================

    /**
     * Chuẩn hóa 1 tuyến/danh sách kho thành KHÓA tuyến (UPPER, đúng thứ tự, nối "|").
     * Khoan dung dấu nối: dấu phẩy, " - " (gạch có khoảng trắng), mũi tên → cùng 1 khóa.
     * Nhờ vậy kho lô "TL, TS, QV" và phí tuyến "TL - TS - QV" khớp nhau dù khác dấu/khoảng trắng/hoa-thường.
     * Vẫn GIỮ thứ tự (TL→TS→QV ≠ TL→QV→TS) vì chiều tuyến có ý nghĩa.
     */
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
        // Danh mục CÓ MÃ (địa điểm/kho): định danh theo MÃ (ký hiệu) — TÊN được phép trùng.
        // Khớp & cập nhật theo mã để GIỮ id (không đứt link price_rows.location_id); mã rỗng → khớp theo tên.
        if ($coded) {
            $rawNames = $cfg[$key] ?? [];
            $codeArr  = $cfg[$coded . 'Arr'] ?? null;        // mảng mã theo chỉ số dòng (mô hình mới)
            $nameMap  = $cfg[$coded] ?? [];                  // map tên→mã (tương thích bản cũ)
            $keepIds = [];
            $sort = 0;
            foreach ($rawNames as $i => $rawName) {
                $name = trim((string) $rawName);
                if ($name === '') continue;
                $code = $codeArr !== null ? trim((string) ($codeArr[$i] ?? '')) : trim((string) ($nameMap[$name] ?? ''));
                $row = $code !== ''
                    ? $cls::where('code', $code)->first()
                    : ($cls::where('name', $name)->whereRaw("COALESCE(code,'') = ''")->first() ?? null);
                if ($row) {
                    $row->update(['name' => $name, 'code' => ($code !== '' ? $code : null), 'sort' => $sort]);
                } else {
                    $row = $cls::create(['name' => $name, 'code' => ($code !== '' ? $code : null), 'sort' => $sort]);
                }
                $keepIds[] = $row->id;
                $sort++;
            }
            $cls::whereNotIn('id', $keepIds ?: [0])->delete();
            return;
        }

        // Danh mục KHÔNG mã: định danh theo TÊN (như cũ).
        $names = array_values(array_filter(array_map('trim', $cfg[$key] ?? []), fn ($v) => $v !== ''));
        $cls::whereNotIn('name', $names ?: [''])->delete();
        foreach ($names as $i => $name) {
            $attrs = ['sort' => $i];
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
            $type = $cfg['vehicleType'][$plate] ?? 'MBF';
            // Số cầu chỉ áp dụng cho xe MBF (xe ngoài không cần)
            $axle = $type === 'MBF' ? (($cfg['vehicleAxle'][$plate] ?? null) ?: null) : null;
            TruckingVehicle::updateOrCreate(['plate' => $plate], ['type' => $type, 'axle' => $axle]);
        }
    }

    private function reconcileSettings(array $cfg): void
    {
        if (isset($cfg['vatDefault']['hph'])) TruckingSetting::put('vat_default_hph', $cfg['vatDefault']['hph']);
        if (isset($cfg['vatDefault']['icd'])) TruckingSetting::put('vat_default_icd', $cfg['vatDefault']['icd']);
        if (array_key_exists('freeTimeHours', $cfg)) TruckingSetting::put('free_time_hours', $cfg['freeTimeHours']);
        if (array_key_exists('dueWarnDays', $cfg)) {
            $d = (int) preg_replace('/[^\d]/', '', (string) $cfg['dueWarnDays']);
            TruckingSetting::put('due_warn_days', (string) ($d > 0 ? $d : 30));
        }
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
                'hashid'   => Hashid::encode($st->id),
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
            'hashid'    => Hashid::encode($st->id),
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
