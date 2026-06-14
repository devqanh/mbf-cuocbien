<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite index (customer_id, cont_ra) cho statementCandidates — lọc lô của 1 KHÁCH
 * trong KHOẢNG cont-ra cùng lúc (nhanh hơn khi 1 khách có rất nhiều lô).
 * Các bảng con (cost_lines/attachments/vehicle_costs/…) đã đủ index từ trước.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->index(['customer_id', 'cont_ra'], 'tsh_customer_contra_idx');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->dropIndex('tsh_customer_contra_idx');
        });
    }
};
