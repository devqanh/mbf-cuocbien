@extends('layouts.app')

@section('title', 'Task — ' . $task->title)

@php
    $statusOptions = [
        'todo'  => ['lbl' => 'Chưa làm',  'color' => 'secondary', 'icon' => 'circle'],
        'doing' => ['lbl' => 'Đang làm',  'color' => 'primary',   'icon' => 'play-circle-fill'],
        'done'  => ['lbl' => 'Hoàn thành','color' => 'success',   'icon' => 'check-circle-fill'],
    ];
    $priorityOptions = [
        'low'    => ['lbl' => 'Thấp',       'color' => 'secondary'],
        'normal' => ['lbl' => 'Bình thường','color' => 'info'],
        'high'   => ['lbl' => 'Cao',        'color' => 'warning'],
        'urgent' => ['lbl' => 'Khẩn cấp',   'color' => 'danger'],
    ];
    $overdue = $task->isOverdue();
    $canEdit = auth()->user()->can('tasks.manage_all')
        || $task->created_by === auth()->id()
        || $task->assignees->contains('id', auth()->id());
    $canDelete = auth()->user()->can('tasks.manage_all')
        || $task->created_by === auth()->id();
@endphp

@push('styles')
<style>
    .task-detail-header {
        background: linear-gradient(135deg, #0153a9 0%, #013f80 100%);
        color: #fff;
        border-radius: 14px;
        padding: 24px 28px;
        margin-bottom: 20px;
        position: relative;
        overflow: hidden;
    }
    .task-detail-header::after {
        content: '';
        position: absolute;
        width: 320px; height: 320px;
        border-radius: 50%;
        background: rgba(255,255,255,.06);
        top: -120px; right: -100px;
    }
    .task-detail-header h1 {
        font-size: 22px; font-weight: 700; margin: 0; position: relative; z-index: 1;
    }
    .task-detail-header .meta {
        opacity: .85; font-size: 13px; margin-top: 8px;
        display: flex; flex-wrap: wrap; gap: 14px; position: relative; z-index: 1;
    }
    .task-detail-header .meta i { margin-right: 4px; }
    .status-pill {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 5px 12px; border-radius: 999px;
        background: rgba(255,255,255,.2);
        font-size: 12px; font-weight: 600;
        position: relative; z-index: 1;
    }

    .info-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 12px 0;
        border-bottom: 1px dashed var(--azia-border);
        font-size: 13.5px;
    }
    .info-row:last-child { border-bottom: none; }
    .info-row .label { color: var(--azia-muted); }
    .info-row .value { font-weight: 600; }
    .av-list .av {
        width: 28px; height: 28px; border-radius: 50%;
        background: var(--azia-primary); color: #fff;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 700;
        margin-right: 4px;
    }
    .body-block {
        background: #fafbfd;
        border-radius: 10px;
        padding: 16px;
        white-space: pre-wrap;
        font-size: 14px;
        line-height: 1.6;
        color: var(--azia-text);
    }

    /* ========== Progress stepper status ========== */
    .status-stepper {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        padding: 8px 12px 4px;
        gap: 0;
        position: relative;
    }
    .step-form { margin: 0; flex: 0 0 auto; position: relative; z-index: 2; }
    .step-btn {
        background: transparent;
        border: none;
        padding: 0;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        min-width: 100px;
    }
    .step-btn:disabled { cursor: default; }

    .step-circle {
        width: 44px; height: 44px;
        border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 18px;
        background: #fff;
        border: 2px solid var(--azia-border);
        color: var(--azia-muted);
        transition: all .25s cubic-bezier(.2, .8, .3, 1);
        position: relative;
    }
    .step-label {
        font-size: 12.5px;
        font-weight: 600;
        color: var(--azia-muted);
        transition: color .2s;
        white-space: nowrap;
    }

    /* Hover trên step chưa active → highlight */
    .step-btn:not(:disabled):hover .step-circle {
        border-color: var(--azia-primary);
        color: var(--azia-primary);
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(1, 83, 169, .15);
    }
    .step-btn:not(:disabled):hover .step-label { color: var(--azia-primary); }

    /* Passed step (đã qua) — màu primary đậm, check icon */
    .step-passed .step-circle {
        background: var(--azia-primary);
        border-color: var(--azia-primary);
        color: #fff;
    }
    .step-passed .step-label { color: var(--azia-primary); }

    /* Current step (đang ở) — gradient + glow + pulse */
    .step-current .step-circle {
        background: linear-gradient(135deg, var(--azia-primary) 0%, #4a8fd9 100%);
        border-color: var(--azia-primary);
        color: #fff;
        box-shadow: 0 0 0 6px rgba(1, 83, 169, .12), 0 8px 20px rgba(1, 83, 169, .25);
        transform: scale(1.08);
    }
    .step-current .step-label {
        color: var(--azia-primary);
        font-weight: 700;
    }
    .step-current .step-circle::after {
        content: '';
        position: absolute;
        inset: -4px;
        border-radius: 50%;
        border: 2px solid rgba(1, 83, 169, .3);
        animation: stepPulse 2s ease-out infinite;
    }
    @keyframes stepPulse {
        0%   { transform: scale(1);   opacity: 1; }
        100% { transform: scale(1.4); opacity: 0; }
    }

    /* Done state (step Hoàn thành đang là current) — đổi sang xanh success */
    .step-current.step-done .step-circle {
        background: linear-gradient(135deg, var(--azia-success) 0%, #1aa37e 100%);
        border-color: var(--azia-success);
        box-shadow: 0 0 0 6px rgba(36, 211, 159, .15), 0 8px 20px rgba(36, 211, 159, .25);
    }
    .step-current.step-done .step-label { color: var(--azia-success); }
    .step-current.step-done .step-circle::after { border-color: rgba(36, 211, 159, .3); }

    /* Connector line giữa các step */
    .step-connector {
        flex: 1;
        height: 3px;
        background: var(--azia-border);
        margin: 21px -10px 0;       /* căn giữa với circle 44px (44/2 ~ 22px) */
        position: relative;
        z-index: 1;
        border-radius: 2px;
        overflow: hidden;
    }
    .step-connector::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, var(--azia-primary), #4a8fd9);
        transform-origin: left;
        transform: scaleX(0);
        transition: transform .5s cubic-bezier(.2, .8, .3, 1);
    }
    .step-connector.is-filled::after { transform: scaleX(1); }
    /* Connector đến step "done" → xanh success */
    .step-connector.is-filled.to-done::after {
        background: linear-gradient(90deg, var(--azia-primary), var(--azia-success));
    }

    /* ========== Comments thread ========== */
    .comment-list { padding: 0; }
    .comment-item {
        display: flex; gap: 12px;
        padding: 14px 18px;
        border-bottom: 1px solid var(--azia-border);
    }
    .comment-item:last-child { border-bottom: none; }
    .comment-av {
        width: 36px; height: 36px; border-radius: 50%;
        background: var(--azia-primary); color: #fff;
        display: inline-flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 13px;
        flex-shrink: 0;
    }
    .comment-body { flex: 1; min-width: 0; }
    .comment-head {
        display: flex; justify-content: space-between; align-items: baseline;
        margin-bottom: 4px;
    }
    .comment-name { font-weight: 600; font-size: 13.5px; color: var(--azia-text); }
    .comment-time { font-size: 11px; color: var(--azia-muted); }
    .comment-text {
        font-size: 13.5px; line-height: 1.55;
        color: var(--azia-text);
        white-space: pre-wrap;
        word-break: break-word;
    }
    .comment-text .mention {
        background: var(--azia-primary-soft);
        color: var(--azia-primary);
        padding: 1px 6px;
        border-radius: 6px;
        font-weight: 600;
    }
    .comment-form {
        padding: 14px 18px;
        background: #fafbfd;
        border-top: 1px solid var(--azia-border);
        position: relative;
    }
    .comment-form textarea {
        resize: none;
        font-size: 13.5px;
    }
    .comment-empty {
        text-align: center;
        padding: 30px 16px;
        color: var(--azia-muted);
    }
    .comment-actions {
        margin-top: 4px;
        font-size: 12px;
    }
    .comment-actions .btn-reply {
        background: none; border: none; padding: 0;
        color: var(--azia-muted); font-weight: 600;
        font-size: 12px;
    }
    .comment-actions .btn-reply:hover { color: var(--azia-primary); }

    /* Replies block — indent left + thin guide line */
    .replies-block {
        margin-top: 10px;
        padding-left: 16px;
        border-left: 2px solid var(--azia-border);
    }
    .replies-block .comment-item {
        padding: 10px 0 10px 14px;
        border-bottom: 1px dashed #f0f3fa;
    }
    .replies-block .comment-item:last-child { border-bottom: none; }
    .replies-block .comment-av {
        width: 28px; height: 28px;
        font-size: 11px;
    }
    .reply-item.is-collapsed { display: none; }

    .btn-show-older-replies {
        background: transparent;
        border: none;
        color: var(--azia-primary);
        padding: 4px 10px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        border-radius: 6px;
        margin-bottom: 6px;
        display: inline-flex; align-items: center;
        transition: background .12s;
    }
    .btn-show-older-replies:hover {
        background: var(--azia-primary-soft);
    }

    /* Inline reply form */
    .reply-form-wrap {
        margin-top: 10px;
        padding-left: 16px;
        border-left: 2px solid var(--azia-primary-soft);
        display: none;
    }
    .reply-form-wrap.is-open { display: block; }
    .reply-form-wrap form { padding-left: 14px; }
    .reply-form-wrap textarea {
        font-size: 13px;
        resize: none;
    }

    /* Mention picker dropdown */
    .mention-picker {
        position: absolute;
        background: #fff;
        border: 1px solid var(--azia-border);
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(28,39,60,.12);
        max-height: 200px;
        overflow-y: auto;
        min-width: 220px;
        z-index: 1000;
        padding: 4px;
    }
    .mention-item {
        display: flex; align-items: center; gap: 10px;
        padding: 8px 10px;
        border-radius: 7px;
        cursor: pointer;
        font-size: 13.5px;
    }
    .mention-item.active, .mention-item:hover {
        background: var(--azia-primary-soft);
        color: var(--azia-primary);
    }
    .mention-item .av-sm {
        width: 26px; height: 26px; border-radius: 50%;
        background: var(--azia-primary); color: #fff;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 700;
    }

    /* ========== Inline title edit (header) ========== */
    .title-editable {
        display: inline-flex; align-items: center; gap: 8px;
        cursor: pointer;
        position: relative;
        padding: 2px 8px;
        border-radius: 8px;
        transition: background .12s;
    }
    .title-editable:hover {
        background: rgba(255,255,255,.12);
    }
    .title-editable .edit-icon {
        opacity: 0;
        font-size: 16px;
        transition: opacity .12s;
        color: rgba(255,255,255,.7);
    }
    .title-editable:hover .edit-icon { opacity: 1; }

    /* Inline title input mode — wrap container layout flex để nút luôn show */
    .title-edit-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;       /* mobile sẽ wrap xuống dòng */
        width: 100%;
    }
    .title-input {
        background: rgba(255,255,255,.18);
        border: 1px solid rgba(255,255,255,.35);
        color: #fff;
        font-size: 22px; font-weight: 700;
        padding: 6px 12px;
        border-radius: 8px;
        outline: none;
        flex: 1 1 280px;       /* grow + shrink, min 280px */
        min-width: 240px;
        max-width: 600px;
    }
    .title-input::placeholder { color: rgba(255,255,255,.5); }
    .title-input:focus {
        background: rgba(255,255,255,.25);
        border-color: rgba(255,255,255,.6);
        box-shadow: 0 0 0 3px rgba(255,255,255,.15);
    }
    .title-edit-actions {
        display: inline-flex;
        gap: 6px;
        flex-shrink: 0;        /* không cho ép — nút luôn show */
    }
    .title-edit-actions .btn {
        padding: 8px 16px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 8px;
        white-space: nowrap;
    }
    .title-edit-actions .btn-save {
        background: #fff;
        border: 1px solid #fff;
        color: var(--azia-primary);
    }
    .title-edit-actions .btn-save:hover {
        background: #f4f5f8; color: var(--azia-primary);
    }
    .title-edit-actions .btn-cancel {
        background: transparent;
        border: 1px solid rgba(255,255,255,.5);
        color: #fff;
    }
    .title-edit-actions .btn-cancel:hover {
        background: rgba(255,255,255,.12);
    }
