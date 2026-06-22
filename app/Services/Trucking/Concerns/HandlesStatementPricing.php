<?php

namespace App\Services\Trucking\Concerns;

use App\Models\TruckingCustomer;
use App\Models\TruckingLocation;
use App\Models\TruckingSetting;
use App\Models\TruckingShipment;
use App\Models\TruckingStatement;
use App\Models\TruckingWarehouse;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * SO KHỚP GIÁ Ở BACKEND (nguồn chân lý duy nhất) — port từ makePricer/priceFor (ui.jsx)
 * + calcFreeTime. So khớp KIND chuẩn hóa lowercase+trim (không phân biệt hoa/thường).
 * Dùng cho: tạo bảng kê (statementCandidates) và tính lại (statementReprice).
 * Tối ưu scale: chỉ truy vấn lô CỦA 1 KHÁCH trong khoảng ngày (không kéo toàn bộ lô về client).
 *
 * REQUEST-SCOPED CACHE: code map / threshold / priceList tải 1 lần / request. Pricing
 * context precompute ký hiệu CHUẨN cho từng dòng giá (tránh chuẩn hóa lặp khi loop nhiều lô).
 */
trait HandlesStatementPricing
{
    /** @var array<string,string>|null */
    private ?array $locCodeCache = null;
    /** @var array<string,string>|null */
    private ?array $codeMapCache = null;
    private ?string $thresholdCache = null;
    /** @var array<int,array> per customer_id → precomputed pricing context */
    private array $pricingCtxCache = [];
    /** Free time của 1 lô — null nếu thiếu dữ liệu. (port calcFreeTime) */
    private function freeTimeOf(TruckingShipment $s, $thresholdH): ?array
    {
        // Free time = Giờ xe ra − Giờ xe đến (follow theo XE ra). Giờ xe ra theo ra_mode:
        //  self→cont này ra (gio_xe_ra); none→xe đầu kéo (gio_xe_ra_xe); other→cont KHÁC thực sự ra (raOther.gio_xe_ra).
        $ra = match ($s->ra_mode ?? 'self') {
            'none'  => $s->gio_xe_ra_xe,
            'other' => $s->raOther?->gio_xe_ra,
            default => $s->gio_xe_ra,
        };
        $den = $s->gio_xe_den;
        if (! $ra || ! $den) return null;
        try { $dRa = Carbon::parse($ra); $start = Carbon::parse($den); }
        catch (\Throwable) { return null; }
        $hours = ($dRa->getTimestamp() - $start->getTimestamp()) / 3600;
        $th = $this->freeTimeThresholdForDate($dRa, $thresholdH);   // ngưỡng theo NGÀY xe ra (quy tắc khoảng ngày), fallback mặc định
        return ['hours' => $hours, 'connect' => $hours > $th, 'threshold' => $th, 'basis' => 'Giờ xe đến'];
    }

    /** Ngưỡng free time (giờ) theo NGÀY cont ra: khớp quy tắc khoảng ngày (free_time_rules) → fallback mặc định. */
    private ?array $freeTimeRulesCache = null;
    private function freeTimeThresholdForDate(Carbon $dRa, $default): float
    {
        $def = ($default === null || $default === '') ? 4.0 : (float) $default;
        if ($this->freeTimeRulesCache === null) {
            $raw = TruckingSetting::get('free_time_rules', '');
            $arr = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
            $this->freeTimeRulesCache = is_array($arr) ? $arr : [];
        }
        $ymd = $dRa->format('Y-m-d');
        foreach ($this->freeTimeRulesCache as $r) {
            $from = trim((string) ($r['from'] ?? ''));
            $to   = trim((string) ($r['to'] ?? ''));
            if ($from !== '' && $ymd >= $from && ($to === '' || $ymd <= $to)) {
                return (isset($r['hours']) && $r['hours'] !== '' && $r['hours'] !== null) ? (float) $r['hours'] : $def;
            }
        }
        return $def;
    }

