<?php

namespace App\Services\Trucking\Concerns;

use App\Models\TruckingOfficeCostType;
use App\Models\TruckingVehicle;
use App\Models\TruckingVehicleCost;
use App\Support\Hashid;
use Carbon\Carbon;

/**
 * BÁO CÁO CHI PHÍ VĂN PHÒNG (chi phí quản lý doanh nghiệp) — tab "Văn phòng" ở /bao-cao (1 tháng)
 * và /bao-cao-tai-san (khoảng tháng from→to).
 *
 * Nguồn: phiếu chi của trung tâm chi phí kind='office' (trucking_vehicle_costs), bỏ phiếu đã hủy.
 * Hai cách nhìn, ghi rõ để không lẫn:
 *  - "Chi tiêu" (spent): theo NGÀY CHI, nguyên số tiền — KHỚP với nhóm "Chi phí văn phòng" trên P&L tháng.
 *  - "Ghi nhận" (accrual): phiếu thường theo ngày chi + phần PHÂN BỔ của khoản trả trước rơi vào kỳ
 *    (bảo hiểm 12 tháng, thuê VP trả trước…) — đúng bản chất chi phí của kỳ để so sánh kỳ với kỳ.
 */
trait HandlesOfficeReport
{
    /** 1 tháng (trang /bao-cao). */
    public function officeReport(int $year, int $month): array
    {
        $i = $year * 12 + ($month - 1);
        return $this->officeReportRange($i, $i);
    }

