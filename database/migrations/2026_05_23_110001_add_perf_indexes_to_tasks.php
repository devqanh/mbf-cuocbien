<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // tasks: phục vụ cron `tasks:remind` chạy mỗi phút.
        // Query: WHERE reminder_sent_at IS NULL AND status != 'done' AND due_at IS NOT NULL
        //        AND DATE_SUB(due_at, INTERVAL COALESCE(remind_before, 0) MINUTE) <= NOW()
        // Composite này lọc rất nhanh khi reminder_sent_at = NULL (đa số task done/đã gửi đều bị loại).
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['reminder_sent_at', 'status', 'due_at'], 'idx_reminder_lookup');
        });

        // task_user pivot: phục vụ relation assignees()/watchers() — WHERE task_id=? AND role=?
        // UNIQUE (task_id, user_id, role) hiện tại không cover được "role" mà không có user_id.
        Schema::table('task_user', function (Blueprint $table) {
            $table->index(['task_id', 'role'], 'idx_task_role');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('idx_reminder_lookup');
        });
        Schema::table('task_user', function (Blueprint $table) {
            $table->dropIndex('idx_task_role');
        });
    }
};
