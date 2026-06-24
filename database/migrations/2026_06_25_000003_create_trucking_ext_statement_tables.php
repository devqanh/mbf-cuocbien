<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trucking v2 — Bảng kê xe ngoài (phải trả nhà xe thuê).
 *
 * Mirror bảng kê khách (trucking_statements): gom nhiều lô của 1 NHÀ XE theo kỳ
 * (Giờ xe đến). Mỗi dòng chụp lại (snapshot) thông tin lô + cước thuê (ext_fee).
 * Trả nhiều đợt qua trucking_ext_statement_payments → công nợ = tổng cước − đã trả.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucking_ext_statements', function (Blueprint $table) {
            $table->id();
            $table->string('no')->index();                 // số bảng kê
            $table->string('ext_vendor')->nullable();      // tên nhà xe (khớp danh mục extVendors)
            $table->date('date')->nullable();              // ngày lập
            $table->date('period_from')->nullable();       // kỳ: Giờ xe đến từ
            $table->date('period_to')->nullable();         // kỳ: Giờ xe đến đến
            $table->decimal('total', 15, 2)->default(0);   // tổng cước phải trả
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['ext_vendor', 'period_to']);
        });

        Schema::create('trucking_ext_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ext_statement_id')->constrained('trucking_ext_statements')->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()
                  ->constrained('trucking_shipments')->nullOnDelete();
            $table->string('booking')->nullable();
            $table->string('sheet', 8)->nullable();        // 'hph' | 'icd'
            $table->string('bks')->nullable();
            $table->string('from_loc')->nullable();
            $table->string('to_loc')->nullable();
            $table->string('cont_label')->nullable();      // VD: "2 × 40HC"
            $table->date('date')->nullable();              // ngày Giờ xe đến
            $table->decimal('fee', 15, 2)->default(0);     // cước thuê xe ngoài
            $table->string('note')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index('shipment_id');
        });

        Schema::create('trucking_ext_statement_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ext_statement_id')->constrained('trucking_ext_statements')->cascadeOnDelete();
            $table->date('date')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('note')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_ext_statement_payments');
        Schema::dropIfExists('trucking_ext_statement_lines');
        Schema::dropIfExists('trucking_ext_statements');
    }
};
