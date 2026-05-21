<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'price',
        'stock',
        'unit',
        'note',
        'is_active',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'stock'     => 'integer',
        'is_active' => 'boolean',
    ];
}
