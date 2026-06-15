<?php

namespace App\Services\Trucking\Concerns;

use App\Models\TruckingCustomer;
use App\Models\TruckingLocation;
use App\Models\TruckingSetting;
use App\Models\TruckingShipment;
use App\Models\TruckingStatement;
use Carbon\Carbon;

/**
 * SO KHỚP GIÁ Ở BACKEND (nguồn chân lý duy nhất) — port từ makePricer/priceFor (ui.jsx)
 * + calcFreeTime. So khớp KIND chuẩn hóa lowercase+trim (không phân biệt hoa/thường).
 * Dùng cho: tạo bảng kê (statementCandidates) và tính lại (statementReprice).
 * Tối ưu scale: chỉ truy vấn lô CỦA 1 KHÁCH trong khoảng ngày (không kéo toàn bộ lô về client).
 */
trait HandlesStatementPricing
{
    /** Free time của 1 lô — null nếu thiếu dữ liệu. (port calcFreeTime) */
    private function freeTimeOf(TruckingShipment $s, $thresholdH): ?array
    {
        $ra = $s->gio_xe_ra; $den = $s->gio_xe_den; $duKien = $s->gio_den_du_kien;
        if (! $ra || (! $den && ! $duKien)) return null;
        try {
            $dRa = Carbon::parse($ra);
            if ($den && $duKien) {
                $dDen = Carbon::parse($den); $dDk = Carbon::parse($duKien);
                if ($dDen->gt($dDk)) { $start = $dDen; $basis = 'Giờ xe đến'; } else { $start = $dDk; $basis = 'Giờ đến kế hoạch'; }
            } else { $start = Carbon::parse($den ?: $duKien); $basis = $den ? 'Giờ xe đến' : 'Giờ đến kế hoạch'; }
        } catch (\Throwable) { return null; }
        $hours = ($dRa->getTimestamp() - $start->getTimestamp()) / 3600;
        $th = ($thresholdH === null || $thresholdH === '') ? 4.0 : (float) $thresholdH;
        return ['hours' => $hours, 'connect' => $hours > $th, 'threshold' => $th, 'basis' => $basis];
    }

    /** Định giá 1 lô theo bảng giá khách (port priceFor). $priceList = customerPriceList(); $locCode = [tên=>ký hiệu]. */
    private function priceShipment(TruckingShipment $s, array $priceList, array $locCode, $threshold): array
    {
        $codeOf = function ($name) use ($locCode) { $v = trim((string) $name); return $locCode[$v] ?? $v; };
        $codeToName = [];
        foreach ($locCode as $nm => $c) { $c = trim((string) $c); if ($c !== '') $codeToName[$c] = $nm; }
        $nameOf = function ($v) use ($codeToName) { $v = trim((string) $v); return $codeToName[$v] ?? $v; };
        $nk = fn ($v) => mb_strtolower(trim((string) $v));   // chuẩn hóa: lowercase + trim → KHÔNG phân biệt hoa/thường

        $cont20   = str_contains((string) $s->cont_type, '20');
        $isExport = str_contains(mb_strtolower((string) $s->io), 'xu');
        $kind = $s->cru
            ? ($isExport ? 'External CRU transportation' : 'Internal CRU transportation')
            : 'Transportation 1 way of Import/Export';

        $fromRaw = trim((string) $s->from_loc); $dropRaw = trim((string) $s->to_loc);
        $ft = $this->freeTimeOf($s, $threshold);
        $conn = $ft ? ($ft['connect'] ? 'Connect' : 'Disconnect') : null;
        $fromC = $codeOf($s->from_loc); $dropC = $codeOf($s->to_loc);

        $eq = fn ($a, $b) => $a !== '' && $a !== null && $a === $b;
        $fromMatch = fn ($p) => $eq($codeOf($p['from'] ?? ''), $fromC) || $eq(trim((string) ($p['from'] ?? '')), $fromRaw);
        $dropMatch = function ($p) use ($dropRaw, $dropC, $codeOf) {
            if ($dropRaw === '') return true;
            $c = [$codeOf($p['to1'] ?? ''), trim((string) ($p['to1'] ?? '')), $codeOf($p['loc'] ?? ''), trim((string) ($p['loc'] ?? ''))];
            return in_array($dropC, $c, true) || in_array($dropRaw, $c, true);
        };
        $kindMatch = fn ($p) => $nk($p['kind'] ?? '') === $nk($kind);

        $p = null;
        foreach ($priceList as $row) {
            if ($fromMatch($row) && $dropMatch($row) && $kindMatch($row) && (! $conn || ($row['conn'] ?? 'Connect') === $conn)) { $p = $row; break; }
        }
        if (! $p) foreach ($priceList as $row) {
            if ($fromMatch($row) && $dropMatch($row) && $kindMatch($row)) { $p = $row; break; }
        }

        $is20 = $cont20;
        $cuoc = $p ? (int) ($is20 ? $p['transFee20'] : $p['transFee40']) : 0;
        $dau  = $p ? (int) ($is20 ? $p['fuelFee20'] : $p['fuelFee40']) : 0;

        $choHoItems = []; $costItems = [];
        foreach ($s->costLines as $c) {
            $amt = (int) round((float) $c->amount); $bill = (bool) $c->billable;
            if ($bill) $choHoItems[] = ['item' => $c->item ?: '(khoản)', 'amount' => $amt];
            $costItems[] = ['item' => $c->item ?: '(khoản)', 'amount' => $amt, 'billable' => $bill, 'src' => $c->src ?? ''];
        }
        $chiHo = array_sum(array_column($choHoItems, 'amount'));
        $route = $p ? (($nameOf($p['from'] ?? '') ?: '?') . ' → ' . ($nameOf(($p['to1'] ?? '') ?: ($p['loc'] ?? '')) ?: '?')) : null;
        $noDrop = $dropRaw === '' && (bool) $p;

        return [
            'matched' => (bool) $p, 'conn' => $conn, 'kind' => $kind, 'is20' => $is20,
            'cuoc' => $cuoc, 'dau' => $dau, 'chiHo' => $chiHo, 'choHoItems' => $choHoItems, 'costItems' => $costItems,
            'route' => $route, 'noDrop' => $noDrop,
            'ftHours' => $ft['hours'] ?? null, 'ftThreshold' => $ft['threshold'] ?? null, 'ftBasis' => $ft['basis'] ?? null,
            'phaiThu' => $cuoc + $dau + $chiHo,
        ];
    }

