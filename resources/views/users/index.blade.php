@extends('layouts.app')

@section('title', 'Danh sách thành viên')

@section('content')
    <div class="page-header">
        <div>
            <h1>Danh sách thành viên</h1>
            <nav class="breadcrumb mt-1">
                <a href="{{ route('trucking.index') }}">Trang chủ</a>
                <span class="mx-2">/</span>
                <span>Quản trị</span>
                <span class="mx-2">/</span>
                <span>Thành viên</span>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('users.broadcastTest') }}" class="m-0" id="broadcastTestForm">
                @csrf
                <button type="button" id="btnBroadcastTest" class="btn btn-outline-secondary"
                        title="Đẩy 1 thông báo test tới tất cả user để kiểm tra Reverb">
                    <i class="bi bi-broadcast me-1"></i> Test broadcast
                </button>
            </form>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal"
                    onclick="openUserModal(null)">
                <i class="bi bi-person-plus me-1"></i> Thêm thành viên
            </button>
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('btnBroadcastTest')?.addEventListener('click', async (e) => {
            const $btn = e.currentTarget;
            const ok = await confirmAction({
                title: 'Đẩy thông báo TEST?',
                text: 'Tất cả <b>{{ $users->total() }}</b> user trong hệ thống sẽ nhận 1 toast realtime để kiểm tra Reverb còn sống không.',
                confirmText: '<i class="bi bi-broadcast me-1"></i> Đẩy ngay',
            });
            if (! ok) return;
            $btn.disabled = true;
            $btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Đang đẩy…';
            document.getElementById('broadcastTestForm').submit();
        });
    </script>
    @endpush

    {{-- Khối nhắc nhở bảo mật 2FA --}}
    @php
        $tfPercent = $totalUsers > 0 ? round($twoFactorEnabled / $totalUsers * 100) : 0;
    @endphp
    <div class="tf-guide mb-3">
        <div class="tf-guide-head">
            <div class="tf-guide-icon"><i class="bi bi-shield-lock-fill"></i></div>
            <div class="flex-grow-1">
                <div class="tf-guide-title">Xác thực 2 lớp (2FA) — khuyến nghị bật cho mọi thành viên</div>
                <div class="tf-guide-sub">
                    Đã bật: <strong>{{ $twoFactorEnabled }}/{{ $totalUsers }}</strong> thành viên ({{ $tfPercent }}%).
                    Mỗi người tự bật tại trang <a href="{{ route('profile.show') }}">Thông tin cá nhân</a>.
                </div>
                <div class="tf-progress mt-2"><span style="width: {{ $tfPercent }}%"></span></div>
            </div>
            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 tf-guide-toggle"
                    data-bs-toggle="collapse" data-bs-target="#tfGuideBody">
                Cách dùng an toàn <i class="bi bi-chevron-down"></i>
            </button>
        </div>
        <div class="collapse" id="tfGuideBody">
            {{-- Vì sao nên bật --}}
            <div class="tf-why mt-3">
                <div class="tf-why-title"><i class="bi bi-question-circle-fill me-1"></i> Vì sao nên bật 2FA?</div>
                <p class="tf-why-lead">
                    Chỉ mật khẩu là <b>chưa đủ an toàn</b>: mật khẩu có thể bị lộ do dùng lại ở nhiều nơi,
                    bị đoán, dính link giả (phishing) hay rò rỉ từ web khác. 2FA thêm <b>lớp khoá thứ hai</b> —
                    mã 6 số chỉ có trên điện thoại của bạn — nên dù lộ mật khẩu, kẻ xấu vẫn <b>không đăng nhập được</b>.
                </p>
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="tf-why-item"><i class="bi bi-shield-exclamation"></i>
                            <div><b>Chặn lộ mật khẩu</b><span>Mật khẩu bị lộ vẫn vô hại nếu thiếu mã trên thiết bị của bạn.</span></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="tf-why-item"><i class="bi bi-database-lock"></i>
                            <div><b>Bảo vệ dữ liệu kinh doanh</b><span>Giá cước, bảng kê, thông tin khách hàng không lọt ra ngoài.</span></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="tf-why-item"><i class="bi bi-globe2"></i>
                            <div><b>Ngăn đăng nhập từ xa lạ</b><span>Truy cập trái phép từ máy/người khác bị chặn ngay tại bước mã.</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tf-why-title mt-3"><i class="bi bi-list-check me-1"></i> Cách dùng an toàn</div>
            <div class="row g-3 mt-0">
                <div class="col-md-6">
                    <div class="tf-tip"><i class="bi bi-phone"></i>
                        <div><b>Dùng app authenticator</b><span>Google Authenticator, Microsoft Authenticator hoặc Authy — an toàn hơn nhận mã qua SMS.</span></div>
                    </div>
                    <div class="tf-tip"><i class="bi bi-key"></i>
                        <div><b>Lưu mã khôi phục nơi an toàn</b><span>Khi bật 2FA hệ thống cấp 8 mã khôi phục. Cất kỹ (sổ tay/trình quản lý mật khẩu), <b>đừng</b> gửi qua chat/email.</span></div>
                    </div>
                    <div class="tf-tip"><i class="bi bi-eye-slash"></i>
                        <div><b>Không chia sẻ mã 6 số</b><span>Mã đổi mỗi 30 giây và chỉ dành cho bạn — nhân viên/admin không bao giờ hỏi xin mã này.</span></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="tf-tip"><i class="bi bi-arrow-repeat"></i>
                        <div><b>Mất điện thoại? Dùng mã khôi phục</b><span>Nhập một mã khôi phục (dạng XXXX-XXXX) ở màn đăng nhập để vào, sau đó bật lại 2FA bằng thiết bị mới.</span></div>
                    </div>
                    <div class="tf-tip"><i class="bi bi-shield-slash"></i>
                        <div><b>Hết cả mã khôi phục? Nhờ admin reset</b><span>Admin bấm <i class="bi bi-shield-slash"></i> ở dòng thành viên (hoặc trong cửa sổ Sửa) để reset 2FA; người đó đăng nhập bằng mật khẩu rồi thiết lập lại.</span></div>
                    </div>
                    <div class="tf-tip"><i class="bi bi-person-lock"></i>
                        <div><b>Tài khoản quyền cao nên bật trước</b><span>Super admin và người có quyền quản trị là mục tiêu ưu tiên — hãy bật 2FA sớm nhất.</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>Tổng cộng <strong>{{ $users->total() }}</strong> thành viên</div>
            <form method="GET" action="{{ route('users.index') }}" class="d-flex gap-2" style="max-width:300px;">
                <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm"
                       placeholder="Tìm theo tên / email...">
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Họ tên</th>
                        <th>Email</th>
                        <th>Vai trò</th>
                        <th>Ngày tạo</th>
                        <th class="text-end" style="width:120px">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($users as $u)
                    <tr>
                        <td>{{ $u->id }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="user-chip"><span class="avatar">{{ strtoupper(substr($u->name,0,1)) }}</span></span>
                                <span class="fw-semibold">{{ $u->name }}</span>
                                @if($u->hasTwoFactorEnabled())
                                    <span class="badge badge-soft-primary" title="Đã bật xác thực 2 lớp">
                                        <i class="bi bi-shield-lock-fill"></i> 2FA
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border" title="Chưa bật xác thực 2 lớp">
                                        <i class="bi bi-shield-slash"></i> Chưa 2FA
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td>{{ $u->email }}</td>
                        <td>
                            @forelse($u->roles as $r)
                                <span class="badge badge-soft-primary">{{ $r->name }}</span>
                            @empty
                                <span class="text-muted small">— chưa gán —</span>
                            @endforelse
                        </td>
                        <td><span class="text-muted">{{ $u->created_at?->format('d/m/Y H:i') }}</span></td>
                        @php $uJson = $u->only(["id","name","email"]) + ["roles" => $u->roles->pluck("name"), "two_factor" => $u->hasTwoFactorEnabled()]; @endphp
                        <td class="text-end">
                            <div class="action-group">
                                <button type="button" class="action-btn action-edit" title="Sửa"
                                        onclick='openUserModal(@json($uJson))'
                                        data-bs-toggle="modal" data-bs-target="#userModal">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                @if($u->hasTwoFactorEnabled())
                                    <form action="{{ route('users.2fa.reset', $u) }}" method="POST"
                                          onsubmit="event.preventDefault(); confirmAction({title: 'Reset 2FA?', text: 'Tắt xác thực 2 lớp của <b>{{ $u->name }}</b> (dùng khi họ mất thiết bị authenticator). Họ sẽ đăng nhập bằng mật khẩu và tự bật lại sau.', confirmText: '<i class=&quot;bi bi-shield-slash me-1&quot;></i> Reset 2FA', danger: true}).then(ok => { if (ok) this.submit(); }); return false;">
                                        @csrf
                                        <button type="submit" class="action-btn action-2fa" title="Reset 2FA (thành viên mất thiết bị)">
                                            <i class="bi bi-shield-slash"></i>
                                        </button>
                                    </form>
                                @endif
                                <form action="{{ route('users.destroy', $u) }}" method="POST"
                                      onsubmit="return confirmDelete(this, {title: 'Xoá thành viên?', text: 'Bạn sắp xoá <b>{{ $u->name }}</b>. Hành động này không thể hoàn tác.'})">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="action-btn action-delete" title="Xoá">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Không có dữ liệu</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">
            {{ $users->links() }}
        </div>
    </div>

    {{-- Modal thêm/sửa thành viên --}}
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="userForm" method="POST">
                    @csrf
                    <input type="hidden" name="_method" value="POST" id="u_method">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-person-gear me-1"></i> <span id="u_title">Thêm thành viên</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        {{-- Nhận diện trực quan: avatar + tên/email cập nhật trực tiếp --}}
                        <div class="um-identity d-flex align-items-center gap-3 mb-4">
                            <div class="um-avatar" id="u_avatar">?</div>
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate" id="u_avatar_name" style="font-size:15px">Thành viên mới</div>
                                <div class="text-muted small text-truncate" id="u_avatar_email">chưa có email</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Họ tên <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-muted"><i class="bi bi-person"></i></span>
                                    <input type="text" name="name" id="u_name" class="form-control" placeholder="Nguyễn Văn A" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-muted"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" id="u_email" class="form-control" placeholder="name@example.com" required>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label fw-semibold">Mật khẩu <small class="text-muted fw-normal" id="u_pw_hint">(bắt buộc)</small></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" id="u_password" class="form-control" autocomplete="new-password" placeholder="Tối thiểu 6 ký tự">
                                <button type="button" class="btn btn-outline-secondary" id="u_pw_toggle" title="Hiện/ẩn mật khẩu">
                                    <i class="bi bi-eye" id="u_pw_eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="um-section-label">Vai trò &amp; quyền truy cập</div>
                            <div class="um-roles">
                                @foreach($roles as $r)
                                    <label class="um-role" for="role_{{ $r->id }}">
                                        <input class="form-check-input role-check d-none" type="checkbox"
                                               name="roles[]" value="{{ $r->name }}" id="role_{{ $r->id }}">
                                        <i class="bi bi-shield-check"></i>
                                        <span>{{ $r->display_name ?: $r->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Trạng thái 2FA — chỉ hiện khi SỬA user đã bật --}}
                        <div class="mt-4 d-none" id="u_2fa_block">
                            <div class="um-section-label">Bảo mật</div>
                            <div class="d-flex align-items-center justify-content-between gap-2 p-3 rounded"
                                 style="background:#fff7ed; border:1px solid #fed7aa">
                                <div class="small">
                                    <i class="bi bi-shield-lock-fill me-1" style="color:#c2410c"></i>
                                    <b>Đã bật xác thực 2 lớp.</b>
                                    <span class="text-muted d-block">Reset khi thành viên mất thiết bị authenticator để họ thiết lập lại.</span>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-warning flex-shrink-0" id="u_2fa_reset">
                                    <i class="bi bi-shield-slash me-1"></i> Reset 2FA
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Lưu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const URL_STORE = @json(route('users.store'));
    const URL_UPDATE_BASE = @json(url('users'));
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Cập nhật avatar + tên/email hiển thị theo input (preview trực tiếp)
    function refreshIdentity() {
        const name  = document.getElementById('u_name').value.trim();
        const email = document.getElementById('u_email').value.trim();
        document.getElementById('u_avatar').textContent      = name ? name[0] : '?';
        document.getElementById('u_avatar_name').textContent  = name || 'Thành viên mới';
        document.getElementById('u_avatar_email').textContent = email || 'chưa có email';
    }

    function openUserModal(user) {
        const form = document.getElementById('userForm');
        document.querySelectorAll('.role-check').forEach(c => c.checked = false);
        const $2fa = document.getElementById('u_2fa_block');

        if (user) {
            document.getElementById('u_title').textContent = 'Sửa thành viên';
            document.getElementById('u_method').value = 'PUT';
            form.action = `${URL_UPDATE_BASE}/${user.id}`;
            document.getElementById('u_name').value     = user.name;
            document.getElementById('u_email').value    = user.email;
            document.getElementById('u_password').value = '';
            document.getElementById('u_pw_hint').textContent = '(để trống nếu không đổi)';
            (user.roles || []).forEach(name => {
                const el = document.querySelector(`.role-check[value="${name}"]`);
                if (el) el.checked = true;
            });
            // Khối 2FA chỉ hiện khi user này đã bật
            if (user.two_factor) {
                $2fa.classList.remove('d-none');
                document.getElementById('u_2fa_reset').dataset.url  = `${URL_UPDATE_BASE}/${user.id}/2fa/reset`;
                document.getElementById('u_2fa_reset').dataset.name = user.name;
            } else {
                $2fa.classList.add('d-none');
            }
        } else {
            document.getElementById('u_title').textContent = 'Thêm thành viên';
            document.getElementById('u_method').value = 'POST';
            form.action = URL_STORE;
            document.getElementById('u_name').value = '';
            document.getElementById('u_email').value = '';
            document.getElementById('u_password').value = '';
            document.getElementById('u_pw_hint').textContent = '(bắt buộc)';
            $2fa.classList.add('d-none');
        }
        refreshIdentity();
    }

    // Preview trực tiếp khi gõ
    document.getElementById('u_name')?.addEventListener('input', refreshIdentity);
    document.getElementById('u_email')?.addEventListener('input', refreshIdentity);

    // Toggle hiện/ẩn mật khẩu
    document.getElementById('u_pw_toggle')?.addEventListener('click', () => {
        const inp = document.getElementById('u_password');
        const eye = document.getElementById('u_pw_eye');
        const show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        eye.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });

    // Reset 2FA ngay trong modal
    document.getElementById('u_2fa_reset')?.addEventListener('click', async (e) => {
        const btn  = e.currentTarget;
        const name = btn.dataset.name || 'thành viên này';
        const ok = await confirmAction({
            title: 'Reset 2FA?',
            text: `Tắt xác thực 2 lớp của <b>${name.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}</b>. Họ sẽ đăng nhập bằng mật khẩu và tự bật lại sau.`,
            confirmText: '<i class="bi bi-shield-slash me-1"></i> Reset 2FA',
            danger: true,
        });
        if (! ok) return;
        const res = await fetch(btn.dataset.url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            credentials: 'same-origin',
        });
        if (res.ok) {
            window.location.reload();
        } else {
            Swal.fire({ ...APP_SWAL, icon: 'error', title: 'Không thể reset', text: 'Vui lòng thử lại.' });
        }
    });

    // Confirm trước khi lưu user (gán role = trao quyền truy cập)
    (function () {
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const $userForm = document.getElementById('userForm');
        $userForm.addEventListener('submit', async (e) => {
            if ($userForm.dataset.confirmed === '1') return;
            e.preventDefault();
            const isUpdate = document.getElementById('u_method').value === 'PUT';
            const name  = document.getElementById('u_name').value.trim() || '(chưa đặt tên)';
            const email = document.getElementById('u_email').value.trim() || '(chưa có email)';
            const roles = Array.from(document.querySelectorAll('#userForm .role-check:checked')).map(c => c.value);
            const rolesHtml = roles.length ? esc(roles.join(', ')) : '<i class="text-muted">chưa gán vai trò</i>';
            const ok = await window.confirmAction({
                title: isUpdate ? 'Cập nhật thành viên?' : 'Tạo thành viên mới?',
                text: `<div class="text-start"><b>${esc(name)}</b> · ${esc(email)}<br><span class="text-muted small">Vai trò:</span> ${rolesHtml}</div>`,
                confirmText: '<i class="bi bi-save me-1"></i> ' + (isUpdate ? 'Cập nhật' : 'Tạo'),
            });
            if (! ok) return;
            $userForm.dataset.confirmed = '1';
            $userForm.submit();
        });
    })();
</script>
@endpush
