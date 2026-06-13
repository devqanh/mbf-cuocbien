<?php

namespace App\Console\Commands;

use App\Models\TruckingChohoItem;
use App\Models\TruckingContType;
use App\Models\TruckingCostItem;
use App\Models\TruckingCostLine;
use App\Models\TruckingCustomer;
use App\Models\TruckingDriver;
use App\Models\TruckingLocation;
use App\Models\TruckingPayer;
use App\Models\TruckingPayment;
use App\Models\TruckingPriceRow;
use App\Models\TruckingRevenueItem;
use App\Models\TruckingRevenueLine;
use App\Models\TruckingSetting;
use App\Models\TruckingShipment;
use App\Models\TruckingStatement;
use App\Models\TruckingStatementLine;
use App\Models\TruckingStatementPayment;
use App\Models\TruckingVehicle;
use App\Models\TruckingWarehouse;
use Illuminate\Console\Command;

/**
 * Xóa SẠCH toàn bộ dữ liệu Trucking v2 để test lại từ đầu.
 * KHÔNG đụng bảng cũ `trucking_entries` (bản Luckysheet).
 */
class TruckingClear extends Command
{
    protected $signature = 'trucking:clear {--force : Xóa luôn, không hỏi xác nhận}';

    protected $description = 'Xóa sạch dữ liệu Trucking v2 (lô hàng, bảng kê, bảng giá, danh mục, cấu hình) để test lại';

    /** Xóa theo thứ tự con → cha để an toàn khóa ngoại. */
    private array $order = [
        TruckingStatementPayment::class,
        TruckingStatementLine::class,
        TruckingStatement::class,
        TruckingPayment::class,
        TruckingRevenueLine::class,
        TruckingCostLine::class,
        TruckingShipment::class,
        TruckingPriceRow::class,
        TruckingCustomer::class,
        TruckingVehicle::class,
        TruckingLocation::class,
        TruckingPayer::class,
        TruckingDriver::class,
        TruckingContType::class,
        TruckingWarehouse::class,
        TruckingCostItem::class,
        TruckingChohoItem::class,
        TruckingRevenueItem::class,
        TruckingSetting::class,
    ];

    public function handle(): int
    {
        // Tổng quan trước khi xóa
        $counts = [];
        $total = 0;
        foreach ($this->order as $m) {
            $c = $m::query()->count();
            $counts[class_basename($m)] = $c;
            $total += $c;
        }

        if ($total === 0) {
            $this->info('Dữ liệu Trucking v2 đã trống — không có gì để xóa.');
            return self::SUCCESS;
        }

        $this->warn("Sắp xóa {$total} bản ghi Trucking v2:");
        foreach ($counts as $name => $c) {
            if ($c > 0) $this->line(sprintf('  %-26s %d', $name, $c));
        }

        if (! $this->option('force') && ! $this->confirm('Xóa sạch toàn bộ dữ liệu trên? Không thể hoàn tác.')) {
            $this->info('Đã hủy.');
            return self::SUCCESS;
        }

        foreach ($this->order as $m) {
            $m::query()->delete();
        }

        $this->info("Đã xóa sạch {$total} bản ghi. Dữ liệu Trucking v2 giờ trống.");

        return self::SUCCESS;
    }
}
