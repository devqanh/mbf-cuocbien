<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Danh mục LOẠI CHI PHÍ TÀI SẢN (riêng, tách khỏi "Loại chi phí xe").
 * Phiếu chi của tài sản (trucking_vehicles.kind='asset') tham chiếu danh mục này qua
 * trucking_vehicle_costs.cost_type_id để nhóm báo cáo theo loại — KHÔNG dùng chung
 * với "Loại chi phí xe". Báo cáo phân giải cost_type_id theo kind của xe/tài sản.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trucking_asset_cost_types')) return;

        Schema::create('trucking_asset_cost_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        foreach (['Bảo trì, bảo dưỡng', 'Sửa chữa', 'Nâng cấp', 'Vật tư tiêu hao', 'Vận hành', 'Khác'] as $i => $name) {
            DB::table('trucking_asset_cost_types')->insert(['name' => $name, 'sort' => $i, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_asset_cost_types');
    }
};