    /** [tên địa điểm => ký hiệu] cho codeOf. */
    private function locationCodeMap(): array
    {
        $m = [];
        foreach (TruckingLocation::get(['name', 'code']) as $l) { if ($l->name && $l->code) $m[$l->name] = $l->code; }
        return $m;
    }

    /**
     * Ứng viên cho 1 bảng kê: lô CỦA 1 KHÁCH trong khoảng cont-ra, ĐÃ ĐỊNH GIÁ ở server.
     * Trả về dòng sẵn sàng hiển thị + lưu (kèm pr để hiện trạng thái khớp).
     */
    public function statementCandidates(string $customer, ?string $from, ?string $to): array
    {
        $cust = trim($customer);
        if ($cust === '') return ['candidates' => []];
        $custId = TruckingCustomer::where('name', $cust)->value('id');
        if (! $custId) return ['candidates' => []];

        $priceList = $this->customerPriceList($cust);
        $locCode   = $this->locationCodeMap();
        $threshold = TruckingSetting::get('free_time_hours', '4');

        // ============================================================
        // NGÀY KỲ BẢNG KÊ = NGÀY của "Giờ xe ra" (cột `gio_xe_ra`) — INPUT mà bộ lọc
        // "Cont ra từ ngày – đến ngày" ở trang Tạo bảng kê dựa vào.
        // (Đã bỏ field "Ngày cont ra"/`cont_ra` ở popup Lô hàng — gio_xe_ra là mốc cont rời đi.)
        // ICD: CHỈ tính theo gio_xe_ra → lô chưa có giờ ra KHÔNG vào bảng kê.
        // HPH: fallback sail_date (HPH không có khái niệm giờ xe ra).
        // ============================================================
        $rows = TruckingShipment::where('customer_id', $custId)->with('costLines')->orderBy('gio_xe_ra')->get();
        $out = [];
        foreach ($rows as $s) {
            $sheet = strtoupper((string) $s->sheet);
            // Cột "Cont ra" = ngày của Giờ xe ra (ICD). HPH fallback sail_date (HPH không có giờ xe ra).
            $date  = $this->outDate($s->gio_xe_ra) ?: ($sheet === 'HPH' ? $this->outDate($s->sail_date) : '');
            // Lọc theo kỳ = theo NGÀY GIỜ XE RA. Lô CHƯA CÓ giờ ra (date rỗng) → CHƯA rời đi → KHÔNG đưa vào bảng kê.
            if (($from || $to) && ! $date) continue;
            if ($from && $date && $date < $from) continue;
            if ($to && $date && $date > $to) continue;
            $out[] = $this->candidateRow($s, $sheet, $date, $this->priceShipment($s, $priceList, $locCode, $threshold));
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
            'from' => $s->from_loc ?? '', 'to' => $s->to_loc ?? '', 'qty' => $s->qty,
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
        $customer = $st->customer_name ?? $st->customer?->name ?? '';
        $priceList = $this->customerPriceList($customer);
        $locCode   = $this->locationCodeMap();
        $threshold = TruckingSetting::get('free_time_hours', '4');

        $ids = $st->lines->map(fn ($l) => $l->shipment_id)->filter()->all();
        $ships = TruckingShipment::whereIn('id', $ids)->with('costLines')->get()->keyBy('id');

        $out = [];
        foreach ($ids as $id) {
            $s = $ships->get($id);
            if (! $s) continue;
            $sheet = strtoupper((string) $s->sheet);
            // Ngày kỳ = ngày "Giờ xe ra" (gio_xe_ra) — đồng bộ với statementCandidates. HPH fallback sail_date.
            $date  = $this->outDate($s->gio_xe_ra) ?: ($sheet === 'HPH' ? $this->outDate($s->sail_date) : '');
            $out[(string) $id] = $this->candidateRow($s, $sheet, $date, $this->priceShipment($s, $priceList, $locCode, $threshold));
        }
        return ['repriced' => $out];
    }

    /**
     * ĐỐI SOÁT NHANH cả danh sách bảng kê: phát hiện bảng kê nào có lô mà PHẢI THU
     * định giá theo dữ liệu HIỆN TẠI khác số đã chốt trong snapshot → cần mở vào bấm
     * "Tính lại". Dùng cho cảnh báo ngoài danh sách (trang Bảng kê).
     *
     * Cùng quy tắc lệch với recalcDiff ở trang xem: CHỈ xét lô còn trong hệ thống và
     * so PHẢI THU (lô đã xóa giữ nguyên số đã lưu → không coi là "phát sinh").
     *
     * Tối ưu query: 1 lượt nạp bảng kê (kèm lines) + 1 lượt nạp toàn bộ lô liên quan;
     * bảng giá nạp 1 lần / khách (cache theo tên). Gọi LAZY sau khi danh sách đã render.
     *
     * @return array<string,array{changed:int}> map statementId => số lô bị lệch (chỉ bảng kê có lệch)
     */
    public function statementsDrift(): array
    {
        $statements = TruckingStatement::with(['lines', 'customer'])->get();
        if ($statements->isEmpty()) return [];

        // Gom mọi shipment_id qua tất cả bảng kê → nạp lô + costLines 1 lần.
        $allIds = $statements->flatMap(fn ($st) => $st->lines->pluck('shipment_id'))
            ->filter()->unique()->values()->all();
        $ships = empty($allIds)
            ? collect()
            : TruckingShipment::whereIn('id', $allIds)->with('costLines')->get()->keyBy('id');

        $locCode   = $this->locationCodeMap();
        $threshold = TruckingSetting::get('free_time_hours', '4');
        $priceCache = [];   // tên khách => bảng giá (tránh query lặp khi nhiều bảng kê cùng khách)

        $out = [];
        foreach ($statements as $st) {
            $customer = $st->customer_name ?? $st->customer?->name ?? '';
            $priceList = $priceCache[$customer] ??= $this->customerPriceList($customer);

            $changed = 0;
            foreach ($st->lines as $l) {
                $s = $l->shipment_id ? $ships->get($l->shipment_id) : null;
                if (! $s) continue;   // lô đã xóa → giữ số đã lưu, không phải "phát sinh"
                $pr = $this->priceShipment($s, $priceList, $locCode, $threshold);
                if ((int) round((float) $pr['phaiThu']) !== (int) round((float) $l->phai_thu)) $changed++;
            }
            if ($changed > 0) $out[(string) $st->id] = ['changed' => $changed];
        }

        return $out;
    }
}
