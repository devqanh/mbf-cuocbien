<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Đội xe — biển số + loại (Xe MBF | Xe ngoài). */
class TruckingVehicle extends Model
{
    protected $fillable = ['plate', 'type'];
}
