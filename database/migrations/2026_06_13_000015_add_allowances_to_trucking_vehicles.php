<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Định mức km theo loại chi phí cho từng xe: [{costItem, km}] — chặn tạo phiếu chi quá sớm. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_vehicles', function (Blueprint $table) {
            $table->json('allowances')->nullable()->after('documents');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_vehicles', function (Blueprint $table) {
            $table->dropColumn('allowances');
        });
    }
};
