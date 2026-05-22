<?php

namespace App\Models;

use App\Casts\CompressedJson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SheetSnapshot extends Model
{
    protected $fillable = ['key', 'payload', 'version', 'updated_by'];

    protected $casts = [
        'payload'    => CompressedJson::class,
        'version'    => 'integer',
        'updated_by' => 'integer',
    ];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
