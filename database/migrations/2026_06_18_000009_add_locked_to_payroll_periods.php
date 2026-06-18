<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kỳ lương: cờ CHỐT (đóng băng) — khóa số liệu, không cho Tính lại/sửa khi đã chốt. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_payroll_periods', function (Blueprint $table) {
            $table->boolean('locked')->default(false)->after('paid_daily');
            $table->timestamp('locked_at')->nullable()->after('locked');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_payroll_periods', function (Blueprint $table) {
            $table->dropColumn(['locked', 'locked_at']);
        });
    }
};
