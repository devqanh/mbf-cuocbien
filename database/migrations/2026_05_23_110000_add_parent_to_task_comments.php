<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: thêm column + index mới TRƯỚC.
        // MySQL không cho drop index `task_id_created_at` cùng lúc với FK trên task_id
        // → phải tách 2 statement: tạo index mới trước (FK có chỗ rely), rồi mới drop cái cũ.
        Schema::table('task_comments', function (Blueprint $table) {
            // Reply: comment con trỏ về comment cha (luôn là TOP-LEVEL, không chain sâu).
            // ON DELETE CASCADE: xoá comment gốc → tất cả replies cũng đi theo.
            $table->foreignId('parent_id')
                ->nullable()
                ->after('user_id')
                ->constrained('task_comments')
                ->cascadeOnDelete();

            // Composite cover cả 2 query nóng:
            //   - List top-level:  WHERE task_id=? AND parent_id IS NULL ORDER BY created_at
            //   - List replies:    WHERE task_id=? AND parent_id=?      ORDER BY created_at
            $table->index(['task_id', 'parent_id', 'created_at'], 'idx_task_parent_time');
        });

        // Step 2: drop index cũ (giờ FK có composite mới cover task_id rồi).
        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropIndex(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropIndex('idx_task_parent_time');
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
            $table->index(['task_id', 'created_at']);
        });
    }
};
