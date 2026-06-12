<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bảng kê cần thu — gom nhiều lô của 1 khách theo kỳ (ngày cont ra). */
class TruckingStatement extends Model
{
    protected $fillable = [
        'no', 'customer_id', 'customer_name', 'info',
        'date', 'period_from', 'period_to', 'total',
    ];

    protected $casts = [
        'info'        => 'array',
        'date'        => 'date',
        'period_from' => 'date',
        'period_to'   => 'date',
        'total'       => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(TruckingCustomer::class, 'customer_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TruckingStatementLine::class, 'statement_id')->orderBy('sort');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TruckingStatementPayment::class, 'statement_id')->orderBy('sort');
    }
}
