<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kỳ LƯƠNG lái xe (snapshot) — gom theo BIỂN SỐ XE qua khoảng ngày.
 * lines JSON = chốt số liệu lúc tạo (bks, lái auto, đã chi theo ngày, lương phải trả, chi tiết).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucking_payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('no')->nullable();          // mã kỳ (LG-0001…)
            $table->string('name')->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->decimal('total', 16, 2)->default(0);        // tổng lương phải trả đợt
            $table->decimal('paid_daily', 16, 2)->default(0);   // tổng đã chi theo ngày (tham khảo)
            $table->json('lines')->nullable();                  // snapshot theo bks
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_payroll_periods');
    }
};
