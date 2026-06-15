<?php

namespace App\Console\Commands;

use App\Models\TruckingAttachment;
use App\Models\TruckingCostLine;
use App\Models\TruckingPayment;
use App\Models\TruckingRevenueLine;
use App\Models\TruckingShipment;
use App\Models\TruckingShipmentWarehouse;
use App\Models\TruckingStatement;
use App\Models\TruckingStatementLine;
use App\Models\TruckingStatementPayment;
use App\Models\TruckingTripCostBatch;
use App\Models\TruckingTripCostLine;
use Illuminate\Console\Command;

/**
 * Xóa dữ liệu NGHIỆP VỤ Trucking v2 để test lại: LÔ HÀNG + BẢNG KÊ + PHÍ XE.
 * GIỮ NGUYÊN: danh mục & cấu hình (Cài đặt: địa điểm, khách hàng, đội xe, payers,
 * lái xe, loại cont, kho, khoản chi/thu, cấu hình…) + BẢNG GIÁ (price rows).
 * KHÔNG đụng bảng cũ `trucking_entries` (Luckysheet).
 */
class TruckingClear extends Command
{
    protected $signature = 'trucking:clear {--force : Xóa luôn, không hỏi xác nhận}';

    protected $description = 'Xóa dữ liệu nghiệp vụ Trucking v2 (LÔ HÀNG + BẢNG KÊ + PHÍ XE). GIỮ danh mục/cấu hình (Cài đặt) + bảng giá';

    /** Xóa theo thứ tự con → cha để an toàn khóa ngoại. CHỈ bảng nghiệp vụ. */
    private array $order = [
        // Bảng kê
        TruckingStatementPayment::class,
        TruckingStatementLine::class,
        TruckingStatement::class,
        // Phí xe nội bộ (kỳ/snapshot)
        TruckingTripCostLine::class,
        TruckingTripCostBatch::class,
        // Lô hàng (con → cha)
        TruckingPayment::class,
        TruckingRevenueLine::class,
        TruckingCostLine::class,
        TruckingShipmentWarehouse::class,
        TruckingShipment::class,
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
        // Ảnh lô (đính qua bảng attachments polymorphic, owner = lô hàng) — xóa kèm để khỏi mồ côi
        $shipAtt = TruckingAttachment::where('owner_type', TruckingShipment::class)->count();
        $counts['Attachment (ảnh lô)'] = $shipAtt;
        $total += $shipAtt;

        if ($total === 0) {
            $this->info('Dữ liệu nghiệp vụ Trucking v2 đã trống — không có gì để xóa.');
            return self::SUCCESS;
        }

        $this->warn("Sắp xóa {$total} bản ghi NGHIỆP VỤ (lô hàng + bảng kê + phí xe):");
        foreach ($counts as $name => $c) {
            if ($c > 0) $this->line(sprintf('  %-26s %d', $name, $c));
        }
        $this->info('GIỮ NGUYÊN: danh mục & cấu hình (Cài đặt) + bảng giá.');

        if (! $this->option('force') && ! $this->confirm('Xóa các dữ liệu nghiệp vụ trên? Không thể hoàn tác.')) {
            $this->info('Đã hủy.');
            return self::SUCCESS;
        }

        foreach ($this->order as $m) {
            $m::query()->delete();
        }
        TruckingAttachment::where('owner_type', TruckingShipment::class)->delete();

        $this->info("Đã xóa {$total} bản ghi nghiệp vụ. Danh mục/cấu hình + bảng giá được giữ nguyên.");

        return self::SUCCESS;
    }
}
