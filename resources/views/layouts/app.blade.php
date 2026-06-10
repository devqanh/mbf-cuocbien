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
    {{-- Select2 4.1 --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    {{-- Flatpickr (date/time picker hiện đại) --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">

    <link rel="stylesheet" href="@assetVer('css/app.css')">
    @stack('styles')
</head>
<body>

<header class="app-header">
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid p-0">
            <a class="navbar-brand brand-logo" href="{{ route('trucking.index') }}">
                MBF
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse app-nav" id="mainNav">
                <ul class="navbar-nav me-auto">
                    {{-- Trucking (HẠ HPH + HẠ ICD) --}}
                    @can('shipments.view')
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('trucking.*') ? 'active' : '' }}"
                           href="{{ route('trucking.index') }}">
                            <i class="bi bi-truck-front"></i> Trucking
                        </a>
                    </li>
                    @endcan

                    {{-- Follow Up Shipment --}}
                    @can('shipments.view')
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('shipments.*') ? 'active' : '' }}"
                           href="{{ route('shipments.index') }}">
                            <i class="bi bi-truck"></i> Follow Up Shipment
                        </a>
                    </li>
                    @endcan

                    {{-- Ghi chú & công việc (đặt trước Báo cáo để dễ thấy) --}}
                    @can('tasks.view')
                    @php
                        // Số task chưa done được giao cho user hiện tại — dùng index task_user.(user_id, role) + tasks.(status, due_at)
                        $myPendingTasksCount = \App\Models\Task::query()
                            ->assignedTo(auth()->id())
                            ->open()
                            ->count();
                    @endphp
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('tasks.*') ? 'active' : '' }}"
                           href="{{ route('tasks.index') }}">
                            <i class="bi bi-check2-square"></i> Công việc
                            <span id="navTaskBadge" class="menu-badge {{ $myPendingTasksCount > 0 ? '' : 'd-none' }}">
                                {{ $myPendingTasksCount > 99 ? '99+' : $myPendingTasksCount }}
                            </span>
                        </a>
                    </li>
                    @endcan

                    {{-- Báo cáo --}}
                    @can('reports.view')
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->routeIs('reports.*') ? 'active' : '' }}"
                           href="#" data-bs-toggle="dropdown" role="button">
                            <i class="bi bi-clipboard-data"></i> Báo cáo
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item {{ request()->routeIs('reports.payable.index','reports.payable.show','reports.payable.store','reports.payable.destroy') ? 'active' : '' }}"
                                   href="{{ route('reports.payable.index') }}">
                                <i class="bi bi-cash-stack"></i> Báo cáo phải trả</a></li>
                            <li><a class="dropdown-item {{ request()->routeIs('reports.payable.initial.*') ? 'active' : '' }}"
                                   href="{{ route('reports.payable.initial.index') }}">
                                <i class="bi bi-pencil-square"></i> Cấu hình đầu kỳ NCC</a></li>
                        </ul>
                    </li>
                    @endcan

                    {{-- Quản trị — chỉ hiện nếu có ít nhất 1 quyền users.view hoặc roles.view --}}
                    @if(auth()->user()->hasAnyPermission(['users.view', 'roles.view']))
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->routeIs('users.*','roles.*') ? 'active' : '' }}"
                           href="#" data-bs-toggle="dropdown" role="button">
                            <i class="bi bi-shield-lock"></i> Quản trị
                        </a>
                        <ul class="dropdown-menu">
                            @can('users.view')
                            <li><a class="dropdown-item {{ request()->routeIs('users.*') ? 'active' : '' }}"
                                   href="{{ route('users.index') }}">
                                <i class="bi bi-people"></i> Danh sách thành viên</a></li>
                            @endcan
                            @can('roles.view')
                            <li><a class="dropdown-item {{ request()->routeIs('roles.*') ? 'active' : '' }}"
                                   href="{{ route('roles.index') }}">
                                <i class="bi bi-shield-check"></i> Danh sách phân quyền</a></li>
                            @endcan
                        </ul>
                    </li>
                    @endif
                </ul>

                <div class="header-actions d-flex align-items-center gap-2">
                    @can('tasks.create')
                    <button class="icon-btn" type="button" title="Tạo task nhanh (phím N)"
                            data-bs-toggle="modal" data-bs-target="#quickTaskModal">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                    @endcan

                    {{-- Bell notification --}}
                    <div class="dropdown bell-wrap">
                        <button id="bellBtn" type="button" class="icon-btn position-relative"
                                data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                                title="Thông báo">
                            <i class="bi bi-bell"></i>
                            <span id="bellBadge" class="bell-badge d-none">0</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end bell-menu">
                            <div class="bell-head">
                                <strong>Thông báo</strong>
                                <button type="button" class="btn btn-link btn-sm p-0" id="bellMarkAll">
                                    Đánh dấu đã đọc
                                </button>
                            </div>
                            <div id="bellList" class="bell-list">
                                <div class="bell-empty">
                                    <i class="bi bi-bell-slash"></i>
                                    <div class="small text-muted mt-2">Chưa có thông báo</div>
                                </div>
                            </div>
                            <a href="{{ route('notifications.index') }}" class="bell-foot">
                                Xem tất cả <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>

                    <button class="icon-btn" type="button" title="Toàn màn hình" onclick="toggleFullscreen()">
                        <i class="bi bi-arrows-fullscreen"></i>
                    </button>

                    <div class="dropdown">
                        <a href="#" class="user-chip text-decoration-none text-dark" data-bs-toggle="dropdown">
                            <span class="avatar">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</span>
                            <div class="d-none d-md-block">
                                <div class="name">{{ auth()->user()->name ?? 'Guest' }}</div>
                                <div class="role">{{ auth()->user()->roleLabel() }}</div>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item {{ request()->routeIs('profile.*') ? 'active' : '' }}"
                                   href="{{ route('profile.show') }}">
                                    <i class="bi bi-person-circle"></i> Thông tin cá nhân
                                </a>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
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

