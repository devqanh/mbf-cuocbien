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

/** Tách từ TruckingV2Service — nhóm HandlesStatements. */
trait HandlesStatements
{
    // ===================================================================
    // STATEMENT (bảng kê) — serialize & persist
    // ===================================================================

    /**
     * PHẠM VI bảng kê (io_scope: all | nhap | xuat): lô có io ("Nhập"/"Xuất") thuộc phạm vi mới được TÍNH TIỀN,
     * in và xuất Excel; lô khác loại vẫn nằm trong bảng kê (hiện mờ) nhưng không tính. Khớp ioKey ở frontend.
     */
    public static function ioInScope(?string $io, ?string $scope): bool
    {
        $scope = $scope ?: 'all';
        if ($scope === 'all') return true;
        $v = mb_strtolower((string) $io);
        return $scope === 'xuat' ? str_contains($v, 'xu') : str_contains($v, 'nh');
    }

    /**
     * Biển kiểm soát in ở bảng kê: cont XUẤT lấy BKS xe ra (xe ra trống / trùng xe vào thì là xe vào);
     * cont nhập lấy BKS xe vào (trống thì xe ra).
     */
    public static function statementPlate(?string $io, ?string $bksVao, ?string $bksRa): string
    {
        $vao = trim((string) $bksVao); $ra = trim((string) $bksRa);
        return str_contains(mb_strtolower((string) $io), 'xu') ? ($ra ?: $vao) : ($vao ?: $ra);
    }

    /** Chuẩn hóa giá trị phạm vi từ client / DB. */
    private static function ioScopeOf($v): string
    {
        $v = (string) ($v ?? 'all');
        return in_array($v, ['nhap', 'xuat'], true) ? $v : 'all';
    }

    /**
     * CỘT CHI PHÍ RIÊNG của 1 bảng kê (không khai trong danh mục Khoản chi phí — mỗi bảng kê tự thêm):
     * chuẩn hóa [{key, label, vat}] từ client / DB. Bỏ cột không có nhãn; key rỗng/trùng → tự sinh để
     * detail.extras của từng dòng luôn trỏ đúng cột. vat = khoản này chịu VAT% của bảng kê.
     *
     * @return list<array{key:string,label:string,vat:bool}>
     */
    public static function extraColsOf($v): array
    {
        $out = []; $seen = [];
        foreach (is_array($v) ? $v : [] as $i => $c) {
            if (! is_array($c)) continue;
            $label = trim((string) ($c['label'] ?? ''));
            if ($label === '') continue;
            $key = trim((string) ($c['key'] ?? ''));
            if ($key === '' || isset($seen[$key])) $key = 'x' . ($i + 1) . ($seen ? '_' . count($seen) : '');
            $seen[$key] = true;
            $out[] = ['key' => $key, 'label' => $label, 'vat' => (bool) ($c['vat'] ?? false)];
        }
        return $out;
    }

