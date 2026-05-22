<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 8 cột workflow status — đổi từ boolean → string (chứa note ngắn, ngày, "x", etc.) */
    private const STATUS_FIELDS = [
        'vgm', 'si', 'bl_draft', 'bl_confirm', 'obl', 'tlx', 'swb', 'shipment_done',
    ];

    public function up(): void
    {
        // 1) Drop 8 cột boolean cũ
        Schema::table('shipments', function (Blueprint $t) {
            $t->dropColumn(self::STATUS_FIELDS);
        });

        // 2) Recreate as string + thêm 24 cột mới
        Schema::table('shipments', function (Blueprint $t) {
            // --- Status (text — chứa "x", "11/05 up SWB", "pending"...) ---
            foreach (self::STATUS_FIELDS as $f) {
                $t->string($f, 100)->nullable();
            }

            // --- Mua / chi phí / NCC (Supplier) ---
            $t->text   ('purchase_note')->nullable();                   // Note giá mua
            $t->decimal('payment_amount', 18, 2)->nullable();           // Số tiền thanh toán
            $t->string ('supplier', 128)->nullable();                   // NCC
            $t->date   ('supplier_due_date')->nullable();               // Hạn phải trả NCC
            $t->date   ('supplier_paid_date')->nullable();              // Ngày trả NCC
            $t->decimal('cost_recognized', 18, 2)->nullable();          // Chi phí ghi nhận
            $t->decimal('trucking_cost', 18, 2)->nullable();            // Trucking
            $t->string ('purchase_invoice_no', 64)->nullable();         // Số HĐ đầu vào
            $t->date   ('purchase_invoice_date')->nullable();           // Ngày HĐ đầu vào

            // --- Agent ---
            $t->string ('driver_hoa', 128)->nullable();                 // Driver Hoa
            $t->decimal('agent_fee', 18, 2)->nullable();                // Phí agent (ngoại tệ)
            $t->string ('agent_name', 128)->nullable();                 // Tên Agent
            $t->decimal('agent_fee_vnd', 18, 2)->nullable();            // Quy đổi VNĐ
            $t->date   ('agent_due_date')->nullable();                  // Hạn phải trả Agent
            $t->date   ('agent_paid_date')->nullable();                 // Ngày trả Agent

            // --- Bán / doanh thu / KH ---
            $t->text   ('sale_note')->nullable();                       // Note giá bán
            $t->decimal('receivable_amount', 18, 2)->nullable();        // Phải thu khách
            $t->string ('customer', 128)->nullable();                   // Khách hàng (≠ Client của lô)
            $t->decimal('received_amount', 18, 2)->nullable();          // Tiền đã thu
            $t->date   ('receivable_due_date')->nullable();             // Hạn phải thu
            $t->date   ('received_date')->nullable();                   // Ngày thu
            $t->decimal('revenue_recognized', 18, 2)->nullable();       // Doanh thu ghi nhận
            $t->string ('sale_invoice_no', 64)->nullable();             // Số HĐ đầu ra
            $t->date   ('sale_invoice_date')->nullable();               // Ngày HĐ đầu ra
        });

        // 3) Xoá snapshot cũ — workbook structure đã đổi (cột mới)
        DB::table('sheet_snapshots')->where('key', 'like', 'shipments_grid%')->delete();
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $t) {
            foreach (self::STATUS_FIELDS as $f) $t->dropColumn($f);
            $t->dropColumn([
                'purchase_note', 'payment_amount', 'supplier',
                'supplier_due_date', 'supplier_paid_date', 'cost_recognized',
                'trucking_cost', 'purchase_invoice_no', 'purchase_invoice_date',
                'driver_hoa', 'agent_fee', 'agent_name', 'agent_fee_vnd',
                'agent_due_date', 'agent_paid_date',
                'sale_note', 'receivable_amount', 'customer', 'received_amount',
                'receivable_due_date', 'received_date', 'revenue_recognized',
                'sale_invoice_no', 'sale_invoice_date',
            ]);
        });
        Schema::table('shipments', function (Blueprint $t) {
            foreach (self::STATUS_FIELDS as $f) $t->boolean($f)->default(false);
        });
    }
};
