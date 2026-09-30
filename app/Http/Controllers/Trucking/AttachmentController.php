<?php

namespace App\Http\Controllers\Trucking;

use App\Models\TruckingAttachment;
use App\Models\TruckingShipment;
use App\Models\TruckingVehicle;
use App\Models\TruckingVehicleCost;
use Illuminate\Support\Facades\Storage;

/** Stream file tập trung (bảng attachments) — disk-agnostic (local/S3), phân quyền theo owner. */
class AttachmentController extends BaseTruckingController
{
    public function show(TruckingAttachment $attachment)
    {
        $u = auth()->user();
        // Quyền xem file = quyền xem trang chứa nó: tài liệu/ảnh hóa đơn xe → Quản lý tài sản (fleet.view),
        // ảnh lô hàng → Lô hàng. settings.view giữ như cũ (danh mục đội xe/lái xe ở Cài đặt).
        $allowed = $u?->can('settings.view')
            || ($attachment->owner_type === TruckingVehicle::class && $u?->can('fleet.view'))
            || ($attachment->owner_type === TruckingShipment::class && $u?->can('shipments.view'))
            || ($attachment->group === 'costPhoto' && $u?->can('spend.request')
                && TruckingVehicleCost::where('created_by', $u->id)->whereJsonContains('photos', $attachment->id)->exists());
        abort_unless($allowed, 403);
        if ($attachment->disk === 's3') $this->svc->applyS3Config();
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);
        return $disk->response($attachment->path, $attachment->name ?: 'file', ['Content-Type' => $attachment->mime ?: 'application/octet-stream']);
    }
}
