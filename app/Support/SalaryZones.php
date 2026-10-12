<?php

namespace App\Support;

/**
 * Quy chế lương chuyến xét theo TỈNH CŨ (63 tỉnh trước sáp nhập 2025), riêng Hưng Yên tách "chưa qua trạm" /
 * "qua trạm". Kho gán tỉnh cũ (`trucking_warehouses.old_province`). VÙNG lương + NHÓM CẢNG XUẤT PHÁT là DỮ LIỆU
 * TÙY CHỈNH của từng bảng phí tuyến (book.regulation) — Quy chế 03/2026 chỉ là mẫu mặc định (defaultRegulation).
 * "Ngoài Hải Phòng" (trợ cấp quay đầu) = tỉnh cũ khác Hải Phòng.
 */
class SalaryZones
{
    public const HAI_PHONG = 'Hải Phòng';
    public const HY_CHUA_TRAM = 'Hưng Yên (chưa qua trạm – Phố Nối…)';
    public const HY_QUA_TRAM = 'Hưng Yên (qua trạm)';

    /** 63 tỉnh cũ (Hưng Yên tách 2) — danh sách chọn ở Cài đặt › Kho và ô tỉnh của vùng. Tỉnh trong quy chế xếp trước. */
    public const OLD_PROVINCES = [
        self::HAI_PHONG, 'Hải Dương', self::HY_CHUA_TRAM, 'Thái Bình', self::HY_QUA_TRAM,
        'Hà Nội', 'Bắc Ninh', 'Bắc Giang', 'Hà Nam', 'Ninh Bình', 'Nam Định',
        'Thái Nguyên', 'Phú Thọ', 'Hòa Bình', 'Vĩnh Phúc', 'Thanh Hóa', 'Lạng Sơn', 'Tuyên Quang', 'Bắc Kạn',
        'Yên Bái', 'Nghệ An', 'Cao Bằng', 'Hà Giang',
        'Quảng Ninh', 'Lào Cai', 'Lai Châu', 'Điện Biên', 'Sơn La', 'Hà Tĩnh', 'Quảng Bình', 'Quảng Trị',
        'Thừa Thiên Huế', 'Đà Nẵng', 'Quảng Nam', 'Quảng Ngãi', 'Bình Định', 'Phú Yên', 'Khánh Hòa', 'Ninh Thuận',
        'Bình Thuận', 'Kon Tum', 'Gia Lai', 'Đắk Lắk', 'Đắk Nông', 'Lâm Đồng', 'Bình Phước', 'Tây Ninh', 'Bình Dương',
        'Đồng Nai', 'Bà Rịa - Vũng Tàu', 'TP. Hồ Chí Minh', 'Long An', 'Tiền Giang', 'Bến Tre', 'Trà Vinh', 'Vĩnh Long',
        'Đồng Tháp', 'An Giang', 'Kiên Giang', 'Cần Thơ', 'Hậu Giang', 'Sóc Trăng', 'Bạc Liêu', 'Cà Mau',
    ];

    /**
     * Từ khóa địa danh trong ĐỊA CHỈ → tỉnh cũ (GỢI Ý, bấm mới điền). Phố Nối trước Hưng Yên; mọi tỉnh khác trước
     * Hải Phòng (địa chỉ sau sáp nhập có thể ghi "TP Hải Phòng" cho vùng Hải Dương cũ). "Hưng Yên" không kèm
     * Phố Nối → gợi ý "qua trạm" (người dùng sửa nếu sai). FE dùng chung bảng này.
     */
    public static function keywords(): array
    {
        $kw = [['Phố Nối', self::HY_CHUA_TRAM], ['Hưng Yên', self::HY_QUA_TRAM], ['Hòa Bình', 'Hòa Bình'], ['Hoà Bình', 'Hòa Bình'],
               ['Thanh Hoá', 'Thanh Hóa'], ['Bắc Cạn', 'Bắc Kạn'], ['Hồ Chí Minh', 'TP. Hồ Chí Minh'], ['Vũng Tàu', 'Bà Rịa - Vũng Tàu'],
               ['Huế', 'Thừa Thiên Huế'], ['Khánh Hoà', 'Khánh Hòa']];
        foreach (self::OLD_PROVINCES as $p) {
            if (in_array($p, [self::HAI_PHONG, self::HY_CHUA_TRAM, self::HY_QUA_TRAM], true)) continue;
            $kw[] = [$p, $p];
        }
        $kw[] = [self::HAI_PHONG, self::HAI_PHONG];
        return $kw;
    }

