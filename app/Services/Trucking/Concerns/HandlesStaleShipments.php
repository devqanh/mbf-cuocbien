<?php

namespace App\Services\Trucking\Concerns;

use App\Models\TruckingSetting;
use App\Models\TruckingShipment;
use Illuminate\Database\Eloquent\Builder;

/**
 * CẢNH BÁO LÔ QUÊN NHẬP GIỜ XE RA: lô đã có Giờ xe đến quá N ngày (mặc định 15 — xe có thể nằm chờ tới 15 ngày)
 * mà vẫn chưa có Giờ xe ra (cont này). Lô chưa ra thì không lên được bảng kê thu khách (bảng kê lấy lô theo Giờ xe ra).
 * N chỉnh ở Cài đặt hệ thống › Tính năng. Giờ lô (gio_*) lưu giờ VN không múi.
 */
trait HandlesStaleShipments
{
    /** Số ngày tính từ Giờ xe đến mà chưa có Giờ xe ra thì cảnh báo. */
    public function staleDays(): int
    {
        return max(1, (int) TruckingSetting::get('sys.stale_ra_days', 15) ?: 15);
    }

    /** Lọc lô quá hạn: CHƯA RA (tab "Chưa ra") + Giờ xe đến cũ hơn N ngày. */
    public function applyStale(Builder $b): Builder
    {
        return $b->where(fn ($a) => $a->whereNull('gio_xe_ra')->orWhere('gio_xe_ra', ''))
            ->whereNotNull('gio_xe_den')
            ->where('gio_xe_den', '<', now('Asia/Ho_Chi_Minh')->subDays($this->staleDays())->format('Y-m-d H:i:s'));
    }

    /** Số lô cần cập nhật Giờ xe ra (biểu tượng cảnh báo trên thanh menu). */
    public function staleShipmentCount(): int
    {
        return $this->applyStale(TruckingShipment::ofSheet('icd'))->count();
    }
}
