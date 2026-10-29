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

    /**
     * Dạng biển CHUẨN để LƯU (cùng format danh mục xe): viết hoa, bỏ chấm/cách, gạch đúng chỗ.
     *   "15C 15877" / "15c-158.77" / "15C15877" → "15C-15877".
     * Chuỗi không phải 1 biển (vd 2 biển "50E-36177 - 50H-16679") → chỉ viết hoa + gộp khoảng trắng.
     * null nếu rỗng.
     */
    public static function canonical(?string $plate): ?string
    {
        $p = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', (string) $plate) ?? ''));
        if ($p === '') return null;
        if (preg_match('/^(\d{2}[A-Z]{1,2})(\d+)$/', preg_replace('/[\s.\-]+/u', '', $p) ?? '', $m)) {
            return $m[1] . '-' . $m[2];
        }
        return $p;
    }
}
