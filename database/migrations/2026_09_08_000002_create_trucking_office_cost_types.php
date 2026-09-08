<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Danh mục LOẠI CHI PHÍ VĂN PHÒNG (chi phí quản lý doanh nghiệp — G&A), tách khỏi loại chi phí xe / tài sản.
 * Phiếu chi của trung tâm chi phí "Văn phòng" (trucking_vehicles.kind='office') tham chiếu danh mục này qua
 * trucking_vehicle_costs.cost_type_id để nhóm báo cáo. Báo cáo phân giải cost_type_id theo kind của đối tượng.
 * Seed theo phân loại kế toán thường dùng cho doanh nghiệp vận tải nhỏ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trucking_office_cost_types')) return;

        Schema::create('trucking_office_cost_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        $seed = ['Thuê văn phòng', 'Điện, nước', 'Internet, điện thoại', 'Văn phòng phẩm', 'Lương & BHXH khối văn phòng',
                 'Phí ngân hàng', 'Tiếp khách, đối ngoại', 'Công tác phí', 'Phần mềm, dịch vụ CNTT', 'Thuế, phí, lệ phí',
                 'Sửa chữa, bảo trì văn phòng', 'Khác'];
        foreach ($seed as $i => $name) {
            DB::table('trucking_office_cost_types')->insert(['name' => $name, 'sort' => $i, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_office_cost_types');
    }
};
