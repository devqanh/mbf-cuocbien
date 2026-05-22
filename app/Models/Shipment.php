<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    public const DIRECTION_IMPORT = 'import';
    public const DIRECTION_EXPORT = 'export';

    /** 8 cột workflow status (text, không phải boolean) */
    public const STATUS_FIELDS = [
        'vgm', 'si', 'bl_draft', 'bl_confirm', 'obl', 'tlx', 'swb', 'shipment_done',
    ];

    /** Cột số (decimal) — dùng để cast + validate + format */
    public const DECIMAL_FIELDS = [
        'payment_amount', 'cost_recognized', 'trucking_cost',
        'agent_fee', 'agent_fee_vnd',
        'receivable_amount', 'received_amount', 'revenue_recognized',
    ];

    /** Cột ngày */
    public const DATE_FIELDS = [
        'etd', 'eta',
        'supplier_due_date',
        'report_close_date_increase', 'report_close_date_decrease',
        'supplier_paid_date', 'purchase_invoice_date',
        'agent_due_date', 'agent_paid_date',
        'receivable_due_date', 'received_date', 'sale_invoice_date',
    ];

    protected $fillable = [
        // identity
        'period', 'direction',
        // shipment core
        'client', 'hbl', 'mbl_no', 'bkg_no',
        'pol', 'pod', 'vol', 'container_type',
        'etd', 'eta', 'vessel_name', 'line', 'note',
        // status (8)
        'vgm', 'si', 'bl_draft', 'bl_confirm', 'obl', 'tlx', 'swb', 'shipment_done',
        // mua / NCC
        'purchase_note', 'payment_amount', 'supplier',
        'supplier_due_date',
        'report_close_date_increase', 'report_close_date_decrease',
        'supplier_paid_date',
        'cost_recognized', 'trucking_cost',
        'purchase_invoice_no', 'purchase_invoice_date',
        // agent
        'driver_hoa', 'agent_fee', 'agent_name', 'agent_fee_vnd',
        'agent_due_date', 'agent_paid_date',
        // bán / KH
        'sale_note', 'receivable_amount', 'customer', 'received_amount',
        'receivable_due_date', 'received_date',
        'revenue_recognized', 'sale_invoice_no', 'sale_invoice_date',
    ];

    protected $casts = [
        'etd'                   => 'date',
        'eta'                   => 'date',
        'supplier_due_date'         => 'date',
        'report_close_date_increase'=> 'date',
        'report_close_date_decrease'=> 'date',
        'supplier_paid_date'        => 'date',
        'purchase_invoice_date' => 'date',
        'agent_due_date'        => 'date',
        'agent_paid_date'       => 'date',
        'receivable_due_date'   => 'date',
        'received_date'         => 'date',
        'sale_invoice_date'     => 'date',

        'payment_amount'        => 'decimal:2',
        'cost_recognized'       => 'decimal:2',
        'trucking_cost'         => 'decimal:2',
        'agent_fee'             => 'decimal:2',
        'agent_fee_vnd'         => 'decimal:2',
        'receivable_amount'     => 'decimal:2',
        'received_amount'       => 'decimal:2',
        'revenue_recognized'    => 'decimal:2',
    ];

    public function scopeInPeriod(Builder $q, string $period): Builder
    {
        return $q->where('period', $period);
    }

    public function scopeOfDirection(Builder $q, string $direction): Builder
    {
        return $q->where('direction', $direction);
    }

    /**
     * Liên kết NCC qua tên (denormalized string).
     * Cho phép eager load: Shipment::with('supplierBalance') để lấy initial_balance + as_of_date.
     */
    public function supplierBalance(): BelongsTo
    {
        return $this->belongsTo(PayableInitialBalance::class, 'supplier', 'supplier');
    }
}
