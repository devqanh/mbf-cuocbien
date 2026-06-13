<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Phí tuyến đường — định mức phí/dầu/km cho mỗi tuyến (tập kho). */
class TruckingRouteFee extends Model
{
    protected $fillable = [
        'route', 'route_key', 've_tram', 'tien_duong', 'tro_cap', 'phi_khac',
        'cru', 'luong', 'salary_parts', 'km', 'dau_2cau', 'dau_1cau', 'sort',
    ];

    protected $casts = [
        'cru'          => 'boolean',
        'salary_parts' => 'array',
        've_tram'    => 'decimal:2',
        'tien_duong' => 'decimal:2',
        'tro_cap'    => 'decimal:2',
        'phi_khac'   => 'decimal:2',
        'luong'      => 'decimal:2',
        'km'         => 'decimal:2',
        'dau_2cau'   => 'decimal:2',
        'dau_1cau'   => 'decimal:2',
        'sort'       => 'integer',
    ];
}
