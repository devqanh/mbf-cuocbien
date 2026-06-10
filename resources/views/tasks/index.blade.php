@extends('layouts.app')

@section('title', 'Công việc & ghi chú')

@php
    $u = auth()->user();

    $views = [
        'mine'     => ['label' => 'Được giao cho tôi', 'icon' => 'inbox-fill',        'color' => 'primary'],
        'today'    => ['label' => 'Hôm nay',           'icon' => 'calendar-event',    'color' => 'warning'],
        'overdue'  => ['label' => 'Quá hạn',           'icon' => 'exclamation-circle-fill', 'color' => 'danger'],
        'upcoming' => ['label' => '7 ngày tới',        'icon' => 'calendar-week',     'color' => 'info'],
        'created'  => ['label' => 'Tôi tạo',           'icon' => 'pencil-square',     'color' => 'success'],
        'done'     => ['label' => 'Đã hoàn thành',     'icon' => 'check2-all',        'color' => 'secondary'],
    ];
    if ($u->can('tasks.manage_all')) {
        $views['all'] = ['label' => 'Tất cả (admin)', 'icon' => 'collection-fill', 'color' => 'dark'];
    }

    $priorityMeta = [
        'low'    => ['lbl' => 'Thấp',       'color' => '#7987a1'],
        'normal' => ['lbl' => 'Bình thường','color' => '#00b8d4'],
        'high'   => ['lbl' => 'Cao',        'color' => '#ffb822'],
        'urgent' => ['lbl' => 'Khẩn cấp',   'color' => '#ff5b5b'],
    ];

    // Status options dùng cho status picker dropdown
    $statusOptions = [
        'todo'  => ['lbl' => 'Chưa làm',  'color' => 'secondary'],
        'doing' => ['lbl' => 'Đang làm',  'color' => 'primary'],
        'done'  => ['lbl' => 'Hoàn thành','color' => 'success'],
    ];

    // Group tasks by relative due-date bucket cho các view có time (mine, today, upcoming, all)
    $shouldGroup = in_array($view, ['mine', 'upcoming', 'created', 'all'], true);
    $groupedTasks = $tasks;
    $dayLabels = [];   // labels cho D+2 đến D+7 (sẽ generate động)

    if ($shouldGroup) {
        $today = now()->startOfDay();

        // Pre-generate labels theo ngày cụ thể (Thứ X, DD/MM) cho D+2 đến D+7
        $weekdayNames = [
            'Mon' => 'Thứ 2', 'Tue' => 'Thứ 3', 'Wed' => 'Thứ 4',
            'Thu' => 'Thứ 5', 'Fri' => 'Thứ 6', 'Sat' => 'Thứ 7', 'Sun' => 'Chủ nhật',
        ];
        for ($i = 2; $i <= 7; $i++) {
            $d = $today->copy()->addDays($i);
            $key = 'day-' . $d->format('Y-m-d');
            $dayLabels[$key] = [
                'lbl'   => $weekdayNames[$d->format('D')] . ', ' . $d->format('d/m'),
                'icon'  => 'calendar-day',
                'color' => 'primary',
            ];
        }

        $bucket = function ($task) use ($today) {
            if (! $task->due_at) return 'no-due';
            if ($task->due_at->isPast() && $task->status !== 'done') return 'overdue';
            $dueDate = $task->due_at->copy()->startOfDay();
            $diff = $today->diffInDays($dueDate, false);
            if ($diff === 0) return 'today';
            if ($diff === 1) return 'tomorrow';
            if ($diff >= 2 && $diff <= 7) return 'day-' . $dueDate->format('Y-m-d');
            return 'later';   // >7 ngày
        };
        $groupedTasks = $tasks->getCollection()->groupBy($bucket);
    }

    $groupLabels = [
        'overdue'   => ['lbl' => 'Quá hạn',         'icon' => 'exclamation-circle-fill', 'color' => 'danger'],
        'today'     => ['lbl' => 'Hôm nay',         'icon' => 'calendar-event-fill',     'color' => 'warning'],
        'tomorrow'  => ['lbl' => 'Ngày mai',        'icon' => 'calendar-day',            'color' => 'info'],
        ...$dayLabels,
        'later'     => ['lbl' => 'Sau 7 ngày tới',  'icon' => 'calendar3',               'color' => 'secondary'],
        'no-due'    => ['lbl' => 'Không có hạn',    'icon' => 'infinity',                'color' => 'secondary'],
    ];
