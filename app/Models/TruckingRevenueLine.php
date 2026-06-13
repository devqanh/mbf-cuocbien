<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dòng doanh thu hoặc thu chi hộ của lô. kind: 'doanhThu' | 'choHo'. */
class TruckingRevenueLine extends Model
{
    public const KIND_REVENUE = 'doanhThu';
    public const KIND_COLLECT = 'choHo';

    protected $fillable = ['shipment_id', 'kind', 'item', 'item_id', 'amount', 'sort'];

    protected $casts = [
        'amount' => 'decimal:2',
        'sort'   => 'integer',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(TruckingShipment::class, 'shipment_id');
    }
}
