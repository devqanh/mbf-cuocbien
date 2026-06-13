<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Địa điểm & Kho: bỏ UNIQUE trên `name` để cho phép TRÙNG TÊN (tên chỉ là nhãn hiển thị).
 * Định danh thực = KÝ HIỆU (code) — duy nhất do tầng ứng dụng đảm bảo. Thêm index thường
 * cho name/code để tra cứu/reconcile nhanh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_locations', function (Blueprint $t) {
            $t->dropUnique('trucking_locations_name_unique');
            $t->index('name');
            $t->index('code');
        });
        Schema::table('trucking_warehouses', function (Blueprint $t) {
            $t->dropUnique('trucking_warehouses_name_unique');
            $t->index('name');
            $t->index('code');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_locations', function (Blueprint $t) {
            $t->dropIndex(['name']);
            $t->dropIndex(['code']);
            $t->unique('name');
        });
        Schema::table('trucking_warehouses', function (Blueprint $t) {
            $t->dropIndex(['name']);
            $t->dropIndex(['code']);
            $t->unique('name');
        });
    }
};
