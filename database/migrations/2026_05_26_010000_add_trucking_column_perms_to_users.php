<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quyền cột Trucking (admin set, per-user) + ẩn cột cá nhân (user tự chọn).
 * Tách riêng khỏi shipments vì 2 module có bộ cột khác nhau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('trucking_column_permissions')->nullable()->after('shipment_column_prefs');
            $table->json('trucking_column_prefs')->nullable()->after('trucking_column_permissions');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['trucking_column_permissions', 'trucking_column_prefs']);
        });
    }
};
