<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast cho cột BLOB lưu JSON đã gzip (gzdeflate, level 6).
 *
 * - Khi đọc: thử gzinflate trước; nếu lỗi (data cũ chưa nén) fallback decode JSON thuần.
 * - Khi ghi: json_encode(UNESCAPED_UNICODE) rồi gzdeflate → cắt ~80–90% so với JSON raw.
 *
 * Dùng cho snapshot Luckysheet (payload có thể 3–11 MB) — sau gzip còn ~300 KB.
 */
class CompressedJson implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') return null;

        // Backward compat: data cũ ghi dạng JSON raw (longText) — gzinflate sẽ fail, fallback decode trực tiếp.
        $decompressed = @gzinflate($value);
        if ($decompressed === false) {
            $decompressed = $value;
        }

        $decoded = json_decode($decompressed, true);
        return $decoded === null && json_last_error() !== JSON_ERROR_NONE ? null : $decoded;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) return [$key => null];

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return [$key => gzdeflate($json, 6)];
    }
}
