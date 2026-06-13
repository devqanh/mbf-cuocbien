<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Đội xe — biển số + loại (Xe MBF | Xe ngoài) + số cầu. */
class TruckingVehicle extends Model
{
    protected $fillable = ['plate', 'type', 'axle', 'info', 'documents', 'allowances'];

    protected $casts = [
        'info'       => 'array',
        'documents'  => 'array',
        'allowances' => 'array',
    ];

    public function vehicleUsages(): HasMany
    {
        return $this->hasMany(TruckingVehicleUsage::class, 'vehicle_id')->orderBy('sort');
    }

    public function vehicleCosts(): HasMany
    {
        return $this->hasMany(TruckingVehicleCost::class, 'vehicle_id')->orderBy('sort');
    }

    public function vehicleDepreciations(): HasMany
    {
        return $this->hasMany(TruckingVehicleDepreciation::class, 'vehicle_id')->orderBy('sort');
    }
}
