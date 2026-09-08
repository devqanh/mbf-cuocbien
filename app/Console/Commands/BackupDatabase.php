<?php

namespace App\Console\Commands;

use App\Models\TruckingSetting;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Sao lưu MySQL ra file .sql.gz trong storage/app/backups.
 * - Xoay vòng: chỉ giữ N bản gần nhất (mặc định 15), xóa phần dư.
 * - Ghi "báo cáo" lần chạy gần nhất vào TruckingSetting (sys.backup_last_run)
 *   để trang Cài đặt hệ thống hiển thị trạng thái + thời gian.
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--keep=15 : Số bản sao lưu giữ lại}';
    protected $description = 'Sao lưu MySQL ra file gzip, xoay vòng giữ N bản gần nhất, ghi báo cáo';

    public function handle(): int
    {
        $started = microtime(true);
        $conn = config('database.connections.mysql');
        $dir  = storage_path('app/backups');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = $conn['database'] . '_' . now()->format('Y_m_d_His') . '.sql.gz';
        $path = $dir . DIRECTORY_SEPARATOR . $file;

        // KHÔNG dùng shell pipe `mysqldump | gzip`: exit code của pipe là của gzip, nên mysqldump chết
        // giữa chừng vẫn báo THÀNH CÔNG và để lại file cụt (đã gặp: dump đúng 1/68 bảng mà vẫn "Backup xong").
        // Ở đây gzip ngay trong PHP và chỉ giữ file khi mysqldump trả exit code 0.
        $args = ['mysqldump', '--single-transaction', '--quick', '--no-tablespaces',
            '--host=' . $conn['host'], '--port=' . (string) $conn['port'], '--user=' . $conn['username']];
        // mysqldump của MySQL 8 hỏi bảng COLUMN_STATISTICS — MariaDB không có, dump chết ngay bảng đầu.
        // Chỉ thêm cờ khi bản mysqldump này hiểu nó (mysqldump của MariaDB không có cờ và sẽ báo unknown option).
        if ($this->supportsColumnStatistics()) $args[] = '--column-statistics=0';
        $args[] = $conn['database'];

        // truyền password qua env để không lộ trong `ps`
        $process = new Process($args, null, ['MYSQL_PWD' => $conn['password']]);
        $process->setTimeout(null); // 10GB dump có thể vài phút, đừng để timeout cắt

        $gz = gzopen($path, 'wb6');
        if (! $gz) {
            $this->record(false, null, 0, $started, 'Không mở được file ghi: ' . $path);
            $this->error('Backup thất bại: không ghi được ' . $path);
            return self::FAILURE;
        }
        $process->run(function ($type, $buf) use ($gz) {
            if ($type === Process::OUT) gzwrite($gz, $buf);
        });
        gzclose($gz);

        if ($process->getExitCode() !== 0) {
            @unlink($path); // bỏ file cụt — thà không có backup còn hơn tưởng là có
            $err = trim($process->getErrorOutput()) ?: 'mysqldump trả về mã ' . $process->getExitCode();
            $this->record(false, null, 0, $started, $err);
            $this->error('Backup thất bại: ' . $err);
            return self::FAILURE;
        }

        $bytes = is_file($path) ? (int) filesize($path) : 0;
        $kept  = $this->rotate($dir, (int) $this->option('keep'));
        $this->record(true, $file, $bytes, $started, null);
        $this->info("Backup xong: {$file} (" . $this->human($bytes) . "). Giữ {$kept} bản gần nhất.");
        return self::SUCCESS;
    }

    /** mysqldump này có cờ --column-statistics không (MySQL 8 có, MariaDB không)? */
    private function supportsColumnStatistics(): bool
    {
        $p = new Process(['mysqldump', '--help']);
        $p->setTimeout(20);
        try { $p->run(); } catch (\Throwable) { return false; }
        return str_contains($p->getOutput(), 'column-statistics');
    }

    /** Giữ N file .sql.gz mới nhất, xóa phần dư. Trả về số file còn giữ. */
    private function rotate(string $dir, int $keep): int
    {
        $keep  = max(1, $keep);
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql.gz') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a)); // mới → cũ
        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
        }
        return min(count($files), $keep);
    }

    /** Lưu báo cáo lần chạy gần nhất để trang Cài đặt hệ thống đọc. */
    private function record(bool $ok, ?string $file, int $bytes, float $started, ?string $error): void
    {
        TruckingSetting::put('sys.backup_last_run', json_encode([
            'at'    => now()->toIso8601String(),
            'ok'    => $ok,
            'file'  => $file,
            'bytes' => $bytes,
            'ms'    => (int) round((microtime(true) - $started) * 1000),
            'error' => $error ? mb_substr($error, 0, 500) : null,
        ], JSON_UNESCAPED_UNICODE));
    }

    private function human(int $b): string
    {
        if ($b >= 1073741824) return round($b / 1073741824, 2) . ' GB';
        if ($b >= 1048576)    return round($b / 1048576, 2) . ' MB';
        if ($b >= 1024)       return round($b / 1024) . ' KB';
        return $b . ' B';
    }
}
