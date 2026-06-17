<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Kéo toàn bộ database từ server REMOTE về MySQL LOCAL để dev/test.
 *
 * Luồng:
 *  1) mysqldump từ remote (host/user/pass theo option, mặc định = server dev)
 *     -> nén gzip lưu tạm ra storage/app/backups (cũng là 1 bản backup remote).
 *  2) (Tùy chọn) sao lưu nhanh DB local hiện tại trước khi ghi đè.
 *  3) Giải nén -> nạp vào MySQL local (connection 'mysql' trong config).
 *
 * CẢNH BÁO: lệnh này GHI ĐÈ toàn bộ DB local. Có hỏi xác nhận, bỏ qua bằng --force.
 *
 * Yêu cầu: mysqldump, mysql, gzip có trong PATH (Laragon/ServBay đều kèm sẵn).
 */
class PullDatabase extends Command
{
    protected $signature = 'db:pull
        {--rhost=13.112.124.198 : Host remote}
        {--rport=3306 : Port remote}
        {--rdb=cuocbien_dev : Tên database remote}
        {--ruser=cuocbien_dev : User remote}
        {--rpass=H7D2wzYfXB8fwrLB : Mật khẩu remote}
        {--no-local-backup : Bỏ qua bước sao lưu DB local trước khi ghi đè}
        {--keep-dump : Giữ lại file dump remote sau khi nạp xong}
        {--force : Không hỏi xác nhận, ghi đè luôn}';

    protected $description = 'Kéo database từ server remote về MySQL local (ghi đè local) để test';

    public function handle(): int
    {
        $local = config('database.connections.mysql');

        $rhost = (string) $this->option('rhost');
        $rport = (string) $this->option('rport');
        $rdb   = (string) $this->option('rdb');
        $ruser = (string) $this->option('ruser');
        $rpass = (string) $this->option('rpass');

        $this->line('Nguồn (remote): ' . "{$ruser}@{$rhost}:{$rport}/{$rdb}");
        $this->line('Đích  (local) : ' . "{$local['username']}@{$local['host']}:{$local['port']}/{$local['database']}");

        if (! $this->option('force') && ! $this->confirm("Sẽ GHI ĐÈ toàn bộ DB local '{$local['database']}'. Tiếp tục?", false)) {
            $this->warn('Đã hủy.');
            return self::SUCCESS;
        }

        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // 1) Sao lưu local trước (nếu DB local đang có dữ liệu cần giữ)
        if (! $this->option('no-local-backup')) {
            $this->info('› Sao lưu DB local hiện tại...');
            $localBak = $dir . DIRECTORY_SEPARATOR
                . 'LOCAL_' . $local['database'] . '_' . now()->format('Y_m_d_His') . '.sql.gz';
            $ok = $this->sh(
                $this->dumpCmd($local['host'], $local['port'], $local['username'], $local['database'], $localBak),
                ['MYSQL_PWD' => (string) $local['password']]
            );
            if ($ok) {
                $this->line('  → ' . basename($localBak));
            } else {
                $this->warn('  Bỏ qua (DB local có thể chưa tồn tại) — vẫn tiếp tục.');
            }
        }

        // 2) Dump remote -> file gzip tạm
        $dump = $dir . DIRECTORY_SEPARATOR
            . 'REMOTE_' . $rdb . '_' . now()->format('Y_m_d_His') . '.sql.gz';
        $this->info('› Tải dump từ remote (có thể mất vài phút)...');
        if (! $this->sh($this->dumpCmd($rhost, $rport, $ruser, $rdb, $dump), ['MYSQL_PWD' => $rpass])) {
            @unlink($dump);
            return self::FAILURE;
        }
        $this->line('  → ' . basename($dump) . ' (' . $this->human((int) @filesize($dump)) . ')');

        // 3) Nạp vào local
        $this->info('› Nạp vào DB local...');
        $create = sprintf(
            'mysql --host=%s --port=%s --user=%s -e %s',
            escapeshellarg($local['host']),
            escapeshellarg((string) $local['port']),
            escapeshellarg($local['username']),
            escapeshellarg("CREATE DATABASE IF NOT EXISTS `{$local['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")
        );
        if (! $this->sh($create, ['MYSQL_PWD' => (string) $local['password']])) {
            return self::FAILURE;
        }

        $import = sprintf(
            'gzip -dc %s | mysql --host=%s --port=%s --user=%s %s',
            escapeshellarg($dump),
            escapeshellarg($local['host']),
            escapeshellarg((string) $local['port']),
            escapeshellarg($local['username']),
            escapeshellarg($local['database'])
        );
        if (! $this->sh($import, ['MYSQL_PWD' => (string) $local['password']])) {
            return self::FAILURE;
        }

        if (! $this->option('keep-dump')) {
            @unlink($dump);
        } else {
            $this->line('  Giữ dump: ' . basename($dump));
        }

        $this->info("✓ Xong. DB local '{$local['database']}' đã đồng bộ từ remote.");
        return self::SUCCESS;
    }

    /** Lệnh mysqldump -> gzip ra file. */
    private function dumpCmd(string $host, $port, string $user, string $db, string $out): string
    {
        return sprintf(
            'mysqldump --single-transaction --quick --no-tablespaces --column-statistics=0 --routines --events --default-character-set=utf8mb4 '
            . '--host=%s --port=%s --user=%s %s | gzip > %s',
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($user),
            escapeshellarg($db),
            escapeshellarg($out)
        );
    }

    /** Chạy shell command, password truyền qua MYSQL_PWD để không lộ trong `ps`. */
    private function sh(string $command, array $env): bool
    {
        $p = Process::fromShellCommandline($command, null, $env);
        $p->setTimeout(null);
        $p->run(fn ($type, $buffer) => $this->output->write($buffer));

        if (! $p->isSuccessful()) {
            $this->error(trim($p->getErrorOutput()) ?: 'Lỗi không rõ');
            return false;
        }
        return true;
    }

    private function human(int $b): string
    {
        if ($b >= 1073741824) return round($b / 1073741824, 2) . ' GB';
        if ($b >= 1048576)    return round($b / 1048576, 2) . ' MB';
        if ($b >= 1024)       return round($b / 1024) . ' KB';
        return $b . ' B';
    }
}
