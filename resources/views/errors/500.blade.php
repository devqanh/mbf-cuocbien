@php
    // APP_DEBUG=false thì Laravel bọc lỗi thật thành HttpException(500) → lỗi gốc nằm ở getPrevious().
    $ex   = $exception ?? null;
    $real = $ex?->getPrevious() ?: $ex;
    $ref  = app()->bound('sys.errorRef') ? app('sys.errorRef') : null;

    // Chi tiết lỗi lộ đường dẫn file / câu SQL nên CHỈ cho người có quyền xem cài đặt (hoặc khi bật APP_DEBUG).
    // Người dùng thường vẫn thấy MÃ LỖI để báo lại. auth() có thể chưa sẵn sàng nếu lỗi xảy ra trước session.
    try { $showDetail = (bool) config('app.debug') || (bool) auth()->user()?->can('settings.view'); }
    catch (\Throwable $e) { $showDetail = (bool) config('app.debug'); }

    // Vài dòng stack THUỘC CODE DỰ ÁN (bỏ vendor) — đủ để biết lỗi phát sinh ở đâu mà không đổ cả trang trace.
    $frames = [];
    if ($showDetail && $real) {
        $root = base_path() . DIRECTORY_SEPARATOR;
        $rel  = fn ($f) => str_replace(['\\', $root, str_replace('\\', '/', $root)], ['/', '', ''], (string) $f);
        foreach ($real->getTrace() as $f) {
            $file = $f['file'] ?? null;
            if (! $file || str_contains($rel($file), 'vendor/')) continue;
            $frames[] = $rel($file) . ':' . ($f['line'] ?? '?');
            if (count($frames) >= 5) break;
        }
    }
@endphp
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
            @if ($ref)
                <div class="err-info-row">
                    <i class="bi bi-hash"></i>
                    <div>Mã lỗi: <strong>{{ $ref }}</strong> <span class="err-info-hint">— gửi mã này cho kỹ thuật để tra log</span></div>
                </div>
            @endif
        </div>

        @if ($showDetail && $real)
            <div class="err-detail">
                <div class="err-detail-head">
                    <i class="bi bi-bug-fill"></i> Chi tiết lỗi
                    <span>chỉ người có quyền xem cài đặt mới thấy phần này</span>
                </div>
                <div class="err-detail-type">{{ class_basename($real) }}</div>
                <p class="err-detail-msg">{{ $real->getMessage() ?: '(lỗi không kèm thông báo)' }}</p>
                <div class="err-detail-at">
                    <code>{{ str_replace(['\\', str_replace('\\', '/', base_path()) . '/'], ['/', ''], $real->getFile()) }}:{{ $real->getLine() }}</code>
                </div>
                @if ($frames)
                    <ul class="err-detail-trace">
                        @foreach ($frames as $f)
                            <li><code>{{ $f }}</code></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

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
