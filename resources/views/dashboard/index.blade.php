@extends('layouts.app')

@section('title', 'Tổng quan')

@section('content')
    <div class="page-header">
        <div>
            <h1>Hi, welcome back! 👋</h1>
            <nav class="breadcrumb mt-1">
                <a href="{{ route('dashboard') }}">Trang chủ</a>
                <span class="mx-2">/</span>
                <span>Tổng quan</span>
            </nav>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <div class="small text-muted me-2">
                <div class="fw-bold">EVENT CATEGORY</div>
                <div>All Categories</div>
            </div>
            <button class="btn btn-primary">
                <i class="bi bi-download me-1"></i> Export
            </button>
        </div>
    </div>

    {{-- Stat cards --}}
    <div class="row g-3 mb-3">
        <div class="col-md-3 col-sm-6">
            <div class="card stat-card">
                <div class="card-body d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Người dùng</div>
                        <div class="stat-value">{{ number_format($stats['users']) }}</div>
                        <div class="small text-success mt-1">
                            <i class="bi bi-arrow-up-short"></i> 12.5% so với tháng trước
                        </div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-people"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card stat-card">
                <div class="card-body d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Sản phẩm</div>
                        <div class="stat-value">{{ number_format($stats['items']) }}</div>
                        <div class="small text-success mt-1">
                            <i class="bi bi-arrow-up-short"></i> 4 mới trong tuần
                        </div>
                    </div>
                    <div class="stat-icon" style="background:rgba(36,211,159,.12); color:var(--azia-success)">
                        <i class="bi bi-box-seam"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card stat-card">
                <div class="card-body d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Đang kinh doanh</div>
                        <div class="stat-value">{{ number_format($stats['active']) }}</div>
                        <div class="small text-muted mt-1">trên tổng {{ $stats['items'] }} sản phẩm</div>
                    </div>
                    <div class="stat-icon" style="background:rgba(255,184,34,.15); color:#b8800f">
                        <i class="bi bi-shop"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card stat-card">
                <div class="card-body d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Giá trị tồn kho</div>
                        <div class="stat-value">{{ number_format($stats['value']) }}<span class="small text-muted">₫</span></div>
                        <div class="small text-danger mt-1">
                            <i class="bi bi-arrow-down-short"></i> 0.86% tuần này
                        </div>
                    </div>
                    <div class="stat-icon" style="background:rgba(0,184,212,.12); color:var(--azia-info)">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <div>Website Audience Metrics</div>
                        <div class="small text-muted fw-normal">Audience to which the users belonged while on the current date range.</div>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary active">Day</button>
                        <button class="btn btn-outline-secondary">Week</button>
                        <button class="btn btn-outline-secondary">Month</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col">
                            <div class="text-muted small">Users</div>
                            <div class="fw-bold fs-5">13,956</div>
                        </div>
                        <div class="col">
                            <div class="text-muted small">Bounce Rate</div>
                            <div class="fw-bold fs-5">33.50%</div>
                        </div>
                        <div class="col">
                            <div class="text-muted small">Page Views</div>
                            <div class="fw-bold fs-5">83,123</div>
                        </div>
                        <div class="col">
                            <div class="text-muted small">Sessions</div>
                            <div class="fw-bold fs-5">16,869</div>
                        </div>
                    </div>
                    <canvas id="metricsChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="stat-label">Bounce rate</div>
                            <div class="stat-value">33.50%</div>
                            <div class="small text-success"><i class="bi bi-graph-up-arrow"></i> 18.02%</div>
                        </div>
                        <canvas id="miniChart1" width="120" height="60"></canvas>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="stat-label">All sessions</div>
                            <div class="stat-value">16,869</div>
                            <div class="small text-success"><i class="bi bi-arrow-up-short"></i> 2.87%</div>
                        </div>
                        <canvas id="miniChart2" width="120" height="60"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>Sản phẩm mới nhất</div>
                    <a href="{{ route('items.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-table"></i> Mở bảng tính
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Mã</th><th>Tên</th><th>Nhóm</th>
                                <th class="text-end">Giá</th>
                                <th class="text-end">Tồn</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($latestItems as $it)
                            <tr>
                                <td><span class="badge badge-soft-primary">{{ $it->code }}</span></td>
                                <td class="fw-semibold">{{ $it->name }}</td>
                                <td>{{ $it->category }}</td>
                                <td class="text-end">{{ number_format($it->price) }} ₫</td>
                                <td class="text-end">{{ number_format($it->stock) }} {{ $it->unit }}</td>
                                <td>
                                    @if($it->is_active)
                                        <span class="badge badge-soft-success">Đang bán</span>
                                    @else
                                        <span class="badge badge-soft-danger">Ngưng</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Chưa có dữ liệu</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const labels = Array.from({length: 30}, (_, i) => 'D' + (i+1));
    const rand   = (n=80) => Array.from({length: 30}, () => Math.round(20 + Math.random() * n));

    new Chart(document.getElementById('metricsChart'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label: 'Users', data: rand(), borderColor: '#0153a9', backgroundColor: 'rgba(1,83,169,.15)', fill: true, tension: .35 },
                { label: 'Sessions', data: rand(50), borderColor: '#00b8d4', backgroundColor: 'rgba(0,184,212,.1)', fill: true, tension: .35 },
            ]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
    });

    const mini = (id, color) => new Chart(document.getElementById(id), {
        type: 'line',
        data: { labels: rand().map((_,i)=>i), datasets: [{ data: rand(40), borderColor: color, backgroundColor: color+'20', fill: true, tension:.4, pointRadius: 0 }] },
        options: { plugins:{legend:{display:false}}, scales:{x:{display:false}, y:{display:false}}, elements:{line:{borderWidth:2}} }
    });
    mini('miniChart1', '#24d39f');
    mini('miniChart2', '#0153a9');
</script>
@endpush
