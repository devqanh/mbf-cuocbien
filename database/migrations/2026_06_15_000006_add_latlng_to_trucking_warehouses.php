<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tọa độ GPS (lat/lng) của kho — phục vụ geofence: xác định xe đã đưa lô hàng về kho chưa. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_warehouses', function (Blueprint $table) {
            $table->double('lat')->nullable()->after('address');
            $table->double('lng')->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_warehouses', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng']);
        });
    }
};
