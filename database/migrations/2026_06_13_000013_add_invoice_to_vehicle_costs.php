<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Số hóa đơn (# HĐ) cho phiếu chi xe — để đối chiếu + ghi "đã gia hạn ở HĐ #…". */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_vehicle_costs', function (Blueprint $table) {
            $table->string('invoice_no')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_vehicle_costs', function (Blueprint $table) {
            $table->dropColumn('invoice_no');
        });
    }
};
