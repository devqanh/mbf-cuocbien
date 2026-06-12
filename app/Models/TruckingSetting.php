<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Cấu hình key/value: vat_default_hph, vat_default_icd, free_time_hours… */
class TruckingSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Đọc 1 setting, trả về $default nếu chưa có. */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    /** Ghi (upsert) 1 setting. */
    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
