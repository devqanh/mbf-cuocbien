<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Danh mục loại tài sản (Máy móc, Thiết bị VP, Nhà xưởng…) — chọn có sẵn + thêm mới. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucking_asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->integer('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_asset_categories');
    }
};
