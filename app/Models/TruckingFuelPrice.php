<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Bảng giá dầu — đơn giá (đồng/lít) theo khoảng ngày hiệu lực. */
class TruckingFuelPrice extends Model
{
    protected $fillable = ['from_date', 'to_date', 'price', 'note', 'sort'];

    protected $casts = [
        'from_date' => 'date',
        'to_date'   => 'date',
        'price'     => 'decimal:2',
        'sort'      => 'integer',
    ];
}
