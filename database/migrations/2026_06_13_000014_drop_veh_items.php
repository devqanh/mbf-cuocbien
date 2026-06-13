<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gỡ danh mục "Chi phí xe" (trucking_veh_items) — không nơi nào trong hệ thống dùng tới;
 * vai trò đã thay bằng Phí tuyến đường + Khoản chi phí + Khoản lương ở module Phí xe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('trucking_veh_items');
    }

    public function down(): void
    {
        Schema::create('trucking_veh_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('default_price', 15, 2)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }
};
