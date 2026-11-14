<?php

namespace App\Services\Trucking\Concerns;

use App\Models\TruckingStatement;
use App\Support\Hashid;
use Carbon\Carbon;

/**
 * Báo cáo DOANH THU PHẢI THU theo tháng — dựng từ BẢNG KÊ THU (bảng kê cần thu khách) + các đợt khách thanh toán.
 * Cùng cách tính với báo cáo công nợ xe ngoài (extPayableReport), chỉ đổi chiều: phải THU khách.
 */
trait HandlesReceivableReport
{
    /**
     * Sổ tổng hợp công nợ phải thu khách theo KỲ = khoảng tháng (1 tháng / quý / tùy chọn; chỉ đọc). "Trong tháng" dưới đây = trong kỳ:
     *  - Doanh thu phát sinh (TĂNG) = bảng kê có "ĐẾN NGÀY" của kỳ cont ra (period_to) trong tháng; chưa có kỳ → ngày lập.
     *    Tách DOANH THU (nền cước+dầu+sà lan) · VAT · chi hộ · phí khác (phí tờ khai + cột phí riêng) — số đã CHỐT khi lưu bảng kê;
 *    PHẢI THU = tổng bảng kê = doanh thu + VAT + chi hộ + phí khác.
     *  - Đã thu (GIẢM) = đợt thanh toán có NGÀY THU trong tháng.
     *  - Dư đầu kỳ = Σ bảng kê trước tháng − Σ đã thu trước tháng; dư cuối = đầu + tăng − giảm (âm = khách trả trước).
     *  - openDocs: bảng kê còn phải thu tính đến cuối tháng + hạn thanh toán / số ngày quá hạn.
     */
    public function receivableReport(string $fromYm, ?string $toYm = null): array
    {
        // KỲ = khoảng THÁNG [fromYm, toYm] ("YYYY-MM"): 1 tháng, 1 quý hoặc tùy chọn. Đảo nếu nhập ngược.
        $start = Carbon::createFromFormat('!Y-m', $fromYm)->startOfMonth();
        $end   = Carbon::createFromFormat('!Y-m', $toYm ?: $fromYm)->endOfMonth();
        if ($end->lt($start)) [$start, $end] = [$end->copy()->startOfMonth(), $start->copy()->endOfMonth()];
        $months = $start->diffInMonths($end->copy()->addDay()->startOfMonth());   // số tháng của kỳ
        $from  = $start->toDateString();
        $to    = $end->toDateString();
        $prevFrom = $start->copy()->subMonthsNoOverflow((int) $months)->toDateString();   // kỳ trước cùng độ dài
        // Mốc tính quá hạn: cuối tháng xem, nhưng tháng hiện tại thì tính tới hôm nay.
        $asOf  = min($to, Carbon::today()->toDateString());

        $sts = TruckingStatement::with(['payments', 'customer'])->withCount('lines')->orderBy('date')->orderBy('id')->get();
        $dayOf = fn ($m) => $m->date ? $m->date->toDateString() : optional($m->created_at)->toDateString();
        $stDay = fn ($st) => $st->period_to ? $st->period_to->toDateString() : $dayOf($st);

        $rows = []; $incDocs = []; $decDocs = []; $openDocs = [];
        $prev = ['inc' => 0, 'dec' => 0, 'base' => 0];
        foreach ($sts as $st) {
            $c = trim((string) ($st->customer?->name ?: $st->customer_name)) ?: '(Chưa có khách)';
            $ref = ['hashid' => Hashid::encode($st->id), 'no' => $st->no, 'customer' => $c];
            $a = $this->statementListAmounts($st);   // số đã chốt: base · vat · choho · extra (phí riêng + phí tờ khai) · total
            $total = $a['total'];
            $d = $stDay($st);
            $r = &$rows[$c];
            $r ??= ['customer' => $c, 'opening' => 0, 'inc' => 0, 'dec' => 0, 'nInc' => 0, 'nDec' => 0, 'overdue' => 0,
                'base' => 0, 'vat' => 0, 'choho' => 0, 'other' => 0];

            if ($d > $to) { unset($r); continue; }   // bảng kê kỳ sau tháng đang xem — chưa phát sinh
            if ($d < $from) {
                $r['opening'] += $total;
                if ($d >= $prevFrom) { $prev['inc'] += $total; $prev['base'] += $a['base']; }
            } else {
                $r['inc'] += $total; $r['nInc']++;
                $r['base'] += $a['base']; $r['vat'] += $a['vat']; $r['choho'] += $a['choho']; $r['other'] += $a['extra'];
                $incDocs[] = $ref + ['date' => $d, 'created' => $dayOf($st), 'from' => $this->outDate($st->period_from), 'to' => $this->outDate($st->period_to),
                    'count' => (int) $st->lines_count, 'base' => $a['base'], 'vat' => $a['vat'], 'choho' => $a['choho'], 'other' => $a['extra'], 'total' => $total];
            }

            $paidToEnd = 0;
            foreach ($st->payments as $p) {
                $amt = (int) round((float) $p->amount);
                if (! $amt) continue;
                $pd = $dayOf($p);
                if ($pd > $to) continue;
                $paidToEnd += $amt;
                if ($pd < $from) {
                    $r['opening'] -= $amt;
                    if ($pd >= $prevFrom) $prev['dec'] += $amt;
                } else {
                    $r['dec'] += $amt; $r['nDec']++;
                    $decDocs[] = $ref + ['date' => $pd, 'amount' => $amt, 'note' => $p->note ?? ''];
                }
            }

            // Còn phải thu của bảng kê tính đến cuối tháng + hạn thanh toán.
            $rem = $total - $paidToEnd;
            if ($rem > 0) {
                $due = $this->statementDueDate($st);
                $late = ($due !== '' && $due < $asOf) ? (int) Carbon::parse($due)->diffInDays(Carbon::parse($asOf)) : 0;
                if ($late > 0) $r['overdue'] += $rem;
                $openDocs[] = $ref + ['date' => $d, 'created' => $dayOf($st), 'total' => $total, 'paid' => $paidToEnd, 'remaining' => $rem, 'due' => $due, 'late' => $late];
            }
            unset($r);
        }

        // Bỏ khách đã thu đủ từ trước và không phát sinh gì trong tháng.
        $rows = array_values(array_filter($rows, fn ($r) => $r['opening'] || $r['inc'] || $r['dec']));
        foreach ($rows as &$r) $r['closing'] = $r['opening'] + $r['inc'] - $r['dec'];
        unset($r);
        usort($rows, fn ($a, $b) => [$b['closing'], $a['customer']] <=> [$a['closing'], $b['customer']]);
        $byDate = fn ($a, $b) => [$a['date'], $a['no']] <=> [$b['date'], $b['no']];
        usort($incDocs, $byDate); usort($decDocs, $byDate);
        usort($openDocs, fn ($a, $b) => [$b['late'], $b['remaining']] <=> [$a['late'], $a['remaining']]);

        $sum = fn (array $list, string $k) => array_sum(array_column($list, $k));
        return [
            'fromYm' => $start->format('Y-m'), 'toYm' => $end->format('Y-m'), 'months' => (int) $months, 'from' => $from, 'to' => $to, 'asOf' => $asOf,
            'rows' => $rows, 'incDocs' => $incDocs, 'decDocs' => $decDocs, 'openDocs' => $openDocs,
            'totals' => [
                'opening' => $sum($rows, 'opening'), 'inc' => $sum($rows, 'inc'), 'dec' => $sum($rows, 'dec'), 'closing' => $sum($rows, 'closing'),
                'base' => $sum($incDocs, 'base'), 'vat' => $sum($incDocs, 'vat'), 'choho' => $sum($incDocs, 'choho'), 'other' => $sum($incDocs, 'other'),
                'overdue' => $sum($rows, 'overdue'), 'lots' => $sum($incDocs, 'count'),
            ],
            'prev' => $prev,
        ];
    }
}
