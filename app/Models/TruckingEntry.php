<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Một dòng trucking. Phân biệt sheet qua cột `sheet` ('hph' | 'icd').
 *
 * Fillable / casts / nhóm field (date, decimal, text) đều SUY RA TỪ
 * config('trucking_columns') → không hardcode, không drift với migration & view.
 */
class TruckingEntry extends Model
{
    public const SHEET_HPH = 'hph';
    public const SHEET_ICD = 'icd';

    /** Memoize meta suy ra từ config (per-process). */
    private static ?array $metaCache = null;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $meta = self::meta();

        // fillable = mọi key cột (trừ id) + sheet + cell_formulas
        $this->mergeFillable([...$meta['keys'], 'sheet', 'cell_formulas']);

        // casts
        $casts = ['cell_formulas' => 'array'];
        foreach ($meta['dates'] as $f)    $casts[$f] = 'date';
        foreach ($meta['decimals'] as $f) $casts[$f] = 'decimal:2';
        $this->mergeCasts($casts);
    }

    /**
     * Quét config 1 lần → trả về danh sách key/date/decimal/text (union 2 sheet).
     *
     * @return array{keys: string[], dates: string[], decimals: string[], texts: string[]}
     */
    public static function meta(): array
    {
        if (self::$metaCache !== null) return self::$metaCache;

        $cols = config('trucking_columns', []);
        $keys = $dates = $decimals = $texts = [];

        foreach ($cols as $list) {
            foreach ($list as $c) {
                $k = $c['key'] ?? null;
                if (! $k || $k === 'id') continue;
                $keys[$k] = true;

                $type = $c['type'] ?? 'text';
                if ($type === 'date')                          $dates[$k] = true;
                elseif ($type === 'vnd' || $type === 'number') $decimals[$k] = true;
                else                                           $texts[$k] = true;
            }
        }

        return self::$metaCache = [
            'keys'     => array_keys($keys),
            'dates'    => array_keys($dates),
            'decimals' => array_keys($decimals),
            'texts'    => array_keys($texts),
        ];
    }

    /** @return string[] cột kiểu ngày (union 2 sheet) */
    public static function dateFields(): array { return self::meta()['dates']; }

    /** @return string[] cột kiểu số/tiền */
    public static function decimalFields(): array { return self::meta()['decimals']; }

    /** @return string[] cột kiểu text */
    public static function textFields(): array { return self::meta()['texts']; }

    public function scopeOfSheet(Builder $q, string $sheet): Builder
    {
        return $q->where('sheet', $sheet);
    }
}