    /**
     * Định giá 1 lô theo CONTEXT bảng giá đã chuẩn hóa sẵn (xem pricingContext()).
     * Tách khỏi normalize/lookup → loop nhiều lô của 1 khách không phải chuẩn hóa lặp dòng giá.
     */
    private function priceShipment(TruckingShipment $s, array $ctx): array
    {
        $codeMap   = $ctx['codeMap'];
        $codeToName = $ctx['codeToName'];
        $priceList = $ctx['priceList'];
        $threshold = $ctx['threshold'];

        $nk   = fn ($v) => mb_strtolower(trim((string) $v));
        $norm = fn ($v) => mb_strtoupper(preg_replace('/\s+/u', '', trim(Str::ascii((string) $v))) ?? '');   // bỏ DẤU CÁCH giữa: "ICD QV" == "ICDQV"
        $rc   = fn ($v) => $codeMap[$norm($v)] ?? $norm($v);

        $cont20   = str_contains((string) $s->cont_type, '20');
        $isExport = str_contains(mb_strtolower((string) $s->io), 'xu');
        $kind = $s->cru
            ? ($isExport ? 'External CRU transportation' : 'Internal CRU transportation')
            : 'Transportation 1 way of Import/Export';
        $nkKind = $nk($kind);

        $ft = $this->freeTimeOf($s, $threshold);
        $conn = $ft ? ($ft['connect'] ? 'Connect' : 'Disconnect') : null;

        // ========= KHỚP 3 VAI TRÒ + LOẠI + KẾT NỐI (user chốt) =========
        // Bảng giá: from = điểm ĐI · loc = điểm HẠ · to1..to4 = NHÀ MÁY. Lô: from_loc → KHO → to_loc.
        // So theo KÝ HIỆU CHUẨN ($rc) → "LACH HUYEN"=="LẠCH HUYỆN"=="LHP".
        $loFrom = $rc($s->from_loc);
        $loDrop = $rc($s->to_loc);
        $noDrop = ($loDrop === '');   // lô không có nơi hạ → khớp theo đi+nhà máy (giữ ràng buộc hạ khi có)
        $khoCodes = [];
        foreach (preg_split('/\s*(?:,|→|->|–|—|\s-\s)\s*/u', (string) $s->kho) ?: [] as $k) { $c = $rc($k); if ($c !== '') $khoCodes[] = $c; }

        // Linear scan trên priceList ĐÃ PRECOMPUTE rcFrom/rcDrop/rcKho/nkKind/conn (pricingContext)
        // → không có chuẩn hóa per-row trong vòng lặp; chỉ so chuỗi đã chuẩn.
        $p = null; $fallback = null;
        foreach ($priceList as $r) {
            if ($loFrom !== '' && $r['rcFrom'] !== '' && $r['rcFrom'] !== $loFrom) continue;
            if ($loDrop !== '' && $r['rcDrop'] !== '' && $r['rcDrop'] !== $loDrop) continue;
            if (empty($khoCodes) || empty($r['rcKho'])) continue;   // kho rỗng = không khớp (giữ semantic cũ)
            $khoOk = false;
            foreach ($r['rcKho'] as $k) if (in_array($k, $khoCodes, true)) { $khoOk = true; break; }
            if (! $khoOk) continue;
            if ($r['nkKind'] !== '' && $r['nkKind'] !== $nkKind) continue;

            if ($fallback === null) $fallback = $r['row'];   // base match, để fallback nếu không có conn khớp
            if (! $conn || $r['conn'] === $conn) { $p = $r['row']; break; }
        }
        if (! $p) $p = $fallback;

        $cuoc = $p ? (int) ($cont20 ? $p['transFee20'] : $p['transFee40']) : 0;
        $dau  = $p ? (int) ($cont20 ? $p['fuelFee20'] : $p['fuelFee40']) : 0;

        $choHoItems = []; $costItems = [];
        foreach ($s->costLines as $c) {
            $amt = (int) round((float) $c->amount); $bill = (bool) $c->billable;
            if ($bill) $choHoItems[] = ['item' => $c->item ?: '(khoản)', 'amount' => $amt];
            $costItems[] = ['item' => $c->item ?: '(khoản)', 'amount' => $amt, 'billable' => $bill, 'src' => $c->src ?? ''];
        }
        $chiHo = array_sum(array_column($choHoItems, 'amount'));
        // Tuyến hiển thị: ĐI → NHÀ MÁY → HẠ (theo tên cho rõ; ký hiệu là khóa link)
        $nameOf = function ($v) use ($codeToName) { $v = trim((string) $v); return $codeToName[$v] ?? $v; };
        $route = $p ? (($nameOf($p['from'] ?? '') ?: '?') . ' → ' . ($nameOf($p['to1'] ?? '') ?: '?') . ' → ' . ($nameOf($p['loc'] ?? '') ?: '?')) : null;

        $diag = [
            'hasPrice' => count($priceList) > 0,
            'di'       => $loFrom !== '' ? $loFrom : '(trống)',
            'nhaMay'   => $khoCodes ? implode(' / ', $khoCodes) : '(lô chưa có kho/nhà máy)',
            'ha'       => $loDrop !== '' ? $loDrop : '(trống)',
            'kind'     => $kind, 'conn' => $conn,
        ];
        $loTrinh = implode(' → ', array_filter([$loFrom, implode('+', $khoCodes), $loDrop], fn ($x) => $x !== ''));

        return [
            'matched' => (bool) $p, 'conn' => $conn, 'kind' => $kind, 'is20' => $cont20,
            'cuoc' => $cuoc, 'dau' => $dau, 'chiHo' => $chiHo, 'choHoItems' => $choHoItems, 'costItems' => $costItems,
            'route' => $route, 'loTrinh' => $loTrinh, 'kho' => trim((string) $s->kho), 'noDrop' => $noDrop, 'diag' => $diag,
            'ftHours' => $ft['hours'] ?? null, 'ftThreshold' => $ft['threshold'] ?? null, 'ftBasis' => $ft['basis'] ?? null,
            'phaiThu' => $cuoc + $dau + $chiHo,
        ];
    }

