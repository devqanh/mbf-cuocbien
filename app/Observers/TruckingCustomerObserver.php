<?php

namespace App\Observers;

use App\Models\TruckingCustomer;

/**
 * Chuẩn hóa name của khách hàng TRƯỚC khi ghi DB — safety net cho mọi write path
 * (firstOrCreate, create, save, update). Tránh "Cty  ABC" và "Cty ABC" tạo 2 record
 * khác nhau khi có code path nào bỏ qua collapse ở tầng service.
 */
class TruckingCustomerObserver
{
    public function saving(TruckingCustomer $customer): void
    {
        if ($customer->name !== null) {
            $customer->name = preg_replace('/\s+/u', ' ', trim((string) $customer->name)) ?? '';
        }
    }
}
