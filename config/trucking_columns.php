<?php

/*
|--------------------------------------------------------------------------
| Định nghĩa cột tính năng TRUCKING — 2 sheet HẠ HPH & HẠ ICD
|--------------------------------------------------------------------------
| Single source of truth — dùng cho:
|   - Migration (sinh cột bảng trucking_entries — union 2 sheet)
|   - Model TruckingEntry (fillable / casts / date / decimal / text fields)
|   - Frontend Luckysheet (render cột RIÊNG cho từng sheet)
|
| Trường mỗi cột:
|   key      — tên cột DB (dùng chung giữa 2 sheet khi nghĩa trùng)
|   title    — nhãn hiển thị (theo Excel ketoan.xlsx)
|   width    — độ rộng px
|   type     — 'text' (mặc định) | 'date' | 'vnd' | 'number'
|   group    — 1..5 (màu header pastel)
|   readonly — true nếu cột không cho sửa (cột id/No.)
|   formula  — (tùy chọn) công thức auto-fill, THAM CHIẾU THEO KEY cột cùng dòng:
|       ['op'=>'sum','cols'=>[...]]                  → =A+B+C
|       ['op'=>'sub','cols'=>['a','b']]              → =a-b
|       ['op'=>'mul','col'=>'x','factor'=>0.08]      → =x*0.08
|       ['op'=>'expr','template'=>'{a}+{b}*{c}']     → thay {key} bằng ô tương ứng
|
| Frontend đổi key→chữ cái cột (A1) theo VỊ TRÍ render thực tế, không theo Excel.
|
| Nhóm:
|   1 = Thông tin lô hàng      2 = Chi phí
|   3 = Chi phí xe ngoài / TT  4 = Chi phí xe MBF chạy
|   5 = Doanh thu
*/

