@extends('layouts.app')

@section('title', 'Báo cáo phải trả · ' . $report->report_date->format('d/m/Y'))

@php
    $totals = [
        'opening'  => (float) $report->lines->sum('opening_balance'),
        'increase' => (float) $report->lines->sum('increase_amount'),
        'decrease' => (float) $report->lines->sum('decrease_amount'),
        'closing'  => (float) $report->lines->sum('closing_balance'),
    ];

    $prevClosing = $previous ? (float) $previous->lines->sum('closing_balance') : null;
    $delta       = $prevClosing !== null ? $totals['closing'] - $prevClosing : null;
    $deltaPct    = $prevClosing !== null && $prevClosing != 0
        ? ($delta / abs($prevClosing)) * 100
        : null;

    // Compact VND format
    $vnd  = fn ($n) => number_format((float)$n, 0, ',', '.');
    $sign = fn ($n) => ($n > 0 ? '+' : ($n < 0 ? '−' : '')) . $vnd(abs($n));
    $initials = fn ($s) => mb_strtoupper(mb_substr(trim($s), 0, 1, 'UTF-8'));

    // 1 màu palette per supplier (consistent across rows)
    $palette = ['#0153a9', '#24d39f', '#7c5cff', '#ffb822', '#00b8d4', '#ff5b5b', '#6c63ff', '#13c2c2'];
    $colorFor = function ($s) use ($palette) {
        $hash = 0;
        foreach (str_split($s) as $ch) $hash = ($hash * 31 + ord($ch)) & 0xFFFFFFFF;
        return $palette[$hash % count($palette)];
    };
@endphp

