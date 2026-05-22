@extends('layouts.app')

@section('title', 'Danh sách thành viên')

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
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal"
                onclick="openUserModal(null)">
            <i class="bi bi-person-plus me-1"></i> Thêm thành viên
        </button>
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
                        @php $uJson = $u->only(["id","name","email"]) + ["roles" => $u->roles->pluck("name")]; @endphp
                        <td class="text-end">
                            <div class="action-group">
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
@endsection

@push('scripts')
<script>
    const URL_STORE = @json(route('users.store'));
    const URL_UPDATE_BASE = @json(url('users'));

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
