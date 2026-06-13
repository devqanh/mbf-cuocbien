<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Danh mục "Khoản lương (lái xe)" — các khoản lương thêm (thưởng, phụ cấp…) chọn từ danh mục
 * thay vì nhập tay ở Phí xe → sau này query tổng hợp lương dễ. Tương tự "Khoản chi phí" (costItems)
 * dùng cho phí khác phát sinh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucking_salary_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // vài khoản mặc định để dùng ngay
        foreach (['Thưởng chuyên cần', 'Phụ cấp ăn ca', 'Phụ cấp xa', 'Lương thêm giờ', 'Thưởng chuyến'] as $i => $name) {
            DB::table('trucking_salary_items')->insert(['name' => $name, 'sort' => $i, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_salary_items');
    }
};