    /**
     * NGUỒN CHÂN LÝ DUY NHẤT cho các con số bảng kê (backend).
     * VAT áp lên NỀN vận chuyển (cước+dầu+sà lan) + các CỘT CHI PHÍ RIÊNG có cờ vat; chi hộ và PHÍ TỜ KHAI
     * (phí mở + phí thanh lý, detail.declFee / tlFee) KHÔNG chịu VAT.
     *
     * Thứ tự ưu tiên NỀN dòng (khớp lineAmounts ở frontend):
     *  1. detail.manualBase — GIÁ TÙY CHỈNH người dùng đặt tay; cuoc/dau/sà lan trong detail chỉ là số hệ thống
     *     tính để đối chiếu, "Tính lại" không ghi đè.
     *  2. cuoc + dau + bargeCuoc + bargeDau (snapshot định giá).
     *  3. phaiThu (dòng cũ không có detail).
     * Phí riêng dòng = Σ detail.extras[key] theo các cột $extraCols (key không còn trong cột → bỏ qua).
     *
     * @param iterable $details   Mỗi phần tử là detail snapshot 1 dòng (mảng) chứa
     *                            cuoc/dau/chiHo/bargeCuoc/bargeDau/manualBase/extras. Khi thiếu detail
     *                            (bảng kê cũ) → fallback phaiThu coi như cước (giữ total cũ).
     * @param array   $extraCols  Cột chi phí riêng của bảng kê (extraColsOf); [] = không có.
     * @return array{base:int,choho:int,extra:int,vat:int,total:int}
     */
    public static function statementAmounts(iterable $details, float $vatRate, array $extraCols = []): array
    {
        $base = 0; $choho = 0; $vat = 0; $extra = 0; $tk = 0;
        foreach ($details as $d) {
            $d = is_array($d) ? $d : (array) $d;
            $tk += self::lineDeclFee($d);
            $lineBase = 0.0; $lineChoho = 0.0; $lineExtra = 0; $lineExtraVat = 0;
            $ex = is_array($d['extras'] ?? null) ? $d['extras'] : [];
            foreach ($extraCols as $c) {
                $amt = (int) round((float) ($ex[$c['key']] ?? 0));
                $lineExtra += $amt;
                if (! empty($c['vat'])) $lineExtraVat += $amt;
            }
            if (isset($d['manualBase']) && $d['manualBase'] !== '') {
                $lineBase  = (float) $d['manualBase'];
                $lineChoho = (float) ($d['chiHo'] ?? 0);
            } elseif (array_key_exists('cuoc', $d) || array_key_exists('dau', $d)
                || array_key_exists('bargeCuoc', $d) || array_key_exists('bargeDau', $d)) {
                $lineBase  = (float) ($d['cuoc'] ?? 0) + (float) ($d['dau'] ?? 0)
                           + (float) ($d['bargeCuoc'] ?? 0) + (float) ($d['bargeDau'] ?? 0);
                $lineChoho = (float) ($d['chiHo'] ?? 0);
            } else {
                // Không có detail → coi phaiThu là nền (bảng kê cũ, vat=0 → total không đổi).
                $lineBase  = (float) ($d['phaiThu'] ?? 0);
            }
            $lineBase  = (int) round($lineBase);
            $lineChoho = (int) round($lineChoho);
            // VAT theo DÒNG: override detail.vat nếu có, else % mặc định bảng kê. Cộng VAT từng dòng (khớp frontend).
            $rate = (isset($d['vat']) && $d['vat'] !== '' && $d['vat'] !== null) ? (float) $d['vat'] : $vatRate;
            $base  += $lineBase;
            $choho += $lineChoho;
            $extra += $lineExtra;
            $vat   += (int) round(($lineBase + $lineExtraVat) * $rate / 100);
        }
        return ['base' => $base, 'choho' => $choho, 'extra' => $extra, 'tk' => $tk, 'vat' => $vat, 'total' => $base + $vat + $choho + $extra + $tk];
    }

    /** Phí tờ khai 1 dòng (thu khách, KHÔNG VAT) = phí mở các tờ khai + phí thanh lý (detail.declFee + detail.tlFee). */
    public static function lineDeclFee(array $d): int
    {
        return (int) round((float) ($d['declFee'] ?? 0)) + (int) round((float) ($d['tlFee'] ?? 0));
    }

    public function statements(): array
    {
        return TruckingStatement::with(['lines.shipment:id,info_note', 'payments', 'customer'])->orderBy('id')->get()
            ->map(fn ($st) => $this->statementToArray($st))->all();
    }

