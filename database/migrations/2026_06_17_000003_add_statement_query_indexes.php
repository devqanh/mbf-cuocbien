<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tối ưu truy vấn Bảng kê khi data lớn:
 *  - tsh_customer_gio_xe_ra_idx (customer_id, gio_xe_ra): statementCandidates lọc lô của
 *    1 khách trong khoảng "Cont ra" (gio_xe_ra). Composite cũ (customer_id, cont_ra) vẫn giữ
 *    để không phá rolling queries cũ, nhưng query thực tế đã chuyển sang gio_xe_ra.
 *  - tsh_sheet_sail_date_idx (sheet, sail_date): fallback HPH (statementCandidates dùng
 *    sail_date khi sheet='HPH'). Hữu ích khi 1 khách có rất nhiều lô HPH.
 *  - tsl_shipment_idx (statement_id, shipment_id): tăng tốc statementsDrift gom shipment_id
 *    qua tất cả lines (thường đã có FK index nhưng composite này gọn hơn cho query gom id).
 *
 * Idempotent: kiểm tra hiện diện qua INFORMATION_SCHEMA trước khi tạo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->safeIndex('trucking_shipments', 'tsh_customer_gio_xe_ra_idx',
            fn (Blueprint $t) => $t->index(['customer_id', 'gio_xe_ra'], 'tsh_customer_gio_xe_ra_idx'));

        $this->safeIndex('trucking_shipments', 'tsh_sheet_sail_date_idx',
            fn (Blueprint $t) => $t->index(['sheet', 'sail_date'], 'tsh_sheet_sail_date_idx'));

        $this->safeIndex('trucking_statement_lines', 'tsl_statement_shipment_idx',
            fn (Blueprint $t) => $t->index(['statement_id', 'shipment_id'], 'tsl_statement_shipment_idx'));
    }

    public function down(): void
    {
        $this->dropIfExists('trucking_shipments', 'tsh_customer_gio_xe_ra_idx');
        $this->dropIfExists('trucking_shipments', 'tsh_sheet_sail_date_idx');
        $this->dropIfExists('trucking_statement_lines', 'tsl_statement_shipment_idx');
    }

    private function safeIndex(string $table, string $name, \Closure $create): void
    {
        if ($this->indexExists($table, $name)) return;
        Schema::table($table, $create);
    }

    private function dropIfExists(string $table, string $name): void
    {
        if (! $this->indexExists($table, $name)) return;
        Schema::table($table, function (Blueprint $t) use ($name) { $t->dropIndex($name); });
    }

    private function indexExists(string $table, string $name): bool
    {
        $db = DB::getDatabaseName();
        if (! $db) return false;
        $rows = DB::select(
            'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$db, $table, $name]
        );
        return ! empty($rows);
    }
};
