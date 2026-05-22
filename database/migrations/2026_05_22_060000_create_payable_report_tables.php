<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Đầu kỳ ban đầu của mỗi NCC (chỉ nhập 1 lần đầu tiên)
        Schema::create('payable_initial_balances', function (Blueprint $t) {
            $t->id();
            $t->string('supplier', 128)->unique();          // NCC
            $t->decimal('opening_amount', 18, 2)->default(0); // Đầu kỳ
            $t->date('as_of_date')->nullable();              // Ngày áp dụng (tham khảo)
            $t->text('note')->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // Báo cáo phải trả — 1 row 1 báo cáo
        Schema::create('payable_reports', function (Blueprint $t) {
            $t->id();
            $t->date('report_date');                         // Ngày tạo báo cáo
            $t->date('increase_date')->nullable();           // Ngày chốt báo cáo phát sinh tăng (chọn)
            $t->date('decrease_date')->nullable();           // Ngày chốt báo cáo phát sinh giảm (chọn)
            $t->text('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index('report_date');
        });

        // Chi tiết từng dòng NCC trong báo cáo
        Schema::create('payable_report_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_id')->constrained('payable_reports')->cascadeOnDelete();
            $t->string('supplier', 128);
            $t->decimal('opening_balance',  18, 2)->default(0);  // Đầu kỳ (snapshot)
            $t->decimal('increase_amount',  18, 2)->default(0);  // Phát sinh tăng
            $t->decimal('decrease_amount',  18, 2)->default(0);  // Phát sinh giảm
            $t->decimal('closing_balance',  18, 2)->default(0);  // Số cuối kỳ
            $t->timestamps();

            $t->index(['report_id', 'supplier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payable_report_lines');
        Schema::dropIfExists('payable_reports');
        Schema::dropIfExists('payable_initial_balances');
    }
};