    /**
     * MẪU quy chế theo Phụ lục 03/2026 (07/09/2026) — nút "Nạp mặc định" ở Cài đặt › Phí tuyến; mọi phần sửa được.
     *  - originGroups: nhóm cảng xuất phát {key, name, mode: zone = bảng vùng theo tỉnh cũ của kho | kcn = bảng KCN
     *    theo ký hiệu kho, codes: ký hiệu cảng}.
     *  - zones: vùng {name, provinces: tỉnh cũ, luong, phuCap, long: tuyến dài}.
     *  - distances: chặng {from, to, km, toll = vé trạm chi hộ (mẫu chưa có, kế toán tự điền)} — km theo sổ tay kế toán,
     *    theo CHIỀU: không có chiều ngược thì dùng chung 2 chiều;
     *    Tiên Sơn → Thăng Long 40 khác Thăng Long → Tiên Sơn 35); Tri Phương ↔ KCN = nửa km khứ hồi bảng TP
     *    (chuyến HP → QV → TP tính "nửa HP–QV–HP + nửa TP–QV–TP").
     */
    public static function defaultRegulation(): array
    {
        $z = fn ($name, $provs, $l, $p, $long) => ['name' => $name, 'provinces' => $provs, 'luong' => $l, 'phuCap' => $p, 'long' => $long];
        return [
            'originGroups' => [
                ['key' => 'hp',  'name' => 'Hải Phòng',          'mode' => 'zone', 'codes' => ['HPP', 'LHP']],
                ['key' => 'icd', 'name' => 'ICD Quế Võ',         'mode' => 'kcn',  'codes' => ['ICDQV']],
                ['key' => 'tp',  'name' => 'Sowatco Tri Phương', 'mode' => 'kcn',  'codes' => ['ICDTP']],
            ],
            'zones' => [
                $z('Hải Phòng', [self::HAI_PHONG], 100000, 300000, false),
                $z('Hải Dương, Hưng Yên chưa qua trạm', ['Hải Dương', self::HY_CHUA_TRAM], 200000, 400000, false),
                $z('Thái Bình', ['Thái Bình'], 250000, 400000, true),
                $z('Hưng Yên qua trạm', [self::HY_QUA_TRAM], 250000, 450000, true),
                $z('Hà Nội, Bắc Ninh, Bắc Giang…', ['Hà Nội', 'Bắc Ninh', 'Bắc Giang', 'Hà Nam', 'Ninh Bình', 'Nam Định'], 300000, 500000, true),
                $z('Thái Nguyên, Phú Thọ, Hòa Bình…', ['Thái Nguyên', 'Phú Thọ', 'Hòa Bình', 'Vĩnh Phúc', 'Thanh Hóa', 'Lạng Sơn', 'Tuyên Quang', 'Bắc Kạn'], 450000, 600000, true),
                $z('Yên Bái, Nghệ An, Cao Bằng, Hà Giang', ['Yên Bái', 'Nghệ An', 'Cao Bằng', 'Hà Giang'], 650000, 800000, true),
            ],
            'kcn' => [
                ['group' => 'icd', 'kho' => 'QV', 'luong' => 150000, 'phuCap' => 200000, 'km' => 40],
                ['group' => 'icd', 'kho' => 'TS', 'luong' => 150000, 'phuCap' => 250000, 'km' => 64],
                ['group' => 'icd', 'kho' => 'TL', 'luong' => 200000, 'phuCap' => 300000, 'km' => 150],
                ['group' => 'tp',  'kho' => 'QV', 'luong' => 150000, 'phuCap' => 200000, 'km' => 60],
                ['group' => 'tp',  'kho' => 'TS', 'luong' => 150000, 'phuCap' => 200000, 'km' => 40],
                ['group' => 'tp',  'kho' => 'TL', 'luong' => 250000, 'phuCap' => 200000, 'km' => 130],
            ],
            'fuel' => ['axle1' => ['hr' => 26, 'hh' => 30], 'axle2' => ['hr' => 35, 'hh' => 40]],
            // Trợ cấp theo ngày (chi trong ngày, tính lương): quay đầu tuyến ngoài HP (mỗi lần) · chuyến thứ 3 trong ngày.
            'allowances' => ['turnaround' => 100000, 'thirdTrip' => 100000],
            'distances' => array_map(fn ($r) => ['from' => $r[0], 'to' => $r[1], 'km' => $r[2]], [
                ['HPP', 'QV', 121.5], ['HPP', 'TL', 143.5], ['HPP', 'TS', 133], ['HPP', 'ICDQV', 90], ['HPP', 'VSIP', 123],
                ['QV', 'TL', 55], ['TL', 'TS', 35], ['TS', 'TL', 40], ['TS', 'QV', 20], ['CEV', 'TL', 40], ['CEV', 'TS', 30],
                ['ICDQV', 'TL', 85], ['ICDQV', 'TS', 35], ['ICDQV', 'QV', 20], ['VSIP', 'QV', 23], ['VSIP', 'ICDQV', 40],
                ['ICDTP', 'QV', 30], ['ICDTP', 'TS', 20], ['ICDTP', 'TL', 65],
            ]),
        ];
    }