    /** [tên địa điểm => ký hiệu] cho codeOf. Memoize per-request. */
    private function locationCodeMap(): array
    {
        if ($this->locCodeCache !== null) return $this->locCodeCache;
        $m = [];
        foreach (TruckingLocation::toBase()->get(['name', 'code']) as $l) {
            if ($l->name && $l->code) $m[$l->name] = $l->code;
        }
        return $this->locCodeCache = $m;
    }

    /**
     * [chuẩn-hóa(bỏ dấu + viết hoa) => KÝ HIỆU] gộp ĐỊA ĐIỂM + KHO, khớp cả code lẫn tên.
     * Để khớp bảng giá theo ký hiệu duy nhất dù dữ liệu ghi tên có/không dấu
     * (vd "LACH HUYEN" / "LẠCH HUYỆN" / "LHP" → cùng "LHP"). Memoize per-request.
     */
    private function normalizedCodeMap(): array
    {
        if ($this->codeMapCache !== null) return $this->codeMapCache;
        $norm = fn ($v) => mb_strtoupper(preg_replace('/\s+/u', '', trim(Str::ascii((string) $v))) ?? '');   // bỏ DẤU CÁCH giữa: "ICD QV" == "ICDQV"
        $m = [];
        foreach (TruckingLocation::toBase()->get(['name', 'code']) as $l) {
            if ($l->code) { $m[$norm($l->code)] = $l->code; if ($l->name) $m[$norm($l->name)] = $l->code; }
        }
        foreach (TruckingWarehouse::toBase()->get(['name', 'code']) as $w) {
            if ($w->code) { $m[$norm($w->code)] = $w->code; if ($w->name) $m[$norm($w->name)] = $w->code; }
        }
        return $this->codeMapCache = $m;
    }

    /** Ngưỡng free time — đọc setting 1 lần / request. */
    private function freeTimeThreshold(): string
    {
        return $this->thresholdCache ??= (string) TruckingSetting::get('free_time_hours', '4');
    }

