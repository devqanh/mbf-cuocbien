<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tham chiếu & số liệu chốt sẵn cho BÁO CÁO (lô hàng):
 *  Tier 1 — tổng tiền mức lô (doanh thu/VAT/chi hộ/phải thu/đã thu/còn nợ/chi phí/lợi nhuận)
 *  Tier 2 — khóa khoản: cost_lines.cost_item_id + payer_id; revenue_lines.item_id
 *  Tier 3 — khóa xe/địa điểm: shipments.vehicle_id/from_location_id/to_location_id + bảng shipment_warehouses
 * Giữ NGUYÊN các cột chuỗi cũ (lịch sử) — id chỉ là khóa join thêm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            // Tier 1 — số liệu chốt
            $table->decimal('rev_base', 15, 2)->default(0)->after('vat_rate');       // doanh thu chưa VAT
            $table->decimal('vat_amount', 15, 2)->default(0)->after('rev_base');
            $table->decimal('choho_revenue', 15, 2)->default(0)->after('vat_amount'); // chi hộ thu
            $table->decimal('phai_thu', 15, 2)->default(0)->after('choho_revenue');
            $table->decimal('da_thu', 15, 2)->default(0)->after('phai_thu');
            $table->decimal('con_no', 15, 2)->default(0)->after('da_thu');
            $table->decimal('cost_total', 15, 2)->default(0)->after('con_no');        // tổng chi phí
            $table->decimal('cost_billable', 15, 2)->default(0)->after('cost_total'); // chi phí thu/chi hộ
            $table->decimal('cost_company', 15, 2)->default(0)->after('cost_billable');// công ty chịu
            $table->decimal('profit', 15, 2)->default(0)->after('cost_company');      // rev_base − cost_company
            // Tier 3 — khóa xe/địa điểm
            $table->unsignedBigInteger('vehicle_id')->nullable()->after('bks_vao')->index();
            $table->unsignedBigInteger('from_location_id')->nullable()->after('from_loc')->index();
            $table->unsignedBigInteger('to_location_id')->nullable()->after('to_loc')->index();
        });

        // Tier 2 — khóa khoản
        Schema::table('trucking_cost_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('cost_item_id')->nullable()->after('item')->index();
            $table->unsignedBigInteger('payer_id')->nullable()->after('payer')->index();
        });
        Schema::table('trucking_revenue_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->nullable()->after('item')->index();   // revItems nếu kind=doanhThu, choHoItems nếu kind=choHo
        });

        // Tier 3 — kho theo lô (mỗi kho 1 dòng) để báo cáo theo kho/tuyến
        Schema::create('trucking_shipment_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('trucking_shipments')->cascadeOnDelete();
            $table->unsignedBigInteger('warehouse_id')->nullable()->index();
            $table->string('name')->nullable();   // snapshot tên kho
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index('shipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_shipment_warehouses');
        Schema::table('trucking_revenue_lines', fn (Blueprint $t) => $t->dropColumn('item_id'));
        Schema::table('trucking_cost_lines', fn (Blueprint $t) => $t->dropColumn(['cost_item_id', 'payer_id']));
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->dropColumn(['rev_base', 'vat_amount', 'choho_revenue', 'phai_thu', 'da_thu', 'con_no',
                'cost_total', 'cost_billable', 'cost_company', 'profit', 'vehicle_id', 'from_location_id', 'to_location_id']);
        });
    }
};
