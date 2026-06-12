<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một đợt khách thanh toán cho bảng kê. */
class TruckingStatementPayment extends Model
{
    protected $fillable = ['statement_id', 'date', 'amount', 'note', 'sort'];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'decimal:2',
        'sort'   => 'integer',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(TruckingStatement::class, 'statement_id');
    }
}
