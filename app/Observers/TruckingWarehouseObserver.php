<?php

namespace App\Observers;

use App\Models\TruckingWarehouse;

/** Chuẩn hóa name + code kho trước khi ghi DB — safety net cho mọi write path. */
class TruckingWarehouseObserver
{
    public function saving(TruckingWarehouse $warehouse): void
    {
        if ($warehouse->name !== null) {
            $warehouse->name = preg_replace('/\s+/u', ' ', trim((string) $warehouse->name)) ?? '';
        }
        if ($warehouse->code !== null) {
            $c = preg_replace('/\s+/u', ' ', trim((string) $warehouse->code)) ?? '';
            $warehouse->code = $c !== '' ? $c : null;
        }
    }
}
