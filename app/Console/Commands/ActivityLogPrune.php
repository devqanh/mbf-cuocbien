<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\TruckingSetting;
use Illuminate\Console\Command;

/** Xóa nhật ký thao tác cũ hơn thời hạn cấu hình (sys.activity_retention_days, 0 = giữ vĩnh viễn). */
class ActivityLogPrune extends Command
{
    protected $signature = 'activity:prune {--days= : Ghi đè số ngày giữ}';

    protected $description = 'Xóa nhật ký thao tác quá thời hạn lưu giữ';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? TruckingSetting::get('sys.activity_retention_days', 365));
        if ($days <= 0) {
            $this->info('Thời hạn = 0 → giữ vĩnh viễn, không xóa.');
            return self::SUCCESS;
        }
        $cut = now()->subDays($days);
        $total = 0;
        // Xóa theo lô nhỏ → không khóa bảng lâu khi tồn nhiều dòng.
        do {
            $ids = ActivityLog::where('created_at', '<', $cut)->orderBy('id')->limit(5000)->pluck('id');
            if ($ids->isNotEmpty()) $total += ActivityLog::whereIn('id', $ids)->delete();
        } while ($ids->count() === 5000);
        $this->info("Đã xóa {$total} dòng nhật ký trước {$cut->format('Y-m-d')} (giữ {$days} ngày).");
        return self::SUCCESS;
    }
}
