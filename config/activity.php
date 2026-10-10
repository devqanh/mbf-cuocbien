<?php

use App\Models;

/*
|--------------------------------------------------------------------------
| Nhật ký thao tác (activity_logs)
|--------------------------------------------------------------------------
| models: model gắn trait LogsActivity → nhãn tiếng Việt, cột làm tiêu đề đối tượng, cột cha (dòng con
|         ghi kèm "thuộc Lô #…"), cột bỏ qua (số liệu suy diễn tự tính lại — ghi vào chỉ gây nhiễu).
| fields: nhãn tiếng Việt cho cột hay gặp (thiếu thì hiện tên cột gốc).
| mask:   khóa payload / cột chứa chuỗi này → che giá trị (không bao giờ lưu mật khẩu, secret, token).
*/
return [
    'max_rows_per_request' => 500,   // trần số dòng diff 1 request/lệnh (reconcile danh mục có thể chạm cả trăm dòng)
    'max_payload_bytes'    => 16000,

    'mask' => ['password', 'secret', 'token', '_key', 'two_factor', 'recovery', 'pass', 'otp', 'code_2fa'],

    'models' => [
        Models\TruckingShipment::class => [
            'label' => 'Lô hàng', 'title' => ['cont_no', 'booking'],
            'ignore' => ['rev_base', 'vat_amount', 'choho_revenue', 'phai_thu', 'da_thu', 'con_no', 'cost_total', 'cost_billable', 'cost_company', 'profit'],
        ],
        Models\TruckingCostLine::class         => ['label' => 'Chi phí lô', 'title' => ['item'], 'parent' => ['shipment_id', 'Lô']],
        Models\TruckingRevenueLine::class      => ['label' => 'Doanh thu lô', 'title' => ['item'], 'parent' => ['shipment_id', 'Lô']],
        Models\TruckingStatement::class        => ['label' => 'Bảng kê', 'title' => ['no', 'customer_name']],
        Models\TruckingStatementLine::class    => ['label' => 'Dòng bảng kê', 'title' => ['cont_no'], 'parent' => ['statement_id', 'Bảng kê']],
        Models\TruckingStatementPayment::class => ['label' => 'Thu tiền bảng kê', 'parent' => ['statement_id', 'Bảng kê']],
        Models\TruckingExtStatement::class     => ['label' => 'Bảng kê xe ngoài', 'title' => ['no', 'ext_vendor']],
        Models\TruckingExtStatementPayment::class => ['label' => 'Trả tiền bảng kê xe ngoài', 'parent' => ['ext_statement_id', 'Bảng kê xe ngoài']],
        Models\TruckingVehicle::class          => ['label' => 'Xe / tài sản', 'title' => ['plate']],
        Models\TruckingVehicleCost::class      => ['label' => 'Phiếu chi', 'title' => ['name'], 'parent' => ['vehicle_id', 'Xe']],
        Models\TruckingVehicleDepreciation::class => ['label' => 'Khấu hao', 'title' => ['name'], 'parent' => ['vehicle_id', 'Xe']],
        Models\TruckingPriceBook::class        => ['label' => 'Bảng giá', 'title' => ['label'], 'parent' => ['customer_id', 'Khách']],
        Models\TruckingPriceRow::class         => ['label' => 'Dòng bảng giá', 'parent' => ['price_book_id', 'Bảng giá']],
        Models\TruckingRoutePay::class         => ['label' => 'Chi lái (Lộ trình)', 'title' => ['bks', 'work_date']],
        Models\TruckingRouteFeeBook::class     => ['label' => 'Quy chế lương chuyến', 'title' => ['label', 'from_date']],
        Models\TruckingPayrollPeriod::class    => ['label' => 'Kỳ lương', 'title' => ['no', 'name'], 'ignore' => ['lines']],
        Models\TruckingTripCostBatch::class    => ['label' => 'Kỳ phí xe', 'title' => ['no', 'name']],
        Models\TruckingCustomer::class         => ['label' => 'Khách hàng', 'title' => ['name']],
        Models\TruckingDriver::class           => ['label' => 'Lái xe', 'title' => ['name']],
        Models\TruckingLocation::class         => ['label' => 'Địa điểm', 'title' => ['name', 'code']],
        Models\TruckingWarehouse::class        => ['label' => 'Kho', 'title' => ['name', 'code']],
        Models\TruckingCostItem::class         => ['label' => 'Khoản chi phí', 'title' => ['name']],
        Models\TruckingExtVendor::class        => ['label' => 'Đơn vị xe ngoài', 'title' => ['name']],
        Models\TruckingSetting::class          => ['label' => 'Cấu hình', 'title' => ['key']],
        Models\User::class                     => ['label' => 'Tài khoản', 'title' => ['name', 'email'], 'ignore' => ['shipment_column_prefs', 'remember_token']],
    ],

    'fields' => [
        'type' => 'Loại', 'kind' => 'Nhóm', 'plate' => 'Biển số', 'axle' => 'Số cầu', 'gps_ref' => 'GPS', 'driver_id' => 'Lái xe (id)',
        'customer_id' => 'Khách (id)', 'booking' => 'Booking', 'io' => 'Nhập/Xuất', 'cont_no' => 'Số cont', 'cont_type' => 'Loại cont',
        'kho' => 'Kho', 'from_loc' => 'Nơi lấy', 'to_loc' => 'Nơi hạ', 'bks_vao' => 'BKS vào', 'bks_ra' => 'BKS ra', 'vehicle_id' => 'Xe (id)',
        'driver' => 'Lái xe', 'ra_mode' => 'Kiểu ra', 'ext_vendor' => 'Nhà xe ngoài', 'ext_fee' => 'Cước xe ngoài',
        'gio_den_du_kien' => 'Giờ đến dự kiến', 'gio_xe_den' => 'Giờ xe đến', 'gio_xe_ra' => 'Giờ xe ra', 'gio_xe_ra_xe' => 'Giờ xe ra (xe)',
        'thanh_ly_date' => 'Ngày thanh lý', 'ha_cont_date' => 'Ngày hạ cont', 'ghi_chu' => 'Ghi chú', 'info_note' => 'Ghi chú lô',
        'name' => 'Tên', 'code' => 'Ký hiệu', 'amount' => 'Số tiền', 'vat' => 'VAT', 'invoice_no' => 'Số HĐ', 'payer' => 'Người chi',
        'paid' => 'Đã chi', 'paid_date' => 'Ngày chi', 'approved' => 'Đã duyệt', 'cancelled_at' => 'Hủy lúc', 'billable' => 'Chi hộ',
        'item' => 'Khoản', 'date' => 'Ngày', 'note' => 'Ghi chú', 'total' => 'Tổng', 'value' => 'Giá trị', 'key' => 'Khóa',
        'period_from' => 'Từ ngày', 'period_to' => 'Đến ngày', 'locked' => 'Đã chốt', 'frozen' => 'Đã chốt', 'extra_items' => 'Chi khác',
        'email' => 'Email', 'role' => 'Vai trò', 'prices' => 'Giá', 'spend_date' => 'Ngày chi phí', 'due_date' => 'Hạn',
    ],
];
