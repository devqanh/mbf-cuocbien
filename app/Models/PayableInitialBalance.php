<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayableInitialBalance extends Model
{
    protected $fillable = ['supplier', 'opening_amount', 'as_of_date', 'note', 'updated_by'];

    protected $casts = [
        'opening_amount' => 'decimal:2',
        'as_of_date'     => 'date',
    ];
}
