<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Hạng mục khấu hao xe = nguyên giá/(30×số tháng) × số ngày dùng. */
class TruckingVehicleDepreciation extends Model
{
    protected $fillable = ['vehicle_id', 'name', 'orig_price', 'start_date', 'months', 'sort'];
    protected $casts = ['orig_price' => 'decimal:2', 'start_date' => 'date', 'months' => 'integer', 'sort' => 'integer'];
}
