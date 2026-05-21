<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SheetSnapshot extends Model
{
    protected $fillable = ['key', 'payload'];

    protected $casts = [
        'payload' => 'array',
    ];
}
