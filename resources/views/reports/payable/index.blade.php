@extends('layouts.app')

@section('title', 'Báo cáo phải trả')

@section('content')
    <div class="page-header">
        <div>
            <h1><i class="bi bi-cash-stack me-1" style="color: var(--azia-primary)"></i> Báo cáo phải trả</h1>
            <nav class="breadcrumb mt-1">
                <a href="{{ route('trucking.index') }}">Trang chủ</a>
                <span class="mx-2">/</span>
                <span>Báo cáo</span>
                <span class="mx-2">/</span>
                <span>Phải trả NCC</span>
            </nav>
        </div>
        <a href="{{ route('reports.payable.initial.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-pencil-square me-1"></i> Cấu hình đầu kỳ NCC
        </a>
    </div>

    <div class="row g-3">
        {{-- Form tạo báo cáo --}}
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-plus-circle me-1" style="color: var(--azia-primary)"></i>
                    Tạo báo cáo mới
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('reports.payable.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold">Ngày tạo báo cáo <span class="text-danger">*</span></label>
                            <input type="date" name="report_date" class="form-control"
                                   value="{{ old('report_date', date('Y-m-d')) }}" required>
                            <div class="form-text">Ngày đặt tên cho báo cáo. Đầu kỳ sẽ lấy từ báo cáo gần nhất TRƯỚC ngày này.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Ngày chốt báo cáo phát sinh <span class="text-success">tăng</span></label>
                            <select name="increase_date" class="form-select">
                                <option value="">— Không tính tăng kỳ này —</option>
                                @foreach($increase_dates as $d)
                                    <option value="{{ $d }}" {{ old('increase_date') === $d ? 'selected' : '' }}>
                                        {{ \Carbon\Carbon::parse($d)->format('d/m/Y') }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                Cộng <code>payment_amount</code> của các shipment có cột <em>"Ngày chốt báo cáo phát sinh tăng"</em> bằng ngày này, group theo NCC.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Ngày chốt báo cáo phát sinh <span class="text-danger">giảm</span></label>
                            <select name="decrease_date" class="form-select">
                                <option value="">— Không tính giảm kỳ này —</option>
                                @foreach($decrease_dates as $d)
                                    <option value="{{ $d }}" {{ old('decrease_date') === $d ? 'selected' : '' }}>
                                        {{ \Carbon\Carbon::parse($d)->format('d/m/Y') }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                Tương tự, lấy theo cột <em>"Ngày chốt báo cáo phát sinh giảm"</em>.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Ghi chú</label>
                            <textarea name="note" rows="2" class="form-control" placeholder="vd: Báo cáo tuần 3 tháng 5">{{ old('note') }}</textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-magic me-1"></i> Tạo báo cáo
                        </button>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body small text-muted">
                    <strong>Công thức:</strong><br>
                    Đầu kỳ = closing của báo cáo gần nhất TRƯỚC ngày này (theo NCC), nếu không có → lấy từ <a href="{{ route('reports.payable.initial.index') }}">cấu hình đầu kỳ</a>.<br>
                    Cuối kỳ = Đầu kỳ + Phát sinh tăng − Phát sinh giảm.
                </div>
            </div>
        </div>

        {{-- Danh sách báo cáo --}}
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-list-ul me-1" style="color: var(--azia-primary)"></i>
                        Danh sách báo cáo
                    </div>
                    <span class="badge badge-soft-primary">{{ $reports->total() }} báo cáo</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Ngày báo cáo</th>
                                <th>Tăng / Giảm</th>
                                <th class="text-end">NCC</th>
                                <th>Người tạo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($reports as $r)
                            <tr>
                                <td>{{ $r->id }}</td>
                                <td>
                                    <a href="{{ route('reports.payable.show', $r) }}" class="fw-semibold text-decoration-none">
                                        {{ $r->report_date->format('d/m/Y') }}
                                    </a>
                                    @if($r->note)
                                        <div class="small text-muted">{{ \Illuminate\Support\Str::limit($r->note, 50) }}</div>
                                    @endif
                                </td>
                                <td class="small">
                                    @if($r->increase_date)
                                        <div><i class="bi bi-arrow-up text-success"></i> {{ $r->increase_date->format('d/m/Y') }}</div>
                                    @endif
                                    @if($r->decrease_date)
                                        <div><i class="bi bi-arrow-down text-danger"></i> {{ $r->decrease_date->format('d/m/Y') }}</div>
                                    @endif
                                </td>
                                <td class="text-end"><span class="badge badge-soft-primary">{{ $r->lines_count }}</span></td>
                                <td><small>{{ $r->creator?->name ?? '—' }}</small></td>
                                <td class="text-end">
                                    <div class="action-group">
                                        <a href="{{ route('reports.payable.show', $r) }}"
                                           class="action-btn action-view" title="Xem chi tiết">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <form action="{{ route('reports.payable.destroy', $r) }}" method="POST"
                                              onsubmit="return confirmDelete(this, {title: 'Xoá báo cáo?', text: 'Báo cáo ngày <b>{{ $r->report_date->format('d/m/Y') }}</b> và toàn bộ dòng dữ liệu bên trong sẽ bị xoá vĩnh viễn.'})">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="action-btn action-delete" title="Xoá báo cáo">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Chưa có báo cáo nào. Tạo báo cáo đầu tiên bằng form bên trái.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if($reports->hasPages())
                    <div class="card-footer bg-white border-top">
                        {{ $reports->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
