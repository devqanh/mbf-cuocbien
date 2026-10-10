@extends('layouts.app')
@section('title', 'Nhật ký thao tác')

@php
  $evBadge = ['request' => ['Thao tác', 'secondary'], 'created' => ['Tạo', 'success'], 'updated' => ['Sửa', 'primary'], 'deleted' => ['Xóa', 'danger']];
  $show = fn ($v) => $v === null || $v === '' ? '(trống)' : (is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE));
@endphp

@section('content')
<div class="container-fluid" style="max-width: 1280px;">
  <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
    <div class="rounded-3 d-grid" style="width:46px;height:46px;place-items:center;background:#eef1f6;color:#475569;font-size:22px;">
      <i class="bi bi-journal-text"></i>
    </div>
    <div class="me-auto">
      <h4 class="mb-0 fw-bold">Nhật ký thao tác</h4>
      <div class="text-muted small">Ai đã lưu / sửa / xóa gì, lúc nào. Bấm 1 dòng để xem giá trị cũ → mới.</div>
    </div>
    @can('system.settings')
      <a href="{{ route('system.settings') }}#activity" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear"></i> Cấu hình nhật ký</a>
    @endcan
  </div>

  {{-- Bộ lọc --}}
  <form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
          <label class="form-label small fw-semibold mb-1">Tìm (biển số, số cont, mã bảng kê, nội dung…)</label>
          <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="vd 29C-18195">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">Nhân viên</label>
          <select name="user_id" class="form-select form-select-sm">
            <option value="">Tất cả</option>
            @foreach($users as $u)
              <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? '') == $u->id)>{{ $u->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">Trang</label>
          <select name="module" class="form-select form-select-sm">
            <option value="">Tất cả</option>
            @foreach($modules as $m)
              <option value="{{ $m }}" @selected(($filters['module'] ?? '') === $m)>{{ $m }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-md-1">
          <label class="form-label small fw-semibold mb-1">Loại</label>
          <select name="event" class="form-select form-select-sm">
            <option value="">Tất cả</option>
            @foreach($evBadge as $k => [$lbl])
              <option value="{{ $k }}" @selected(($filters['event'] ?? '') === $k)>{{ $lbl }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">Từ ngày</label>
          <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">Đến ngày</label>
          <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
        </div>
        <div class="col-12 d-flex gap-2">
          @if(!empty($filters['request_id']))
            <input type="hidden" name="request_id" value="{{ $filters['request_id'] }}">
            <span class="badge text-bg-warning align-self-center">Đang xem 1 lần thao tác · {{ \Illuminate\Support\Str::limit($filters['request_id'], 8, '') }}</span>
          @endif
          <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Lọc</button>
          <a href="{{ route('system.activityLog') }}" class="btn btn-light btn-sm">Xóa lọc</a>
          <span class="ms-auto small text-muted align-self-center">{{ number_format($logs->total()) }} dòng</span>
        </div>
      </div>
    </div>
  </form>

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0 actlog">
        <thead class="table-light">
          <tr><th style="width:150px">Thời gian</th><th style="width:160px">Người làm</th><th style="width:120px">Trang</th><th style="width:80px">Loại</th><th>Nội dung</th></tr>
        </thead>
        <tbody>
        @forelse($logs as $log)
          @php [$evLbl, $evCls] = $evBadge[$log->event] ?? [$log->event, 'secondary']; $ch = $log->changes ?? []; @endphp
          <tr class="actlog-row" data-bs-toggle="collapse" data-bs-target="#al{{ $log->id }}" style="cursor:pointer">
            <td class="small text-nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
            <td class="small">{{ $log->actor }}</td>
            <td class="small text-muted">{{ $log->module }}</td>
            <td><span class="badge text-bg-{{ $evCls }}">{{ $evLbl }}</span>
              @if($log->status && $log->status >= 400)<span class="badge text-bg-danger ms-1" title="Máy chủ từ chối / lỗi">{{ $log->status }}</span>@endif</td>
            <td class="small">
              @if($log->subject_label)<div class="fw-semibold">{{ $log->subject_label }}</div>@endif
              <div class="text-muted text-truncate" style="max-width:640px">{{ \Illuminate\Support\Str::after($log->summary, ' · ') }}</div>
            </td>
          </tr>
          <tr class="collapse" id="al{{ $log->id }}">
            <td colspan="5" class="bg-light small">
              <div class="d-flex gap-3 flex-wrap text-muted mb-2">
                <span><i class="bi bi-signpost"></i> {{ $log->method }} {{ $log->route }}</span>
                @if($log->ip)<span><i class="bi bi-globe"></i> {{ $log->ip }}</span>@endif
                <a href="{{ route('system.activityLog', ['request_id' => $log->request_id]) }}"><i class="bi bi-diagram-3"></i> Xem cả lần thao tác này</a>
              </div>
              @if($log->event === 'request')
                <pre class="mb-0 p-2 bg-white border rounded" style="max-height:320px;overflow:auto;white-space:pre-wrap">{{ json_encode($ch, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
              @elseif($ch)
                <table class="table table-sm table-bordered bg-white mb-0">
                  <thead><tr><th style="width:200px">Trường</th><th>Cũ</th><th>Mới</th></tr></thead>
                  <tbody>
                  @foreach($ch as $k => $pair)
                    <tr><td>{{ $fields[$k] ?? $k }} <span class="text-muted">({{ $k }})</span></td>
                      <td class="text-danger" style="word-break:break-all">{{ $show($pair[0] ?? null) }}</td>
                      <td class="text-success" style="word-break:break-all">{{ $show($pair[1] ?? null) }}</td></tr>
                  @endforeach
                  </tbody>
                </table>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted py-4">Chưa có dòng nhật ký nào khớp bộ lọc.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-3">{{ $logs->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
