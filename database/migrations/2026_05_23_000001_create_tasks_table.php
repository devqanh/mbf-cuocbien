<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();

            $table->enum('status', ['todo', 'doing', 'done'])->default('todo');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

            // Khi due_at có giá trị → task trở thành nhắc hẹn.
            $table->dateTime('due_at')->nullable();
            // Số phút trước due_at sẽ gửi notification nhắc (vd 60 = nhắc trước 1 tiếng).
            $table->unsignedInteger('remind_before')->nullable();
            // Đánh dấu đã gửi notification reminder rồi, tránh gửi lại.
            $table->dateTime('reminder_sent_at')->nullable();

            $table->dateTime('completed_at')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // Polymorphic: gắn task vào Shipment, PayableReport, hoặc để null (task tự do).
            $table->nullableMorphs('linkable');

            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index('created_by');
            $table->index('completed_at');
        });

        Schema::create('task_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // 'assignee' = người được giao thực thi; 'watcher' = follow thầm để nhận thông báo
            $table->enum('role', ['assignee', 'watcher'])->default('assignee');
            $table->timestamps();

            $table->unique(['task_id', 'user_id', 'role']);
            $table->index(['user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_user');
        Schema::dropIfExists('tasks');
    }
};
