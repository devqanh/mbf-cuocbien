<?php

namespace App\Http\Controllers\Trucking;

use App\Http\Controllers\Controller;
use App\Services\TruckingV2Service;

/**
 * Base cho các controller Trucking v2 (đã tách theo domain).
 * Giữ helper dùng chung: quyền theo trang (pageData) + user hiện tại.
 * Controller mỏng; serialize/persist nằm ở TruckingV2Service.
 */
abstract class BaseTruckingController extends Controller
{
    public function __construct(protected readonly TruckingV2Service $svc) {}

    /**
     * Dữ liệu chung cho mọi trang: quyền (canEdit/canDelete theo ĐÚNG tính năng của trang)
     * + boot (inline, không cần fetch). Mỗi trang truyền quyền sửa/xóa tương ứng.
     */
    protected function pageData(array $boot, string $editPerm = 'shipments.update', string $deletePerm = 'shipments.delete'): array
    {
        $u = $this->user();
        return [
            'canEdit'   => $u->can($editPerm),
            'canDelete' => $u->can($deletePerm),
            'boot'      => $boot,
        ];
    }

    protected function user()
    {
        return request()->user();
    }
}
