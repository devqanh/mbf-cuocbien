<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayableReportLine extends Model
{
    protected $fillable = [
        'report_id', 'supplier',
        'opening_balance', 'increase_amount', 'decrease_amount', 'closing_balance',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'increase_amount' => 'decimal:2',
        'decrease_amount' => 'decimal:2',
        'closing_balance' => 'decimal:2',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(PayableReport::class, 'report_id');
    }
}