    /**
     * Danh sách bảng kê cho trang Bảng kê (KePage) — CHỈ tóm tắt + payments, KHÔNG nạp
     * lines (snapshot từng lô) vì trang danh sách không hiển thị. Tránh hydrate hàng trăm
     * dòng statement_lines vô ích.
     *
     * SCALE: hỗ trợ limit/offset + filter (customer, kỳ period_to) để endpoint paginate
     * trả nhanh khi dữ liệu lớn. Mặc định không filter/limit → backward-compatible (boot
     * cũ vẫn trả ARRAY phẳng, KePage không phải sửa).
     *
     * @param array{customer?:?string,from?:?string,to?:?string} $filters
     */
    public function statementsForList(?int $limit = null, int $offset = 0, array $filters = []): array
    {
        $q = $this->statementsListQuery($filters);
        if ($limit !== null) $q->orderByDesc('id')->limit($limit)->offset(max(0, $offset));
        else $q->orderBy('id');   // backward-compatible: ASC khi không paginate

        $rows = $q->get()
            ->map(function ($st) {
                $a = $this->statementListAmounts($st);
                return [
                    'id'          => $st->id,
                    'hashid'      => Hashid::encode($st->id),
                    'no'          => $st->no,
                    'customer'    => $st->customer_name ?? $st->customer?->name ?? '',
                    'date'        => $this->outDate($st->date),
                    'from'        => $this->outDate($st->period_from),
                    'to'          => $this->outDate($st->period_to),
                    'ioScope'     => self::ioScopeOf($st->io_scope ?? 'all'),
                    'info'        => array_intersect_key((array) ($st->info ?? []), ['fromTime' => 1, 'toTime' => 1]),   // giờ kỳ cont ra
                    'dueDate'     => $this->statementDueDate($st),   // hạn thanh toán (lọc Quá hạn / Sắp đến hạn)
                    // Các con số bảng kê (nền/VAT/chi hộ/phí riêng/tổng).
                    'vatRate'     => (float) ($st->vat_rate ?? 0),
                    'baseAmount'  => $a['base'],
                    'vatAmount'   => $a['vat'],
                    'chohoAmount' => $a['choho'],
                    'extraAmount' => $a['extra'],
                    // tongThu = Tổng tiền (nền+VAT+chi hộ) — đồng nhất với trang xem & công nợ.
                    'tongThu'     => $a['total'],
                    'payments'    => $st->payments->map(fn ($p) => [
                        'id'     => $p->id,
                        'date'   => $this->outDate($p->date),
                        'amount' => $this->outMoney($p->amount),
                        'note'   => $p->note ?? '',
                    ])->all(),
                ];
            })->all();

        // Khi paginate (DESC + limit): đảo chiều để CALLER nhận id-ASC như bản cũ → frontend
        // .slice().reverse() vẫn hiển thị newest-first đúng như trước.
        return $limit !== null ? array_reverse($rows) : $rows;
    }

    /**
     * Các con số cho 1 dòng DANH SÁCH — đọc cột dẫn xuất (base/choho/extra/vat_rate) đã lưu khi save.
     * Bảng kê CŨ chưa có cột dẫn xuất (mặc định 0) → fallback dùng Σ phai_thu (lines_total)
     * làm nền, chi hộ 0, vat 0 → Tổng = total cũ (backward-compatible).
     */
    private function statementListAmounts(TruckingStatement $st): array
    {
        $base  = (float) ($st->base_amount ?? 0);
        $choho = (float) ($st->choho_amount ?? 0);
        $extra = (float) ($st->extra_amount ?? 0);
        $total = (float) ($st->total ?? 0);
        if ($base <= 0 && $choho <= 0 && $extra <= 0) {   // bảng kê cũ — chưa lưu cột dẫn xuất
            $base = (float) ($st->lines_total ?? $st->total ?? 0);
            $total = $total ?: $base;
        }
        $base  = (int) round($base);
        $choho = (int) round($choho);
        $extra = (int) round($extra);
        $total = (int) round($total);
        // VAT = total − nền − chi hộ − phí riêng (total đã chốt từ Σ per-line VAT khi save) → đúng cả khi VAT khác nhau theo dòng.
        $vat   = max(0, $total - $base - $choho - $extra);
        return ['base' => $base, 'choho' => $choho, 'extra' => $extra, 'vat' => $vat, 'total' => $base + $vat + $choho + $extra];
    }

