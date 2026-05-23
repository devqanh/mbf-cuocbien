<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>403 · Không có quyền truy cập · {{ config('app.name') }}</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="@assetVer('css/error.css')">
</head>
<body>

@php
    $user = auth()->user();
    $roles = $user ? $user->roles->pluck('name')->all() : [];
@endphp

<div class="err-bg"></div>

<div class="err-wrap">
    <div class="err-card is-403">

        <div class="err-icon-badge">
            <i class="bi bi-shield-lock-fill"></i>
        </div>

        <h1 class="err-code">403</h1>
        <div class="err-status">Forbidden</div>

        <h2 class="err-title">Bạn không có quyền truy cập</h2>
        <p class="err-subtitle">
            @if(isset($exception) && $exception?->getMessage() && $exception->getMessage() !== 'This action is unauthorized.')
                {{ $exception->getMessage() }}
            @else
                Tài khoản của bạn chưa được cấp quyền để xem hoặc thao tác với nội dung này.
                Vui lòng liên hệ quản trị viên nếu bạn cho rằng đây là sự nhầm lẫn.
            @endif
        </p>

        <div class="err-url">
            <i class="bi bi-link-45deg"></i>
            <code>/{{ request()->path() }}</code>
        </div>

        @auth
            <div class="err-info">
                <div class="err-info-row">
                    <i class="bi bi-person-circle"></i>
                    <div>Đăng nhập với <strong>{{ $user->name }}</strong> · {{ $user->email }}</div>
                </div>
                @if(count($roles))
                    <div class="err-info-row">
                        <i class="bi bi-shield-check"></i>
                        <div>
                            Vai trò:
                            @foreach($roles as $r)
                                <span class="role-badge">{{ $r }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endauth

        <div class="err-actions">
            <a href="javascript:history.back()" class="err-btn err-btn-ghost">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
            <a href="{{ url('/') }}" class="err-btn err-btn-primary">
                <i class="bi bi-house-door-fill"></i> Về trang chính
            </a>
            @auth
                <form action="{{ route('logout') }}" method="POST" style="margin:0; display:inline;">
                    @csrf
                    <button type="submit" class="err-btn err-btn-ghost">
                        <i class="bi bi-box-arrow-right"></i> Đăng xuất
                    </button>
                </form>
            @endauth
        </div>

    </div>
</div>

</body>
</html>
