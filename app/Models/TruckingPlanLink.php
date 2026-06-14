<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Link kế hoạch công khai — lái xe cập nhật giờ xe đến/ra theo khoảng "giờ đến dự kiến". */
class TruckingPlanLink extends Model
{
    protected $fillable = ['token', 'title', 'from_date', 'to_date', 'active', 'created_by'];

    protected $casts = [
        'from_date' => 'date',
        'to_date'   => 'date',
        'active'    => 'boolean',
    ];

    public static function newToken(): string
    {
        do { $t = Str::lower(Str::random(24)); } while (static::where('token', $t)->exists());
        return $t;
    }
}
