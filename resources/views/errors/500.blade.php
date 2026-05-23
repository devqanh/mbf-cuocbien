<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>500 · Lỗi hệ thống · {{ config('app.name') }}</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="@assetVer('css/error.css')">
</head>
<body>

<div class="err-bg"></div>

<div class="err-wrap">
    <div class="err-card is-500">

        <div class="err-icon-badge">
            <i class="bi bi-cone-striped"></i>
        </div>

        <h1 class="err-code">500</h1>
        <div class="err-status">Server Error</div>

        <h2 class="err-title">Đã có sự cố xảy ra</h2>
        <p class="err-subtitle">
            Hệ thống gặp lỗi không mong muốn trong khi xử lý yêu cầu của bạn.
            Đội kỹ thuật đã được thông báo. Vui lòng thử lại sau ít phút.
        </p>

        <div class="err-info">
            <div class="err-info-row">
                <i class="bi bi-clock-history"></i>
                <div>Thời gian: <strong>{{ now()->format('d/m/Y H:i:s') }}</strong></div>
            </div>
            <div class="err-info-row">
                <i class="bi bi-link-45deg"></i>
                <div>URL: <code>/{{ request()->path() }}</code></div>
            </div>
        </div>

        <div class="err-actions">
            <a href="javascript:location.reload()" class="err-btn err-btn-ghost">
                <i class="bi bi-arrow-clockwise"></i> Thử lại
            </a>
            <a href="{{ url('/') }}" class="err-btn err-btn-primary">
                <i class="bi bi-house-door-fill"></i> Về trang chính
            </a>
        </div>

    </div>
</div>

</body>
</html>
