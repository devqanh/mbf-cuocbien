@extends('layouts.app')

@section('title', 'Thông báo')

@php
    $colorMap = ['primary' => 'primary', 'info' => 'info', 'warning' => 'warning', 'danger' => 'danger', 'success' => 'success'];
@endphp

@push('styles')
<style>
    .notif-row {
        display: flex; gap: 14px; align-items: flex-start;
        padding: 16px 20px;
        border-bottom: 1px solid var(--azia-border);
        text-decoration: none;
        color: var(--azia-text);
        transition: background .12s;
        position: relative;
    }
    .notif-row:hover { background: #fafbfd; color: var(--azia-text); }
    .notif-row.is-read { opacity: .6; }
    .notif-icon {
        width: 44px; height: 44px;
        border-radius: 12px;
        display: inline-flex; align-items: center; justify-content: center;
        flex-shrink: 0; font-size: 18px;
    }
    .notif-body { flex: 1; min-width: 0; }
    .notif-text { font-size: 14px; line-height: 1.5; font-weight: 500; }
    .notif-time { font-size: 11.5px; color: var(--azia-muted); margin-top: 2px; }
    .notif-row .unread-dot {
        position: absolute; top: 22px; right: 18px;
        width: 8px; height: 8px; border-radius: 50%;
        background: var(--azia-primary);
    }
</style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <h1><i class="bi bi-bell me-1" style="color: var(--azia-primary)"></i> Thông báo</h1>
            <nav class="breadcrumb mt-1">
                <a href="{{ route('trucking.index') }}">Trang chủ</a>
                <span class="mx-2">/</span>
                <span>Thông báo</span>
            </nav>
        </div>
        <form method="POST" action="{{ route('notifications.readAll') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">
                <i class="bi bi-check2-all me-1"></i> Đánh dấu tất cả đã đọc
            </button>
        </form>
    </div>

    <div class="card p-0">
        @forelse($notifications as $n)
            @php
                $d = $n->data;
                $color = $colorMap[$d['color'] ?? ''] ?? 'secondary';
            @endphp
            <a class="notif-row {{ $n->read_at ? 'is-read' : '' }}" href="{{ $d['url'] ?? '#' }}">
                <div class="notif-icon bg-{{ $color }}-subtle text-{{ $color }}">
                    <i class="bi bi-{{ $d['icon'] ?? 'bell' }}"></i>
                </div>
                <div class="notif-body">
                    <div class="notif-text">{{ $d['message'] ?? 'Thông báo' }}</div>
                    <div class="notif-time">
                        <i class="bi bi-clock me-1"></i>{{ $n->created_at?->diffForHumans() }}
                        <span class="ms-2 text-muted">{{ $n->created_at?->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
                @unless($n->read_at)
                    <span class="unread-dot"></span>
                @endunless
            </a>
        @empty
            <div class="text-center text-muted py-5">
                <i class="bi bi-bell-slash" style="font-size: 48px; opacity: .3"></i>
                <h5 class="mt-3">Không có thông báo</h5>
                <p class="small mb-0">Khi có ai giao việc hoặc đến giờ nhắc hẹn, bạn sẽ thấy ở đây.</p>
            </div>
        @endforelse

        @if($notifications->hasPages())
            <div class="p-3 border-top">{{ $notifications->links() }}</div>
        @endif
    </div>
@endsection
