<?php

namespace App\Console\Commands;

use App\Models\TruckingAttachment;
use App\Models\TruckingCostLine;
use App\Models\TruckingPayment;
use App\Models\TruckingRevenueLine;
use App\Models\TruckingShipment;
use App\Models\TruckingShipmentWarehouse;
use Illuminate\Console\Command;

/**
 * Xóa CHỈ lô hàng (và dữ liệu con: chi phí, doanh thu, thanh toán, kho, ảnh).
 * KHÔNG đụng bảng kê, phí xe, danh mục, cấu hình, bảng giá, đội xe.
 */
class TruckingEmptyShipments extends Command
{
    protected $signature = 'trucking:empty-shipments {--force : Xóa luôn, không hỏi xác nhận}';

    protected $description = 'Xóa toàn bộ lô hàng (shipments). Giữ nguyên bảng kê, phí xe, danh mục, cấu hình, bảng giá';

    public function handle(): int
    {
        $counts = [
            'TruckingPayment'            => TruckingPayment::query()->count(),
            'TruckingRevenueLine'        => TruckingRevenueLine::query()->count(),
            'TruckingCostLine'           => TruckingCostLine::query()->count(),
            'TruckingShipmentWarehouse'  => TruckingShipmentWarehouse::query()->count(),
            'TruckingShipment'           => TruckingShipment::query()->count(),
        ];
        $shipAtt = TruckingAttachment::where('owner_type', TruckingShipment::class)->count();
        $counts['Attachment (ảnh lô)'] = $shipAtt;

        $total = array_sum($counts);

        if ($total === 0) {
            $this->info('Không có lô hàng nào — đã trống sẵn.');
            return self::SUCCESS;
        }

        $this->warn("Sắp xóa {$total} bản ghi LÔ HÀNG:");
        foreach ($counts as $name => $c) {
            if ($c > 0) $this->line(sprintf('  %-28s %d', $name, $c));
        }
        $this->info('GIỮ NGUYÊN: bảng kê, phí xe, danh mục, cấu hình, bảng giá, đội xe.');

        if (! $this->option('force') && ! $this->confirm('Xóa toàn bộ lô hàng? Không thể hoàn tác.')) {
            $this->info('Đã hủy.');
            return self::SUCCESS;
        }

        // Xóa con → cha
        TruckingPayment::query()->delete();
        TruckingRevenueLine::query()->delete();
        TruckingCostLine::query()->delete();
        TruckingShipmentWarehouse::query()->delete();
        TruckingAttachment::where('owner_type', TruckingShipment::class)->delete();
        TruckingShipment::query()->delete();

        $this->info("Đã xóa {$total} bản ghi lô hàng. Mọi thứ khác giữ nguyên.");

        return self::SUCCESS;
    }
}
