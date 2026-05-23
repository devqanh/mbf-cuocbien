@extends('layouts.app')

@section('title', 'Thông tin cá nhân')

@push('styles')
<style>
    .profile-hero {
        background: linear-gradient(135deg, #0153a9 0%, #013f80 100%);
        color: #fff;
        border-radius: 14px;
        padding: 28px 32px;
        margin-bottom: 20px;
        position: relative;
        overflow: hidden;
    }
    .profile-hero::after {
        content: '';
        position: absolute;
        width: 320px; height: 320px;
        border-radius: 50%;
        background: rgba(255,255,255,.06);
        top: -120px; right: -100px;
    }
    .profile-hero .avatar-xl {
        width: 80px; height: 80px;
        border-radius: 20px;
        background: rgba(255,255,255,.15);
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 34px; font-weight: 700;
        position: relative; z-index: 1;
    }
    .profile-hero h2 { font-size: 22px; font-weight: 700; margin: 0; position: relative; z-index: 1; }
    .profile-hero .email { opacity: .85; font-size: 14px; position: relative; z-index: 1; }
    .profile-hero .role-pill {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(255,255,255,.18);
        padding: 4px 12px; border-radius: 999px;
        font-size: 12px; font-weight: 600;
        margin-top: 6px;
        position: relative; z-index: 1;
    }

    .info-list { list-style: none; padding: 0; margin: 0; }
    .info-list li {
        display: flex; justify-content: space-between; align-items: center;
        padding: 12px 0;
        border-bottom: 1px dashed var(--azia-border);
        font-size: 13.5px;
    }
    .info-list li:last-child { border-bottom: none; }
    .info-list .label { color: var(--azia-muted); }
    .info-list .value { font-weight: 600; color: var(--azia-text); }

    .pw-hint {
        background: var(--azia-primary-soft);
        color: var(--azia-primary);
        border-radius: 10px;
        padding: 10px 14px;
        font-size: 12.5px;
        line-height: 1.5;
        margin-bottom: 16px;
    }
</style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <nav class="breadcrumb">
                <a href="{{ route('shipments.index') }}">Trang chủ</a>
                <span class="mx-2">/</span>
                <span>Thông tin cá nhân</span>
            </nav>
            <h1 class="mt-1">Thông tin cá nhân</h1>
        </div>
    </div>

    {{-- HERO --}}
    <div class="profile-hero d-flex align-items-center gap-3 flex-wrap">
        <div class="avatar-xl">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</div>
        <div style="position:relative; z-index:1">
            <h2>{{ $user->name }}</h2>
            <div class="email"><i class="bi bi-envelope me-1"></i> {{ $user->email }}</div>
            <span class="role-pill">
                <i class="bi bi-shield-check"></i> {{ $user->roleLabel() }}
            </span>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 small">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        {{-- Cột trái: 2 form --}}
        <div class="col-lg-8">
            {{-- Thông tin tài khoản --}}
            <div class="card mb-3">
                <div class="card-header">
                    <i class="bi bi-person-vcard me-1" style="color: var(--azia-primary)"></i>
                    Thông tin tài khoản
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.info') }}">
                        @csrf @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Họ tên <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control"
                                       value="{{ old('name', $user->name) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control"
                                       value="{{ old('email', $user->email) }}" required>
                            </div>
                        </div>
                        <div class="mt-3 text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2-circle me-1"></i> Lưu thay đổi
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Đổi mật khẩu --}}
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-key-fill me-1" style="color: var(--azia-primary)"></i>
                    Đổi mật khẩu
                </div>
                <div class="card-body">
                    <div class="pw-hint">
                        <i class="bi bi-info-circle me-1"></i>
                        Mật khẩu nên có tối thiểu 6 ký tự. Sau khi đổi, các thiết bị khác đã đăng nhập vẫn giữ phiên cho đến khi hết hạn.
                    </div>
                    <form method="POST" action="{{ route('profile.password') }}" autocomplete="off">
                        @csrf @method('PUT')
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Mật khẩu mới <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" required minlength="6">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                                <input type="password" name="password_confirmation" class="form-control" required minlength="6">
                            </div>
                        </div>
                        <div class="mt-3 text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-shield-lock me-1"></i> Cập nhật mật khẩu
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Cột phải: tóm tắt --}}
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-info-square me-1" style="color: var(--azia-primary)"></i>
                    Tổng quan tài khoản
                </div>
                <div class="card-body">
                    <ul class="info-list">
                        <li>
                            <span class="label">Mã thành viên</span>
                            <span class="value">#{{ $user->id }}</span>
                        </li>
                        <li>
                            <span class="label">Ngày tham gia</span>
                            <span class="value">{{ $user->created_at?->format('d/m/Y') ?? '—' }}</span>
                        </li>
                        <li>
                            <span class="label">Cập nhật gần nhất</span>
                            <span class="value">{{ $user->updated_at?->format('d/m/Y H:i') ?? '—' }}</span>
                        </li>
                        <li>
                            <span class="label">Vai trò</span>
                            <span class="value">{{ $user->roleLabel() }}</span>
                        </li>
                    </ul>

                    @if($user->roles->count() > 1)
                        <div class="mt-3">
                            <div class="small text-muted mb-2">Tất cả vai trò được gán:</div>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($user->roles as $r)
                                    <span class="badge badge-soft-primary">{{ $r->display_name ?: $r->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
