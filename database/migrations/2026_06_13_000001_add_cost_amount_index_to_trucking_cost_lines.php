<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index phủ (shipment_id, amount) cho trucking_cost_lines.
 *
 * Trang Lô hàng tính SUM(amount) theo lô (tổng chi phí toàn cục + sắp xếp theo chi
 * phí qua withSum). Index FK sẵn có chỉ là (shipment_id) → vẫn phải đọc dòng để lấy
 * amount. Thêm (shipment_id, amount) giúp SUM/sort chạy index-only (covering), không
 * chạm clustered index — nhanh hơn rõ khi dữ liệu lớn.
 *
 * Các index khác đã đủ: InnoDB tự gắn PK vào secondary index nên `sheet` index của
 * trucking_shipments đã phục vụ ORDER BY id; customers.name đã unique; mọi FK
 * (customer_id, shipment_id, location_id, statement_id) đã được constrained() đánh index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_cost_lines', function (Blueprint $table) {
            $table->index(['shipment_id', 'amount'], 'tcl_shipment_amount_idx');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_cost_lines', function (Blueprint $table) {
            $table->dropIndex('tcl_shipment_amount_idx');
        });
    }
};
