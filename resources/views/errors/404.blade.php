<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>404 · Không tìm thấy trang · {{ config('app.name') }}</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/error.css') }}">
</head>
<body>

<div class="err-bg"></div>

<div class="err-wrap">
    <div class="err-card is-404">

        <div class="err-icon-badge">
            <i class="bi bi-compass"></i>
        </div>

        <h1 class="err-code">404</h1>
        <div class="err-status">Page Not Found</div>

        <h2 class="err-title">Hmm, không tìm thấy trang này</h2>
        <p class="err-subtitle">
            Trang bạn yêu cầu có thể đã bị xoá, đổi địa chỉ, hoặc URL bị gõ sai.
            Thử quay lại hoặc đi tới một trong các trang gợi ý bên dưới.
        </p>

        <div class="err-url">
            <i class="bi bi-link-45deg"></i>
            <code>/{{ request()->path() }}</code>
        </div>

        <div class="err-suggest-title">
            <i class="bi bi-stars"></i> Có thể bạn đang tìm
        </div>
        <ul class="err-suggestions">
            <li>
                <a href="{{ url('/shipments') }}">
                    <span class="sug-icon c-blue"><i class="bi bi-truck"></i></span>
                    <span class="sug-text">
                        <strong>Follow Up Shipment</strong>
                        <small>Theo dõi lô hàng</small>
                    </span>
                </a>
            </li>
            <li>
                <a href="{{ url('/reports/payable') }}">
                    <span class="sug-icon c-cyan"><i class="bi bi-clipboard-data"></i></span>
                    <span class="sug-text">
                        <strong>Báo cáo phải trả</strong>
                        <small>Công nợ NCC</small>
                    </span>
                </a>
            </li>
            <li>
                <a href="{{ url('/users') }}">
                    <span class="sug-icon c-green"><i class="bi bi-people"></i></span>
                    <span class="sug-text">
                        <strong>Danh sách thành viên</strong>
                        <small>Quản lý tài khoản</small>
                    </span>
                </a>
            </li>
            <li>
                <a href="{{ url('/roles') }}">
                    <span class="sug-icon c-purple"><i class="bi bi-shield-check"></i></span>
                    <span class="sug-text">
                        <strong>Vai trò &amp; phân quyền</strong>
                        <small>Cấu hình quyền hạn</small>
                    </span>
                </a>
            </li>
        </ul>

        <div class="err-actions">
            <a href="javascript:history.back()" class="err-btn err-btn-ghost">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
            <a href="{{ url('/') }}" class="err-btn err-btn-primary">
                <i class="bi bi-house-door-fill"></i> Về trang chính
            </a>
        </div>

    </div>
</div>

</body>
</html>
