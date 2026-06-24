<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Khoản chi phí: cờ AUTO — tự hiện sẵn ở popup Chi phí lô hàng + (nếu có màu theo dõi) nhắc "chưa điền" trên mọi lô. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_cost_items', function (Blueprint $table) {
            $table->boolean('auto')->default(false)->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_cost_items', function (Blueprint $table) {
            $table->dropColumn('auto');
        });
    }
};
