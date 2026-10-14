<?php

namespace App\Services\Trucking\Concerns;

use App\Models\TruckingExtStatement;
use App\Models\TruckingExtStatementLine;
use App\Models\TruckingExtStatementPayment;
use App\Models\TruckingShipment;
use App\Support\Hashid;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bảng kê xe ngoài (phải trả nhà xe thuê) — mirror HandlesStatements.
 *
 * Mỗi bảng kê = 1 NHÀ XE. Chọn nhà xe + khoảng ngày Giờ xe đến → gom các lô của
 * nhà xe đó (ext_fee > 0) → snapshot dòng + lịch sử thanh toán. Công nợ = tổng
 * cước − đã trả. KHÔNG định giá phức tạp — cước lấy thẳng từ shipments.ext_fee.
 */
trait HandlesExtStatements
{
    /**
     * Ứng viên cho 1 bảng kê xe ngoài: lô của 1 NHÀ XE trong khoảng GIỜ XE ĐẾN.
     * Lấy lô có cước thuê (ext_fee > 0) HOẶC có khoản tích "Chi cho xe ngoài" (ext_pay)
     * ở Chi phí lô hàng. Đẩy lọc vào SQL theo index (ext_vendor, gio_xe_den) — không kéo
     * toàn bộ lô. Eager-load costLines để cộng các khoản chi cho xe ngoài (gross).
     */
    public function extStatementCandidates(string $vendor, ?string $from, ?string $to): array
    {
        $v = trim($vendor);
        if ($v === '') return ['candidates' => []];

        $q = TruckingShipment::with('costLines', 'customer')
            ->where('ext_vendor', $v)
            ->whereNotNull('gio_xe_den')
            ->where(function ($w) {
                $w->where('ext_fee', '>', 0)->orWhereHas('costLines', fn ($c) => $c->where('ext_pay', true));
            });
        if ($from) $q->where('gio_xe_den', '>=', $from . ' 00:00:00');
        if ($to)   $q->where('gio_xe_den', '<=', $to . ' 23:59:59');

        $rows = $q->orderBy('gio_xe_den')->get();
        $out = [];
        foreach ($rows as $s) {
            $sheet = strtoupper((string) $s->sheet);
            $contLabel = $sheet === 'HPH'
                ? ($s->qty . ' × ' . $s->cont_type)
                : (($s->cont_no ?: $s->cont_type) . ($s->cont_no ? ' · ' . $s->cont_type : ''));
            $fee   = (int) round((float) $s->ext_fee);
            // Chi hộ = các khoản tích "Chi cho xe ngoài" (ext_pay) ở Chi phí lô hàng — gross (như bảng kê khách).
            $chohoItems = []; $choho = 0;
            foreach ($s->costLines->where('ext_pay', true) as $c) {
                $amt = (int) round((float) $c->amount);
                $choho += $amt;
                $chohoItems[] = ['key' => (string) $c->id, 'item' => $c->item ?: '(khoản)', 'amount' => $amt];
            }
            if ($fee <= 0 && $choho <= 0) continue;   // không có gì để trả nhà xe
            $out[] = [
                'id'         => $s->id,
                'booking'    => $s->booking ?? '',
                'customer'   => $s->customer ? ($s->customer->short_name ?: $s->customer->name) : '',
                'io'         => $s->io ?? '',
                'sheet'      => $sheet,
                'bks'        => $s->bks_vao ?: ($s->bks_ra ?: ''),
                'from'       => $s->from_loc ?? '',
                'to'         => $s->to_loc ?? '',
                'contLabel'  => $contLabel,
                'date'       => $this->outDate($s->gio_xe_den),
                'fee'        => $fee,
                'choho'      => $choho,
                'chohoItems' => $chohoItems,                    // breakdown danh mục chi hộ
                'chohoNote'  => $this->chohoNoteText($chohoItems),
                'note'       => trim((string) $s->ghi_chu),
            ];
        }
        return ['candidates' => $out];
    }

    /** Gộp breakdown chi hộ thành 1 dòng ghi chú: "Nâng 2.916.000 · Lưu cont 300.000". */
    private function chohoNoteText(array $items): string
    {
        return implode(' · ', array_map(
            fn ($it) => trim((string) ($it['item'] ?? '')) . ' ' . number_format((int) round((float) ($it['amount'] ?? 0)), 0, ',', '.'),
            array_filter($items, fn ($it) => (int) round((float) ($it['amount'] ?? 0)) !== 0)
        ));
    }

