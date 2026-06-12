<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một dòng trong bảng kê — snapshot lô tại thời điểm lập + phải thu. */
class TruckingStatementLine extends Model
{
    protected $fillable = [
        'statement_id', 'shipment_id', 'booking', 'sheet', 'io',
        'decl_no', 'cont_type', 'inv', 'cont_no', 'bks',
        'from_loc', 'to_loc', 'date', 'cont_label', 'phai_thu', 'cuoc', 'thanh_ly', 'note', 'detail', 'sort',
    ];

    protected $casts = [
        'date'     => 'date',
        'phai_thu' => 'decimal:2',
        'cuoc'     => 'decimal:2',
        'thanh_ly' => 'decimal:2',
        'detail'   => 'array',
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
