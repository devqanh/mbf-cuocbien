<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lô hàng: nhãn (tags) — chọn nhiều, lọc theo nhãn ở Lô hàng. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('info_note');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }
};