    /**
     * Gom toàn bộ đầu vào định giá cho 1 khách thành 1 context dùng lại được:
     *  - priceList ĐÃ PRECOMPUTE ký hiệu chuẩn (rcFrom/rcDrop/rcKho/rcKind/conn) cho mỗi
     *    dòng giá → khi loop qua nhiều lô không phải chuẩn hóa lặp.
     *  - locCode / codeMap / threshold / codeToName chia sẻ.
     * Cache theo customer_id để cùng 1 khách qua nhiều lượt gọi chỉ build 1 lần.
     */
    private function pricingContext(?int $customerId, ?string $customerName = null): array
    {
        $key = $customerId ?: 0;
        if (isset($this->pricingCtxCache[$key])) return $this->pricingCtxCache[$key];

        $priceList = $customerId
            ? $this->customerPriceListById($customerId)
            : ($customerName ? $this->customerPriceList($customerName) : []);

        $locCode = $this->locationCodeMap();
        $codeMap = $this->normalizedCodeMap();
        $norm = fn ($v) => mb_strtoupper(preg_replace('/\s+/u', '', trim(Str::ascii((string) $v))) ?? '');   // bỏ DẤU CÁCH giữa: "ICD QV" == "ICDQV"
        $rc   = fn ($v) => $codeMap[$norm($v)] ?? $norm($v);
        $nk   = fn ($v) => mb_strtolower(trim((string) $v));

        $codeToName = [];
        foreach ($locCode as $nm => $c) { $c = trim((string) $c); if ($c !== '') $codeToName[$c] = $nm; }

        $prepared = [];
        foreach ($priceList as $row) {
            $prepared[] = [
                'row'    => $row,
                'rcFrom' => $rc($row['from'] ?? ''),
                'rcDrop' => $rc($row['loc'] ?? ''),
                'rcKho'  => array_values(array_filter([
                    $rc($row['to1'] ?? ''), $rc($row['to2'] ?? ''),
                    $rc($row['to3'] ?? ''), $rc($row['to4'] ?? ''),
                ], fn ($x) => $x !== '')),
                'nkKind' => $nk($row['kind'] ?? ''),
                'conn'   => $row['conn'] ?? 'Connect',
            ];
        }

        return $this->pricingCtxCache[$key] = [
            'priceList'  => $prepared,
            'rawList'    => $priceList,
            'locCode'    => $locCode,
            'codeMap'    => $codeMap,
            'codeToName' => $codeToName,
            'threshold'  => $this->freeTimeThreshold(),
        ];
    }

