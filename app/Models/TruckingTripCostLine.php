<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 1 dòng phí xe trong kỳ — snapshot phí của 1 lô lúc lập. */
class TruckingTripCostLine extends Model
{
    protected $fillable = [
        'batch_id', 'shipment_id', 'booking', 'route', 'kho', 'bks', 'axle', 'date',
        'driver', 've_tram', 'tien_duong', 'tro_cap', 'phi_khac', 'cru', 'luong', 'salary_parts',
        'fuel_liters', 'fuel_price', 'extras', 'salary_extras', 'line_total',
        'salary_total', 'cost_total', 'fuel_amount', 'driver_id', 'vehicle_id', 'note', 'sort',
    ];

    protected $casts = [
        'date'        => 'date',
        'cru'         => 'boolean',
        've_tram'     => 'decimal:2',
        'tien_duong'  => 'decimal:2',
        'tro_cap'     => 'decimal:2',
        'phi_khac'    => 'decimal:2',
        'luong'       => 'decimal:2',
        'fuel_liters' => 'decimal:2',
        'fuel_price'  => 'decimal:2',
        'line_total'  => 'decimal:2',
        'extras'        => 'array',
        'salary_parts'  => 'array',
        'salary_extras' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TruckingTripCostBatch::class, 'batch_id');
    }
}
