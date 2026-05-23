@extends('layouts.app')

@section('title', 'Danh sách thành viên')

@push('styles')
<style>
    /* Fix scroll modal Quyền cột — cách đơn giản và chắc chắn nhất:
       set explicit max-height cho modal-body + overflow-y:auto */
    #columnPermsModal .modal-body {
        max-height: calc(100vh - 220px) !important;   /* trừ header (~70px) + footer (~70px) + margins */
        overflow-y: auto !important;
        overflow-x: hidden !important;
    }
    /* Đảm bảo modal container không tự scroll page */
    #columnPermsModal.modal {
        padding-right: 0 !important;
    }
</style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <h1>Danh sách thành viên</h1>
            <nav class="breadcrumb mt-1">
                <a href="{{ route('shipments.index') }}">Trang chủ</a>
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
                        <th class="text-end" style="width:160px">Thao tác</th>
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
                        @php
                            $uJson = $u->only(["id","name","email"]) + ["roles" => $u->roles->pluck("name")];
                            $uPermsJson = ['id' => $u->id, 'name' => $u->name, 'permissions' => (object) ($u->column_permissions ?? [])];
                        @endphp
                        <td class="text-end">
                            <div class="action-group">
                                <button type="button" class="action-btn action-view" title="Quyền cột"
                                        onclick='openColumnPermsModal(@json($uPermsJson))'
                                        data-bs-toggle="modal" data-bs-target="#columnPermsModal">
                                    <i class="bi bi-grid-3x3-gap"></i>
                                </button>
                                <button type="button" class="action-btn action-edit" title="Sửa"
                                        onclick='openUserModal(@json($uJson))'
                                        data-bs-toggle="modal" data-bs-target="#userModal">
                                    <i class="bi bi-pencil"></i>
                                </button>
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

    {{-- Modal --}}
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
                        <div class="mb-3">
                            <label class="form-label">Họ tên <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="u_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="u_email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu <small class="text-muted" id="u_pw_hint">(bắt buộc)</small></label>
                            <input type="password" name="password" id="u_password" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Vai trò</label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($roles as $r)
                                    <div class="form-check">
                                        <input class="form-check-input role-check" type="checkbox"
                                               name="roles[]" value="{{ $r->name }}" id="role_{{ $r->id }}">
                                        <label class="form-check-label" for="role_{{ $r->id }}">{{ $r->name }}</label>
                                    </div>
                                @endforeach
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

    {{-- ===== Modal Cấu hình QUYỀN CỘT cho user ===== --}}
    @php
        $groupedCols = collect($shipmentColumns)->groupBy('group');
        $groupNames = [
            1 => ['title' => 'NHÓM 1 — Thông tin lô hàng',     'color' => '#D4E6B5'],
            2 => ['title' => 'NHÓM 2 — Chứng từ vận chuyển',   'color' => '#FCE4D6'],
            3 => ['title' => 'NHÓM 3 — Thanh toán NCC & Agent','color' => '#DEEBF7'],
            4 => ['title' => 'NHÓM 4 — Doanh thu khách hàng',  'color' => '#FFF2CC'],
        ];
    @endphp
    <div class="modal fade" id="columnPermsModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <form id="columnPermsForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-grid-3x3-gap me-1" style="color: var(--azia-primary)"></i>
                            Quyền cột bảng Shipment — <span id="cp_user_name" class="text-primary"></span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info d-flex gap-2 mb-3">
                            <i class="bi bi-info-circle-fill fs-5"></i>
                            <div>
                                Cấu hình cho từng cột user này được phép:
                                <strong class="text-success">Sửa</strong> (mặc định)
                                / <strong class="text-warning">Chỉ xem</strong>
                                / <strong class="text-danger">Ẩn</strong>.
                                Super admin luôn có toàn quyền (bỏ qua cấu hình).
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mb-2 gap-1">
                            <button type="button" class="btn btn-sm btn-light" onclick="setAllPerms('edit')">
                                Tất cả: Sửa
                            </button>
                            <button type="button" class="btn btn-sm btn-light" onclick="setAllPerms('view')">
                                Tất cả: Chỉ xem
                            </button>
                            <button type="button" class="btn btn-sm btn-light" onclick="setAllPerms('hidden')">
                                Tất cả: Ẩn
                            </button>
                        </div>

                        @foreach($groupedCols as $gid => $cols)
                            <div class="card mb-3" style="border-color: {{ $groupNames[$gid]['color'] ?? '#e1e6f1' }}">
                                <div class="card-header d-flex justify-content-between align-items-center"
                                     style="background: {{ $groupNames[$gid]['color'] ?? '#fafbfd' }}; color: #000">
                                    <strong>{{ $groupNames[$gid]['title'] ?? "Nhóm $gid" }}</strong>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-light"
                                                onclick="setGroupPerms({{ $gid }}, 'edit')">Sửa</button>
                                        <button type="button" class="btn btn-sm btn-light"
                                                onclick="setGroupPerms({{ $gid }}, 'view')">Chỉ xem</button>
                                        <button type="button" class="btn btn-sm btn-light"
                                                onclick="setGroupPerms({{ $gid }}, 'hidden')">Ẩn</button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0 align-middle">
                                        <thead>
                                            <tr>
                                                <th>Cột</th>
                                                <th class="text-center" style="width:110px"><i class="bi bi-pencil-fill text-success"></i> Sửa</th>
                                                <th class="text-center" style="width:110px"><i class="bi bi-eye-fill text-warning"></i> Chỉ xem</th>
                                                <th class="text-center" style="width:110px"><i class="bi bi-eye-slash-fill text-danger"></i> Ẩn</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($cols as $col)
                                            <tr data-group="{{ $gid }}" data-key="{{ $col['key'] }}">
                                                <td>
                                                    <strong>{{ $col['title'] }}</strong>
                                                    <code class="text-muted small ms-1">{{ $col['key'] }}</code>
                                                </td>
                                                @foreach(['edit','view','hidden'] as $p)
                                                    <td class="text-center">
                                                        <input type="radio" class="form-check-input cp-radio"
                                                               data-key="{{ $col['key'] }}"
                                                               name="permissions[{{ $col['key'] }}]"
                                                               value="{{ $p }}"
                                                               {{ $p === 'edit' ? 'checked' : '' }}>
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Lưu quyền cột
                        </button>
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

    // ===== Column Permissions Modal =====
    function openColumnPermsModal(user) {
        document.getElementById('cp_user_name').textContent = user.name;
        document.getElementById('columnPermsForm').action  = `${URL_UPDATE_BASE}/${user.id}/column-permissions`;

        const perms = user.permissions || {};
        // Default tất cả về 'edit', sau đó override theo perms
        document.querySelectorAll('.cp-radio[value="edit"]').forEach(r => r.checked = true);
        Object.entries(perms).forEach(([key, val]) => {
            const r = document.querySelector(`.cp-radio[data-key="${key}"][value="${val}"]`);
            if (r) r.checked = true;
        });
    }
    function setAllPerms(value) {
        document.querySelectorAll(`.cp-radio[value="${value}"]`).forEach(r => r.checked = true);
    }
    function setGroupPerms(gid, value) {
        document.querySelectorAll(`tr[data-group="${gid}"] .cp-radio[value="${value}"]`)
                .forEach(r => r.checked = true);
    }

    function openUserModal(user) {
        const form = document.getElementById('userForm');
        document.querySelectorAll('.role-check').forEach(c => c.checked = false);

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
        } else {
            document.getElementById('u_title').textContent = 'Thêm thành viên';
            document.getElementById('u_method').value = 'POST';
            form.action = URL_STORE;
            document.getElementById('u_name').value = '';
            document.getElementById('u_email').value = '';
            document.getElementById('u_password').value = '';
            document.getElementById('u_pw_hint').textContent = '(bắt buộc)';
        }
    }
</script>
@endpush
