<?php

namespace App\Services\Trucking\Concerns;

use App\Models\TruckingVehicle;
use App\Support\Hashid;
use Carbon\Carbon;

/**
 * BÁO CÁO TÀI SẢN — thống kê theo TỪNG XE / TÀI SẢN trong khoảng THÁNG [from, to]:
 *  - Chi phí thường : phiếu chi KHÔNG phân bổ, theo Ngày chi rơi trong kỳ.
 *  - Chi phí phân bổ: phiếu chi phân bổ — cộng (Số tiền ÷ số tháng) cho mỗi THÁNG phân bổ rơi trong kỳ.
 *  - Khấu hao       : theo NGÀY = Nguyên giá ÷ (30 × số tháng) × số NGÀY của kỳ nằm trong kỳ khấu hao.
 * Tất cả đều "ĐÃ PHÁT SINH" — KHÔNG tính phần thuộc tương lai (chặn ở tháng/ngày hiện tại).
 */
trait HandlesAssetReport
{
    /** "YYYY-MM" → [năm, tháng]; sai định dạng → tháng hiện tại. */
    private function ymParts(?string $ym): array
    {
        if (preg_match('/^(\d{4})-(\d{1,2})$/', trim((string) $ym), $m)) {
            $mo = max(1, min(12, (int) $m[2]));
            return [(int) $m[1], $mo];
        }
        return [(int) now()->format('Y'), (int) now()->format('n')];
    }

    /**
     * Các NĂM có thể chọn ở báo cáo = từ năm có dữ liệu sớm nhất (phiếu chi / khấu hao) → năm hiện tại.
     * Lấy theo DỮ LIỆU THẬT nên danh sách luôn ngắn & không bao giờ thiếu năm cũ.
     */
    public function assetReportYears(): array
    {
        $nowY = (int) now()->format('Y');
        $minCost = \App\Models\TruckingVehicleCost::whereNull('cancelled_at')->min('spend_date');
        $minDep  = \App\Models\TruckingVehicleDepreciation::min('start_date');
        $years = [];
        foreach ([$minCost, $minDep] as $d) {
            if ($d) { try { $years[] = (int) Carbon::parse($d)->format('Y'); } catch (\Throwable) {} }
        }
        $min = $years ? min($years) : $nowY;
        $min = max(2000, min($min, $nowY));           // chặn năm rác
        return array_map('intval', range($nowY, $min));   // mới → cũ
    }

