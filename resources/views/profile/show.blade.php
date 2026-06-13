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

    /* ===== Quản lý thiết bị ===== */
    .device-list { display: flex; flex-direction: column; gap: 10px; }
    .device-row {
        display: flex; align-items: center; gap: 14px;
        padding: 14px 16px;
        border: 1px solid var(--azia-border);
        border-radius: 12px;
        background: #fff;
        transition: border-color .15s, box-shadow .15s;
    }
    .device-row:hover { border-color: var(--azia-primary); box-shadow: 0 2px 10px rgba(1,83,169,.06); }
    .device-row.is-current { border-color: var(--azia-primary); background: var(--azia-primary-soft); }
    .device-ico {
        flex: 0 0 auto;
        width: 44px; height: 44px;
        border-radius: 12px;
        background: var(--azia-primary-soft);
        color: var(--azia-primary);
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 22px;
    }
    .device-row.is-current .device-ico { background: var(--azia-primary); color: #fff; }
    .device-meta { flex: 1; min-width: 0; }
    .device-meta .title {
        font-weight: 600; font-size: 14px; color: var(--azia-text);
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }
    .device-meta .sub {
        font-size: 12.5px; color: var(--azia-muted);
        margin-top: 3px;
        display: flex; flex-wrap: wrap; gap: 6px 14px;
    }
    .device-meta .sub i { color: var(--azia-primary); margin-right: 3px; }
    .device-actions { flex: 0 0 auto; }
    .device-current-badge {
        display: inline-flex; align-items: center; gap: 4px;
        background: var(--azia-primary); color: #fff;
        padding: 2px 10px; border-radius: 999px;
        font-size: 11px; font-weight: 600;
    }

    /* Khung mã QR 2FA */
    .tf-qr-frame {
        display: inline-block;
        padding: 12px;
        background: #fff;
        border: 1px solid var(--azia-border);
        border-radius: 14px;
        box-shadow: 0 2px 10px rgba(28,39,60,.06);
        line-height: 0;
    }
    .tf-qr-frame #tfQr { width: 188px; height: 188px; }
    .tf-qr-frame #tfQr img,
    .tf-qr-frame #tfQr canvas { width: 188px !important; height: 188px !important; display: block; }
</style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <nav class="breadcrumb">
                <a href="{{ route('trucking.index') }}">Trang chủ</a>
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
                    <form id="passwordForm" method="POST" action="{{ route('profile.password') }}" autocomplete="off">
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

            {{-- Xác thực 2 lớp (2FA) --}}
            <div class="card mt-3" id="twoFactorCard" data-enabled="{{ $user->hasTwoFactorEnabled() ? '1' : '0' }}">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <i class="bi bi-shield-lock-fill me-1" style="color: var(--azia-primary)"></i>
                        Xác thực 2 lớp (2FA)
                        @if($user->hasTwoFactorEnabled())
                            <span class="badge badge-soft-primary ms-1" id="tfBadge"><i class="bi bi-check-circle-fill me-1"></i>Đang bật</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary ms-1" id="tfBadge">Chưa bật</span>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <div class="pw-hint">
                        <i class="bi bi-info-circle me-1"></i>
                        Bảo vệ tài khoản bằng mã 6 số đổi mỗi 30 giây từ ứng dụng Authenticator
                        (Google Authenticator, Microsoft Authenticator, Authy…). Mỗi lần đăng nhập
                        sẽ cần thêm mã này ngoài mật khẩu.
                    </div>

                    {{-- TRẠNG THÁI: CHƯA BẬT --}}
                    <div id="tfDisabledView" class="@if($user->hasTwoFactorEnabled()) d-none @endif">
                        <button type="button" class="btn btn-primary" id="btnStart2fa">
                            <i class="bi bi-shield-plus me-1"></i> Bật xác thực 2 lớp
                        </button>

                        {{-- Khu setup (ẩn cho tới khi bấm Bật) --}}
                        <div id="tfSetup" class="d-none mt-3">
                            <div class="row g-4 align-items-start">
                                <div class="col-12 col-md-auto">
                                    <div class="tf-qr-frame">
                                        <div id="tfQr"></div>
                                    </div>
                                    <div class="text-danger small mt-2 d-none" id="tfQrErr">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        Không tải được mã QR. Hãy nhập khoá bí mật bên cạnh thủ công.
                                    </div>
                                </div>
                                <div class="col-12 col-md">
                                    <ol class="ps-3 mb-3 small" style="line-height:1.8">
                                        <li>Mở app Authenticator → <b>thêm tài khoản</b> → quét mã QR.</li>
                                        <li>Không quét được? Nhập thủ công khoá bí mật:</li>
                                    </ol>
                                    <div class="input-group input-group-sm mb-4">
                                        <input type="text" id="tfSecret" class="form-control font-monospace" readonly>
                                        <button class="btn btn-outline-secondary" type="button" id="btnCopySecret" title="Sao chép">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                    <label class="form-label fw-semibold">Nhập mã 6 số để xác nhận <span class="text-danger">*</span></label>
                                    <div class="d-flex gap-2 flex-nowrap align-items-stretch">
                                        <input type="text" id="tfCode" class="form-control flex-grow-1" inputmode="numeric"
                                               autocomplete="one-time-code" maxlength="6" placeholder="000000"
                                               style="letter-spacing:.3em; font-weight:600; text-align:center; max-width:160px;">
                                        <button type="button" class="btn btn-primary flex-shrink-0 text-nowrap px-3" id="btnConfirm2fa">
                                            <i class="bi bi-check2-circle me-1"></i> Xác nhận
                                        </button>
                                    </div>
                                    <div class="text-danger small mt-2 d-none" id="tfCodeErr"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- TRẠNG THÁI: ĐÃ BẬT --}}
                    <div id="tfEnabledView" class="@if(! $user->hasTwoFactorEnabled()) d-none @endif">
                        <div class="d-flex align-items-center gap-2 mb-3 text-success">
                            <i class="bi bi-shield-check fs-4"></i>
                            <div class="small">Tài khoản đang được bảo vệ bằng xác thực 2 lớp.</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRegenCodes">
                                <i class="bi bi-arrow-repeat me-1"></i> Tạo lại mã khôi phục
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="btnDisable2fa">
                                <i class="bi bi-shield-slash me-1"></i> Tắt 2FA
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Quản lý thiết bị đăng nhập --}}
            <div class="card mt-3">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <i class="bi bi-laptop me-1" style="color: var(--azia-primary)"></i>
                        Quản lý thiết bị đăng nhập
                        <span class="badge badge-soft-primary ms-1">{{ count($sessions) }}</span>
                    </div>
                    @if(count($sessions) > 1)
                        <button type="button" class="btn btn-sm btn-outline-danger" id="btnLogoutOthers">
                            <i class="bi bi-box-arrow-right me-1"></i> Đăng xuất các thiết bị khác
                        </button>
                    @endif
                </div>
                <div class="card-body">
                    @if(empty($sessions))
                        <div class="text-muted small">
                            <i class="bi bi-info-circle me-1"></i>
                            Không có phiên đăng nhập nào được ghi nhận.
                        </div>
                    @else
                        <div class="device-list" id="deviceList">
                            @foreach($sessions as $s)
                                <div class="device-row {{ $s['is_current'] ? 'is-current' : '' }}" data-session-id="{{ $s['id'] }}">
                                    <div class="device-ico"><i class="bi bi-{{ $s['icon'] }}"></i></div>
                                    <div class="device-meta">
                                        <div class="title">
                                            <span>{{ $s['browser'] }} · {{ $s['os'] }}</span>
                                            @if($s['is_current'])
                                                <span class="device-current-badge"><i class="bi bi-check-circle-fill"></i> Phiên hiện tại</span>
                                            @endif
                                        </div>
                                        <div class="sub">
                                            <span><i class="bi bi-geo-alt"></i>{{ $s['location'] ?? 'Không xác định vị trí' }}</span>
                                            <span><i class="bi bi-hdd-network"></i>{{ $s['ip'] ?? '—' }}</span>
                                            <span><i class="bi bi-clock-history"></i>Hoạt động {{ $s['last_activity']->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                    <div class="device-actions">
                                        @if(! $s['is_current'])
                                            <button type="button" class="btn btn-sm btn-outline-danger js-revoke"
                                                    data-session-id="{{ $s['id'] }}"
                                                    data-device="{{ $s['browser'] }} · {{ $s['os'] }}">
                                                <i class="bi bi-box-arrow-right me-1"></i> Thoát
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
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

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"
        integrity="sha384-3zSEDfvllQohrq0PHL1fOXJuC/jSOO34H46t6UQfobFOmxE5BpjjaIJY5F2/bMnU"
        crossorigin="anonymous"></script>
<script>
    // Confirm trước khi đổi mật khẩu — security-sensitive
    (function () {
        const $form = document.getElementById('passwordForm');
        if (! $form) return;
        $form.addEventListener('submit', async (e) => {
            if ($form.dataset.confirmed === '1') return;
            e.preventDefault();
            const ok = await window.confirmAction({
                title: 'Xác nhận đổi mật khẩu?',
                text: 'Hãy ghi nhớ mật khẩu mới của bạn. Các thiết bị đã đăng nhập sẽ vẫn giữ phiên cho đến khi hết hạn.',
                confirmText: '<i class="bi bi-shield-lock me-1"></i> Đổi mật khẩu',
                danger: true,
            });
            if (! ok) return;
            $form.dataset.confirmed = '1';
            $form.submit();
        });
    })();

    // ===== Quản lý thiết bị =====
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const esc  = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

        async function call(url, method) {
            const res = await fetch(url, {
                method,
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            const data = await res.json().catch(() => ({}));
            return { ok: res.ok && data.ok !== false, data };
        }

        // Thoát 1 thiết bị
        document.querySelectorAll('.js-revoke').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id     = btn.dataset.sessionId;
                const device = btn.dataset.device || 'Thiết bị này';
                const ok = await window.confirmAction({
                    title: 'Thoát thiết bị này?',
                    text: `Phiên đăng nhập trên <b>${esc(device)}</b> sẽ bị huỷ. Lần truy cập tiếp theo, thiết bị đó sẽ phải đăng nhập lại.`,
                    confirmText: '<i class="bi bi-box-arrow-right me-1"></i> Thoát thiết bị',
                    danger: true,
                });
                if (! ok) return;

                const { ok: success, data } = await call(`{{ url('/profile/sessions') }}/${encodeURIComponent(id)}`, 'DELETE');
                if (success) {
                    btn.closest('.device-row')?.remove();
                    Swal.fire({ ...APP_SWAL, icon: 'success', title: 'Đã thoát thiết bị', timer: 1600, showConfirmButton: false });
                } else {
                    Swal.fire({ ...APP_SWAL, icon: 'error', title: 'Không thể thoát thiết bị', text: data.message || 'Vui lòng thử lại.' });
                }
            });
        });

        // ===== Xác thực 2 lớp (2FA) =====
        (function () {
            const card = document.getElementById('twoFactorCard');
            if (! card) return;

            const post = async (url, body, method = 'POST') => {
                const res = await fetch(url, {
                    method,
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: body ? JSON.stringify(body) : undefined,
                });
                const data = await res.json().catch(() => ({}));
                return { ok: res.ok && data.ok !== false, status: res.status, data };
            };

            // Hiển thị danh sách mã khôi phục (1 lần) — nhắc user lưu lại.
            const showRecoveryCodes = (codes) => {
                const list = (codes || []).map(c => `<code style="font-size:15px">${esc(c)}</code>`).join('<br>');
                return Swal.fire({
                    ...APP_SWAL,
                    icon: 'success',
                    title: 'Mã khôi phục của bạn',
                    html: `<div class="text-start small mb-2 text-muted">Lưu các mã này nơi an toàn. Mỗi mã dùng được <b>1 lần</b> để đăng nhập khi mất thiết bị. Sẽ không hiển thị lại.</div>
                           <div class="text-center p-2 rounded" style="background:#f6f8fb; line-height:2">${list}</div>`,
                    confirmButtonText: '<i class="bi bi-clipboard-check me-1"></i> Tôi đã lưu',
                    showCancelButton: true,
                    cancelButtonText: 'Sao chép',
                    preConfirm: () => true,
                }).then((r) => {
                    if (r.dismiss === Swal.DismissReason.cancel) {
                        navigator.clipboard?.writeText((codes || []).join('\n'));
                        Swal.fire({ ...APP_SWAL, icon: 'success', title: 'Đã sao chép mã', timer: 1200, showConfirmButton: false });
                    }
                });
            };

            // --- Bật 2FA: lấy secret + QR ---
            document.getElementById('btnStart2fa')?.addEventListener('click', async (e) => {
                const $btn = e.currentTarget;
                $btn.disabled = true;
                const { ok, data } = await post(@json(route('profile.2fa.start')));
                $btn.disabled = false;
                if (! ok) {
                    Swal.fire({ ...APP_SWAL, icon: 'error', title: 'Không thể bật 2FA', text: data.message || 'Vui lòng thử lại.' });
                    return;
                }
                document.getElementById('tfSecret').value = data.secret;
                document.getElementById('tfSetup').classList.remove('d-none');
                $btn.classList.add('d-none');

                // Render QR bằng qrcodejs (global QRCode). Lỗi/CDN chặn → hiện
                // cảnh báo và để user nhập khoá bí mật thủ công.
                const qrEl  = document.getElementById('tfQr');
                const qrErr = document.getElementById('tfQrErr');
                qrEl.innerHTML = '';
                qrErr.classList.add('d-none');
                try {
                    if (! window.QRCode) throw new Error('QRCode chưa nạp');
                    new QRCode(qrEl, {
                        text: data.otpauthUrl,
                        width: 188,
                        height: 188,
                        correctLevel: QRCode.CorrectLevel.M,
                    });
                } catch (err) {
                    qrErr.classList.remove('d-none');
                }
                document.getElementById('tfCode')?.focus();
            });

            // --- Sao chép secret ---
            document.getElementById('btnCopySecret')?.addEventListener('click', () => {
                navigator.clipboard?.writeText(document.getElementById('tfSecret').value);
                Swal.fire({ ...APP_SWAL, icon: 'success', title: 'Đã sao chép', timer: 1000, showConfirmButton: false });
            });

            // --- Xác nhận mã để hoàn tất bật 2FA ---
            const doConfirm = async () => {
                const $err = document.getElementById('tfCodeErr');
                const code = document.getElementById('tfCode').value.trim();
                $err.classList.add('d-none');
                if (!/^\d{6}$/.test(code)) {
                    $err.textContent = 'Nhập đúng mã 6 số.';
                    $err.classList.remove('d-none');
                    return;
                }
                const { ok, data } = await post(@json(route('profile.2fa.confirm')), { code });
                if (! ok) {
                    $err.textContent = (data.errors?.code?.[0]) || data.message || 'Mã không đúng.';
                    $err.classList.remove('d-none');
                    return;
                }
                await showRecoveryCodes(data.recoveryCodes);
                // Chuyển sang trạng thái "đã bật"
                document.getElementById('tfDisabledView').classList.add('d-none');
                document.getElementById('tfEnabledView').classList.remove('d-none');
                const badge = document.getElementById('tfBadge');
                if (badge) { badge.className = 'badge badge-soft-primary ms-1'; badge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Đang bật'; }
            };
            document.getElementById('btnConfirm2fa')?.addEventListener('click', doConfirm);
            document.getElementById('tfCode')?.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') { ev.preventDefault(); doConfirm(); } });

            // --- Tạo lại mã khôi phục ---
            document.getElementById('btnRegenCodes')?.addEventListener('click', async () => {
                const ok = await window.confirmAction({
                    title: 'Tạo lại mã khôi phục?',
                    text: 'Bộ mã khôi phục cũ sẽ <b>hết hiệu lực ngay</b>. Bạn sẽ nhận bộ mã mới.',
                    confirmText: '<i class="bi bi-arrow-repeat me-1"></i> Tạo lại',
                });
                if (! ok) return;
                const { ok: success, data } = await post(@json(route('profile.2fa.recovery')));
                if (success) showRecoveryCodes(data.recoveryCodes);
                else Swal.fire({ ...APP_SWAL, icon: 'error', title: 'Không thể tạo lại', text: data.message || 'Vui lòng thử lại.' });
            });

            // --- Tắt 2FA (yêu cầu nhập mật khẩu) ---
            document.getElementById('btnDisable2fa')?.addEventListener('click', async () => {
                const { value: password, isConfirmed } = await Swal.fire({
                    ...APP_SWAL,
                    icon: 'warning',
                    title: 'Tắt xác thực 2 lớp?',
                    html: '<div class="text-start small text-muted mb-2">Nhập mật khẩu hiện tại để xác nhận. Tài khoản sẽ giảm mức bảo mật.</div>',
                    input: 'password',
                    inputPlaceholder: 'Mật khẩu hiện tại',
                    inputAttributes: { autocomplete: 'current-password' },
                    showCancelButton: true,
                    cancelButtonText: 'Huỷ',
                    confirmButtonText: '<i class="bi bi-shield-slash me-1"></i> Tắt 2FA',
                });
                if (! isConfirmed || ! password) return;
                const { ok, data } = await post(@json(route('profile.2fa.disable')), { password }, 'DELETE');
                if (! ok) {
                    Swal.fire({ ...APP_SWAL, icon: 'error', title: 'Không thể tắt', text: (data.errors?.password?.[0]) || data.message || 'Mật khẩu không đúng.' });
                    return;
                }
                document.getElementById('tfEnabledView').classList.add('d-none');
                document.getElementById('tfDisabledView').classList.remove('d-none');
                document.getElementById('btnStart2fa').classList.remove('d-none');
                document.getElementById('tfSetup').classList.add('d-none');
                const badge = document.getElementById('tfBadge');
                if (badge) { badge.className = 'badge bg-secondary-subtle text-secondary ms-1'; badge.textContent = 'Chưa bật'; }
                Swal.fire({ ...APP_SWAL, icon: 'success', title: 'Đã tắt 2FA', timer: 1500, showConfirmButton: false });
            });
        })();

        // Đăng xuất tất cả thiết bị khác
        const $btnAll = document.getElementById('btnLogoutOthers');
        if ($btnAll) {
            $btnAll.addEventListener('click', async () => {
                const ok = await window.confirmAction({
                    title: 'Đăng xuất các thiết bị khác?',
                    text: 'Tất cả phiên đăng nhập trên các thiết bị khác (không phải thiết bị hiện tại) sẽ bị huỷ.',
                    confirmText: '<i class="bi bi-box-arrow-right me-1"></i> Đăng xuất tất cả',
                    danger: true,
                });
                if (! ok) return;

                const { ok: success, data } = await call('{{ route('profile.sessions.logoutOthers') }}', 'POST');
                if (success) {
                    // Xoá tất cả row không phải current khỏi DOM
                    document.querySelectorAll('#deviceList .device-row:not(.is-current)').forEach((el) => el.remove());
                    $btnAll.remove();
                    Swal.fire({ ...APP_SWAL, icon: 'success', title: data.message || 'Đã đăng xuất các thiết bị khác', timer: 1800, showConfirmButton: false });
                } else {
                    Swal.fire({ ...APP_SWAL, icon: 'error', title: 'Không thể thực hiện', text: data.message || 'Vui lòng thử lại.' });
                }
            });
        }
    })();
</script>
@endpush
