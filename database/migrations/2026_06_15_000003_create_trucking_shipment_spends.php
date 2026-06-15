<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duyệt chi theo lô (theo biển kiểm soát): mỗi dòng = 1 khoản THỰC CHI cho 1 lô.
 * Nguồn gợi ý = Phí tuyến đường (route fee) khớp theo `kho` + CRU + số cầu, có thể
 * xóa/sửa, thêm "chi khác". Phân loại `kind`: salary (lương tài xế) | company (chi phí
 * công ty) — để /phi-xe tổng hợp Kế hoạch / Đã chi / Còn lại theo kỳ + biển số.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucking_shipment_spends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('trucking_shipments')->cascadeOnDelete();
            $table->unsignedBigInteger('vehicle_id')->nullable();   // xe MBF nếu BKS thuộc đội (best-effort, không FK cứng vì có xe ngoài)
            $table->string('bks')->nullable();                      // snapshot biển số (xe chạy tuyến)
            $table->string('driver')->nullable();                   // tài xế nhận (cho khoản lương)
            $table->string('source')->default('other');             // veTram|tienDuong|troCap|luong|dau|phiKhac|other
            $table->string('kind')->default('company');             // salary | company
            $table->string('name');                                 // nhãn khoản chi
            $table->decimal('amount', 14, 2)->default(0);
            $table->date('spend_date')->nullable();                 // ngày chi (mặc định hôm nay khi tạo)
            $table->boolean('paid')->default(true);                 // đã chi (mặc định coi như chi luôn khi duyệt)
            $table->date('paid_date')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->index('shipment_id');
            $table->index('spend_date');
            $table->index(['vehicle_id', 'spend_date']);            // tổng hợp Phí xe theo xe + kỳ
            $table->index(['kind', 'spend_date']);                  // tách lương / chi phí công ty theo kỳ
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_shipment_spends');
    }
};
