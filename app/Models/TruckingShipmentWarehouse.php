<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kho của 1 lô (mỗi kho 1 dòng) — để báo cáo theo kho/tuyến chuẩn. */
class TruckingShipmentWarehouse extends Model
{
    protected $fillable = ['shipment_id', 'warehouse_id', 'name', 'sort'];
    protected $casts = ['sort' => 'integer'];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(TruckingShipment::class, 'shipment_id');
    }
}