    /** Chuẩn hóa khối quy chế (lưu + đọc): bỏ dòng rỗng, ép kiểu số; [] = bảng chưa cấu hình quy chế. */
    public static function cleanRegulation($r): array
    {
        if (! is_array($r)) return [];
        $num = fn ($v) => is_numeric(str_replace(',', '.', (string) $v)) ? (float) str_replace(',', '.', (string) $v) : 0.0;
        // Tiền VND số nguyên: số thật giữ nguyên; chuỗi "150.000" / "150,000" → bỏ mọi ký tự không phải số.
        $money = fn ($v) => is_int($v) || is_float($v) ? (int) round($v) : (int) preg_replace('/\D/', '', (string) $v);
        $code = fn ($v) => mb_strtoupper(trim((string) $v));
        $list = fn ($v) => is_string($v) ? preg_split('/\s*[,;]\s*/u', $v) : (array) $v;
        $out = ['originGroups' => [], 'zones' => [], 'kcn' => [], 'fuel' => [], 'distances' => []];

        // Nhóm cảng xuất phát — key do FE sinh khi thêm nhóm (dòng KCN tham chiếu theo key); trùng/rỗng → sinh lại.
        $keys = [];
        foreach ((array) ($r['originGroups'] ?? []) as $g) {
            if (! is_array($g)) continue;
            $codes = array_values(array_unique(array_filter(array_map($code, $list($g['codes'] ?? [])), fn ($x) => $x !== '')));
            $name = trim((string) ($g['name'] ?? ''));
            if ($name === '' && ! $codes) continue;
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($g['key'] ?? '')));
            if ($key === '' || isset($keys[$key])) { $n = count($keys) + 1; while (isset($keys['g' . $n])) $n++; $key = 'g' . $n; }
            $keys[$key] = true;
            $out['originGroups'][] = ['key' => $key, 'name' => $name ?: implode(', ', $codes), 'mode' => ($g['mode'] ?? '') === 'kcn' ? 'kcn' : 'zone', 'codes' => $codes];
        }
        // Vùng: tên + tỉnh cũ thuộc vùng (1 tỉnh chỉ nên thuộc 1 vùng — trùng thì vùng đứng trước thắng, FE cảnh báo).
        foreach ((array) ($r['zones'] ?? []) as $z) {
            if (! is_array($z)) continue;
            $provs = array_values(array_unique(array_filter(array_map(fn ($p) => trim((string) $p), $list($z['provinces'] ?? [])), fn ($p) => $p !== '')));
            $name = trim((string) ($z['name'] ?? ''));
            $l = $money($z['luong'] ?? 0); $p = $money($z['phuCap'] ?? 0);
            if ($name === '' && ! $provs && ! $l && ! $p) continue;
            $out['zones'][] = ['name' => $name ?: implode(', ', $provs), 'provinces' => $provs, 'luong' => $l, 'phuCap' => $p, 'long' => ! empty($z['long'])];
        }
        foreach ((array) ($r['kcn'] ?? []) as $k) {
            if (! is_array($k) || ! isset($keys[$k['group'] ?? '']) || $code($k['kho'] ?? '') === '') continue;
            $out['kcn'][] = ['group' => $k['group'], 'kho' => $code($k['kho']), 'luong' => $money($k['luong'] ?? 0), 'phuCap' => $money($k['phuCap'] ?? 0), 'km' => $num($k['km'] ?? 0) ?: null];
        }
        foreach (['axle1', 'axle2'] as $a) $out['fuel'][$a] = ['hr' => $num($r['fuel'][$a]['hr'] ?? 0), 'hh' => $num($r['fuel'][$a]['hh'] ?? 0)];
        // Trợ cấp theo ngày — trường TÙY CHỌN (quy chế lưu trước khi có mục này → mặc định 100.000); có khai mà để trống / 0
        // = không trả.
        $alw = is_array($r['allowances'] ?? null) ? $r['allowances'] : [];
        $pick = fn ($k) => array_key_exists($k, $alw) ? $money($alw[$k] ?? 0) : 100000;
        $out['allowances'] = ['turnaround' => $pick('turnaround'), 'thirdTrip' => $pick('thirdTrip')];
        // Chặng: km + VÉ TRẠM (chi hộ) theo chiều — dòng chỉ có vé (km trống) vẫn giữ.
        foreach ((array) ($r['distances'] ?? []) as $d) {
            if (! is_array($d)) continue;
            $km = $num($d['km'] ?? 0); $toll = $money($d['toll'] ?? 0);
            if (($km <= 0 && $toll <= 0) || $code($d['from'] ?? '') === '' || $code($d['to'] ?? '') === '') continue;
            $out['distances'][] = ['from' => $code($d['from']), 'to' => $code($d['to']), 'km' => $km > 0 ? $km : 0, 'toll' => $toll];
        }
        $empty = ! $out['originGroups'] && ! $out['zones'] && ! $out['kcn'] && ! $out['distances']
            && ! array_filter($out['fuel'], fn ($f) => $f['hr'] || $f['hh']);
        return $empty ? [] : $out;
    }

    /** [tỉnh cũ => chỉ số vùng] của 1 quy chế đã chuẩn hóa — tỉnh khai ở nhiều vùng: vùng đứng trước thắng. */
    public static function zoneIndex(array $reg): array
    {
        $m = [];
        foreach ($reg['zones'] ?? [] as $i => $z) foreach ($z['provinces'] as $p) $m[$p] ??= $i;
        return $m;
    }

    /** [tỉnh cũ => "V{n} · tên vùng"] — nhãn gợi ý ở Cài đặt › Kho. */
    public static function zoneLabels(array $reg): array
    {
        return array_map(fn ($i) => 'V' . ($i + 1) . ' · ' . $reg['zones'][$i]['name'], self::zoneIndex($reg));
    }

    /** Tỉnh cũ của kho có phải NGOÀI Hải Phòng (chưa gán → không xác định → false). */
    public static function isOutsideHaiPhong(?string $oldProvince): bool
    {
        $p = trim((string) $oldProvince);
        return $p !== '' && $p !== self::HAI_PHONG;
    }
}
