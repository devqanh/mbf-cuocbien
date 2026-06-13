<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Khoản lương khác theo từng lô (cho lái xe): repeater [{name,amount,note}] cộng thẳng
 * vào lương nhân sự của lô — tách bạch với "phí khác phát sinh" (chi phí vận hành).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_trip_cost_lines', function (Blueprint $table) {
            $table->json('salary_extras')->nullable()->after('extras');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_trip_cost_lines', fn (Blueprint $t) => $t->dropColumn('salary_extras'));
    }
};
