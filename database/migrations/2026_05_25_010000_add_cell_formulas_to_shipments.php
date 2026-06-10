<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cột `cell_formulas` (JSON) — lưu công thức Luckysheet theo từng ô.
 * Map dạng { colKey: "=SUM(...)" } per row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->json('cell_formulas')->nullable()->after('sale_invoice_date');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('cell_formulas');
        });
    }
};
