<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lookup IP → vị trí địa lý qua ip-api.com (free, 45 req/phút).
 * - Cache 24h theo IP để tiết kiệm quota.
 * - Bỏ qua IP private/loopback (LAN không có vị trí).
 * - Gọi server-side vì ip-api.com free chỉ HTTP (page HTTPS sẽ Mixed Content).
 */
class GeoIp
{
    private const CACHE_TTL_SECONDS = 86400;   // 24h
    private const HTTP_TIMEOUT      = 2;        // giây — tránh treo profile page

    /**
     * Trả về mảng ['city', 'region', 'country', 'isp', 'flag'] hoặc null nếu không lookup được.
     */
    public function lookup(?string $ip): ?array
    {
        if (! $ip || $this->isPrivateIp($ip)) {
            return null;
        }

        return Cache::remember("geoip:{$ip}", self::CACHE_TTL_SECONDS, function () use ($ip) {
            try {
                $res = Http::timeout(self::HTTP_TIMEOUT)
                    ->get("http://ip-api.com/json/{$ip}", [
                        'fields' => 'status,country,countryCode,regionName,city,isp',
                        'lang'   => 'vi',
                    ]);

                if (! $res->ok()) return null;
                $data = $res->json();
                if (($data['status'] ?? '') !== 'success') return null;

                return [
                    'city'    => $data['city']        ?? null,
                    'region'  => $data['regionName']  ?? null,
                    'country' => $data['country']     ?? null,
                    'isp'     => $data['isp']         ?? null,
                    'flag'    => $this->flagEmoji($data['countryCode'] ?? null),
                ];
            } catch (\Throwable $e) {
                Log::channel('single')->warning('GeoIp lookup failed', [
                    'ip' => $ip, 'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    /** Tóm tắt 1 dòng để hiển thị, vd "🇻🇳 Hà Nội, Vietnam". */
    public function summary(?string $ip): ?string
    {
        $geo = $this->lookup($ip);
        if (! $geo) return null;

        $parts = array_filter([$geo['city'], $geo['country']]);
        $text  = $parts ? implode(', ', $parts) : null;
        if (! $text) return null;

        return trim(($geo['flag'] ?? '') . ' ' . $text);
    }

    private function isPrivateIp(string $ip): bool
    {
        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /** Chuyển country code 2 chữ (vd "VN") thành flag emoji 🇻🇳 dùng regional indicator unicode. */
    private function flagEmoji(?string $code): ?string
    {
        if (! $code || strlen($code) !== 2) return null;
        $code = strtoupper($code);
        $a = mb_chr(ord($code[0]) - ord('A') + 0x1F1E6);
        $b = mb_chr(ord($code[1]) - ord('A') + 0x1F1E6);
        return $a . $b;
    }
}
