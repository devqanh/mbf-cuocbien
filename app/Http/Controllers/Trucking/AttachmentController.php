<?php

namespace App\Http\Controllers\Trucking;

use App\Models\TruckingAttachment;
use App\Models\TruckingVehicleCost;
use Illuminate\Support\Facades\Storage;

/** Stream file tập trung (bảng attachments) — disk-agnostic (local/S3), phân quyền theo owner. */
class AttachmentController extends BaseTruckingController
{
    public function show(TruckingAttachment $attachment)
    {
        $u = auth()->user();
        if ($attachment->group === 'costPhoto') {
            $allowed = $u?->can('settings.view')
                || ($u?->can('spend.request') && TruckingVehicleCost::where('created_by', $u->id)->whereJsonContains('photos', $attachment->id)->exists());
        } else {
            $allowed = $u?->can('settings.view');
        }
        abort_unless($allowed, 403);
        if ($attachment->disk === 's3') $this->svc->applyS3Config();
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);
        return $disk->response($attachment->path, $attachment->name ?: 'file', ['Content-Type' => $attachment->mime ?: 'application/octet-stream']);
    }
}
