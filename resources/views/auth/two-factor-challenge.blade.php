<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Xác thực 2 lớp · {{ config('app.name') }}</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="@assetVer('css/app.css')">
</head>
<body>

<div class="auth-wrapper">

    {{-- Left side branding --}}
    <aside class="auth-side">
        <div class="brand">MBF</div>
        <div>
            <h1 class="mb-3">Bảo mật 2 lớp 🔐</h1>
            <p class="mb-4">
                Tài khoản của bạn được bảo vệ bằng xác thực 2 lớp. Hãy mở ứng dụng
                Authenticator trên điện thoại và nhập mã 6 số đang hiển thị để tiếp tục.
            </p>
            <ul class="list-unstyled" style="position: relative; z-index: 2;">
                <li class="mb-2"><i class="bi bi-check2-circle me-2"></i> Google / Microsoft Authenticator, Authy…</li>
                <li class="mb-2"><i class="bi bi-check2-circle me-2"></i> Mã đổi mới mỗi 30 giây</li>
                <li class="mb-2"><i class="bi bi-check2-circle me-2"></i> Mất điện thoại? Dùng mã khôi phục</li>
            </ul>
        </div>
        <div class="copyright">© {{ date('Y') }} MBF. All rights reserved.</div>
    </aside>

    {{-- Right side form --}}
    <section class="auth-form-wrap">
        <div class="auth-form">
            <h2>Nhập mã xác thực</h2>
            <p class="subtitle">Mở ứng dụng Authenticator và nhập mã 6 số.</p>

            @if ($errors->any())
                <div class="alert alert-danger d-flex align-items-start gap-2">
                    <i class="bi bi-exclamation-octagon-fill mt-1"></i>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            <form action="{{ route('login.2fa.attempt') }}" method="POST" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="code" class="form-label">Mã xác thực</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white" style="border-right:0;">
                            <i class="bi bi-shield-lock text-muted"></i>
                        </span>
                        <input type="text" name="code" id="code"
                               class="form-control @error('code') is-invalid @enderror"
                               style="border-left:0; letter-spacing:.3em; font-weight:600;"
                               inputmode="numeric" autocomplete="one-time-code"
                               placeholder="000000" required autofocus>
                    </div>
                    <div class="form-text">
                        <i class="bi bi-info-circle me-1"></i>
                        Mất thiết bị? Nhập một <strong>mã khôi phục</strong> (dạng XXXX-XXXX) vào ô trên.
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Xác nhận & đăng nhập
                </button>

                <div class="text-center">
                    <a href="{{ route('login') }}" class="small text-decoration-none" style="color: var(--azia-primary)">
                        <i class="bi bi-arrow-left me-1"></i> Quay lại đăng nhập
                    </a>
                </div>
            </form>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
