<?php

namespace App\Console\Commands;

use App\Models\TruckingShipment;
use App\Services\TruckingV2Service;
use Illuminate\Console\Command;

/**
 * Backfill số liệu + tham chiếu báo cáo cho lô hàng đã có
 * (tổng tiền mức lô, cost_item_id/payer_id/item_id, vehicle_id/location_id, kho theo lô).
 *   php artisan trucking:recompute-derived
 */
class TruckingRecomputeDerived extends Command
{
    protected $signature = 'trucking:recompute-derived';
    protected $description = 'Tính lại số liệu/tham chiếu báo cáo cho tất cả lô hàng';

    public function handle(TruckingV2Service $svc): int
    {
        $n = TruckingShipment::count();
        $this->info("Backfill {$n} lô…");
        $bar = $this->output->createProgressBar($n);
        TruckingShipment::with(['costLines', 'revenueLines', 'payments'])->chunkById(200, function ($chunk) use ($svc, $bar) {
            foreach ($chunk as $s) { $svc->recomputeShipmentDerived($s); $bar->advance(); }
        });
        $bar->finish();
        $this->newLine();
        $this->info('✓ Xong.');
        return self::SUCCESS;
    }
}
