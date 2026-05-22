<?php

namespace App\Models;

use App\Casts\CompressedJson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SheetSnapshotHistory extends Model
{
    protected $table = 'sheet_snapshot_history';

    /** Chỉ có created_at, không có updated_at (history immutable) */
    public $timestamps = false;

    protected $fillable = [
        'snapshot_key', 'version', 'payload', 'editor_id', 'created_at',
    ];

    protected $casts = [
        'payload'    => CompressedJson::class,
        'version'    => 'integer',
        'editor_id'  => 'integer',
        'created_at' => 'datetime',
    ];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }
}