    /**
     * Lưu bảng kê xe ngoài. Payload: {id?, no, vendor, date, from, to, note?, lines[], payments[]}.
     * total = Σ line.fee (nguồn chân lý — server tự cộng). Bulk insert lines + payments.
     */
    public function saveExtStatement(array $data, ?TruckingExtStatement $st = null): TruckingExtStatement
    {
        return DB::transaction(function () use ($data, $st) {
            // Tổng = Σ(cước + VAT(cước) + chi hộ). VAT chỉ áp lên cước; chi hộ không VAT.
            $total = 0;
            foreach (($data['lines'] ?? []) as $l) {
                $fee   = $this->inMoney($l['fee'] ?? null) ?? 0;
                $choho = $this->inMoney($l['choho'] ?? null) ?? 0;
                $rate  = (float) ($l['vatRate'] ?? 0);
                $total += $fee + (int) round($fee * $rate / 100) + $choho;
            }

            $st ??= new TruckingExtStatement();
            $st->fill([
                'no'          => $data['no'] ?? $st->no,
                'ext_vendor'  => $this->str($data['vendor'] ?? null),
                'date'        => $this->inDate($data['date'] ?? null),
                'period_from' => $this->inDate($data['from'] ?? null),
                'period_to'   => $this->inDate($data['to'] ?? null),
                'total'       => $total,
                'note'        => $this->str($data['note'] ?? null),
            ]);
            $st->save();

            $now = Carbon::now();
            $st->lines()->delete();
            $linesBatch = [];
            foreach (($data['lines'] ?? []) as $i => $l) {
                $fee   = $this->inMoney($l['fee'] ?? null) ?? 0;
                $items = $this->extChohoItems($l['chohoItems'] ?? null, (int) ($this->inMoney($l['choho'] ?? null) ?? 0), $l['chohoNote'] ?? '');
                $parts = $this->extLineParts((int) round($fee), (int) round($fee * (float) ($l['vatRate'] ?? 0) / 100), $items);
                // Dấu đã chi theo phần; payload cũ chỉ có paidAt (đã chi đủ) → coi mọi phần đã chi ngày đó.
                $pp    = $l['paidParts'] ?? null;
                $paid  = (is_array($pp) || is_object($pp)) ? (array) $pp
                    : (! empty($l['paidAt']) ? array_fill_keys(array_keys($parts), $l['paidAt']) : []);
                $paid  = $this->extCleanPaid($paid, $parts);
                $linesBatch[] = [
                    'ext_statement_id' => $st->id,
                    'shipment_id'      => $l['id'] ?? null,
                    'booking'          => $this->str($l['booking'] ?? null),
                    'customer'         => $this->str($l['customer'] ?? null),
                    'io'               => $this->str($l['io'] ?? null),
                    'sheet'            => $this->str($l['sheet'] ?? null),
                    'bks'              => $this->str($l['bks'] ?? null),
                    'from_loc'         => $this->str($l['from'] ?? null),
                    'to_loc'           => $this->str($l['to'] ?? null),
                    'cont_label'       => $this->str($l['contLabel'] ?? null),
                    'date'             => $this->inDate($l['date'] ?? null),
                    'fee'              => $this->inMoney($l['fee'] ?? null) ?? 0,
                    'choho'            => $this->inMoney($l['choho'] ?? null) ?? 0,
                    'choho_note'       => $this->str($l['chohoNote'] ?? null),
                    'choho_items'      => $items ? json_encode($items, JSON_UNESCAPED_UNICODE) : null,
                    'vat_rate'         => (float) ($l['vatRate'] ?? 0),
                    'note'             => $this->str($l['note'] ?? null),
                    'paid_at'          => $this->extLinePaidAt($parts, $paid),   // đã chi ĐỦ = mọi phần đã tích
                    'paid_parts'       => $paid ? json_encode($paid) : null,
                    'sort'             => $i,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }
            if ($linesBatch) TruckingExtStatementLine::insert($linesBatch);

            $st->payments()->delete();
            $paymentsBatch = [];
            foreach (($data['payments'] ?? []) as $i => $p) {
                $paymentsBatch[] = [
                    'ext_statement_id' => $st->id,
                    'date'             => $this->inDate($p['date'] ?? null),
                    'amount'           => $this->inMoney($p['amount'] ?? null),
                    'note'             => $this->str($p['note'] ?? null),
                    'sort'             => $i,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }
            if ($paymentsBatch) TruckingExtStatementPayment::insert($paymentsBatch);

            return $st->fresh(['lines', 'payments']);
        });
    }

    /**
     * Danh sách bảng kê xe ngoài cho trang list — tóm tắt + payments (không nạp lines).
     * Lọc optional theo vendor/from/to (kỳ = period_to). Sắp mới nhất.
     *
     * @param array{vendor?:?string,from?:?string,to?:?string} $filters
     */
    public function extStatementsForList(array $filters = []): array
    {
        $q = TruckingExtStatement::with('payments');
        $vendor = trim((string) ($filters['vendor'] ?? ''));
        if ($vendor !== '') $q->where('ext_vendor', $vendor);
        if (! empty($filters['from'])) $q->where(function ($w) use ($filters) {
            $w->where('period_to', '>=', $filters['from'])
              ->orWhere(function ($a) use ($filters) { $a->whereNull('period_to')->where('date', '>=', $filters['from']); });
        });
        if (! empty($filters['to'])) $q->where(function ($w) use ($filters) {
            $w->where('period_to', '<=', $filters['to'])
              ->orWhere(function ($a) use ($filters) { $a->whereNull('period_to')->where('date', '<=', $filters['to']); });
        });

        $q->withCount(['lines', 'lines as paid_count' => fn ($l) => $l->whereNotNull('paid_at')]);
        return $q->orderByDesc('id')->get()->map(function ($st) {
            $total = (int) round((float) $st->total);
            $paid  = (int) $st->payments->reduce(fn ($a, $p) => $a + (int) round((float) $p->amount), 0);
            return [
                'id'     => $st->id,
                'hashid' => Hashid::encode($st->id),
                'no'     => $st->no,
                'vendor' => $st->ext_vendor ?? '',
                'date'   => $this->outDate($st->date),
                'from'   => $this->outDate($st->period_from),
                'to'     => $this->outDate($st->period_to),
                'total'  => $total,
                'paid'   => $paid,
                'conNo'  => $total - $paid,
                'count'     => (int) $st->lines_count,
                'paidCount' => (int) $st->paid_count,   // số lô đã tích "đã chi"
            ];
        })->all();
    }

    /** Chi tiết đầy đủ 1 bảng kê xe ngoài: lines + payments + total + paid + conNo. */
    public function extStatementToArray(TruckingExtStatement $st): array
    {
        $total = (int) round((float) $st->total);
        $paid  = (int) $st->payments->reduce(fn ($a, $p) => $a + (int) round((float) $p->amount), 0);
        return [
            'id'     => $st->id,
            'hashid' => Hashid::encode($st->id),
            'no'     => $st->no,
            'vendor' => $st->ext_vendor ?? '',
            'date'   => $this->outDate($st->date),
            'from'   => $this->outDate($st->period_from),
            'to'     => $this->outDate($st->period_to),
            'note'   => $st->note ?? '',
            'total'  => $total,
            'paid'   => $paid,
            'conNo'  => $total - $paid,
            'lines'  => $st->lines->map(function ($l) {
                $fee  = (int) round((float) $l->fee);
                $rate = (float) $l->vat_rate;
                return [
                    'id'        => $l->shipment_id ?? $l->id,
                    'lineId'    => $l->id,
                    'booking'   => $l->booking ?? '',
                    'customer'  => $l->customer ?? '',
                    'io'        => $l->io ?? '',
                    'sheet'     => $l->sheet ?? '',
                    'bks'       => $l->bks ?? '',
                    'from'      => $l->from_loc ?? '',
                    'to'        => $l->to_loc ?? '',
                    'contLabel' => $l->cont_label ?? '',
                    'date'      => $this->outDate($l->date),
                    'fee'       => $fee,
                    'choho'     => (int) round((float) $l->choho),
                    'chohoNote' => $l->choho_note ?? '',
                    'vatRate'   => $rate,
                    'vat'       => (int) round($fee * $rate / 100),   // VAT chỉ trên cước
                    'note'      => $l->note ?? '',
                    'paidAt'    => $this->outDate($l->paid_at),   // '' = chưa chi đủ
                    'chohoItems' => $this->extChohoItems($l->choho_items, (int) round((float) $l->choho), $l->choho_note ?? ''),
                    'paidParts'  => (object) $this->extLinePaidParts($l),
                ];
            })->all(),
            'payments' => $st->payments->map(fn ($p) => [
                'id'     => $p->id,
                'date'   => $this->outDate($p->date),
                'amount' => $this->outMoney($p->amount),
                'note'   => $p->note ?? '',
            ])->all(),
        ];
    }

    /**
     * Tích / bỏ tích "đã chi" cho các dòng lô của 1 bảng kê — ghi ngay, không đụng cột khác.
     * $part = null → mọi phần của dòng; 'fee' → cước (+VAT); 'i:<key>' → 1 khoản chi hộ.
     * Trả map {lineId: {paidAt, paidParts}} sau khi cập nhật.
     */
    public function setExtStatementLinesPaid(TruckingExtStatement $st, array $lineIds, bool $paid, ?string $date = null, ?string $part = null): array
    {
        $ids  = array_values(array_filter(array_map('intval', $lineIds)));
        $date = $date ?: Carbon::today()->toDateString();
        $out  = [];
        foreach ($st->lines()->whereIn('id', $ids)->get() as $l) {
            $parts = $this->extLinePartsOf($l);
            $cur   = $this->extLinePaidParts($l);
            foreach ($part !== null ? array_intersect([$part], array_keys($parts)) : array_keys($parts) as $k) {
                if ($paid) $cur[$k] ??= $date; else unset($cur[$k]);
            }
            $l->paid_parts = $cur ?: null;
            $l->paid_at    = $this->extLinePaidAt($parts, $cur);
            $l->save();
            $out[$l->id] = ['paidAt' => $this->outDate($l->paid_at), 'paidParts' => (object) $cur];
        }
        return $out;
    }

    /**
     * Các khoản chi hộ của 1 dòng [{key, item, amount}]. Dòng cũ chưa lưu chi tiết → gộp 1 khoản
     * "Chi hộ" (key 'choho') để vẫn tích chi được.
     */
    private function extChohoItems($items, int $choho, string $note): array
    {
        if (is_string($items)) $items = json_decode($items, true);
        if (is_array($items) && $items) {
            return array_values(array_map(fn ($it, $i) => [
                'key'    => (string) ($it['key'] ?? $i),
                'item'   => (string) ($it['item'] ?? ''),
                'amount' => (int) round((float) ($it['amount'] ?? 0)),
            ], $items, array_keys($items)));
        }
        return $choho ? [['key' => 'choho', 'item' => trim($note) !== '' ? $note : 'Chi hộ', 'amount' => $choho]] : [];
    }

    /** Các phần tiền tích chi riêng được: 'fee' => cước+VAT, 'i:<key>' => 1 khoản chi hộ (bỏ phần 0đ). */
    private function extLineParts(int $fee, int $vat, array $items): array
    {
        $parts = [];
        if ($fee + $vat !== 0) $parts['fee'] = $fee + $vat;
        foreach ($items as $it) {
            if ((int) $it['amount'] !== 0) $parts['i:' . $it['key']] = (int) $it['amount'];
        }
        return $parts;
    }

    private function extLinePartsOf(TruckingExtStatementLine $l): array
    {
        $fee = (int) round((float) $l->fee);
        return $this->extLineParts($fee, (int) round($fee * (float) $l->vat_rate / 100),
            $this->extChohoItems($l->choho_items, (int) round((float) $l->choho), $l->choho_note ?? ''));
    }

    /** Dấu đã chi theo phần của dòng; dòng chỉ có paid_at (đã chi đủ, trước khi tách phần) → mọi phần. */
    private function extLinePaidParts(TruckingExtStatementLine $l): array
    {
        $parts = $this->extLinePartsOf($l);
        if (is_array($l->paid_parts)) return $this->extCleanPaid($l->paid_parts, $parts);
        return $l->paid_at ? array_fill_keys(array_keys($parts), $l->paid_at->toDateString()) : [];
    }

    /** Chỉ giữ dấu của phần còn tồn tại, ngày hợp lệ Y-m-d. */
    private function extCleanPaid(array $paid, array $parts): array
    {
        $out = [];
        foreach ($paid as $k => $d) {
            if (isset($parts[$k]) && is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) $out[$k] = $d;
        }
        return $out;
    }

    /** Đã chi ĐỦ khi mọi phần đã tích → ngày chi muộn nhất; còn phần chưa tích → null. */
    private function extLinePaidAt(array $parts, array $paid): ?string
    {
        if (! $parts) return null;
        foreach (array_keys($parts) as $k) if (empty($paid[$k])) return null;
        return max(array_intersect_key($paid, $parts));
    }
}
