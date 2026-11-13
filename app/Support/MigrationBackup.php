<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Sao lưu GIÁ TRỊ CŨ của các dòng mà 1 migration dữ liệu sắp ghi đè — server chỉ chạy được `php artisan migrate`
 * nên migration tự lưu trước khi sửa, `migrate:rollback` đọc lại để khôi phục.
 * File: storage/app/private/migration-backups/{tên}.json (disk local, không public) — [id => giá trị cũ].
 */
class MigrationBackup
{
    public static function save(string $name, array $rows): void
    {
        Storage::disk('local')->put("migration-backups/{$name}.json", json_encode($rows, JSON_UNESCAPED_UNICODE));
    }

    /** null nếu chưa có bản sao lưu (migration chạy từ bản cũ chưa có bước lưu). */
    public static function load(string $name): ?array
    {
        $path = "migration-backups/{$name}.json";
        if (! Storage::disk('local')->exists($path)) return null;
        $rows = json_decode((string) Storage::disk('local')->get($path), true);
        return is_array($rows) ? $rows : null;
    }
}
