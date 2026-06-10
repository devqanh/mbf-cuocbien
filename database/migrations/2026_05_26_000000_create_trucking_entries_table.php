<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng trucking_entries — tính năng Trucking (2 sheet HẠ HPH + HẠ ICD).
 * Cột sinh tự động từ config('trucking_columns') = union 2 sheet → 1 nguồn duy nhất.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucking_entries', function (Blueprint $table) {
            $table->id();
            $table->string('sheet', 8)->index();   // 'hph' | 'icd'

            $cols = config('trucking_columns', []);
            $seen = [];

            foreach ($cols as $list) {
                foreach ($list as $c) {
                    $key = $c['key'] ?? null;
                    if (! $key || $key === 'id' || isset($seen[$key])) continue;
                    $seen[$key] = true;

                    $type = $c['type'] ?? 'text';
                    match ($type) {
                        'date'           => $table->date($key)->nullable(),
                        'vnd', 'number'  => $table->decimal($key, 15, 2)->nullable(),
                        // TEXT thay vì VARCHAR: bảng có ~40 cột text, nếu dùng varchar(500)
                        // utf8mb4 (2000 byte/cột) sẽ vượt giới hạn row size 65535 của InnoDB.
                        default          => $table->text($key)->nullable(),
                    };
                }
            }

            $table->json('cell_formulas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_entries');
    }
};