    /**
     * Ứng viên cho 1 bảng kê: lô CỦA 1 KHÁCH trong khoảng cont-ra, ĐÃ ĐỊNH GIÁ ở server.
     * Trả về dòng sẵn sàng hiển thị + lưu (kèm pr để hiện trạng thái khớp).
     */
    public function statementCandidates(string $customer, ?string $from, ?string $to): array
    {
        $cust = trim($customer);
        if ($cust === '') return ['candidates' => []];
        $custId = $this->customerIdByName($cust);
        if (! $custId) return ['candidates' => []];

        $ctx = $this->pricingContext((int) $custId, $cust);

        // ============================================================
        // NGÀY KỲ BẢNG KÊ = NGÀY của "Giờ xe ra" (cột `gio_xe_ra`) — INPUT mà bộ lọc
        // "Cont ra từ ngày – đến ngày" ở trang Tạo bảng kê dựa vào.
        // (Đã bỏ field "Ngày cont ra"/`cont_ra` ở popup Lô hàng — gio_xe_ra là mốc cont rời đi.)
        // ICD: CHỈ tính theo gio_xe_ra → lô chưa có giờ ra KHÔNG vào bảng kê.
        // HPH: fallback sail_date (HPH không có khái niệm giờ xe ra).
        // ============================================================
        // SCALE: đẩy lọc kỳ vào SQL (dùng index gio_xe_ra / sail_date) — KHÔNG nạp toàn bộ lô của khách.
        // ICD lọc gio_xe_ra trong [from 00:00, to 23:59]; HPH fallback sail_date. Lọc PHP bên dưới giữ làm chốt chặn.
        $q = TruckingShipment::where('customer_id', $custId)->with(['costLines', 'raOther:id,gio_xe_ra']);
        if ($from || $to) {
            $lo = $from ? $from . ' 00:00:00' : null; $hi = $to ? $to . ' 23:59:59' : null;
            $q->where(function ($w) use ($lo, $hi, $from, $to) {
                $w->where(function ($a) use ($lo, $hi) {
                    $a->whereNotNull('gio_xe_ra');
                    if ($lo) $a->where('gio_xe_ra', '>=', $lo);
                    if ($hi) $a->where('gio_xe_ra', '<=', $hi);
                })->orWhere(function ($a) use ($from, $to) {
                    $a->where('sheet', 'HPH')->whereNotNull('sail_date');
                    if ($from) $a->whereDate('sail_date', '>=', $from);
                    if ($to) $a->whereDate('sail_date', '<=', $to);
                });
            });
        }
        $rows = $q->orderBy('gio_xe_ra')->get();
        $out = [];
        foreach ($rows as $s) {
            $sheet = strtoupper((string) $s->sheet);
            // Cột "Cont ra" = ngày của Giờ xe ra (ICD). HPH fallback sail_date (HPH không có giờ xe ra).
            $date  = $this->outDate($s->gio_xe_ra) ?: ($sheet === 'HPH' ? $this->outDate($s->sail_date) : '');
            // Lọc theo kỳ = theo NGÀY GIỜ XE RA. Lô CHƯA CÓ giờ ra (date rỗng) → CHƯA rời đi → KHÔNG đưa vào bảng kê.
            if (($from || $to) && ! $date) continue;
            if ($from && $date && $date < $from) continue;
            if ($to && $date && $date > $to) continue;
            $out[] = $this->candidateRow($s, $sheet, $date, $this->priceShipment($s, $ctx));
        }
        return ['candidates' => $out];
    }

    /** Shape 1 ứng viên (dùng chung cho candidates). */
    private function candidateRow(TruckingShipment $s, string $sheet, string $date, array $pr): array
    {
        $thanhLy = 0;
        foreach ($s->costLines as $c) if (($c->src ?? '') === 'thanhLyFee') $thanhLy += (int) round((float) $c->amount);
        $contLabel = $sheet === 'HPH'
            ? ($s->qty . ' × ' . $s->cont_type)
            : (($s->cont_no ?: $s->cont_type) . ($s->cont_no ? ' · ' . $s->cont_type : ''));
        $note = trim((string) $s->ghi_chu) ?: (implode(' → ', array_filter([$s->from_loc, $s->to_loc])) . ($pr['conn'] ? ' · ' . $pr['conn'] : ''));

        return [
            'id' => $s->id, 'booking' => $s->booking ?? '', 'io' => $s->io ?? '', 'sheet' => $sheet,
            'declNo' => $s->declaration_no ?? '', 'contType' => $s->cont_type ?? '', 'inv' => $s->inv ?? '',
            'contNo' => $s->cont_no ?? '', 'bks' => $s->bks_vao ?: ($s->bks_ra ?: ''),
            'from' => $s->from_loc ?? '', 'to' => $s->to_loc ?? '', 'kho' => $s->kho ?? '', 'qty' => $s->qty,
            'cru' => (bool) $s->cru, 'date' => $date, 'contLabel' => $contLabel, 'note' => $note, 'thanhLy' => $thanhLy,
            'pr' => $pr,
        ];
    }

