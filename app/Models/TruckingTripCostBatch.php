<?php

namespace App\Models;

use App\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Phí xe nội bộ — 1 kỳ/đợt (snapshot nhiều lô theo "ngày xe ra"). */
class TruckingTripCostBatch extends Model
{
    use HasHashid;

    protected $fillable = [
        'no', 'name', 'date', 'period_from', 'period_to', 'total', 'note',
    ];

    protected $casts = [
        'date'        => 'date',
        'period_from' => 'date',
        'period_to'   => 'date',
        'total'       => 'decimal:2',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(TruckingTripCostLine::class, 'batch_id')->orderBy('sort');
    }
}
