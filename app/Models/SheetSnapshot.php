<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SheetSnapshot extends Model
{
    protected $fillable = ['key', 'payload', 'version', 'updated_by'];

    protected $casts = [
        'payload'    => 'array',
        'version'    => 'integer',
        'updated_by' => 'integer',
    ];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
