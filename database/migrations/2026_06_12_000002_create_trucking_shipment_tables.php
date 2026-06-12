<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trucking v2 — lô hàng + các dòng con (chi phí, doanh thu, thanh toán).
 *
 * Một "lô hàng" (shipment) thuộc 1 sheet ('hph' | 'icd'). Chi phí theo dõi
 * per-lô qua trucking_cost_lines (danh sách khoản linh hoạt). Doanh thu &
 * thu chi hộ qua trucking_revenue_lines. Đợt khách trả qua trucking_payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucking_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('sheet', 8)->index();           // 'hph' | 'icd'
            $table->foreignId('customer_id')->nullable()
                  ->constrained('trucking_customers')->nullOnDelete();

            // Thông tin chung
            $table->string('booking')->nullable();
            $table->string('inv')->nullable();             // số INV / hóa đơn
            $table->string('io', 16)->nullable();          // Nhập | Xuất | Khác

            // Container
            $table->unsignedInteger('qty')->nullable();    // số lượng cont (HPH)
            $table->string('cont_type')->nullable();
            $table->string('cont_no')->nullable();
            $table->string('declaration_no')->nullable();  // số tờ khai
            $table->string('kho')->nullable();             // kho (ICD)

            // Tuyến
            $table->string('from_loc')->nullable();
            $table->string('to_loc')->nullable();

            // Xe & tài xế
            $table->string('bks_vao')->nullable();
            $table->string('bks_ra')->nullable();
            $table->string('driver')->nullable();
            $table->string('ra_mode', 16)->default('self'); // 'self' | 'other'
            $table->unsignedBigInteger('ra_other_id')->nullable(); // cont khác ra cùng chuyến

            // Lịch trình
            $table->date('sail_date')->nullable();         // ngày tàu chạy (HPH)
            $table->string('cut_off')->nullable();         // cắt máng (text tự do)
            $table->date('cont_den')->nullable();          // ngày cont đến (ICD)
            $table->date('cont_ra')->nullable();           // ngày cont ra (ICD)
            $table->dateTime('gio_den_du_kien')->nullable();
            $table->dateTime('gio_xe_den')->nullable();
            $table->dateTime('gio_xe_ra')->nullable();

            // Doanh thu / công nợ (phần đầu, chi tiết ở bảng con)
            $table->decimal('vat_rate', 5, 2)->nullable(); // % VAT lô này
            $table->date('han_tt')->nullable();            // hạn thanh toán
            $table->text('ghi_chu')->nullable();           // ghi chú kế toán

            $table->timestamps();
        });

        // Khoản chi phí — đơn vị "phân bổ" lặp lại
        Schema::create('trucking_cost_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('trucking_shipments')->cascadeOnDelete();
            $table->string('item')->nullable();            // tên khoản
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('payer')->nullable();           // người chi / bên TT
            $table->date('date')->nullable();              // ngày chi
            $table->boolean('billable')->default(false);   // tích "chi hộ khách"
            $table->string('color', 16)->nullable();       // màu theo dõi (hex)
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // Dòng doanh thu & thu chi hộ
        Schema::create('trucking_revenue_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('trucking_shipments')->cascadeOnDelete();
            $table->string('kind', 16)->index();           // 'doanhThu' | 'choHo'
            $table->string('item')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // Đợt khách thanh toán (per lô)
        Schema::create('trucking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('trucking_shipments')->cascadeOnDelete();
            $table->decimal('amount', 15, 2)->nullable();
            $table->date('date')->nullable();
            $table->string('note')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_payments');
        Schema::dropIfExists('trucking_revenue_lines');
        Schema::dropIfExists('trucking_cost_lines');
        Schema::dropIfExists('trucking_shipments');
    }
};
