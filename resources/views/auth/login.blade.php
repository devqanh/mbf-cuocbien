<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Đăng nhập · {{ config('app.name') }}</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>

<div class="auth-wrapper">

    {{-- Left side branding --}}
    <aside class="auth-side">
        <div class="brand">
            MBF
        </div>

        <div>
            <h1 class="mb-3">Chào mừng quay lại 👋</h1>
            <p class="mb-4">
                Đăng nhập để truy cập trang quản trị, theo dõi số liệu kinh doanh và quản lý hệ thống của bạn theo thời gian thực.
            </p>
            <ul class="list-unstyled" style="position: relative; z-index: 2;">
                <li class="mb-2"><i class="bi bi-check2-circle me-2"></i> Quản lý dữ liệu dạng bảng tính Luckysheet</li>
                <li class="mb-2"><i class="bi bi-check2-circle me-2"></i> Báo cáo theo thời gian thực</li>
                <li class="mb-2"><i class="bi bi-check2-circle me-2"></i> Phân quyền chi tiết theo vai trò</li>
            </ul>
        </div>

        <div class="copyright">© {{ date('Y') }} MBF. All rights reserved.</div>
    </aside>

    {{-- Right side form --}}
    <section class="auth-form-wrap">
        <div class="auth-form">
            <h2>Đăng nhập</h2>
            <p class="subtitle">Vui lòng nhập email và mật khẩu để tiếp tục.</p>

            @if ($errors->any())
                <div class="alert alert-danger d-flex align-items-start gap-2">
                    <i class="bi bi-exclamation-octagon-fill mt-1"></i>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            @if (session('success'))
                <div class="alert alert-success d-flex align-items-start gap-2">
                    <i class="bi bi-check-circle-fill mt-1"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            <form action="{{ route('login.attempt') }}" method="POST" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white" style="border-right:0;">
                            <i class="bi bi-envelope text-muted"></i>
                        </span>
                        <input type="email" name="email" id="email"
                               value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror"
                               style="border-left:0;"
                               placeholder="name@example.com" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <label for="password" class="form-label">Mật khẩu</label>
                        <a href="#" class="small text-decoration-none" style="color: var(--azia-primary)">Quên mật khẩu?</a>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-white" style="border-right:0;">
                            <i class="bi bi-lock text-muted"></i>
                        </span>
                        <input type="password" name="password" id="password"
                               class="form-control @error('password') is-invalid @enderror"
                               style="border-left:0; border-right:0;"
                               placeholder="••••••••" required>
                        <span class="input-group-text toggle-pass" onclick="togglePass()">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </span>
                    </div>
                </div>

                <div class="form-check mb-4">
                    <input type="checkbox" name="remember" id="remember" class="form-check-input" value="1"
                           {{ old('remember', '1') ? 'checked' : '' }}>
                    <label for="remember" class="form-check-label">Ghi nhớ đăng nhập (6 tháng)</label>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Đăng nhập
                </button>

                <div class="text-center text-muted small">
                    Tài khoản demo: <strong>devqanh@gmail.com</strong> / <strong>Quyenanh_2016</strong>
                </div>
            </form>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function togglePass() {
        const inp = document.getElementById('password');
        const icon = document.getElementById('eyeIcon');
        if (inp.type === 'password') { inp.type = 'text'; icon.className = 'bi bi-eye-slash'; }
        else { inp.type = 'password'; icon.className = 'bi bi-eye'; }
    }
</script>
</body>
</html>
