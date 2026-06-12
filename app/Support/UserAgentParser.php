<?php

namespace App\Support;

/**
 * Parse User-Agent string thành thông tin device hiển thị thân thiện.
 * Không phụ thuộc thư viện ngoài — regex đơn giản đủ cho UI hiển thị.
 */
class UserAgentParser
{
    /**
     * Trả về ['browser', 'os', 'device', 'icon'] từ UA string.
     */
    public static function parse(?string $ua): array
    {
        $ua = (string) $ua;

        return [
            'browser' => self::browser($ua),
            'os'      => self::os($ua),
            'device'  => self::device($ua),
            'icon'    => self::icon($ua),
        ];
    }

    private static function browser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg/')                        => 'Microsoft Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Chrome/') && ! str_contains($ua, 'Edg/') && ! str_contains($ua, 'OPR/') => 'Chrome',
            str_contains($ua, 'Firefox/')                    => 'Firefox',
            str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome/') => 'Safari',
            str_contains($ua, 'curl/')                       => 'cURL',
            str_contains($ua, 'PostmanRuntime')              => 'Postman',
            default                                          => 'Trình duyệt khác',
        };
    }

    private static function os(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Windows NT 10.0') => 'Windows 10/11',
            str_contains($ua, 'Windows NT 6.3')  => 'Windows 8.1',
            str_contains($ua, 'Windows NT 6.1')  => 'Windows 7',
            str_contains($ua, 'Windows')         => 'Windows',
            str_contains($ua, 'iPhone')          => 'iOS (iPhone)',
            str_contains($ua, 'iPad')            => 'iPadOS',
            str_contains($ua, 'Mac OS X')        => 'macOS',
            str_contains($ua, 'Android')         => 'Android',
            str_contains($ua, 'Linux')           => 'Linux',
            default                              => 'Hệ điều hành khác',
        };
    }

    private static function device(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'Mobile',
            str_contains($ua, 'Android') && str_contains($ua, 'Mobile') => 'Mobile',
            str_contains($ua, 'Android')                            => 'Tablet',
            default                                                 => 'Desktop',
        };
    }

    /** Bootstrap Icons class name khớp với device/OS. */
    private static function icon(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'iPhone')                            => 'phone',
            str_contains($ua, 'iPad')                              => 'tablet',
            str_contains($ua, 'Android') && str_contains($ua, 'Mobile') => 'phone',
            str_contains($ua, 'Android')                           => 'tablet',
            str_contains($ua, 'Windows')                           => 'pc-display',
            str_contains($ua, 'Mac OS X')                          => 'laptop',
            str_contains($ua, 'Linux')                             => 'pc-display-horizontal',
            default                                                => 'display',
        };
    }
}
