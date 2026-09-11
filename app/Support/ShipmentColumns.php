<?php

namespace App\Support;

/**
 * Quyền XEM TỪNG CỘT của bảng Lô hàng (/trucking-v2/lo-hang).
 *
 * Hai mức hiệu lực, cố ý khác nhau:
 * - cost / revenue: dữ liệu tiền tự chứa → CẮT HẲN khỏi payload, client không có gì để hiện.
 * - id / customs / route / plate / schedule: chính là dữ liệu vận hành của bảng (bộ lọc nơi lấy /
 *   nơi hạ, chip "đã ra", free-time, popup sửa lô) nên vẫn phải gửi; quyền chỉ ẩn cột trên giao
 *   diện cho gọn theo vai trò, KHÔNG phải rào dữ liệu.
 */
final class ShipmentColumns
{
    /** cột => permission. Khách hàng + Cont không có quyền: ẩn đi thì bảng vô nghĩa. */
    public const MAP = [
        'id'       => 'shipments.view_id',
        'customs'  => 'shipments.view_customs',
        'route'    => 'shipments.view_route',
        'plate'    => 'shipments.view_plate',
        'schedule' => 'shipments.view_schedule',
        'cost'     => 'shipments.view_cost',
        'revenue'  => 'shipments.view_revenue',
    ];

    /** Field bị cắt khỏi payload khi thiếu quyền — chỉ 2 cột tiền. */
    private const STRIP = [
        'cost'    => ['cost'],
        'revenue' => ['rev', 'cuocDau', 'priceMatched'],
    ];

    /** @var array<int|string,array<string,bool>> nhớ theo user cho 1 request */
    private static array $memo = [];

    /**
     * Cột nào user hiện tại được xem. Không có user (artisan, import, queue) → xem hết.
     *
     * @return array<string,bool>
     */
    public static function allowed(): array
    {
        $u   = auth()->user();
        $key = $u?->getAuthIdentifier() ?? 'cli';
        if (isset(self::$memo[$key])) return self::$memo[$key];

        $can = [];
        foreach (self::MAP as $col => $perm) $can[$col] = $u === null || $u->can($perm);

        return self::$memo[$key] = $can;
    }

    public static function can(string $col): bool
    {
        return self::allowed()[$col] ?? true;
    }

    /** Bỏ field của cột tiền mà user không được xem. */
    public static function strip(array $row): array
    {
        foreach (self::STRIP as $col => $fields) {
            if (self::can($col)) continue;
            foreach ($fields as $f) unset($row[$f]);
        }

        return $row;
    }
}
