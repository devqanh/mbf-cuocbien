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

    // Group tasks by relative due-date bucket cho các view có time (mine, today, upcoming, all)
    $shouldGroup = in_array($view, ['mine', 'upcoming', 'created', 'all'], true);
    $groupedTasks = $tasks;
    if ($shouldGroup) {
        $today    = now()->startOfDay();
        $tomorrow = now()->copy()->addDay()->startOfDay();
        $weekEnd  = now()->copy()->endOfWeek();
        $bucket = function ($task) use ($today, $tomorrow, $weekEnd) {
            if (! $task->due_at) return 'no-due';
            if ($task->due_at->isPast() && $task->status !== 'done') return 'overdue';
            if ($task->due_at->lt($tomorrow)) return 'today';
            if ($task->due_at->lt($tomorrow->copy()->addDay())) return 'tomorrow';
            if ($task->due_at->lte($weekEnd)) return 'this-week';
            return 'later';
        };
        $groupedTasks = $tasks->getCollection()->groupBy($bucket);
    }

    $groupLabels = [
        'overdue'   => ['lbl' => 'Quá hạn',     'icon' => 'exclamation-circle-fill', 'color' => 'danger'],
        'today'     => ['lbl' => 'Hôm nay',     'icon' => 'calendar-event-fill',     'color' => 'warning'],
        'tomorrow'  => ['lbl' => 'Ngày mai',    'icon' => 'calendar-day',            'color' => 'info'],
        'this-week' => ['lbl' => 'Tuần này',    'icon' => 'calendar-week',           'color' => 'primary'],
        'later'     => ['lbl' => 'Sau đó',      'icon' => 'calendar3',               'color' => 'secondary'],
        'no-due'    => ['lbl' => 'Không có hạn','icon' => 'infinity',                'color' => 'secondary'],
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

    /* ========== Task card list ========== */
    .task-card {
        background: #fff;
        border: 1px solid var(--azia-border);
        border-radius: 12px;
        overflow: hidden;
    }

    /* ========== Task row — modern minimalist ========== */
    .task-row {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 13px 18px;
        border-bottom: 1px solid var(--azia-border);
        transition: background .12s;
        position: relative;
    }
    .task-row:last-child { border-bottom: none; }
    .task-row:hover { background: #fafbfd; }
    .task-row.is-done { background: #fafbfd; }
    .task-row.is-done .task-title-link {
        text-decoration: line-through;
        color: var(--azia-muted);
    }

    /* Checkbox: bi-circle (todo), bi-clock (doing), bi-check-circle-fill (done) */
    .task-toggle {
        background: none; border: none;
        padding: 0;
        font-size: 22px;
        line-height: 1;
        cursor: pointer;
        transition: transform .1s, color .12s;
        color: #c5cbd6;
        flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        width: 24px; height: 24px;
    }
    .task-toggle:hover { transform: scale(1.1); color: var(--azia-primary); }
    .task-row.is-doing .task-toggle { color: var(--azia-primary); }
    .task-row.is-done  .task-toggle { color: var(--azia-success); }

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
                <a href="{{ route('shipments.index') }}">Trang chủ</a>
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
                    $renderGroups = $shouldGroup
                        ? collect(['overdue','today','tomorrow','this-week','later','no-due'])
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

                        <div class="task-card">
                            @foreach($rows as $task)
                                @php
                                    $overdue = $task->isOverdue();
                                    $isToday = $task->due_at
                                        && $task->due_at->isToday()
                                        && ! $overdue
                                        && $task->status !== 'done';
                                    $togglerNext = $task->status === 'done' ? 'todo' : 'done';
                                    $togglerIcon = match($task->status) {
                                        'done'  => 'check-circle-fill',
                                        'doing' => 'clock-fill',
                                        default => 'circle',
                                    };
                                    $togglerTitle = $task->status === 'done' ? 'Mở lại' : 'Đánh dấu hoàn thành';
                                @endphp

                                <div class="task-row {{ $task->status === 'done' ? 'is-done' : '' }} {{ $task->status === 'doing' ? 'is-doing' : '' }}">
                                    <span class="priority-bar priority-{{ $task->priority }}"></span>

                                    {{-- Toggle done --}}
                                    <form method="POST" action="{{ route('tasks.toggleStatus', $task) }}" class="m-0">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="status" value="{{ $togglerNext }}">
                                        <button type="submit" class="task-toggle" title="{{ $togglerTitle }}">
                                            <i class="bi bi-{{ $togglerIcon }}"></i>
                                        </button>
                                    </form>

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
                                            <span><i class="bi bi-person"></i> {{ $task->creator?->name ?? '—' }}</span>
                                        </div>
                                    </div>

                                    {{-- Right: assignees + actions --}}
                                    <div class="task-right">
                                        <div class="task-assignees">
                                            @foreach($task->assignees->take(3) as $a)
                                                <span class="av" title="{{ $a->name }}">{{ strtoupper(mb_substr($a->name, 0, 1)) }}</span>
                                            @endforeach
                                            @if($task->assignees->count() > 3)
                                                <span class="av av-more" title="{{ $task->assignees->skip(3)->pluck('name')->implode(', ') }}">+{{ $task->assignees->count() - 3 }}</span>
                                            @endif
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
@endsection
