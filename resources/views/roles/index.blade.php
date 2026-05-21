@extends('layouts.app')

@section('title', 'Danh sách phân quyền')

@push('styles')
<style>
    .perm-group {
        background: #fafbfd;
        border: 1px solid var(--azia-border);
        border-radius: 8px;
        padding: 12px 14px;
        margin-bottom: 10px;
    }
    .perm-group-title {
        font-weight: 700;
        color: var(--azia-primary);
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: .8px;
        margin-bottom: 8px;
    }
    .role-card { transition: transform .15s; }
    .role-card:hover { transform: translateY(-2px); }
    .role-card .perm-count {
        background: var(--azia-primary-soft);
        color: var(--azia-primary);
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
    }
</style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <h1>Danh sách phân quyền</h1>
            <nav class="breadcrumb mt-1">
                <a href="{{ route('dashboard') }}">Trang chủ</a>
                <span class="mx-2">/</span>
                <span>Quản trị</span>
                <span class="mx-2">/</span>
                <span>Vai trò &amp; Quyền</span>
            </nav>
        </div>
        <button class="btn btn-primary" onclick="openRoleModal(null)"
                data-bs-toggle="modal" data-bs-target="#roleModal">
            <i class="bi bi-shield-plus me-1"></i> Thêm vai trò
        </button>
    </div>

    <div class="row g-3">
        @foreach($roles as $role)
            @php $rolePerms = $role->permissions->pluck('name')->all(); @endphp
            <div class="col-lg-6">
                <div class="card role-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-shield-check" style="color: var(--azia-primary)"></i>
                            <strong>{{ $role->name }}</strong>
                            @if($role->name === 'super_admin')
                                <span class="badge badge-soft-warning">System</span>
                            @endif
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <span class="perm-count">{{ count($rolePerms) }} quyền</span>
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#roleModal"
                                    onclick='openRoleModal(@json(["id" => $role->id, "name" => $role->name, "permissions" => $rolePerms]))'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            @if($role->name !== 'super_admin')
                                <form action="{{ route('roles.destroy', $role) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Xoá vai trò {{ $role->name }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        @if(count($rolePerms) === 0)
                            <div class="text-muted small">Chưa gán quyền nào.</div>
                        @else
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($rolePerms as $perm)
                                    <span class="badge badge-soft-primary">{{ $perm }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Bảng tổng (matrix) --}}
    <div class="card mt-3">
        <div class="card-header">
            <div>Ma trận quyền</div>
            <div class="small text-muted fw-normal">So sánh quyền giữa các vai trò</div>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Quyền</th>
                        @foreach($roles as $role)
                            <th class="text-center">{{ $role->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @foreach($grouped as $group => $perms)
                    <tr class="table-light">
                        <td colspan="{{ $roles->count() + 1 }}">
                            <strong class="text-uppercase small" style="color: var(--azia-primary)">{{ $group }}</strong>
                        </td>
                    </tr>
                    @foreach($perms as $perm)
                        <tr>
                            <td>{{ $perm->name }}</td>
                            @foreach($roles as $role)
                                <td class="text-center">
                                    @if($role->permissions->contains('name', $perm->name))
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                    @else
                                        <i class="bi bi-x-circle text-muted opacity-50"></i>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal --}}
    <div class="modal fade" id="roleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="roleForm" method="POST">
                    @csrf
                    <input type="hidden" name="_method" value="POST" id="r_method">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-shield-lock me-1"></i> <span id="r_title">Thêm vai trò</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Tên vai trò <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="r_name" class="form-control"
                                   placeholder="vd: manager, viewer..." required>
                        </div>

                        <div class="mb-2 d-flex justify-content-between align-items-center">
                            <label class="form-label mb-0">Quyền hạn</label>
                            <div>
                                <button type="button" class="btn btn-sm btn-link p-0 me-2" onclick="toggleAllPerms(true)">Chọn tất cả</button>
                                <button type="button" class="btn btn-sm btn-link p-0 text-danger" onclick="toggleAllPerms(false)">Bỏ chọn</button>
                            </div>
                        </div>

                        @foreach($grouped as $group => $perms)
                            <div class="perm-group">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="perm-group-title">
                                        <i class="bi bi-folder2"></i> {{ $group }}
                                    </div>
                                    <button type="button" class="btn btn-sm btn-link p-0" onclick="toggleGroup('{{ $group }}')">
                                        Toggle nhóm
                                    </button>
                                </div>
                                <div class="row g-2">
                                    @foreach($perms as $perm)
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input perm-check group-{{ $group }}"
                                                       type="checkbox" name="permissions[]"
                                                       value="{{ $perm->name }}" id="perm_{{ $perm->id }}">
                                                <label class="form-check-label" for="perm_{{ $perm->id }}">
                                                    {{ $perm->name }}
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
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
    const ROLE_STORE = @json(route('roles.store'));
    const ROLE_UPDATE_BASE = @json(url('roles'));

    function openRoleModal(role) {
        const form = document.getElementById('roleForm');
        document.querySelectorAll('.perm-check').forEach(c => c.checked = false);

        if (role) {
            document.getElementById('r_title').textContent = 'Sửa vai trò: ' + role.name;
            document.getElementById('r_method').value = 'PUT';
            form.action = `${ROLE_UPDATE_BASE}/${role.id}`;
            document.getElementById('r_name').value = role.name;
            (role.permissions || []).forEach(name => {
                const el = document.querySelector(`.perm-check[value="${name}"]`);
                if (el) el.checked = true;
            });
        } else {
            document.getElementById('r_title').textContent = 'Thêm vai trò';
            document.getElementById('r_method').value = 'POST';
            form.action = ROLE_STORE;
            document.getElementById('r_name').value = '';
        }
    }

    function toggleAllPerms(checked) {
        document.querySelectorAll('.perm-check').forEach(c => c.checked = checked);
    }
    function toggleGroup(group) {
        const items = document.querySelectorAll('.group-' + group);
        const anyUnchecked = Array.from(items).some(i => !i.checked);
        items.forEach(i => i.checked = anyUnchecked);
    }
</script>
@endpush
