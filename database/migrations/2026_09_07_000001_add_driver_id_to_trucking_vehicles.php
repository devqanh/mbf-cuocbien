<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lái xe MẶC ĐỊNH của xe (Cài đặt → Biển số xe, chỉ xe MBF) — liên kết theo id vì tên lái xe có thể trùng.
 * Xóa lái xe khỏi danh mục → xe tự về "chưa gán" (nullOnDelete), không để id treo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_vehicles', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->after('gps_ref')->constrained('trucking_drivers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trucking_vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('driver_id');
        });
    }
};
