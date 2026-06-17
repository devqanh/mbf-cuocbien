<?php

namespace App\Observers;

use App\Models\TruckingDriver;

/** Chuẩn hóa name lái xe trước khi ghi DB — safety net cho mọi write path. */
class TruckingDriverObserver
{
    public function saving(TruckingDriver $driver): void
    {
        if ($driver->name !== null) {
            $driver->name = preg_replace('/\s+/u', ' ', trim((string) $driver->name)) ?? '';
        }
    }
}
