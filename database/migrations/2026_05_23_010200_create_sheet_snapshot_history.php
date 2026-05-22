<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * History table cho snapshot — lưu mỗi version save vào để có audit trail + khả năng rollback.
 *
 * - snapshot_key: trỏ về sheet_snapshots.key (không phải FK vì key có thể đổi)
 * - version: bản ghi version (tăng dần per key)
 * - payload: LONGBLOB chứa JSON gzip (cùng format với sheet_snapshots.payload)
 * - editor_id: user thực hiện save
 *
 * Index (snapshot_key, version) UNIQUE để tránh duplicate.
 * Index (snapshot_key, created_at) để query "version gần nhất X ngày" trong prune job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sheet_snapshot_history', function (Blueprint $table) {
            $table->id();
            $table->string('snapshot_key', 100);
            $table->unsignedInteger('version');
            // LONGBLOB không có method Blueprint trực tiếp — dùng raw column
            $table->dateTime('created_at')->useCurrent();
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unique(['snapshot_key', 'version'], 'snapshot_history_key_version_uq');
            $table->index(['snapshot_key', 'created_at'], 'snapshot_history_key_created_idx');
        });

        // Thêm payload LONGBLOB bằng raw SQL (Blueprint::binary mặc định BLOB 64KB)
        DB::statement('ALTER TABLE sheet_snapshot_history ADD COLUMN payload LONGBLOB NOT NULL AFTER version');
    }

    public function down(): void
    {
        Schema::dropIfExists('sheet_snapshot_history');
    }
};
