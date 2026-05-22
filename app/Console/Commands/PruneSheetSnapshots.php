<?php

namespace App\Console\Commands;

use App\Models\SheetSnapshotHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneSheetSnapshots extends Command
{
    protected $signature = 'snapshots:prune
                            {--days=30 : Xoá history cũ hơn N ngày (default 30)}
                            {--keep=10 : Giữ tối thiểu N version gần nhất per key (default 10)}
                            {--optimize : Chạy OPTIMIZE TABLE sau khi prune để reclaim space}';

    protected $description = 'Prune snapshot history cũ. Giữ tối thiểu N version gần nhất per key, xoá phần còn lại quá hạn.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $keep = (int) $this->option('keep');
        $cutoff = now()->subDays($days);

        $this->info("Pruning snapshot history cũ hơn {$days} ngày (giữ tối thiểu {$keep} version/key)…");

        $totalDeleted = 0;
        $keys = SheetSnapshotHistory::query()->distinct()->pluck('snapshot_key');

        foreach ($keys as $key) {
            // ID của N version gần nhất → giữ lại bất kể tuổi
            $keepIds = SheetSnapshotHistory::where('snapshot_key', $key)
                ->orderByDesc('version')
                ->limit($keep)
                ->pluck('id')
                ->all();

            $deleted = SheetSnapshotHistory::where('snapshot_key', $key)
                ->where('created_at', '<', $cutoff)
                ->whereNotIn('id', $keepIds)
                ->delete();

            $totalDeleted += $deleted;
            if ($deleted > 0) {
                $this->line("  • {$key}: xoá {$deleted} version");
            }
        }

        $this->info("✔ Đã xoá tổng {$totalDeleted} version.");

        if ($this->option('optimize')) {
            $this->info('Chạy OPTIMIZE TABLE để reclaim InnoDB space…');
            DB::statement('OPTIMIZE TABLE sheet_snapshots');
            DB::statement('OPTIMIZE TABLE sheet_snapshot_history');
            $this->info('✔ OPTIMIZE xong.');
        }

        return self::SUCCESS;
    }
}
