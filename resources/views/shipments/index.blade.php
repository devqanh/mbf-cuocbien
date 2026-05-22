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
                    const isEmpty = raw == null || raw === '';
                    const readonly = isReadonly(c);

                    // Skip cell editable rỗng — Luckysheet auto-render rỗng, tiết kiệm 60-80% celldata
                    // Readonly cell vẫn keep dù rỗng → giữ visual cue (bg xám + lock)
                    if (isEmpty && ! readonly) return;

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

                    // Readonly: nền xám + text mờ + lock — user nhìn ra ngay, không gõ vào để khỏi mất dữ liệu
                    if (readonly) {
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
            return readSheetRowsWithMeta(sheetIndex).map(m => m.data);
        }

        // Trả về [{data, sheetRow}] — cần sheetRow để setCellValue sau save
        function readSheetRowsWithMeta(sheetIndex) {
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
                rows.push({ data: obj, sheetRow: r });
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
            // Reset dirty tracker + flags — data vừa fresh, không còn cell nào dirty/stale
            resetDirty();
            _needsResync = false;
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

        /**
         * Self-healing: augment snapshot với DB rows missing.
         *
         * Vấn đề: snapshot lưu trên server chỉ chứa workbook state tại thời điểm save.
         * Nếu DB có rows mới (do user khác tạo, hoặc do snapshot mismatch giữa data[] và
         * celldata[]), super admin dùng snapshot sẽ THIẾU rows so với DB. Restricted user
         * (dùng buildCellData từ rowsByDir) thì luôn thấy đầy đủ.
         *
         * Fix: match rowsByDir vs snapshot theo `id` (cell ở idCol). Row nào có ở DB
         * nhưng KHÔNG có ở snapshot → append vào snapshot's celldata + bump sheet.row.
         */
        function augmentSnapshotWithDbRows(snapshot) {
            const idColIdx = COLS.findIndex(c => c.key === 'id');
            if (idColIdx < 0) return snapshot;

            const buildCellForRow = (row, ri) => {
                const cells = [];
                COLS.forEach((c, ci) => {
                    const raw = row[c.key];
                    const isEmpty = raw == null || raw === '';
                    const readonly = c.readonly || COLUMN_PERMS[c.key] === 'view';
                    if (isEmpty && ! readonly) return;

                    let m;
                    if (c.type === 'vnd')        m = fmtVND(raw);
                    else if (c.type === 'number') m = fmtNumber(raw);
                    else if (c.type === 'date')   m = fmtDate(raw);
                    else                          m = isEmpty ? '' : String(raw);

                    const cell = {
                        v: isEmpty ? '' : raw,
                        m: m,
                        ct: ctFor(c.type),
                        tb: 2,
                        vt: 0,
                    };
                    if (c.type === 'vnd' || c.type === 'number') cell.ht = 2;
                    if (readonly) {
                        cell.bg = '#f4f6fb';
                        cell.fc = '#7987a1';
                        cell.lo = 1;
                    }
                    cells.push({ r: ri, c: ci, v: cell });
                });
                return cells;
            };

            const dirNames = ['import', 'export'];
            snapshot.forEach((sheet, sheetIdx) => {
                const dbRows = rowsByDir[dirNames[sheetIdx]] || [];
                if (! Array.isArray(dbRows) || dbRows.length === 0) return;

                // Thu thập IDs đã có trong snapshot (check cả data[] và celldata[])
                const presentIds = new Set();
                if (Array.isArray(sheet.data)) {
                    for (let r = 1; r < sheet.data.length; r++) {
                        const cell = sheet.data[r]?.[idColIdx];
                        const v = cell?.v ?? cell?.m;
                        if (v != null && v !== '') presentIds.add(parseInt(v));
                    }
                }
                if (Array.isArray(sheet.celldata)) {
                    sheet.celldata.forEach(cd => {
                        if (cd.c === idColIdx) {
                            const v = cd.v?.v ?? cd.v?.m;
                            if (v != null && v !== '') presentIds.add(parseInt(v));
                        }
                    });
                }

                // DB rows không có trong snapshot → cần append
                const missing = dbRows.filter(r => {
                    const id = r.id != null ? parseInt(r.id) : null;
                    return id && ! presentIds.has(id);
                });
                if (missing.length === 0) return;

                // Tìm row index cuối cùng có data trong snapshot để append phía sau
                let lastDataRow = 0;
                if (Array.isArray(sheet.celldata)) {
                    sheet.celldata.forEach(cd => {
                        if (cd.r > lastDataRow) lastDataRow = cd.r;
                    });
                }
                if (Array.isArray(sheet.data)) {
                    lastDataRow = Math.max(lastDataRow, sheet.data.length - 1);
                }

                // Append missing rows
                if (! Array.isArray(sheet.celldata)) sheet.celldata = [];
                missing.forEach((row, i) => {
                    const ri = lastDataRow + 1 + i;
                    const cells = buildCellForRow(row, ri);
                    sheet.celldata.push(...cells);
                });

                // Bump sheet.row + sheet.data để đủ chỗ
                const newRowCount = lastDataRow + missing.length + 20;   // +20 buffer rows
                if (! sheet.row || sheet.row < newRowCount) sheet.row = newRowCount;

                console.info(`[augment] sheet ${dirNames[sheetIdx]}: append ${missing.length} DB rows missing trong snapshot`);
            });
            return snapshot;
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
                defaultRowHeight: 36,
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
                defaultRowHeight: 36,
            };

            // Restricted user (HAS_RESTRICTIONS=true) → rebuild từ defaultSheet để áp readonly styling
            // Super_admin / user full quyền → dùng snapshot để giữ format đã lưu
            // FORCE name/order/color theo defaults — Luckysheet 2.1.13 render tab dùng template
            // `${name}`, nếu sheet object thiếu name (snapshot cũ/corrupt) sẽ hiện literal "${name}"
            const SHEET_DEFAULTS = [
                { name: 'HÀNG NHẬP', color: '#24d39f' },
                { name: 'HÀNG XUẤT', color: '#0153a9' },
            ];
            const DEFAULT_ROW_HEIGHT = 36;
            const useSnapshot = ! HAS_RESTRICTIONS && snapshot && Array.isArray(snapshot) && snapshot.length >= 2;
            const sheets = useSnapshot
                ? augmentSnapshotWithDbRows(snapshot).map((sh, i) => ({
                    ...sh,
                    name:  SHEET_DEFAULTS[i]?.name  || sh.name || `Sheet${i + 1}`,
                    order: i,
                    color: SHEET_DEFAULTS[i]?.color || sh.color,
                    defaultRowHeight: DEFAULT_ROW_HEIGHT,
                    config: {
                        ...sh.config,
                        rowlen: { ...(sh.config?.rowlen || {}), 0: 48 },
                    },
                  }))
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
                // Dynamic range: lấy số row thật của sheet thay vì hardcode 199
                // Đảm bảo dropdown vẫn hoạt động khi tháng có >200 dòng
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

                _privateChan = window.Echo.private('items-sheet');

                // 1) Server-broadcast event: ai đó save xong
                // - toOthers() ở backend đã loại sender's socket (nhờ X-Socket-ID header trên save).
                //   Nên KHÔNG cần check editorId — multi-tab same user vẫn nhận event ở tab khác.
                // - Smart strategy: nếu user không có dirty cells → reload silent.
                //   Nếu có dirty → đặt flag _needsResync, reload sau khi user save xong.
                _privateChan.listen('.sheet.updated', (e) => {
                    if (! e.sheetKey || ! e.sheetKey.endsWith(PERIOD)) return;

                    sheetVersion = e.version;
                    updateVersionBadge({ id: e.editorId, name: e.editorName }, new Date().toISOString());

                    if (countDirty() === 0) {
                        // An toàn — user không đang gõ dở → reload silent.
                        toast(`<i class="bi bi-person-fill-gear"></i> <strong>${e.editorName}</strong> vừa lưu (v${e.version}). Đang đồng bộ…`, 'info');
                        loadData();
                    } else {
                        // User đang có thay đổi chưa lưu → đặt flag, sẽ resync sau khi save xong.
                        _needsResync = true;
                        toast(
                            `<i class="bi bi-person-fill-gear"></i> <strong>${e.editorName}</strong> vừa lưu (v${e.version}). ` +
                            `Sẽ tự đồng bộ sau khi bạn lưu thay đổi của mình.`,
                            'warning'
                        );
                    }
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

                [0, 1].forEach((sheetOrder) => {
                    const allRows = readSheetRowsWithMeta(sheetOrder);
                    const dirtyMap = dirtyAtStart[sheetOrder] || new Map();

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
                            // UPDATE — chỉ gửi cell dirty
                            const dirtyKeys = dirtyMap.get(m.sheetRow);
                            if (! dirtyKeys || dirtyKeys.size === 0) return;
                            const payload = { id: effectiveId };
                            dirtyKeys.forEach(key => {
                                payload[key] = data[key] === undefined ? null : data[key];
                            });
                            dirtyMeta.push({ data: payload, sheetRow: m.sheetRow, sheetOrder, isNew: false });
                        } else {
                            // CREATE — bắt buộc có client (text non-empty)
                            const client = (data.client ?? '').toString().trim();
                            if (! client) return;
                            // Safety net: nếu dirty tracker không bắt được (vd user chưa commit
                            // cell trước khi bấm Lưu), chúng ta vẫn include row này.
                            if (! dirtyMap.has(m.sheetRow)) newRowsRecovered++;
                            const payload = { ...data, client };   // gán client đã trim
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

                // Nếu user bị ẩn cột → KHÔNG gửi snapshot (tránh đè master bằng layout thiếu cột)
                const fullSheets = HAS_RESTRICTIONS ? null : luckysheet.getAllSheets();

                // Không có row dirty + không có snapshot mới → khỏi gọi API
                if (dirtyMeta.length === 0 && ! fullSheets) {
                    return toast('Không có thay đổi cần lưu.', 'info');
                }

                // X-Socket-ID — Reverb's toOthers() dùng header này để loại tab của sender
                // khỏi broadcast. Không có nó → tab gửi cũng nhận event của chính nó.
                const socketId = (() => {
                    try { return window.Echo?.socketId?.() ?? ''; }
                    catch (e) { return ''; }
                })();

                const res = await fetch(ROUTES.bulk, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':  CSRF,
                        'X-Socket-ID':   socketId,
                        'Accept':        'application/json',
                    },
                    body: JSON.stringify({
                        rows: { import: importRows, export: exportRows },
                        snapshot: fullSheets,
                        client_version: sheetVersion,
                    })
                });

                const json = await res.json();
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

                // Snapshot resave sau khi inject IDs — đảm bảo other users load thấy snapshot
                // có IDs đầy đủ (không bị empty ID cells gây augment hiểu nhầm).
                if (updatedNew > 0 && ! HAS_RESTRICTIONS) {
                    setTimeout(async () => {
                        try {
                            const refreshedSheets = luckysheet.getAllSheets();
                            await fetch(ROUTES.bulk, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN':  CSRF,
                                    'X-Socket-ID':   socketId,
                                    'Accept':        'application/json',
                                },
                                body: JSON.stringify({
                                    rows: { import: [], export: [] },
                                    snapshot: refreshedSheets,
                                    client_version: sheetVersion,
                                })
                            });
                        } catch (e) { console.warn('snapshot resave failed:', e); }
                    }, 800);
                }

                // Thông báo
                if (json.snapshot_conflict) {
                    toast(
                        `✓ Đã lưu ${json.saved} dòng. ⚠️ Formatting bị overwrite bởi người khác — đang đồng bộ lại…`,
                        'warning'
                    );
                    setTimeout(loadData, 1500);
                } else if (_needsResync) {
                    // User khác đã save trong lúc mình gõ → giờ mình save xong → reload đồng bộ
                    toast(`Đã lưu ${json.saved} dòng. Đang đồng bộ với thay đổi của người khác…`, 'info');
                    setTimeout(loadData, 500);
                } else if (updatedNew > 0) {
                    let msg = `Đã lưu — gán <strong>${updatedNew}</strong> No. mới.`;
                    if (newRowsRecovered > 0) msg += ` (${newRowsRecovered} dòng phục hồi từ scan)`;
                    toast(msg, 'success');
                } else {
                    toast(`Đã lưu ${json.saved} dòng (v${json.version}).`);
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
