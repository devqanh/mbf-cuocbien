<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một dòng bảng giá đã gửi của 1 khách hàng. */
class TruckingPriceRow extends Model
{
    protected $fillable = [
        'customer_id', 'location_id', 'loc', 'conn', 'kind',
        'from', 'to1', 'to2', 'to3', 'to4',
        'distance', 'trans_fee_40', 'trans_fee_20', 'fuel_fee_40', 'fuel_fee_20', 'sort',
    ];

    protected $casts = [
        'trans_fee_40' => 'decimal:2',
        'trans_fee_20' => 'decimal:2',
        'fuel_fee_40'  => 'decimal:2',
        'fuel_fee_20'  => 'decimal:2',
        'sort'         => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(TruckingCustomer::class, 'customer_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(TruckingLocation::class, 'location_id');
    }
}