@push('styles')
<style>
    .report-hero {
        background: linear-gradient(135deg, #0153a9 0%, #013f80 100%);
        color: #fff;
        border: none;
        border-radius: 14px;
        padding: 28px 32px;
        margin-bottom: 20px;
        position: relative;
        overflow: hidden;
    }
    .report-hero::after {
        content: '';
        position: absolute;
        width: 320px; height: 320px;
        border-radius: 50%;
        background: rgba(255,255,255,.06);
        top: -120px; right: -100px;
    }
    .report-hero .hero-label {
        font-size: 11px; text-transform: uppercase; letter-spacing: 2px;
        opacity: .8; font-weight: 600; margin: 0;
    }
    .report-hero .hero-value {
        font-size: 38px; font-weight: 700; line-height: 1.1; margin: 6px 0 4px;
    }
    .report-hero .hero-currency { font-size: 18px; opacity: .8; font-weight: 500; }
    .report-hero .hero-sub  { font-size: 13px; opacity: .85; }
    .report-hero .hero-delta {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(255,255,255,.15);
        padding: 6px 12px; border-radius: 999px;
        font-size: 13px; font-weight: 600;
    }
    .report-hero .hero-delta.positive { background: rgba(255,91,91,.25); }
    .report-hero .hero-delta.negative { background: rgba(36,211,159,.25); }

    .stat-mini {
        display: flex; align-items: center; gap: 14px;
        padding: 16px 18px;
        background: #fff;
        border: 1px solid var(--azia-border);
        border-radius: 12px;
        transition: all .15s;
    }
    .stat-mini:hover { box-shadow: 0 4px 14px rgba(28,39,60,.06); }
    .stat-mini-icon {
        width: 44px; height: 44px; border-radius: 12px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 20px; flex-shrink: 0;
    }
    .stat-mini-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px;
                       color: var(--azia-muted); font-weight: 700; }
    .stat-mini-value { font-size: 18px; font-weight: 700; color: var(--azia-text); }

    .meta-pill {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 10px; border-radius: 999px;
        background: var(--azia-bg); font-size: 12px; color: var(--azia-text);
        font-weight: 500;
    }
    .meta-pill i { color: var(--azia-muted); }

    /* NCC table */
    .ncc-table th {
        background: #fafbfd; border-bottom: 1px solid var(--azia-border);
        font-size: 11px; text-transform: uppercase; letter-spacing: .8px;
        color: var(--azia-muted); font-weight: 700; padding: 12px 14px;
    }
    .ncc-table td { padding: 14px; vertical-align: middle; }
    .ncc-table tbody tr { transition: background .12s; }
    .ncc-table tbody tr:hover { background: #fafbfd; }
    .ncc-avatar {
        width: 36px; height: 36px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: 14px; flex-shrink: 0;
    }
    .ncc-name { font-weight: 600; color: var(--azia-text); }

    .delta-chip {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 4px 10px; border-radius: 8px;
        font-size: 12px; font-weight: 600;
        font-variant-numeric: tabular-nums;
    }
    .delta-up   { background: rgba(36,211,159,.12); color: var(--azia-success); }
    .delta-down { background: rgba(255,91,91,.12);  color: var(--azia-danger); }
    .delta-zero { background: #f4f6fb;              color: #c5cbd6; }

    .closing-amount { font-weight: 700; color: var(--azia-primary); font-size: 15px; }
    .opening-amount { color: var(--azia-text); font-variant-numeric: tabular-nums; }

    .table-footer {
        background: linear-gradient(180deg, #fafbfd 0%, #f4f5f8 100%);
        border-top: 2px solid var(--azia-border);
        font-weight: 700;
    }
    .table-footer td { padding: 16px 14px; font-size: 14px; }

    /* Action bar refined */
    .action-bar { display: inline-flex; align-items: center; gap: 6px; }
    .action-bar .btn-icon {
        width: 36px; height: 36px; padding: 0;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 10px; border: 1px solid var(--azia-border);
        background: #fff; color: var(--azia-text);
        transition: all .15s; font-size: 15px;
    }
    .action-bar .btn-icon:hover {
        background: var(--azia-bg);
        border-color: var(--azia-primary);
        color: var(--azia-primary);
    }
    .action-bar .btn-icon.is-ghost {
        border-color: transparent; background: transparent; color: var(--azia-muted);
    }
    .action-bar .btn-icon.is-ghost:hover {
        background: var(--azia-bg); color: var(--azia-text); border-color: transparent;
    }
    .action-bar .btn-primary-action {
        height: 36px; padding: 0 14px; border-radius: 10px;
        border: 1px solid var(--azia-border); background: #fff;
        color: var(--azia-text); font-weight: 600; font-size: 13px;
        display: inline-flex; align-items: center; gap: 6px;
        transition: all .15s;
    }
    .action-bar .btn-primary-action:hover {
        background: var(--azia-primary); border-color: var(--azia-primary); color: #fff;
    }
    .action-bar .action-menu {
        border: 1px solid var(--azia-border);
        border-radius: 12px; padding: 6px; min-width: 220px;
        box-shadow: 0 8px 24px rgba(28,39,60,.10);
    }
    .action-bar .action-menu .dropdown-item {
        border-radius: 8px; padding: 8px 12px;
        font-size: 13px; display: flex; align-items: center; gap: 10px;
    }
    .action-bar .action-menu .dropdown-item i { font-size: 15px; width: 16px; }
    .action-bar .action-menu .dropdown-item.text-danger:hover {
        background: rgba(255,91,91,.08); color: var(--azia-danger);
    }

    /* Print: hide chrome, keep content */
    @media print {
        .page-header .action-bar,
        nav.navbar, .toast-container { display: none !important; }
        .report-hero { background: #0153a9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .card { box-shadow: none !important; border-color: #ddd !important; }
        body { background: #fff !important; }
    }
</style>
@endpush

@section('content')
    {{-- Compact page header --}}
    <div class="page-header">
        <div>
            <nav class="breadcrumb">
                <a href="{{ route('shipments.index') }}">Trang chủ</a>
                <span class="mx-2">/</span>
                <a href="{{ route('reports.payable.index') }}">Báo cáo phải trả</a>
                <span class="mx-2">/</span>
                <span>#{{ $report->id }}</span>
            </nav>
            <h1 class="mt-1">Báo cáo {{ $report->report_date->format('d/m/Y') }}</h1>
            <div class="d-flex flex-wrap gap-2 mt-2">
                @if($report->increase_date)
                    <span class="meta-pill"><i class="bi bi-arrow-up-circle text-success"></i> Tăng: {{ $report->increase_date->format('d/m/Y') }}</span>
                @endif
                @if($report->decrease_date)
                    <span class="meta-pill"><i class="bi bi-arrow-down-circle text-danger"></i> Giảm: {{ $report->decrease_date->format('d/m/Y') }}</span>
                @endif
                <span class="meta-pill"><i class="bi bi-person-circle"></i> {{ $report->creator?->name ?? '—' }}</span>
                <span class="meta-pill"><i class="bi bi-people"></i> {{ $report->lines->count() }} NCC</span>
            </div>
        </div>
        <div class="action-bar">
            <a href="{{ route('reports.payable.index') }}" class="btn-icon is-ghost" title="Quay lại danh sách">
                <i class="bi bi-arrow-left"></i>
            </a>
            <button type="button" class="btn-primary-action" onclick="window.print()">
                <i class="bi bi-printer"></i> In
            </button>
            <div class="dropdown">
                <button type="button" class="btn-icon" data-bs-toggle="dropdown" aria-expanded="false" title="Thao tác khác">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end action-menu">
                    <li>
                        <button type="button" class="dropdown-item" onclick="copyReportLink(this)">
                            <i class="bi bi-link-45deg"></i> Sao chép liên kết
                        </button>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <form action="{{ route('reports.payable.destroy', $report) }}" method="POST" class="m-0"
                              onsubmit="return confirmDelete(this, {title: 'Xoá báo cáo {{ $report->report_date->format('d/m/Y') }}?', text: 'Toàn bộ <b>{{ $report->lines->count() }}</b> dòng nhà cung cấp trong báo cáo sẽ bị xoá vĩnh viễn.'})">
                            @csrf @method('DELETE')
                            <button type="submit" class="dropdown-item text-danger w-100">
                                <i class="bi bi-trash"></i> Xoá báo cáo
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function copyReportLink(btn) {
            navigator.clipboard.writeText(window.location.href).then(() => {
                const original = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check2"></i> Đã sao chép';
                setTimeout(() => { btn.innerHTML = original; }, 1500);
            });
        }
    </script>
    @endpush

    @if($report->note)
        <div class="alert alert-warning d-flex gap-2 mb-3">
            <i class="bi bi-sticky-fill fs-5"></i>
            <div>{{ $report->note }}</div>
        </div>
    @endif

    {{-- HERO: Tổng cuối kỳ --}}
    <div class="report-hero d-flex justify-content-between flex-wrap gap-3">
        <div style="position:relative; z-index:1">
            <p class="hero-label">Tổng số phải trả cuối kỳ</p>
            <div class="hero-value">
                {{ $vnd($totals['closing']) }} <span class="hero-currency">VNĐ</span>
            </div>
            <div class="hero-sub">
                Qua {{ $report->lines->count() }} nhà cung cấp · báo cáo ngày {{ $report->report_date->format('d/m/Y') }}
            </div>
        </div>

        @if($delta !== null)
            @php
                $cls = $delta > 0 ? 'positive' : ($delta < 0 ? 'negative' : '');
                $arrow = $delta > 0 ? 'bi-arrow-up-right' : ($delta < 0 ? 'bi-arrow-down-right' : 'bi-dash');
            @endphp
            <div style="position:relative; z-index:1" class="text-end">
                <div class="hero-delta {{ $cls }}">
                    <i class="bi {{ $arrow }}"></i>
                    {{ $sign($delta) }} VNĐ
                    @if($deltaPct !== null)
                        <span class="opacity-75">({{ number_format($deltaPct, 1, ',', '.') }}%)</span>
                    @endif
                </div>
                <div class="small mt-2 opacity-75">
                    vs báo cáo {{ $previous->report_date->format('d/m/Y') }}
                </div>
            </div>
        @endif
    </div>

    {{-- 3 stat mini: Đầu kỳ - Tăng - Giảm --}}
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="stat-mini">
                <div class="stat-mini-icon" style="background: var(--azia-primary-soft); color: var(--azia-primary)">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <div>
                    <div class="stat-mini-label">Tổng đầu kỳ</div>
                    <div class="stat-mini-value">{{ $vnd($totals['opening']) }} <small class="text-muted">VNĐ</small></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-mini">
                <div class="stat-mini-icon" style="background: rgba(36,211,159,.12); color: var(--azia-success)">
                    <i class="bi bi-arrow-up-circle-fill"></i>
                </div>
                <div>
                    <div class="stat-mini-label">Phát sinh tăng</div>
                    <div class="stat-mini-value text-success">+{{ $vnd($totals['increase']) }} <small class="text-muted">VNĐ</small></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-mini">
                <div class="stat-mini-icon" style="background: rgba(255,91,91,.12); color: var(--azia-danger)">
                    <i class="bi bi-arrow-down-circle-fill"></i>
                </div>
                <div>
                    <div class="stat-mini-label">Phát sinh giảm</div>
                    <div class="stat-mini-value text-danger">−{{ $vnd($totals['decrease']) }} <small class="text-muted">VNĐ</small></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bảng chi tiết --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="bi bi-list-columns-reverse me-1" style="color: var(--azia-primary)"></i>
                Chi tiết theo nhà cung cấp
            </div>
            <span class="text-muted small">
                <i class="bi bi-info-circle"></i> Sắp xếp theo cuối kỳ giảm dần
            </span>
        </div>
        <div class="table-responsive">
            <table class="table ncc-table mb-0">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>NCC</th>
                        <th class="text-end">Đầu kỳ</th>
                        <th class="text-end">Phát sinh</th>
                        <th class="text-end" style="width:180px">Cuối kỳ</th>
                    </tr>
                </thead>
                <tbody>
                @php $lines = $report->lines->sortByDesc('closing_balance')->values(); @endphp
                @forelse($lines as $i => $line)
                    @php
                        $hex = $colorFor($line->supplier);
                        $changed = $line->increase_amount > 0 || $line->decrease_amount > 0;
                    @endphp
                    <tr>
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="ncc-avatar" style="background: {{ $hex }}">
                                    {{ $initials($line->supplier) }}
                                </div>
                                <div>
                                    <div class="ncc-name">{{ $line->supplier }}</div>
                                    @if(! $changed)
                                        <div class="small text-muted">Không phát sinh kỳ này</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="text-end opening-amount">{{ $vnd($line->opening_balance) }}</td>
                        <td class="text-end">
                            <div class="d-flex gap-2 justify-content-end align-items-center">
                                @if($line->increase_amount > 0)
                                    <span class="delta-chip delta-up" title="Phát sinh tăng">
                                        <i class="bi bi-arrow-up"></i> {{ $vnd($line->increase_amount) }}
                                    </span>
                                @endif
                                @if($line->decrease_amount > 0)
                                    <span class="delta-chip delta-down" title="Phát sinh giảm">
                                        <i class="bi bi-arrow-down"></i> {{ $vnd($line->decrease_amount) }}
                                    </span>
                                @endif
                                @if(! $changed)
                                    <span class="delta-chip delta-zero">—</span>
                                @endif
                            </div>
                        </td>
                        <td class="text-end closing-amount">
                            {{ $vnd($line->closing_balance) }} <small class="text-muted">VNĐ</small>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5">
                        <i class="bi bi-inbox display-4 d-block mb-2 opacity-50"></i>
                        Không có dòng nào trong báo cáo này
                    </td></tr>
                @endforelse
                </tbody>
                @if($lines->count())
                    <tfoot class="table-footer">
                        <tr>
                            <td colspan="2" class="text-end">TỔNG CỘNG</td>
                            <td class="text-end">{{ $vnd($totals['opening']) }}</td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end">
                                    <span class="delta-chip delta-up">
                                        <i class="bi bi-arrow-up"></i> {{ $vnd($totals['increase']) }}
                                    </span>
                                    <span class="delta-chip delta-down">
                                        <i class="bi bi-arrow-down"></i> {{ $vnd($totals['decrease']) }}
                                    </span>
                                </div>
                            </td>
                            <td class="text-end closing-amount" style="font-size:16px">
                                {{ $vnd($totals['closing']) }} <small class="text-muted">VNĐ</small>
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- ============ NOTES & TASKS GẮN VỚI BÁO CÁO NÀY ============ --}}
    <div class="card mt-3" id="notesCard">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="bi bi-sticky-fill me-1" style="color: var(--azia-warning)"></i>
                Ghi chú &amp; công việc liên quan
                @if($tasks->count())
                    <span class="badge badge-soft-primary ms-2">{{ $tasks->count() }}</span>
                @endif
            </div>
            @can('tasks.create')
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#linkedTaskModal">
                    <i class="bi bi-plus-lg me-1"></i> Thêm ghi chú / task
                </button>
            @endcan
        </div>
        <div class="card-body p-0">
            @forelse($tasks as $t)
                @php
                    $sColor = match($t->status) { 'done' => 'success', 'doing' => 'primary', default => 'secondary' };
                    $sLabel = match($t->status) { 'done' => 'Hoàn thành', 'doing' => 'Đang làm', default => 'Chưa làm' };
                    $isOverdue = $t->isOverdue();
                @endphp
                <div class="d-flex align-items-center gap-3 px-3 py-3 border-bottom" style="border-color: var(--azia-border)!important">
                    <span class="badge badge-soft-{{ $sColor }}" style="min-width: 80px">{{ $sLabel }}</span>
                    <div class="flex-grow-1 min-w-0">
                        <a href="{{ route('tasks.show', $t) }}" class="fw-semibold text-decoration-none" style="color: var(--azia-text)">
                            {{ $t->title }}
                        </a>
                        <div class="small text-muted mt-1 d-flex flex-wrap gap-2">
                            <span><i class="bi bi-person"></i> {{ $t->creator?->name }}</span>
                            @if($t->due_at)
                                <span class="{{ $isOverdue ? 'text-danger fw-semibold' : '' }}">
                                    <i class="bi bi-{{ $isOverdue ? 'alarm-fill' : 'clock' }}"></i>
                                    {{ $t->due_at->format('d/m H:i') }}
                                    @if($isOverdue) (quá hạn) @endif
                                </span>
                            @endif
                            @if($t->assignees->count())
                                <span><i class="bi bi-people"></i>
                                    {{ $t->assignees->pluck('name')->take(3)->implode(', ') }}
                                    @if($t->assignees->count() > 3) +{{ $t->assignees->count() - 3 }} @endif
                                </span>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('tasks.show', $t) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            @empty
                <div class="text-center text-muted py-4 small">
                    <i class="bi bi-sticky" style="font-size: 32px; opacity: .3"></i>
                    <div class="mt-2">Chưa có ghi chú nào gắn với báo cáo này.</div>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Modal tạo task gắn vào báo cáo này --}}
    @can('tasks.create')
    <div class="modal fade" id="linkedTaskModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border:none; border-radius:14px;">
                <form method="POST" action="{{ route('tasks.store') }}">
                    @csrf
                    <input type="hidden" name="linkable_type" value="report">
                    <input type="hidden" name="linkable_id" value="{{ $report->id }}">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-sticky-fill me-1" style="color: var(--azia-warning)"></i>
                            Tạo ghi chú / task gắn vào báo cáo này
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info small mb-3">
                            <i class="bi bi-link-45deg"></i>
                            Task sẽ được gắn vào <strong>Báo cáo {{ $report->report_date->format('d/m/Y') }}</strong>.
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tiêu đề <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required autofocus
                                   placeholder="vd: Đối chiếu lại số phải trả MSC kỳ này">
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Hạn</label>
                                <input type="text" name="due_at" class="form-control js-datetimepicker"
                                       placeholder="Chọn ngày & giờ…">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nhắc trước</label>
                                <select name="remind_before" class="form-select">
                                    <option value="">Không nhắc</option>
                                    <option value="60" selected>1 giờ trước</option>
                                    <option value="1440">1 ngày trước</option>
                                </select>
                            </div>
                        </div>
                        @can('tasks.assign_others')
                        <div class="mt-3">
                            <label class="form-label fw-semibold">Giao cho</label>
                            <select name="assignees[]" class="form-select js-select2-users" multiple
                                    data-placeholder="Chọn người được giao…">
                                @foreach(\App\Models\User::orderBy('name')->get(['id','name']) as $u)
                                    <option value="{{ $u->id }}" {{ $u->id === auth()->id() ? 'selected' : '' }}>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endcan
                        <div class="mt-3">
                            <label class="form-label fw-semibold">Ghi chú</label>
                            <textarea name="body" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Huỷ</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle me-1"></i> Tạo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endcan
@endsection
