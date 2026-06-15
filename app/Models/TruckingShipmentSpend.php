<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Khoản THỰC CHI của 1 lô (duyệt chi theo biển kiểm soát). */
class TruckingShipmentSpend extends Model
{
    protected $fillable = [
        'shipment_id', 'vehicle_id', 'bks', 'driver', 'source', 'kind',
        'name', 'amount', 'spend_date', 'paid', 'paid_date', 'note', 'created_by', 'sort',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'spend_date' => 'date',
        'paid'       => 'boolean',
        'paid_date'  => 'date',
        'sort'       => 'integer',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(TruckingShipment::class, 'shipment_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(TruckingVehicle::class, 'vehicle_id');
    }
}