    /**
     * HẠN THANH TOÁN của bảng kê (Y-m-d, '' = chưa có): info.dueDate người dùng đặt; chưa đặt thì
     * ngày lập + số ngày Hạn TT (snapshot info.termDays lúc lập, không có thì Hạn TT hiện tại của khách).
     * Khớp dueOf() ở frontend; dùng cho danh sách, trang xem và Excel.
     */
    private function statementDueDate(TruckingStatement $st): string
    {
        $info = is_array($st->info) ? $st->info : [];
        $due  = trim((string) ($info['dueDate'] ?? ''));
        if ($due !== '') return $due;
        $days = (int) ($info['termDays'] ?? 0) ?: (int) ($st->customer?->term_days ?? 0);
        $date = $this->outDate($st->date);
        return ($days > 0 && $date !== '') ? Carbon::parse($date)->addDays($days)->toDateString() : '';
    }

    /**
     * Gắn / bỏ hạn thanh toán của 1 bảng kê từ trang danh sách — CHỈ ghi info.dueDate, không đụng lô / số tiền
     * (không đi qua saveStatement). Bỏ hạn ('' / null) → về hạn mặc định theo Hạn TT của khách (nếu có).
     * @return string hạn hiệu lực sau khi ghi (statementDueDate)
     */
    public function setStatementDueDate(TruckingStatement $st, ?string $due): string
    {
        $info = is_array($st->info) ? $st->info : [];
        $info['dueDate'] = $due ?: '';
        $st->info = $info;
        $st->save();
        return $this->statementDueDate($st->loadMissing('customer'));
    }

    /** Meta cho paginate: tổng số bảng kê khớp filter (để KePage biết còn trang hay không). */
    public function statementsForListMeta(array $filters = []): array
    {
        return ['total' => (int) $this->statementsListQuery($filters)->toBase()->count('trucking_statements.id')];
    }

    /** Query builder dùng chung cho list + meta — đẩy filter vào SQL (dùng FK index). */
    private function statementsListQuery(array $filters)
    {
        $q = TruckingStatement::with(['payments', 'customer'])
            ->withSum('lines as lines_total', 'phai_thu');
        $cust = trim((string) ($filters['customer'] ?? ''));
        if ($cust !== '') {
            $custId = $this->customerIdByName($cust);
            if ($custId) $q->where('customer_id', $custId);
            else $q->where('customer_name', $cust);
        }
        // Kỳ = period_to (mốc kết thúc kỳ). Nếu rỗng fallback date (ngày lập) để giữ trùm.
        if (! empty($filters['from'])) $q->where(function ($w) use ($filters) {
            $w->where('period_to', '>=', $filters['from'])
              ->orWhere(function ($a) use ($filters) { $a->whereNull('period_to')->where('date', '>=', $filters['from']); });
        });
        if (! empty($filters['to'])) $q->where(function ($w) use ($filters) {
            $w->where('period_to', '<=', $filters['to'])
              ->orWhere(function ($a) use ($filters) { $a->whereNull('period_to')->where('date', '<=', $filters['to']); });
        });
        return $q;
    }

