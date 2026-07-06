<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phiếu chi xe (trucking_vehicle_costs): NỚI cột `note` từ varchar(255) → TEXT.
 * Ghi chú tự do có thể dài (>255) gây lỗi 1406 "Data too long for column 'note'".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_vehicle_costs', function (Blueprint $table) {
            $table->text('note')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('trucking_vehicle_costs', function (Blueprint $table) {
            $table->string('note')->nullable()->change();
        });
    }
};
