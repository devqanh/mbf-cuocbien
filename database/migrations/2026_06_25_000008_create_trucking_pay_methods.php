<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Danh mục HÌNH THỨC THANH TOÁN (phiếu chi xe/tài sản) — cấu hình tập trung ở Cài đặt
 * để thêm tài khoản/ngân hàng (Vietinbank, Bank công ty, Cá nhân…) và sau LỌC theo hình thức.
 * Phiếu chi vẫn lưu chuỗi paid_method (không đổi schema vehicle_costs) — danh mục chỉ là gợi ý chọn.
 * Seed 3 mặc định cũ để giữ nguyên hành vi hiện tại.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trucking_pay_methods')) return;

        Schema::create('trucking_pay_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        foreach (['Chuyển khoản', 'Tiền mặt', 'Khác'] as $i => $name) {
            DB::table('trucking_pay_methods')->insert(['name' => $name, 'sort' => $i, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_pay_methods');
    }
};
