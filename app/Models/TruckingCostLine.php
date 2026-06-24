<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một khoản chi phí của lô — số tiền, người chi, tích chi hộ, màu theo dõi. */
class TruckingCostLine extends Model
{
    protected $fillable = [
        'shipment_id', 'item', 'cost_item_id', 'amount', 'vat', 'invoice_no', 'payer', 'payer_id', 'date', 'billable', 'color', 'src', 'note', 'sort',
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'vat'      => 'decimal:2',
        'date'     => 'date',
        'billable' => 'boolean',
        'sort'     => 'integer',
    ];

    /** Chi phí NET (trước VAT) = số tiền (gồm VAT) ÷ (1 + vat/100). */
    public function netAmount(): float
    {
        $v = (float) $this->vat;
        $a = (float) $this->amount;
        return $v > 0 ? round($a / (1 + $v / 100)) : $a;
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(TruckingShipment::class, 'shipment_id');
    }
}