@endphp

@push('styles')
<style>
    .task-layout { display: grid; grid-template-columns: 240px 1fr; gap: 20px; }
    @media (max-width: 992px) { .task-layout { grid-template-columns: 1fr; } }

    /* ========== Sidebar ========== */
    .task-sidebar {
        background: #fff;
        border: 1px solid var(--azia-border);
        border-radius: 12px;
        padding: 14px;
        height: fit-content;
        position: sticky; top: 84px;
    }
    .task-sidebar-title {
        font-size: 11px; text-transform: uppercase; letter-spacing: .8px;
        color: var(--azia-muted); font-weight: 700;
        padding: 0 8px 8px;
    }
    .task-view-item {
        display: flex; align-items: center; gap: 10px;
        padding: 9px 12px; border-radius: 10px;
        color: var(--azia-text);
        font-size: 13.5px; font-weight: 500;
        text-decoration: none;
        transition: all .12s;
        margin-bottom: 2px;
    }
    .task-view-item:hover { background: var(--azia-bg); color: var(--azia-text); }
    .task-view-item.active {
        background: var(--azia-primary-soft);
        color: var(--azia-primary);
        font-weight: 600;
    }
    .task-view-item i { width: 18px; }
    .task-view-item .count {
        margin-left: auto;
        font-size: 11px;
        background: var(--azia-bg);
        color: var(--azia-muted);
        padding: 2px 8px;
        border-radius: 999px;
        font-weight: 600;
    }
    .task-view-item.active .count { background: #fff; color: var(--azia-primary); }

    /* ========== Group section ========== */
    .group-section { margin-bottom: 22px; }
    .group-section:last-child { margin-bottom: 0; }
    .group-header {
        display: flex; align-items: center; gap: 8px;
        padding: 10px 4px 8px;
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 700;
        color: var(--azia-muted);
    }
    .group-header.is-danger  { color: var(--azia-danger); }
    .group-header.is-warning { color: #d28b00; }
    .group-header.is-info    { color: var(--azia-info); }
    .group-header.is-primary { color: var(--azia-primary); }
    .group-header .count-pill {
        background: var(--azia-bg);
        color: var(--azia-muted);
        font-size: 10px;
        padding: 2px 8px;
        border-radius: 999px;
        text-transform: none;
        letter-spacing: 0;
        font-weight: 600;
    }
    .group-header .line {
        flex: 1; height: 1px; background: var(--azia-border);
        margin-left: 4px;
    }

    /* ========== Task grid — 2 cột trên desktop, 1 cột mobile ========== */
    .task-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    @media (max-width: 992px) {
        .task-grid { grid-template-columns: 1fr; }
    }

    /* Mỗi task-row giờ là 1 card độc lập */
    .task-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        background: #fff;
        border: 1px solid var(--azia-border);
        border-radius: 12px;
        transition: all .12s;
        position: relative;
        min-width: 0;
    }
    .task-row:hover {
        background: #fafbfd;
        border-color: var(--azia-primary);
        box-shadow: 0 4px 12px rgba(28, 39, 60, .06);
    }
    .task-row.is-done { background: #fafbfd; opacity: .85; }
    .task-row.is-done .task-title-link {
        text-decoration: line-through;
        color: var(--azia-muted);
    }

    /* ========== Status Picker (dropdown 3 trạng thái) ========== */
    .task-status-picker { position: relative; flex-shrink: 0; }

    /* Nút tròn có BORDER RÕ — clearly "đây là 1 checkbox/button" */
    .status-current {
        width: 30px; height: 30px;
        border-radius: 50%;
        background: #fff;
        border: 2px solid #d0d6e0;
        cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
        padding: 0;
        font-size: 14px;
        color: #d0d6e0;
        transition: all .15s;
        position: relative;
    }
    .status-current:hover {
        transform: scale(1.08);
        box-shadow: 0 2px 8px rgba(28, 39, 60, .12);
    }
    .status-current::after {
        /* Mũi tên nhỏ dưới góc — hint "có dropdown" */
        content: '';
        position: absolute;
        bottom: -2px; right: -2px;
        width: 10px; height: 10px;
        background: #fff;
        border: 1.5px solid #d0d6e0;
        border-radius: 50%;
        background-image: url("data:image/svg+xml;charset=utf-8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%237987a1'><path d='M3.204 5h9.592L8 10.481 3.204 5zm-.753.659 4.796 5.48a1 1 0 0 0 1.506 0l4.796-5.48c.566-.647.106-1.659-.753-1.659H3.204a1 1 0 0 0-.753 1.659z'/></svg>");
        background-size: 7px; background-repeat: no-repeat; background-position: center;
    }

    /* Trạng thái: todo = empty circle */
    .task-row:not(.is-doing):not(.is-done) .status-current {
        color: transparent;       /* ẩn icon, chỉ thấy border */
    }
    .task-row:not(.is-doing):not(.is-done) .status-current:hover {
        border-color: var(--azia-success);
        background: rgba(36, 211, 159, .08);
        color: var(--azia-success);
    }

    /* Trạng thái: doing = border primary + dot chính giữa */
    .task-row.is-doing .status-current {
        border-color: var(--azia-primary);
        color: var(--azia-primary);
    }
    .task-row.is-doing .status-current::before {
        content: '';
        width: 12px; height: 12px;
        border-radius: 50%;
        background: var(--azia-primary);
    }
    .task-row.is-doing .status-current > * { display: none; } /* ẩn icon BS — chỉ dùng ::before */

    /* Trạng thái: done = nền xanh đặc + check trắng */
    .task-row.is-done .status-current {
        background: var(--azia-success);
        border-color: var(--azia-success);
        color: #fff;
    }
    .task-row.is-done .status-current::after {
        /* Cho biểu tượng tròn nhỏ ở góc cũng đổi sang trắng */
        background-color: var(--azia-success);
        border-color: rgba(255,255,255,.5);
        background-image: url("data:image/svg+xml;charset=utf-8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'><path d='M3.204 5h9.592L8 10.481 3.204 5zm-.753.659 4.796 5.48a1 1 0 0 0 1.506 0l4.796-5.48c.566-.647.106-1.659-.753-1.659H3.204a1 1 0 0 0-.753 1.659z'/></svg>");
    }

    /* Dropdown menu cho status picker */
    .status-menu {
        min-width: 200px;
        padding: 6px;
        border: 1px solid var(--azia-border);
        border-radius: 12px;
        box-shadow: 0 12px 32px rgba(28, 39, 60, .14);
    }
    .status-menu .status-item {
        display: flex; align-items: center;
        gap: 10px;
        padding: 8px 10px;
        border-radius: 8px;
        font-size: 13.5px;
        font-weight: 500;
        background: transparent;
        border: none;
        width: 100%;
        text-align: left;
        color: var(--azia-text);
        cursor: pointer;
        transition: background .12s;
    }
    .status-menu .status-item:hover { background: var(--azia-bg); }
    .status-menu .status-item.is-current {
        background: var(--azia-primary-soft);
        color: var(--azia-primary);
        font-weight: 700;
    }
    .status-menu .status-dot {
        width: 14px; height: 14px;
        border-radius: 50%;
        border: 2px solid #d0d6e0;
        background: #fff;
        flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .status-menu .status-dot.dot-doing { border-color: var(--azia-primary); background: var(--azia-primary); }
    .status-menu .status-dot.dot-done {
        border-color: var(--azia-success); background: var(--azia-success);
        color: #fff; font-size: 9px;
    }
    .status-menu .status-item .check {
        margin-left: auto;
        color: var(--azia-primary);
    }
    .status-menu form { margin: 0; }

    /* Priority indicator: vertical bar on left of title */
    .priority-bar {
        width: 3px;
        height: 32px;
        border-radius: 3px;
        flex-shrink: 0;
        background: transparent;
    }
    .priority-bar.priority-urgent { background: #ff5b5b; }
    .priority-bar.priority-high   { background: #ffb822; }
    .priority-bar.priority-normal { background: #e1e6f1; }
    .priority-bar.priority-low    { background: #e1e6f1; }

    /* Title + sub-line */
    .task-content { flex: 1; min-width: 0; }
    .task-title-row {
        display: flex; align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .task-title-link {
        font-size: 14px;
        font-weight: 600;
        color: var(--azia-text);
        text-decoration: none;
        line-height: 1.35;
    }
    .task-title-link:hover { color: var(--azia-primary); }

    .task-chip {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.5;
    }
    .chip-doing { background: rgba(1,83,169,.1); color: var(--azia-primary); }
    .chip-priority-urgent { background: rgba(255,91,91,.12); color: var(--azia-danger); }
    .chip-priority-high   { background: rgba(255,184,34,.18); color: #d28b00; }
    .chip-link { background: var(--azia-bg); color: var(--azia-muted); }

    .task-subline {
        display: flex; flex-wrap: wrap;
        gap: 10px;
        margin-top: 3px;
        font-size: 11.5px;
        color: var(--azia-muted);
    }
    .task-subline > span { display: inline-flex; align-items: center; gap: 4px; }
    .task-subline .due.is-overdue {
        color: var(--azia-danger);
        font-weight: 700;
    }
    .task-subline .due.is-today {
        color: #d28b00;
        font-weight: 600;
    }

    /* Right side */
    .task-right {
        display: flex; align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }
    .task-assignees { display: inline-flex; }
    .task-assignees .av {
        width: 28px; height: 28px; border-radius: 50%;
        background: var(--azia-primary); color: #fff;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 700;
        border: 2px solid #fff;
        margin-left: -8px;
        transition: transform .12s;
    }
    .task-assignees .av:first-child { margin-left: 0; }
    .task-row:hover .task-assignees .av { transform: translateY(-1px); }
    .task-assignees .av.av-more { background: #cbd5e1; color: #475569; }

    /* ========== Participants popover (hover hiện list user) ========== */
    .has-popover { position: relative; cursor: default; }
    .participants-popover {
        position: absolute;
        bottom: calc(100% + 8px);
        left: 50%;
        transform: translateX(-50%) translateY(6px);
        background: #fff;
        border: 1px solid var(--azia-border);
        border-radius: 12px;
        box-shadow: 0 12px 32px rgba(28, 39, 60, .14);
        padding: 10px;
        min-width: 240px;
        max-width: 320px;
        opacity: 0;
        visibility: hidden;
        transition: opacity .15s, transform .15s;
        z-index: 50;
        pointer-events: none;
    }
    .has-popover:hover .participants-popover {
        opacity: 1;
        visibility: visible;
        transform: translateX(-50%) translateY(0);
    }
    /* Mũi tên trỏ xuống dưới popover */
    .participants-popover::after {
        content: '';
        position: absolute;
        top: 100%; left: 50%;
        transform: translateX(-50%);
        border: 7px solid transparent;
        border-top-color: #fff;
        margin-top: -1px;
        filter: drop-shadow(0 2px 1px rgba(28,39,60,.08));
    }
    .popover-title {
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: .8px;
        font-weight: 700;
        color: var(--azia-muted);
        margin-bottom: 6px;
        padding: 0 4px;
    }
    .popover-row {
        display: flex; align-items: center;
        gap: 10px;
        padding: 6px 4px;
        border-radius: 8px;
        font-size: 13px;
        color: var(--azia-text);
    }
    .popover-row:hover { background: var(--azia-bg); }
    .popover-row .popover-av {
        width: 28px; height: 28px; border-radius: 50%;
        background: var(--azia-primary); color: #fff;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 700;
        flex-shrink: 0;
    }
    .popover-row .popover-name { flex: 1; font-weight: 500; }
    .popover-row .popover-tag {
        font-size: 10px;
        background: var(--azia-primary-soft);
        color: var(--azia-primary);
        padding: 1px 7px;
        border-radius: 999px;
        font-weight: 600;
    }
    /* Popover trên avatar stack (bên phải) → căn về phải để không bị tràn */
    .task-right .has-popover .participants-popover {
        left: auto; right: 0;
        transform: translateY(6px);
    }
    .task-right .has-popover:hover .participants-popover {
        transform: translateY(0);
    }
    .task-right .has-popover .participants-popover::after {
        left: auto; right: 12px;
        transform: none;
    }

    /* Quick actions revealed on hover */
    .task-actions {
        display: inline-flex; gap: 4px;
        opacity: 0;
        transition: opacity .15s;
    }
    .task-row:hover .task-actions { opacity: 1; }
    .task-actions .icon-act {
        background: #fff;
        border: 1px solid var(--azia-border);
        width: 30px; height: 30px;
        border-radius: 8px;
        color: var(--azia-muted);
        display: inline-flex; align-items: center; justify-content: center;
        cursor: pointer;
        transition: all .12s;
        font-size: 13px;
        text-decoration: none;
    }
    .task-actions .icon-act:hover {
        border-color: var(--azia-primary);
        color: var(--azia-primary);
        background: var(--azia-primary-soft);
    }

    /* Empty state */
    .empty-state {
        padding: 64px 24px;
        text-align: center;
        color: var(--azia-muted);
    }
    .empty-state i { font-size: 56px; opacity: .3; }
    .empty-state h5 { font-weight: 700; color: var(--azia-text); }
</style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <h1><i class="bi bi-check2-square me-1" style="color: var(--azia-primary)"></i> Công việc & ghi chú</h1>
            <nav class="breadcrumb mt-1">
                <a href="{{ route('trucking.index') }}">Trang chủ</a>
                <span class="mx-2">/</span>
                <span>{{ $views[$view]['label'] ?? 'Tất cả' }}</span>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <form method="GET" action="{{ route('tasks.index') }}" class="d-flex gap-2">
                <input type="hidden" name="view" value="{{ $view }}">
                <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm"
                       placeholder="Tìm tiêu đề / nội dung…" style="min-width:240px">
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
            </form>
            @can('tasks.create')
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quickTaskModal">
                <i class="bi bi-plus-lg me-1"></i> Tạo task <small class="ms-1 opacity-75">N</small>
            </button>
            @endcan
        </div>
    </div>

    <div class="task-layout">
        {{-- Sidebar filters --}}
        <aside class="task-sidebar">
            <div class="task-sidebar-title">Bộ lọc</div>
            @foreach($views as $key => $v)
                <a href="{{ route('tasks.index', ['view' => $key, 'q' => $q ?: null]) }}"
                   class="task-view-item {{ $view === $key ? 'active' : '' }}">
                    <i class="bi bi-{{ $v['icon'] }} text-{{ $v['color'] }}"></i>
                    <span>{{ $v['label'] }}</span>
                    @if(isset($counters[$key]) && $counters[$key] > 0)
                        <span class="count">{{ $counters[$key] }}</span>
                    @endif
                </a>
            @endforeach
        </aside>

        {{-- Task list --}}
        <div>
            @if($tasks->isEmpty())
                <div class="task-card">
                    <div class="empty-state">
                        <i class="bi bi-{{ $views[$view]['icon'] ?? 'inbox' }}"></i>
                        <h5 class="mt-3 mb-1">Không có công việc nào</h5>
                        <p class="small mb-0">
                            @if($view === 'mine')
                                Chưa ai giao việc cho bạn. Bấm <kbd>N</kbd> để tự tạo task mới.
                            @elseif($view === 'overdue')
                                Tuyệt vời! Không có công việc nào quá hạn.
                            @elseif($view === 'today')
                                Không có công việc nào hôm nay. Chill 😌
                            @else
                                Bộ lọc này hiện không có kết quả.
                            @endif
                        </p>
                    </div>
                </div>
            @else
                @php
                    // Nếu không group → wrap tất cả trong 1 group "ungrouped" cho consistent rendering
                    // Thứ tự render: overdue → today → tomorrow → D+2..D+7 → later → no-due
                    $renderOrder = array_merge(
                        ['overdue', 'today', 'tomorrow'],
                        array_keys($dayLabels),   // day-YYYY-MM-DD keys cho D+2..D+7
                        ['later', 'no-due']
                    );
                    $renderGroups = $shouldGroup
                        ? collect($renderOrder)
                            ->filter(fn($k) => isset($groupedTasks[$k]) && $groupedTasks[$k]->isNotEmpty())
                            ->mapWithKeys(fn($k) => [$k => $groupedTasks[$k]])
                        : collect(['_' => $tasks->getCollection()]);
                @endphp

                @foreach($renderGroups as $groupKey => $rows)
                    <div class="group-section">
                        @if($shouldGroup)
                            @php $g = $groupLabels[$groupKey] ?? null; @endphp
                            @if($g)
                                <div class="group-header is-{{ $g['color'] }}">
                                    <i class="bi bi-{{ $g['icon'] }}"></i>
                                    <span>{{ $g['lbl'] }}</span>
                                    <span class="count-pill">{{ $rows->count() }}</span>
                                    <span class="line"></span>
                                </div>
                            @endif
                        @endif

                        <div class="task-grid">
                            @foreach($rows as $task)
                                @php
                                    $overdue = $task->isOverdue();
                                    $isToday = $task->due_at
                                        && $task->due_at->isToday()
                                        && ! $overdue
                                        && $task->status !== 'done';
                                    $togglerNext = $task->status === 'done' ? 'todo' : 'done';
                                    // Icon clearer cho 3 status — không dùng clock-fill (gây nhầm deadline)
                                    $togglerIcon = match($task->status) {
                                        'done'  => 'check-circle-fill',
                                        'doing' => 'record-circle-fill',
                                        default => 'circle',
                                    };
                                    $togglerTitle = $task->status === 'done' ? 'Mở lại' : 'Đánh dấu hoàn thành';
                                @endphp

                                <div class="task-row {{ $task->status === 'done' ? 'is-done' : '' }} {{ $task->status === 'doing' ? 'is-doing' : '' }}">
                                    <span class="priority-bar priority-{{ $task->priority }}"></span>

                                    {{-- Status picker — dropdown 3 trạng thái rõ ràng --}}
                                    <div class="task-status-picker dropdown">
                                        <button type="button" class="status-current"
                                                data-bs-toggle="dropdown" aria-expanded="false"
                                                title="Đổi trạng thái">
                                            <i class="bi bi-check2"></i>
                                        </button>
                                        <ul class="dropdown-menu status-menu">
                                            @foreach($statusOptions as $key => $opt)
                                                <li>
                                                    <form method="POST" action="{{ route('tasks.toggleStatus', $task) }}"
                                                          class="task-toggle-form"
                                                          data-task-title="{{ $task->title }}"
                                                          data-target-status="{{ $key }}"
                                                          data-target-label="{{ $opt['lbl'] }}"
                                                          data-confirm="1">
                                                        @csrf @method('PUT')
                                                        <input type="hidden" name="status" value="{{ $key }}">
                                                        <button type="submit" class="status-item {{ $task->status === $key ? 'is-current' : '' }}"
                                                                {{ $task->status === $key ? 'disabled' : '' }}>
                                                            <span class="status-dot dot-{{ $key }}">
                                                                @if($key === 'done')<i class="bi bi-check"></i>@endif
                                                            </span>
                                                            <span>{{ $opt['lbl'] }}</span>
                                                            @if($task->status === $key)
                                                                <i class="bi bi-check2 check"></i>
                                                            @endif
                                                        </button>
                                                    </form>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>

                                    {{-- Title + sub-line --}}
                                    <div class="task-content">
                                        <div class="task-title-row">
                                            <a href="{{ route('tasks.show', $task) }}" class="task-title-link">{{ $task->title }}</a>

                                            @if($task->status === 'doing')
                                                <span class="task-chip chip-doing">
                                                    <i class="bi bi-play-fill"></i> Đang làm
                                                </span>
                                            @endif

                                            @if(in_array($task->priority, ['urgent', 'high'], true))
                                                <span class="task-chip chip-priority-{{ $task->priority }}">
                                                    <i class="bi bi-flag-fill"></i> {{ $priorityMeta[$task->priority]['lbl'] }}
                                                </span>
                                            @endif

                                            @if($task->linkable)
                                                <span class="task-chip chip-link">
                                                    <i class="bi bi-link-45deg"></i>
                                                    @if($task->linkable_type === \App\Models\Shipment::class)
                                                        Shipment #{{ $task->linkable_id }}
                                                    @elseif($task->linkable_type === \App\Models\PayableReport::class)
                                                        Báo cáo #{{ $task->linkable_id }}
                                                    @endif
                                                </span>
                                            @endif
                                        </div>

                                        @php
                                            // Gom creator + assignees thành "participants" (unique)
                                            $participants = collect();
                                            if ($task->creator) $participants->push($task->creator);
                                            $participants = $participants->concat($task->assignees)
                                                ->unique('id')
                                                ->values();
                                            $participantNames = $participants->pluck('name')->implode(', ');
                                        @endphp
                                        <div class="task-subline">
                                            @if($task->due_at)
                                                <span class="due {{ $overdue ? 'is-overdue' : ($isToday ? 'is-today' : '') }}"
                                                      title="{{ $task->due_at->format('d/m/Y H:i') }}">
                                                    <i class="bi bi-{{ $overdue ? 'alarm-fill' : 'clock' }}"></i>
                                                    @if($overdue)
                                                        Quá hạn {{ $task->due_at->diffForHumans(now(), true) }}
                                                    @elseif($isToday)
                                                        Hôm nay {{ $task->due_at->format('H:i') }}
                                                    @else
                                                        {{ $task->due_at->format('d/m H:i') }}
                                                    @endif
                                                </span>
                                            @endif
                                            {{-- Participants count — hover hiện popover list user --}}
                                            <span class="has-popover">
                                                <i class="bi bi-people-fill"></i> {{ $participants->count() }} người
                                                <span class="participants-popover">
                                                    <div class="popover-title">Người tham gia ({{ $participants->count() }})</div>
                                                    @foreach($participants as $p)
                                                        <div class="popover-row">
                                                            <span class="popover-av">{{ strtoupper(mb_substr($p->name, 0, 1)) }}</span>
                                                            <span class="popover-name">{{ $p->name }}</span>
                                                            @if($p->id === $task->created_by)
                                                                <span class="popover-tag">Tạo</span>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </span>
                                            </span>
                                        </div>
                                    </div>

                                    {{-- Right: avatar stack tất cả participants + action --}}
                                    <div class="task-right">
                                        <div class="task-assignees has-popover">
                                            @foreach($participants->take(3) as $p)
                                                <span class="av">{{ strtoupper(mb_substr($p->name, 0, 1)) }}</span>
                                            @endforeach
                                            @if($participants->count() > 3)
                                                <span class="av av-more">+{{ $participants->count() - 3 }}</span>
                                            @endif
                                            <span class="participants-popover">
                                                <div class="popover-title">Người tham gia ({{ $participants->count() }})</div>
                                                @foreach($participants as $p)
                                                    <div class="popover-row">
                                                        <span class="popover-av">{{ strtoupper(mb_substr($p->name, 0, 1)) }}</span>
                                                        <span class="popover-name">{{ $p->name }}</span>
                                                        @if($p->id === $task->created_by)
                                                            <span class="popover-tag">Tạo</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </span>
                                        </div>

                                        <div class="task-actions">
                                            <a href="{{ route('tasks.show', $task) }}" class="icon-act" title="Xem chi tiết">
                                                <i class="bi bi-arrow-up-right"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if($tasks->hasPages())
                    <div class="task-card p-3 mt-3">{{ $tasks->withQueryString()->links() }}</div>
                @endif
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        // ---- Confirm SweetAlert trước khi đánh dấu task = done ----
        // - Đánh dấu done: hỏi xác nhận (đỡ lỡ tay)
        // - Mở lại (done → todo): submit thẳng, không hỏi
        document.querySelectorAll('.task-toggle-form').forEach(($form) => {
            const needConfirm = $form.dataset.confirm === '1';
            if (! needConfirm) return;

            $form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const title  = ($form.dataset.taskTitle || 'task này').replace(/</g,'&lt;');
                const target = $form.dataset.targetStatus;

                let opts;
                if (target === 'done') {
                    opts = {
                        icon: 'question',
                        title: 'Đã hoàn thành task?',
                        text: `Đánh dấu <b>"${title}"</b> là <b>Hoàn thành</b>?<br><small class="text-muted">Task sẽ ẩn khỏi danh sách "Được giao cho tôi".</small>`,
                        confirmText: '<i class="bi bi-check2-circle me-1"></i> Đã xong',
                    };
                } else if (target === 'doing') {
                    opts = {
                        icon: 'question',
                        title: 'Bắt đầu làm task?',
                        text: `Chuyển <b>"${title}"</b> sang trạng thái <b>Đang làm</b>?`,
                        confirmText: '<i class="bi bi-play-fill me-1"></i> Bắt đầu',
                    };
                } else { // todo
                    opts = {
                        icon: 'warning',
                        title: 'Đặt lại về Chưa làm?',
                        text: `Task <b>"${title}"</b> sẽ trở về trạng thái <b>Chưa làm</b>.`,
                        confirmText: '<i class="bi bi-arrow-counterclockwise me-1"></i> Đặt lại',
                    };
                }

                const ok = await confirmAction(opts);
                if (ok) {
                    const $btn = $form.querySelector('button[type=submit]');
                    if ($btn) $btn.disabled = true;
                    $form.submit();
                }
            });
        });
    </script>
    @endpush
@endsection
