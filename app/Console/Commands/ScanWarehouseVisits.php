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

        $this->info("Đã quét vị trí GPS để cập nhật lịch sử xe đến/rời kho:");
        $this->line("  • {$r['positions']} xe có tín hiệu GPS");
        $this->line("  • {$r['opened']} xe vừa ĐẾN kho (tạo lượt ghé mới)");
        $this->line("  • {$r['closed']} xe đã RỜI kho (đóng lượt ghé)");
        $this->line("  • {$r['updated']} xe ĐANG ở kho (cập nhật giờ còn ở — cùng 1 lượt, không tạo mới)");
        $this->line("  • {$r['dropped']} bỏ qua (xe chỉ tạt ngang, chưa đủ thời gian ở kho)");
        return self::SUCCESS;
    }
}
