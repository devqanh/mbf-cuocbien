<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chuyển sheet_snapshots.payload từ LONGTEXT (JSON raw) sang LONGBLOB (JSON đã gzip).
 *
 * Lý do: snapshot Luckysheet ~3–11 MB/save, gzip cắt ~80–90% còn ~300 KB–1 MB.
 * Tiết kiệm DB size + network I/O + giảm InnoDB fragmentation từ overwrite-in-place.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Thêm cột BLOB tạm
        DB::statement('ALTER TABLE sheet_snapshots ADD COLUMN payload_blob LONGBLOB NULL AFTER payload');

        // 2) Compress data cũ — chunk 50 row/lần để tránh OOM (snapshot có thể vài MB)
        DB::table('sheet_snapshots')
            ->select(['id', 'payload'])
            ->orderBy('id')
            ->chunkById(50, function ($rows) {
                foreach ($rows as $row) {
                    if ($row->payload === null || $row->payload === '') continue;
                    $compressed = gzdeflate($row->payload, 6);
                    DB::table('sheet_snapshots')
                        ->where('id', $row->id)
                        ->update(['payload_blob' => $compressed]);
                }
            });

        // 3) Drop cột cũ, rename payload_blob → payload, set NOT NULL
        DB::statement('ALTER TABLE sheet_snapshots DROP COLUMN payload');
        DB::statement('ALTER TABLE sheet_snapshots CHANGE COLUMN payload_blob payload LONGBLOB NOT NULL');
    }

    public function down(): void
    {
        // Rollback: decompress về LONGTEXT
        DB::statement('ALTER TABLE sheet_snapshots ADD COLUMN payload_text LONGTEXT NULL AFTER payload');

        DB::table('sheet_snapshots')
            ->select(['id', 'payload'])
            ->orderBy('id')
            ->chunkById(50, function ($rows) {
                foreach ($rows as $row) {
                    if ($row->payload === null || $row->payload === '') continue;
                    $decompressed = @gzinflate($row->payload);
                    if ($decompressed === false) $decompressed = $row->payload;
                    DB::table('sheet_snapshots')
                        ->where('id', $row->id)
                        ->update(['payload_text' => $decompressed]);
                }
            });

        DB::statement('ALTER TABLE sheet_snapshots DROP COLUMN payload');
        DB::statement('ALTER TABLE sheet_snapshots CHANGE COLUMN payload_text payload LONGTEXT NOT NULL');
    }
};
