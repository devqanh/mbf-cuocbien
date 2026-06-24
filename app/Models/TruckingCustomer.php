<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Khách hàng trucking — master data (MST, liên hệ, hạn TT, bảng giá). */
class TruckingCustomer extends Model
{
    protected $fillable = [
        'name', 'short_name', 'tax_code', 'phone', 'contact', 'email',
        'term_days', 'address', 'note',
    ];

    protected $casts = ['term_days' => 'integer'];

    public function priceRows(): HasMany
    {
        return $this->hasMany(TruckingPriceRow::class, 'customer_id')->orderBy('sort');
    }

    public function priceBooks(): HasMany
    {
        return $this->hasMany(TruckingPriceBook::class, 'customer_id')->orderBy('period_from');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(TruckingShipment::class, 'customer_id');
    }
}
