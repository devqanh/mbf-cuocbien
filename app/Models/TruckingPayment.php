<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một đợt khách thanh toán cho lô. */
class TruckingPayment extends Model
{
    protected $fillable = ['shipment_id', 'amount', 'date', 'note', 'sort'];

    protected $casts = [
        'amount' => 'decimal:2',
        'date'   => 'date',
        'sort'   => 'integer',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(TruckingShipment::class, 'shipment_id');
    }
}
