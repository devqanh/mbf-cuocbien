<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * 34 tỉnh/thành Việt Nam sau sáp nhập 01/07/2025 + tên cũ → tỉnh mới.
 * Dùng để gán Tỉnh cho Kho (Cài đặt → Kho) và khớp Phí tuyến dạng "Cảng → Tỉnh → Cảng" ở Lộ trình.
 */
final class VnProvinces
{
    public const LIST = [
        'Hà Nội', 'TP. Hồ Chí Minh', 'Hải Phòng', 'Đà Nẵng', 'Cần Thơ', 'Huế',
        'Lai Châu', 'Điện Biên', 'Sơn La', 'Lào Cai', 'Tuyên Quang', 'Thái Nguyên', 'Lạng Sơn', 'Cao Bằng',
        'Quảng Ninh', 'Bắc Ninh', 'Phú Thọ', 'Hưng Yên', 'Ninh Bình', 'Thanh Hóa', 'Nghệ An', 'Hà Tĩnh',
        'Quảng Trị', 'Quảng Ngãi', 'Gia Lai', 'Khánh Hòa', 'Đắk Lắk', 'Lâm Đồng', 'Đồng Nai', 'Tây Ninh',
        'Vĩnh Long', 'Đồng Tháp', 'An Giang', 'Cà Mau',
    ];

    /** Tên cũ (trước sáp nhập) / tên gọi khác → tỉnh mới — để đoán tỉnh từ địa chỉ cũ. */
    public const ALIASES = [
        'Hồ Chí Minh' => 'TP. Hồ Chí Minh', 'Sài Gòn' => 'TP. Hồ Chí Minh', 'Bình Dương' => 'TP. Hồ Chí Minh',
        'Bà Rịa' => 'TP. Hồ Chí Minh', 'Vũng Tàu' => 'TP. Hồ Chí Minh',
        'Hải Dương' => 'Hải Phòng', 'Quảng Nam' => 'Đà Nẵng', 'Hậu Giang' => 'Cần Thơ', 'Sóc Trăng' => 'Cần Thơ',
        'Thừa Thiên Huế' => 'Huế', 'Yên Bái' => 'Lào Cai', 'Hà Giang' => 'Tuyên Quang', 'Bắc Kạn' => 'Thái Nguyên',
        'Bắc Giang' => 'Bắc Ninh', 'Vĩnh Phúc' => 'Phú Thọ', 'Hòa Bình' => 'Phú Thọ', 'Thái Bình' => 'Hưng Yên',
        'Hà Nam' => 'Ninh Bình', 'Nam Định' => 'Ninh Bình', 'Quảng Bình' => 'Quảng Trị', 'Kon Tum' => 'Quảng Ngãi',
        'Bình Định' => 'Gia Lai', 'Ninh Thuận' => 'Khánh Hòa', 'Phú Yên' => 'Đắk Lắk', 'Bình Thuận' => 'Lâm Đồng',
        'Đắk Nông' => 'Lâm Đồng', 'Bình Phước' => 'Đồng Nai', 'Long An' => 'Tây Ninh', 'Bến Tre' => 'Vĩnh Long',
        'Trà Vinh' => 'Vĩnh Long', 'Tiền Giang' => 'Đồng Tháp', 'Kiên Giang' => 'An Giang', 'Bạc Liêu' => 'Cà Mau',
    ];

    /** Chuẩn hóa để so khớp: bỏ dấu, chỉ giữ chữ + số, in hoa ("TP. Hồ Chí Minh" → "TPHOCHIMINH"). */
    public static function norm(?string $v): string
    {
        return mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii((string) $v)) ?? '');
    }

    /** Tên tỉnh CHUẨN nếu $v là 1 tỉnh (tên mới, có/không "TP.", hoặc tên cũ); ngược lại null. */
    public static function canonical(?string $v): ?string
    {
        $n = self::norm($v);
        if ($n === '') return null;
        foreach (self::LIST as $p) {
            if (self::norm($p) === $n || self::norm(preg_replace('/^TP\.?\s*/u', '', $p)) === $n) return $p;
        }
        foreach (self::ALIASES as $old => $new) {
            if (self::norm($old) === $n) return $new;
        }
        return null;
    }

    public static function isProvince(?string $v): bool
    {
        return self::canonical($v) !== null;
    }

    /**
     * Đoán tỉnh từ địa chỉ: tìm tên tỉnh (mới hoặc cũ) xuất hiện trong địa chỉ đã chuẩn hóa — tên dài khớp trước
     * ("Hà Nam" không nhầm "Hà Nội"). Chỉ là GỢI Ý để người dùng bấm điền, không tự ghi.
     */
    public static function guess(?string $address): ?string
    {
        $a = self::norm($address);
        if ($a === '') return null;
        $cands = [];
        foreach (self::LIST as $p) {
            $cands[] = [self::norm($p), $p];
            $cands[] = [self::norm(preg_replace('/^TP\.?\s*/u', '', $p)), $p];
        }
        foreach (self::ALIASES as $old => $new) $cands[] = [self::norm($old), $new];
        $cands = array_values(array_filter($cands, fn ($c) => strlen($c[0]) >= 4));
        usort($cands, fn ($x, $y) => strlen($y[0]) <=> strlen($x[0]));
        foreach ($cands as [$k, $name]) {
            if (str_contains($a, $k)) return $name;
        }
        return null;
    }
}
