<?php

namespace App\Models;

use App\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Đội xe — biển số + loại (Xe MBF | Xe ngoài) + số cầu + lái xe mặc định. */
class TruckingVehicle extends Model
{
    use HasHashid;

    protected $fillable = ['plate', 'type', 'kind', 'axle', 'gps_ref', 'driver_id', 'info', 'documents', 'allowances'];

    /** Lái xe mặc định (Cài đặt → Biển số xe) — theo id vì tên lái xe có thể trùng. */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(TruckingDriver::class, 'driver_id');
    }

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
