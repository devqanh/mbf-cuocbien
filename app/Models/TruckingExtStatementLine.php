<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một dòng trong bảng kê xe ngoài — snapshot lô tại thời điểm lập + cước phải trả nhà xe. */
class TruckingExtStatementLine extends Model
{
    protected $fillable = [
        'ext_statement_id', 'shipment_id', 'booking', 'customer', 'sheet', 'bks',
        'from_loc', 'to_loc', 'cont_label', 'date', 'fee', 'choho', 'choho_note', 'vat_rate', 'note', 'sort',
    ];

    protected $casts = [
        'date'     => 'date',
        'fee'      => 'decimal:2',
        'choho'    => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'sort'     => 'integer',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(TruckingExtStatement::class, 'ext_statement_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(TruckingShipment::class, 'shipment_id');
    }
}
