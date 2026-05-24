<?php

/*
|--------------------------------------------------------------------------
| Định nghĩa các cột của bảng Follow Up Shipment
|--------------------------------------------------------------------------
| Single source of truth — dùng cho cả backend (filter data, snapshot)
| và frontend (Luckysheet render).
|
| Trường:
|   key      — tên cột DB
|   title    — nhãn hiển thị
|   width    — độ rộng px
|   type     — 'text' (mặc định) | 'date' | 'vnd' | 'number'
|   group    — 1..4 (quyết định màu header pastel)
|   readonly — true nếu cột không cho phép edit dù user có quyền (vd cột id)
*/

return [
    // === NHÓM 1 — THÔNG TIN LÔ HÀNG (14 cột) ===
    // Client đặt TRƯỚC No. để chỉ Client được freeze (col 0). No. ở col 1 vẫn hiển thị
    // nhưng sẽ scroll khi user scroll ngang — yêu cầu user: "chỉ đóng băng client".
    ['key' => 'client',         'title' => 'Client',      'width' => 140, 'group' => 1],
    ['key' => 'id',             'title' => 'No.',         'width' => 50,  'group' => 1, 'readonly' => true],
    ['key' => 'hbl',            'title' => 'HB/L',        'width' => 120, 'group' => 1],
    ['key' => 'mbl_no',         'title' => 'MB/L NO',     'width' => 130, 'group' => 1],
    ['key' => 'bkg_no',         'title' => 'BKG NO.',     'width' => 130, 'group' => 1],
    ['key' => 'pol',            'title' => 'POL',         'width' => 70,  'group' => 1],
    ['key' => 'pod',            'title' => 'POD',         'width' => 70,  'group' => 1],
    ['key' => 'vol',            'title' => 'VOL',         'width' => 55,  'group' => 1],
    ['key' => 'container_type', 'title' => 'TYPE',        'width' => 65,  'group' => 1],
    ['key' => 'etd',            'title' => 'ETD',         'width' => 85,  'type' => 'date', 'group' => 1],
    ['key' => 'eta',            'title' => 'ETA',         'width' => 85,  'type' => 'date', 'group' => 1],
    ['key' => 'vessel_name',    'title' => 'VESSEL NAME', 'width' => 140, 'group' => 1],
    ['key' => 'note',           'title' => 'NOTE',        'width' => 130, 'group' => 1],
    ['key' => 'line',           'title' => 'LINE',        'width' => 80,  'group' => 1],

    // === NHÓM 2 — CHỨNG TỪ VẬN CHUYỂN (8 cột) ===
    ['key' => 'vgm',            'title' => 'VGM',           'width' => 70,  'group' => 2],
    ['key' => 'si',             'title' => 'SI',            'width' => 70,  'group' => 2],
    ['key' => 'bl_draft',       'title' => 'BL DRAFT',      'width' => 80,  'group' => 2],
    ['key' => 'bl_confirm',     'title' => 'BL CONFIRM',    'width' => 90,  'group' => 2],
    ['key' => 'obl',            'title' => 'OBL',           'width' => 70,  'group' => 2],
    ['key' => 'tlx',            'title' => 'TLX',           'width' => 70,  'group' => 2],
    ['key' => 'swb',            'title' => 'SWB',           'width' => 70,  'group' => 2],
    ['key' => 'shipment_done',  'title' => 'SHIPMENT DONE', 'width' => 100, 'group' => 2],

    // === NHÓM 3 — THANH TOÁN NCC & AGENT (17 cột) ===
    ['key' => 'purchase_note',           'title' => 'Note giá mua',                       'width' => 130, 'group' => 3],
    ['key' => 'payment_amount',          'title' => 'Số tiền thanh toán',                 'width' => 140, 'type' => 'vnd',    'group' => 3],
    ['key' => 'supplier',                'title' => 'NCC',                                 'width' => 130, 'group' => 3],
    ['key' => 'supplier_due_date',       'title' => 'Hạn phải trả',                       'width' => 110, 'type' => 'date',   'group' => 3],
    ['key' => 'report_close_date_increase', 'title' => 'Ngày chốt báo cáo phát sinh tăng', 'width' => 140, 'type' => 'date',   'group' => 3],
    ['key' => 'report_close_date_decrease', 'title' => 'Ngày chốt báo cáo phát sinh giảm', 'width' => 140, 'type' => 'date',   'group' => 3],
    ['key' => 'supplier_paid_date',      'title' => 'Ngày trả',                            'width' => 110, 'type' => 'date',   'group' => 3],
    ['key' => 'cost_recognized',         'title' => 'Chi phí ghi nhận',                    'width' => 140, 'type' => 'vnd',    'group' => 3],
    ['key' => 'trucking_cost',           'title' => 'Trucking nếu có',                     'width' => 130, 'type' => 'vnd',    'group' => 3],
    ['key' => 'purchase_invoice_no',     'title' => 'Số hóa đơn đầu vào',                 'width' => 130, 'group' => 3],
    ['key' => 'purchase_invoice_date',   'title' => 'Ngày hóa đơn đầu vào',               'width' => 130, 'type' => 'date',   'group' => 3],
    ['key' => 'driver_hoa',              'title' => 'Driver Hoa',                          'width' => 110, 'group' => 3],
    ['key' => 'agent_fee',               'title' => 'Phí agent',                           'width' => 110, 'type' => 'number', 'group' => 3],
    ['key' => 'agent_name',              'title' => 'Tên Agent',                           'width' => 130, 'group' => 3],
    ['key' => 'agent_fee_vnd',           'title' => 'Quy đổi Agent sang VNĐ',              'width' => 150, 'type' => 'vnd',    'group' => 3],
    ['key' => 'agent_due_date',          'title' => 'Hạn phải trả (Agent)',                'width' => 130, 'type' => 'date',   'group' => 3],
    ['key' => 'agent_paid_date',         'title' => 'Ngày trả (Agent)',                    'width' => 120, 'type' => 'date',   'group' => 3],

    // Agent receivable (credit note) — phải thu từ agent
    ['key' => 'credit_note_agent',       'title' => 'Credit note (Agent)',                  'width' => 140, 'type' => 'number', 'group' => 3],
    ['key' => 'agent_receivable_amount', 'title' => 'Phải thu (Agent)',                    'width' => 130, 'type' => 'vnd',    'group' => 3],
    ['key' => 'credit_note_agent_vnd',   'title' => 'Quy đổi credit note sang VNĐ (Agent)','width' => 180, 'type' => 'vnd',    'group' => 3],
    ['key' => 'agent_receivable_due_date','title'=> 'Hạn phải thu (Agent)',                'width' => 130, 'type' => 'date',   'group' => 3],
    ['key' => 'agent_received_amount',   'title' => 'Đã thu (Agent)',                      'width' => 130, 'type' => 'vnd',    'group' => 3],
    ['key' => 'agent_received_date',     'title' => 'Ngày thu (Agent)',                    'width' => 120, 'type' => 'date',   'group' => 3],

    // === NHÓM 4 — DOANH THU KHÁCH HÀNG (9 cột) ===
    ['key' => 'sale_note',           'title' => 'Note giá bán',          'width' => 130, 'group' => 4],
    ['key' => 'receivable_amount',   'title' => 'Phải thu khách',        'width' => 140, 'type' => 'vnd',  'group' => 4],
    ['key' => 'customer',            'title' => 'Khách hàng',            'width' => 130, 'group' => 4],
    ['key' => 'received_amount',     'title' => 'Tiền đã thu',           'width' => 140, 'type' => 'vnd',  'group' => 4],
    ['key' => 'receivable_due_date', 'title' => 'Hạn phải thu',          'width' => 110, 'type' => 'date', 'group' => 4],
    ['key' => 'received_date',       'title' => 'Ngày thu',              'width' => 110, 'type' => 'date', 'group' => 4],
    ['key' => 'revenue_recognized',  'title' => 'Doanh thu ghi nhận',    'width' => 140, 'type' => 'vnd',  'group' => 4],
    ['key' => 'sale_invoice_no',     'title' => 'Số hóa đơn đầu ra',     'width' => 130, 'group' => 4],
    ['key' => 'sale_invoice_date',   'title' => 'Ngày hóa đơn đầu ra',   'width' => 130, 'type' => 'date', 'group' => 4],
];
