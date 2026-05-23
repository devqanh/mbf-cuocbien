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

    /* ========== Segmented status control ========== */
    .status-segmented {
        display: inline-flex;
        background: #fafbfd;
        border: 1px solid var(--azia-border);
        border-radius: 10px;
        padding: 4px;
        gap: 2px;
    }
    .status-segmented form { margin: 0; }
    .status-seg-btn {
        background: transparent;
        border: none;
        padding: 8px 18px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        color: var(--azia-muted);
        display: inline-flex; align-items: center; gap: 6px;
        cursor: pointer;
        transition: all .15s;
        white-space: nowrap;
    }
    .status-seg-btn:hover:not(.is-active) { background: #fff; color: var(--azia-text); }
    .status-seg-btn.is-active {
        background: #fff;
        color: var(--azia-text);
        box-shadow: 0 1px 3px rgba(28,39,60,.08);
    }
    .status-seg-btn.is-active.color-secondary { color: var(--azia-muted); }
    .status-seg-btn.is-active.color-primary   { color: var(--azia-primary); }
    .status-seg-btn.is-active.color-success   { color: var(--azia-success); }
    .status-seg-btn .dot {
        width: 8px; height: 8px; border-radius: 50%;
        background: currentColor;
        display: inline-block;
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

    /* Inline title input mode */
    .title-input {
        background: rgba(255,255,255,.18);
        border: 1px solid rgba(255,255,255,.3);
        color: #fff;
        font-size: 22px; font-weight: 700;
        padding: 4px 10px;
        border-radius: 8px;
        outline: none;
        width: 100%;
        max-width: 600px;
    }
    .title-input:focus {
        background: rgba(255,255,255,.25);
        border-color: rgba(255,255,255,.5);
    }
    .title-edit-actions {
        display: inline-flex; gap: 6px; margin-left: 8px;
    }
    .title-edit-actions .btn-sm {
        padding: 4px 12px;
        font-size: 12px;
        border-radius: 8px;
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

            {{-- Status quick actions — segmented control --}}
            @if($canEdit)
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-lightning-charge me-1" style="color: var(--azia-primary)"></i>
                        Cập nhật trạng thái
                    </div>
                </div>
                <div class="card-body">
                    <div class="status-segmented">
                        @foreach($statusOptions as $key => $opt)
                            <form method="POST" action="{{ route('tasks.toggleStatus', $task) }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="status" value="{{ $key }}">
                                <button type="submit"
                                        class="status-seg-btn color-{{ $opt['color'] }} {{ $task->status === $key ? 'is-active' : '' }}"
                                        {{ $task->status === $key ? 'disabled' : '' }}
                                        title="{{ $opt['lbl'] }}">
                                    <span class="dot"></span> {{ $opt['lbl'] }}
                                </button>
                            </form>
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

                                {{-- Replies (nested 1 level only - flat thread) --}}
                                @if($c->replies->isNotEmpty())
                                    <div class="replies-block">
                                        @foreach($c->replies as $r)
                                            <div class="comment-item" id="comment-{{ $r->id }}">
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
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-info-square me-1" style="color: var(--azia-primary)"></i> Chi tiết</div>
                <div class="card-body">
                    <div class="info-row">
                        <span class="label">Trạng thái</span>
                        <span class="value">
                            <span class="badge badge-soft-{{ $statusOptions[$task->status]['color'] }}">
                                {{ $statusOptions[$task->status]['lbl'] }}
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="label">Độ ưu tiên</span>
                        <span class="value">
                            <span class="badge badge-soft-{{ $priorityOptions[$task->priority]['color'] }}">
                                {{ $priorityOptions[$task->priority]['lbl'] }}
                            </span>
                        </span>
                    </div>
                    @if($task->due_at)
                    <div class="info-row">
                        <span class="label">Hạn chót</span>
                        <span class="value {{ $overdue ? 'text-danger' : '' }}">
                            {{ $task->due_at->format('d/m/Y H:i') }}
                        </span>
                    </div>
                    @if($task->remind_before !== null)
                    <div class="info-row">
                        <span class="label">Nhắc trước</span>
                        <span class="value">
                            @if($task->remind_before === 0) Đúng giờ
                            @elseif($task->remind_before < 60) {{ $task->remind_before }} phút
                            @elseif($task->remind_before < 1440) {{ intdiv($task->remind_before, 60) }} giờ
                            @else {{ intdiv($task->remind_before, 1440) }} ngày
                            @endif
                        </span>
                    </div>
                    @endif
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
                <input type="text" class="title-input" value="${current.replace(/"/g, '&quot;')}" maxlength="255">
                <span class="title-edit-actions">
                    <button type="button" class="btn btn-sm btn-light" data-action="cancel">Huỷ</button>
                    <button type="button" class="btn btn-sm btn-primary" data-action="save">
                        <i class="bi bi-check2 me-1"></i>Lưu
                    </button>
                </span>
            `;
            const $input = $wrap.querySelector('input');
            $input.focus();
            $input.setSelectionRange(0, $input.value.length);

            $input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); save(); }
                else if (e.key === 'Escape') { cancel(); }
            });
            $wrap.querySelector('[data-action=save]').addEventListener('click', save);
            $wrap.querySelector('[data-action=cancel]').addEventListener('click', cancel);
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
                $saveBtn.innerHTML = '<i class="bi bi-check2 me-1"></i>Lưu';
                Swal && Swal.fire({
                    ...APP_SWAL, icon: 'error',
                    title: 'Lưu thất bại', html: 'Không cập nhật được. Thử lại sau.',
                    showCancelButton: false, confirmButtonText: 'Đóng',
                });
            }
        };

        $wrap.addEventListener('click', (e) => {
            if ($wrap.dataset.editing === '1') return;
            // Không trigger khi click vào edit-icon hoặc actions sau khi render lại
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
                                <input type="datetime-local" name="due_at" class="form-control"
                                       value="{{ $task->due_at?->format('Y-m-d\TH:i') }}">
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
