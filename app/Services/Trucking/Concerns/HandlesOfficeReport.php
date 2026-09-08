<?php

namespace App\Services\Trucking\Concerns;

use App\Models\TruckingOfficeCostType;
use App\Models\TruckingVehicleCost;
use App\Support\Hashid;
use Carbon\Carbon;

/**
 * BÁO CÁO CHI PHÍ VĂN PHÒNG (chi phí quản lý doanh nghiệp) — tab "Văn phòng" ở /bao-cao.
 *
 * Nguồn: phiếu chi của trung tâm chi phí kind='office' (trucking_vehicle_costs), bỏ phiếu đã hủy.
 * Hai cách nhìn, ghi rõ để không lẫn:
 *  - "Chi tiêu" (spent): theo NGÀY CHI, nguyên số tiền — KHỚP với nhóm "Chi phí văn phòng" trên P&L tháng.
 *  - "Ghi nhận" (accrual): phiếu thường theo ngày chi + phần PHÂN BỔ của khoản trả trước rơi vào tháng
 *    (bảo hiểm 12 tháng, thuê VP trả trước…) — đúng bản chất chi phí của tháng để so sánh tháng với tháng.
 */
trait HandlesOfficeReport
{
    public function officeReport(int $year, int $month): array
    {
        $cur  = Carbon::create($year, $month, 1)->startOfMonth();
        $idx  = fn (Carbon $d) => (int) $d->format('Y') * 12 + ((int) $d->format('n') - 1);   // chỉ số tháng liên tục
        $curI = $idx($cur);
        $today = Carbon::today();
        $typeName = TruckingOfficeCostType::pluck('name', 'id');

        // Toàn bộ phiếu văn phòng (bảng nhỏ) → tính tháng này, tháng trước, xu hướng 12 tháng trong 1 lượt.
        $slips = TruckingVehicleCost::with('vehicle:id,kind')
            ->whereHas('vehicle', fn ($q) => $q->where('kind', 'office'))
            ->whereNull('cancelled_at')->orderBy('spend_date')->orderBy('sort')->get();

        $typeOf = fn (TruckingVehicleCost $c) => ($c->cost_type_id ? ($typeName[$c->cost_type_id] ?? null) : null) ?: (trim((string) $c->name) ?: 'Khác');

        // ---- Gom theo tháng: spent (ngày chi) + accrual (thường theo ngày chi, phân bổ rải đều) ----
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

        // ---- Tháng này: theo loại, theo nhà cung cấp, danh sách phiếu ----
        $byType = []; $bySup = []; $list = [];
        $pending = ['count' => 0, 'amount' => 0]; $toPay = ['count' => 0, 'amount' => 0];
        foreach ($slips as $c) {
            $amt = (int) round((float) $c->amount);
            // Chờ xử lý — đếm toàn bộ, không theo kỳ (kế toán cần biết còn bao nhiêu phiếu treo)
            if (! $c->approved) { $pending['count']++; $pending['amount'] += $amt; }
            elseif (! $c->paid) { $toPay['count']++; $toPay['amount'] += $amt; }

            if (! $c->spend_date || $idx(Carbon::parse($c->spend_date)) !== $curI) continue;
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
        $monthSpent = (int) ($spent[$curI] ?? 0);
        $byType = array_values($byType);
        usort($byType, fn ($a, $b) => $b['amount'] <=> $a['amount']);
        foreach ($byType as &$t) $t['pct'] = $monthSpent ? round($t['amount'] * 100 / $monthSpent, 1) : 0;
        unset($t);
        arsort($bySup);
        $bySupplier = [];
        foreach (array_slice($bySup, 0, 8, true) as $k => $v) $bySupplier[] = ['label' => $k, 'amount' => $v];
        usort($list, fn ($a, $b) => strcmp($b['spendDate'], $a['spendDate']) ?: ($b['amount'] <=> $a['amount']));

        // ---- Khoản trả trước đang phân bổ TRONG tháng này ----
        $alloc = [];
        foreach ($slips as $c) {
            if (! ($c->alloc && (int) $c->alloc_months > 0 && $c->spend_date)) continue;
            $m = (int) $c->alloc_months; $amt = (int) round((float) $c->amount);
            $sI = $idx(Carbon::parse($c->spend_date)); $eI = $sI + $m - 1;
            if ($curI < $sI || $curI > $eI) continue;
            $per = (int) round($amt / $m);
            $done = $curI - $sI + 1;   // số tháng đã phân bổ tính đến hết tháng này
            $alloc[] = [
                'id' => $c->id, 'hashid' => Hashid::encode($c->id), 'name' => $typeOf($c), 'detail' => $c->name ?? '', 'invoiceNo' => $c->invoice_no ?? '',
                'amount' => $amt, 'months' => $m, 'perMonth' => $per, 'doneMonths' => $done, 'remainMonths' => $m - $done,
                'remainAmount' => max(0, $amt - $per * $done),
                'from' => Carbon::parse($c->spend_date)->format('m/Y'), 'to' => Carbon::parse($c->spend_date)->addMonths($m - 1)->format('m/Y'),
            ];
        }
        usort($alloc, fn ($a, $b) => $b['perMonth'] <=> $a['perMonth']);

        // ---- Khoản định kỳ sắp đến hạn / quá hạn (thuê VP, internet, bảo hiểm…) trong 45 ngày tới ----
        $due = [];
        foreach ($slips as $c) {
            if ($c->kind !== 'recurring' || ! $c->due_date) continue;
            $d = Carbon::parse($c->due_date)->startOfDay();
            $days = $today->diffInDays($d, false);
            if ($days > 45) continue;
            $due[] = ['id' => $c->id, 'hashid' => Hashid::encode($c->id), 'name' => $typeOf($c), 'detail' => $c->name ?? '', 'supplier' => trim((string) $c->supplier),
                      'amount' => (int) round((float) $c->amount), 'dueDate' => $this->outDate($c->due_date), 'daysLeft' => (int) $days];
        }
        usort($due, fn ($a, $b) => $a['daysLeft'] <=> $b['daysLeft']);

        // ---- Xu hướng 12 tháng (kết thúc ở tháng đang xem) ----
        $trend = [];
        for ($i = $curI - 11; $i <= $curI; $i++) {
            $d = Carbon::create(intdiv($i, 12), $i % 12 + 1, 1);
            $trend[] = ['ym' => $d->format('Y-m'), 'label' => $d->format('m/Y'), 'spent' => (int) ($spent[$i] ?? 0), 'accrual' => (int) ($accr[$i] ?? 0), 'count' => (int) ($cnt[$i] ?? 0)];
        }

        return [
            'spent'    => $monthSpent,                       // khớp nhóm "Chi phí văn phòng" của P&L
            'accrual'  => (int) ($accr[$curI] ?? 0),         // đã tính phân bổ trả trước
            'count'    => (int) ($cnt[$curI] ?? 0),
            'prev'     => ['spent' => (int) ($spent[$curI - 1] ?? 0), 'accrual' => (int) ($accr[$curI - 1] ?? 0), 'count' => (int) ($cnt[$curI - 1] ?? 0)],
            'avg6'     => (int) round(array_sum(array_map(fn ($i) => $spent[$i] ?? 0, range($curI - 6, $curI - 1))) / 6),   // TB 6 tháng trước — để thấy tháng này bất thường không
            'byType'   => $byType,
            'bySupplier' => $bySupplier,
            'slips'    => $list,
            'alloc'    => $alloc,
            'due'      => $due,
            'pending'  => $pending,
            'toPay'    => $toPay,
            'trend'    => $trend,
            'hasEntity' => $slips->isNotEmpty() || \App\Models\TruckingVehicle::where('kind', 'office')->exists(),
        ];
    }
}
