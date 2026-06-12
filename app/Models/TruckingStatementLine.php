<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một dòng trong bảng kê — snapshot lô tại thời điểm lập + phải thu. */
class TruckingStatementLine extends Model
{
    protected $fillable = [
        'statement_id', 'shipment_id', 'booking', 'sheet', 'io',
        'from_loc', 'to_loc', 'date', 'cont_label', 'phai_thu', 'sort',
    ];

    protected $casts = [
        'date'     => 'date',
        'phai_thu' => 'decimal:2',
        'sort'     => 'integer',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(TruckingStatement::class, 'statement_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(TruckingShipment::class, 'shipment_id');
    }
}
