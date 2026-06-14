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

        $command = sprintf(
            'mysqldump --single-transaction --quick --no-tablespaces '
            . '--host=%s --port=%s --user=%s %s | gzip > %s',
            escapeshellarg($conn['host']),
            escapeshellarg((string) $conn['port']),
            escapeshellarg($conn['username']),
            escapeshellarg($conn['database']),
            escapeshellarg($path)
        );

        // truyền password qua env để không lộ trong `ps`
        $process = Process::fromShellCommandline($command, null, [
            'MYSQL_PWD' => $conn['password'],
        ]);
        $process->setTimeout(null); // 10GB dump có thể vài phút, đừng để timeout cắt

        $process->run();

        if (! $process->isSuccessful()) {
            @unlink($path); // bỏ file rỗng/hỏng nếu có
            $err = trim($process->getErrorOutput()) ?: 'Lỗi không rõ';
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
