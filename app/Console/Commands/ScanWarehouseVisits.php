<?php

namespace App\Console\Commands;

use App\Services\Gps\GpsTrackingService;
use Illuminate\Console\Command;

/**
 * Quét vị trí GPS (mọi nguồn) → ghi lịch sử xe ĐẾN/RỜI kho (geofence).
 * Lịch: mỗi 5 phút (routes/console.php). Cần cron `php artisan schedule:run` mỗi phút.
 */
class ScanWarehouseVisits extends Command
{
    protected $signature = 'trucking:scan-warehouse-visits';
    protected $description = 'Quét GPS, ghi lịch sử xe đến/rời kho (geofence visit)';

    public function handle(GpsTrackingService $gps): int
    {
        $r = $gps->scanVisits();
        if (empty($r['ok'])) { $this->warn('Bỏ qua: ' . ($r['reason'] ?? 'không rõ')); return self::SUCCESS; }
        $this->info("Quét {$r['positions']} xe · mở {$r['opened']} · đóng {$r['closed']} · cập nhật {$r['updated']} · bỏ (tạt ngang) {$r['dropped']}");
        return self::SUCCESS;
    }
}