return [

    // =====================================================================
    // SHEET 1 — HẠ HPH (Hải Phòng)
    // =====================================================================
    'hph' => [
        // --- NHÓM 1: THÔNG TIN LÔ HÀNG ---
        ['key' => 'id',                 'title' => 'STT',                          'width' => 50,  'group' => 1, 'readonly' => true],
        ['key' => 'customer',           'title' => 'KHÁCH HÀNG',                   'width' => 150, 'group' => 1],
        ['key' => 'booking_no',         'title' => 'SỐ BOOKING',                   'width' => 130, 'group' => 1],
        ['key' => 'direction',          'title' => 'NHẬP/XUẤT',                    'width' => 90,  'group' => 1],
        ['key' => 'cont_qty',           'title' => 'SỐ LƯỢNG CONT',                'width' => 90,  'type' => 'number', 'group' => 1],
        ['key' => 'cont_type',          'title' => 'LOẠI CONT',                    'width' => 80,  'group' => 1],
        ['key' => 'container_no',       'title' => 'SỐ CONTAINER',                 'width' => 130, 'group' => 1],
        ['key' => 'declaration_no',     'title' => 'SỐ TỜ KHAI',                   'width' => 130, 'group' => 1],
        ['key' => 'cut_off',            'title' => 'CẮT MÁNG',                     'width' => 110, 'group' => 1],
        ['key' => 'vessel_date',        'title' => 'NGÀY TÀU CHẠY',                'width' => 110, 'type' => 'date', 'group' => 1],
        ['key' => 'shipping_date',      'title' => 'NGÀY ĐÓNG/TRẢ HÀNG',           'width' => 130, 'type' => 'date', 'group' => 1],
        ['key' => 'truck_in_time',      'title' => 'GIỜ XE LÊN NHÀ MÁY',           'width' => 110, 'group' => 1],
        ['key' => 'truck_out_time',     'title' => 'GIỜ XE RỜI NHÀ MÁY',           'width' => 110, 'group' => 1],
        ['key' => 'disconnect_connect', 'title' => 'DISCONNECT/CONNECT',           'width' => 130, 'group' => 1],
        ['key' => 'loading_address',    'title' => 'ĐỊA CHỈ ĐÓNG HÀNG',            'width' => 160, 'group' => 1],
        ['key' => 'invoice_info',       'title' => 'THÔNG TIN XUẤT HÓA ĐƠN',       'width' => 160, 'group' => 1],
        ['key' => 'pickup_location',    'title' => 'NƠI LẤY CONT',                 'width' => 130, 'group' => 1],
        ['key' => 'drop_location',      'title' => 'NƠI HẠ CONT',                  'width' => 130, 'group' => 1],
        ['key' => 'trucking_info',      'title' => 'THÔNG TIN NHÀ XE',             'width' => 140, 'group' => 1],
        ['key' => 'plate_in',           'title' => 'BKS KÉO CONT LÊN NHÀ MÁY',     'width' => 150, 'group' => 1],
        ['key' => 'plate_out',          'title' => 'BKS KÉO CONT RỜI NHÀ MÁY',     'width' => 150, 'group' => 1],
        ['key' => 'barge_no',           'title' => 'SỐ HIỆU SÀ LAN',               'width' => 120, 'group' => 1],
        ['key' => 'trucking_note',      'title' => 'GHI CHÚ ĐỘI TRUCKING',         'width' => 160, 'group' => 1],
        ['key' => 'canon_inv',          'title' => 'CANON INV',                    'width' => 110, 'group' => 1],

        // --- NHÓM 2: CHI PHÍ ---
        ['key' => 'ext_truck_fee',      'title' => 'CƯỚC XE NGOÀI',                'width' => 130, 'type' => 'vnd', 'group' => 2],
        ['key' => 'lift_amount',        'title' => 'NÂNG — SỐ TIỀN',               'width' => 130, 'type' => 'vnd', 'group' => 2],
        ['key' => 'lift_payer',         'title' => 'NÂNG — BÊN TT',                'width' => 110, 'group' => 2],
        ['key' => 'lower_amount',       'title' => 'HẠ — SỐ TIỀN',                 'width' => 130, 'type' => 'vnd', 'group' => 2],
        ['key' => 'lower_payer',        'title' => 'HẠ — BÊN TT',                  'width' => 110, 'group' => 2],
        ['key' => 'other_cost_amount',  'title' => 'CHI PHÍ KHÁC — SỐ TIỀN',       'width' => 140, 'type' => 'vnd', 'group' => 2],
        ['key' => 'other_cost_payer',   'title' => 'CHI PHÍ KHÁC — BÊN TT',        'width' => 120, 'group' => 2],
        ['key' => 'csht_amount',        'title' => 'CSHT — SỐ TIỀN',               'width' => 130, 'type' => 'vnd', 'group' => 2],
        ['key' => 'total_collect_paid', 'title' => 'TỔNG THU CHI HỘ',              'width' => 140, 'type' => 'vnd', 'group' => 2,
            'formula' => ['op' => 'sum', 'cols' => ['lift_amount', 'lower_amount', 'other_cost_amount', 'csht_amount']]],
        ['key' => 'mbf_vk_cost',        'title' => 'CHI PHÍ MBF, VK',              'width' => 130, 'type' => 'vnd', 'group' => 2],
        ['key' => 'customs_fixed',      'title' => 'HẢI QUAN — TỜ KHAI KHOÁN',     'width' => 140, 'type' => 'vnd', 'group' => 2],
        ['key' => 'customs_other',      'title' => 'HẢI QUAN — KHÁC',              'width' => 120, 'type' => 'vnd', 'group' => 2],
        ['key' => 'customs_payer',      'title' => 'HẢI QUAN — BÊN TT',            'width' => 120, 'group' => 2],
        ['key' => 'choose_cont_amount', 'title' => 'CHỌN VỎ — SỐ TIỀN',            'width' => 130, 'type' => 'vnd', 'group' => 2],
        ['key' => 'choose_cont_payer',  'title' => 'CHỌN VỎ — BÊN TT',             'width' => 110, 'group' => 2],
        ['key' => 'total_cost',         'title' => 'TỔNG CHI PHÍ',                 'width' => 150, 'type' => 'vnd', 'group' => 2,
            'formula' => ['op' => 'sum', 'cols' => ['ext_truck_fee', 'lift_amount', 'lower_amount', 'other_cost_amount', 'csht_amount', 'customs_fixed', 'customs_other', 'choose_cont_amount']]],

        // --- NHÓM 3: CHI PHÍ XE NGOÀI / THANH TOÁN ---
        ['key' => 'ext_freight',        'title' => 'CƯỚC',                         'width' => 120, 'type' => 'vnd', 'group' => 3,
            'formula' => ['op' => 'expr', 'template' => '{ext_truck_fee}']],
        ['key' => 'ext_vat',            'title' => 'VAT 8%',                       'width' => 110, 'type' => 'vnd', 'group' => 3,
            'formula' => ['op' => 'mul', 'col' => 'ext_freight', 'factor' => 0.08]],
        ['key' => 'ext_total_collect',  'title' => 'TỔNG THU CHI HỘ (XE NGOÀI)',   'width' => 150, 'type' => 'vnd', 'group' => 3,
            'formula' => ['op' => 'sum', 'cols' => ['lift_amount', 'lower_amount', 'other_cost_amount']]],
        ['key' => 'ext_paid',           'title' => 'SỐ ĐÃ THANH TOÁN',             'width' => 130, 'type' => 'vnd', 'group' => 3],
        ['key' => 'ext_paid_date',      'title' => 'NGÀY TT',                      'width' => 110, 'type' => 'date', 'group' => 3],
        ['key' => 'ext_debt',           'title' => 'CÒN NỢ (XE NGOÀI)',            'width' => 130, 'type' => 'vnd', 'group' => 3,
            'formula' => ['op' => 'expr', 'template' => '{ext_freight}+{ext_vat}+{ext_total_collect}-{ext_paid}']],

        // --- NHÓM 4: CHI PHÍ XE MBF CHẠY ---
        ['key' => 'loading_point',      'title' => 'ĐIỂM ĐÓNG HÀNG',               'width' => 140, 'group' => 4],
        ['key' => 'province',           'title' => 'TỈNH',                         'width' => 110, 'group' => 4],
        ['key' => 'driver_expense',     'title' => 'LÁI XE CHI',                   'width' => 120, 'type' => 'vnd', 'group' => 4],
        ['key' => 'station_ticket',     'title' => 'VÉ TRẠM',                      'width' => 110, 'type' => 'vnd', 'group' => 4],
        ['key' => 'road_money',         'title' => 'TIỀN ĐƯỜNG',                   'width' => 110, 'type' => 'vnd', 'group' => 4],
        ['key' => 'allowance',          'title' => 'TRỢ CẤP',                      'width' => 110, 'type' => 'vnd', 'group' => 4],
        ['key' => 'mbf_other_cost',     'title' => 'CHI PHÍ KHÁC (XE)',            'width' => 120, 'type' => 'vnd', 'group' => 4],
        ['key' => 'vetc_ticket',        'title' => 'VÉ VETC',                      'width' => 100, 'type' => 'vnd', 'group' => 4],
        ['key' => 'salary',             'title' => 'LƯƠNG',                        'width' => 110, 'type' => 'vnd', 'group' => 4],
        ['key' => 'distance',           'title' => 'QUÃNG ĐƯỜNG (KM)',             'width' => 110, 'type' => 'number', 'group' => 4],
        ['key' => 'liters',             'title' => 'LÍT',                          'width' => 80,  'type' => 'number', 'group' => 4],
        ['key' => 'fuel_price',         'title' => 'ĐƠN GIÁ DẦU',                  'width' => 110, 'type' => 'vnd', 'group' => 4],
        ['key' => 'mbf_total_cost',     'title' => 'TỔNG CHI PHÍ XE MBF CHẠY',     'width' => 160, 'type' => 'vnd', 'group' => 4,
            'formula' => ['op' => 'expr', 'template' => '{station_ticket}+{road_money}+{allowance}+{mbf_other_cost}+{vetc_ticket}+{salary}+{liters}*{fuel_price}']],

        // --- NHÓM 5: DOANH THU ---
        ['key' => 'accountant_note',    'title' => 'GHI CHÚ KẾ TOÁN',              'width' => 150, 'group' => 5],
        ['key' => 'rev_truck_freight',  'title' => 'DOANH THU CƯỚC XE',            'width' => 140, 'type' => 'vnd', 'group' => 5],
        ['key' => 'rev_other_transport','title' => 'PHÍ VẬN TẢI KHÁC',             'width' => 130, 'type' => 'vnd', 'group' => 5],
        ['key' => 'rev_clearance',      'title' => 'PHÍ THANH LÝ TỜ KHAI',         'width' => 140, 'type' => 'vnd', 'group' => 5],
        ['key' => 'rev_storage',        'title' => 'LƯU CA',                       'width' => 110, 'type' => 'vnd', 'group' => 5],
        ['key' => 'rev_no_declaration', 'title' => 'TỜ KHAI KHÔNG PHƠI',           'width' => 130, 'type' => 'vnd', 'group' => 5],
        ['key' => 'rev_empty_reuse',    'title' => 'PHÍ TÁI SỬ DỤNG CONT RỖNG',    'width' => 150, 'type' => 'vnd', 'group' => 5],
        ['key' => 'total_revenue',      'title' => 'TỔNG DOANH THU',               'width' => 150, 'type' => 'vnd', 'group' => 5,
            'formula' => ['op' => 'sum', 'cols' => ['rev_truck_freight', 'rev_other_transport', 'rev_clearance', 'rev_storage', 'rev_no_declaration', 'rev_empty_reuse']]],
        ['key' => 'rev_vat',            'title' => 'VAT',                          'width' => 110, 'type' => 'vnd', 'group' => 5,
            'formula' => ['op' => 'mul', 'col' => 'total_revenue', 'factor' => 0.08]],
        ['key' => 'collect_lift',       'title' => 'SỐ TIỀN CHI HỘ NÂNG',          'width' => 140, 'type' => 'vnd', 'group' => 5],
        ['key' => 'collect_lower',      'title' => 'SỐ TIỀN CHI HỘ HẠ',            'width' => 140, 'type' => 'vnd', 'group' => 5],
        ['key' => 'collect_csht',       'title' => 'SỐ TIỀN CSHT',                 'width' => 120, 'type' => 'vnd', 'group' => 5],
        ['key' => 'total_receivable',   'title' => 'TỔNG PHẢI THU',                'width' => 150, 'type' => 'vnd', 'group' => 5,
            'formula' => ['op' => 'sum', 'cols' => ['total_revenue', 'rev_vat', 'collect_lift', 'collect_lower', 'collect_csht']]],
        ['key' => 'customer_paid',      'title' => 'KHÁCH HÀNG ĐÃ THANH TOÁN',     'width' => 150, 'type' => 'vnd', 'group' => 5],
        ['key' => 'customer_debt',      'title' => 'CÒN NỢ',                       'width' => 130, 'type' => 'vnd', 'group' => 5,
            'formula' => ['op' => 'sub', 'cols' => ['total_receivable', 'customer_paid']]],
        ['key' => 'customer_due_date',  'title' => 'HẠN KHÁCH HÀNG THANH TOÁN',    'width' => 150, 'type' => 'date', 'group' => 5],
        ['key' => 'customer_paid_date', 'title' => 'NGÀY KHÁCH HÀNG THANH TOÁN',   'width' => 150, 'type' => 'date', 'group' => 5],
    ],

    // =====================================================================
    // SHEET 2 — HẠ ICD (Quế Võ)
    // =====================================================================
    'icd' => [
        // --- NHÓM 1: THÔNG TIN ---
        ['key' => 'id',                  'title' => 'STT',                  'width' => 50,  'group' => 1, 'readonly' => true],
        ['key' => 'customer',            'title' => 'KHÁCH HÀNG',           'width' => 150, 'group' => 1],
        ['key' => 'booking_no',          'title' => 'SỐ BOOKING/BILL',      'width' => 130, 'group' => 1],
        ['key' => 'direction',           'title' => 'NHẬP/XUẤT',            'width' => 90,  'group' => 1],
        ['key' => 'vol',                 'title' => 'VOL',                  'width' => 70,  'type' => 'number', 'group' => 1],
        ['key' => 'cont_type',           'title' => 'LOẠI CONT',            'width' => 80,  'group' => 1],
        ['key' => 'cut_off',             'title' => 'CẮT MÁNG',             'width' => 110, 'group' => 1],
        ['key' => 'pickup_location',     'title' => 'NƠI LẤY',              'width' => 130, 'group' => 1],
        ['key' => 'drop_location',       'title' => 'NƠI HẠ',               'width' => 130, 'group' => 1],
        ['key' => 'cont_arrival_date',   'title' => 'NGÀY CONT ĐẾN',        'width' => 110, 'type' => 'date', 'group' => 1],
        ['key' => 'expected_arrival_time','title' => 'GIỜ ĐẾN DỰ KIẾN',     'width' => 110, 'group' => 1],
        ['key' => 'cont_out_date',       'title' => 'NGÀY CONT RA',         'width' => 110, 'type' => 'date', 'group' => 1],
        ['key' => 'truck_arrival_time',  'title' => 'GIỜ XE ĐẾN',           'width' => 100, 'group' => 1],
        ['key' => 'warehouse',           'title' => 'KHO',                  'width' => 100, 'group' => 1],
        ['key' => 'canon_inv',           'title' => 'CANON INV',            'width' => 110, 'group' => 1],
        ['key' => 'container_in_no',     'title' => 'SỐ CONTAINER ĐẾN',     'width' => 130, 'group' => 1],
        ['key' => 'driver_name_phone',   'title' => 'TÊN LÁI XE + SĐT',     'width' => 150, 'group' => 1],
        ['key' => 'plate_in',            'title' => 'BKS KÉO CONT ĐẾN',     'width' => 130, 'group' => 1],
        ['key' => 'container_out_no',    'title' => 'SỐ CONTAINER RA',      'width' => 130, 'group' => 1],
        ['key' => 'truck_out_time',      'title' => 'GIỜ XE RA',            'width' => 100, 'group' => 1],
        ['key' => 'out_date',            'title' => 'NGÀY RA',              'width' => 110, 'type' => 'date', 'group' => 1],
        ['key' => 'plate_out',           'title' => 'BKS KÉO CONT RA',      'width' => 130, 'group' => 1],
        ['key' => 'trucking_note',       'title' => 'GHI CHÚ TRUCKING',     'width' => 160, 'group' => 1],
        ['key' => 'disconnect_connect',  'title' => 'DISCONNECT/CONNECT',   'width' => 130, 'group' => 1],
        ['key' => 'free_time',           'title' => 'FREE TIME',            'width' => 100, 'group' => 1],

        // --- NHÓM 4: CHI PHÍ XE MBF CHẠY ---
        ['key' => 'road_allowance',      'title' => 'PHỤ CẤP TIỀN ĐƯỜNG',   'width' => 140, 'type' => 'vnd', 'group' => 4],
        ['key' => 'allowance',           'title' => 'TRỢ CẤP',              'width' => 110, 'type' => 'vnd', 'group' => 4],
        ['key' => 'salary',              'title' => 'LƯƠNG',                'width' => 110, 'type' => 'vnd', 'group' => 4],
        ['key' => 'mbf_other_cost',      'title' => 'CHI PHÍ KHÁC',         'width' => 120, 'type' => 'vnd', 'group' => 4],
        ['key' => 'distance',            'title' => 'QUÃNG ĐƯỜNG (KM)',     'width' => 110, 'type' => 'number', 'group' => 4],
        ['key' => 'liters',              'title' => 'LÍT',                  'width' => 80,  'type' => 'number', 'group' => 4],
        ['key' => 'fuel_price',          'title' => 'ĐƠN GIÁ',              'width' => 110, 'type' => 'vnd', 'group' => 4],
        ['key' => 'total_cost',          'title' => 'TỔNG CHI PHÍ',         'width' => 150, 'type' => 'vnd', 'group' => 4,
            'formula' => ['op' => 'expr', 'template' => '{road_allowance}+{allowance}+{salary}+{mbf_other_cost}+{liters}*{fuel_price}']],

        // --- NHÓM 5: DOANH THU ---
        ['key' => 'rev_tien_son',        'title' => 'DOANH THU TIÊN SƠN',   'width' => 140, 'type' => 'vnd', 'group' => 5],
        ['key' => 'rev_que_vo',          'title' => 'DOANH THU QUẾ VÕ',     'width' => 140, 'type' => 'vnd', 'group' => 5],
        ['key' => 'rev_thang_long',      'title' => 'DOANH THU THĂNG LONG', 'width' => 150, 'type' => 'vnd', 'group' => 5],
        ['key' => 'rev_clearance',       'title' => 'THANH LÝ TỜ KHAI',     'width' => 130, 'type' => 'vnd', 'group' => 5],
        ['key' => 'total_revenue',       'title' => 'TỔNG DOANH THU',       'width' => 150, 'type' => 'vnd', 'group' => 5,
            'formula' => ['op' => 'sum', 'cols' => ['rev_tien_son', 'rev_que_vo', 'rev_thang_long', 'rev_clearance']]],
        ['key' => 'rev_vat',             'title' => 'VAT',                  'width' => 110, 'type' => 'vnd', 'group' => 5,
            'formula' => ['op' => 'mul', 'col' => 'total_revenue', 'factor' => 0]],
        ['key' => 'collect',             'title' => 'CHI HỘ',               'width' => 120, 'type' => 'vnd', 'group' => 5],
        ['key' => 'total_receivable',    'title' => 'PHẢI THU',             'width' => 150, 'type' => 'vnd', 'group' => 5,
            'formula' => ['op' => 'sum', 'cols' => ['total_revenue', 'rev_vat', 'collect']]],
    ],
];
