<?php

/*
|--------------------------------------------------------------------------
| Bật/tắt module (feature flags)
|--------------------------------------------------------------------------
| shipments — module "Follow Up Shipment" cũ. Đặt false để TẠM TẮT (ẩn menu +
| chặn route, redirect về Trucking). Code vẫn giữ nguyên, bật lại bằng cách
| đổi env FEATURE_SHIPMENTS=true (hoặc sửa default bên dưới).
*/

return [
    'shipments' => env('FEATURE_SHIPMENTS', false),
];
