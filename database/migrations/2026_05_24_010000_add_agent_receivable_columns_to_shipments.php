<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm 6 cột mới về AGENT RECEIVABLE (credit note + phải thu agent).
 * Đặt sau agent_paid_date — kết thúc block agent payment, mở block agent receivable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->decimal('credit_note_agent', 15, 2)->nullable()->after('agent_paid_date');
            $table->decimal('agent_receivable_amount', 15, 2)->nullable()->after('credit_note_agent');
            $table->decimal('credit_note_agent_vnd', 15, 2)->nullable()->after('agent_receivable_amount');
            $table->date('agent_receivable_due_date')->nullable()->after('credit_note_agent_vnd');
            $table->decimal('agent_received_amount', 15, 2)->nullable()->after('agent_receivable_due_date');
            $table->date('agent_received_date')->nullable()->after('agent_received_amount');

            // Index cho cột date thường lọc/tìm
            $table->index('agent_receivable_due_date', 'shipments_agent_recv_due_idx');
            $table->index('agent_received_date',      'shipments_agent_recv_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex('shipments_agent_recv_due_idx');
            $table->dropIndex('shipments_agent_recv_date_idx');
            $table->dropColumn([
                'credit_note_agent',
                'agent_receivable_amount',
                'credit_note_agent_vnd',
                'agent_receivable_due_date',
                'agent_received_amount',
                'agent_received_date',
            ]);
        });
    }
};
