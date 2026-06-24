<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một đợt thanh toán cho nhà xe ngoài (trừ vào công nợ bảng kê xe ngoài). */
class TruckingExtStatementPayment extends Model
{
    protected $fillable = ['ext_statement_id', 'date', 'amount', 'note', 'sort'];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'decimal:2',
        'sort'   => 'integer',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(TruckingExtStatement::class, 'ext_statement_id');
    }
}
