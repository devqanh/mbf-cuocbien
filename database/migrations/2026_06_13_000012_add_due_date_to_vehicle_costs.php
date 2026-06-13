<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chi phí xe định kỳ → có NGÀY HẾT HẠN (due_date) để nhắc gia hạn (bảo hiểm, đăng kiểm,
 * thuê bao GPS…). Loại chi phí gộp còn: fixed (cố định) | recurring (định kỳ).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_vehicle_costs', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('spend_date');   // hạn của chi phí định kỳ
        });
        // Chuẩn hóa loại cũ monthly/yearly → recurring
        \Illuminate\Support\Facades\DB::table('trucking_vehicle_costs')
            ->whereIn('kind', ['monthly', 'yearly'])->update(['kind' => 'recurring']);
    }

    public function down(): void
    {
        Schema::table('trucking_vehicle_costs', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
