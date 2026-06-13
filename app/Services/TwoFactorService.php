<?php

namespace App\Services;

/**
 * TOTP (RFC 6238) thuần PHP — tương thích Google Authenticator, Microsoft
 * Authenticator, Authy… (HMAC-SHA1, 6 chữ số, chu kỳ 30s).
 *
 * Không phụ thuộc package ngoài: dễ audit, không cần composer trên production.
 */
class TwoFactorService
{
    /** Bảng chữ cái Base32 chuẩn RFC 4648 (dùng để mã hoá/giải mã secret). */
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private int $digits = 6;
    private int $period = 30;

    /**
     * Sinh secret mới (Base32, không padding). 20 byte ngẫu nhiên = chuẩn của
     * hầu hết app authenticator.
     */
    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    /**
     * Kiểm tra mã 6 số người dùng nhập có khớp secret không.
     * $window = số bước 30s cho phép lệch trước/sau (bù lệch giờ thiết bị).
     */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== $this->digits) {
            return false;
        }

        $key = $this->base32Decode($secret);
        if ($key === '') {
            return false;
        }

        $counter = (int) floor(time() / $this->period);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->codeAt($key, $counter + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * URI otpauth:// để render QR cho app authenticator quét.
     * $label thường là email user, $issuer là tên hệ thống.
     */
    public function otpauthUrl(string $secret, string $label, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $label)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1'
            . '&digits=' . $this->digits
            . '&period=' . $this->period;
    }

    /**
     * Sinh danh sách mã khôi phục dạng XXXX-XXXX (loại bỏ ký tự dễ nhầm 0/O/1/I).
     *
     * @return array<int,string>
     */
    public function recoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->randomRecoveryCode() . '-' . $this->randomRecoveryCode();
        }

        return $codes;
    }

    // ===================================================================
    // Nội bộ
    // ===================================================================

    /** Sinh 1 mã TOTP cho 1 bước thời gian (counter) cụ thể. */
    private function codeAt(string $key, int $counter): string
    {
        // 8 byte big-endian: 32 bit cao = 0, 32 bit thấp = counter.
        $binCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $binCounter, $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** $this->digits);

        return str_pad((string) $value, $this->digits, '0', STR_PAD_LEFT);
    }

    private function randomRecoveryCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < 4; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }

    private function base32Encode(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::BASE32[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $out;
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
        if ($secret === '') {
            return '';
        }

        $bits = '';
        foreach (str_split($secret) as $char) {
            $bits .= str_pad(decbin(strpos(self::BASE32, $char)), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
