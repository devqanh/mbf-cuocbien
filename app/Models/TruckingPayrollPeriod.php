<?php

namespace App\Models;

use App\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Model;

/** Kỳ lương lái xe (snapshot) — gom theo biển số xe qua khoảng ngày. */
class TruckingPayrollPeriod extends Model
{
    use HasHashid;

    protected $fillable = [
        'no', 'name', 'period_from', 'period_to', 'total', 'paid_daily', 'locked', 'locked_at', 'lines', 'note', 'created_by',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to'   => 'date',
        'total'       => 'decimal:2',
        'paid_daily'  => 'decimal:2',
        'locked'      => 'boolean',
        'locked_at'   => 'datetime',
        'lines'       => 'array',
    ];
}