    /**
     * id lô hợp lệ trong payload dòng → map "id" => id lô CÒN TỒN TẠI.
     * Bỏ khóa không phải số ("L…" = dòng mất liên kết) và lô đã xóa → shipment_id null thay vì lỗi khóa ngoại.
     */
    public function existingShipmentIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            fn ($v) => is_int($v) || (is_string($v) && ctype_digit($v)) ? (int) $v : 0, $ids))));
        if (! $ids) return [];
        return \App\Models\TruckingShipment::whereIn('id', $ids)->pluck('id')
            ->mapWithKeys(fn ($id) => [(string) $id => (int) $id])->all();
    }

    public function statementToArray(TruckingStatement $st): array
    {
        // "Ghi chú tự do cho lô hàng" (info_note — ô Ghi chú ở popup Thông tin lô hàng) lấy TRỰC TIẾP từ lô:
        // sửa ở popup là bảng kê hiện theo. KHÁC ghi_chu ("Ghi chú kế toán" ở popup Doanh thu).
        // (Cột note của dòng là snapshot "ghi chú kế toán, trống thì tuyến + kết nối" nên không dùng được.)
        // Đã eager-load lines.shipment thì dùng luôn, không thì 1 query cho cả bảng kê.
        // Cùng query: GIỜ KÉO VÀO (giờ xe đến) + GIỜ CONT RA thực tế của lô để bảng kê hiện ngày + giờ (lấy hiện tại).
        $ships = $st->lines->every(fn ($l) => $l->relationLoaded('shipment'))
            ? $st->lines->mapWithKeys(fn ($l) => [(int) $l->shipment_id => $l->shipment])->filter()->all()
            : \App\Models\TruckingShipment::whereIn('id', $st->lines->pluck('shipment_id')->filter()->unique()->values())
                ->get(['id', 'info_note', 'gio_xe_den', 'gio_xe_ra'])->keyBy('id')->all();
        $infoNote = array_map(fn ($s) => $s->info_note, $ships);
        // Dòng MẤT liên kết lô (lô bị xóa / nhập lại → shipment_id null): dò theo SỐ CONT + NGÀY cont ra để vẫn hiện giờ.
        $byContDate = [];
        $orphan = $st->lines->filter(fn ($l) => ! $l->shipment_id && trim((string) $l->cont_no) !== '');
        if ($orphan->isNotEmpty()) {
            foreach (\App\Models\TruckingShipment::whereIn('cont_no', $orphan->pluck('cont_no')->unique()->values())->get(['cont_no', 'gio_xe_den', 'gio_xe_ra']) as $s) {
                if ($s->gio_xe_ra) $byContDate[$s->cont_no . '|' . $s->gio_xe_ra->format('Y-m-d')] = $s;
            }
        }
        $shipOf = fn ($l) => $l->shipment_id ? ($ships[(int) $l->shipment_id] ?? null) : ($byContDate[$l->cont_no . '|' . ($l->date?->format('Y-m-d') ?? '')] ?? null);
        // Các con số tính TỪ detail từng dòng (chân lý, đúng cả bảng kê cũ) + vat_rate đã lưu — CHỈ lô trong phạm vi io_scope.
        $rate  = (float) ($st->vat_rate ?? 0);
        $scope = self::ioScopeOf($st->io_scope ?? 'all');
        $cols  = self::extraColsOf($st->extra_cols);
        $amt  = self::statementAmounts($st->lines->filter(fn ($l) => self::ioInScope($l->io, $scope))->map(function ($l) {
            $d = is_array($l->detail ?? null) ? $l->detail : [];
            if (! $d) $d = ['phaiThu' => (float) $l->phai_thu];
            return $d;
        }), $rate, $cols);
        return [
            'id'          => $st->id,
            'hashid'      => Hashid::encode($st->id),
            'no'          => $st->no,
            'customer'    => $st->customer_name ?? $st->customer?->name ?? '',
            'info'        => $st->info ?? [],
            // Hạn TT hiện tại của khách — FE gợi ý hạn khi bảng kê chưa đặt và lúc lập khách chưa có Hạn TT.
            'customerTermDays' => (int) ($st->customer?->term_days ?? 0),
            'dueDate'     => $this->statementDueDate($st),   // hạn đã tính (Excel); FE sửa qua info.dueDate
            'date'        => $this->outDate($st->date),
            'from'        => $this->outDate($st->period_from),
            'to'          => $this->outDate($st->period_to),
            'ioScope'     => $scope,   // phạm vi lô tính tiền: all | nhap | xuat
            'extraCols'   => $cols,    // cột chi phí riêng [{key,label,vat}] — số từng lô ở lines[].detail.extras
            'vatRate'     => $rate,
            'baseAmount'  => $amt['base'],
            'vatAmount'   => $amt['vat'],
            'chohoAmount' => $amt['choho'],
            'extraAmount' => $amt['extra'],
            'tkAmount'    => $amt['tk'],      // phí tờ khai (mở + thanh lý), không VAT
            'tongThu'     => $amt['total'],   // = Tổng tiền (nền + VAT + chi hộ + phí riêng + phí tờ khai)
            'lines'       => $st->lines->map(fn ($l) => [
                // Dòng mất liên kết lô: khóa "L{id dòng}" (KHÔNG phải số) để không bị lưu nhầm thành shipment_id / trùng id lô thật.
                'id'        => $l->shipment_id ?? ('L' . $l->id),
                'infoNote'  => trim((string) ($infoNote[(int) $l->shipment_id] ?? '')),
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
                'gioVao'    => $this->outDateTime($shipOf($l)?->gio_xe_den),   // kéo vào (giờ xe đến)
                'gioRa'     => $this->outDateTime($shipOf($l)?->gio_xe_ra),    // cont ra
                'contLabel' => $l->cont_label ?? '',
                'phaiThu'   => (int) round((float) $l->phai_thu),
                'cuoc'      => (int) round((float) $l->cuoc),
                'thanhLy'   => is_array($l->detail) && (isset($l->detail['declFee']) || isset($l->detail['tlFee']))
                    ? self::lineDeclFee($l->detail) : (int) round((float) $l->thanh_ly),   // phí tờ khai (mở + thanh lý)
                'note'      => $l->note ?? '',
                'userNote'  => $l->user_note ?? '',   // cột Ghi chú của bảng kê (người dùng gõ)
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
                $customerId = $this->customerIdByName($data['customer']);
            }

            // 4 con số = NGUỒN CHÂN LÝ (server tự cộng từ detail từng dòng) — không tin client.
            // VAT chỉ áp nền (cước+dầu+sà lan); chi hộ không VAT.
            $vatRate = max(0.0, (float) ($data['vatRate'] ?? 0));
            // Phạm vi Nhập/Xuất: chỉ lô trong phạm vi được cộng vào các con số (lô khác loại vẫn lưu dòng, không tính).
            $scope = self::ioScopeOf($data['ioScope'] ?? 'all');
            // Cột chi phí riêng của bảng kê này; số từng lô nằm trong detail.extras (chỉ giữ key của cột còn tồn tại).
            $cols = self::extraColsOf($data['extraCols'] ?? []);
            $colKeys = array_flip(array_column($cols, 'key'));
            $cleanDetail = function ($detail) use ($colKeys) {
                if (! is_array($detail)) return $detail;
                $ex = [];
                foreach (is_array($detail['extras'] ?? null) ? $detail['extras'] : [] as $k => $v) {
                    if (isset($colKeys[$k]) && (int) round((float) $v) !== 0) $ex[$k] = (int) round((float) $v);
                }
                if ($ex) $detail['extras'] = $ex; else unset($detail['extras']);
                return $detail;
            };
            $details = array_map(function ($l) use ($cleanDetail) {
                $d = is_array($l['detail'] ?? null) ? $cleanDetail($l['detail']) : [];
                // Nếu thiếu detail nhưng có phaiThu (override thủ công) → dùng phaiThu làm nền.
                if (! $d) $d = ['phaiThu' => $this->inMoney($l['phaiThu'] ?? null) ?? 0];
                return $d;
            }, array_values(array_filter($data['lines'] ?? [], fn ($l) => self::ioInScope($l['io'] ?? '', $scope))));
            $amt = self::statementAmounts($details, $vatRate, $cols);

            $st ??= new TruckingStatement();
            $st->fill([
                'no'            => $data['no'] ?? $st->no,
                'customer_id'   => $customerId,
                'customer_name' => $this->str($data['customer'] ?? null),
                'info'          => $data['info'] ?? null,
                'date'          => $this->inDate($data['date'] ?? null),
                'period_from'   => $this->inDate($data['from'] ?? null),
                'period_to'     => $this->inDate($data['to'] ?? null),
                'vat_rate'      => $vatRate,
                'io_scope'      => $scope,
                'extra_cols'    => $cols ?: null,
                'base_amount'   => $amt['base'],
                'choho_amount'  => $amt['choho'],
                // Phí tờ khai (không VAT) gộp vào cột dẫn xuất extra_amount: danh sách suy VAT = total − nền − chi hộ − extra.
                'extra_amount'  => $amt['extra'] + $amt['tk'],
                // total = nền + VAT(nền + phí riêng có vat) + chi hộ + phí riêng + phí tờ khai — backward-compat: vat=0, không cột riêng → = nền + chi hộ.
                'total'         => $amt['total'],
            ]);
            $st->save();

            // BULK INSERT: 1 query thay vì N (bảng kê lớn có thể có hàng trăm dòng).
            // Bypass Eloquent → tự set timestamps + json_encode `detail` (cast 'array').
            $now = Carbon::now();
            $st->lines()->delete();
            $linesBatch = [];
            $shipIds = $this->existingShipmentIds(array_column($data['lines'] ?? [], 'id'));
            foreach (($data['lines'] ?? []) as $i => $l) {
                $detail = is_array($l['detail'] ?? null) ? $cleanDetail($l['detail']) : null;
                $linesBatch[] = [
                    'statement_id' => $st->id,
                    'shipment_id'  => $shipIds[(string) ($l['id'] ?? '')] ?? null,
                    'booking'      => $this->str($l['booking'] ?? null),
                    'sheet'        => $this->str($l['sheet'] ?? null),
                    'io'           => $this->str($l['io'] ?? null),
                    'decl_no'      => $this->str($l['declNo'] ?? null),
                    'cont_type'    => $this->str($l['contType'] ?? null),
                    'inv'          => $this->str($l['inv'] ?? null),
                    'cont_no'      => $this->str($l['contNo'] ?? null),
                    'bks'          => $this->str($l['bks'] ?? null),
                    'from_loc'     => $this->str($l['from'] ?? null),
                    'to_loc'       => $this->str($l['to'] ?? null),
                    'date'         => $this->inDate($l['date'] ?? null),
                    'cont_label'   => $this->str($l['contLabel'] ?? null),
                    'phai_thu'     => $this->inMoney($l['phaiThu'] ?? null) ?? 0,
                    'cuoc'         => $this->inMoney($l['cuoc'] ?? null) ?? 0,
                    // Phí tờ khai (mở + thanh lý) — snapshot mới lấy từ detail; dòng cũ giữ số đã lưu.
                    'thanh_ly'     => is_array($detail) && (isset($detail['declFee']) || isset($detail['tlFee']))
                        ? self::lineDeclFee($detail) : ($this->inMoney($l['thanhLy'] ?? null) ?? 0),
                    'note'         => $this->str($l['note'] ?? null),
                    'user_note'    => $this->str($l['userNote'] ?? null),   // ghi chú người dùng gõ ở cột Ghi chú
                    'detail'       => $detail === null ? null : json_encode($detail, JSON_UNESCAPED_UNICODE),
                    'sort'         => $i,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
            if ($linesBatch) \App\Models\TruckingStatementLine::insert($linesBatch);

            $st->payments()->delete();
            $paymentsBatch = [];
            foreach (($data['payments'] ?? []) as $i => $p) {
                $paymentsBatch[] = [
                    'statement_id' => $st->id,
                    'date'         => $this->inDate($p['date'] ?? null),
                    'amount'       => $this->inMoney($p['amount'] ?? null),
                    'note'         => $this->str($p['note'] ?? null),
                    'sort'         => $i,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
            if ($paymentsBatch) \App\Models\TruckingStatementPayment::insert($paymentsBatch);

            return $st->fresh(['lines', 'payments']);
        });
    }
}
