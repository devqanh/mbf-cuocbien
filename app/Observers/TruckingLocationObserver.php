<?php

namespace App\Observers;

use App\Models\TruckingLocation;

/** Chuẩn hóa name + code địa điểm trước khi ghi DB — safety net cho mọi write path. */
class TruckingLocationObserver
{
    public function saving(TruckingLocation $location): void
    {
        if ($location->name !== null) {
            $location->name = preg_replace('/\s+/u', ' ', trim((string) $location->name)) ?? '';
        }
        if ($location->code !== null) {
            $c = preg_replace('/\s+/u', ' ', trim((string) $location->code)) ?? '';
            $location->code = $c !== '' ? $c : null;
        }
    }
}
