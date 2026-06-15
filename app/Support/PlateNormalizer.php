<?php

namespace App\Support;

/**
 * Chuẩn hóa biển số để so khớp giữa các nguồn (GPS Viettel/dvbk ⇄ trucking_vehicles).
 * Bỏ mọi ký tự không phải chữ/số + viết HOA. Vd:
 *   "29E-384.41" → "29E38441", "29H 81607" → "29H81607".
 */
class PlateNormalizer
{
    public static function norm(?string $plate): string
    {
        $p = mb_strtoupper(trim((string) $plate));
        // Bỏ dấu cách, gạch, chấm… chỉ giữ A-Z 0-9 (đã uppercase nên không cần a-z).
        return preg_replace('/[^A-Z0-9]/u', '', $p) ?? '';
    }
}
