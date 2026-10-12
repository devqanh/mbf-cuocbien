<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Chi cho lái xe theo ngày + xe (lộ trình). Tiền tính lại từ Phí tuyến — bảng chỉ lưu lái nhận + đã chi. */
class TruckingRoutePay extends Model
{
    protected $fillable = [
        'work_date', 'bks', 'vehicle_id', 'driver', 'driver_id', 'paid', 'paid_date', 'note', 'extra_items', 'trip_overrides',
        'frozen', 'frozen_at', 'frozen_data', 'updated_by',
    ];

    protected $casts = [
        'work_date' => 'date',
        'paid'      => 'boolean',
        'paid_date' => 'date',
        'vehicle_id' => 'integer',
        'driver_id'  => 'integer',
        'extra_items' => 'array',
        'trip_overrides' => 'array',   // chỉnh tay theo chuyến: {cont: {liters?, choho?: [khóa khoản]}}
        'frozen'      => 'boolean',
        'frozen_at'   => 'datetime',
        'frozen_data' => 'array',
    ];
}
