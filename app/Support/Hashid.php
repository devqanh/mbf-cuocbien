<?php

namespace App\Support;

/**
 * Mã hóa ID số → chuỗi ngắn khó đoán (và giải mã ngược) — KHÔNG cần cột phụ trong DB.
 *
 * Cơ chế: "Optimus" (Knuth multiplicative hashing trên 31-bit) cho ra số xáo trộn
 * (id tuần tự → giá trị rải rác, không lộ thứ tự), rồi base62 cho gọn URL.
 * XOR mask lấy từ APP_KEY nên mỗi deployment 1 khác. Thuần PHP, không package.
 *
 * Giới hạn id ≤ 2^31-1 (~2.1 tỷ) — quá đủ cho mọi bảng ở đây.
 */
class Hashid
{
    private const MASK  = 0x7FFFFFFF;       // 2^31 - 1
    private const MOD   = 0x80000000;       // 2^31
    private const PRIME = 1580030173;       // số nguyên tố < 2^31 (cố định)

    private static ?int $inverse = null;    // nghịch đảo modular của PRIME mod 2^31
    private static ?int $xor = null;        // mask bí mật theo APP_KEY
    private const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private static function init(): void
    {
        if (self::$inverse !== null) return;
        self::$inverse = self::modInverse(self::PRIME, self::MOD);
        self::$xor = (int) (hexdec(substr(hash('sha256', 'trk-hashid|' . (string) config('app.key')), 0, 8))) & self::MASK;
    }

    /** id (số) → chuỗi hash ngắn. */
    public static function encode(int $id): string
    {
        self::init();
        if ($id < 0) return '';
        $n = ((($id * self::PRIME) & self::MASK) ^ self::$xor);
        return self::toBase62($n);
    }

    /** chuỗi hash → id (số), hoặc null nếu không hợp lệ. */
    public static function decode(?string $hash): ?int
    {
        self::init();
        $hash = (string) $hash;
        if ($hash === '') return null;
        $n = self::fromBase62($hash);
        if ($n === null) return null;
        $id = ((($n ^ self::$xor) & self::MASK) * self::$inverse) & self::MASK;
        // Kiểm tra khứ hồi để loại chuỗi rác (mọi chuỗi base62 đều decode được số nào đó)
        return self::encode($id) === $hash ? $id : null;
    }

    private static function toBase62(int $n): string
    {
        if ($n === 0) return '0';
        $s = '';
        while ($n > 0) {
            $s = self::ALPHABET[$n % 62] . $s;
            $n = intdiv($n, 62);
        }
        return $s;
    }

    private static function fromBase62(string $s): ?int
    {
        $n = 0;
        for ($i = 0, $len = strlen($s); $i < $len; $i++) {
            $p = strpos(self::ALPHABET, $s[$i]);
            if ($p === false) return null;
            $n = $n * 62 + $p;
            if ($n > 0xFFFFFFFF) return null;   // vượt ngưỡng → rác
        }
        return $n;
    }

    /** Nghịch đảo modular bằng thuật toán Euclid mở rộng: a*x ≡ 1 (mod m). */
    private static function modInverse(int $a, int $m): int
    {
        [$old_r, $r] = [$a % $m, $m];
        [$old_s, $s] = [1, 0];
        while ($r !== 0) {
            $q = intdiv($old_r, $r);
            [$old_r, $r] = [$r, $old_r - $q * $r];
            [$old_s, $s] = [$s, $old_s - $q * $s];
        }
        return (($old_s % $m) + $m) % $m;
    }
}
