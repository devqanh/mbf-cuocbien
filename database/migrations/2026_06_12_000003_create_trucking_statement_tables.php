<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trucking v2 — Bảng kê cần thu (statement).
 *
 * Doanh thu/công nợ thu theo BẢNG KÊ: gom nhiều lô của 1 khách theo kỳ
 * (ngày cont ra). Mỗi dòng bảng kê chụp lại (snapshot) thông tin lô tại thời
 * điểm lập để in/PDF ổn định, kèm phải thu có thể chỉnh tay. Khách trả nhiều
 * đợt qua trucking_statement_payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucking_statements', function (Blueprint $table) {
            $table->id();
            $table->string('no')->index();                 // số bảng kê
            $table->foreignId('customer_id')->nullable()
                  ->constrained('trucking_customers')->nullOnDelete();
            $table->string('customer_name')->nullable();   // snapshot tên khách
            $table->json('info')->nullable();              // snapshot MST/địa chỉ/liên hệ/hạn TT
            $table->date('date')->nullable();              // ngày lập
            $table->date('period_from')->nullable();       // kỳ: cont ra từ
            $table->date('period_to')->nullable();         // kỳ: cont ra đến
            $table->decimal('total', 15, 2)->default(0);   // tổng phải thu
            $table->timestamps();
        });

        Schema::create('trucking_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_id')->constrained('trucking_statements')->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()
                  ->constrained('trucking_shipments')->nullOnDelete();
            $table->string('booking')->nullable();
            $table->string('sheet', 8)->nullable();        // 'hph' | 'icd'
            $table->string('io', 16)->nullable();
            $table->string('from_loc')->nullable();
            $table->string('to_loc')->nullable();
            $table->date('date')->nullable();              // cont ra
            $table->string('cont_label')->nullable();      // VD: "2 × 40HC"
            $table->decimal('phai_thu', 15, 2)->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('trucking_statement_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_id')->constrained('trucking_statements')->cascadeOnDelete();
            $table->date('date')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('note')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_statement_payments');
        Schema::dropIfExists('trucking_statement_lines');
        Schema::dropIfExists('trucking_statements');
    }
};
