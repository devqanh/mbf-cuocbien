@extends('layouts.app')

@section('title', 'Follow Up Shipment — ' . $period)

@push('styles')
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/plugins/css/pluginsCss.css' />
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/plugins/plugins.css' />
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/css/luckysheet.css' />
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/assets/iconfont/iconfont.css' />
<style>
    /* ===== Column visibility offcanvas (slide-in panel) ===== */
    .cv-offcanvas {
        width: 420px !important;
        max-width: 100vw;
        display: flex !important;
        flex-direction: column !important;
    }
    .cv-header {
        background: linear-gradient(135deg, #0153a9 0%, #013f80 100%);
        color: #fff;
        padding: 14px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .cv-header .cv-title { font-size: 13px; font-weight: 700; letter-spacing: .3px; }
    .cv-header .cv-counter {
        background: rgba(255,255,255,.18);
        font-size: 11px; font-weight: 600;
        padding: 3px 10px; border-radius: 999px;
    }
    .cv-search {
        padding: 10px 14px; border-bottom: 1px solid var(--azia-border);
        background: #fafbfd;
    }
    .cv-search input {
        width: 100%; padding: 7px 12px 7px 32px;
        border: 1px solid var(--azia-border); border-radius: 8px;
        font-size: 13px;
        background: #fff url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%237987a1'><path d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/></svg>") no-repeat 10px center;
        background-size: 14px;
    }
    .cv-search input:focus { outline: none; border-color: var(--azia-primary); box-shadow: 0 0 0 3px rgba(1,83,169,.12); }
    .cv-quick {
        display: flex; gap: 6px; padding: 10px 14px;
        border-bottom: 1px solid var(--azia-border); background: #fafbfd;
    }
    .cv-quick button {
        flex: 1; font-size: 11px; padding: 4px 8px;
        border: 1px solid var(--azia-border); background: #fff;
        border-radius: 6px; cursor: pointer; color: var(--azia-text);
        font-weight: 600; transition: all .15s;
    }
    .cv-quick button:hover { background: var(--azia-primary-soft); color: var(--azia-primary); border-color: var(--azia-primary); }

    .cv-body {
        flex: 1 1 auto;
        min-height: 0;      /* QUAN TRỌNG cho flex child cuộn */
        overflow-y: auto;
        padding: 4px 0;
    }
    .cv-header, .cv-search, .cv-quick, .cv-footer { flex: 0 0 auto; }
    .cv-group {
        padding: 8px 14px 4px;
        border-left: 4px solid transparent;
        margin-bottom: 4px;
    }
    .cv-group.g-1 { border-left-color: #D4E6B5; }
    .cv-group.g-2 { border-left-color: #FCE4D6; }
    .cv-group.g-3 { border-left-color: #DEEBF7; }
    .cv-group.g-4 { border-left-color: #FFF2CC; }

    .cv-group-head {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 6px;
    }
    .cv-group-title {
        font-size: 10px; text-transform: uppercase; letter-spacing: 1px;
        color: var(--azia-muted); font-weight: 700;
    }
    .cv-group-count {
        background: var(--azia-bg); color: var(--azia-text);
        font-size: 10px; font-weight: 700;
        padding: 2px 8px; border-radius: 999px;
    }
    .cv-group-toggle {
        font-size: 11px; padding: 2px 8px;
        border: 1px solid var(--azia-border); border-radius: 6px;
        background: #fff; cursor: pointer; color: var(--azia-muted);
        margin-left: 4px;
    }
    .cv-group-toggle:hover { color: var(--azia-primary); border-color: var(--azia-primary); }

    .cv-row {
        display: flex; align-items: center; gap: 10px;
        padding: 6px 8px; border-radius: 6px;
        cursor: pointer; transition: background .12s;
    }
    .cv-row:hover { background: #fafbfd; }

    /* Custom toggle switch */
    .cv-switch {
        position: relative;
        width: 32px; height: 18px;
        background: #d5dae3; border-radius: 999px;
        flex-shrink: 0; transition: background .2s;
    }
    .cv-switch::after {
        content: ''; position: absolute;
        top: 2px; left: 2px;
        width: 14px; height: 14px; background: #fff;
        border-radius: 50%; transition: transform .2s;
        box-shadow: 0 1px 3px rgba(0,0,0,.2);
    }
    .cv-row input:checked + .cv-switch { background: var(--azia-primary); }
    .cv-row input:checked + .cv-switch::after { transform: translateX(14px); }
    .cv-row input:disabled + .cv-switch { background: #e1e6f1; }
    .cv-row input { position: absolute; opacity: 0; pointer-events: none; }

    .cv-label {
        flex: 1; font-size: 13px; color: var(--azia-text);
        display: flex; align-items: center; gap: 6px;
        min-width: 0;
    }
    .cv-label .name { font-weight: 500; }
    .cv-locked {
        font-size: 9px; color: var(--azia-danger);
        background: rgba(255,91,91,.1); padding: 1px 6px;
        border-radius: 4px; font-weight: 700; text-transform: uppercase;
        letter-spacing: .5px;
    }

    .cv-empty {
        text-align: center; padding: 30px 20px;
        color: var(--azia-muted); font-size: 13px;
    }

    .cv-footer {
        display: flex; gap: 8px;
        padding: 12px 14px;
        background: #fafbfd; border-top: 1px solid var(--azia-border);
    }
    .cv-footer .btn { font-size: 13px; padding: 8px 14px; }

    /* ===== Date filter dropdown ===== */
    .df-dropdown {
        min-width: 360px; padding: 0; border: none; border-radius: 14px;
        box-shadow: 0 20px 50px rgba(28,39,60,.18); overflow: hidden;
    }
    .df-header {
        background: linear-gradient(135deg, #24d39f 0%, #169a72 100%);
        color: #fff; padding: 14px 18px;
        display: flex; justify-content: space-between; align-items: center;
    }
    .df-body { padding: 16px; }
    .df-field { margin-bottom: 14px; }
    .df-field label {
        display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;
        color: var(--azia-muted); font-weight: 700; margin-bottom: 6px;
    }
    .df-field select, .df-field input {
        width: 100%; padding: 8px 12px;
        border: 1px solid var(--azia-border); border-radius: 8px;
        font-size: 13px;
    }
    .df-field select:focus, .df-field input:focus {
        outline: none; border-color: var(--azia-success);
        box-shadow: 0 0 0 3px rgba(36,211,159,.15);
    }
    .df-help { font-size: 12px; color: var(--azia-muted); padding: 8px 12px; background: #fafbfd; border-radius: 8px; }
    .df-help i { color: var(--azia-success); margin-right: 4px; }

    .df-footer { display: flex; gap: 8px; padding: 12px 16px; background: #fafbfd; border-top: 1px solid var(--azia-border); }

    .filter-chip {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(36,211,159,.15); color: #169a72;
        font-size: 12px; font-weight: 600;
        padding: 4px 10px 4px 10px; border-radius: 999px;
        margin-left: 6px;
    }
    .filter-chip button {
        background: transparent; border: none; color: #169a72;
        cursor: pointer; padding: 0; margin-left: 4px;
        display: inline-flex; align-items: center;
    }
    .filter-chip button:hover { color: var(--azia-danger); }

    .period-tabs {
        display: flex; gap: 4px; flex-wrap: wrap;
        padding: 6px; background: #fafbfd;
        border: 1px solid var(--azia-border); border-radius: 10px;
        margin-bottom: 14px;
    }
    .period-tab {
        padding: 6px 14px; border-radius: 6px;
        background: #fff; border: 1px solid var(--azia-border);
        color: var(--azia-text); font-size: 12px; font-weight: 600;
        text-decoration: none; text-transform: uppercase; letter-spacing: .5px;
        transition: all .15s;
    }
    .period-tab:hover { background: var(--azia-primary-soft); color: var(--azia-primary); }
    .period-tab.active {
        background: var(--azia-primary); color: #fff;
        border-color: var(--azia-primary);
    }
    .period-tab.current::after { content: ' •'; color: #ffd700; }
    .period-add {
        padding: 6px 12px; border-radius: 6px;
        background: transparent; border: 1px dashed var(--azia-muted);
        color: var(--azia-muted); font-size: 12px; cursor: pointer;
    }
    .period-add:hover { color: var(--azia-primary); border-color: var(--azia-primary); }
</style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <h1><i class="bi bi-truck me-1" style="color: var(--azia-primary)"></i> FOLLOW UP SHIPMENT</h1>
            <nav class="breadcrumb mt-1">
                <a href="{{ route('shipments.index') }}">Trang chủ</a>
                <span class="mx-2">/</span>
                <span>Tháng <strong>{{ $period }}</strong></span>
            </nav>
        </div>
        <div class="d-flex gap-2">
            {{-- Dropdown chọn cột hiển thị (lưu riêng cho user) --}}
            @php
                $groupTitles = [
                    1 => 'Thông tin lô hàng',
                    2 => 'Chứng từ vận chuyển',
                    3 => 'Thanh toán NCC & Agent',
                    4 => 'Doanh thu khách hàng',
                ];
                $colsByGroup = collect($columns)->groupBy('group');
                $userHidden  = $userPrefs ?? [];

                // Tính số cột đang hiển thị (bao gồm cả readonly như No.)
                $totalToggleable = 0;
                $currentlyShown  = 0;
                foreach ($columns as $c) {
                    $adminHide = ($columnPerms[$c['key']] ?? 'edit') === 'hidden';
                    if ($adminHide) continue;
                    $totalToggleable++;
                    if (! in_array($c['key'], $userHidden)) $currentlyShown++;
                }
            @endphp
            <button class="btn btn-outline-secondary" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#cvOffcanvas" aria-controls="cvOffcanvas">
                <i class="bi bi-layout-three-columns me-1"></i> Cột hiển thị
                <span class="badge bg-primary ms-1" id="colsCountBadge">{{ $currentlyShown }}</span>
            </button>

            <div class="offcanvas offcanvas-end cv-offcanvas" tabindex="-1" id="cvOffcanvas" aria-labelledby="cvOffcanvasLabel">
                {{-- Header gradient --}}
                <div class="cv-header">
                    <div>
                        <div class="cv-title" id="cvOffcanvasLabel"><i class="bi bi-layout-three-columns me-1"></i> Tuỳ chỉnh cột hiển thị</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="cv-counter">
                            <span id="cvShownNum">{{ $currentlyShown }}</span> / {{ $totalToggleable }} cột
                        </span>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Đóng"></button>
                    </div>
                </div>

                {{-- Search --}}
                <div class="cv-search">
                    <input type="search" id="cvSearch" placeholder="Tìm cột theo tên...">
                </div>

                {{-- Quick actions --}}
                <div class="cv-quick">
                    <button type="button" onclick="toggleAllCols(true)">
                        <i class="bi bi-eye"></i> Hiện tất cả
                    </button>
                    <button type="button" onclick="toggleAllCols(false)">
                        <i class="bi bi-eye-slash"></i> Ẩn tất cả
                    </button>
                    <button type="button" onclick="resetUserPrefs()">
                        <i class="bi bi-arrow-counterclockwise"></i> Mặc định
                    </button>
                </div>

                {{-- Body --}}
                <div class="cv-body" id="cvBody">
                        @foreach($colsByGroup as $gid => $grpCols)
                            @php
                                $groupCount = 0; $groupVisible = 0;
                                foreach ($grpCols as $c) {
                                    if (($columnPerms[$c['key']] ?? 'edit') === 'hidden') continue;
                                    $groupCount++;
                                    if (! in_array($c['key'], $userHidden)) $groupVisible++;
                                }
                            @endphp
                            {{-- Nếu cả nhóm bị admin ẩn hết → bỏ qua section --}}
                            @if($groupCount === 0) @continue @endif
                            <div class="cv-group g-{{ $gid }}" data-group-section="{{ $gid }}">
                                <div class="cv-group-head">
                                    <div>
                                        <span class="cv-group-title">NHÓM {{ $gid }} — {{ $groupTitles[$gid] ?? "Nhóm $gid" }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="cv-group-count" id="cvCount_{{ $gid }}">{{ $groupVisible }}/{{ $groupCount }}</span>
                                        <button type="button" class="cv-group-toggle" onclick="toggleGroupCols({{ $gid }})" title="Bật/tắt cả nhóm">
                                            <i class="bi bi-toggles"></i>
                                        </button>
                                    </div>
                                </div>
                                @foreach($grpCols as $col)
                                    {{-- Cột admin đã ẩn → KHÔNG show trong dropdown (user không cần biết) --}}
                                    @if(($columnPerms[$col['key']] ?? 'edit') === 'hidden')
                                        @continue
                                    @endif
                                    <label class="cv-row"
                                           data-name="{{ mb_strtolower($col['title']) }}"
                                           data-group="{{ $gid }}"
                                           for="cp_{{ $col['key'] }}">
                                        <input class="col-pref-toggle"
                                               type="checkbox"
                                               data-key="{{ $col['key'] }}"
                                               data-group="{{ $gid }}"
                                               id="cp_{{ $col['key'] }}"
                                               {{ ! in_array($col['key'], $userHidden) ? 'checked' : '' }}>
                                        <span class="cv-switch"></span>
                                        <span class="cv-label">
                                            <span class="name">{{ $col['title'] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    <div class="cv-empty d-none" id="cvEmpty">
                        <i class="bi bi-search d-block mb-2" style="font-size:24px"></i>
                        Không tìm thấy cột nào khớp.
                    </div>
                </div>

                {{-- Footer --}}
                <div class="cv-footer">
                    <button type="button" class="btn btn-light flex-grow-1" data-bs-dismiss="offcanvas">
                        <i class="bi bi-x-lg"></i> Đóng
                    </button>
                    <button type="button" class="btn btn-primary flex-grow-1" id="btnApplyCols">
                        <i class="bi bi-check2-circle me-1"></i> Áp dụng
                    </button>
                </div>
            </div>
            {{-- end .cv-offcanvas --}}

            {{-- Dropdown lọc theo cột ngày --}}
            @php
                $dateColumns = collect($columns)->where('type', 'date')->values();
            @endphp
            <div class="dropdown">
                <button class="btn btn-outline-success dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <i class="bi bi-funnel me-1"></i> Lọc ngày
                    <span class="badge bg-success ms-1 d-none" id="dfBadge">0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end df-dropdown">
                    <div class="df-header">
                        <div>
                            <div style="font-size:13px;font-weight:700;letter-spacing:.3px">
                                <i class="bi bi-funnel-fill me-1"></i> Lọc dòng theo cột ngày
                            </div>
                            <div style="font-size:11px;opacity:.85;margin-top:2px">Dành cho kế toán rà soát hạn / thanh toán</div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" aria-label="Đóng"
                                onclick="closeDropdown(this)" style="filter:brightness(0) invert(1);opacity:.8"></button>
                    </div>
                    <div class="df-body">
                        <div class="df-field">
                            <label>Cột áp dụng</label>
                            <select id="dfColumn">
                                <option value="">— Chọn cột —</option>
                                @foreach($dateColumns as $col)
                                    <option value="{{ $col['key'] }}">{{ $col['title'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="df-field">
                            <label>Điều kiện</label>
                            <select id="dfOperator">
                                <option value="overdue">⚠️ Quá hạn (trước hôm nay)</option>
                                <option value="within_days" selected>⏰ Sắp đến hạn trong ≤ N ngày</option>
                                <option value="due_today">📅 Đúng hôm nay</option>
                                <option value="between">📆 Trong khoảng từ → đến</option>
                                <option value="not_empty">✓ Có giá trị</option>
                                <option value="empty">✗ Trống</option>
                            </select>
                        </div>

                        <div class="df-field" id="dfParamN">
                            <label>Số ngày (N)</label>
                            <input type="number" id="dfN" value="7" min="0" max="365">
                        </div>

                        <div class="df-field d-none" id="dfParamRange">
                            <label>Từ ngày → đến ngày</label>
                            <div class="d-flex gap-2">
                                <input type="date" id="dfFrom">
                                <input type="date" id="dfTo">
                            </div>
                        </div>

                        <div class="df-help">
                            <i class="bi bi-info-circle"></i>
                            Ẩn các dòng không khớp điều kiện. Filter chỉ ảnh hưởng VIEW, không lưu DB.
                        </div>
                    </div>
                    <div class="df-footer">
                        <button type="button" class="btn btn-light" onclick="closeDropdown(this)">
                            <i class="bi bi-x-lg"></i> Đóng
                        </button>
                        <button type="button" class="btn btn-light flex-grow-1" id="btnClearFilter">
                            <i class="bi bi-eraser"></i> Xoá lọc
                        </button>
                        <button type="button" class="btn btn-success flex-grow-1" id="btnApplyFilter">
                            <i class="bi bi-check2-circle me-1"></i> Áp dụng
                        </button>
                    </div>
                </div>
            </div>

            <span id="filterChip"></span>

            {{-- [2026-05-23] Ẩn nút NCC — user yêu cầu, vẫn truy cập được qua menu Báo cáo --}}
            {{-- <a href="{{ route('reports.payable.initial.index') }}" class="btn btn-outline-secondary"
               target="_blank" title="Cấu hình danh sách NCC — mở tab mới">
                <i class="bi bi-people me-1"></i> NCC
                <span class="badge bg-secondary ms-1">{{ count($suppliers) }}</span>
            </a> --}}
            <a class="btn btn-outline-success" href="{{ route('shipments.export', ['period' => $period]) }}"
               title="Tải file Excel toàn bộ dữ liệu tháng này">
                <i class="bi bi-file-earmark-excel me-1"></i> Xuất Excel
            </a>
            <button class="btn btn-outline-secondary" id="btnReload">
                <i class="bi bi-arrow-clockwise me-1"></i> Tải lại
            </button>
            <button class="btn btn-primary" id="btnSaveAll">
                <i class="bi bi-cloud-arrow-up me-1"></i> Lưu thay đổi
            </button>
        </div>
    </div>

    {{-- Period tabs --}}
    <div class="period-tabs">
        @foreach($periods as $p)
            @php
                [$y, $m] = explode('-', $p);
                $label = strtoupper(date('M', mktime(0,0,0,(int)$m,1))) . '-' . substr($y, 2);
            @endphp
            <a href="{{ route('shipments.show', ['period' => $p]) }}"
               class="period-tab {{ $p === $period ? 'active' : '' }} {{ $p === $current ? 'current' : '' }}"
               title="{{ $p }}{{ $p === $current ? ' (tháng hiện tại)' : '' }}">{{ $label }}</a>
        @endforeach
        <button class="period-add" data-bs-toggle="modal" data-bs-target="#periodModal">
            <i class="bi bi-plus-lg"></i> Tạo tháng
        </button>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span>Tháng <strong>{{ $period }}</strong></span>
                    <span id="versionBadge" class="badge badge-soft-warning">
                        <i class="bi bi-clock-history"></i> Đang tải…
                    </span>
                    <span id="liveStatus" class="badge badge-soft-warning">
                        <i class="bi bi-wifi-off"></i> Offline
                    </span>
                    <span id="liveActivity" class="small text-warning fw-semibold ms-2"
                          style="opacity:0; transition: opacity .3s ease;"></span>
                </div>
                <div class="small text-muted fw-normal">
                    Workbook có 2 sheet ở dưới: <strong>HÀNG NHẬP</strong> và <strong>HÀNG XUẤT</strong>.
                    Click ô <kbd>x</kbd> trong các cột VGM/SI/BL/OBL/TLX/SWB/SHIPMENT để đánh dấu workflow.
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="luckysheet" style="height: calc(100vh - 320px); min-height: 520px;"></div>
        </div>
    </div>

    {{-- Modal tạo tháng --}}
    <div class="modal fade" id="periodModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="periodForm">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-calendar-plus me-1"></i> Tạo tháng mới</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Chọn tháng cần tạo</label>
                        <input type="month" id="newPeriod" class="form-control" required>
                        <div class="form-text">Sẽ tạo 1 workbook trắng (HÀNG NHẬP + HÀNG XUẤT) cho tháng này.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Tạo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/plugins/js/plugin.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/luckysheet.umd.js"></script>
    {{-- Pusher + Echo đã được layout load, không cần load lại --}}

    <script>
        const PERIOD = @json($period);
        const ROUTES = {
            data:           @json(route('shipments.data',         ['period' => $period])),
            bulk:           @json(route('shipments.bulk',         ['period' => $period])),
            createPeriod:   @json(route('shipments.createPeriod')),
            columnPrefs:    @json(route('shipments.columnPrefs')),
        };
        const CSRF = document.querySelector('meta[name="csrf-token"]').content;

        const REVERB = {
            key:    @json(config('broadcasting.connections.reverb.key')),
            host:   @json(config('broadcasting.connections.reverb.options.host', request()->getHost())),
            port:   @json((int) config('broadcasting.connections.reverb.options.port', 8080)),
            scheme: @json(config('broadcasting.connections.reverb.options.scheme', 'http')),
        };
        const CURRENT_USER = @json(['id' => auth()->id(), 'name' => auth()->user()->name]);

        // Header background theo nhóm (áp dụng cả HÀNG NHẬP và HÀNG XUẤT)
        const GROUP_HEADER_BG = {
            1: '#D4E6B5',   // NHÓM 1 - THÔNG TIN LÔ HÀNG (xanh lá pastel)
            2: '#FCE4D6',   // NHÓM 2 - CHỨNG TỪ VẬN CHUYỂN (cam đào nhạt)
            3: '#DEEBF7',   // NHÓM 3 - THANH TOÁN NCC & AGENT (xanh dương pastel)
            4: '#FFF2CC',   // NHÓM 4 - DOANH THU KHÁCH HÀNG (vàng kem nhạt)
        };

        // Định nghĩa cột — load từ config PHP để đồng bộ giữa backend & frontend
        const ALL_COLS     = @json($columns);
        const COLUMN_PERMS = @json($columnPerms);          // {key: 'hidden'|'view'|'edit'} — admin set
        const USER_HIDDEN  = new Set(@json($userPrefs));   // [key,...] — user tự chọn ẩn
        const SUPPLIERS    = @json($suppliers);            // danh sách NCC từ payable_initial_balances + shipments

        // GLOBAL — dùng ở nhiều function (renderSheet, btnSaveAll handler...)
        // ADMIN_RESTRICTED: admin set col permissions hidden/view → user không có quyền sửa
        //   toàn workbook → KHÔNG cho save formatting (sẽ overwrite cols admin restrict).
        // USER_HIDDEN: user TỰ chọn hide cho riêng họ (preference) → vẫn cho save format
        //   trên cols visible. Backend merge để không mất format cols other users đã set.
        const ADMIN_RESTRICTED = Object.values(COLUMN_PERMS).some(p => p === 'hidden' || p === 'view');
        const HAS_RESTRICTIONS = ADMIN_RESTRICTED || USER_HIDDEN.size > 0;   // legacy — vẫn dùng cho render snapshot

        // Cho save formatting: chỉ cần KHÔNG bị admin restrict (user-hidden OK).
        const CAN_SAVE_FORMATTING = ! ADMIN_RESTRICTED;

        // Quyền xóa rows — từ backend (Spatie permission shipments.delete)
        const CAN_DELETE_ROWS = @json($canDelete ?? false);

        // Filter:
        //  1. Bỏ cột admin đã ẩn
        //  2. Bỏ cột user tự chọn ẩn
        //  3. Mark readonly cho cột view
        const COLS = ALL_COLS
            .filter(c => (COLUMN_PERMS[c.key] || 'edit') !== 'hidden')
            .filter(c => ! USER_HIDDEN.has(c.key))
            .map(c => {
                const perm = COLUMN_PERMS[c.key] || 'edit';
                return { ...c, readonly: c.readonly || perm === 'view' };
            });

        function toast(msg, type = 'success') {
            const el = document.createElement('div');
            el.className = `toast align-items-center text-bg-${type} border-0 show`;
            el.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
            document.querySelector('.toast-container').appendChild(el);
            setTimeout(() => bootstrap.Toast.getOrCreateInstance(el).hide(), 3500);
        }

        // Format helpers
        const fmtVND    = (n) => n == null || n === '' ? '' : Number(n).toLocaleString('vi-VN') + ' VNĐ';
        const fmtNumber = (n) => n == null || n === '' ? '' : Number(n).toLocaleString('vi-VN');
        const fmtDate   = (s) => {
            if (!s) return '';
            const d = new Date(s);
            if (isNaN(d)) return s;
            return String(d.getDate()).padStart(2,'0') + '/' +
                   String(d.getMonth()+1).padStart(2,'0') + '/' + d.getFullYear();
        };

        // ct (cell type) cho Luckysheet theo column type
        const ctFor = (type) => {
            if (type === 'vnd')    return { fa: '#,##0" VNĐ"', t: 'n' };
            if (type === 'number') return { fa: '#,##0',        t: 'n' };
            if (type === 'date')   return { fa: 'dd/MM/yyyy',   t: 'g' };  // 'g' để giữ chuỗi đã format
            return { fa: 'General', t: 'g' };
        };

        // Flag bypass cho khi SYSTEM tự update cells readonly (vd: gán id mới sau save)
        // Khi true, các hook cellUpdateBefore/cellUpdated sẽ không chặn/revert
        let _systemUpdate = false;

        // Per-cell system update tracking — thay thế global flag để KHÔNG block
        // user typing ở cell khác. Key: "sheetOrder:row:col" → TTL 400ms.
        // System code call markSystemCell trước setCellValue/setCellFormat; hook check
        // isSystemCell để skip markDirty CHỈ cho cell đó, không block toàn sheet.
        const _systemCells = new Map();   // key → expireAt timestamp

        function markSystemCell(sheetOrder, row, col, ttl = 400) {
            const k = `${sheetOrder}:${row}:${col}`;
            _systemCells.set(k, Date.now() + ttl);
        }
        function isSystemCell(sheetOrder, row, col) {
            const k = `${sheetOrder}:${row}:${col}`;
            const exp = _systemCells.get(k);
            if (! exp) return false;
            if (Date.now() > exp) { _systemCells.delete(k); return false; }
            return true;
        }

        // ===== DIRTY-CELL TRACKER (Mức 2 — collaborative editing) =====
        // Map per-sheet-order { sheetRow → Set(colKey) }
        // Chỉ cell nằm trong tracker được gửi lên server khi Lưu → backend partial update
        // → User A sửa cột X, user B sửa cột Y cùng row → cả 2 được giữ (không đè nhau).
        let dirtyCells = { 0: new Map(), 1: new Map() };

        function markDirty(sheetOrder, sheetRow, colKey) {
            if (! dirtyCells[sheetOrder]) dirtyCells[sheetOrder] = new Map();
            const m = dirtyCells[sheetOrder];
            if (! m.has(sheetRow)) m.set(sheetRow, new Set());
            m.get(sheetRow).add(colKey);
        }

        function resetDirty() {
            dirtyCells = { 0: new Map(), 1: new Map() };
        }

        // Snapshot dirty state (deep clone) — dùng để: save chụp tại t0, sau khi server
        // confirm chỉ xóa keys ĐÃ GỬI (giữ keys user typing trong lúc save đang fetch).
        function snapshotDirty() {
            const snap = { 0: new Map(), 1: new Map() };
            [0, 1].forEach(o => {
                (dirtyCells[o] || new Map()).forEach((set, row) => {
                    snap[o].set(row, new Set(set));
                });
            });
            return snap;
        }

        // Remove entries trong snap khỏi dirtyCells (giữ entries mới user typing sau snap)
        function removeDirtyMatching(snap) {
            [0, 1].forEach(o => {
                const liveMap = dirtyCells[o];
                if (! liveMap) return;
                snap[o].forEach((sentKeys, row) => {
                    const liveSet = liveMap.get(row);
                    if (! liveSet) return;
                    sentKeys.forEach(k => liveSet.delete(k));
                    if (liveSet.size === 0) liveMap.delete(row);
                });
            });
        }

        function countDirty() {
            let total = 0;
            Object.values(dirtyCells).forEach(m => m.forEach(set => total += set.size));
            return total;
        }

        // Mark mọi cell trong range (dùng cho paste hook) + whisper
        function markRangeDirty(range) {
            if (! range) return;
            const ranges = Array.isArray(range) ? range : [range];
            const sheetOrder = luckysheet.getSheet()?.order ?? 0;
            const batchEdits = [];   // collect cells để whisper batch
            ranges.forEach(rg => {
                if (! rg || ! rg.row || ! rg.column) return;
                const [r0, r1] = rg.row;
                const [c0, c1] = rg.column;
                for (let r = Math.max(1, r0); r <= r1; r++) {
                    for (let c = c0; c <= c1; c++) {
                        const colDef = COLS[c];
                        if (colDef && ! colDef.readonly && COLUMN_PERMS[colDef.key] !== 'view') {
                            markDirty(sheetOrder, r, colDef.key);
                            batchEdits.push({ sheetOrder, sheetRow: r, colKey: colDef.key });
                        }
                    }
                }
            });
            if (batchEdits.length > 0) whisperCellBatch(batchEdits);
        }

        // ===== LIVE REALTIME via Reverb client events (whisper) =====
        // Cơ chế Google-Sheets-like: A gõ → debounce 250ms → whisper trực tiếp tới channel
        // (không qua DB). B nhận → apply cell ngay. Save vẫn là source-of-truth, snapshot vẫn lưu khi save.
        const WHISPER_DEBOUNCE_MS = 250;
        const _whisperPending = new Map();   // "sheetOrder:row:col" → timeoutId

        // Diagnostic counters — expose qua window._realtimeStats để debug khi cần
        const _rtStats = { sent: 0, received: 0, sendFailed: 0, noChannel: 0, skippedNoId: 0 };
        window._realtimeStats = () => ({ ..._rtStats, channelReady: !!_privateChan });

        function whisperCellEdit(sheetOrder, sheetRow, colKey) {
            if (! window.Echo || ! _privateChan) { _rtStats.noChannel++; return; }

            const k = `${sheetOrder}:${sheetRow}:${colKey}`;
            if (_whisperPending.has(k)) clearTimeout(_whisperPending.get(k));

            _whisperPending.set(k, setTimeout(() => {
                _whisperPending.delete(k);
                try {
                    const colIndex = COLS.findIndex(c => c.key === colKey);
                    if (colIndex < 0) return;
                    const cellValue = luckysheet.getCellValue(sheetRow, colIndex, { order: sheetOrder });
                    const idCol = COLS.findIndex(c => c.key === 'id');
                    const rowId = idCol >= 0
                        ? luckysheet.getCellValue(sheetRow, idCol, { order: sheetOrder })
                        : null;

                    // Row chưa có id (mới insert) → skip whisper, đợi save để có id
                    if (rowId == null || rowId === '') { _rtStats.skippedNoId++; return; }

                    _privateChan.whisper('cell-edit', {
                        period:     PERIOD,
                        sheetOrder, sheetRow,
                        rowId:      parseInt(rowId) || null,
                        colKey,
                        value:      cellValue,
                        editor:     { id: CURRENT_USER.id, name: CURRENT_USER.name },
                        ts:         Date.now(),
                    });
                    _rtStats.sent++;
                } catch (e) {
                    _rtStats.sendFailed++;
                    console.warn('[whisper] cell-edit failed:', e);
                }
            }, WHISPER_DEBOUNCE_MS));
        }

        // Whisper batch (cho paste) — gửi 1 event với array of edits, tránh spam rate-limit
        function whisperCellBatch(edits) {
            if (! window.Echo || ! _privateChan) return;
            try {
                const idCol = COLS.findIndex(c => c.key === 'id');
                const payload = [];
                edits.forEach(({ sheetOrder, sheetRow, colKey }) => {
                    const colIndex = COLS.findIndex(c => c.key === colKey);
                    if (colIndex < 0) return;
                    const rowId = idCol >= 0
                        ? luckysheet.getCellValue(sheetRow, idCol, { order: sheetOrder })
                        : null;
                    if (rowId == null || rowId === '') return;   // skip new rows
                    const cellValue = luckysheet.getCellValue(sheetRow, colIndex, { order: sheetOrder });
                    payload.push({ sheetOrder, sheetRow, rowId: parseInt(rowId), colKey, value: cellValue });
                });
                if (payload.length === 0) return;

                _privateChan.whisper('cell-batch', {
                    period: PERIOD,
                    edits:  payload,
                    editor: { id: CURRENT_USER.id, name: CURRENT_USER.name },
                    ts:     Date.now(),
                });
            } catch (e) { console.warn('whisper batch failed:', e); }
        }

        // Áp dụng cell change từ user khác — bypass hook readonly, không mark dirty
        function applyRemoteCellEdit(edit, editorName) {
            try {
                const colIndex = COLS.findIndex(c => c.key === edit.colKey);
                if (colIndex < 0) return;   // user này đã ẩn cột → bỏ qua

                // Match row theo rowId (cột id cell), KHÔNG dùng sheetRow trực tiếp
                // vì 2 user có thể có sheet row khác nhau (vd: A vừa insert dòng mới)
                const idCol = COLS.findIndex(c => c.key === 'id');
                if (idCol < 0) return;

                const sheets = luckysheet.getluckysheetfile();
                const sheet = sheets[edit.sheetOrder];
                if (! sheet?.data) return;

                let targetRow = -1;
                for (let r = 1; r < sheet.data.length; r++) {
                    const cell = sheet.data[r]?.[idCol];
                    const v = cell?.v ?? cell?.m;
                    if (v != null && parseInt(v) === edit.rowId) {
                        targetRow = r;
                        break;
                    }
                }
                if (targetRow < 0) return;

                // Per-cell mark — KHÔNG dùng global _systemUpdate để không block user khác.
                markSystemCell(edit.sheetOrder, targetRow, colIndex, 400);
                try {
                    luckysheet.setCellValue(targetRow, colIndex, edit.value ?? '', { order: edit.sheetOrder });
                } catch (e) { console.warn('apply setCellValue failed:', e); }

                flashRemoteCell(edit.sheetOrder, targetRow, colIndex, editorName);
            } catch (e) { console.warn('applyRemoteCellEdit failed:', e); }
        }

        // Visual flash tại cell vừa bị remote edit — bg vàng 1.5s rồi restore.
        // Dùng per-cell marker, KHÔNG block global → user gõ cell khác vẫn được track dirty.
        function flashRemoteCell(sheetOrder, row, col, editorName) {
            try {
                const allSheets = luckysheet.getluckysheetfile();
                const sheet = allSheets[sheetOrder];
                if (! sheet?.data?.[row]?.[col]) return;
                const oldBg = sheet.data[row][col].bg;

                markSystemCell(sheetOrder, row, col, 200);
                try {
                    luckysheet.setCellFormat(row, col, 'bg', '#fef3c7', { order: sheetOrder });
                } catch (e) {}

                setTimeout(() => {
                    markSystemCell(sheetOrder, row, col, 200);
                    try {
                        luckysheet.setCellFormat(row, col, 'bg', oldBg || null, { order: sheetOrder });
                    } catch (e) {}
                }, 1500);

                _showEditorActivity(editorName);
            } catch (e) {}
        }

        // Throttle activity indicator per editor
        const _activityShown = new Map();   // editorName → lastShownTs
        function _showEditorActivity(editorName) {
            if (! editorName) return;
            const now = Date.now();
            const last = _activityShown.get(editorName) || 0;
            if (now - last < 5000) return;
            _activityShown.set(editorName, now);
            const el = document.getElementById('liveActivity');
            if (! el) return;
            el.innerHTML = `<i class="bi bi-pencil-fill text-warning"></i> <strong>${editorName}</strong> đang sửa…`;
            el.style.opacity = '1';
            clearTimeout(_showEditorActivity._t);
            _showEditorActivity._t = setTimeout(() => { el.style.opacity = '0'; }, 3000);
        }

        // Reference tới private channel — set sau khi Echo init
        let _privateChan = null;

        // Flag: user khác đã save trong khi mình đang gõ dở → cần resync sau khi save xong
        let _needsResync = false;

        // ===== Scan + clear duplicate ID trong cột No. =====
        // QUAN TRỌNG: phải bật _systemUpdate vì setCellValue cho cột id (readonly) bị hook chặn
        function scanDuplicateIds() {
            try {
                const idCol = COLS.findIndex(c => c.key === 'id');
                if (idCol < 0) return 0;

                const allSheets = luckysheet.getluckysheetfile();
                const activeOrder = luckysheet.getSheet().order ?? 0;
                const seenIds = new Set();
                const toClear = [];   // [{r, sheetOrder}]

                allSheets.forEach((sheet, sheetOrder) => {
                    const data = sheet.data;
                    if (! data) return;
                    for (let r = 1; r < data.length; r++) {
                        const cell = data[r]?.[idCol];
                        const v = cell?.v ?? cell?.m;
                        if (v == null || v === '') continue;
                        const numId = parseInt(v);
                        if (! numId) continue;

                        if (seenIds.has(numId)) {
                            toClear.push({ r, sheetOrder });
                        } else {
                            seenIds.add(numId);
                        }
                    }
                });

                if (toClear.length === 0) return 0;

                // Per-cell mark thay vì global flag → user gõ cell khác không bị block
                toClear.forEach(({ r, sheetOrder }) => {
                    try {
                        markSystemCell(sheetOrder, r, idCol, 300);
                        if (sheetOrder === activeOrder) {
                            luckysheet.setCellValue(r, idCol, '');
                        } else {
                            luckysheet.setCellValue(r, idCol, '', { order: sheetOrder });
                        }
                    } catch (e) { console.warn('clear duplicate id fail:', e); }
                });

                toast(`Đã xoá <strong>${toClear.length}</strong> No. trùng (copy/paste) — khi lưu sẽ tạo dòng mới.`, 'info');
                return toClear.length;
            } catch (e) {
                console.warn('scanDuplicateIds failed:', e);
                return 0;
            }
        }

        // Helper close dropdown bằng API Bootstrap (dùng cho onclick inline)
        window.closeDropdown = function (btn) {
            const dropdown = btn.closest('.dropdown');
            if (! dropdown) return;
            const toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
            if (toggle) bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
        };

        // ===== Date filter helpers =====
        function parseDateFromCell(value) {
            if (value == null || value === '') return null;
            const s = String(value).trim();
            let m = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
            if (m) return new Date(+m[1], +m[2] - 1, +m[3]);
            m = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
            if (m) return new Date(+m[3], +m[2] - 1, +m[1]);
            return null;
        }

        let dateFilterState = { active: false, hiddenRows: [], colKey: null, op: null, label: null, matched: 0 };

        function applyDateFilter() {
            const colKey = document.getElementById('dfColumn').value;
            const op     = document.getElementById('dfOperator').value;
            if (! colKey) { toast('Vui lòng chọn cột để lọc.', 'warning'); return; }

            const colIndex = COLS.findIndex(c => c.key === colKey);
            if (colIndex < 0) {
                toast('Cột này đang bị ẩn — bật lại ở "Cột hiển thị" để dùng filter.', 'warning');
                return;
            }

            clearDateFilter(false);

            const today = new Date(); today.setHours(0, 0, 0, 0);
            const n = parseInt(document.getElementById('dfN').value || 0);
            const from = parseDateFromCell(document.getElementById('dfFrom').value);
            const to   = parseDateFromCell(document.getElementById('dfTo').value);
            if (to) to.setHours(23, 59, 59);

            const sheets = luckysheet.getluckysheetfile();
            const activeIdx = luckysheet.getSheet().order ?? 0;
            const sheet = sheets[activeIdx];
            const data  = sheet.data;
            const clientIdx = COLS.findIndex(c => c.key === 'client');

            let matchedCount = 0;
            const toHide = [];
            const heights = {};

            for (let r = 1; r < data.length; r++) {
                const row = data[r];
                if (! row) continue;
                const hasClient = clientIdx >= 0 && row[clientIdx] && (row[clientIdx].v ?? row[clientIdx].m);
                if (! hasClient) continue;

                const cell = row[colIndex];
                const dateVal = cell ? (cell.v ?? cell.m) : null;
                const cellDate = parseDateFromCell(dateVal);

                let match = false;
                switch (op) {
                    case 'overdue':     match = cellDate && cellDate < today; break;
                    case 'within_days': {
                        const limit = new Date(today);
                        limit.setDate(limit.getDate() + n);
                        match = cellDate && cellDate >= today && cellDate <= limit;
                        break;
                    }
                    case 'due_today':   match = cellDate && cellDate.getTime() === today.getTime(); break;
                    case 'between':     match = cellDate && from && to && cellDate >= from && cellDate <= to; break;
                    case 'empty':       match = ! cellDate; break;
                    case 'not_empty':   match = !! cellDate; break;
                }

                if (match) matchedCount++;
                else { toHide.push(r); heights[r] = 0; }
            }

            if (Object.keys(heights).length > 0) {
                luckysheet.setRowHeight(heights);
            }

            const colTitle = COLS[colIndex].title;
            const opLabels = {
                overdue: 'quá hạn', within_days: `≤ ${n} ngày tới`, due_today: 'đúng hôm nay',
                between: 'trong khoảng', empty: 'trống', not_empty: 'có giá trị',
            };
            const label = `${colTitle} · ${opLabels[op]}`;

            dateFilterState = { active: true, hiddenRows: toHide, colKey, op, label, matched: matchedCount };
            updateFilterChip(matchedCount);
            toast(`Lọc xong: <strong>${matchedCount}</strong> dòng khớp.`);
        }

        window.clearDateFilter = function (notify = true) {
            if (dateFilterState.hiddenRows.length > 0) {
                const heights = {};
                dateFilterState.hiddenRows.forEach(r => { heights[r] = 32; });
                luckysheet.setRowHeight(heights);
            }
            dateFilterState = { active: false, hiddenRows: [], colKey: null, op: null, label: null, matched: 0 };
            updateFilterChip(0);
            if (notify) toast('Đã xoá lọc — hiện tất cả dòng.', 'info');
        };

        function updateFilterChip(matched) {
            const chip  = document.getElementById('filterChip');
            const badge = document.getElementById('dfBadge');
            if (dateFilterState.active) {
                chip.innerHTML = `<span class="filter-chip">
                    <i class="bi bi-funnel-fill"></i> ${dateFilterState.label} — <strong>${matched}</strong> dòng
                    <button type="button" onclick="clearDateFilter()" title="Xoá lọc"><i class="bi bi-x-circle-fill"></i></button>
                </span>`;
                badge.classList.remove('d-none');
                badge.textContent = matched;
            } else {
                chip.innerHTML = '';
                badge.classList.add('d-none');
            }
        }

        function setupDateFilter() {
            const op = document.getElementById('dfOperator');
            if (! op) return;
            op.addEventListener('change', () => {
                const v = op.value;
                document.getElementById('dfParamN').classList.toggle('d-none', v !== 'within_days');
                document.getElementById('dfParamRange').classList.toggle('d-none', v !== 'between');
            });
            document.getElementById('btnApplyFilter').addEventListener('click', applyDateFilter);
            document.getElementById('btnClearFilter').addEventListener('click', () => clearDateFilter());
        }

        // ===== Column visibility dropdown helpers =====
        window.toggleAllCols = function (show) {
            document.querySelectorAll('.col-pref-toggle:not(:disabled)').forEach(cb => cb.checked = show);
            updateCvCounters();
        };
        window.toggleGroupCols = function (gid) {
            const items = document.querySelectorAll(`.col-pref-toggle[data-group="${gid}"]:not(:disabled)`);
            const anyUnchecked = Array.from(items).some(i => ! i.checked);
            items.forEach(i => i.checked = anyUnchecked);
            updateCvCounters();
        };
        // Bỏ ẩn tất cả — reset về mặc định (chỉ enable cột chưa bị admin khoá)
        window.resetUserPrefs = function () {
            document.querySelectorAll('.col-pref-toggle:not(:disabled)').forEach(cb => cb.checked = true);
            updateCvCounters();
        };

        // Cập nhật counter tổng + theo nhóm
        function updateCvCounters() {
            let total = 0, shown = 0;
            const byGroup = { 1: {t:0,s:0}, 2: {t:0,s:0}, 3: {t:0,s:0}, 4: {t:0,s:0} };
            document.querySelectorAll('.col-pref-toggle').forEach(cb => {
                if (cb.disabled) return;
                const g = cb.dataset.group;
                total++; byGroup[g].t++;
                if (cb.checked) { shown++; byGroup[g].s++; }
            });
            const totalEl = document.getElementById('cvShownNum');
            const badgeEl = document.getElementById('colsCountBadge');
            if (totalEl) totalEl.textContent = shown;
            if (badgeEl) badgeEl.textContent = shown;
            Object.entries(byGroup).forEach(([g, v]) => {
                const el = document.getElementById('cvCount_' + g);
                if (el) el.textContent = `${v.s}/${v.t}`;
            });
        }

        // Search filter
        function setupCvSearch() {
            const input = document.getElementById('cvSearch');
            if (! input) return;
            input.addEventListener('input', (e) => {
                const q = e.target.value.trim().toLowerCase();
                let anyMatch = false;
                document.querySelectorAll('.cv-row').forEach(row => {
                    const match = ! q || row.dataset.name.includes(q);
                    row.style.display = match ? '' : 'none';
                    if (match) anyMatch = true;
                });
                // Ẩn nhóm nếu không có row nào hiện
                document.querySelectorAll('[data-group-section]').forEach(sec => {
                    const visibleRows = sec.querySelectorAll('.cv-row:not([style*="display: none"])').length;
                    sec.style.display = visibleRows > 0 ? '' : 'none';
                });
                document.getElementById('cvEmpty').classList.toggle('d-none', anyMatch);
            });
        }

        function buildCellData(rows, sheetOrder) {
            const celldata = [];
            const isReadonly = (c) => c.readonly || COLUMN_PERMS[c.key] === 'view';
            const showLockIcon = (c) => COLUMN_PERMS[c.key] === 'view';

            // BAKED-IN OVERLAY: merge formatting (bg, fc, font...) trực tiếp vào celldata
            // → bg là part của initial render, KHÔNG cần setCellFormat post-render
            // → tránh race condition + canvas redraw lag.
            const overlayMap = new Map();   // id → Map(colKey → fmt)
            if (snapshot?.formatting && sheetOrder != null) {
                const dir = sheetOrder === 0 ? 'import' : 'export';
                (snapshot.formatting[dir] || []).forEach(entry => {
                    if (! entry.id) return;
                    const id = parseInt(entry.id);
                    if (! overlayMap.has(id)) overlayMap.set(id, new Map());
                    overlayMap.get(id).set(entry.col, entry.fmt || {});
                });
            }

            // Header row 0
            COLS.forEach((c, ci) => {
                const headerTitle = showLockIcon(c) ? '🔒 ' + c.title : c.title;
                celldata.push({
                    r: 0, c: ci,
                    v: {
                        v: headerTitle, m: headerTitle,
                        bl: 1,
                        bg: GROUP_HEADER_BG[c.group] || '#e1e6f1',
                        fc: '#000000',
                        ht: 0, vt: 0,
                        tb: 2,
                    }
                });
            });

            rows.forEach((row, ri) => {
                const rowOverlay = row.id != null ? overlayMap.get(parseInt(row.id)) : null;
                const rowFormulas = (row.cell_formulas && typeof row.cell_formulas === 'object')
                    ? row.cell_formulas : null;

                COLS.forEach((c, ci) => {
                    const raw = row[c.key];
                    const isEmpty = raw == null || raw === '';
                    const readonly = isReadonly(c);
                    const overlayFmt = rowOverlay?.get(c.key) || null;
                    const formula = rowFormulas?.[c.key] || null;

                    // Skip cell editable rỗng + KHÔNG có overlay format + KHÔNG có formula
                    // (Cell có formula nhưng v=null vẫn phải render để giữ formula!)
                    if (isEmpty && ! readonly && ! overlayFmt && ! formula) return;

                    let displayed;
                    if (c.type === 'vnd')        displayed = fmtVND(raw);
                    else if (c.type === 'number') displayed = fmtNumber(raw);
                    else if (c.type === 'date')   displayed = fmtDate(raw);
                    else                          displayed = (raw == null ? '' : String(raw));

                    const cell = {
                        v: isEmpty ? '' : raw,
                        m: displayed,
                        ct: ctFor(c.type),
                        tb: 2,
                        vt: 0,
                    };
                    if (c.type === 'vnd' || c.type === 'number') cell.ht = 2;

                    // Restore formula — giữ v/m (cached) để cell hiển thị ngay.
                    // Recompute thực sự được trigger ở workbookCreateAfter qua setCellValue
                    // (xem renderSheet > hook > workbookCreateAfter).
                    if (formula) cell.f = formula;

                    if (readonly) {
                        cell.bg = '#f4f6fb';
                        cell.fc = '#7987a1';
                        cell.lo = 1;
                    }

                    // Apply overlay LAST → ghi đè default styling (vd user override readonly bg)
                    // SKIP layout defaults (vt, tb, ht) — old snapshot có thể chứa noise
                    // từ trước fix, các keys này không nên override defaults của buildCellData.
                    if (overlayFmt) {
                        Object.entries(overlayFmt).forEach(([k, v]) => {
                            if (k === 'vt' || k === 'tb' || k === 'ht') return;
                            cell[k] = v;
                        });
                    }

                    celldata.push({ r: ri + 1, c: ci, v: cell });
                });
            });
            return celldata;
        }

        // Re-register formulas vào Luckysheet's calcChain (dependency graph).
        // Khi load lại từ DB, celldata có cell.f nhưng Luckysheet 2.1.x đôi khi
        // KHÔNG auto-register vào calcChain → khi user edit cell phụ thuộc, formula
        // không auto-recompute live. Fix: setCellValue lại formula như STRING ("=SUM..")
        // → Luckysheet parse, register calcChain, compute.
        function recomputeAllFormulas() {
            const sheets = luckysheet.getAllSheets();
            if (! Array.isArray(sheets) || sheets.length === 0) return;

            // Collect (r,c,f,sheetOrder) trước khi setCellValue (tránh mutation lúc duyệt).
            const targets = [];
            sheets.forEach((sheet, sheetOrder) => {
                if (! Array.isArray(sheet.celldata)) return;
                sheet.celldata.forEach(cd => {
                    const cell = cd?.v;
                    if (! cell || typeof cell.f !== 'string' || cell.f.length === 0) return;
                    targets.push({ r: cd.r, c: cd.c, f: cell.f, order: sheetOrder });
                });
            });
            if (targets.length === 0) return;

            // Wrap _systemUpdate để không markDirty.
            _systemUpdate = true;
            try {
                targets.forEach(t => {
                    try {
                        // Truyền formula STRING (bắt đầu '=') → Luckysheet parse + register calcChain.
                        luckysheet.setCellValue(t.r, t.c, t.f, { order: t.order });
                    } catch (e) {
                        console.warn(`recomputeFormula r${t.r}c${t.c} failed:`, e);
                    }
                });
            } finally {
                setTimeout(() => { _systemUpdate = false; }, 80);
            }
        }

        // Parse value từ cell — ưu tiên v (giá trị gốc), fallback m (hiển thị)
        function parseCellValue(cell, type) {
            if (!cell) return '';
            // Ưu tiên v (giá trị gốc number/string), fallback m (hiển thị có format)
            let raw = cell.v;
            if (raw === undefined || raw === null) raw = cell.m;
            if (raw === undefined || raw === null) return '';

            const s = String(raw).trim();
            if (s === '') return '';

            if (type === 'vnd' || type === 'number') {
                // Bỏ "VNĐ", dấu phẩy, khoảng trắng → số
                const n = parseFloat(s.replace(/[^0-9.\-]/g, ''));
                return isNaN(n) ? null : n;
            }
            if (type === 'date') {
                // Có thể là dd/MM/yyyy, yyyy-MM-dd, hoặc Excel serial
                if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
                const m = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
                if (m) return `${m[3]}-${m[2].padStart(2,'0')}-${m[1].padStart(2,'0')}`;
                return s;  // backend parseDate sẽ thử nhiều format khác
            }
            return s;
        }

        function readSheetRows(sheetIndex) {
            return readSheetRowsWithMeta(sheetIndex).map(m => m.data);
        }

        // Trả về [{data, sheetRow}] đọc từ CẢ data[] và celldata[].
        //
        // Luckysheet đôi khi lưu cell mới user gõ vào celldata mà KHÔNG extend data[].
        // Nếu chỉ đọc data[] → miss rows → save không thấy thay đổi của user
        // (bug 'Không có thay đổi cần lưu' khi user thực tế có gõ).
        function readSheetRowsWithMeta(sheetIndex) {
            const sheets = luckysheet.getAllSheets();
            const sheet  = sheets[sheetIndex];
            if (! sheet) return [];

            // Index celldata vào Map theo "r:c" để lookup nhanh
            const cdMap = new Map();
            if (Array.isArray(sheet.celldata)) {
                sheet.celldata.forEach(cd => {
                    if (cd && cd.r != null && cd.c != null) {
                        cdMap.set(`${cd.r}:${cd.c}`, cd.v);
                    }
                });
            }

            // Tập row indices từ BOTH data[] và celldata[]
            const rowIndices = new Set();
            if (Array.isArray(sheet.data)) {
                for (let r = 1; r < sheet.data.length; r++) {
                    if (sheet.data[r]) rowIndices.add(r);
                }
            }
            cdMap.forEach((_, key) => {
                const r = parseInt(key.split(':')[0]);
                if (r > 0) rowIndices.add(r);
            });

            const rows = [];
            Array.from(rowIndices).sort((a, b) => a - b).forEach(r => {
                const obj = {};
                const formulas = {};
                COLS.forEach((c, ci) => {
                    // Ưu tiên data[r][ci], fallback celldata
                    let cell = sheet.data?.[r]?.[ci];
                    if (! cell || (cell.v == null && cell.m == null)) {
                        cell = cdMap.get(`${r}:${ci}`);
                    }
                    let v = parseCellValue(cell, c.type);
                    if (c.key === 'id') v = v === '' ? null : (parseInt(v) || null);
                    obj[c.key] = v;

                    // Bắt formula nếu cell có cell.f = "=SUM(...)" (Luckysheet lưu vào field f)
                    if (cell && typeof cell.f === 'string' && cell.f.length > 0 && c.key !== 'id') {
                        formulas[c.key] = cell.f;
                    }
                });
                // cell_formulas: chỉ gắn khi có ít nhất 1 formula → backend null hóa khi empty
                obj.cell_formulas = Object.keys(formulas).length > 0 ? formulas : null;
                rows.push({ data: obj, sheetRow: r });
            });
            return rows;
        }

        // ===== Formatting overlay — persist manual styling (bg color, font, etc.) =====
        // Khác snapshot cũ: chỉ lưu CELL STYLE, không lưu row data.
        // Anchored theo (id, colKey) thay vì (sheetRow, colIdx) để không bị drift
        // khi rows reorder/insert/delete.
        //
        // CHỈ persist USER-MEANINGFUL styles. Skip layout defaults (vt, tb, ht) vì:
        // 1. Chúng đã được buildCellData tự set mỗi lần render (vt:0, tb:2, ht:2 cho number).
        // 2. Capture chúng → bloat snapshot (hàng trăm entries vô nghĩa).
        // 3. applyFormattingOverlay gọi setCellFormat(vt=0) sau setCellFormat(bg=...)
        //    đôi khi RESET bg → bg mất sau reload!
        const STYLE_KEYS = ['bg', 'fc', 'bd', 'bl', 'it', 'un', 'cl', 'fs', 'ff'];

        function extractFormatting(sheetOrder) {
            try {
                const idColIdx = COLS.findIndex(c => c.key === 'id');
                if (idColIdx < 0) return [];
                const sheet = luckysheet.getAllSheets()[sheetOrder];
                if (! sheet) return [];

                // Build map sheetRow → id (đọc từ CẢ data + celldata)
                const rowToId = new Map();
                if (Array.isArray(sheet.data)) {
                    for (let r = 1; r < sheet.data.length; r++) {
                        const cell = sheet.data[r]?.[idColIdx];
                        const v = cell?.v ?? cell?.m;
                        if (v != null && v !== '') rowToId.set(r, parseInt(v));
                    }
                }
                if (Array.isArray(sheet.celldata)) {
                    sheet.celldata.forEach(cd => {
                        if (cd.c === idColIdx && cd.v) {
                            const v = cd.v.v ?? cd.v.m;
                            if (v != null && v !== '' && ! rowToId.has(cd.r)) {
                                rowToId.set(cd.r, parseInt(v));
                            }
                        }
                    });
                }

                // Merge cells từ CẢ celldata + data — KHÔNG dedup (bg có thể nằm ở
                // 1 nguồn mà không nguồn còn lại → cần merge để không miss).
                const cellMap = new Map();   // "r:c" → {id, col, fmt}

                const processCell = (r, c, cellV) => {
                    if (! cellV || r === 0) return;
                    const col = COLS[c];
                    if (! col || col.readonly) return;
                    if (COLUMN_PERMS[col.key] === 'view') return;
                    const id = rowToId.get(r);
                    if (! id) return;

                    const fmt = {};
                    STYLE_KEYS.forEach(k => {
                        if (cellV[k] !== undefined && cellV[k] !== null && cellV[k] !== '') {
                            fmt[k] = cellV[k];
                        }
                    });
                    if (Object.keys(fmt).length === 0) return;

                    const key = `${r}:${c}`;
                    const existing = cellMap.get(key);
                    if (existing) {
                        // Merge — last source wins per key
                        cellMap.set(key, { id, col: col.key, fmt: { ...existing.fmt, ...fmt } });
                    } else {
                        cellMap.set(key, { id, col: col.key, fmt });
                    }
                };

                // Process data[] FIRST (raw store), then celldata (sparse — usually has more recent updates)
                if (Array.isArray(sheet.data)) {
                    for (let r = 1; r < sheet.data.length; r++) {
                        const row = sheet.data[r];
                        if (! row) continue;
                        for (let c = 0; c < row.length; c++) {
                            processCell(r, c, row[c]);
                        }
                    }
                }
                if (Array.isArray(sheet.celldata)) {
                    sheet.celldata.forEach(cd => processCell(cd.r, cd.c, cd.v));
                }

                return Array.from(cellMap.values());
            } catch (e) { console.warn('extractFormatting failed:', e); return []; }
        }

        // Debug tool — user gõ vào console để check bg flow đang đứng ở bước nào
        window._debugBg = function() {
            const out = {
                can_save_formatting: CAN_SAVE_FORMATTING,
                admin_restricted: ADMIN_RESTRICTED,
                user_hidden_count: USER_HIDDEN.size,
                snapshot_has_formatting: !! snapshot?.formatting,
                snapshot_formatting: snapshot?.formatting,
                extract_import: extractFormatting(0),
                extract_export: extractFormatting(1),
                sheet_count: luckysheet.getAllSheets().length,
                celldata_with_bg: luckysheet.getAllSheets()[0]?.celldata
                    ?.filter(cd => cd.v?.bg)
                    ?.slice(0, 10)
                    ?.map(cd => ({ r: cd.r, c: cd.c, bg: cd.v.bg })),
                data_with_bg: (() => {
                    const sheet = luckysheet.getAllSheets()[0];
                    if (! sheet?.data) return [];
                    const out = [];
                    for (let r = 1; r < sheet.data.length && out.length < 10; r++) {
                        const row = sheet.data[r];
                        if (! row) continue;
                        for (let c = 0; c < row.length; c++) {
                            if (row[c]?.bg && row[c].bg !== '#f4f6fb') {
                                out.push({ r, c, bg: row[c].bg });
                            }
                        }
                    }
                    return out;
                })(),
            };
            console.log('=== BG DEBUG ===');
            console.log(JSON.stringify(out, null, 2));
            return out;
        };

        // Debug — fetch raw /data từ server để xem snapshot backend đang trả gì
        window._debugServerSnapshot = async function() {
            const res = await fetch(ROUTES.data, { headers: { 'Accept': 'application/json' } });
            const j = await res.json();
            console.log('=== SERVER SNAPSHOT ===');
            console.log('snapshot:', j.snapshot);
            console.log('snapshot.formatting?', j.snapshot?.formatting);
            if (j.snapshot?.formatting) {
                console.log('import entries:', j.snapshot.formatting.import?.length || 0);
                console.log('first import entry with bg:',
                    j.snapshot.formatting.import?.find(e => e.fmt?.bg));
            }
            return j.snapshot;
        };

        function applyFormattingOverlay(sheetOrder, overlay) {
            if (! Array.isArray(overlay) || overlay.length === 0) return;
            try {
                const idColIdx = COLS.findIndex(c => c.key === 'id');
                if (idColIdx < 0) return;
                const sheet = luckysheet.getAllSheets()[sheetOrder];
                if (! sheet?.data) return;

                const idToRow = new Map();
                for (let r = 1; r < sheet.data.length; r++) {
                    const cell = sheet.data[r]?.[idColIdx];
                    const v = cell?.v ?? cell?.m;
                    if (v != null && v !== '') idToRow.set(parseInt(v), r);
                }
                if (Array.isArray(sheet.celldata)) {
                    sheet.celldata.forEach(cd => {
                        if (cd.c === idColIdx && cd.v) {
                            const v = cd.v.v ?? cd.v.m;
                            if (v != null && v !== '' && ! idToRow.has(parseInt(v))) {
                                idToRow.set(parseInt(v), cd.r);
                            }
                        }
                    });
                }

                overlay.forEach(entry => {
                    const sheetRow = idToRow.get(parseInt(entry.id));
                    if (sheetRow == null) return;
                    const colIdx = COLS.findIndex(c => c.key === entry.col);
                    if (colIdx < 0) return;

                    Object.entries(entry.fmt || {}).forEach(([k, v]) => {
                        // SKIP layout defaults — chúng RESET bg/fc khi setCellFormat
                        // (Luckysheet 2.1.13 quirk). Bg/fc đã được baked-in qua buildCellData.
                        if (k === 'vt' || k === 'tb' || k === 'ht') return;
                        try {
                            markSystemCell(sheetOrder, sheetRow, colIdx, 400);
                            luckysheet.setCellFormat(sheetRow, colIdx, k, v, { order: sheetOrder });
                        } catch (e) {}
                    });
                });
            } catch (e) { console.warn('applyFormattingOverlay failed:', e); }
        }

        // So sánh 2 giá trị cell — handle null/empty/number/string normalize
        function cellValuesEqual(a, b) {
            const norm = (x) => {
                if (x == null || x === '') return '';
                if (typeof x === 'number') return String(x);
                return String(x).trim();
            };
            return norm(a) === norm(b);
        }

        // Build lookup map: id → row data từ rowsByDir cho safety net comparison
        function buildDbRowsById(direction) {
            const map = new Map();
            const rows = rowsByDir[direction] || [];
            (Array.isArray(rows) ? rows : Array.from(rows)).forEach(r => {
                if (r.id != null) map.set(parseInt(r.id), r);
            });
            return map;
        }

        let rowsByDir = { import: [], export: [] };
        let snapshot = null;
        let sheetVersion = 0;

        // Track IDs hiện có trong DB lúc load → diff với currentIds lúc save để
        // tìm rows user đã xóa khỏi sheet (Luckysheet's delete row chỉ remove
        // visually, không có hook nên dùng diff approach).
        let _originalIds = { import: new Set(), export: new Set() };

        function recordOriginalIds() {
            _originalIds = { import: new Set(), export: new Set() };
            ['import', 'export'].forEach(dir => {
                (rowsByDir[dir] || []).forEach(r => {
                    if (r.id != null) _originalIds[dir].add(parseInt(r.id));
                });
            });
        }

        async function loadData() {
            const res  = await fetch(ROUTES.data, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            rowsByDir    = json.data;
            snapshot     = json.snapshot;
            sheetVersion = json.version ?? 0;
            renderSheet();
            updateVersionBadge(json.editor, json.updatedAt);

            // Apply formatting overlay via setCellFormat — Luckysheet đôi khi strip bg
            // khỏi celldata sau create() dù bake-in đã set. setCellFormat ép sync vào
            // CẢ data[] lẫn celldata + trigger canvas redraw cell.
            // Đã filter vt/tb/ht trong applyFormattingOverlay nên không reset bg.
            if (snapshot?.formatting) {
                setTimeout(() => {
                    applyFormattingOverlay(0, snapshot.formatting.import || []);
                    applyFormattingOverlay(1, snapshot.formatting.export || []);
                    // Force canvas redraw final (no-op sheet switch)
                    try {
                        const cur = luckysheet.getSheet()?.order ?? 0;
                        luckysheet.setSheetActive(cur);
                    } catch (e) {}
                }, 300);
            }

            recordOriginalIds();
            resetDirty();
            _needsResync = false;
        }

        /**
         * Soft merge — fetch data mới + setCellValue per cell thay đổi.
         * KHÔNG destroy/recreate Luckysheet → giữ scroll, selection, dirty cells.
         *
         * - Diff old vs new rowsByDir theo id
         * - Cell thay đổi: setCellValue với per-cell marker (không trigger dirty)
         * - Cell user đang sửa (dirty): SKIP, giữ nguyên công sửa
         * - Row mới: append vào cuối sheet
         * - Row bị xóa: tạm thời skip (rare case, manual reload nếu cần)
         *
         * @returns {Promise<{updated: number, appended: number, skippedDirty: number}>}
         */
        // Flash cell silent (không toast) — visual cue + force canvas redraw
        // (setCellFormat trigger redraw cell ngay, khắc phục việc setCellValue không
        // luôn redraw kịp khi update nhiều cell trong softMerge).
        function flashCellSilent(sheetOrder, row, col) {
            try {
                const sheet = luckysheet.getluckysheetfile()[sheetOrder];
                if (! sheet?.data?.[row]?.[col]) return;
                const oldBg = sheet.data[row][col].bg;
                markSystemCell(sheetOrder, row, col, 200);
                luckysheet.setCellFormat(row, col, 'bg', '#dbeafe', { order: sheetOrder });   // xanh nhạt
                setTimeout(() => {
                    markSystemCell(sheetOrder, row, col, 200);
                    try { luckysheet.setCellFormat(row, col, 'bg', oldBg || null, { order: sheetOrder }); } catch (e) {}
                }, 1500);
            } catch (e) {}
        }

        async function softMerge() {
            try {
                const res = await fetch(ROUTES.data, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                const newRowsByDir = json.data;
                sheetVersion = json.version ?? sheetVersion;

                let updated = 0, appended = 0, deleted = 0, skippedDirty = 0;
                const changedSheets = new Set();   // sheet orders có changes — để force redraw sau

                [0, 1].forEach((sheetOrder) => {
                    const dir = sheetOrder === 0 ? 'import' : 'export';
                    const newRows = (newRowsByDir[dir] || []).filter(r => r.id != null);
                    const newById = new Map(newRows.map(r => [parseInt(r.id), r]));
                    const newIdsSet = new Set(newRows.map(r => parseInt(r.id)));
                    const toDeleteSheetRows = [];   // sheet rows cần xóa (user khác đã xóa rows này)

                    const rendered = readSheetRowsWithMeta(sheetOrder);
                    const dirtyMap = dirtyCells[sheetOrder] || new Map();
                    const renderedIds = new Set();
                    let lastSheetRow = 0;

                    // PASS 1: Update cells của existing rows (skip dirty cells)
                    rendered.forEach(m => {
                        const id = m.data.id != null ? parseInt(m.data.id) : null;
                        // lastSheetRow chỉ tính rows CÓ CONTENT thật (id hoặc value).
                        // KHÔNG tính empty styled rows (Luckysheet auto-expand đến row 80
                        // với data[r] truthy nhưng cells trống) — nếu không sẽ append
                        // new row tại row 81+ → quá xa, user không thấy.
                        const hasContent = id != null || Object.keys(m.data).some(k => {
                            const v = m.data[k];
                            return v != null && v !== '' && k !== 'id';
                        });
                        if (hasContent && m.sheetRow > lastSheetRow) lastSheetRow = m.sheetRow;
                        if (! id) return;
                        renderedIds.add(id);

                        const newRow = newById.get(id);
                        if (! newRow) {
                            // Row đã bị user khác XÓA khỏi DB → mark để xóa khỏi sheet.
                            // SKIP nếu user mình đang editing (có dirty cells) — preserve work,
                            // backend dedup khi save sẽ tạo thành row mới.
                            const rowDirtyKeys = dirtyMap.get(m.sheetRow);
                            if (! rowDirtyKeys || rowDirtyKeys.size === 0) {
                                toDeleteSheetRows.push(m.sheetRow);
                            }
                            return;
                        }

                        const rowDirtyKeys = dirtyMap.get(m.sheetRow) || new Set();

                        COLS.forEach((col, ci) => {
                            if (col.readonly) return;                          // skip id, etc.
                            if (rowDirtyKeys.has(col.key)) { skippedDirty++; return; }   // PRESERVE user edit

                            const newVal = newRow[col.key];
                            const curVal = m.data[col.key];
                            if (! cellValuesEqual(newVal, curVal)) {
                                try {
                                    markSystemCell(sheetOrder, m.sheetRow, ci, 500);
                                    luckysheet.setCellValue(m.sheetRow, ci, newVal ?? '', { order: sheetOrder });
                                    flashCellSilent(sheetOrder, m.sheetRow, ci);   // force redraw + visual cue
                                    updated++;
                                    changedSheets.add(sheetOrder);
                                } catch (e) { console.warn('softMerge update cell failed:', e); }
                            }
                        });
                    });

                    // PASS 2: Append new rows (id chưa có trong rendered)
                    const toAppend = newRows.filter(r => ! renderedIds.has(parseInt(r.id)));
                    toAppend.forEach((row, i) => {
                        const targetRow = lastSheetRow + 1 + i;
                        COLS.forEach((col, ci) => {
                            const val = row[col.key];
                            if (val == null || val === '') return;
                            try {
                                markSystemCell(sheetOrder, targetRow, ci, 500);
                                luckysheet.setCellValue(targetRow, ci, val, { order: sheetOrder });
                                flashCellSilent(sheetOrder, targetRow, ci);
                            } catch (e) { console.warn('softMerge append cell failed:', e); }
                        });
                        appended++;
                        changedSheets.add(sheetOrder);
                    });

                    // PASS 3: Delete rows mà user khác đã xóa khỏi DB.
                    // Sort DESC để delete từ dưới lên — tránh index shift làm sai các delete sau.
                    if (toDeleteSheetRows.length > 0) {
                        toDeleteSheetRows.sort((a, b) => b - a);
                        toDeleteSheetRows.forEach(r => {
                            try {
                                // Mark mọi cell trong row là system update → bypass hook markDirty
                                for (let c = 0; c < COLS.length; c++) markSystemCell(sheetOrder, r, c, 800);
                                luckysheet.deleteRowCol('row', r, 1, { order: sheetOrder });
                                deleted++;
                                changedSheets.add(sheetOrder);
                            } catch (e) {
                                // Fallback: nếu deleteRowCol fail (API khác version), clear cells
                                console.warn('softMerge deleteRowCol failed, fallback clear:', e);
                                try {
                                    for (let c = 0; c < COLS.length; c++) {
                                        markSystemCell(sheetOrder, r, c, 500);
                                        luckysheet.setCellValue(r, c, '', { order: sheetOrder });
                                    }
                                    deleted++;
                                    changedSheets.add(sheetOrder);
                                } catch (e2) { console.warn('softMerge clear fallback failed:', e2); }
                            }
                        });
                    }
                });

                // Force canvas redraw cho các sheet có changes — đảm bảo user thấy update
                // ngay cả khi nhiều cell update batch lại
                if (changedSheets.size > 0) {
                    setTimeout(() => {
                        try {
                            const curOrder = luckysheet.getSheet()?.order ?? 0;
                            // Re-set sheet active (no-op switch) để Luckysheet flush render
                            luckysheet.setSheetActive(curOrder);
                        } catch (e) {}
                    }, 100);
                }

                // Update local cache + badge + originalIds (cho diff deletion lần save kế)
                rowsByDir = newRowsByDir;
                snapshot  = json.snapshot;
                recordOriginalIds();
                updateVersionBadge(json.editor, json.updatedAt);

                // Apply formatting overlay (bg colors etc.) — không destroy sheet
                if (json.snapshot?.formatting) {
                    applyFormattingOverlay(0, json.snapshot.formatting.import || []);
                    applyFormattingOverlay(1, json.snapshot.formatting.export || []);
                }

                return { updated, appended, deleted, skippedDirty };
            } catch (e) {
                console.warn('softMerge failed, fallback loadData:', e);
                await loadData();
                return { updated: 0, appended: 0, deleted: 0, skippedDirty: 0, fallback: true };
            }
        }

        function updateVersionBadge(editor, at) {
            const badge = document.getElementById('versionBadge');
            if (editor) {
                const t = at ? new Date(at).toLocaleString('vi-VN') : '';
                badge.innerHTML = `<i class="bi bi-clock-history"></i> v${sheetVersion} · ${editor.name} · ${t}`;
                badge.className = 'badge badge-soft-primary';
            } else {
                badge.innerHTML = `<i class="bi bi-clock-history"></i> Chưa có snapshot`;
                badge.className = 'badge badge-soft-warning';
            }
        }

        // Build columnlen cho 1 sheet — apply user-customized widths từ snapshot
        // (anchored theo col KEY, không theo index → portable giữa users với COLS khác nhau).
        function buildColumnlen(sheetDir) {
            const columnlen = COLS.reduce((acc, c, i) => (acc[i] = c.width, acc), {});
            const overlay = snapshot?.columnlen?.[sheetDir];
            if (overlay && typeof overlay === 'object') {
                COLS.forEach((c, i) => {
                    if (overlay[c.key] != null) columnlen[i] = overlay[c.key];
                });
            }
            return columnlen;
        }

        // Extract column widths hiện tại của sheet — keyed theo col KEY
        function extractColumnWidths(sheetOrder) {
            const sheet = luckysheet.getAllSheets()[sheetOrder];
            if (! sheet?.config?.columnlen) return {};
            const cl = sheet.config.columnlen;
            const result = {};
            Object.entries(cl).forEach(([idx, width]) => {
                const col = COLS[parseInt(idx)];
                if (col && col.key && width != null) result[col.key] = width;
            });
            return result;
        }

        function renderSheet() {
            const columnlenImport = buildColumnlen('import');
            const columnlenExport = buildColumnlen('export');

            // Border đen mảnh cho toàn bộ cell — style 1 = thin solid
            const makeBorderInfo = (totalRows) => ([{
                rangeType: 'range',
                borderType: 'border-all',
                style:  '1',
                color:  '#000000',
                range: [{ row: [0, totalRows - 1], column: [0, COLS.length - 1] }],
            }]);

            const importRowCount = Math.max((rowsByDir.import?.length || 0) + 50, 80);
            const exportRowCount = Math.max((rowsByDir.export?.length || 0) + 50, 80);

            const importSheet = {
                name: 'HÀNG NHẬP', order: 0, color: '#24d39f',
                config: {
                    columnlen: columnlenImport, rowlen: { 0: 48 }, customHeight: { 0: 1 },
                    borderInfo: makeBorderInfo(importRowCount),
                },
                celldata: buildCellData(rowsByDir.import || [], 0),
                row: importRowCount,
                column: COLS.length + 1,
                frozen: { type: 'rangeBoth', range: { row_focus: 0, column_focus: 1 } },
                defaultRowHeight: 36,
            };
            const exportSheet = {
                name: 'HÀNG XUẤT', order: 1, color: '#0153a9',
                config: {
                    columnlen: columnlenExport, rowlen: { 0: 48 }, customHeight: { 0: 1 },
                    borderInfo: makeBorderInfo(exportRowCount),
                },
                celldata: buildCellData(rowsByDir.export || [], 1),
                row: exportRowCount,
                column: COLS.length + 1,
                frozen: { type: 'rangeBoth', range: { row_focus: 0, column_focus: 1 } },
                defaultRowHeight: 36,
            };

            // [2026-05-23] Bỏ snapshot mechanism — tất cả user dùng buildCellData(rowsByDir).
            // Lý do: snapshot gây hàng loạt bug đồng bộ (stale rows, missing borders,
            // mismatch giữa users, augment workaround...). Dùng DB là single source of
            // truth → 100% consistency. Trade-off: mất manual cell formatting (bg, border
            // custom), nhưng use case logistics chỉ cần data entry, không dùng formatting.
            const sheets = [importSheet, exportSheet];

            luckysheet.destroy();
            luckysheet.create({
                container: 'luckysheet',
                lang: 'en',
                showinfobar: false,
                showsheetbar: true,
                showstatisticBar: false,
                enableAddRow: true,
                enableAddBackTop: false,
                allowEdit: true,
                data: sheets,
                // Hide toolbar features không persist hoặc không phù hợp logistics tracking.
                // Persist: bg/fc/bold/italic/underline/strike/font/size/align/border.
                // KHÔNG persist (ẩn): image, chart, postil, conditionalFormat, pivotTable.
                showtoolbarConfig: {
                    undoRedo:           true,
                    paintFormat:        true,
                    currencyFormat:     true,
                    percentageFormat:   true,
                    numberDecrease:     true,
                    numberIncrease:     true,
                    moreFormats:        true,
                    font:               true,
                    fontSize:           true,
                    bold:               true,
                    italic:             true,
                    strikethrough:      true,
                    underline:          true,
                    textColor:          true,
                    fillColor:          true,
                    border:             true,
                    mergeCell:          true,
                    horizontalAlignMode: true,
                    verticalAlignMode:   true,
                    textWrapMode:       true,
                    textRotateMode:     false,   // hiếm dùng cho logistics
                    image:              false,   // không cần ảnh trong sheet data
                    link:               false,   // không persist tốt
                    chart:              false,   // không phù hợp use case
                    postil:             false,   // comment KHÔNG persist qua save
                    pivotTable:         false,   // không phù hợp
                    function:           false,   // hiếm dùng, dễ gây nhầm cell
                    frozenMode:         true,
                    sortAndFilter:      false,   // KHÔNG persist + di chuyển cells phá row id mapping
                    conditionalFormat:  false,   // KHÔNG persist qua save
                    dataVerification:   true,    // dùng cho dropdown NCC
                    splitColumn:        false,   // hiếm dùng
                    screenshot:         false,   // không cần
                    findAndReplace:     true,
                    protection:         false,   // đã handle qua code permissions
                    print:              true,
                },
                hook: {
                    workbookCreateAfter() {
                        applySupplierDropdown();
                        applyDateValidation();
                        // Formatting overlay đã được bake-in qua buildCellData → không cần
                        // applyFormattingOverlay ở đây (setCellFormat có thể reset bg do
                        // Luckysheet quirk khi gọi cho cell vừa render). Giữ overlay cho
                        // softMerge dynamic update khi user khác đổi format realtime.

                        // Recompute formulas — Luckysheet dùng cached v khi render init,
                        // không re-evaluate. Trigger compute = setCellValue lại f cho mỗi
                        // cell có formula (đẩy qua formula engine).
                        try { recomputeAllFormulas(); } catch (e) { console.warn('recomputeAllFormulas failed:', e); }
                    },
                    // Helper kiểm cột readonly bằng index c
                    // (COLS đã filter theo perms, nên COLS[c] tương ứng đúng cột hiển thị tại index c)
                    // Chặn nhập vào cột readonly (id auto-gen + admin set 'view')
                    cellEditBefore(range) {
                        const ranges = Array.isArray(range) ? range : [range];
                        for (const r of ranges) {
                            if (r == null) continue;
                            let startCol, endCol, startRow;
                            if (typeof r === 'number') {
                                startCol = endCol = r;
                                startRow = 0;
                            } else if (r.column) {
                                startCol = Array.isArray(r.column) ? r.column[0] : r.column;
                                endCol   = Array.isArray(r.column) ? (r.column[1] ?? startCol) : startCol;
                                startRow = Array.isArray(r.row) ? r.row[0] : (r.row ?? 0);
                            } else continue;

                            for (let c = startCol; c <= endCol; c++) {
                                const colDef = COLS[c];
                                if (! colDef) continue;
                                if (COLUMN_PERMS[colDef.key] === 'view') {
                                    toast(`Cột "<strong>${colDef.title}</strong>" chỉ xem, không thể sửa.`, 'warning');
                                    return false;
                                }
                                if (colDef.readonly) {
                                    toast(`Cột "<strong>${colDef.title}</strong>" tự động tạo, không sửa được.`, 'info');
                                    return false;
                                }
                            }
                        }
                    },
                    cellUpdateBefore(r, c, value, isRefresh) {
                        if (_systemUpdate || isRefresh) return;
                        const colDef = COLS[c];
                        if (! colDef) return;
                        // Chỉ chặn cứng cột admin-set 'view'. Cột readonly inherent (id) đã được
                        // cellEditBefore chặn user-edit → KHÔNG chặn ở đây để setCellValue programmatic chạy được.
                        if (COLUMN_PERMS[colDef.key] === 'view') {
                            return false;
                        }
                    },
                    cellUpdated(r, c, oldValue, newValue, isRefresh) {
                        if (isRefresh) return;
                        const sheetOrder = luckysheet.getSheet()?.order ?? 0;
                        // Per-cell check: bỏ qua CHỈ cell vừa được system update (không block cell khác)
                        if (isSystemCell(sheetOrder, r, c)) return;
                        // Legacy global flag — vẫn check để backward compat với code chưa migrate
                        if (_systemUpdate) return;

                        const colDef = COLS[c];
                        if (! colDef) return;

                        if (COLUMN_PERMS[colDef.key] === 'view') {
                            setTimeout(() => {
                                try {
                                    markSystemCell(sheetOrder, r, c, 200);
                                    luckysheet.setCellValue(r, c, oldValue?.v ?? oldValue?.m ?? '');
                                } catch (e) {}
                            }, 50);
                            return;
                        }

                        // Dirty tracker — mark cell vừa sửa để save handler gửi chỉ cell này.
                        // Skip cột readonly (id auto-gen) — không bao giờ là "thay đổi của user".
                        if (! colDef.readonly) {
                            markDirty(sheetOrder, r, colDef.key);
                            // LIVE REALTIME: whisper cell edit cho user khác thấy ngay (không cần save)
                            whisperCellEdit(sheetOrder, r, colDef.key);
                        }

                        if (colDef.key === 'id') {
                            setTimeout(scanDuplicateIds, 100);
                        }
                    },

                    // Paste: mark mọi cell trong range là dirty (cellUpdated có thể không fire kịp/all)
                    pasted(range) {
                        setTimeout(scanDuplicateIds, 150);
                        markRangeDirty(range);
                    },
                    pasteBefore(html, plain, range) {
                        setTimeout(scanDuplicateIds, 200);
                        markRangeDirty(range);
                        return undefined;
                    },
                }
            });
        }

        // Set data validation dropdown cho cột NCC (cả HÀNG NHẬP + HÀNG XUẤT)
        function applySupplierDropdown() {
            if (! SUPPLIERS || SUPPLIERS.length === 0) return;
            const colIndex = COLS.findIndex(c => c.key === 'supplier');
            if (colIndex < 0) return;   // user/admin đã ẩn cột supplier

            const valueList = SUPPLIERS.join(',');
            const allSheets = luckysheet.getAllSheets();
            allSheets.forEach((sheet, sheetOrder) => {
                const lastRow = Math.max((sheet.row ?? 80) - 1, 199);
                try {
                    luckysheet.setDataVerification({
                        type: 'dropdown',
                        type2: '',
                        value1: valueList,
                        value2: '',
                        validity: '',
                        remote: false,
                        prohibitInput: false,    // cho phép gõ tay NCC mới
                        hintShow: true,
                        hintText: 'Chọn NCC từ danh sách hoặc gõ tên mới',
                    }, {
                        range: { row: [1, lastRow], column: [colIndex, colIndex] },
                        order: sheetOrder,
                    });
                } catch (e) {
                    console.warn('Không apply được dropdown NCC cho sheet', sheetOrder, e);
                }
            });
        }

        // Set data verification 'date' cho TẤT CẢ cột date — user click ô trống thấy
        // date picker, đảm bảo input chuẩn YYYY-MM-DD, không cho gõ chuỗi tùy ý.
        function applyDateValidation() {
            const dateColIndexes = [];
            COLS.forEach((c, i) => {
                if (c.type === 'date') dateColIndexes.push({ idx: i, key: c.key, title: c.title });
            });
            if (dateColIndexes.length === 0) return;

            const allSheets = luckysheet.getAllSheets();
            allSheets.forEach((sheet, sheetOrder) => {
                const lastRow = Math.max((sheet.row ?? 80) - 1, 199);
                dateColIndexes.forEach(({ idx, title }) => {
                    try {
                        luckysheet.setDataVerification({
                            type:           'date',
                            type2:          'bw',                  // between dates
                            value1:         '1900-01-01',          // min
                            value2:         '2099-12-31',          // max
                            validity:       'Định dạng ngày không hợp lệ (YYYY-MM-DD).',
                            remote:         false,
                            prohibitInput:  false,                 // cho phép gõ tay
                            hintShow:       true,
                            hintText:       `📅 ${title}: chọn ngày từ lịch hoặc gõ YYYY-MM-DD`,
                        }, {
                            range: { row: [1, lastRow], column: [idx, idx] },
                            order: sheetOrder,
                        });
                    } catch (e) {
                        console.warn(`Không apply được date validation cho ${title} sheet ${sheetOrder}:`, e);
                    }
                });
            });
        }

        function setupRealtime() {
            try {
                // Echo đã được layout init sẵn (là instance, có .connector).
                // Defensive: nếu vì lý do gì chưa có instance → tự tạo.
                const isInstance = window.Echo && window.Echo.connector;
                if (! isInstance && typeof Echo === 'function') {
                    const EchoCtor = window.Echo;
                    window.Echo = new EchoCtor({
                        broadcaster: 'reverb',
                        key:     REVERB.key,
                        wsHost:  REVERB.host,
                        wsPort:  REVERB.port,
                        wssPort: REVERB.port,
                        forceTLS: REVERB.scheme === 'https',
                        enabledTransports: ['ws', 'wss'],
                        auth: { headers: { 'X-CSRF-TOKEN': CSRF } },
                    });
                }
                if (! window.Echo || ! window.Echo.connector) throw new Error('Echo unavailable');
                const status = document.getElementById('liveStatus');
                const pc = window.Echo.connector.pusher.connection;
                pc.bind('connected',    () => { status.innerHTML = '<i class="bi bi-wifi"></i> Live'; status.className = 'badge badge-soft-success'; });
                pc.bind('disconnected', () => { status.innerHTML = '<i class="bi bi-wifi-off"></i> Offline'; status.className = 'badge badge-soft-warning'; });
                pc.bind('error',        (e) => { console.warn('Echo error:', e); status.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Lỗi WS'; status.className = 'badge badge-soft-danger'; });

                _privateChan = window.Echo.private('items-sheet');

                // 1) Server-broadcast event: ai đó save xong
                // - toOthers() ở backend đã loại sender's socket (nhờ X-Socket-ID header trên save).
                //   Nên KHÔNG cần check editorId — multi-tab same user vẫn nhận event ở tab khác.
                // - Smart strategy: nếu user không có dirty cells → reload silent.
                //   Nếu có dirty → đặt flag _needsResync, reload sau khi user save xong.
                _privateChan.listen('.sheet.updated', async (e) => {
                    if (! e.sheetKey || ! e.sheetKey.endsWith(PERIOD)) return;

                    sheetVersion = e.version;
                    updateVersionBadge({ id: e.editorId, name: e.editorName }, new Date().toISOString());

                    // SOFT MERGE — không reload toàn sheet, chỉ update cells thay đổi.
                    // Cells user đang sửa (dirty) sẽ được GIỮ NGUYÊN, không bị đè.
                    const result = await softMerge();
                    const { updated, appended, deleted, skippedDirty, fallback } = result;

                    if (fallback) {
                        toast(`<i class="bi bi-person-fill-gear"></i> <strong>${e.editorName}</strong> vừa lưu (v${e.version}). Đã reload.`, 'info');
                        return;
                    }

                    // Hint sheet name nếu user đang ở sheet khác
                    const curOrder = luckysheet.getSheet()?.order ?? 0;
                    const sheetName = curOrder === 0 ? 'HÀNG NHẬP' : 'HÀNG XUẤT';

                    let msg = `<i class="bi bi-person-fill-gear"></i> <strong>${e.editorName}</strong> vừa lưu (v${e.version}).`;
                    const parts = [];
                    if (updated > 0)      parts.push(`<strong>${updated}</strong> cell update`);
                    if (appended > 0)     parts.push(`<strong>${appended}</strong> dòng mới`);
                    if (deleted > 0)      parts.push(`<strong>${deleted}</strong> dòng xóa`);
                    if (skippedDirty > 0) parts.push(`giữ ${skippedDirty} cell bạn đang sửa`);
                    if (parts.length > 0) {
                        msg += ' ' + parts.join(' • ') + '. ';
                        msg += `<small class="text-muted">(Có thể ở sheet khác — kiểm tra HÀNG NHẬP / HÀNG XUẤT)</small>`;
                    }
                    toast(msg, 'info');

                    if (updated + appended > 0) _showEditorActivity(e.editorName);
                });

                // 2) Client-event (whisper): A gõ cell → B nhận instant, không qua DB
                _privateChan.listenForWhisper('cell-edit', (e) => {
                    _rtStats.received++;
                    if (! e || e.period !== PERIOD) return;
                    if (e.editor?.id === CURRENT_USER.id) return;
                    applyRemoteCellEdit(e, e.editor?.name);
                });

                // 3) Batch whisper cho paste (1 event chứa nhiều edits)
                _privateChan.listenForWhisper('cell-batch', (e) => {
                    _rtStats.received++;
                    if (! e || e.period !== PERIOD || ! Array.isArray(e.edits)) return;
                    if (e.editor?.id === CURRENT_USER.id) return;
                    e.edits.forEach(edit => applyRemoteCellEdit(edit, e.editor?.name));
                });
            } catch (err) { console.warn('Realtime init failed:', err); }
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadData();
            setupRealtime();

            document.getElementById('btnReload').addEventListener('click', loadData);

            document.getElementById('btnSaveAll').addEventListener('click', async () => {
                // Confirm trước khi bulk save — đè snapshot + broadcast realtime cho mọi user
                const ok = await window.confirmAction({
                    title: 'Lưu toàn bộ thay đổi?',
                    text: 'Các chỉnh sửa hiện tại sẽ được ghi vào cơ sở dữ liệu và đồng bộ realtime cho mọi người đang xem.',
                    confirmText: '<i class="bi bi-save me-1"></i> Lưu ngay',
                });
                if (! ok) return;

                // Auto-clear filter trước save để không lưu row heights=0 vào snapshot
                if (dateFilterState.active) {
                    clearDateFilter(false);
                }

                // FORCE COMMIT cell đang edit — blur active element + click sheet để Luckysheet
                // flush in-progress edit vào data array. Không làm bước này → user gõ rồi bấm Lưu
                // ngay không kịp Enter/Tab → cellUpdated chưa fire → dirty tracker miss.
                try {
                    if (document.activeElement && document.activeElement.blur) {
                        document.activeElement.blur();
                    }
                } catch (e) {}

                // SAFETY NET: scan + clear duplicate No. trước khi build payload
                scanDuplicateIds();
                await new Promise(r => setTimeout(r, 300));   // dài hơn cho commit kịp

                // SNAPSHOT dirty TẠI t0 — selective clear sau khi save xong
                // (giữ dirty user tạo trong lúc fetch đang chạy)
                const dirtyAtStart = snapshotDirty();

                // ===== Build payload =====
                // - Row có id + dirty cells → partial update {id, ...dirty}
                // - Row có id, không dirty → SKIP
                // - Row không id + có client (dù dirty tracker miss vẫn được include — safety net)
                const dirtyMeta = [];
                const seenIds = new Set();
                let duplicateCount = 0;
                let newRowsRecovered = 0;   // đếm new row được phục hồi từ scan (không phải dirty tracker)

                let updatesRecovered = 0;   // đếm update phục hồi từ comparison (dirty tracker miss)

                [0, 1].forEach((sheetOrder) => {
                    const allRows = readSheetRowsWithMeta(sheetOrder);
                    const dirtyMap = dirtyAtStart[sheetOrder] || new Map();
                    const dbRowsById = buildDbRowsById(sheetOrder === 0 ? 'import' : 'export');

                    allRows.forEach(m => {
                        const data = m.data;
                        const hasId = data.id != null && data.id !== '';

                        // Dedup duplicate ID cross-sheet → coi như new row
                        let effectiveId = hasId ? data.id : null;
                        if (effectiveId && seenIds.has(effectiveId)) {
                            duplicateCount++;
                            effectiveId = null;
                        }
                        if (effectiveId) seenIds.add(effectiveId);

                        if (effectiveId) {
                            // UPDATE — gửi cell dirty + comparison safety net
                            const dirtyKeys = new Set(dirtyMap.get(m.sheetRow) || []);

                            // SAFETY NET: so giá trị cell hiện tại với DB row (rowsByDir).
                            // Nếu khác → mark dirty implicit. Cover case dirty tracker miss
                            // (vd: cellUpdated không fire, _systemUpdate timing, hoặc cell
                            // được lưu trong celldata mà không phải data[]).
                            const dbRow = dbRowsById.get(parseInt(effectiveId));
                            let formulasDirty = false;
                            if (dbRow) {
                                const before = dirtyKeys.size;
                                COLS.forEach(col => {
                                    if (col.readonly) return;
                                    if (COLUMN_PERMS[col.key] === 'view') return;
                                    if (col.key === 'id') return;
                                    if (! cellValuesEqual(data[col.key], dbRow[col.key])) {
                                        dirtyKeys.add(col.key);
                                    }
                                });
                                if (dirtyKeys.size > before) updatesRecovered++;

                                // Compare cell_formulas (object) via JSON — đơn giản & an toàn
                                const a = JSON.stringify(data.cell_formulas || null);
                                const b = JSON.stringify(dbRow.cell_formulas || null);
                                if (a !== b) formulasDirty = true;
                            } else if (data.cell_formulas) {
                                formulasDirty = true;
                            }

                            if (dirtyKeys.size === 0 && ! formulasDirty) return;
                            const payload = { id: effectiveId };
                            dirtyKeys.forEach(key => {
                                payload[key] = data[key] === undefined ? null : data[key];
                            });
                            // Khi có formula change HOẶC value dirty → gửi full formulas map
                            // (backend overwrite — múc cell_formulas của row này)
                            if (formulasDirty || dirtyKeys.size > 0) {
                                payload.cell_formulas = data.cell_formulas || null;
                            }
                            dirtyMeta.push({ data: payload, sheetRow: m.sheetRow, sheetOrder, isNew: false });
                        } else {
                            // CREATE — bắt buộc có client (text non-empty)
                            const client = (data.client ?? '').toString().trim();
                            if (! client) return;
                            if (! dirtyMap.has(m.sheetRow)) newRowsRecovered++;
                            const payload = { ...data, client };
                            delete payload.id;
                            dirtyMeta.push({ data: payload, sheetRow: m.sheetRow, sheetOrder, isNew: true });
                        }
                    });
                });

                if (duplicateCount > 0) {
                    toast(`⚠️ Phát hiện <strong>${duplicateCount}</strong> dòng trùng ID — sẽ tạo thành dòng MỚI.`, 'warning');
                }

                const importRows = dirtyMeta.filter(m => m.sheetOrder === 0).map(m => m.data);
                const exportRows = dirtyMeta.filter(m => m.sheetOrder === 1).map(m => m.data);

                // Diff deletion: IDs có trong _originalIds nhưng KHÔNG có trong current sheet
                // → user đã xóa rows này (Luckysheet delete row không có hook nên dùng diff).
                const currentIds = { import: new Set(), export: new Set() };
                [0, 1].forEach((sheetOrder) => {
                    const dir = sheetOrder === 0 ? 'import' : 'export';
                    const allRows = readSheetRowsWithMeta(sheetOrder);
                    allRows.forEach(m => {
                        if (m.data.id != null) currentIds[dir].add(parseInt(m.data.id));
                    });
                });
                let deletedIds = [];
                ['import', 'export'].forEach(dir => {
                    _originalIds[dir].forEach(id => {
                        if (! currentIds[dir].has(id)) deletedIds.push(id);
                    });
                });

                // Check quyền xóa — nếu KHÔNG có quyền nhưng try xóa → warn + skip deletion
                // (vẫn cho save các thay đổi khác). Sẽ trigger reload sau save để khôi phục
                // rows visually đã xóa lỡ.
                let _deleteBlocked = 0;
                if (deletedIds.length > 0 && ! CAN_DELETE_ROWS) {
                    _deleteBlocked = deletedIds.length;
                    toast(
                        `⚠️ Bạn KHÔNG có quyền xóa dòng (cần quyền 'shipments.delete'). ` +
                        `<strong>${_deleteBlocked}</strong> dòng sẽ được khôi phục sau khi lưu. ` +
                        `Liên hệ quản trị viên để cấp quyền.`,
                        'danger'
                    );
                    deletedIds = [];   // không gửi lên backend
                }
                if (deletedIds.length > 0) {
                    console.log('[save] deletedIds:', deletedIds);
                }

                // Detect formatting + column width changes
                let formattingChanged = false;
                let columnlenChanged  = false;
                let currentFmt = null;
                let currentColumnlen = null;
                if (CAN_SAVE_FORMATTING) {
                    currentFmt = {
                        import: extractFormatting(0),
                        export: extractFormatting(1),
                    };
                    const savedFmtStr = JSON.stringify(snapshot?.formatting || { import: [], export: [] });
                    formattingChanged = JSON.stringify(currentFmt) !== savedFmtStr;

                    currentColumnlen = {
                        import: extractColumnWidths(0),
                        export: extractColumnWidths(1),
                    };
                    const savedColumnlenStr = JSON.stringify(snapshot?.columnlen || { import: {}, export: {} });
                    columnlenChanged = JSON.stringify(currentColumnlen) !== savedColumnlenStr;

                    console.log('[save] formattingChanged:', formattingChanged,
                                '| columnlenChanged:', columnlenChanged,
                                '| CAN_SAVE:', CAN_SAVE_FORMATTING);
                }

                // Không có row dirty + không có deletion + không có format/columnlen → khỏi API
                if (dirtyMeta.length === 0 && deletedIds.length === 0 && ! formattingChanged && ! columnlenChanged) {
                    console.log('[save] EARLY RETURN — no changes');
                    return toast('Không có thay đổi cần lưu.', 'info');
                }

                // X-Socket-ID — Reverb's toOthers() dùng header này để loại tab của sender
                // khỏi broadcast. Không có nó → tab gửi cũng nhận event của chính nó.
                const socketId = (() => {
                    try {
                        const id = window.Echo?.socketId?.();
                        // Chỉ nhận socket id đúng định dạng số.số (Pusher/Reverb).
                        // Echo chưa nối → undefined → trả '' để KHÔNG gắn header.
                        return /^\d+\.\d+$/.test(id) ? id : '';
                    } catch (e) { return ''; }
                })();

                // Chỉ gắn X-Socket-ID khi hợp lệ — header rỗng làm toOthers() gửi
                // socket_id='' → Pusher ném "Invalid socket ID" chết job broadcast.
                const socketHeader = socketId ? { 'X-Socket-ID': socketId } : {};

                // Snapshot payload: formatting overlay + column widths.
                // Anchored theo col KEY → portable giữa users với COLS khác nhau.
                // Backend merge theo formatting_scope (visible cols), giữ values cho hidden cols.
                const formattingPayload = CAN_SAVE_FORMATTING ? {
                    formatting: currentFmt,
                    formatting_scope: COLS.map(c => c.key),
                    columnlen: currentColumnlen,
                } : null;
                console.log('[save] sending snapshot:', formattingPayload ? JSON.stringify(formattingPayload).slice(0, 200) + '...' : 'NULL');

                const res = await fetch(ROUTES.bulk, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':  CSRF,
                        ...socketHeader,
                        'Accept':        'application/json',
                    },
                    body: JSON.stringify({
                        rows: { import: importRows, export: exportRows },
                        deleted_ids: deletedIds,
                        snapshot: formattingPayload,
                        client_version: sheetVersion,
                    })
                });

                const json = await res.json();
                console.log('[save] response:', { ok: json.ok, version: json.version, saved: json.saved, snapshot_conflict: json.snapshot_conflict });
                if (json._debug_snapshot) {
                    console.log('[save] BACKEND SNAPSHOT AFTER SAVE:', json._debug_snapshot);
                }
                if (! json.ok) {
                    toast(json.message || 'Lưu thất bại.', 'danger');
                    return;
                }

                sheetVersion = json.version;

                // Gán No. mới cho dòng vừa được tạo + verify hiển thị
                const idCol = COLS.findIndex(c => c.key === 'id');
                const newRowMetas = [];
                let updatedNew = 0;

                if (idCol >= 0 && Array.isArray(json.ids)) {
                    // PER-CELL tracking thay vì global flag → KHÔNG block user gõ cell khác
                    for (let i = 0; i < dirtyMeta.length; i++) {
                        const m = dirtyMeta[i];
                        const newId = json.ids[i];
                        if (m.isNew && newId != null) {
                            try {
                                markSystemCell(m.sheetOrder, m.sheetRow, idCol, 600);
                                luckysheet.setCellValue(m.sheetRow, idCol, newId, { order: m.sheetOrder });
                                newRowMetas.push({ ...m, newId });
                                updatedNew++;
                            } catch (e) { console.warn('setCellValue id inject failed:', e); }
                        }
                    }

                    // Verify sau 250ms
                    if (newRowMetas.length > 0) {
                        await new Promise(r => setTimeout(r, 250));
                        const stillEmpty = newRowMetas.filter(m => {
                            try {
                                const v = luckysheet.getCellValue(m.sheetRow, idCol, { order: m.sheetOrder });
                                return v == null || v === '';
                            } catch (e) { return true; }
                        });
                        if (stillEmpty.length > 0) {
                            console.warn(`${stillEmpty.length}/${newRowMetas.length} No. cell không hiển thị — fallback loadData`);
                            toast(`Đã lưu — đang đồng bộ ${stillEmpty.length} No. mới…`, 'info');
                            removeDirtyMatching(dirtyAtStart);
                            updateVersionBadge(CURRENT_USER, new Date().toISOString());
                            return loadData();
                        }
                    }
                }

                // SELECTIVE clear — chỉ xóa dirty entries đã SENT (giữ entries user typing trong lúc fetch)
                removeDirtyMatching(dirtyAtStart);

                // Update originalIds: rows đã DELETE khỏi DB không còn trong "original"
                deletedIds.forEach(id => {
                    _originalIds.import.delete(id);
                    _originalIds.export.delete(id);
                });
                // Cộng thêm IDs mới được tạo (json.ids) vào originalIds
                if (Array.isArray(json.ids)) {
                    json.ids.forEach((id, i) => {
                        if (id != null && dirtyMeta[i]?.isNew) {
                            const dir = dirtyMeta[i].sheetOrder === 0 ? 'import' : 'export';
                            _originalIds[dir].add(parseInt(id));
                        }
                    });
                }

                // Update local snapshot cache — tránh stale ở lần save kế (formattingChanged compare)
                if (currentFmt) {
                    if (! snapshot) snapshot = {};
                    snapshot.formatting = currentFmt;
                }
                if (currentColumnlen) {
                    if (! snapshot) snapshot = {};
                    snapshot.columnlen = currentColumnlen;
                }

                // FORMAT RESAVE — sau khi inject new IDs, re-extract formatting + save lại.
                if (updatedNew > 0 && CAN_SAVE_FORMATTING) {
                    setTimeout(async () => {
                        try {
                            const refreshedFmt = {
                                import: extractFormatting(0),
                                export: extractFormatting(1),
                            };
                            if (! snapshot) snapshot = {};
                            snapshot.formatting = refreshedFmt;

                            await fetch(ROUTES.bulk, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN':  CSRF,
                                    ...socketHeader,
                                    'Accept':        'application/json',
                                },
                                body: JSON.stringify({
                                    rows: { import: [], export: [] },
                                    snapshot: {
                                        formatting: refreshedFmt,
                                        formatting_scope: COLS.map(c => c.key),
                                    },
                                    client_version: sheetVersion,
                                })
                            });
                        } catch (e) { console.warn('format resave failed:', e); }
                    }, 500);
                }

                // Nếu có delete bị block → reload để khôi phục rows visually
                if (_deleteBlocked > 0) {
                    updateVersionBadge(CURRENT_USER, new Date().toISOString());
                    setTimeout(loadData, 800);   // delay cho toast warning user thấy trước
                    return;
                }

                // Thông báo — đơn giản, người dùng dễ hiểu
                if (updatedNew > 0) {
                    let msg = `Đã lưu — gán <strong>${updatedNew}</strong> No. mới.`;
                    if (json.deleted > 0) msg += ` Đã XÓA <strong>${json.deleted}</strong> dòng.`;
                    toast(msg, 'success');
                } else {
                    let msg = `Đã lưu ${json.saved} dòng`;
                    if (json.deleted > 0) msg += `, đã XÓA ${json.deleted} dòng`;
                    toast(msg + '.', 'success');
                }
                updateVersionBadge(CURRENT_USER, new Date().toISOString());
            });

            // ===== Column prefs dropdown =====
            document.querySelectorAll('.col-pref-toggle').forEach(cb => {
                cb.addEventListener('change', updateCvCounters);
            });
            setupCvSearch();

            // ===== Date filter dropdown =====
            setupDateFilter();

            // Document-level Ctrl+V safety net — backup nếu Luckysheet hook không fire
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && (e.key === 'v' || e.key === 'V')) {
                    setTimeout(scanDuplicateIds, 300);
                }
            });

            document.getElementById('btnApplyCols').addEventListener('click', async () => {
                // Thu thập key các cột BỊ ẨN (checkbox không tick)
                const hidden = [];
                document.querySelectorAll('.col-pref-toggle:not(:disabled)').forEach(cb => {
                    if (! cb.checked) hidden.push(cb.dataset.key);
                });
                const res = await fetch(ROUTES.columnPrefs, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ hidden })
                });
                const json = await res.json();
                if (json.ok) {
                    toast(`Đã lưu (ẩn ${hidden.length} cột). Đang tải lại…`);
                    setTimeout(() => location.reload(), 600);
                } else {
                    toast('Lưu thất bại.', 'danger');
                }
            });

            // Tạo tháng mới
            document.getElementById('periodForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const period = document.getElementById('newPeriod').value;   // YYYY-MM
                if (!period) return;
                const res = await fetch(ROUTES.createPeriod, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ period }),
                });
                const json = await res.json();
                if (json.ok) {
                    toast('Đã tạo tháng ' + json.period + '. Đang chuyển trang…');
                    setTimeout(() => location.href = `/shipments/${json.period}`, 800);
                } else {
                    toast(json.message || 'Tạo thất bại', 'danger');
                }
            });
        });
    </script>
@endpush