    /**
     * Tính lại 1 bảng kê đã lưu: định giá lại các lô (theo id trong snapshot) bằng dữ liệu HIỆN TẠI.
     * Trả về map shipmentId => pr (frontend áp vào dòng tương ứng).
     */
    public function statementReprice(\App\Models\TruckingStatement $st): array
    {
        // Ưu tiên customer_id (bền khi khách đổi tên); name là fallback cho bảng kê cũ.
        $ctx = $this->pricingContext(
            $st->customer_id ? (int) $st->customer_id : null,
            $st->customer_name ?? $st->customer?->name ?? ''
        );

        $ids = $st->lines->map(fn ($l) => $l->shipment_id)->filter()->all();
        $ships = TruckingShipment::whereIn('id', $ids)->with(['costLines', 'raOther:id,gio_xe_ra'])->get()->keyBy('id');

        $out = [];
        foreach ($ids as $id) {
            $s = $ships->get($id);
            if (! $s) continue;
            $sheet = strtoupper((string) $s->sheet);
            // Ngày kỳ = ngày "Giờ xe ra" (gio_xe_ra) — đồng bộ với statementCandidates. HPH fallback sail_date.
            $date  = $this->outDate($s->gio_xe_ra) ?: ($sheet === 'HPH' ? $this->outDate($s->sail_date) : '');
            $out[(string) $id] = $this->candidateRow($s, $sheet, $date, $this->priceShipment($s, $ctx));
        }
        return ['repriced' => $out];
    }

    /**
     * ĐỐI SOÁT NHANH danh sách bảng kê: phát hiện bảng kê nào có lô mà PHẢI THU
     * định giá theo dữ liệu HIỆN TẠI khác số đã chốt trong snapshot → cần mở vào bấm
     * "Tính lại". Dùng cho cảnh báo ngoài danh sách (trang Bảng kê).
     *
     * Cùng quy tắc lệch với recalcDiff ở trang xem: CHỈ xét lô còn trong hệ thống và
     * so PHẢI THU (lô đã xóa giữ nguyên số đã lưu → không coi là "phát sinh").
     *
     * SCALE: scope NGẦM theo $days (mặc định 90) — quét bảng kê có period_to (hoặc
     * created_at fallback) trong khoảng gần đây; bảng kê cũ coi như "đã chốt" không
     * cảnh báo nữa. Chunk 200 để RAM ổn định khi số bảng kê lớn. Bảng giá / code map
     * cache per-request qua pricingContext().
     *
     * @return array<string,array{changed:int}> map statementId => số lô bị lệch
     */
    public function statementsDrift(int $days = 90): array
    {
        $q = TruckingStatement::query()->with('lines');
        if ($days > 0) {
            $cutoff = Carbon::now()->subDays($days)->toDateString();
            $q->where(function ($w) use ($cutoff) {
                $w->where('period_to', '>=', $cutoff)
                  ->orWhere(function ($a) use ($cutoff) {
                      $a->whereNull('period_to')->where('created_at', '>=', $cutoff . ' 00:00:00');
                  });
            });
        }

        $out = [];
        $q->chunkById(200, function ($statements) use (&$out) {
            $allIds = $statements->flatMap(fn ($st) => $st->lines->pluck('shipment_id'))
                ->filter()->unique()->values()->all();
            $ships = empty($allIds)
                ? collect()
                : TruckingShipment::whereIn('id', $allIds)->with(['costLines', 'raOther:id,gio_xe_ra'])->get()->keyBy('id');

            foreach ($statements as $st) {
                // Ưu tiên customer_id (bền); name là fallback.
                $ctx = $this->pricingContext(
                    $st->customer_id ? (int) $st->customer_id : null,
                    $st->customer_name ?? ''
                );
                $changed = 0;
                foreach ($st->lines as $l) {
                    $s = $l->shipment_id ? $ships->get($l->shipment_id) : null;
                    if (! $s) continue;   // lô đã xóa → giữ số đã lưu, không phải "phát sinh"
                    $pr = $this->priceShipment($s, $ctx);
                    if ((int) round((float) $pr['phaiThu']) !== (int) round((float) $l->phai_thu)) $changed++;
                }
                if ($changed > 0) $out[(string) $st->id] = ['changed' => $changed];
            }
        });

        return $out;
    }
}
