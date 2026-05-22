@extends('layouts.app')

@section('title', 'Follow Up Shipment — ' . $period)

@push('styles')
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/plugins/css/pluginsCss.css' />
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/plugins/plugins.css' />
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/css/luckysheet.css' />
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/assets/iconfont/iconfont.css' />
<style>
    /* ===== Column visibility dropdown ===== */
    .cv-dropdown {
        min-width: 380px;
        padding: 0;
        border: none;
        border-radius: 14px;
        box-shadow: 0 20px 50px rgba(28,39,60,.18);
        overflow: hidden;
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

    .cv-body { max-height: 380px; overflow-y: auto; padding: 4px 0; }
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
    .cv-row.is-disabled {
        opacity: .55;
        cursor: not-allowed;
        background: #fff5f5;
        pointer-events: none;   /* QUAN TRỌNG: chặn mọi click leak vào input/switch */
    }
    .cv-row.is-disabled:hover { background: #fff5f5; }
    .cv-row.is-disabled .cv-switch { background: #ffd0d0 !important; }
    .cv-row.is-disabled .cv-switch::after { background: #f5f5f5; }

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
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <i class="bi bi-layout-three-columns me-1"></i> Cột hiển thị
                    <span class="badge bg-primary ms-1" id="colsCountBadge">{{ $currentlyShown }}</span>
                </button>

                <div class="dropdown-menu dropdown-menu-end cv-dropdown">
                    {{-- Header gradient --}}
                    <div class="cv-header">
                        <div>
                            <div class="cv-title"><i class="bi bi-layout-three-columns me-1"></i> Tuỳ chỉnh cột hiển thị</div>
                        </div>
                        <span class="cv-counter">
                            <span id="cvShownNum">{{ $currentlyShown }}</span> / {{ $totalToggleable }} cột
                        </span>
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
                                    @php
                                        $adminHidden = ($columnPerms[$col['key']] ?? 'edit') === 'hidden';
                                    @endphp
                                    <label class="cv-row {{ $adminHidden ? 'is-disabled' : '' }}"
                                           data-name="{{ mb_strtolower($col['title']) }}"
                                           data-group="{{ $gid }}"
                                           for="cp_{{ $col['key'] }}">
                                        <input class="col-pref-toggle"
                                               type="checkbox"
                                               data-key="{{ $col['key'] }}"
                                               data-group="{{ $gid }}"
                                               id="cp_{{ $col['key'] }}"
                                               {{ $adminHidden ? 'disabled' : '' }}
                                               {{ (! $adminHidden && ! in_array($col['key'], $userHidden)) ? 'checked' : '' }}>
                                        <span class="cv-switch"></span>
                                        <span class="cv-label">
                                            <span class="name">{{ $col['title'] }}</span>
                                            @if($adminHidden)
                                                <span class="cv-locked"><i class="bi bi-lock-fill"></i> Admin khoá</span>
                                            @endif
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
                        <button type="button" class="btn btn-light flex-grow-1" onclick="closeDropdown(this)">
                            <i class="bi bi-x-lg"></i> Đóng
                        </button>
                        <button type="button" class="btn btn-primary flex-grow-1" id="btnApplyCols">
                            <i class="bi bi-check2-circle me-1"></i> Áp dụng
                        </button>
                    </div>
                </div>
            </div>

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
                        <button type="button" class="btn btn-light flex-grow-1" id="btnClearFilter">
                            <i class="bi bi-x-lg"></i> Xoá lọc
                        </button>
                        <button type="button" class="btn btn-success flex-grow-1" id="btnApplyFilter">
                            <i class="bi bi-check2-circle me-1"></i> Áp dụng
                        </button>
                    </div>
                </div>
            </div>

            <span id="filterChip"></span>

            <a href="{{ route('reports.payable.initial.index') }}" class="btn btn-outline-secondary"
               target="_blank" title="Cấu hình danh sách NCC — mở tab mới">
                <i class="bi bi-people me-1"></i> NCC
                <span class="badge bg-secondary ms-1">{{ count($suppliers) }}</span>
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
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>

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
        // User bị admin restrict (hidden/view) hoặc user tự ẩn cột → skip snapshot logic
        const HAS_RESTRICTIONS = Object.values(COLUMN_PERMS).some(p => p === 'hidden' || p === 'view')
                              || USER_HIDDEN.size > 0;

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

        function buildCellData(rows) {
            const celldata = [];
            // Cột readonly = id auto-gen HOẶC admin set 'view'
            const isReadonly = (c) => c.readonly || COLUMN_PERMS[c.key] === 'view';
            // Chỉ hiện 🔒 cho cột bị admin restrict (không phải id, vì id hiển nhiên auto)
            const showLockIcon = (c) => COLUMN_PERMS[c.key] === 'view';

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
                COLS.forEach((c, ci) => {
                    const raw = row[c.key];
                    let displayed;
                    if (c.type === 'vnd')        displayed = fmtVND(raw);
                    else if (c.type === 'number') displayed = fmtNumber(raw);
                    else if (c.type === 'date')   displayed = fmtDate(raw);
                    else                          displayed = (raw == null ? '' : String(raw));

                    const cell = {
                        v: raw == null ? '' : raw,
                        m: displayed,
                        ct: ctFor(c.type),
                        tb: 2,
                        vt: 0,
                    };
                    if (c.type === 'vnd' || c.type === 'number') cell.ht = 2;

                    // Readonly: nền xám + text mờ + lock — user nhìn ra ngay, không gõ vào để khỏi mất dữ liệu
                    if (isReadonly(c)) {
                        cell.bg = '#f4f6fb';
                        cell.fc = '#7987a1';
                        cell.lo = 1;
                    }

                    celldata.push({ r: ri + 1, c: ci, v: cell });
                });
            });
            return celldata;
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
            const sheets = luckysheet.getAllSheets();
            const sheet  = sheets[sheetIndex];
            if (!sheet || !sheet.data) return [];
            const rows = [];
            for (let r = 1; r < sheet.data.length; r++) {
                const row = sheet.data[r];
                if (!row) continue;
                const obj = {};
                COLS.forEach((c, ci) => {
                    let v = parseCellValue(row[ci], c.type);
                    if (c.key === 'id') v = v === '' ? null : (parseInt(v) || null);
                    obj[c.key] = v;
                });
                rows.push(obj);
            }
            return rows;
        }

        let rowsByDir = { import: [], export: [] };
        let snapshot = null;
        let sheetVersion = 0;

        async function loadData() {
            const res  = await fetch(ROUTES.data, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            rowsByDir    = json.data;
            snapshot     = json.snapshot;
            sheetVersion = json.version ?? 0;
            renderSheet();
            updateVersionBadge(json.editor, json.updatedAt);
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

        function renderSheet() {
            const columnlen = COLS.reduce((acc, c, i) => (acc[i] = c.width, acc), {});

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
                    columnlen, rowlen: { 0: 48 }, customHeight: { 0: 1 },
                    borderInfo: makeBorderInfo(importRowCount),
                },
                celldata: buildCellData(rowsByDir.import || []),
                row: importRowCount,
                column: COLS.length + 1,
                frozen: { type: 'rangeBoth', range: { row_focus: 0, column_focus: 1 } },
                defaultRowHeight: 32,
            };
            const exportSheet = {
                name: 'HÀNG XUẤT', order: 1, color: '#0153a9',
                config: {
                    columnlen, rowlen: { 0: 48 }, customHeight: { 0: 1 },
                    borderInfo: makeBorderInfo(exportRowCount),
                },
                celldata: buildCellData(rowsByDir.export || []),
                row: exportRowCount,
                column: COLS.length + 1,
                frozen: { type: 'rangeBoth', range: { row_focus: 0, column_focus: 1 } },
                defaultRowHeight: 32,
            };

            // Restricted user (HAS_RESTRICTIONS=true) → rebuild từ defaultSheet để áp readonly styling
            // Super_admin / user full quyền → dùng snapshot để giữ format đã lưu
            const sheets = (! HAS_RESTRICTIONS && snapshot && Array.isArray(snapshot) && snapshot.length >= 2)
                ? snapshot
                : [importSheet, exportSheet];

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
                hook: {
                    workbookCreateAfter() {
                        applySupplierDropdown();
                    },
                    // Helper kiểm cột readonly bằng index c
                    // (COLS đã filter theo perms, nên COLS[c] tương ứng đúng cột hiển thị tại index c)
                    // Chặn nhập vào cột readonly (id auto-gen + admin set 'view')
                    cellEditBefore(range) {
                        const ranges = Array.isArray(range) ? range : [range];
                        for (const r of ranges) {
                            if (r == null) continue;
                            let startCol, endCol;
                            if (typeof r === 'number') {
                                startCol = endCol = r;
                            } else if (r.column) {
                                startCol = Array.isArray(r.column) ? r.column[0] : r.column;
                                endCol   = Array.isArray(r.column) ? (r.column[1] ?? startCol) : startCol;
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
                        const colDef = COLS[c];
                        if (! colDef) return;
                        if (colDef.readonly || COLUMN_PERMS[colDef.key] === 'view') {
                            return false;
                        }
                    },
                    // Sau khi cell update — nếu là readonly thì revert
                    cellUpdated(r, c, oldValue, newValue, isRefresh) {
                        if (isRefresh) return;
                        const colDef = COLS[c];
                        if (! colDef) return;
                        if (colDef.readonly || COLUMN_PERMS[colDef.key] === 'view') {
                            // Revert by clearing the cell
                            setTimeout(() => {
                                try {
                                    luckysheet.setCellValue(r, c, oldValue?.v ?? oldValue?.m ?? '');
                                } catch (e) { console.warn('Revert readonly cell failed:', e); }
                            }, 50);
                        }
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
            allSheets.forEach((_, sheetOrder) => {
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
                        range: { row: [1, 199], column: [colIndex, colIndex] },
                        order: sheetOrder,
                    });
                } catch (e) {
                    console.warn('Không apply được dropdown NCC cho sheet', sheetOrder, e);
                }
            });
        }

        function setupRealtime() {
            try {
                window.Echo = new Echo({
                    broadcaster: 'reverb',
                    key:     REVERB.key,
                    wsHost:  REVERB.host,
                    wsPort:  REVERB.port,
                    wssPort: REVERB.port,
                    forceTLS: REVERB.scheme === 'https',
                    enabledTransports: ['ws', 'wss'],
                    auth: { headers: { 'X-CSRF-TOKEN': CSRF } },
                });
                const status = document.getElementById('liveStatus');
                const pc = window.Echo.connector.pusher.connection;
                pc.bind('connected',    () => { status.innerHTML = '<i class="bi bi-wifi"></i> Live'; status.className = 'badge badge-soft-success'; });
                pc.bind('disconnected', () => { status.innerHTML = '<i class="bi bi-wifi-off"></i> Offline'; status.className = 'badge badge-soft-warning'; });
                pc.bind('error',        (e) => { console.warn('Echo error:', e); status.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Lỗi WS'; status.className = 'badge badge-soft-danger'; });

                window.Echo.private('items-sheet').listen('.sheet.updated', (e) => {
                    if (e.editorId === CURRENT_USER.id) return;
                    if (!e.sheetKey || !e.sheetKey.endsWith(PERIOD)) return;  // chỉ react với event của tháng đang xem
                    toast(`<i class="bi bi-person-fill-gear"></i> <strong>${e.editorName}</strong> vừa lưu ${e.savedRows} dòng (v${e.version}). Đang đồng bộ…`, 'info');
                    loadData();
                });
            } catch (err) { console.warn('Realtime init failed:', err); }
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadData();
            setupRealtime();

            document.getElementById('btnReload').addEventListener('click', loadData);

            document.getElementById('btnSaveAll').addEventListener('click', async () => {
                // Auto-clear filter trước save để không lưu row heights=0 vào snapshot
                if (dateFilterState.active) {
                    clearDateFilter(false);
                }
                const importRows = readSheetRows(0).filter(r => r.client);
                const exportRows = readSheetRows(1).filter(r => r.client);
                if (importRows.length === 0 && exportRows.length === 0) {
                    return toast('Không có dòng hợp lệ để lưu (cần ít nhất Client).', 'warning');
                }

                // Nếu user bị ẩn cột → KHÔNG gửi snapshot (tránh ghi đè master snapshot bằng layout thiếu cột)
                // Chỉ super_admin / user full quyền mới được cập nhật snapshot
                const fullSheets = HAS_RESTRICTIONS ? null : luckysheet.getAllSheets();

                const res = await fetch(ROUTES.bulk, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({
                        rows: { import: importRows, export: exportRows },
                        snapshot: fullSheets,
                        client_version: sheetVersion,
                    })
                });
                if (res.status === 409) {
                    const j = await res.json();
                    toast(`⚠️ ${j.message}`, 'warning');
                    setTimeout(loadData, 1500);
                    return;
                }
                const json = await res.json();
                if (json.ok) {
                    sheetVersion = json.version;
                    toast(`Đã lưu ${json.saved} dòng (v${json.version}).`);
                    updateVersionBadge(CURRENT_USER, new Date().toISOString());
                } else {
                    toast('Lưu thất bại.', 'danger');
                }
            });

            // ===== Column prefs dropdown =====
            document.querySelectorAll('.col-pref-toggle').forEach(cb => {
                cb.addEventListener('change', updateCvCounters);
            });
            setupCvSearch();

            // ===== Date filter dropdown =====
            setupDateFilter();

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
