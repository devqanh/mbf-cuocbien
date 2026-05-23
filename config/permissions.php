<?php

/*
|--------------------------------------------------------------------------
| Nhãn tiếng Việt cho permissions, roles, modules
|--------------------------------------------------------------------------
| Dùng để hiển thị thân thiện với người dùng non-tech.
| Cấu trúc permission name: <module>.<action>  (vd: users.view)
*/

return [

    // Module = prefix trước dấu chấm trong tên permission
    'modules' => [
        'dashboard' => [
            'label'       => 'Bảng điều khiển',
            'icon'        => 'speedometer2',
            'color'       => '#0153a9',
            'description' => 'Trang chủ và báo cáo tổng quan',
        ],
        'users' => [
            'label'       => 'Quản lý thành viên',
            'icon'        => 'people-fill',
            'color'       => '#24d39f',
            'description' => 'Tài khoản người dùng trong hệ thống',
        ],
        'roles' => [
            'label'       => 'Vai trò & phân quyền',
            'icon'        => 'shield-lock-fill',
            'color'       => '#7c5cff',
            'description' => 'Cấu hình ai được phép làm gì',
        ],
        'shipments' => [
            'label'       => 'Follow Up Shipment',
            'icon'        => 'truck',
            'color'       => '#ffb822',
            'description' => 'Theo dõi lô hàng xuất/nhập, B/L, ETD/ETA',
        ],
        'reports' => [
            'label'       => 'Báo cáo tài chính',
            'icon'        => 'clipboard-data',
            'color'       => '#00b8d4',
            'description' => 'Báo cáo phải trả, công nợ NCC',
        ],
        'tasks' => [
            'label'       => 'Ghi chú & công việc',
            'icon'        => 'check2-square',
            'color'       => '#ff9f43',
            'description' => 'Ghi chú nội bộ, giao việc, nhắc hẹn theo lô hàng / báo cáo',
        ],
    ],

    // Mỗi permission có nhãn ngắn + mô tả cụ thể để user hiểu hệ quả
    'permissions' => [
        'dashboard.view' => ['label' => 'Xem dashboard',             'desc' => 'Truy cập trang chủ và các biểu đồ tổng quan'],

        'users.view'     => ['label' => 'Xem danh sách thành viên',  'desc' => 'Đọc thông tin các tài khoản trong hệ thống'],
        'users.create'   => ['label' => 'Thêm thành viên mới',       'desc' => 'Tạo tài khoản mới và gán vai trò cho họ'],
        'users.update'   => ['label' => 'Sửa thành viên',            'desc' => 'Cập nhật tên, email, mật khẩu, đổi vai trò'],
        'users.delete'   => ['label' => 'Xoá thành viên',            'desc' => 'Xoá tài khoản khỏi hệ thống (không khôi phục được)'],

        'roles.view'     => ['label' => 'Xem vai trò & quyền',       'desc' => 'Đọc danh sách vai trò và bảng phân quyền'],
        'roles.create'   => ['label' => 'Tạo vai trò mới',           'desc' => 'Định nghĩa nhóm quyền mới cho hệ thống'],
        'roles.update'   => ['label' => 'Sửa vai trò',               'desc' => 'Thêm hoặc bớt quyền của một vai trò'],
        'roles.delete'   => ['label' => 'Xoá vai trò',               'desc' => 'Xoá vai trò (không xoá được nếu đang có người dùng)'],

        'shipments.view'   => ['label' => 'Xem danh sách lô hàng', 'desc' => 'Đọc bảng theo dõi shipment'],
        'shipments.create' => ['label' => 'Thêm lô hàng',          'desc' => 'Tạo mới một lô hàng (Client, B/L, POL/POD…)'],
        'shipments.update' => ['label' => 'Sửa lô hàng',           'desc' => 'Cập nhật thông tin lô hàng đã có'],
        'shipments.delete' => ['label' => 'Xoá lô hàng',           'desc' => 'Xoá lô hàng khỏi hệ thống'],

        'reports.view'   => ['label' => 'Xem báo cáo',     'desc' => 'Xem danh sách báo cáo tài chính'],
        'reports.create' => ['label' => 'Tạo báo cáo',     'desc' => 'Tạo báo cáo phải trả, cấu hình đầu kỳ NCC'],
        'reports.delete' => ['label' => 'Xoá báo cáo',     'desc' => 'Xoá báo cáo đã tạo'],

        'tasks.view'           => ['label' => 'Xem ghi chú & công việc',  'desc' => 'Xem danh sách ghi chú và task được giao'],
        'tasks.create'         => ['label' => 'Tạo ghi chú & công việc',  'desc' => 'Tạo task / ghi chú mới, đặt hạn nhắc'],
        'tasks.assign_others'  => ['label' => 'Giao việc cho người khác', 'desc' => 'Có quyền này mới gán task cho người khác; nếu không chỉ tự gán cho mình'],
        'tasks.manage_all'     => ['label' => 'Quản trị toàn bộ công việc','desc' => 'Xem/sửa/xoá mọi task trong hệ thống (admin)'],
    ],

    // Mô tả từng vai trò (dùng cho 4 role mặc định; role tự tạo dùng fallback)
    'roles' => [
        'super_admin' => [
            'label'       => 'Quản trị hệ thống',
            'description' => 'Toàn quyền hệ thống. Có thể làm mọi thao tác.',
            'icon'        => 'shield-fill-exclamation',
            'color'       => 'danger',
            'system'      => true,
        ],
        'admin' => [
            'label'       => 'Quản trị viên',
            'description' => 'Quản lý thành viên, sản phẩm và xem các báo cáo.',
            'icon'        => 'shield-fill-check',
            'color'       => 'primary',
            'system'      => false,
        ],
        'editor' => [
            'label'       => 'Biên tập viên',
            'description' => 'Chuyên tạo và chỉnh sửa danh mục sản phẩm.',
            'icon'        => 'pencil-square',
            'color'       => 'info',
            'system'      => false,
        ],
        'user' => [
            'label'       => 'Nhân viên xem',
            'description' => 'Chỉ xem dashboard và danh sách sản phẩm.',
            'icon'        => 'person-fill',
            'color'       => 'secondary',
            'system'      => false,
        ],
    ],
];
