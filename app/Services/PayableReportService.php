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
    /** Memoize per-request — service được resolve fresh mỗi request nên cache an toàn. */
    private ?Collection $cachedSuppliers = null;
    private ?Collection $cachedIncreaseDates = null;
    private ?Collection $cachedDecreaseDates = null;

    /** Danh sách báo cáo, mới nhất trên cùng — paginate để không load toàn bộ. */
    public function listReports(int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return PayableReport::with('creator:id,name')
            ->withCount('lines')
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Tất cả NCC có trong shipments hoặc trong initial balances — distinct, sắp xếp A-Z. */
    public function availableSuppliers(): Collection
    {
        if ($this->cachedSuppliers !== null) return $this->cachedSuppliers;

        $fromShipments = Shipment::query()
            ->whereNotNull('supplier')->where('supplier', '!=', '')
            ->distinct()->orderBy('supplier')->pluck('supplier');

        $fromInits = PayableInitialBalance::pluck('supplier');

        return $this->cachedSuppliers = $fromShipments->merge($fromInits)->unique()->sort()->values();
    }

    /** Các ngày `report_close_date_increase` đã có trong shipments — cho dropdown chọn. */
    public function availableIncreaseDates(): Collection
    {
        if ($this->cachedIncreaseDates !== null) return $this->cachedIncreaseDates;

        return $this->cachedIncreaseDates = Shipment::query()
            ->whereNotNull('report_close_date_increase')
            ->distinct()->orderBy('report_close_date_increase', 'desc')
            ->pluck('report_close_date_increase')
            ->map(fn ($d) => $d->format('Y-m-d'));
    }

    public function availableDecreaseDates(): Collection
    {
        if ($this->cachedDecreaseDates !== null) return $this->cachedDecreaseDates;

        return $this->cachedDecreaseDates = Shipment::query()
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
     *
     * Tối ưu: 4 query tổng cho mọi NCC thay vì 3N (N = số NCC):
     *   1× GROUP BY tăng + 1× GROUP BY giảm + 1× batched opening + 1× batched initial.
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

            $suppliers = $this->relevantSuppliers($increaseDate, $decreaseDate);
            if ($suppliers->isEmpty()) {
                return $report->load('lines');
            }
            $supplierList = $suppliers->all();

            // 1 GROUP BY query — tổng tăng cho TẤT CẢ NCC
            $increaseMap = $increaseDate
                ? Shipment::query()
                    ->whereIn('supplier', $supplierList)
                    ->where('report_close_date_increase', $increaseDate)
                    ->groupBy('supplier')
                    ->selectRaw('supplier, SUM(payment_amount) as total')
                    ->pluck('total', 'supplier')->all()
                : [];

            // 1 GROUP BY query — tổng giảm cho TẤT CẢ NCC
            $decreaseMap = $decreaseDate
                ? Shipment::query()
                    ->whereIn('supplier', $supplierList)
                    ->where('report_close_date_decrease', $decreaseDate)
                    ->groupBy('supplier')
                    ->selectRaw('supplier, SUM(payment_amount) as total')
                    ->pluck('total', 'supplier')->all()
                : [];

            // 1 query — initial balances (Phase 2.2: preload thay N query lẻ)
            $initialMap = PayableInitialBalance::whereIn('supplier', $supplierList)
                ->pluck('opening_amount', 'supplier')->all();

            // 1 query — opening balances (closing của report gần nhất TRƯỚC reportDate cho từng NCC)
            $openingMap = $this->batchedOpeningBalances($supplierList, $reportDate);

            // Tính + batch insert tất cả lines
            $now = now()->format('Y-m-d H:i:s');
            $lines = [];
            foreach ($supplierList as $supplier) {
                $opening = isset($openingMap[$supplier])
                    ? $openingMap[$supplier]
                    : (float) ($initialMap[$supplier] ?? 0);
                $increase = (float) ($increaseMap[$supplier] ?? 0);
                $decrease = (float) ($decreaseMap[$supplier] ?? 0);
                $closing  = $opening + $increase - $decrease;

                $lines[] = [
                    'report_id'       => $report->id,
                    'supplier'        => $supplier,
                    'opening_balance' => $opening,
                    'increase_amount' => $increase,
                    'decrease_amount' => $decrease,
                    'closing_balance' => $closing,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }

            if (! empty($lines)) {
                PayableReportLine::insert($lines);
            }

            return $report->load('lines');
        });
    }

    /**
     * Đầu kỳ cho 1 NCC tại thời điểm reportDate. Giữ lại cho ad-hoc lookup ngoài generate().
     * Cho batch (gen báo cáo) dùng batchedOpeningBalances().
     */
    public function openingFor(string $supplier, string $reportDate): float
    {
        $prevLine = PayableReportLine::query()
            ->join('payable_reports as r', 'r.id', '=', 'payable_report_lines.report_id')
            ->where('payable_report_lines.supplier', $supplier)
            ->where('r.report_date', '<', $reportDate)
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
     * Batched opening balances: 1 query lấy latest closing/NCC từ các report cũ hơn.
     * Đã ordered DESC nên line đầu gặp/supplier = latest closing.
     */
    private function batchedOpeningBalances(array $suppliers, string $reportDate): array
    {
        if (empty($suppliers)) return [];

        $allPrevLines = DB::table('payable_report_lines as l')
            ->join('payable_reports as r', 'r.id', '=', 'l.report_id')
            ->whereIn('l.supplier', $suppliers)
            ->where('r.report_date', '<', $reportDate)
            ->select('l.supplier', 'l.closing_balance')
            ->orderByDesc('r.report_date')
            ->orderByDesc('r.id')
            ->get();

        $map = [];
        foreach ($allPrevLines as $line) {
            if (! isset($map[$line->supplier])) {
                $map[$line->supplier] = (float) $line->closing_balance;
            }
        }
        return $map;
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
                if ($increaseDate) $q->orWhere('report_close_date_increase', $increaseDate);
                if ($decreaseDate) $q->orWhere('report_close_date_decrease', $decreaseDate);
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
            ->where('report_date', '<', $report->report_date)
            ->orWhere(function ($q) use ($report) {
                $q->where('report_date', $report->report_date)
                  ->where('id', '<', $report->id);
            })
            ->orderByDesc('report_date')->orderByDesc('id')
            ->first();
    }
}
