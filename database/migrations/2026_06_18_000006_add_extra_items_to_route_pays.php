<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chi cho lái (route_pays): thêm "Chi khác phát sinh" THỦ CÔNG theo từng CHUYẾN (cont).
 * Mỗi dòng {cont, name, amount, perDay} — phát sinh riêng từng lần đi, cộng thêm vào tổng chi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_route_pays', function (Blueprint $table) {
            $table->json('extra_items')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_route_pays', function (Blueprint $table) {
            $table->dropColumn('extra_items');
        });
    }
};
