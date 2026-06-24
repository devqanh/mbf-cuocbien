<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** 1 bảng giá (price book) của khách — 1 phiên bản giá áp cho khoảng ngày [period_from, period_to]. */
class TruckingPriceBook extends Model
{
    protected $fillable = ['customer_id', 'label', 'period_from', 'period_to', 'sort'];

    protected $casts = [
        'period_from' => 'date',
        'period_to'   => 'date',
        'sort'        => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(TruckingCustomer::class, 'customer_id');
    }

    public function priceRows(): HasMany
    {
        return $this->hasMany(TruckingPriceRow::class, 'price_book_id')->orderBy('sort');
    }
}
