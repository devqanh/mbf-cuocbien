@extends('layouts.app')

@section('title', 'Vai trò & phân quyền')

@php
    // Helpers ngắn để view dễ đọc
    $permLabel = fn ($name) => $labels[$name]['label'] ?? $name;
    $permDesc  = fn ($name) => $labels[$name]['desc']  ?? '';

    $moduleLabel = fn ($key) => $modules[$key]['label']       ?? ucfirst($key);
    $moduleIcon  = fn ($key) => $modules[$key]['icon']        ?? 'folder2';
    $moduleColor = fn ($key) => $modules[$key]['color']       ?? '#7987a1';
    $moduleDesc  = fn ($key) => $modules[$key]['description'] ?? '';

    // Ưu tiên: DB display_name → config label → tự sinh từ slug
    $roleLabel = function ($role) use ($roleMeta) {
        if (! empty($role->display_name)) return $role->display_name;
        return $roleMeta[$role->name]['label'] ?? ucwords(str_replace('_', ' ', $role->name));
    };
    $roleDesc   = fn ($name) => $roleMeta[$name]['description'] ?? 'Vai trò tuỳ chỉnh.';
    $roleIcon   = fn ($name) => $roleMeta[$name]['icon']        ?? 'shield';
    $roleColor  = fn ($name) => $roleMeta[$name]['color']       ?? 'secondary';
    $roleSystem = fn ($name) => $roleMeta[$name]['system']      ?? false;
@endphp