    public function assetReport(?string $fromYm, ?string $toYm): array
    {
        [$fy, $fm] = $this->ymParts($fromYm);
        [$ty, $tm] = $this->ymParts($toYm);
        $fromIdx = $fy * 12 + ($fm - 1);
        $toIdx   = $ty * 12 + ($tm - 1);
        if ($toIdx < $fromIdx) [$fromIdx, $toIdx] = [$toIdx, $fromIdx];

        $pStart = Carbon::create(intdiv($fromIdx, 12), $fromIdx % 12 + 1, 1)->startOfDay();
        $pEndEx = Carbon::create(intdiv($toIdx, 12), $toIdx % 12 + 1, 1)->addMonth()->startOfDay();   // exclusive
        $today  = Carbon::today();
        $nowIdx = (int) $today->format('Y') * 12 + ((int) $today->format('n') - 1);
        $capIdx = min($toIdx, $nowIdx);                                   // tháng cuối được tính (không tính tương lai)
        // Ngày cuối được tính (exclusive) = hết kỳ HOẶC hôm nay — dùng $today (không +1) để SỐ NGÀY khớp
        // đúng daysUsed của tab Khấu hao (số ngày ĐÃ TRÔI QUA, không tính ngày hôm nay).
        $capEnd = $pEndEx->min($today);

        $vehicles = TruckingVehicle::with([
            'vehicleCosts' => fn ($q) => $q->whereNull('cancelled_at'),
            'vehicleDepreciations',
        ])->orderBy('kind')->orderBy('plate')->get();

        $rows = [];
        foreach ($vehicles as $v) {
            $costNormal = 0; $costAlloc = 0; $deprec = 0.0;
            $costItems = []; $allocItems = []; $deprecItems = [];

            foreach ($v->vehicleCosts as $c) {
                $amt = (int) round((float) $c->amount);
                if ($c->alloc && (int) $c->alloc_months > 0 && $c->spend_date) {
                    // PHÂN BỔ: rải đều theo THÁNG kể từ tháng Ngày chi
                    $m  = (int) $c->alloc_months;
                    $sd = Carbon::parse($c->spend_date);
                    $sIdx = (int) $sd->format('Y') * 12 + ((int) $sd->format('n') - 1);
                    $lo = max($sIdx, $fromIdx);
                    $hi = min($sIdx + $m - 1, $capIdx);
                    $inMonths = max(0, $hi - $lo + 1);
                    if ($inMonths <= 0) continue;
                    $per = (int) round($amt / $m);
                    $sum = $per * $inMonths;
                    $costAlloc += $sum;
                    $allocItems[] = [
                        'name' => $c->name ?: '(chi phí)', 'invoiceNo' => $c->invoice_no ?? '',
                        'amount' => $amt, 'months' => $m, 'perMonth' => $per,
                        'monthsInPeriod' => $inMonths, 'inPeriod' => $sum,
                        'spendDate' => $this->outDate($c->spend_date),
                    ];
                    continue;
                }
                // THƯỜNG: tính theo Ngày chi rơi trong kỳ
                if (! $c->spend_date) continue;
                $sd = Carbon::parse($c->spend_date)->startOfDay();
                if ($sd->lt($pStart) || $sd->gte($pEndEx)) continue;
                $costNormal += $amt;
                $key = $c->name ?: '(chi phí)';
                if (! isset($costItems[$key])) $costItems[$key] = ['name' => $key, 'amount' => 0, 'count' => 0, 'material' => false];
                $costItems[$key]['amount'] += $amt;
                $costItems[$key]['count']++;
                if ($c->material) $costItems[$key]['material'] = true;
            }

            foreach ($v->vehicleDepreciations as $d) {
                $o = (float) $d->orig_price; $m = (int) $d->months;
                if ($o <= 0 || $m <= 0 || ! $d->start_date) continue;
                $ds = Carbon::parse($d->start_date)->startOfDay();
                $perDay  = $o / (30 * $m);
                $dEndEx  = $ds->copy()->addDays(30 * $m);                 // hết kỳ khấu hao (exclusive)
                $from = $ds->gt($pStart) ? $ds->copy() : $pStart->copy();  // giao [kỳ khấu hao] ∩ [kỳ báo cáo] ∩ [đến hôm nay]
                $to   = $dEndEx->lt($capEnd) ? $dEndEx->copy() : $capEnd->copy();
                $days = $from->lt($to) ? $from->diffInDays($to) : 0;
                if ($days <= 0) continue;
                $amt = $perDay * $days;
                $deprec += $amt;
                $deprecItems[] = [
                    'name' => $d->name ?: '(khấu hao)', 'origPrice' => (int) round($o), 'months' => $m,
                    'perDay' => (int) round($perDay), 'daysInPeriod' => $days, 'inPeriod' => (int) round($amt),
                    'startDate' => $this->outDate($d->start_date),
                ];
            }

            $deprecI = (int) round($deprec);
            $total = $costNormal + $costAlloc + $deprecI;
            if ($total === 0 && ! $costItems && ! $allocItems && ! $deprecItems) continue;   // xe không phát sinh → bỏ

            usort($allocItems, fn ($a, $b) => $b['inPeriod'] <=> $a['inPeriod']);
            usort($deprecItems, fn ($a, $b) => $b['inPeriod'] <=> $a['inPeriod']);
            $ci = array_values($costItems);
            usort($ci, fn ($a, $b) => $b['amount'] <=> $a['amount']);

            $rows[] = [
                'id' => $v->id, 'hashid' => Hashid::encode($v->id),
                'plate' => $v->plate, 'kind' => $v->kind === 'asset' ? 'asset' : 'vehicle',
                'costNormal' => $costNormal, 'costAlloc' => $costAlloc, 'deprec' => $deprecI, 'total' => $total,
                'costItems' => $ci, 'allocItems' => $allocItems, 'deprecItems' => $deprecItems,
            ];
        }

        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);
        $sum = fn (string $k) => array_sum(array_column($rows, $k));

        return [
            'from' => sprintf('%04d-%02d', intdiv($fromIdx, 12), $fromIdx % 12 + 1),
            'to'   => sprintf('%04d-%02d', intdiv($toIdx, 12), $toIdx % 12 + 1),
            'months' => $toIdx - $fromIdx + 1,
            'rows' => $rows,
            'totals' => [
                'costNormal' => $sum('costNormal'), 'costAlloc' => $sum('costAlloc'),
                'deprec' => $sum('deprec'), 'total' => $sum('total'), 'vehicles' => count($rows),
            ],
        ];
    }
}
