<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>

    {{-- Bootstrap 5.3 --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    {{-- Bootstrap Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>

<header class="app-header">
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid p-0">
            <a class="navbar-brand brand-logo" href="{{ route('dashboard') }}">
                <i class="bi bi-water"></i> cuocbien
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse app-nav" id="mainNav">
                <ul class="navbar-nav me-auto">
                    {{-- Dashboard --}}
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                           href="{{ route('dashboard') }}">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>

                    {{-- Sản phẩm --}}
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('items.*') ? 'active' : '' }}"
                           href="{{ route('items.index') }}">
                            <i class="bi bi-table"></i> Sản phẩm
                        </a>
                    </li>

                    {{-- Quản trị --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->routeIs('users.*','roles.*') ? 'active' : '' }}"
                           href="#" data-bs-toggle="dropdown" role="button">
                            <i class="bi bi-shield-lock"></i> Quản trị
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item {{ request()->routeIs('users.*') ? 'active' : '' }}"
                                   href="{{ route('users.index') }}">
                                <i class="bi bi-people"></i> Danh sách thành viên</a></li>
                            <li><a class="dropdown-item {{ request()->routeIs('roles.*') ? 'active' : '' }}"
                                   href="{{ route('roles.index') }}">
                                <i class="bi bi-shield-check"></i> Danh sách phân quyền</a></li>
                        </ul>
                    </li>
                </ul>

                <div class="header-actions d-flex align-items-center gap-2">
                    <button class="icon-btn" type="button" title="Toàn màn hình" onclick="toggleFullscreen()">
                        <i class="bi bi-arrows-fullscreen"></i>
                    </button>

                    <div class="dropdown">
                        <a href="#" class="user-chip text-decoration-none text-dark" data-bs-toggle="dropdown">
                            <span class="avatar">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</span>
                            <div class="d-none d-md-block">
                                <div class="name">{{ auth()->user()->name ?? 'Guest' }}</div>
                                <div class="role">{{ str_replace('_', ' ', auth()->user()->role ?? '') }}</div>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-box-arrow-right"></i> Đăng xuất
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</header>

<main class="app-body">
    @yield('content')
</main>

{{-- Toast container --}}
<div class="toast-container position-fixed top-0 end-0 p-3">
    @if(session('success'))
        <div class="toast align-items-center text-bg-success border-0 show" role="alert">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    @endif
    @if(session('error'))
        <div class="toast align-items-center text-bg-danger border-0 show" role="alert">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleFullscreen() {
        if (!document.fullscreenElement) document.documentElement.requestFullscreen();
        else document.exitFullscreen();
    }
    // Auto-hide toast after 4s
    document.querySelectorAll('.toast.show').forEach(t => setTimeout(() => bootstrap.Toast.getOrCreateInstance(t).hide(), 4000));
</script>
@stack('scripts')
</body>
</html>
