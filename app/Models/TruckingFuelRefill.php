<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Phiếu đổ dầu — ghi nhận lượng dầu thực tế nạp vào xe. */
class TruckingFuelRefill extends Model
{
    protected $fillable = [
        'vehicle_id', 'refill_date', 'liters', 'unit_price', 'total_cost',
        'odometer_km', 'station', 'note', 'created_by',
    ];

    protected $casts = [
        'refill_date' => 'date',
        'liters'      => 'decimal:2',
        'unit_price'  => 'decimal:2',
        'total_cost'  => 'decimal:2',
        'odometer_km' => 'decimal:2',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(TruckingVehicle::class, 'vehicle_id');
    }
}
