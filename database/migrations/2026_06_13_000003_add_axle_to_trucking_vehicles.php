<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đội xe: thêm `axle` (số cầu: "1" | "2") cho xe MBF — để link sang Phí tuyến đường
 * (dầu 2 cầu / 1 cầu) tính chi phí chuyến.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_vehicles', function (Blueprint $table) {
            $table->string('axle', 4)->nullable()->after('type');   // "1" | "2"
        });
    }

    public function down(): void
    {
        Schema::table('trucking_vehicles', function (Blueprint $table) {
            $table->dropColumn('axle');
        });
    }
};