{{-- Quick Task modal (global — phím tắt N hoặc nút + ở header) --}}
@can('tasks.create')
<div class="modal fade" id="quickTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:none; border-radius:14px;">
            <form id="quickTaskForm" method="POST" action="{{ route('tasks.store') }}">
                @csrf
                <div class="modal-header" style="border-bottom: 1px solid var(--azia-border);">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-1" style="color: var(--azia-primary)"></i>
                        Tạo task nhanh
                        <small class="text-muted ms-2" style="font-size:11px;font-weight:500">phím <kbd>N</kbd></small>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tiêu đề <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required autofocus
                               placeholder="vd: Đối chiếu công nợ MSC trước 30/05">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Hạn (tuỳ chọn)</label>
                            <input type="text" name="due_at" class="form-control js-datetimepicker"
                                   placeholder="Chọn ngày & giờ…">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nhắc trước</label>
                            <select name="remind_before" class="form-select">
                                <option value="">Không nhắc</option>
                                <option value="0">Đúng giờ hạn</option>
                                <option value="30">30 phút trước</option>
                                <option value="60" selected>1 giờ trước</option>
                                <option value="240">4 giờ trước</option>
                                <option value="1440">1 ngày trước</option>
                                <option value="2880">2 ngày trước</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Độ ưu tiên</label>
                            <select name="priority" class="form-select">
                                <option value="low">Thấp</option>
                                <option value="normal" selected>Bình thường</option>
                                <option value="high">Cao</option>
                                <option value="urgent">Khẩn cấp</option>
                            </select>
                        </div>
                        @can('tasks.assign_others')
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Giao cho</label>
                            <select name="assignees[]" class="form-select js-select2-users" multiple id="quickAssignees"
                                    data-placeholder="Chọn người được giao…">
                                @foreach(\App\Models\User::orderBy('name')->get(['id','name']) as $u)
                                    <option value="{{ $u->id }}" {{ $u->id === auth()->id() ? 'selected' : '' }}>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endcan
                    </div>
                    <div class="mb-2 mt-3">
                        <label class="form-label fw-semibold">Ghi chú</label>
                        <textarea name="body" class="form-control" rows="3"
                                  placeholder="Mô tả chi tiết, link tham khảo…"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--azia-border);">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Huỷ</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i> Tạo task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/vn.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
