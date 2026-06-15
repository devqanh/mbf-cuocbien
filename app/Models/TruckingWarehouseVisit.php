<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Lịch sử xe ghé kho (geofence visit) — xem migration 2026_06_16_000001. */
class TruckingWarehouseVisit extends Model
{
    protected $fillable = [
        'provider', 'gps_ref', 'gps_plate', 'vehicle_id', 'vehicle_plate', 'driver',
        'warehouse_id', 'warehouse_name', 'arrived_at', 'last_inside_at', 'departed_at',
        'confirmed', 'min_dist_m',
    ];

    protected $casts = [
        'arrived_at'     => 'datetime',
        'last_inside_at' => 'datetime',
        'departed_at'    => 'datetime',
        'confirmed'      => 'boolean',
        'min_dist_m'     => 'integer',
    ];
}
