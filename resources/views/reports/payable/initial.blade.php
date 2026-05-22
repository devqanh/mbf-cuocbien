@extends('layouts.app')

@section('title', 'Cấu hình đầu kỳ NCC')

@php $vnd = fn ($n) => number_format((float)$n, 0, ',', '.') . ' VNĐ'; @endphp

@section('content')
    <div class="page-header">
        <div>
            <h1><i class="bi bi-pencil-square me-1" style="color: var(--azia-primary)"></i> Cấu hình đầu kỳ NCC</h1>
            <nav class="breadcrumb mt-1">
                <a href="{{ route('shipments.index') }}">Trang chủ</a>
                <span class="mx-2">/</span>
                <a href="{{ route('reports.payable.index') }}">Báo cáo phải trả</a>
                <span class="mx-2">/</span>
                <span>Đầu kỳ</span>
            </nav>
        </div>
        <a href="{{ route('reports.payable.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Quay lại
        </a>
    </div>

    <div class="alert alert-info d-flex gap-2">
        <i class="bi bi-info-circle-fill fs-5"></i>
        <div>
            Đầu kỳ chỉ cần nhập <strong>1 lần đầu tiên</strong> cho mỗi NCC. Báo cáo sau sẽ tự lấy số cuối kỳ của báo cáo trước làm đầu kỳ.
            Nếu chưa có cấu hình ở đây mà NCC vẫn xuất hiện trong báo cáo → đầu kỳ = <code>0 VNĐ</code>.
        </div>
    </div>

    <div class="row g-3">
        {{-- Form thêm/sửa --}}
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-plus-circle me-1" style="color: var(--azia-primary)"></i>
                    Thêm / cập nhật đầu kỳ
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('reports.payable.initial.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold">NCC <span class="text-danger">*</span></label>
                            <input type="text" name="supplier" class="form-control" list="supplier-list" required
                                   value="{{ old('supplier') }}" placeholder="vd: MSC Việt Nam">
                            <datalist id="supplier-list">
                                @foreach($suppliers as $s)
                                    <option value="{{ $s }}">
                                @endforeach
                            </datalist>
                            <div class="form-text">Có thể chọn từ NCC đã có trong shipments hoặc tự gõ mới.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Đầu kỳ (VNĐ) <span class="text-danger">*</span></label>
                            <input type="number" name="opening_amount" class="form-control" step="1000" required
                                   value="{{ old('opening_amount') }}" placeholder="100000000">
                            <div class="form-text">Số dương = phải trả NCC. Số âm = NCC đang nợ mình (hiếm).</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Ngày áp dụng</label>
                            <input type="date" name="as_of_date" class="form-control" value="{{ old('as_of_date') }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Ghi chú</label>
                            <textarea name="note" rows="2" class="form-control">{{ old('note') }}</textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-save me-1"></i> Lưu
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Bảng đầu kỳ đã cấu hình --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div><i class="bi bi-list-check me-1" style="color: var(--azia-primary)"></i> Đầu kỳ đã cấu hình</div>
                    <span class="badge badge-soft-primary">{{ $balances->count() }} NCC</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>NCC</th>
                                <th class="text-end">Đầu kỳ</th>
                                <th>Ngày áp dụng</th>
                                <th>Ghi chú</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($balances as $b)
                            @php
                                $bJson = [
                                    'supplier' => $b->supplier,
                                    'opening_amount' => (float) $b->opening_amount,
                                    'as_of_date' => $b->as_of_date?->format('Y-m-d'),
                                    'note' => $b->note,
                                ];
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $b->supplier }}</td>
                                <td class="text-end fw-bold" style="color: var(--azia-primary)">{{ $vnd($b->opening_amount) }}</td>
                                <td>{{ $b->as_of_date?->format('d/m/Y') ?? '—' }}</td>
                                <td class="small text-muted">{{ $b->note }}</td>
                                <td class="text-end">
                                    <div class="action-group">
                                        <button type="button" class="action-btn action-edit" title="Sửa"
                                                onclick='editBalance(@json($bJson))'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="{{ route('reports.payable.initial.destroy', $b) }}" method="POST"
                                              onsubmit="return confirmDelete(this, {title: 'Xoá đầu kỳ?', text: 'Bạn sắp xoá đầu kỳ của <b>{{ $b->supplier }}</b>.'})">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="action-btn action-delete" title="Xoá">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Chưa cấu hình đầu kỳ NCC nào</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editBalance(b) {
            document.querySelector('[name="supplier"]').value       = b.supplier;
            document.querySelector('[name="opening_amount"]').value = b.opening_amount;
            document.querySelector('[name="as_of_date"]').value     = b.as_of_date || '';
            document.querySelector('[name="note"]').value           = b.note || '';
            document.querySelector('[name="supplier"]').scrollIntoView({behavior:'smooth'});
        }
    </script>
@endsection