<script>
    function toggleFullscreen() {
        if (!document.fullscreenElement) document.documentElement.requestFullscreen();
        else document.exitFullscreen();
    }
    // Auto-hide toast after 4s
    document.querySelectorAll('.toast.show').forEach(t => setTimeout(() => bootstrap.Toast.getOrCreateInstance(t).hide(), 4000));

    // ---- SweetAlert global helpers ----
    const APP_SWAL = {
        customClass: {
            popup: 'app-swal-popup',
            title: 'app-swal-title',
            htmlContainer: 'app-swal-text',
            confirmButton: 'app-swal-btn app-swal-btn-confirm',
            cancelButton: 'app-swal-btn app-swal-btn-cancel',
            actions: 'app-swal-actions',
            icon: 'app-swal-icon',
        },
        buttonsStyling: false,
        reverseButtons: true,
        focusCancel: true,
    };

    /**
     * Dùng cho form xoá: <form onsubmit="return confirmDelete(this, {title, text})">
     */
    window.confirmDelete = function (form, opts = {}) {
        Swal.fire({
            ...APP_SWAL,
            icon: 'warning',
            title: opts.title || 'Xác nhận xoá?',
            html: opts.text || 'Hành động này không thể hoàn tác.',
            showCancelButton: true,
            confirmButtonText: opts.confirmText || '<i class="bi bi-trash me-1"></i> Xoá',
            cancelButtonText: 'Huỷ',
            customClass: { ...APP_SWAL.customClass,
                confirmButton: 'app-swal-btn app-swal-btn-danger' },
        }).then((res) => { if (res.isConfirmed) form.submit(); });
        return false;
    };

    /**
     * Dùng trong JS bất đồng bộ: if (!await confirmAction({...})) return;
     */
    window.confirmAction = async function (opts = {}) {
        const danger = !!opts.danger;
        const res = await Swal.fire({
            ...APP_SWAL,
            icon: opts.icon || (danger ? 'warning' : 'question'),
            title: opts.title || 'Bạn chắc chứ?',
            html: opts.text || '',
            showCancelButton: true,
            confirmButtonText: opts.confirmText || 'Đồng ý',
            cancelButtonText: opts.cancelText || 'Huỷ',
            customClass: { ...APP_SWAL.customClass,
                confirmButton: 'app-swal-btn ' + (danger ? 'app-swal-btn-danger' : 'app-swal-btn-confirm') },
        });
        return res.isConfirmed;
    };

    // ---- Flatpickr (date/time picker) init ----
    if (typeof flatpickr !== 'undefined') {
        // Thiết lập locale VN làm mặc định
        if (flatpickr.l10ns && flatpickr.l10ns.vn) {
            flatpickr.localize(flatpickr.l10ns.vn);
        }

        window.initDateTimePicker = function (el) {
            if (! el || el._flatpickr) return;
            const isDate = el.dataset.type === 'date'; // class js-datepicker → chỉ ngày
            return flatpickr(el, {
                enableTime: ! isDate,
                time_24hr: true,
                dateFormat: isDate ? 'Y-m-d' : 'Y-m-d H:i',
                altInput: true,
                altFormat: isDate ? 'd/m/Y' : 'd/m/Y · H:i',
                minuteIncrement: 5,
                allowInput: false,
                // Nút "Đóng" — fix UX user feedback: không tìm được chỗ đóng
                closeOnSelect: false,
                onReady(_, __, instance) {
                    if (! instance.calendarContainer.querySelector('.fp-done-btn')) {
                        const $bar = document.createElement('div');
                        $bar.className = 'fp-action-bar';
                        $bar.innerHTML = `
                            <button type="button" class="fp-btn fp-clear-btn">
                                <i class="bi bi-x-lg me-1"></i>Xoá
                            </button>
                            <button type="button" class="fp-btn fp-done-btn">
                                <i class="bi bi-check2 me-1"></i>Xong
                            </button>
                        `;
                        instance.calendarContainer.appendChild($bar);
                        $bar.querySelector('.fp-done-btn').addEventListener('click', () => instance.close());
                        $bar.querySelector('.fp-clear-btn').addEventListener('click', () => { instance.clear(); instance.close(); });
                    }
                },
            });
        };

        // Auto-init mọi input có class `.js-datetimepicker` hoặc `.js-datepicker`
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.js-datetimepicker, .js-datepicker').forEach((el) => {
                if (el.classList.contains('js-datepicker')) el.dataset.type = 'date';
                window.initDateTimePicker(el);
            });
        });
    }

    // ---- Select2 init cho mọi field "Giao cho" / multi-user picker ----
    // CAPTURE jQuery reference NGAY BÂY GIỜ — phòng trường hợp các thư viện
    // khác (Luckysheet…) bundle jQuery riêng và đè window.$ sau này.
    (function ($) {
        if (! $ || ! $.fn || ! $.fn.select2) {
            console.warn('jQuery/Select2 chưa load — skip initUserSelect2');
            return;
        }

        window.initUserSelect2 = function (el) {
            const $jq = $(el);
            if (! $jq.length || $jq.data('select2-initialized')) return;
            const $modal = $jq.closest('.modal');
            $jq.select2({
                placeholder: $jq.data('placeholder') || 'Chọn người…',
                width: '100%',
                allowClear: true,
                closeOnSelect: false,
                dropdownParent: $modal.length ? $modal : $('body'),
                language: {
                    noResults: () => 'Không tìm thấy',
                    searching: () => 'Đang tìm…',
                    removeAllItems: () => 'Bỏ chọn tất cả',
                },
            });
            $jq.data('select2-initialized', true);
        };

        $(function () {
            $('.js-select2-users').each(function () { window.initUserSelect2(this); });
        });
    })(window.jQuery);

    // ---- Realtime Echo init (1 lần cho cả app) ----
    @auth
    const REVERB_CFG = {
        key:    @json(config('broadcasting.connections.reverb.key')),
        host:   @json(config('broadcasting.connections.reverb.options.host', request()->getHost())),
        port:   @json((int) config('broadcasting.connections.reverb.options.port', 8080)),
        scheme: @json(config('broadcasting.connections.reverb.options.scheme', 'http')),
    };
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
    const AUTH_USER  = @json(['id' => auth()->id(), 'name' => auth()->user()->name]);

    // Quan trọng: laravel-echo iife script ngay khi load đã SET window.Echo = EchoClass.
    // Phải dùng EchoClass để new instance, rồi overwrite window.Echo bằng instance.
    // Check `.connector` để biết đã là instance hay chưa.
    try {
        const isInstance = window.Echo && window.Echo.connector;
        if (! isInstance && typeof Echo === 'function' && REVERB_CFG.key) {
            const EchoCtor = window.Echo;  // class reference trước khi overwrite
            window.Echo = new EchoCtor({
                broadcaster: 'reverb',
                key:     REVERB_CFG.key,
                wsHost:  REVERB_CFG.host,
                wsPort:  REVERB_CFG.port,
                wssPort: REVERB_CFG.port,
                forceTLS: REVERB_CFG.scheme === 'https',
                enabledTransports: ['ws', 'wss'],
                auth: { headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } },
            });
        }
    } catch (e) { console.warn('Echo init failed:', e); }

    // ---- Bell (notification) ----
    const Bell = {
        $btn:    document.getElementById('bellBtn'),
        $badge:  document.getElementById('bellBadge'),
        $list:   document.getElementById('bellList'),
        $markAll:document.getElementById('bellMarkAll'),
        unread: 0,

        setUnread(n) {
            this.unread = Math.max(0, n);
            if (!this.$badge) return;
            if (this.unread <= 0) {
                this.$badge.classList.add('d-none');
                this.$badge.textContent = '0';
            } else {
                this.$badge.classList.remove('d-none');
                this.$badge.textContent = this.unread > 99 ? '99+' : String(this.unread);
            }
        },

        renderItems(items) {
            if (!this.$list) return;
            if (!items || items.length === 0) {
                this.$list.innerHTML = '<div class="bell-empty"><i class="bi bi-bell-slash"></i>'
                                     + '<div class="small text-muted mt-2">Chưa có thông báo</div></div>';
                return;
            }
            const colorMap = { primary:'#0153a9', info:'#00b8d4', warning:'#ffb822', danger:'#ff5b5b', success:'#24d39f' };
            const html = items.map(n => {
                const d = n.data || {};
                const color = colorMap[d.color] || '#7987a1';
                const icon  = d.icon || 'bell';
                return `<a class="bell-item ${n.read ? 'is-read' : ''}" href="${d.url || '#'}"
                            data-id="${n.id}" onclick="Bell.markRead('${n.id}')">
                    <span class="bell-item-icon" style="background:${color}22;color:${color}">
                        <i class="bi bi-${icon}"></i>
                    </span>
                    <div class="bell-item-body">
                        <div class="bell-item-text">${(d.message || '').replace(/</g,'&lt;')}</div>
                        <div class="bell-item-time">${n.created_human || ''}</div>
                    </div>
                    ${n.read ? '' : '<span class="bell-dot"></span>'}
                </a>`;
            }).join('');
            this.$list.innerHTML = html;
        },

        async fetchFeed() {
            try {
                const res = await fetch(@json(route('notifications.feed')), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) return;
                const json = await res.json();
                this.setUnread(json.unread || 0);
                this.renderItems(json.notifications || []);
            } catch (e) { /* silent */ }
        },

        async markRead(id) {
            this.setUnread(this.unread - 1);
            try {
                await fetch(`/notifications/${id}/read`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
            } catch (e) {}
        },

        async markAll() {
            this.setUnread(0);
            try {
                await fetch(@json(route('notifications.readAll')), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                await this.fetchFeed();
            } catch (e) {}
        },

        // ---- App Toast (slide-in từ phải, có progress bar, click để mở chi tiết) ----
        _getStack() {
            let $c = document.getElementById('app-toast-stack');
            if (!$c) {
                $c = document.createElement('div');
                $c.id = 'app-toast-stack';
                $c.className = 'app-toast-stack';
                document.body.appendChild($c);
            }
            return $c;
        },

        _titleFor(type) {
            // Tiêu đề ngắn theo loại notification — pro hơn "Thông báo mới" chung chung
            switch (type) {
                case 'task.assigned':  return '<i class="bi bi-person-fill-add me-1"></i> Bạn vừa được giao việc';
                case 'task.reminder':  return '<i class="bi bi-alarm-fill me-1"></i> Nhắc hẹn công việc';
                case 'task.mentioned': return '<i class="bi bi-at me-1"></i> Bạn được nhắc đến';
                case 'task.commented': return '<i class="bi bi-chat-square-text-fill me-1"></i> Bình luận mới';
                case 'task.updated':   return '<i class="bi bi-pencil-square me-1"></i> Cập nhật công việc';
                default:               return '<i class="bi bi-bell-fill me-1"></i> Thông báo mới';
            }
        },

        toast(notif) {
            const d = notif || {};
            const palette = {
                primary: { fg: '#0153a9', soft: 'rgba(1,83,169,.12)' },
                info:    { fg: '#00b8d4', soft: 'rgba(0,184,212,.14)' },
                warning: { fg: '#d28b00', soft: 'rgba(255,184,34,.18)' },
                danger:  { fg: '#ff5b5b', soft: 'rgba(255,91,91,.14)' },
                success: { fg: '#1aa37e', soft: 'rgba(36,211,159,.16)' },
            };
            const color = palette[d.color] ? d.color : 'primary';
            const c = palette[color];
            const $stack = this._getStack();

            const $t = document.createElement('div');
            $t.className = `app-toast color-${color}`;
            $t.innerHTML = `
                <button class="app-toast-close" type="button" aria-label="Đóng">
                    <i class="bi bi-x-lg"></i>
                </button>
                <div class="app-toast-head">
                    <span class="app-toast-icon" style="background:${c.soft};color:${c.fg};">
                        <i class="bi bi-${d.icon || 'bell-fill'}"></i>
                    </span>
                    <div class="app-toast-meta">
                        <div class="app-toast-title">${this._titleFor(d.type)}</div>
                        <div class="app-toast-time">vừa xong</div>
                    </div>
                </div>
                <div class="app-toast-body">${(d.message || '').replace(/</g, '&lt;')}</div>
                ${d.url ? `<a class="app-toast-action" href="${d.url}">Xem chi tiết <i class="bi bi-arrow-right"></i></a>` : ''}
                <div class="app-toast-progress"><span></span></div>
            `;

            // Cho phép click toàn bộ toast để navigate
            if (d.url) {
                $t.addEventListener('click', (e) => {
                    if (e.target.closest('.app-toast-close')) return;
                    window.location.href = d.url;
                });
            }

            // Đóng
            const dismiss = () => {
                if ($t.dataset.dismissing) return;
                $t.dataset.dismissing = '1';
                $t.classList.add('is-leaving');
                $t.classList.remove('is-visible');
                setTimeout(() => $t.remove(), 350);
            };
            $t.querySelector('.app-toast-close').addEventListener('click', (e) => {
                e.stopPropagation();
                dismiss();
            });

            $stack.appendChild($t);
            // Force reflow → kích hoạt CSS transition
            requestAnimationFrame(() => requestAnimationFrame(() => $t.classList.add('is-visible')));

            // Tồn tại lâu hơn khi tab không focus (user đang làm chuyện khác → không miss)
            // Mention + reminder = quan trọng → 12s; thường = 6s; nếu tab unfocus tăng gấp đôi.
            let lifeMs = 6000;
            if (d.type === 'task.mentioned' || d.type === 'task.reminder') lifeMs = 12000;
            if (! document.hasFocus()) lifeMs *= 2;

            const progress = $t.querySelector('.app-toast-progress span');
            if (progress) progress.style.animationDuration = (lifeMs / 1000) + 's';

            setTimeout(dismiss, lifeMs);
        },
    };
    window.Bell = Bell;

    if (Bell.$btn) Bell.$btn.addEventListener('click', () => Bell.fetchFeed());
    if (Bell.$markAll) Bell.$markAll.addEventListener('click', () => Bell.markAll());

    // Initial load
    Bell.fetchFeed();

    // ---- Helper: tăng badge "Công việc" trên nav khi có task mới được giao ----
    function bumpNavTaskBadge(delta = 1) {
        const $badge = document.getElementById('navTaskBadge');
        if (! $badge) return;
        const current = parseInt(($badge.textContent || '0').replace('+',''), 10) || 0;
        const next = Math.max(0, current + delta);
        if (next === 0) {
            $badge.classList.add('d-none');
            $badge.textContent = '0';
        } else {
            $badge.classList.remove('d-none');
            $badge.textContent = next > 99 ? '99+' : String(next);
        }
    }
    window.bumpNavTaskBadge = bumpNavTaskBadge;

    // ---- Tab title flash: hiện (N) ở title trình duyệt khi có noti chưa đọc ----
    const TabTitle = {
        original: document.title,
        timer: null,
        unread: 0,
        update(unread) {
            this.unread = unread;
            if (unread > 0) {
                document.title = `(${unread > 99 ? '99+' : unread}) ${this.original}`;
                if (! document.hasFocus()) this.startFlash();
            } else {
                this.stopFlash();
                document.title = this.original;
            }
        },
        startFlash() {
            if (this.timer) return;
            let alt = false;
            this.timer = setInterval(() => {
                alt = !alt;
                document.title = alt
                    ? `🔔 ${this.unread > 99 ? '99+' : this.unread} thông báo mới`
                    : `(${this.unread > 99 ? '99+' : this.unread}) ${this.original}`;
            }, 1500);
        },
        stopFlash() {
            if (this.timer) { clearInterval(this.timer); this.timer = null; }
        },
    };
    window.addEventListener('focus', () => { TabTitle.stopFlash(); TabTitle.update(TabTitle.unread); });

    // Wrap Bell.setUnread để đồng bộ với tab title
    const _origSetUnread = Bell.setUnread.bind(Bell);
    Bell.setUnread = function (n) {
        _origSetUnread(n);
        TabTitle.update(Math.max(0, n));
    };

    // ---- Bell shake + badge pulse animation khi noti mới ----
    const shakeBell = () => {
        if (! Bell.$btn) return;
        Bell.$btn.classList.remove('bell-shake');
        void Bell.$btn.offsetWidth;
        Bell.$btn.classList.add('bell-shake');
        // Badge pulse
        if (Bell.$badge) {
            Bell.$badge.classList.remove('badge-pulse');
            void Bell.$badge.offsetWidth;
            Bell.$badge.classList.add('badge-pulse');
        }
    };

    // Realtime subscribe — chờ Echo sẵn sàng
    try {
        if (window.Echo) {
            window.Echo.private(`App.Models.User.${AUTH_USER.id}`)
                .notification((notif) => {
                    console.log('[realtime notification]', notif);

                    Bell.setUnread(Bell.unread + 1);
                    Bell.toast(notif);
                    shakeBell();

                    // Task vừa được giao cho tôi → tăng badge "Công việc"
                    if (notif && notif.type === 'task.assigned') {
                        bumpNavTaskBadge(1);
                    }
                });
        }
    } catch (e) { console.warn('Bell subscribe failed:', e); }

    // Init tab title với số unread ban đầu (server-rendered)
    document.addEventListener('DOMContentLoaded', () => {
        const initUnread = parseInt(Bell.$badge?.textContent || '0', 10) || 0;
        if (initUnread > 0) TabTitle.update(initUnread);
    });

    // Phím tắt N → mở quick task modal (chỉ khi không đang nhập trong input/textarea)
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'n' && e.key !== 'N') return;
        if (e.ctrlKey || e.metaKey || e.altKey) return;
        const t = e.target;
        if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
        const $modal = document.getElementById('quickTaskModal');
        if ($modal) {
            e.preventDefault();
            bootstrap.Modal.getOrCreateInstance($modal).show();
        }
    });
    @endauth
</script>
@stack('scripts')
</body>
</html>