@push('styles')
<style>
    .hero-card {
        background: linear-gradient(135deg, #0153a9 0%, #013f80 100%);
        color: #fff;
        border: none;
        border-radius: 14px;
        padding: 22px 26px;
        margin-bottom: 22px;
        position: relative;
        overflow: hidden;
    }
    .hero-card::after {
        content: '';
        position: absolute;
        width: 280px; height: 280px;
        border-radius: 50%;
        background: rgba(255,255,255,.06);
        top: -100px; right: -80px;
    }
    .hero-card h2 { font-size: 22px; font-weight: 700; margin: 0 0 6px; position: relative; z-index: 1; }
    .hero-card p  { margin: 0; opacity: .9; position: relative; z-index: 1; max-width: 720px; line-height: 1.55; }
    .hero-card .hero-icon {
        width: 56px; height: 56px; border-radius: 14px;
        background: rgba(255,255,255,.15);
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 28px; margin-bottom: 14px;
        position: relative; z-index: 1;
    }

    .role-card {
        border-radius: 14px;
        transition: all .2s;
        border: 1px solid var(--azia-border);
        overflow: hidden;
    }
    .role-card:hover { box-shadow: 0 8px 24px rgba(28, 39, 60, .08); transform: translateY(-3px); }
    .role-card .role-head {
        padding: 18px 20px;
        display: flex; align-items: flex-start; gap: 14px;
        border-bottom: 1px solid var(--azia-border);
        background: #fafbfd;
    }
    .role-icon-box {
        width: 48px; height: 48px; border-radius: 12px;
        display: inline-flex; align-items: center; justify-content: center;
        flex-shrink: 0; font-size: 22px;
    }
    .role-card .role-title { font-size: 16px; font-weight: 700; margin: 0; color: var(--azia-text); }
    .role-card .role-slug  { font-size: 11px; color: var(--azia-muted); font-family: ui-monospace, monospace; }
    .role-card .role-desc  { font-size: 13px; color: var(--azia-muted); margin: 4px 0 0; line-height: 1.5; }

    .module-stat {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 14px;
        border-bottom: 1px solid #f0f3fa;
    }
    .module-stat:last-child { border-bottom: none; }
    .module-icon-sm {
        width: 32px; height: 32px; border-radius: 8px;
        display: inline-flex; align-items: center; justify-content: center;
        flex-shrink: 0; font-size: 14px;
    }
    .module-stat .mod-name { font-size: 13px; font-weight: 600; flex: 1; color: var(--azia-text); }
    .module-stat .mod-count { font-size: 12px; color: var(--azia-muted); }
    .module-bar {
        height: 4px; background: #eef0f5; border-radius: 999px; overflow: hidden;
        margin-top: 4px;
    }
    .module-bar > span { display: block; height: 100%; border-radius: 999px; transition: width .3s; }

    .role-actions {
        padding: 10px 14px;
        background: #fafbfd;
        border-top: 1px solid var(--azia-border);
        display: flex; gap: 8px;
    }

    /* Matrix */
    .matrix-table th, .matrix-table td { vertical-align: middle; }
    .matrix-table .module-row td {
        background: #f4f6fb !important;
        font-weight: 700;
        padding: 10px 14px;
    }
    .matrix-table .perm-row td { padding: 10px 14px; }
    .matrix-table .perm-cell {
        display: inline-flex; align-items: center; justify-content: center;
        width: 28px; height: 28px; border-radius: 8px;
    }
    .matrix-table .perm-yes { background: rgba(36,211,159,.15); color: var(--azia-success); }
    .matrix-table .perm-no  { background: #f4f6fb; color: #c5cbd6; }
    .perm-name-cell .lbl  { font-weight: 600; color: var(--azia-text); }
    .perm-name-cell .slug { font-size: 11px; color: var(--azia-muted); font-family: ui-monospace, monospace; }

    /* Modal scroll fix — set explicit max-height cho modal-body */
    #roleModal .modal-body {
        max-height: calc(100vh - 220px) !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
    }
    #roleModal.modal { padding-right: 0 !important; }

    /* Modal */
    .perm-module {
        border: 1px solid var(--azia-border);
        border-radius: 12px;
        margin-bottom: 12px;
        overflow: hidden;
    }
    .perm-module-head {
        padding: 12px 16px;
        background: #fafbfd;
        display: flex; align-items: center; gap: 12px;
        border-bottom: 1px solid var(--azia-border);
    }
    .perm-module-head .mh-title { font-weight: 700; color: var(--azia-text); }
    .perm-module-head .mh-desc  { font-size: 12px; color: var(--azia-muted); }
    .perm-module-body { padding: 10px 16px; }
    .perm-row-item {
        display: flex; align-items: flex-start; gap: 10px;
        padding: 8px 0;
        border-bottom: 1px dashed #f0f3fa;
    }
    .perm-row-item:last-child { border-bottom: none; }
    .perm-row-item .perm-label-block { flex: 1; }
    .perm-row-item .perm-lbl  { font-weight: 600; font-size: 14px; color: var(--azia-text); }
    .perm-row-item .perm-slug { font-size: 11px; color: var(--azia-muted); font-family: ui-monospace, monospace; }
    .perm-row-item .perm-desc { font-size: 12px; color: var(--azia-muted); margin-top: 2px; }
    .perm-row-item .form-switch input { margin-top: 4px; transform: scale(1.15); }
</style>
@endpush

@section('content')
    {{-- HERO --}}
    <div class="hero-card">
        <div class="hero-icon"><i class="bi bi-shield-lock-fill"></i></div>
        <h2>Vai trò &amp; phân quyền</h2>
        <p>
            <strong>Vai trò</strong> là một nhóm quyền hạn được đặt tên (vd: <em>Kế toán</em>, <em>Kho</em>).
            Mỗi <strong>thành viên</strong> được gán một hoặc nhiều vai trò để xác định họ được phép làm những gì trong hệ thống.
            Chỉnh quyền cho cả nhóm chỉ cần sửa vai trò — không cần đụng tới từng người dùng.
        </p>
    </div>

    <div class="page-header">
        <div>
            <nav class="breadcrumb">
                <a href="{{ route('trucking.index') }}">Trang chủ</a>
                <span class="mx-2">/</span>
                <span>Quản trị</span>
                <span class="mx-2">/</span>
                <span>Vai trò &amp; phân quyền</span>
            </nav>
            <h1 class="mt-1">Có <strong>{{ $roles->count() }}</strong> vai trò · <strong>{{ $permissions->count() }}</strong> quyền · <strong>{{ count($modules) }}</strong> nhóm</h1>
        </div>
        <button class="btn btn-primary" onclick="openRoleModal(null)" data-bs-toggle="modal" data-bs-target="#roleModal">
            <i class="bi bi-shield-plus me-1"></i> Tạo vai trò mới
        </button>
    </div>

    {{-- ROLE CARDS --}}
    <div class="row g-3 mb-4">
        @foreach($roles as $role)
            @php
                $rolePermNames = $role->permissions->pluck('name')->all();
                $color = $roleColor($role->name);
                $hex   = match($color) {
                    'danger'    => '#ff5b5b',
                    'primary'   => '#0153a9',
                    'info'      => '#00b8d4',
                    'success'   => '#24d39f',
                    'warning'   => '#ffb822',
                    default     => '#7987a1',
                };
            @endphp
            <div class="col-lg-6">
                <div class="card role-card h-100">
                    <div class="role-head">
                        <div class="role-icon-box" style="background: {{ $hex }}22; color: {{ $hex }};">
                            <i class="bi bi-{{ $roleIcon($role->name) }}"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h5 class="role-title">{{ $roleLabel($role) }}</h5>
                                @if($roleSystem($role->name))
                                    <span class="badge text-bg-danger" style="font-size:10px">HỆ THỐNG</span>
                                @endif
                                <span class="badge badge-soft-primary" title="Tổng số quyền">
                                    {{ count($rolePermNames) }} / {{ $permissions->count() }} quyền
                                </span>
                            </div>
                            <div class="role-slug">{{ $role->name }}</div>
                            <p class="role-desc">{{ $roleDesc($role->name) }}</p>
                        </div>
                    </div>

                    <div>
                        @foreach($grouped as $modKey => $perms)
                            @php
                                $total    = $perms->count();
                                $granted  = $perms->pluck('name')->intersect($rolePermNames)->count();
                                $percent  = $total > 0 ? round($granted / $total * 100) : 0;
                                $modHex   = $moduleColor($modKey);
                            @endphp
                            <div class="module-stat">
                                <div class="module-icon-sm" style="background: {{ $modHex }}1f; color: {{ $modHex }};">
                                    <i class="bi bi-{{ $moduleIcon($modKey) }}"></i>
                                </div>
                                <div style="flex:1; min-width:0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="mod-name">{{ $moduleLabel($modKey) }}</span>
                                        <span class="mod-count">
                                            <strong>{{ $granted }}</strong>/{{ $total }}
                                            @if($granted === $total)
                                                <i class="bi bi-check-circle-fill text-success ms-1"></i>
                                            @elseif($granted === 0)
                                                <i class="bi bi-dash-circle text-muted ms-1"></i>
                                            @endif
                                        </span>
                                    </div>
                                    <div class="module-bar">
                                        <span style="width: {{ $percent }}%; background: {{ $modHex }};"></span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @php
                        $roleJson = ['id' => $role->id, 'name' => $role->name, 'display_name' => $roleLabel($role), 'permissions' => $rolePermNames];
                    @endphp
                    <div class="role-actions">
                        <button class="btn btn-sm btn-outline-primary flex-grow-1"
                                data-bs-toggle="modal" data-bs-target="#roleModal"
                                onclick='openRoleModal(@json($roleJson))'>
                            <i class="bi bi-pencil"></i> Sửa quyền
                        </button>
                        @if(! $roleSystem($role->name))
                            <form action="{{ route('roles.destroy', $role) }}" method="POST"
                                  onsubmit="return confirmDelete(this, {title: 'Xoá vai trò?', text: 'Bạn sắp xoá vai trò <b>{{ $roleLabel($role) }}</b>. Hành động này không thể hoàn tác.'})">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Xoá vai trò">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        @else
                            <button class="btn btn-sm btn-outline-secondary" disabled title="Vai trò hệ thống — không thể xoá">
                                <i class="bi bi-lock"></i>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- MATRIX --}}
    <div class="card">
        <div class="card-header">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-grid-3x3-gap" style="color: var(--azia-primary)"></i>
                <span>Bảng so sánh chi tiết</span>
            </div>
            <div class="small text-muted fw-normal">
                Dấu <i class="bi bi-check-circle-fill text-success"></i> nghĩa là vai trò có quyền đó. Dấu <i class="bi bi-dash-circle text-muted"></i> là không có.
            </div>
        </div>
        <div class="table-responsive">
            <table class="table matrix-table mb-0">
                <thead>
                    <tr>
                        <th style="min-width:280px">Quyền hạn</th>
                        @foreach($roles as $r)
                            <th class="text-center" style="min-width:110px">
                                <div class="d-flex flex-column align-items-center gap-1">
                                    <i class="bi bi-{{ $roleIcon($r->name) }}"></i>
                                    <span style="font-size:12px">{{ $roleLabel($r) }}</span>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @foreach($grouped as $modKey => $perms)
                    <tr class="module-row">
                        <td colspan="{{ $roles->count() + 1 }}">
                            <span style="color: {{ $moduleColor($modKey) }}">
                                <i class="bi bi-{{ $moduleIcon($modKey) }} me-2"></i>{{ $moduleLabel($modKey) }}
                            </span>
                            <span class="small text-muted fw-normal ms-2">— {{ $moduleDesc($modKey) }}</span>
                        </td>
                    </tr>
                    @foreach($perms as $perm)
                        <tr class="perm-row">
                            <td class="perm-name-cell">
                                <div class="lbl">{{ $permLabel($perm->name) }}</div>
                                @if($permDesc($perm->name))
                                    <div class="text-muted small">{{ $permDesc($perm->name) }}</div>
                                @endif
                                <div class="slug">{{ $perm->name }}</div>
                            </td>
                            @foreach($roles as $r)
                                <td class="text-center">
                                    @if($r->permissions->contains('name', $perm->name))
                                        <span class="perm-cell perm-yes" title="Có quyền"><i class="bi bi-check-lg"></i></span>
                                    @else
                                        <span class="perm-cell perm-no" title="Không có quyền"><i class="bi bi-dash"></i></span>
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

    {{-- MODAL --}}
    <div class="modal fade" id="roleModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="roleForm" method="POST">
                    @csrf
                    <input type="hidden" name="_method" value="POST" id="r_method">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-shield-lock me-1" style="color: var(--azia-primary)"></i>
                            <span id="r_title">Tạo vai trò mới</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tên hiển thị <span class="text-danger">*</span></label>
                            <input type="text" name="display_name" id="r_display_name" class="form-control form-control-lg"
                                   placeholder="vd: Kế toán, Quản lý kho, Trưởng phòng" required maxlength="64"
                                   oninput="autoSlug()">
                            <div class="form-text">Tên này sẽ hiển thị trên giao diện cho người dùng.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label d-flex justify-content-between align-items-center">
                                <span class="text-muted">
                                    <i class="bi bi-code"></i> Mã vai trò (tự sinh)
                                </span>
                                <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" onclick="toggleSlugEdit()">
                                    <span id="slug_toggle_label"><i class="bi bi-pencil"></i> Sửa mã</span>
                                </button>
                            </label>
                            <input type="text" name="name" id="r_name" class="form-control bg-light"
                                   placeholder="tự sinh từ tên hiển thị" pattern="[a-z0-9_]+" readonly>
                            <div class="form-text">
                                Mã kỹ thuật dùng trong code, vd <code>hasRole('ke_toan')</code>. Bình thường không cần sửa.
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="form-label fw-bold mb-0">
                                <i class="bi bi-check2-square me-1"></i> Quyền hạn được cấp
                            </label>
                            <div>
                                <button type="button" class="btn btn-sm btn-link p-0 me-3" onclick="toggleAllPerms(true)">
                                    <i class="bi bi-check-all"></i> Chọn tất cả
                                </button>
                                <button type="button" class="btn btn-sm btn-link p-0 text-danger" onclick="toggleAllPerms(false)">
                                    <i class="bi bi-x-lg"></i> Bỏ chọn hết
                                </button>
                            </div>
                        </div>

                        @foreach($grouped as $modKey => $perms)
                            @php $modHex = $moduleColor($modKey); @endphp
                            <div class="perm-module">
                                <div class="perm-module-head">
                                    <div class="module-icon-sm" style="background: {{ $modHex }}1f; color: {{ $modHex }};">
                                        <i class="bi bi-{{ $moduleIcon($modKey) }}"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="mh-title">{{ $moduleLabel($modKey) }}</div>
                                        <div class="mh-desc">{{ $moduleDesc($modKey) }}</div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light" onclick="toggleGroup('{{ $modKey }}')">
                                        <i class="bi bi-toggles"></i> Cả nhóm
                                    </button>
                                </div>
                                <div class="perm-module-body">
                                    @foreach($perms as $perm)
                                        <div class="perm-row-item">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input perm-check group-{{ $modKey }}"
                                                       type="checkbox" role="switch"
                                                       name="permissions[]"
                                                       value="{{ $perm->name }}"
                                                       id="perm_{{ $perm->id }}">
                                            </div>
                                            <label for="perm_{{ $perm->id }}" class="perm-label-block mb-0" style="cursor:pointer">
                                                <div class="perm-lbl">{{ $permLabel($perm->name) }}</div>
                                                @if($permDesc($perm->name))
                                                    <div class="perm-desc">{{ $permDesc($perm->name) }}</div>
                                                @endif
                                                <div class="perm-slug">{{ $perm->name }}</div>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Lưu vai trò
                        </button>
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

    // State: khi sửa role có sẵn → khoá auto-slug để không ghi đè mã đang dùng
    let slugAutoMode = true;

    function openRoleModal(role) {
        const form = document.getElementById('roleForm');
        document.querySelectorAll('.perm-check').forEach(c => c.checked = false);

        const slugInput = document.getElementById('r_name');
        slugInput.setAttribute('readonly', 'readonly');
        slugInput.classList.add('bg-light');
        document.getElementById('slug_toggle_label').innerHTML = '<i class="bi bi-pencil"></i> Sửa mã';

        if (role) {
            // Sửa: dùng mã hiện tại, KHÔNG auto-slug khi gõ tên
            document.getElementById('r_title').textContent = 'Sửa vai trò: ' + (role.display_name || role.name);
            document.getElementById('r_method').value = 'PUT';
            form.action = `${ROLE_UPDATE_BASE}/${role.id}`;
            document.getElementById('r_display_name').value = role.display_name || '';
            slugInput.value = role.name;
            slugAutoMode = false;
            (role.permissions || []).forEach(name => {
                const el = document.querySelector(`.perm-check[value="${name}"]`);
                if (el) el.checked = true;
            });
        } else {
            // Tạo mới: auto-slug khi gõ tên hiển thị
            document.getElementById('r_title').textContent = 'Tạo vai trò mới';
            document.getElementById('r_method').value = 'POST';
            form.action = ROLE_STORE;
            document.getElementById('r_display_name').value = '';
            slugInput.value = '';
            slugAutoMode = true;
        }
    }

    // Sinh slug snake_case từ chuỗi (xử lý tiếng Việt có dấu)
    function makeSlug(s) {
        if (!s) return '';
        return s.toString().toLowerCase()
            .normalize('NFD').replace(/[̀-ͯ]/g, '')   // bỏ dấu
            .replace(/đ/g, 'd').replace(/Đ/g, 'd')              // đ → d
            .replace(/[^a-z0-9]+/g, '_')                        // ký tự khác → _
            .replace(/^_+|_+$/g, '');                           // trim _
    }

    function autoSlug() {
        if (!slugAutoMode) return;
        const dn = document.getElementById('r_display_name').value;
        document.getElementById('r_name').value = makeSlug(dn);
    }

    function toggleSlugEdit() {
        const slug = document.getElementById('r_name');
        const label = document.getElementById('slug_toggle_label');
        if (slug.hasAttribute('readonly')) {
            slug.removeAttribute('readonly');
            slug.classList.remove('bg-light');
            slug.focus();
            slugAutoMode = false;
            label.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Tự sinh lại';
        } else {
            slug.setAttribute('readonly', 'readonly');
            slug.classList.add('bg-light');
            slugAutoMode = true;
            autoSlug();
            label.innerHTML = '<i class="bi bi-pencil"></i> Sửa mã';
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
