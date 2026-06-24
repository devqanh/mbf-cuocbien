<?php

namespace App\Models;

use App\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bảng kê xe ngoài (phải trả nhà xe thuê) — gom nhiều lô của 1 nhà xe theo kỳ (Giờ xe đến). */
class TruckingExtStatement extends Model
{
    use HasHashid;

    protected $fillable = [
        'no', 'ext_vendor', 'date', 'period_from', 'period_to', 'total', 'note',
    ];

    protected $casts = [
        'date'        => 'date',
        'period_from' => 'date',
        'period_to'   => 'date',
        'total'       => 'decimal:2',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(TruckingExtStatementLine::class, 'ext_statement_id')->orderBy('sort');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TruckingExtStatementPayment::class, 'ext_statement_id')->orderBy('sort');
    }
}
