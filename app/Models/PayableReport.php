<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayableReport extends Model
{
    protected $fillable = ['report_date', 'increase_date', 'decrease_date', 'note', 'created_by'];

    protected $casts = [
        'report_date'   => 'date',
        'increase_date' => 'date',
        'decrease_date' => 'date',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PayableReportLine::class, 'report_id')->orderBy('supplier');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
