<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>419 · Phiên đã hết hạn · {{ config('app.name') }}</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/error.css') }}">
</head>
<body>

<div class="err-bg"></div>

<div class="err-wrap">
    <div class="err-card is-419">

        <div class="err-icon-badge">
            <i class="bi bi-hourglass-split"></i>
        </div>

        <h1 class="err-code">419</h1>
        <div class="err-status">Page Expired</div>

        <h2 class="err-title">Phiên làm việc đã hết hạn</h2>
        <p class="err-subtitle">
            Trang đã hết hiệu lực (CSRF token) do để lâu không thao tác.
            Vui lòng tải lại hoặc đăng nhập lại để tiếp tục.
        </p>

        <div class="err-info">
            <div class="err-info-row">
                <i class="bi bi-shield-fill-check"></i>
                <div>Cơ chế bảo mật mặc định chống tấn công <strong>CSRF</strong>.</div>
            </div>
            <div class="err-info-row">
                <i class="bi bi-info-circle"></i>
                <div>Dữ liệu form chưa kịp lưu — vui lòng thử lại sau khi đăng nhập.</div>
            </div>
        </div>

        <div class="err-actions">
            <a href="javascript:location.reload()" class="err-btn err-btn-ghost">
                <i class="bi bi-arrow-clockwise"></i> Tải lại
            </a>
            <a href="{{ route('login') }}" class="err-btn err-btn-primary">
                <i class="bi bi-box-arrow-in-right"></i> Đăng nhập lại
            </a>
        </div>

    </div>
</div>

</body>
</html>
