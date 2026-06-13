<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Thời gian lái xe sử dụng 1 xe (gán thủ công). */
class TruckingVehicleUsage extends Model
{
    protected $fillable = ['vehicle_id', 'driver', 'from_date', 'to_date', 'note', 'sort'];
    protected $casts = ['from_date' => 'date', 'to_date' => 'date', 'sort' => 'integer'];
}
