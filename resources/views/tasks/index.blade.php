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

    $priorityBadge = [
        'low'    => ['lbl' => 'Thấp',       'cls' => 'badge-soft-secondary'],
        'normal' => ['lbl' => 'Bình thường','cls' => 'badge-soft-info'],
        'high'   => ['lbl' => 'Cao',        'cls' => 'badge-soft-warning'],
        'urgent' => ['lbl' => 'Khẩn cấp',   'cls' => 'badge-soft-danger'],
    ];
    $statusBadge = [
        'todo'  => ['lbl' => 'Chưa làm', 'cls' => 'badge-soft-secondary', 'next' => 'doing', 'nextLbl' => 'Bắt đầu'],
        'doing' => ['lbl' => 'Đang làm', 'cls' => 'badge-soft-primary',   'next' => 'done',  'nextLbl' => 'Xong'],
        'done'  => ['lbl' => 'Hoàn thành','cls' => 'badge-soft-success',  'next' => 'todo',  'nextLbl' => 'Mở lại'],
    ];
@endphp

@push('styles')
<style>
    .task-layout { display: grid; grid-template-columns: 240px 1fr; gap: 20px; }
    @media (max-width: 992px) { .task-layout { grid-template-columns: 1fr; } }

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

    .task-row {
        display: grid;
        grid-template-columns: 28px 1fr auto auto auto;
        gap: 14px;
        align-items: center;
        padding: 14px 18px;
        border-bottom: 1px solid var(--azia-border);
        transition: background .12s;
    }
    .task-row:last-child { border-bottom: none; }
    .task-row:hover { background: #fafbfd; }
    .task-row.is-done { opacity: .58; }
    .task-row.is-done .task-title { text-decoration: line-through; }
    .task-row.is-overdue .task-due { color: var(--azia-danger); font-weight: 700; }

    .task-check {
        width: 22px; height: 22px;
        border-radius: 7px;
        border: 1.5px solid var(--azia-border);
        background: #fff;
        cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
        color: transparent;
        transition: all .12s;
    }
    .task-check:hover { border-color: var(--azia-primary); }
    .task-row.is-done .task-check {
        background: var(--azia-success);
        border-color: var(--azia-success);
        color: #fff;
    }
    .task-row.is-doing .task-check {
        background: var(--azia-primary);
        border-color: var(--azia-primary);
        color: #fff;
    }
    .task-check i { font-size: 12px; }

    .task-title {
        font-size: 14px; font-weight: 600;
        color: var(--azia-text);
        margin: 0; line-height: 1.4;
    }
    .task-title a { color: inherit; text-decoration: none; }
    .task-title a:hover { color: var(--azia-primary); }
    .task-meta {
        display: flex; flex-wrap: wrap; gap: 10px;
        font-size: 11.5px; color: var(--azia-muted);
        margin-top: 4px;
    }
    .task-meta i { margin-right: 3px; }
    .task-due i { margin-right: 4px; }

    .task-assignees { display: inline-flex; }
    .task-assignees .av {
        width: 26px; height: 26px; border-radius: 50%;
        background: var(--azia-primary); color: #fff;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 700;
        border: 2px solid #fff;
        margin-left: -8px;
    }
    .task-assignees .av:first-child { margin-left: 0; }

    .empty-state {
        padding: 60px 24px;
        text-align: center;
        color: var(--azia-muted);
    }
    .empty-state i { font-size: 56px; opacity: .3; }
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
        <div class="card p-0">
            @forelse($tasks as $task)
                @php
                    $sb = $statusBadge[$task->status] ?? $statusBadge['todo'];
                    $pb = $priorityBadge[$task->priority] ?? $priorityBadge['normal'];
                    $overdue = $task->isOverdue();
                @endphp
                <div class="task-row {{ $task->status === 'done' ? 'is-done' : '' }} {{ $task->status === 'doing' ? 'is-doing' : '' }} {{ $overdue ? 'is-overdue' : '' }}">
                    {{-- Checkbox toggle status --}}
                    <form method="POST" action="{{ route('tasks.toggleStatus', $task) }}" class="m-0">
                        @csrf @method('PUT')
                        <input type="hidden" name="status" value="{{ $sb['next'] }}">
                        <button type="submit" class="task-check border-0" title="{{ $sb['nextLbl'] }}">
                            <i class="bi bi-check"></i>
                        </button>
                    </form>

                    {{-- Title + meta --}}
                    <div>
                        <div class="task-title">
                            <a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
                        </div>
                        <div class="task-meta">
                            <span class="badge {{ $sb['cls'] }}">{{ $sb['lbl'] }}</span>
                            @if($task->priority !== 'normal')
                                <span class="badge {{ $pb['cls'] }}">
                                    <i class="bi bi-flag-fill"></i> {{ $pb['lbl'] }}
                                </span>
                            @endif
                            @if($task->linkable)
                                <span><i class="bi bi-link-45deg"></i>
                                    @if($task->linkable_type === \App\Models\Shipment::class)
                                        Shipment #{{ $task->linkable_id }}
                                    @elseif($task->linkable_type === \App\Models\PayableReport::class)
                                        Báo cáo #{{ $task->linkable_id }}
                                    @endif
                                </span>
                            @endif
                            <span><i class="bi bi-person"></i> {{ $task->creator?->name ?? '—' }}</span>
                        </div>
                    </div>

                    {{-- Due date --}}
                    <div class="task-due small">
                        @if($task->due_at)
                            <i class="bi bi-clock"></i>
                            <span title="{{ $task->due_at->format('d/m/Y H:i') }}">
                                {{ $task->due_at->format('d/m H:i') }}
                            </span>
                            @if($overdue)
                                <div class="small">Quá hạn {{ $task->due_at->diffForHumans(now(), true) }}</div>
                            @endif
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </div>

                    {{-- Assignees --}}
                    <div class="task-assignees">
                        @foreach($task->assignees->take(3) as $a)
                            <span class="av" title="{{ $a->name }}">{{ strtoupper(mb_substr($a->name, 0, 1)) }}</span>
                        @endforeach
                        @if($task->assignees->count() > 3)
                            <span class="av" style="background:#7987a1">+{{ $task->assignees->count() - 3 }}</span>
                        @endif
                    </div>

                    {{-- View link --}}
                    <a href="{{ route('tasks.show', $task) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            @empty
                <div class="empty-state">
                    <i class="bi bi-{{ $views[$view]['icon'] ?? 'inbox' }}"></i>
                    <h5 class="mt-3 mb-1">Không có task nào</h5>
                    <p class="small mb-0">
                        @if($view === 'mine')
                            Chưa ai giao task cho bạn. Hoặc bấm <kbd>N</kbd> để tự tạo task.
                        @elseif($view === 'overdue')
                            Tốt! Không có task nào quá hạn.
                        @else
                            Bộ lọc này hiện không có kết quả.
                        @endif
                    </p>
                </div>
            @endforelse

            @if($tasks->hasPages())
                <div class="p-3 border-top">{{ $tasks->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>
@endsection
