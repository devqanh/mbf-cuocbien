<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bỏ phân quyền cột (Luckysheet cũ) — hệ thống chuyển hẳn sang phân quyền theo vai trò
 * ở /roles. Xóa 3 cột không còn dùng trên bảng users.
 */
return new class extends Migration
{
    private array $cols = ['column_permissions', 'trucking_column_permissions', 'trucking_column_prefs'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ($this->cols as $c) {
                if (Schema::hasColumn('users', $c)) $table->dropColumn($c);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'column_permissions'))          $table->json('column_permissions')->nullable();
            if (! Schema::hasColumn('users', 'trucking_column_permissions')) $table->json('trucking_column_permissions')->nullable();
            if (! Schema::hasColumn('users', 'trucking_column_prefs'))       $table->json('trucking_column_prefs')->nullable();
        });
    }
};