</style>
@endpush

@section('content')
    {{-- Header --}}
    <div class="task-detail-header">
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
            <div style="flex: 1; min-width: 0;">
                <nav class="breadcrumb" style="position:relative;z-index:1">
                    <a href="{{ route('shipments.index') }}" class="text-white opacity-75">Trang chủ</a>
                    <span class="mx-2 opacity-75">/</span>
                    <a href="{{ route('tasks.index') }}" class="text-white opacity-75">Công việc</a>
                    <span class="mx-2 opacity-75">/</span>
                    <span class="opacity-75">#{{ $task->id }}</span>
                </nav>
                @if($canEdit)
                    <h1 class="title-editable" id="taskTitleWrap" title="Click để sửa tên task"
                        data-update-url="{{ route('tasks.update', $task) }}"
                        data-original="{{ $task->title }}">
                        <span id="taskTitleText">{{ $task->title }}</span>
                        <i class="bi bi-pencil edit-icon"></i>
                    </h1>
                @else
                    <h1>{{ $task->title }}</h1>
                @endif
                <div class="meta">
                    <span><i class="bi bi-person-circle"></i> {{ $task->creator?->name ?? '—' }}</span>
                    <span><i class="bi bi-clock-history"></i> {{ $task->created_at->format('d/m/Y H:i') }}</span>
                    @if($task->due_at)
                        <span class="{{ $overdue ? 'text-warning' : '' }}">
                            <i class="bi bi-alarm{{ $overdue ? '-fill' : '' }}"></i>
                            Hạn: {{ $task->due_at->format('d/m/Y H:i') }}
                            @if($overdue) (quá hạn) @endif
                        </span>
                    @endif
                </div>
            </div>
            <div class="d-flex flex-column align-items-end gap-2" style="position:relative;z-index:1">
                <span class="status-pill">
                    <i class="bi bi-{{ $statusOptions[$task->status]['icon'] }}"></i>
                    {{ $statusOptions[$task->status]['lbl'] }}
                </span>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- Main column --}}
        <div class="col-lg-8">
            {{-- Body --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-file-text me-1" style="color: var(--azia-primary)"></i>
                        Nội dung
                    </div>
                    @if($canEdit)
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editTaskModal">
                            <i class="bi bi-pencil me-1"></i> Sửa
                        </button>
                    @endif
                </div>
                <div class="card-body">
                    @if($task->body)
                        <div class="body-block">{{ $task->body }}</div>
                    @else
                        <p class="text-muted small mb-0 fst-italic">Không có ghi chú chi tiết.</p>
                    @endif
                </div>
            </div>

            {{-- Status — progress stepper --}}
            @if($canEdit)
            @php
                // Index hiện tại trong sequence todo → doing → done
                $statusKeys = array_keys($statusOptions);    // ['todo','doing','done']
                $currentIdx = array_search($task->status, $statusKeys, true);
            @endphp
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-lightning-charge me-1" style="color: var(--azia-primary)"></i>
                    Tiến trình
                </div>
                <div class="card-body">
                    <div class="status-stepper">
                        @foreach($statusOptions as $key => $opt)
                            @php
                                $idx = array_search($key, $statusKeys, true);
                                $isPassed  = $idx < $currentIdx;
                                $isCurrent = $idx === $currentIdx;
                                $stepClass = $isCurrent ? 'step-current' : ($isPassed ? 'step-passed' : '');
                                if ($isCurrent && $key === 'done') $stepClass .= ' step-done';
                                // Icon: đã qua = check, current = icon theo trạng thái, chưa tới = số/icon mờ
                                $icon = $isPassed ? 'check-lg' : $opt['icon'];
                            @endphp
                            <form method="POST" action="{{ route('tasks.toggleStatus', $task) }}"
                                  class="step-form {{ $stepClass }}"
                                  data-task-title="{{ $task->title }}"
                                  data-needs-confirm="{{ $key === 'done' ? '1' : '0' }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="status" value="{{ $key }}">
                                <button type="submit" class="step-btn"
                                        {{ $isCurrent ? 'disabled' : '' }}
                                        title="{{ $isCurrent ? 'Đang ở trạng thái này' : 'Chuyển sang: ' . $opt['lbl'] }}">
                                    <span class="step-circle">
                                        <i class="bi bi-{{ $icon }}"></i>
                                    </span>
                                    <span class="step-label">{{ $opt['lbl'] }}</span>
                                </button>
                            </form>
                            @if(! $loop->last)
                                @php
                                    $connFilled = $idx < $currentIdx;
                                    $connToDone = $idx + 1 === array_search('done', $statusKeys, true);
                                @endphp
                                <span class="step-connector {{ $connFilled ? 'is-filled' : '' }} {{ $connFilled && $connToDone ? 'to-done' : '' }}"></span>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            {{-- ============ COMMENTS THREAD ============ --}}
            @php
                $totalComments = $task->allComments()->count();
                $canComment = auth()->user()->can('tasks.manage_all')
                    || $task->created_by === auth()->id()
                    || $task->assignees->contains('id', auth()->id())
                    || $task->watchers->contains('id', auth()->id());
            @endphp
            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-chat-square-text me-1" style="color: var(--azia-primary)"></i>
                        Bình luận
                        @if($totalComments > 0)
                            <span class="badge badge-soft-primary ms-2">{{ $totalComments }}</span>
                        @endif
                    </div>
                </div>

                <div class="comment-list">
                    @if(($hiddenCount ?? 0) > 0)
                        <a href="{{ route('tasks.show', $task) }}?all_comments=1" class="d-block text-center py-2 small text-decoration-none"
                           style="background:#fafbfd; color:var(--azia-primary); font-weight:600; border-bottom:1px solid var(--azia-border);">
                            <i class="bi bi-arrow-up-circle me-1"></i>
                            Xem {{ $hiddenCount }} bình luận cũ hơn (tổng {{ $totalTopLevel }})
                        </a>
                    @endif

                    @forelse($task->comments as $c)
                        <div class="comment-item" id="comment-{{ $c->id }}">
                            <span class="comment-av">{{ strtoupper(mb_substr($c->author?->name ?? 'U', 0, 1)) }}</span>
                            <div class="comment-body">
                                <div class="comment-head">
                                    <span class="comment-name">{{ $c->author?->name ?? 'Người dùng đã xoá' }}</span>
                                    <span class="comment-time" title="{{ $c->created_at->format('d/m/Y H:i') }}">
                                        {{ $c->created_at->diffForHumans() }}
                                    </span>
                                </div>
                                <div class="comment-text">{!! $c->renderedBody() !!}</div>

                                @if($canComment)
                                <div class="comment-actions">
                                    <button type="button" class="btn-reply"
                                            data-reply-target="reply-{{ $c->id }}"
                                            data-mention-name="{{ $c->author?->name }}">
                                        <i class="bi bi-reply"></i> Trả lời
                                    </button>
                                </div>
                                @endif

                                {{-- Replies (flat thread). Hiển thị MẶC ĐỊNH 5 mới nhất; nếu nhiều hơn → collapse --}}
                                @php
                                    $repliesAll = $c->replies;
                                    $maxRepliesVisible = \App\Models\Task::REPLIES_PAGE_SIZE;
                                    $hiddenReplies = max(0, $repliesAll->count() - $maxRepliesVisible);
                                @endphp
                                @if($repliesAll->isNotEmpty())
                                    <div class="replies-block" data-replies-of="{{ $c->id }}">
                                        @if($hiddenReplies > 0)
                                            <button type="button" class="btn-show-older-replies small text-decoration-none"
                                                    data-target="replies-of-{{ $c->id }}">
                                                <i class="bi bi-chevron-down me-1"></i>
                                                Xem {{ $hiddenReplies }} trả lời cũ hơn
                                            </button>
                                        @endif

                                        @foreach($repliesAll as $idx => $r)
                                            @php
                                                $isHidden = $hiddenReplies > 0 && $idx < $hiddenReplies;
                                            @endphp
                                            <div class="comment-item reply-item {{ $isHidden ? 'is-collapsed' : '' }}"
                                                 id="comment-{{ $r->id }}"
                                                 data-replies-of-{{ $c->id }}>
                                                <span class="comment-av">{{ strtoupper(mb_substr($r->author?->name ?? 'U', 0, 1)) }}</span>
                                                <div class="comment-body">
                                                    <div class="comment-head">
                                                        <span class="comment-name">{{ $r->author?->name ?? 'Người dùng đã xoá' }}</span>
                                                        <span class="comment-time" title="{{ $r->created_at->format('d/m/Y H:i') }}">
                                                            {{ $r->created_at->diffForHumans() }}
                                                        </span>
                                                    </div>
                                                    <div class="comment-text">{!! $r->renderedBody() !!}</div>
                                                    @if($canComment)
                                                    <div class="comment-actions">
                                                        <button type="button" class="btn-reply"
                                                                data-reply-target="reply-{{ $c->id }}"
                                                                data-mention-name="{{ $r->author?->name }}">
                                                            <i class="bi bi-reply"></i> Trả lời
                                                        </button>
                                                    </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Inline reply form (ẩn mặc định) --}}
                                @if($canComment)
                                <div class="reply-form-wrap" id="reply-{{ $c->id }}">
                                    <form method="POST" action="{{ route('tasks.comments.store', $task) }}" class="comment-reply-form mt-2">
                                        @csrf
                                        <input type="hidden" name="parent_id" value="{{ $c->id }}">
                                        <textarea name="body" class="form-control reply-textarea" rows="2" required
                                                  placeholder="Trả lời… Gõ @ để tag đồng nghiệp"></textarea>
                                        <div class="d-flex justify-content-end gap-2 mt-2">
                                            <button type="button" class="btn btn-sm btn-light btn-cancel-reply">Huỷ</button>
                                            <button type="submit" class="btn btn-sm btn-primary reply-submit-btn">
                                                <i class="bi bi-send me-1"></i> Gửi trả lời
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="comment-empty">
                            <i class="bi bi-chat-square" style="font-size:28px; opacity:.3"></i>
                            <div class="small mt-2">Chưa có bình luận. Hãy là người đầu tiên góp ý.</div>
                        </div>
                    @endforelse
                </div>

                @if($canComment)
                <form method="POST" action="{{ route('tasks.comments.store', $task) }}" class="comment-form" id="commentForm">
                    @csrf
                    <textarea name="body" class="form-control" rows="2" required
                              placeholder="Viết bình luận… Gõ @ để tag đồng nghiệp" id="commentBody"></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            Tag người: gõ <code>@</code> rồi chọn từ danh sách.
                            Người được tag sẽ nhận thông báo.
                        </small>
                        <button type="submit" class="btn btn-sm btn-primary" id="commentSubmitBtn">
                            <i class="bi bi-send me-1"></i> Gửi bình luận
                        </button>
                    </div>
                </form>
                @endif
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            {{-- ========= LỊCH NHẮC — inline editable ========= --}}
            @php
                $remindLabel = function ($n) {
                    if ($n === null) return '—';
                    if ($n === 0) return 'Đúng giờ hạn';
                    if ($n < 60)   return $n . ' phút trước';
                    if ($n < 1440) return intdiv($n, 60) . ' giờ trước';
                    return intdiv($n, 1440) . ' ngày trước';
                };
            @endphp
            <div class="card mb-3" id="dueCard">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-alarm me-1" style="color: var(--azia-primary)"></i> Lịch nhắc
                    </div>
                    @if($canEdit)
                        <button type="button" class="btn btn-sm btn-outline-secondary border-0" id="btnEditDue" title="Sửa hạn / nhắc trước">
                            <i class="bi bi-pencil"></i>
                        </button>
                    @endif
                </div>
                <div class="card-body">
                    {{-- VIEW MODE --}}
                    <div id="dueView">
                        <div class="info-row">
                            <span class="label"><i class="bi bi-{{ $overdue ? 'alarm-fill text-danger' : 'clock' }}"></i> Hạn chót</span>
                            <span class="value {{ $overdue ? 'text-danger' : '' }}">
                                {{ $task->due_at ? $task->due_at->format('d/m/Y H:i') : '—' }}
                            </span>
                        </div>
                        @if($task->due_at)
                            <div class="info-row">
                                <span class="label"><i class="bi bi-bell"></i> Nhắc trước</span>
                                <span class="value">{{ $remindLabel($task->remind_before) }}</span>
                            </div>
                            <div class="info-row">
                                <span class="label"><i class="bi bi-hourglass-split"></i> Còn lại</span>
                                <span class="value {{ $overdue ? 'text-danger fw-bold' : '' }}">
                                    {{ $overdue ? 'Quá hạn ' . $task->due_at->diffForHumans(now(), true) : $task->due_at->diffForHumans() }}
                                </span>
                            </div>
                        @endif
                    </div>

                    {{-- EDIT MODE (ẩn mặc định) --}}
                    @if($canEdit)
                        <div id="dueEdit" class="d-none">
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Hạn chót</label>
                                <input type="text" id="dueEditInput" class="form-control js-datetimepicker"
                                       placeholder="Chọn ngày & giờ…"
                                       value="{{ $task->due_at?->format('Y-m-d H:i') }}">
                                <small class="text-muted">Để trống nếu không cần hạn</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Nhắc trước</label>
                                <select id="remindEditSelect" class="form-select">
                                    <option value="">Không nhắc</option>
                                    <option value="0"    {{ $task->remind_before === 0    ? 'selected' : '' }}>Đúng giờ hạn</option>
                                    <option value="15"   {{ $task->remind_before === 15   ? 'selected' : '' }}>15 phút trước</option>
                                    <option value="30"   {{ $task->remind_before === 30   ? 'selected' : '' }}>30 phút trước</option>
                                    <option value="60"   {{ $task->remind_before === 60   ? 'selected' : '' }}>1 giờ trước</option>
                                    <option value="240"  {{ $task->remind_before === 240  ? 'selected' : '' }}>4 giờ trước</option>
                                    <option value="1440" {{ $task->remind_before === 1440 ? 'selected' : '' }}>1 ngày trước</option>
                                    <option value="2880" {{ $task->remind_before === 2880 ? 'selected' : '' }}>2 ngày trước</option>
                                    <option value="10080"{{ $task->remind_before === 10080? 'selected' : '' }}>1 tuần trước</option>
                                </select>
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <button type="button" class="btn btn-sm btn-primary flex-fill" id="btnSaveDue">
                                    <i class="bi bi-check2 me-1"></i> Lưu
                                </button>
                                <button type="button" class="btn btn-sm btn-light" id="btnCancelDue">Huỷ</button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ========= CHI TIẾT khác — readonly ========= --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-info-square me-1" style="color: var(--azia-primary)"></i> Chi tiết
                    </div>
                    @if($canEdit)
                        <button type="button" class="btn btn-sm btn-outline-secondary border-0" id="btnEditPriority" title="Đổi độ ưu tiên">
                            <i class="bi bi-pencil"></i>
                        </button>
                    @endif
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <span class="label">Trạng thái</span>
                        <span class="value">
                            <span class="badge badge-soft-{{ $statusOptions[$task->status]['color'] }}">
                                {{ $statusOptions[$task->status]['lbl'] }}
                            </span>
                        </span>
                    </div>
                    <div class="info-row" id="priorityView">
                        <span class="label">Độ ưu tiên</span>
                        <span class="value">
                            <span class="badge badge-soft-{{ $priorityOptions[$task->priority]['color'] }}">
                                <i class="bi bi-flag-fill"></i> {{ $priorityOptions[$task->priority]['lbl'] }}
                            </span>
                        </span>
                    </div>
                    @if($canEdit)
                        <div id="priorityEdit" class="d-none mb-2">
                            <label class="form-label fw-semibold small mb-1">Đổi độ ưu tiên</label>
                            <select id="priorityEditSelect" class="form-select form-select-sm">
                                @foreach($priorityOptions as $k => $o)
                                    <option value="{{ $k }}" {{ $task->priority === $k ? 'selected' : '' }}>{{ $o['lbl'] }}</option>
                                @endforeach
                            </select>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-sm btn-primary flex-fill" id="btnSavePriority">
                                    <i class="bi bi-check2 me-1"></i> Lưu
                                </button>
                                <button type="button" class="btn btn-sm btn-light" id="btnCancelPriority">Huỷ</button>
                            </div>
                        </div>
                    @endif
                    @if($task->completed_at)
                        <div class="info-row">
                            <span class="label">Hoàn thành lúc</span>
                            <span class="value">{{ $task->completed_at->format('d/m/Y H:i') }}</span>
                        </div>
                    @endif
                    <div class="info-row">
                        <span class="label">Người tạo</span>
                        <span class="value">{{ $task->creator?->name ?? '—' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Tạo lúc</span>
                        <span class="value small text-muted">{{ $task->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-people me-1" style="color: var(--azia-primary)"></i> Được giao
                    </div>
                    @if($canEdit && auth()->user()->can('tasks.assign_others'))
                        <button type="button" class="btn btn-sm btn-outline-secondary border-0" id="btnEditAssignees" title="Sửa người được giao">
                            <i class="bi bi-pencil"></i>
                        </button>
                    @endif
                </div>
                <div class="card-body">
                    {{-- VIEW MODE --}}
                    <div id="assigneesView">
                        @if($task->assignees->count())
                            <div class="av-list">
                                @foreach($task->assignees as $a)
                                    <span class="d-inline-flex align-items-center gap-2 mb-2 me-2">
                                        <span class="av">{{ strtoupper(mb_substr($a->name, 0, 1)) }}</span>
                                        <span class="small fw-semibold">{{ $a->name }}</span>
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted small mb-0 fst-italic">Chưa giao cho ai.</p>
                        @endif
                    </div>

                    {{-- EDIT MODE (ẩn ban đầu) --}}
                    @if($canEdit && auth()->user()->can('tasks.assign_others'))
                        <div id="assigneesEdit" class="d-none">
                            <select id="assigneesEditSelect" multiple class="form-select"
                                    data-placeholder="Chọn người được giao…">
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" {{ $task->assignees->contains('id', $u->id) ? 'selected' : '' }}>{{ $u->name }}</option>
                                @endforeach
                            </select>
                            <div class="d-flex justify-content-end gap-2 mt-2">
                                <button type="button" class="btn btn-sm btn-light" id="btnCancelAssignees">Huỷ</button>
                                <button type="button" class="btn btn-sm btn-primary" id="btnSaveAssignees">
                                    <i class="bi bi-check2 me-1"></i> Lưu
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            @if($canDelete)
            <form method="POST" action="{{ route('tasks.destroy', $task) }}"
                  onsubmit="return confirmDelete(this, {title:'Xoá task?', text: 'Task <b>{{ $task->title }}</b> sẽ bị xoá vĩnh viễn.'})">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-outline-danger w-100">
                    <i class="bi bi-trash me-1"></i> Xoá task này
                </button>
            </form>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
    // ---- Helper chung: PUT update task với partial fields ----
    const TASK_UPDATE_URL = @json(route('tasks.update', $task));
    const TASK_TITLE = @json($task->title);
    const CSRF_TOK = document.querySelector('meta[name="csrf-token"]').content;

    async function updateTaskField(payload) {
        const fd = new URLSearchParams();
        fd.append('title', TASK_TITLE);   // validate yêu cầu title — luôn gửi kèm
        for (const [k, v] of Object.entries(payload)) {
            if (v === null || v === undefined) {
                fd.append(k, '');         // gửi rỗng để clear (Laravel nullable)
            } else {
                fd.append(k, v);
            }
        }
        const res = await fetch(TASK_UPDATE_URL, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': CSRF_TOK,
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: fd.toString(),
            credentials: 'same-origin',
        });
        if (! res.ok) throw new Error('HTTP ' + res.status);
        return res;
    }

    function showErrorAlert() {
        Swal && Swal.fire({
            ...APP_SWAL, icon: 'error',
            title: 'Lưu thất bại',
            html: 'Không cập nhật được. Thử lại sau.',
            showCancelButton: false, confirmButtonText: 'Đóng',
        });
    }

    // ---- Inline edit Lịch nhắc (deadline + remind_before) ----
    (function () {
        const $btnEdit   = document.getElementById('btnEditDue');
        const $view      = document.getElementById('dueView');
        const $edit      = document.getElementById('dueEdit');
        const $btnSave   = document.getElementById('btnSaveDue');
        const $btnCancel = document.getElementById('btnCancelDue');
        if (! $btnEdit || ! $view || ! $edit) return;

        $btnEdit.addEventListener('click', () => {
            $view.classList.add('d-none');
            $edit.classList.remove('d-none');
        });

        $btnCancel.addEventListener('click', () => {
            $edit.classList.add('d-none');
            $view.classList.remove('d-none');
        });

        $btnSave.addEventListener('click', async () => {
            const dueInput = document.getElementById('dueEditInput');
            const remindSel = document.getElementById('remindEditSelect');
            $btnSave.disabled = true;
            $btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Đang lưu';
            try {
                await updateTaskField({
                    due_at:        dueInput.value || null,
                    remind_before: remindSel.value === '' ? null : remindSel.value,
                });
                // Reload để render lại view với data mới (giữ đơn giản, đảm bảo nhất quán)
                window.location.reload();
            } catch (e) {
                $btnSave.disabled = false;
                $btnSave.innerHTML = '<i class="bi bi-check2 me-1"></i> Lưu';
                showErrorAlert();
            }
        });
    })();

    // ---- Inline edit Độ ưu tiên ----
    (function () {
        const $btnEdit   = document.getElementById('btnEditPriority');
        const $view      = document.getElementById('priorityView');
        const $edit      = document.getElementById('priorityEdit');
        const $btnSave   = document.getElementById('btnSavePriority');
        const $btnCancel = document.getElementById('btnCancelPriority');
        if (! $btnEdit || ! $view || ! $edit) return;

        $btnEdit.addEventListener('click', () => {
            $view.classList.add('d-none');
            $edit.classList.remove('d-none');
        });

        $btnCancel.addEventListener('click', () => {
            $edit.classList.add('d-none');
            $view.classList.remove('d-none');
        });

        $btnSave.addEventListener('click', async () => {
            const sel = document.getElementById('priorityEditSelect');
            $btnSave.disabled = true;
            $btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
            try {
                await updateTaskField({ priority: sel.value });
                window.location.reload();
            } catch (e) {
                $btnSave.disabled = false;
                $btnSave.innerHTML = '<i class="bi bi-check2 me-1"></i> Lưu';
                showErrorAlert();
            }
        });
    })();

    // ---- Confirm SweetAlert khi click bước "Hoàn thành" trong stepper ----
    document.querySelectorAll('.step-form[data-needs-confirm="1"]').forEach(($form) => {
        $form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const title = $form.dataset.taskTitle || 'task này';
            const ok = await confirmAction({
                icon: 'question',
                title: 'Đã hoàn thành task?',
                text: `Đánh dấu <b>"${title.replace(/</g,'&lt;')}"</b> là <b>Hoàn thành</b>?`,
                confirmText: '<i class="bi bi-check2-circle me-1"></i> Đã xong',
            });
            if (ok) {
                const $btn = $form.querySelector('button[type=submit]');
                if ($btn) $btn.disabled = true;
                $form.submit();
            }
        });
    });

    // ---- Inline edit assignees (card "Được giao") ----
    (function () {
        const $btnEdit   = document.getElementById('btnEditAssignees');
        const $view      = document.getElementById('assigneesView');
        const $edit      = document.getElementById('assigneesEdit');
        const $select    = document.getElementById('assigneesEditSelect');
        const $btnCancel = document.getElementById('btnCancelAssignees');
        const $btnSave   = document.getElementById('btnSaveAssignees');
        if (! $btnEdit || ! $view || ! $edit || ! $select) return;

        const updateUrl = @json(route('tasks.update', $task));
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        let select2Initialized = false;
        let originalIds = [...$select.querySelectorAll('option[selected]')].map(o => o.value);

        const enter = () => {
            $view.classList.add('d-none');
            $edit.classList.remove('d-none');
            if (! select2Initialized && window.initUserSelect2) {
                window.initUserSelect2($select);
                select2Initialized = true;
            }
        };
        const leave = () => {
            $edit.classList.add('d-none');
            $view.classList.remove('d-none');
        };

        const renderAssignees = (assignees) => {
            if (! assignees.length) {
                $view.innerHTML = '<p class="text-muted small mb-0 fst-italic">Chưa giao cho ai.</p>';
                return;
            }
            $view.innerHTML = '<div class="av-list">' + assignees.map(a => `
                <span class="d-inline-flex align-items-center gap-2 mb-2 me-2">
                    <span class="av">${a.name.charAt(0).toUpperCase()}</span>
                    <span class="small fw-semibold">${a.name.replace(/</g,'&lt;')}</span>
                </span>
            `).join('') + '</div>';
        };

        $btnEdit.addEventListener('click', enter);
        $btnCancel.addEventListener('click', () => {
            // Reset selection về giá trị gốc
            $($select).val(originalIds).trigger('change');
            leave();
        });

        $btnSave.addEventListener('click', async () => {
            const ids = $($select).val() || [];
            $btnSave.disabled = true;
            $btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Đang lưu';

            try {
                const fd = new URLSearchParams();
                ids.forEach(id => fd.append('assignees[]', id));
                // Title bắt buộc — không gửi → validate fail. Gửi lại title hiện tại để pass.
                fd.append('title', @json($task->title));

                const res = await fetch(updateUrl, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                    },
                    body: fd.toString(),
                    credentials: 'same-origin',
                });
                if (! res.ok) throw new Error('HTTP ' + res.status);

                // Build mới list assignees từ select options
                const newAssignees = ids.map(id => {
                    const opt = $select.querySelector(`option[value="${id}"]`);
                    return { id: id, name: opt ? opt.textContent : '?' };
                });
                renderAssignees(newAssignees);
                originalIds = ids;
                leave();
            } catch (e) {
                Swal && Swal.fire({
                    ...APP_SWAL, icon: 'error',
                    title: 'Lưu thất bại',
                    html: 'Không cập nhật được người được giao. Thử lại sau.',
                    showCancelButton: false, confirmButtonText: 'Đóng',
                });
            } finally {
                $btnSave.disabled = false;
                $btnSave.innerHTML = '<i class="bi bi-check2 me-1"></i> Lưu';
            }
        });
    })();

    // ---- Inline edit title (click vào title trong header → input ----
    (function () {
        const $wrap = document.getElementById('taskTitleWrap');
        if (! $wrap) return;
        const updateUrl = $wrap.dataset.updateUrl;
        const csrf = document.querySelector('meta[name="csrf-token"]').content;

        const enterEditMode = () => {
            if ($wrap.dataset.editing === '1') return;
            $wrap.dataset.editing = '1';

            const current = document.getElementById('taskTitleText').textContent.trim();
            $wrap.innerHTML = `
                <div class="title-edit-wrap">
                    <input type="text" class="title-input"
                           value="${current.replace(/"/g, '&quot;')}"
                           maxlength="255"
                           placeholder="Nhập tên task…">
                    <span class="title-edit-actions">
                        <button type="button" class="btn btn-save" data-action="save">
                            <i class="bi bi-check2-circle me-1"></i>Lưu
                        </button>
                        <button type="button" class="btn btn-cancel" data-action="cancel">
                            <i class="bi bi-x-lg me-1"></i>Huỷ
                        </button>
                    </span>
                </div>
            `;
            const $input = $wrap.querySelector('input');
            $input.focus();
            $input.setSelectionRange(0, $input.value.length);

            $input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); save(); }
                else if (e.key === 'Escape') { e.stopPropagation(); cancel(); }
            });
            // stopPropagation để click button không bubble lên $wrap (gây enterEditMode lại)
            $wrap.querySelector('[data-action=save]').addEventListener('click', (e) => {
                e.stopPropagation();
                save();
            });
            $wrap.querySelector('[data-action=cancel]').addEventListener('click', (e) => {
                e.stopPropagation();
                cancel();
            });
            // Click vào input cũng đừng bubble
            $input.addEventListener('click', (e) => e.stopPropagation());
        };

        const renderTitle = (title) => {
            $wrap.dataset.editing = '0';
            $wrap.dataset.original = title;
            $wrap.innerHTML = `
                <span id="taskTitleText">${title.replace(/</g,'&lt;')}</span>
                <i class="bi bi-pencil edit-icon"></i>
            `;
        };

        const cancel = () => renderTitle($wrap.dataset.original);

        const save = async () => {
            const $input = $wrap.querySelector('input');
            const $saveBtn = $wrap.querySelector('[data-action=save]');
            const newTitle = $input.value.trim();
            if (! newTitle) { $input.focus(); return; }
            if (newTitle === $wrap.dataset.original) { cancel(); return; }

            $saveBtn.disabled = true;
            $saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
            $input.disabled = true;

            try {
                const res = await fetch(updateUrl, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                    },
                    body: 'title=' + encodeURIComponent(newTitle),
                    credentials: 'same-origin',
                });
                if (! res.ok) throw new Error('HTTP ' + res.status);
                renderTitle(newTitle);
                // Cập nhật title tab trình duyệt
                document.title = document.title.replace(/^[^—·]+/, 'Task ');
            } catch (e) {
                $input.disabled = false;
                $saveBtn.disabled = false;
                $saveBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Lưu';
                Swal && Swal.fire({
                    ...APP_SWAL, icon: 'error',
                    title: 'Lưu thất bại', html: 'Không cập nhật được. Thử lại sau.',
                    showCancelButton: false, confirmButtonText: 'Đóng',
                });
            }
        };

        $wrap.addEventListener('click', (e) => {
            if ($wrap.dataset.editing === '1') return;
            enterEditMode();
        });
    })();

    // ---- Form submit loading: cho mọi form trong card bình luận ----
    (function () {
        document.querySelectorAll('#commentForm, .comment-reply-form').forEach(($form) => {
            const $btn = $form.querySelector('button[type=submit]');
            if (! $btn) return;
            const origHTML = $btn.innerHTML;

            $form.addEventListener('submit', (e) => {
                if ($btn.disabled) { e.preventDefault(); return; }
                $btn.disabled = true;
                $btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Đang gửi…';
            });
        });
    })();

    // ---- Toggle collapse cũ hơn cho replies (scale UI khi thread dài) ----
    document.querySelectorAll('.btn-show-older-replies').forEach(($btn) => {
        $btn.addEventListener('click', () => {
            const $block = $btn.closest('.replies-block');
            if (! $block) return;
            $block.querySelectorAll('.reply-item.is-collapsed').forEach((el) => {
                el.classList.remove('is-collapsed');
            });
            $btn.remove();   // ẩn nút sau khi expand
        });
    });

    // ---- Toggle reply form: click "Trả lời" → mở form tương ứng, auto prefill @Tên ----
    (function () {
        document.querySelectorAll('.btn-reply').forEach(($btn) => {
            $btn.addEventListener('click', () => {
                const targetId = $btn.dataset.replyTarget;
                const mentionName = $btn.dataset.mentionName;
                const $wrap = document.getElementById(targetId);
                if (! $wrap) return;

                // Đóng các reply form khác trên page để màn hình gọn
                document.querySelectorAll('.reply-form-wrap.is-open').forEach((w) => {
                    if (w !== $wrap) w.classList.remove('is-open');
                });

                $wrap.classList.add('is-open');
                const $ta = $wrap.querySelector('textarea.reply-textarea');
                if (! $ta) return;

                // Prefill @Tên nếu textarea trống và có tên user để tag
                if (mentionName && $ta.value.trim() === '') {
                    $ta.value = '@' + mentionName + ' ';
                }
                $ta.focus();
                $ta.setSelectionRange($ta.value.length, $ta.value.length);
            });
        });

        // Huỷ → đóng wrap
        document.querySelectorAll('.btn-cancel-reply').forEach(($btn) => {
            $btn.addEventListener('click', () => {
                const $wrap = $btn.closest('.reply-form-wrap');
                if ($wrap) $wrap.classList.remove('is-open');
            });
        });
    })();

    // ---- @mention picker: gắn vào MỌI textarea trong card bình luận (cả main + reply) ----
    (function () {
        const searchUrl = @json(route('users.search'));
        const textareas = document.querySelectorAll('#commentBody, .reply-textarea');
        if (! textareas.length) return;

        // 1 picker dùng chung, bám theo textarea đang active
        let picker = null;
        let activeTa = null;
        let users = [];
        let activeIdx = 0;
        let searchAt = -1;
        let lastQ = '';

        const closePicker = () => { if (picker) { picker.remove(); picker = null; users = []; searchAt = -1; activeTa = null; } };

        const openPicker = ($ta) => {
            closePicker();
            activeTa = $ta;
            picker = document.createElement('div');
            picker.className = 'mention-picker';
            const rect = $ta.getBoundingClientRect();
            picker.style.left = (window.scrollX + rect.left + 12) + 'px';
            picker.style.top  = (window.scrollY + rect.bottom + 4) + 'px';
            document.body.appendChild(picker);
        };

        const renderPicker = () => {
            if (! picker) return;
            if (! users.length) {
                picker.innerHTML = '<div class="text-muted small p-2">Không tìm thấy ai phù hợp</div>';
                return;
            }
            picker.innerHTML = users.map((u, i) => `
                <div class="mention-item ${i === activeIdx ? 'active' : ''}" data-idx="${i}">
                    <span class="av-sm">${u.name.charAt(0).toUpperCase()}</span>
                    <span>${u.name.replace(/</g,'&lt;')}</span>
                </div>
            `).join('');
            picker.querySelectorAll('.mention-item').forEach(el => {
                el.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    activeIdx = parseInt(el.dataset.idx, 10);
                    insertMention();
                });
            });
        };

        const fetchUsers = async (q) => {
            try {
                const res = await fetch(searchUrl + '?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                if (! res.ok) return [];
                return await res.json();
            } catch (e) { return []; }
        };

        const insertMention = () => {
            if (! picker || ! users.length || ! activeTa) return;
            const u = users[activeIdx];
            const before = activeTa.value.slice(0, searchAt);
            const after  = activeTa.value.slice(activeTa.selectionStart);
            const insert = '@' + u.name + ' ';
            activeTa.value = before + insert + after;
            const pos = (before + insert).length;
            activeTa.setSelectionRange(pos, pos);
            activeTa.focus();
            closePicker();
        };

        const bind = ($ta) => {
            $ta.addEventListener('input', async () => {
                const pos = $ta.selectionStart;
                const text = $ta.value.slice(0, pos);
                const match = text.match(/@([^\s@]{0,40})$/);
                if (! match) { closePicker(); return; }

                searchAt = text.length - match[0].length;
                const q = match[1];
                if (q === lastQ && picker && activeTa === $ta) return;
                lastQ = q;

                users = await fetchUsers(q);
                activeIdx = 0;
                if (! picker || activeTa !== $ta) openPicker($ta);
                renderPicker();
            });

            $ta.addEventListener('keydown', (e) => {
                if (! picker || ! users.length || activeTa !== $ta) return;
                if (e.key === 'ArrowDown') { e.preventDefault(); activeIdx = (activeIdx + 1) % users.length; renderPicker(); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = (activeIdx - 1 + users.length) % users.length; renderPicker(); }
                else if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); insertMention(); }
                else if (e.key === 'Escape') { closePicker(); }
            });
        };

        textareas.forEach(bind);

        document.addEventListener('click', (e) => {
            if (picker && ! picker.contains(e.target) && e.target !== activeTa) closePicker();
        });
    })();
    </script>
    @endpush

    {{-- Edit modal --}}
    @if($canEdit)
    <div class="modal fade" id="editTaskModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border:none; border-radius:14px;">
                <form method="POST" action="{{ route('tasks.update', $task) }}">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-1"></i> Sửa task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tiêu đề <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required value="{{ old('title', $task->title) }}">
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Trạng thái</label>
                                <select name="status" class="form-select">
                                    @foreach($statusOptions as $k => $o)
                                        <option value="{{ $k }}" {{ $task->status === $k ? 'selected' : '' }}>{{ $o['lbl'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Độ ưu tiên</label>
                                <select name="priority" class="form-select">
                                    @foreach($priorityOptions as $k => $o)
                                        <option value="{{ $k }}" {{ $task->priority === $k ? 'selected' : '' }}>{{ $o['lbl'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Hạn</label>
                                <input type="text" name="due_at" class="form-control js-datetimepicker"
                                       placeholder="Chọn ngày & giờ…"
                                       value="{{ $task->due_at?->format('Y-m-d H:i') }}">
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nhắc trước</label>
                                <select name="remind_before" class="form-select">
                                    <option value="">Không nhắc</option>
                                    <option value="0"    {{ $task->remind_before === 0    ? 'selected' : '' }}>Đúng giờ hạn</option>
                                    <option value="30"   {{ $task->remind_before === 30   ? 'selected' : '' }}>30 phút trước</option>
                                    <option value="60"   {{ $task->remind_before === 60   ? 'selected' : '' }}>1 giờ trước</option>
                                    <option value="240"  {{ $task->remind_before === 240  ? 'selected' : '' }}>4 giờ trước</option>
                                    <option value="1440" {{ $task->remind_before === 1440 ? 'selected' : '' }}>1 ngày trước</option>
                                    <option value="2880" {{ $task->remind_before === 2880 ? 'selected' : '' }}>2 ngày trước</option>
                                </select>
                            </div>
                            @can('tasks.assign_others')
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Được giao cho</label>
                                <select name="assignees[]" class="form-select js-select2-users" multiple
                                        data-placeholder="Chọn người được giao…">
                                    @foreach($users as $u)
                                        <option value="{{ $u->id }}" {{ $task->assignees->contains('id', $u->id) ? 'selected' : '' }}>{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endcan
                        </div>
                        <div class="mt-3">
                            <label class="form-label fw-semibold">Ghi chú</label>
                            <textarea name="body" class="form-control" rows="5">{{ old('body', $task->body) }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Huỷ</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle me-1"></i> Lưu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
@endsection