    /** Khoảng tháng [fromIdx..toIdx] (chỉ số tháng = năm×12 + tháng−1) — trang /bao-cao-tai-san. */
    public function officeReportRange(int $fromIdx, int $toIdx): array
    {
        if ($toIdx < $fromIdx) [$fromIdx, $toIdx] = [$toIdx, $fromIdx];
        $months = $toIdx - $fromIdx + 1;
        $idx   = fn (Carbon $d) => (int) $d->format('Y') * 12 + ((int) $d->format('n') - 1);
        $ymOf  = fn (int $i) => sprintf('%04d-%02d', intdiv($i, 12), $i % 12 + 1);
        $inRange = fn (int $i) => $i >= $fromIdx && $i <= $toIdx;
        $today = Carbon::today();
        $typeName = TruckingOfficeCostType::pluck('name', 'id');

        // Toàn bộ phiếu văn phòng (bảng nhỏ) → kỳ này, kỳ trước, xu hướng 12 tháng trong 1 lượt.
        $slips = TruckingVehicleCost::with('vehicle:id,kind')
            ->whereHas('vehicle', fn ($q) => $q->where('kind', 'office'))
            ->whereNull('cancelled_at')->orderBy('spend_date')->orderBy('sort')->get();

        $typeOf = fn (TruckingVehicleCost $c) => ($c->cost_type_id ? ($typeName[$c->cost_type_id] ?? null) : null) ?: (trim((string) $c->name) ?: 'Khác');

        // ---- Gom theo tháng: spent (ngày chi) · accrual (thường theo ngày chi, phân bổ rải đều) · count ----
        $spent = []; $accr = []; $cnt = [];
        $add = function (array &$m, int $i, int $amt) { $m[$i] = ($m[$i] ?? 0) + $amt; };
        foreach ($slips as $c) {
            if (! $c->spend_date) continue;
            $amt = (int) round((float) $c->amount);
            $sI = $idx(Carbon::parse($c->spend_date));
            $add($spent, $sI, $amt); $cnt[$sI] = ($cnt[$sI] ?? 0) + 1;
            if ($c->alloc && (int) $c->alloc_months > 0) {
                $m = (int) $c->alloc_months; $per = (int) round($amt / $m);
                for ($i = $sI; $i < $sI + $m; $i++) $add($accr, $i, $per);
            } else {
                $add($accr, $sI, $amt);
            }
        }
        $sumRange = fn (array $m, int $a, int $b) => (int) array_sum(array_map(fn ($i) => $m[$i] ?? 0, range($a, $b)));

        // ---- Trong kỳ: theo loại, theo nhà cung cấp, danh sách phiếu ----
        $byType = []; $bySup = []; $list = [];
        $pending = ['count' => 0, 'amount' => 0]; $toPay = ['count' => 0, 'amount' => 0];
        foreach ($slips as $c) {
            $amt = (int) round((float) $c->amount);
            // Chờ xử lý — đếm toàn bộ, không theo kỳ (kế toán cần biết còn bao nhiêu phiếu treo)
            if (! $c->approved) { $pending['count']++; $pending['amount'] += $amt; }
            elseif (! $c->paid) { $toPay['count']++; $toPay['amount'] += $amt; }

            if (! $c->spend_date || ! $inRange($idx(Carbon::parse($c->spend_date)))) continue;
            $t = $typeOf($c);
            $byType[$t] ??= ['label' => $t, 'amount' => 0, 'count' => 0];
            $byType[$t]['amount'] += $amt; $byType[$t]['count']++;
            $sup = trim((string) $c->supplier);
            if ($sup !== '') $bySup[$sup] = ($bySup[$sup] ?? 0) + $amt;
            $st = $this->vehicleCostStatus($c);
            $list[] = [
                'id' => $c->id, 'hashid' => Hashid::encode($c->id),
                'invoiceNo' => $c->invoice_no ?? '', 'type' => $t, 'name' => $c->name ?? '',
                'supplier' => $sup, 'payer' => $c->payer ?? '', 'note' => $c->note ?? '',
                'amount' => $amt, 'spendDate' => $this->outDate($c->spend_date), 'dueDate' => $this->outDate($c->due_date),
                'recurring' => $c->kind === 'recurring',
                'alloc' => (bool) $c->alloc, 'allocMonths' => (int) $c->alloc_months,
                'approved' => (bool) $c->approved, 'paid' => (bool) $c->paid,
                'status' => $st['code'], 'statusLabel' => $st['label'],
            ];
        }
        $rangeSpent = $sumRange($spent, $fromIdx, $toIdx);
        $byType = array_values($byType);
        usort($byType, fn ($a, $b) => $b['amount'] <=> $a['amount']);
        foreach ($byType as &$t) $t['pct'] = $rangeSpent ? round($t['amount'] * 100 / $rangeSpent, 1) : 0;
        unset($t);
        arsort($bySup);
        $bySupplier = [];
        foreach (array_slice($bySup, 0, 8, true) as $k => $v) $bySupplier[] = ['label' => $k, 'amount' => $v];
        usort($list, fn ($a, $b) => strcmp($b['spendDate'], $a['spendDate']) ?: ($b['amount'] <=> $a['amount']));

        // ---- Khoản trả trước đang phân bổ, còn hiệu lực trong kỳ (tiến độ tính đến THÁNG CUỐI kỳ) ----
        $alloc = [];
        foreach ($slips as $c) {
            if (! ($c->alloc && (int) $c->alloc_months > 0 && $c->spend_date)) continue;
            $m = (int) $c->alloc_months; $amt = (int) round((float) $c->amount);
            $sI = $idx(Carbon::parse($c->spend_date)); $eI = $sI + $m - 1;
            if ($eI < $fromIdx || $sI > $toIdx) continue;   // không giao với kỳ
            $per = (int) round($amt / $m);
            $done = max(0, min($toIdx, $eI) - $sI + 1);   // số tháng đã phân bổ tính đến hết kỳ
            $alloc[] = [
                'id' => $c->id, 'hashid' => Hashid::encode($c->id), 'name' => $typeOf($c), 'detail' => $c->name ?? '', 'invoiceNo' => $c->invoice_no ?? '',
                'amount' => $amt, 'months' => $m, 'perMonth' => $per, 'doneMonths' => $done, 'remainMonths' => $m - $done,
                'remainAmount' => max(0, $amt - $per * $done),
                'inPeriod' => $per * max(0, min($toIdx, $eI) - max($fromIdx, $sI) + 1),   // phần rơi vào kỳ
                'from' => Carbon::parse($c->spend_date)->format('m/Y'), 'to' => Carbon::parse($c->spend_date)->addMonths($m - 1)->format('m/Y'),
            ];
        }
        usort($alloc, fn ($a, $b) => $b['perMonth'] <=> $a['perMonth']);

        // ---- Khoản định kỳ sắp đến hạn / quá hạn (thuê VP, internet, bảo hiểm…) trong 45 ngày tới — không phụ thuộc kỳ ----
        $due = [];
        foreach ($slips as $c) {
            if ($c->kind !== 'recurring' || ! $c->due_date) continue;
            $d = Carbon::parse($c->due_date)->startOfDay();
            $days = (int) $today->diffInDays($d, false);
            if ($days > 45) continue;
            $due[] = ['id' => $c->id, 'hashid' => Hashid::encode($c->id), 'name' => $typeOf($c), 'detail' => $c->name ?? '', 'supplier' => trim((string) $c->supplier),
                      'amount' => (int) round((float) $c->amount), 'dueDate' => $this->outDate($c->due_date), 'daysLeft' => $days];
        }
        usort($due, fn ($a, $b) => $a['daysLeft'] <=> $b['daysLeft']);

        // ---- Xu hướng 12 tháng (kết thúc ở tháng cuối kỳ) ----
        $trend = [];
        for ($i = $toIdx - 11; $i <= $toIdx; $i++) {
            $trend[] = ['ym' => $ymOf($i), 'label' => sprintf('%02d/%04d', $i % 12 + 1, intdiv($i, 12)),
                        'spent' => (int) ($spent[$i] ?? 0), 'accrual' => (int) ($accr[$i] ?? 0), 'count' => (int) ($cnt[$i] ?? 0), 'inPeriod' => $inRange($i)];
        }

        $prevFrom = $fromIdx - $months; $prevTo = $fromIdx - 1;   // kỳ trước = khoảng liền trước, cùng độ dài
        return [
            'from' => $ymOf($fromIdx), 'to' => $ymOf($toIdx), 'months' => $months,
            'spent'    => $rangeSpent,                              // khớp nhóm "Chi phí văn phòng" của P&L
            'accrual'  => $sumRange($accr, $fromIdx, $toIdx),      // đã tính phân bổ trả trước
            'count'    => $sumRange($cnt, $fromIdx, $toIdx),
            'prev'     => ['from' => $ymOf($prevFrom), 'to' => $ymOf($prevTo),
                           'spent' => $sumRange($spent, $prevFrom, $prevTo), 'accrual' => $sumRange($accr, $prevFrom, $prevTo), 'count' => $sumRange($cnt, $prevFrom, $prevTo)],
            'avg6'     => (int) round($sumRange($spent, $fromIdx - 6, $fromIdx - 1) / 6),   // TB MỖI THÁNG của 6 tháng trước kỳ — thấy kỳ này bất thường không
            'byType'   => $byType,
            'bySupplier' => $bySupplier,
            'slips'    => $list,
            'alloc'    => $alloc,
            'due'      => $due,
            'pending'  => $pending,
            'toPay'    => $toPay,
            'trend'    => $trend,
            'hasEntity' => $slips->isNotEmpty() || TruckingVehicle::where('kind', 'office')->exists(),
        ];
    }
}
