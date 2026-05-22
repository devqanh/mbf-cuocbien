<?php

namespace App\Services;

use App\Exceptions\Domain\BusinessRuleException;
use App\Models\PayableInitialBalance;
use App\Models\PayableReport;
use App\Models\PayableReportLine;
use App\Models\Shipment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayableReportService
{
    /** Danh sách báo cáo, mới nhất trên cùng. */
    public function listReports(): Collection
    {
        return PayableReport::with('creator:id,name')
            ->withCount('lines')
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->get();
    }

    /** Tất cả NCC có trong shipments hoặc trong initial balances — distinct, sắp xếp A-Z. */
    public function availableSuppliers(): Collection
    {
        $fromShipments = Shipment::query()
            ->whereNotNull('supplier')->where('supplier', '!=', '')
            ->distinct()->orderBy('supplier')->pluck('supplier');

        $fromInits = PayableInitialBalance::pluck('supplier');

        return $fromShipments->merge($fromInits)->unique()->sort()->values();
    }

    /** Các ngày `report_close_date_increase` đã có trong shipments — cho dropdown chọn. */
    public function availableIncreaseDates(): Collection
    {
        return Shipment::query()
            ->whereNotNull('report_close_date_increase')
            ->distinct()->orderBy('report_close_date_increase', 'desc')
            ->pluck('report_close_date_increase')
            ->map(fn ($d) => $d->format('Y-m-d'));
    }

    public function availableDecreaseDates(): Collection
    {
        return Shipment::query()
            ->whereNotNull('report_close_date_decrease')
            ->distinct()->orderBy('report_close_date_decrease', 'desc')
            ->pluck('report_close_date_decrease')
            ->map(fn ($d) => $d->format('Y-m-d'));
    }

    // ---------- Initial balances ----------

    public function listInitialBalances(): Collection
    {
        return PayableInitialBalance::orderBy('supplier')->get();
    }

    public function setInitialBalance(string $supplier, float $amount, ?string $asOfDate, ?string $note, ?int $userId): PayableInitialBalance
    {
        return PayableInitialBalance::updateOrCreate(
            ['supplier' => $supplier],
            [
                'opening_amount' => $amount,
                'as_of_date'     => $asOfDate ?: null,
                'note'           => $note ?: null,
                'updated_by'     => $userId,
            ]
        );
    }

    public function deleteInitialBalance(PayableInitialBalance $balance): void
    {
        $balance->delete();
    }

    // ---------- Generate report ----------

    /**
     * Tạo báo cáo phải trả mới.
     * - Đầu kỳ = closing của báo cáo gần nhất TRƯỚC reportDate (theo từng NCC)
     *           nếu không có → initial_balance config || 0
     * - Phát sinh tăng = SUM(payment_amount) WHERE supplier=NCC AND report_close_date_increase=increaseDate
     * - Phát sinh giảm = tương tự với decreaseDate
     * - Cuối kỳ = đầu + tăng - giảm
     */
    public function generate(
        string $reportDate,
        ?string $increaseDate,
        ?string $decreaseDate,
        ?string $note,
        ?int $userId,
    ): PayableReport {
        if (! $increaseDate && ! $decreaseDate) {
            throw new BusinessRuleException('Phải chọn ít nhất 1 ngày chốt (tăng hoặc giảm).');
        }

        return DB::transaction(function () use ($reportDate, $increaseDate, $decreaseDate, $note, $userId) {
            $report = PayableReport::create([
                'report_date'   => $reportDate,
                'increase_date' => $increaseDate,
                'decrease_date' => $decreaseDate,
                'note'          => $note,
                'created_by'    => $userId,
            ]);

            // Tính từng NCC
            $suppliers = $this->relevantSuppliers($increaseDate, $decreaseDate);

            foreach ($suppliers as $supplier) {
                $opening  = $this->openingFor($supplier, $reportDate);
                $increase = $increaseDate
                    ? (float) Shipment::query()
                        ->where('supplier', $supplier)
                        ->whereDate('report_close_date_increase', $increaseDate)
                        ->sum('payment_amount')
                    : 0.0;
                $decrease = $decreaseDate
                    ? (float) Shipment::query()
                        ->where('supplier', $supplier)
                        ->whereDate('report_close_date_decrease', $decreaseDate)
                        ->sum('payment_amount')
                    : 0.0;
                $closing  = $opening + $increase - $decrease;

                PayableReportLine::create([
                    'report_id'       => $report->id,
                    'supplier'        => $supplier,
                    'opening_balance' => $opening,
                    'increase_amount' => $increase,
                    'decrease_amount' => $decrease,
                    'closing_balance' => $closing,
                ]);
            }

            return $report->load('lines');
        });
    }

    /**
     * Đầu kỳ cho 1 NCC tại thời điểm reportDate:
     *  1. Tìm báo cáo gần nhất TRƯỚC reportDate có dòng cho NCC này
     *     → dùng closing_balance của dòng đó
     *  2. Không có → dùng initial_balance config
     *  3. Cũng không có → 0
     */
    public function openingFor(string $supplier, string $reportDate): float
    {
        $prevLine = PayableReportLine::query()
            ->join('payable_reports as r', 'r.id', '=', 'payable_report_lines.report_id')
            ->where('payable_report_lines.supplier', $supplier)
            ->whereDate('r.report_date', '<', $reportDate)
            ->orderByDesc('r.report_date')->orderByDesc('r.id')
            ->select('payable_report_lines.closing_balance')
            ->first();

        if ($prevLine) {
            return (float) $prevLine->closing_balance;
        }

        $init = PayableInitialBalance::where('supplier', $supplier)->value('opening_amount');
        return $init !== null ? (float) $init : 0.0;
    }

    /**
     * NCC liên quan đến báo cáo này:
     * - Có shipment chốt vào increaseDate hoặc decreaseDate
     * - HOẶC có initial_balance (để vẫn báo cáo dù tháng này không phát sinh)
     */
    private function relevantSuppliers(?string $increaseDate, ?string $decreaseDate): Collection
    {
        $shipmentSuppliers = Shipment::query()
            ->where(function ($q) use ($increaseDate, $decreaseDate) {
                if ($increaseDate) $q->orWhereDate('report_close_date_increase', $increaseDate);
                if ($decreaseDate) $q->orWhereDate('report_close_date_decrease', $decreaseDate);
            })
            ->whereNotNull('supplier')->where('supplier', '!=', '')
            ->distinct()->pluck('supplier');

        $initSuppliers = PayableInitialBalance::pluck('supplier');

        return $shipmentSuppliers->merge($initSuppliers)->unique()->sort()->values();
    }

    public function delete(PayableReport $report): void
    {
        $report->delete();   // cascade xoá lines
    }

    /** Báo cáo gần nhất trước báo cáo đang xem — dùng để so sánh. */
    public function previousReport(PayableReport $report): ?PayableReport
    {
        return PayableReport::with('lines')
            ->whereDate('report_date', '<', $report->report_date)
            ->orWhere(function ($q) use ($report) {
                $q->whereDate('report_date', $report->report_date)
                  ->where('id', '<', $report->id);
            })
            ->orderByDesc('report_date')->orderByDesc('id')
            ->first();
    }
}
