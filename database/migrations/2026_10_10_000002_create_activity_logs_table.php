<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Nhật ký thao tác nhân viên. Mỗi request ghi (POST/PUT/PATCH/DELETE) = 1 dòng event='request'; mỗi model
 * quan trọng bị tạo/sửa/xóa trong request đó = 1 dòng diff, cùng request_id → tra "ai đổi gì, lúc nào".
 * summary là câu tiếng Việt đọc được ngay (cho người và cho Claude Code qua `php artisan activity:log`).
 *
 * Quyền activity.view cấp cho super_admin + admin (không có Gate::before → không cấp là không ai xem được).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor', 190)->nullable();          // tên người làm lúc ghi (giữ nguyên khi user đổi tên/bị xóa)
            $table->string('event', 16);                        // request | created | updated | deleted
            $table->string('module', 60)->nullable();           // lo-hang, cai-dat, quan-ly-xe… (từ URL)
            $table->string('route', 120)->nullable();
            $table->string('method', 8)->nullable();
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label', 255)->nullable();
            $table->json('changes')->nullable();                // diff {field: [cũ, mới]} hoặc payload đã che (request)
            $table->text('summary')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->unsignedSmallInteger('status')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['user_id', 'created_at']);
            $table->fullText(['summary', 'subject_label']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'activity.view', 'guard_name' => 'web']);
        foreach (Role::where('guard_name', 'web')->whereIn('name', ['super_admin', 'admin'])->get() as $r) $r->givePermissionTo('activity.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Permission::where('name', 'activity.view')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
