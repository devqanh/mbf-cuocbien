<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index tối ưu query cho dữ liệu lớn.
 *
 *  shipments:
 *    - supplier (filter trong báo cáo phải trả)
 *    - report_close_date_increase / _decrease (whereDate + DISTINCT)
 *    - composite (supplier, report_close_date_*) → range scan thay full scan
 *    - customer / agent_name (future-proof reporting)
 *
 *  payable_report_lines:
 *    - (supplier, report_id) — opening balance lookup ORDER BY report_id DESC
 *
 *  sheet_snapshots:
 *    - updated_at — query "snapshot edit gần nhất"
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->index('supplier', 'shipments_supplier_idx');
            $table->index('report_close_date_increase', 'shipments_report_close_inc_idx');
            $table->index('report_close_date_decrease', 'shipments_report_close_dec_idx');
            $table->index(['supplier', 'report_close_date_increase'], 'shipments_supplier_close_inc_idx');
            $table->index(['supplier', 'report_close_date_decrease'], 'shipments_supplier_close_dec_idx');
            $table->index('customer',   'shipments_customer_idx');
            $table->index('agent_name', 'shipments_agent_name_idx');
        });

        Schema::table('payable_report_lines', function (Blueprint $table) {
            $table->index(['supplier', 'report_id'], 'payable_lines_supplier_report_idx');
        });

        Schema::table('sheet_snapshots', function (Blueprint $table) {
            $table->index('updated_at', 'sheet_snapshots_updated_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex('shipments_supplier_idx');
            $table->dropIndex('shipments_report_close_inc_idx');
            $table->dropIndex('shipments_report_close_dec_idx');
            $table->dropIndex('shipments_supplier_close_inc_idx');
            $table->dropIndex('shipments_supplier_close_dec_idx');
            $table->dropIndex('shipments_customer_idx');
            $table->dropIndex('shipments_agent_name_idx');
        });

        Schema::table('payable_report_lines', function (Blueprint $table) {
            $table->dropIndex('payable_lines_supplier_report_idx');
        });

        Schema::table('sheet_snapshots', function (Blueprint $table) {
            $table->dropIndex('sheet_snapshots_updated_at_idx');
        });
    }
};
