@extends('layouts.app')

@section('title', 'Trucking — HẠ HPH & HẠ ICD')

@push('styles')
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/plugins/css/pluginsCss.css' />
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/plugins/plugins.css' />
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/css/luckysheet.css' />
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/assets/iconfont/iconfont.css' />
<style>
    /* ===== Date filter dropdown ===== */
    .df-dropdown { min-width: 360px; padding: 0; border: none; border-radius: 14px; box-shadow: 0 20px 50px rgba(28,39,60,.18); overflow: hidden; }
    .df-header { background: linear-gradient(135deg, #24d39f 0%, #169a72 100%); color: #fff; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; }
    .df-body { padding: 16px; }
    .df-field { margin-bottom: 14px; }
    .df-field label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--azia-muted); font-weight: 700; margin-bottom: 6px; }
    .df-field select, .df-field input { width: 100%; padding: 8px 12px; border: 1px solid var(--azia-border); border-radius: 8px; font-size: 13px; }
    .df-field select:focus, .df-field input:focus { outline: none; border-color: var(--azia-success); box-shadow: 0 0 0 3px rgba(36,211,159,.15); }
    .df-help { font-size: 12px; color: var(--azia-muted); padding: 8px 12px; background: #fafbfd; border-radius: 8px; }
    .df-help i { color: var(--azia-success); margin-right: 4px; }
    .df-footer { display: flex; gap: 8px; padding: 12px 16px; background: #fafbfd; border-top: 1px solid var(--azia-border); }
    .filter-chip { display: inline-flex; align-items: center; gap: 6px; background: rgba(36,211,159,.15); color: #169a72; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 999px; margin-left: 6px; }
    .filter-chip button { background: transparent; border: none; color: #169a72; cursor: pointer; padding: 0; margin-left: 4px; display: inline-flex; align-items: center; }
    .filter-chip button:hover { color: var(--azia-danger); }

    /* ===== Column visibility offcanvas ===== */
    .cv-offcanvas { width: 400px !important; max-width: 100vw; display: flex !important; flex-direction: column !important; }
    .cv-header { background: linear-gradient(135deg, #0153a9 0%, #013f80 100%); color:#fff; padding:14px 18px; display:flex; align-items:center; justify-content:space-between; }
    .cv-title { font-size:13px; font-weight:700; }
    .cv-counter { background: rgba(255,255,255,.18); font-size:11px; font-weight:600; padding:3px 10px; border-radius:999px; }
    .cv-search { padding:10px 14px; border-bottom:1px solid var(--azia-border); background:#fafbfd; }
    .cv-search input { width:100%; padding:7px 12px; border:1px solid var(--azia-border); border-radius:8px; font-size:13px; }
    .cv-quick { display:flex; gap:6px; padding:10px 14px; border-bottom:1px solid var(--azia-border); background:#fafbfd; }
    .cv-quick button { flex:1; font-size:11px; padding:4px 8px; border:1px solid var(--azia-border); background:#fff; border-radius:6px; cursor:pointer; font-weight:600; }
    .cv-quick button:hover { background: var(--azia-primary-soft); color: var(--azia-primary); }
    .cv-body { flex:1 1 auto; min-height:0; overflow-y:auto; padding:4px 0; }
    .cv-header,.cv-search,.cv-quick,.cv-footer { flex:0 0 auto; }
    .cv-sheet-title { font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:#013f80; padding:10px 14px 4px; background:#eef3fb; display:flex; align-items:center; justify-content:space-between; }
    .cv-group { padding:6px 14px 4px; border-left:4px solid transparent; margin-bottom:2px; }
    .cv-group.g-1 { border-left-color:#D4E6B5; } .cv-group.g-2 { border-left-color:#FCE4D6; }
    .cv-group.g-3 { border-left-color:#DEEBF7; } .cv-group.g-4 { border-left-color:#FFF2CC; } .cv-group.g-5 { border-left-color:#E2D9F3; }
    .cv-group-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:4px; }
    .cv-group-title { font-size:10px; text-transform:uppercase; letter-spacing:1px; color:var(--azia-muted); font-weight:700; }
    .cv-sheet-btns, .cv-group-btns { display:inline-flex; gap:4px; }
    .cv-mini-btn { border:1px solid var(--azia-border); background:#fff; border-radius:6px; cursor:pointer; font-size:11px; line-height:1; padding:3px 7px; color:var(--azia-muted); }
    .cv-mini-btn:hover { color: var(--azia-primary); border-color: var(--azia-primary); background: var(--azia-primary-soft); }
    .cv-row { display:flex; align-items:center; gap:10px; padding:5px 8px; border-radius:6px; cursor:pointer; }
    .cv-row:hover { background:#fafbfd; }
    .cv-switch { position:relative; width:32px; height:18px; background:#d5dae3; border-radius:999px; flex-shrink:0; transition:background .2s; }
    .cv-switch::after { content:''; position:absolute; top:2px; left:2px; width:14px; height:14px; background:#fff; border-radius:50%; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
    .cv-row input:checked + .cv-switch { background: var(--azia-primary); }
    .cv-row input:checked + .cv-switch::after { transform: translateX(14px); }
    .cv-row input { position:absolute; opacity:0; pointer-events:none; }
    .cv-label { flex:1; font-size:13px; color:var(--azia-text); min-width:0; }
    .cv-empty { text-align:center; padding:30px 20px; color:var(--azia-muted); font-size:13px; }
    .cv-footer { display:flex; gap:8px; padding:12px 14px; background:#fafbfd; border-top:1px solid var(--azia-border); }
</style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <h1><i class="bi bi-truck-front me-1" style="color: var(--azia-primary)"></i> TRUCKING</h1>
            <nav class="breadcrumb mt-1">
                <a href="{{ route('trucking.index') }}">Trang chủ</a>
                <span class="mx-2">/</span>
                <span>Bảng kê chi phí &amp; doanh thu</span>
            </nav>
        </div>
        <div class="d-flex gap-2">
            {{-- Cột hiển thị (ẩn/hiện cho riêng user) --}}
            @php
                $groupTitles = [1=>'Thông tin lô hàng', 2=>'Chi phí', 3=>'Chi phí xe ngoài / TT', 4=>'Chi phí xe MBF chạy', 5=>'Doanh thu'];
                $sheetsCfg = ['HẠ HPH' => $colsHph, 'HẠ ICD' => $colsIcd];
                $userHidden = $userPrefs ?? [];
                $shownCount = 0; $toggleable = 0;
                foreach ($sheetsCfg as $sCols) foreach ($sCols as $c) {
                    if ($c['key'] === 'id') continue;
                    if (($columnPerms[$c['key']] ?? 'edit') === 'hidden') continue;
                    $toggleable++;
                    if (! in_array($c['key'], $userHidden)) $shownCount++;
                }
            @endphp
            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="offcanvas" data-bs-target="#cvOffcanvas">
                <i class="bi bi-layout-three-columns me-1"></i> Cột hiển thị
                <span class="badge bg-primary ms-1" id="colsCountBadge">{{ $shownCount }}</span>
            </button>
            <div class="offcanvas offcanvas-end cv-offcanvas" tabindex="-1" id="cvOffcanvas">
                <div class="cv-header">
                    <div class="cv-title"><i class="bi bi-layout-three-columns me-1"></i> Tuỳ chỉnh cột hiển thị</div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="cv-counter"><span id="cvShownNum">{{ $shownCount }}</span> / {{ $toggleable }} cột</span>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
                    </div>
                </div>
                <div class="cv-search"><input type="search" id="cvSearch" placeholder="Tìm cột theo tên..."></div>
                <div class="cv-quick">
                    <button type="button" onclick="toggleAllCols(true)"><i class="bi bi-eye"></i> Hiện tất cả</button>
                    <button type="button" onclick="toggleAllCols(false)"><i class="bi bi-eye-slash"></i> Ẩn tất cả</button>
                </div>
                <div class="cv-body" id="cvBody">
                    @foreach($sheetsCfg as $sheetName => $sCols)
                        @php $sheetCols = collect($sCols)->filter(fn($c)=>$c['key']!=='id' && (($columnPerms[$c['key']] ?? 'edit') !== 'hidden')); @endphp
                        @if($sheetCols->isEmpty()) @continue @endif
                        <div class="cv-sheet" data-sheet-section>
                            <div class="cv-sheet-title">
                                <span>{{ $sheetName }}</span>
                                <span class="cv-sheet-btns">
                                    <button type="button" class="cv-mini-btn" onclick="toggleSheetCols(this, true)" title="Hiện cả sheet"><i class="bi bi-eye"></i></button>
                                    <button type="button" class="cv-mini-btn" onclick="toggleSheetCols(this, false)" title="Ẩn cả sheet"><i class="bi bi-eye-slash"></i></button>
                                </span>
                            </div>
                            @foreach($sheetCols->groupBy('group') as $gid => $grp)
                                <div class="cv-group g-{{ $gid }}" data-group-section>
                                    <div class="cv-group-head">
                                        <span class="cv-group-title">{{ $groupTitles[$gid] ?? "Nhóm $gid" }}</span>
                                        <span class="cv-group-btns">
                                            <button type="button" class="cv-mini-btn" onclick="toggleGroupCols(this, true)" title="Hiện cả nhóm"><i class="bi bi-eye"></i></button>
                                            <button type="button" class="cv-mini-btn" onclick="toggleGroupCols(this, false)" title="Ẩn cả nhóm"><i class="bi bi-eye-slash"></i></button>
                                        </span>
                                    </div>
                                    @foreach($grp as $col)
                                        <label class="cv-row" data-name="{{ mb_strtolower($col['title']) }}">
                                            <input class="col-pref-toggle" type="checkbox" data-key="{{ $col['key'] }}"
                                                   {{ ! in_array($col['key'], $userHidden) ? 'checked' : '' }}>
                                            <span class="cv-switch"></span>
                                            <span class="cv-label"><span class="name">{{ $col['title'] }}</span></span>
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                    <div class="cv-empty d-none" id="cvEmpty"><i class="bi bi-search d-block mb-2"></i> Không tìm thấy cột.</div>
                </div>
                <div class="cv-footer">
                    <button type="button" class="btn btn-light flex-grow-1" data-bs-dismiss="offcanvas"><i class="bi bi-x-lg"></i> Đóng</button>
                    <button type="button" class="btn btn-primary flex-grow-1" id="btnApplyCols"><i class="bi bi-check2-circle me-1"></i> Áp dụng</button>
                </div>
            </div>

            {{-- Lọc theo cột ngày --}}
            <div class="dropdown">
                <button class="btn btn-outline-success dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <i class="bi bi-funnel me-1"></i> Lọc ngày
                    <span class="badge bg-success ms-1 d-none" id="dfBadge">0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end df-dropdown">
                    <div class="df-header">
                        <div>
                            <div style="font-size:13px;font-weight:700;letter-spacing:.3px"><i class="bi bi-funnel-fill me-1"></i> Lọc dòng theo cột ngày</div>
                            <div style="font-size:11px;opacity:.85;margin-top:2px">Áp dụng trên sheet đang mở</div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" aria-label="Đóng" onclick="closeDropdown(this)" style="filter:brightness(0) invert(1);opacity:.8"></button>
                    </div>
                    <div class="df-body">
                        <div class="df-field">
                            <label>Cột áp dụng</label>
                            <select id="dfColumn"><option value="">— Chọn cột —</option></select>
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
                            <div class="d-flex gap-2"><input type="date" id="dfFrom"><input type="date" id="dfTo"></div>
                        </div>
                        <div class="df-help"><i class="bi bi-info-circle"></i> Ẩn các dòng không khớp. Chỉ ảnh hưởng VIEW, không lưu DB.</div>
                    </div>
                    <div class="df-footer">
                        <button type="button" class="btn btn-light" onclick="closeDropdown(this)"><i class="bi bi-x-lg"></i> Đóng</button>
                        <button type="button" class="btn btn-light flex-grow-1" id="btnClearFilter"><i class="bi bi-eraser"></i> Xoá lọc</button>
                        <button type="button" class="btn btn-success flex-grow-1" id="btnApplyFilter"><i class="bi bi-check2-circle me-1"></i> Áp dụng</button>
                    </div>
                </div>
            </div>
            <span id="filterChip"></span>

            <button class="btn btn-outline-secondary" id="btnReload"><i class="bi bi-arrow-clockwise me-1"></i> Tải lại</button>
            <button class="btn btn-primary" id="btnSaveAll"><i class="bi bi-cloud-arrow-up me-1"></i> Lưu thay đổi</button>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span><strong>Bảng Trucking</strong></span>
                    <span id="versionBadge" class="badge badge-soft-warning"><i class="bi bi-clock-history"></i> Đang tải…</span>
                    <span id="liveStatus" class="badge badge-soft-warning"><i class="bi bi-wifi-off"></i> Offline</span>
                    <span id="liveActivity" class="small text-warning fw-semibold ms-2" style="opacity:0; transition: opacity .3s ease;"></span>
                </div>
                <div class="small text-muted fw-normal">
                    Workbook có 2 sheet ở dưới: <strong>HẠ HPH</strong> và <strong>HẠ ICD</strong>.
                    Các ô <strong>TỔNG CHI PHÍ / TỔNG DOANH THU / VAT / CÒN NỢ / PHẢI THU</strong> tự tính theo công thức.
                    Click vào ô tổng để xem công thức.
                </div>
                <div id="formulaHint" class="small fw-semibold mt-1" style="color:#0153a9; display:none;"></div>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="luckysheet" style="height: calc(100vh - 240px); min-height: 520px;"></div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/plugins/js/plugin.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/luckysheet.umd.js"></script>

    <script>
        const ROUTES = {
            data: @json(route('trucking.data')),
            bulk: @json(route('trucking.bulk')),
        };
        const SHEET_KEY = 'trucking_grid';
        const CSRF = document.querySelector('meta[name="csrf-token"]').content;

        const REVERB = {
            key:    @json(config('broadcasting.connections.reverb.key')),
            host:   @json(config('broadcasting.connections.reverb.options.host', request()->getHost())),
            port:   @json((int) config('broadcasting.connections.reverb.options.port', 8080)),
            scheme: @json(config('broadcasting.connections.reverb.options.scheme', 'http')),
        };
        const CURRENT_USER = @json(['id' => auth()->id(), 'name' => auth()->user()->name]);
        const CAN_DELETE_ROWS = @json($canDelete ?? false);
        const ROUTE_COLUMN_PREFS = @json(route('trucking.columnPrefs'));

        // ===== Phân quyền cột =====
        const COLUMN_PERMS = @json($columnPerms ?? new \stdClass());   // {key: 'hidden'|'view'|'edit'}
        const USER_HIDDEN  = new Set(@json($userPrefs ?? []));          // [key,...] user tự ẩn
        const ADMIN_RESTRICTED    = Object.values(COLUMN_PERMS).some(p => p === 'hidden' || p === 'view');
        const CAN_SAVE_FORMATTING = ! ADMIN_RESTRICTED;

        // ===== Cột theo từng sheet (order 0 = HẠ HPH, order 1 = HẠ ICD) =====
        // Lọc: bỏ cột admin ẩn → bỏ cột user tự ẩn → mark readonly cho cột 'view'.
        const ALL_COLS_BY_ORDER = [@json($colsHph), @json($colsIcd)];
        const COLS_BY_ORDER = ALL_COLS_BY_ORDER.map(sheetCols => sheetCols
            .filter(c => (COLUMN_PERMS[c.key] || 'edit') !== 'hidden')
            .filter(c => ! USER_HIDDEN.has(c.key))
            .map(c => ({ ...c, readonly: c.readonly || (COLUMN_PERMS[c.key] || 'edit') === 'view' }))
        );
        const SHEET_NAMES   = ['HẠ HPH', 'HẠ ICD'];
        const SHEET_DIRS    = ['hph', 'icd'];
        const SHEET_COLORS  = ['#24d39f', '#0153a9'];
        const colsForOrder  = (o) => COLS_BY_ORDER[o] || [];
        const dirForOrder   = (o) => SHEET_DIRS[o];
        const orderForDir   = (d) => (d === 'hph' ? 0 : 1);

        // Màu header theo nhóm
        const GROUP_HEADER_BG = { 1:'#D4E6B5', 2:'#FCE4D6', 3:'#DEEBF7', 4:'#FFF2CC', 5:'#E2D9F3' };

        function toast(msg, type = 'success') {
            const el = document.createElement('div');
            el.className = `toast align-items-center text-bg-${type} border-0 show`;
            el.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
            document.querySelector('.toast-container').appendChild(el);
            setTimeout(() => bootstrap.Toast.getOrCreateInstance(el).hide(), 3500);
        }

        // Format helpers — dùng dấu PHẨY ngăn cách hàng nghìn (1,000,000)
        const fmtVND    = (n) => n == null || n === '' ? '' : Number(n).toLocaleString('en-US') + ' VNĐ';
        const fmtNumber = (n) => n == null || n === '' ? '' : Number(n).toLocaleString('en-US');
        const fmtDate   = (s) => {
            if (!s) return '';
            const d = new Date(s);
            if (isNaN(d)) return s;
            return String(d.getDate()).padStart(2,'0') + '/' + String(d.getMonth()+1).padStart(2,'0') + '/' + d.getFullYear();
        };
        const ctFor = (type) => {
            if (type === 'vnd')    return { fa: '#,##0" VNĐ"', t: 'n' };
            if (type === 'number') return { fa: '#,##0',        t: 'n' };
            if (type === 'date')   return { fa: 'dd/MM/yyyy',   t: 'g' };
            return { fa: 'General', t: 'g' };
        };

        // ===== Tính toán tự động =====
        // Ghi giá trị / format. QUAN TRỌNG: với SHEET ĐANG XEM phải gọi KHÔNG kèm {order}
        // thì Luckysheet mới VẼ LẠI canvas ngay (gọi kèm {order} chỉ update data, không repaint
        // → tổng không nhảy cho tới khi reload). Sheet khác thì mới truyền {order}.
        function lsSetValue(sheetOrder, r, c, value) {
            const active = luckysheet.getSheet()?.order ?? 0;
            if (sheetOrder === active) luckysheet.setCellValue(r, c, value);
            else luckysheet.setCellValue(r, c, value, { order: sheetOrder });
        }
        function lsSetFormat(sheetOrder, r, c, attr, val) {
            const active = luckysheet.getSheet()?.order ?? 0;
            if (sheetOrder === active) luckysheet.setCellFormat(r, c, attr, val);
            else luckysheet.setCellFormat(r, c, attr, val, { order: sheetOrder });
        }

        // Đổi index cột (0-based) → chữ A1: 0→A, 25→Z, 26→AA, 27→AB...
        function colIndexToA1(idx) {
            let s = ''; let n = idx + 1;
            while (n > 0) { const r = (n - 1) % 26; s = String.fromCharCode(65 + r) + s; n = Math.floor((n - 1) / 26); }
            return s;
        }
        // Dựng công thức A1 cho ô CÔNG THỨC trên dòng r (0-based) → "=AB2+AC2…"
        // Luckysheet engine sẽ tự tính realtime mỗi khi ô nguồn thay đổi (giống Google Sheets,
        // không cần JS recalc + không cần reload trang). Hỗ trợ sum / sub / mul / expr.
        function buildA1Formula(spec, cols, sheetRow) {
            if (! spec) return '';
            const ref = (key) => {
                const i = cols.findIndex(c => c.key === key);
                if (i < 0) return '0';
                return colIndexToA1(i) + (sheetRow + 1);
            };
            switch (spec.op) {
                case 'sum':  return '=' + spec.cols.map(ref).join('+');
                case 'sub':  return '=' + spec.cols.map((k, i) => i === 0 ? ref(k) : '-' + ref(k)).join('');
                case 'mul':  return '=' + ref(spec.col) + '*' + (spec.factor ?? 0);
                case 'expr': return '=' + spec.template.replace(/\{(\w+)\}/g, (_, k) => ref(k));
                default:     return '';
            }
        }

        // Tính 1 ô công thức theo spec, dùng getNum(key) trả giá trị số của ô cùng dòng.
        // (Vẫn dùng cho TIỀN-RENDER giá trị hiển thị ban đầu trong buildCellData để tránh
        // nháy ô trống trước khi engine kịp evaluate.)
        function computeFormula(spec, getNum) {
            if (! spec) return 0;
            const num = (k) => { const n = Number(getNum(k)); return isNaN(n) ? 0 : n; };   // ép SỐ, tránh nối chuỗi
            switch (spec.op) {
                case 'sum': return spec.cols.reduce((s, k) => s + num(k), 0);
                case 'sub': return spec.cols.reduce((s, k, i) => i === 0 ? num(k) : s - num(k), 0);
                case 'mul': return num(spec.col) * (spec.factor ?? 0);
                case 'expr': {
                    const expr = spec.template.replace(/\{(\w+)\}/g, (_, k) => '(' + num(k) + ')');
                    try { return Function('"use strict";return (' + expr + ')')(); } catch (e) { return 0; }
                }
                default: return 0;
            }
        }
        // Đọc giá trị SỐ của ô (r, key) trên sheet đang render.
        // QUAN TRỌNG: phải trả về SỐ 0 cho ô trống — nếu trả '' thì phép + thành NỐI CHUỖI.
        function getCellNum(sheetOrder, r, key, cols) {
            const i = cols.findIndex(c => c.key === key);
            if (i < 0) return 0;
            let v;
            try { v = luckysheet.getCellValue(r, i, { order: sheetOrder }); } catch (e) { v = null; }
            const n = parseCellValue({ v }, 'vnd');
            return (typeof n === 'number' && ! isNaN(n)) ? n : 0;
        }
        // Tính lại MỌI ô CÔNG THỨC của dòng r BẰNG JS rồi ghi giá trị mới ngay tức thì.
        //   - Bước 1: setCellValue({v, m, ct, …}) — KHÔNG kèm `f` → tránh Luckysheet engine
        //             tự re-evaluate công thức và ghi đè giá trị JS đã tính (engine 2.1.13
        //             nhiều khi đọc dependency cũ → ra số sai / 0).
        //   - Bước 2: gán cell.f = "=Z2+AB2+AD2+AF2" trực tiếp vào sheet.data → formula bar
        //             (góc trên trái) vẫn hiện công thức khi click ô, không trigger recalc.
        //   - Duyệt trái→phải: ô tổng PHỤ THUỘC ô tổng trước (vd ext_vat phụ thuộc ext_freight)
        //     luôn đọc được giá trị mới nhất qua getCellNum.
        let _recalcInProgress = false;   // re-entrancy guard — `updated` hook không gọi đệ quy
        function recalcRow(sheetOrder, r) {
            if (r < 1 || _recalcInProgress) return;
            _recalcInProgress = true;
            try {
                const cols = colsForOrder(sheetOrder);
                const sheet = luckysheet.getluckysheetfile()?.[sheetOrder];
                const updates = [];
                cols.forEach((c, ci) => {
                    if (! c.formula) return;
                    const val = computeFormula(c.formula, (k) => getCellNum(sheetOrder, r, k, cols));
                    const f   = buildA1Formula(c.formula, cols, r);
                    const m   = c.type === 'vnd' ? fmtVND(val) : (c.type === 'number' ? fmtNumber(val) : String(val));
                    try {
                        markSystemCell(sheetOrder, r, ci, 800);
                        lsSetValue(sheetOrder, r, ci, { v: val, m, ct: ctFor(c.type), tb: 2, vt: 0, ht: 2 });
                        if (sheet?.data?.[r]?.[ci]) sheet.data[r][ci].f = f;
                        updates.push({ ci, key: c.key, val, m });
                    } catch (e) { console.warn('recalcRow failed:', e); }
                });
                if (window.__DEBUG_RECALC) console.log('[recalcRow]', { sheetOrder, r, updates });
                try { luckysheet.refresh && luckysheet.refresh(); } catch (e) {}
            } finally { _recalcInProgress = false; }
        }
        window.__recalcRow = recalcRow;   // gọi tay từ console: __recalcRow(0, 1)

        // Mô tả công thức bằng TÊN CỘT tiếng Việt (hiện khi click ô tổng).
        function formulaLabel(spec, cols) {
            const t = (key) => { const col = cols.find(c => c.key === key); return col ? col.title : key; };
            switch (spec.op) {
                case 'sum': return spec.cols.map(t).join(' + ');
                case 'sub': return spec.cols.map(t).join(' − ');
                case 'mul': return t(spec.col) + ' × ' + (spec.factor * 100) + '%';
                case 'expr': return spec.template.replace(/\{(\w+)\}/g, (_, k) => t(k)).replace(/\*/g, ' × ').replace(/\+/g, ' + ').replace(/-/g, ' − ');
                default: return '';
            }
        }

        // Cập nhật dòng "công thức" theo ô đang chọn — nếu là ô tổng thì hiện công thức.
        function updateFormulaHint() {
            const el = document.getElementById('formulaHint');
            if (! el) return;
            try {
                const order = luckysheet.getSheet()?.order ?? 0;
                const rng = luckysheet.getRange();
                const c = rng?.[0]?.column?.[0];
                const col = colsForOrder(order)[c];
                if (col && col.formula) {
                    el.innerHTML = `<i class="bi bi-calculator-fill"></i> <strong>${col.title}</strong> = ${formulaLabel(col.formula, colsForOrder(order))}`;
                    el.style.display = '';
                } else {
                    el.style.display = 'none';
                }
            } catch (e) { el.style.display = 'none'; }
        }
        // Tính lại toàn bộ dòng có dữ liệu trên cả 2 sheet (gọi sau khi render xong).
        function recalcAllRows() {
            [0, 1].forEach(sheetOrder => {
                const sheet = luckysheet.getluckysheetfile()?.[sheetOrder];
                const len = sheet?.data?.length || 0;
                for (let r = 1; r < len; r++) {
                    const row = sheet.data[r];
                    if (! row) continue;
                    const hasAny = row.some(cell => cell && (cell.v != null && cell.v !== ''));
                    if (hasAny) recalcRow(sheetOrder, r);
                }
            });
        }

        // ===== System cell tracking =====
        let _systemUpdate = false;
        // Ô đang được user mở inline-editor (capture từ cellEditBefore). Hook `updated`
        // sẽ dùng giá trị này thay vì operate.range (bị shift xuống 1 dòng sau Enter).
        let _lastEditCell = null;
        const _systemCells = new Map();
        function markSystemCell(sheetOrder, row, col, ttl = 400) { _systemCells.set(`${sheetOrder}:${row}:${col}`, Date.now() + ttl); }
        function isSystemCell(sheetOrder, row, col) {
            const k = `${sheetOrder}:${row}:${col}`; const exp = _systemCells.get(k);
            if (! exp) return false;
            if (Date.now() > exp) { _systemCells.delete(k); return false; }
            return true;
        }

        // ===== Dirty-cell tracker =====
        let dirtyCells = { 0: new Map(), 1: new Map() };
        function markDirty(sheetOrder, sheetRow, colKey) {
            if (! dirtyCells[sheetOrder]) dirtyCells[sheetOrder] = new Map();
            const m = dirtyCells[sheetOrder];
            if (! m.has(sheetRow)) m.set(sheetRow, new Set());
            m.get(sheetRow).add(colKey);
        }
        function resetDirty() { dirtyCells = { 0: new Map(), 1: new Map() }; }
        function snapshotDirty() {
            const snap = { 0: new Map(), 1: new Map() };
            [0, 1].forEach(o => (dirtyCells[o] || new Map()).forEach((set, row) => snap[o].set(row, new Set(set))));
            return snap;
        }
        function removeDirtyMatching(snap) {
            [0, 1].forEach(o => {
                const liveMap = dirtyCells[o]; if (! liveMap) return;
                snap[o].forEach((sentKeys, row) => {
                    const liveSet = liveMap.get(row); if (! liveSet) return;
                    sentKeys.forEach(k => liveSet.delete(k));
                    if (liveSet.size === 0) liveMap.delete(row);
                });
            });
        }
        function markRangeDirty(range) {
            if (! range) return;
            const ranges = Array.isArray(range) ? range : [range];
            const sheetOrder = luckysheet.getSheet()?.order ?? 0;
            const cols = colsForOrder(sheetOrder);
            const batchEdits = [];
            ranges.forEach(rg => {
                if (! rg || ! rg.row || ! rg.column) return;
                const [r0, r1] = rg.row; const [c0, c1] = rg.column;
                for (let r = Math.max(1, r0); r <= r1; r++) {
                    for (let c = c0; c <= c1; c++) {
                        const colDef = cols[c];
                        if (colDef && ! colDef.readonly) {
                            markDirty(sheetOrder, r, colDef.key);
                            batchEdits.push({ sheetOrder, sheetRow: r, colKey: colDef.key });
                        }
                    }
                    setTimeout(() => recalcRow(sheetOrder, r), 30);
                }
            });
            if (batchEdits.length > 0) whisperCellBatch(batchEdits);
        }

        // ===== Realtime via Reverb whisper =====
        const WHISPER_DEBOUNCE_MS = 250;
        const _whisperPending = new Map();
        let _privateChan = null;
        let _needsResync = false;

        function whisperCellEdit(sheetOrder, sheetRow, colKey) {
            if (! window.Echo || ! _privateChan) return;
            const k = `${sheetOrder}:${sheetRow}:${colKey}`;
            if (_whisperPending.has(k)) clearTimeout(_whisperPending.get(k));
            _whisperPending.set(k, setTimeout(() => {
                _whisperPending.delete(k);
                try {
                    const cols = colsForOrder(sheetOrder);
                    const colIndex = cols.findIndex(c => c.key === colKey);
                    if (colIndex < 0) return;
                    const cellValue = luckysheet.getCellValue(sheetRow, colIndex, { order: sheetOrder });
                    const idCol = cols.findIndex(c => c.key === 'id');
                    const rowId = idCol >= 0 ? luckysheet.getCellValue(sheetRow, idCol, { order: sheetOrder }) : null;
                    if (rowId == null || rowId === '') return;
                    _privateChan.whisper('cell-edit', {
                        ns: SHEET_KEY, sheetOrder, sheetRow,
                        rowId: parseInt(rowId) || null, colKey, value: cellValue,
                        editor: { id: CURRENT_USER.id, name: CURRENT_USER.name }, ts: Date.now(),
                    });
                } catch (e) { console.warn('[whisper] cell-edit failed:', e); }
            }, WHISPER_DEBOUNCE_MS));
        }
        function whisperCellBatch(edits) {
            if (! window.Echo || ! _privateChan) return;
            try {
                const payload = [];
                edits.forEach(({ sheetOrder, sheetRow, colKey }) => {
                    const cols = colsForOrder(sheetOrder);
                    const colIndex = cols.findIndex(c => c.key === colKey);
                    if (colIndex < 0) return;
                    const idCol = cols.findIndex(c => c.key === 'id');
                    const rowId = idCol >= 0 ? luckysheet.getCellValue(sheetRow, idCol, { order: sheetOrder }) : null;
                    if (rowId == null || rowId === '') return;
                    const cellValue = luckysheet.getCellValue(sheetRow, colIndex, { order: sheetOrder });
                    payload.push({ sheetOrder, sheetRow, rowId: parseInt(rowId), colKey, value: cellValue });
                });
                if (payload.length === 0) return;
                _privateChan.whisper('cell-batch', { ns: SHEET_KEY, edits: payload, editor: { id: CURRENT_USER.id, name: CURRENT_USER.name }, ts: Date.now() });
            } catch (e) { console.warn('whisper batch failed:', e); }
        }
        function applyRemoteCellEdit(edit, editorName) {
            try {
                const cols = colsForOrder(edit.sheetOrder);
                const colIndex = cols.findIndex(c => c.key === edit.colKey);
                if (colIndex < 0) return;
                const idCol = cols.findIndex(c => c.key === 'id');
                if (idCol < 0) return;
                const sheets = luckysheet.getluckysheetfile();
                const sheet = sheets[edit.sheetOrder];
                if (! sheet?.data) return;
                let targetRow = -1;
                for (let r = 1; r < sheet.data.length; r++) {
                    const cell = sheet.data[r]?.[idCol];
                    const v = cell?.v ?? cell?.m;
                    if (v != null && parseInt(v) === edit.rowId) { targetRow = r; break; }
                }
                if (targetRow < 0) return;
                markSystemCell(edit.sheetOrder, targetRow, colIndex, 400);
                try { luckysheet.setCellValue(targetRow, colIndex, edit.value ?? '', { order: edit.sheetOrder }); } catch (e) {}
                flashRemoteCell(edit.sheetOrder, targetRow, colIndex, editorName);
            } catch (e) { console.warn('applyRemoteCellEdit failed:', e); }
        }
        function flashRemoteCell(sheetOrder, row, col, editorName) {
            try {
                const sheet = luckysheet.getluckysheetfile()[sheetOrder];
                if (! sheet?.data?.[row]?.[col]) return;
                const oldBg = sheet.data[row][col].bg;
                markSystemCell(sheetOrder, row, col, 200);
                try { luckysheet.setCellFormat(row, col, 'bg', '#fef3c7', { order: sheetOrder }); } catch (e) {}
                setTimeout(() => { markSystemCell(sheetOrder, row, col, 200); try { luckysheet.setCellFormat(row, col, 'bg', oldBg || null, { order: sheetOrder }); } catch (e) {} }, 1500);
                _showEditorActivity(editorName);
            } catch (e) {}
        }
        const _activityShown = new Map();
        function _showEditorActivity(editorName) {
            if (! editorName) return;
            const now = Date.now(); const last = _activityShown.get(editorName) || 0;
            if (now - last < 5000) return;
            _activityShown.set(editorName, now);
            const el = document.getElementById('liveActivity'); if (! el) return;
            el.innerHTML = `<i class="bi bi-pencil-fill text-warning"></i> <strong>${editorName}</strong> đang sửa…`;
            el.style.opacity = '1';
            clearTimeout(_showEditorActivity._t);
            _showEditorActivity._t = setTimeout(() => { el.style.opacity = '0'; }, 3000);
        }

        // ===== Scan + clear duplicate ID =====
        function scanDuplicateIds() {
            try {
                const allSheets = luckysheet.getluckysheetfile();
                const activeOrder = luckysheet.getSheet().order ?? 0;
                const seenIds = new Set();
                const toClear = [];
                allSheets.forEach((sheet, sheetOrder) => {
                    const idCol = colsForOrder(sheetOrder).findIndex(c => c.key === 'id');
                    if (idCol < 0) return;
                    const data = sheet.data; if (! data) return;
                    for (let r = 1; r < data.length; r++) {
                        const cell = data[r]?.[idCol];
                        const v = cell?.v ?? cell?.m;
                        if (v == null || v === '') continue;
                        const numId = parseInt(v); if (! numId) continue;
                        if (seenIds.has(numId)) toClear.push({ r, sheetOrder, idCol });
                        else seenIds.add(numId);
                    }
                });
                if (toClear.length === 0) return 0;
                toClear.forEach(({ r, sheetOrder, idCol }) => {
                    try { markSystemCell(sheetOrder, r, idCol, 300); luckysheet.setCellValue(r, idCol, '', { order: sheetOrder }); }
                    catch (e) { console.warn('clear dup id fail:', e); }
                });
                toast(`Đã xoá <strong>${toClear.length}</strong> STT trùng (copy/paste) — khi lưu sẽ tạo dòng mới.`, 'info');
                return toClear.length;
            } catch (e) { console.warn('scanDuplicateIds failed:', e); return 0; }
        }

        window.closeDropdown = function (btn) {
            const dropdown = btn.closest('.dropdown'); if (! dropdown) return;
            const toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
            if (toggle) bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
        };

        // ===== Date filter =====
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

            const activeIdx = luckysheet.getSheet().order ?? 0;
            const cols = colsForOrder(activeIdx);
            const colIndex = cols.findIndex(c => c.key === colKey);
            if (colIndex < 0) { toast('Cột này không có trong sheet đang mở.', 'warning'); return; }

            clearDateFilter(false);
            const today = new Date(); today.setHours(0, 0, 0, 0);
            const n = parseInt(document.getElementById('dfN').value || 0);
            const from = parseDateFromCell(document.getElementById('dfFrom').value);
            const to   = parseDateFromCell(document.getElementById('dfTo').value);
            if (to) to.setHours(23, 59, 59);

            const sheet = luckysheet.getluckysheetfile()[activeIdx];
            const data  = sheet.data;
            const custIdx = cols.findIndex(c => c.key === 'customer');
            let matchedCount = 0; const toHide = []; const heights = {};

            for (let r = 1; r < data.length; r++) {
                const row = data[r]; if (! row) continue;
                const hasCust = custIdx >= 0 && row[custIdx] && (row[custIdx].v ?? row[custIdx].m);
                if (! hasCust) continue;
                const cell = row[colIndex];
                const cellDate = parseDateFromCell(cell ? (cell.v ?? cell.m) : null);
                let match = false;
                switch (op) {
                    case 'overdue':     match = cellDate && cellDate < today; break;
                    case 'within_days': { const limit = new Date(today); limit.setDate(limit.getDate() + n); match = cellDate && cellDate >= today && cellDate <= limit; break; }
                    case 'due_today':   match = cellDate && cellDate.getTime() === today.getTime(); break;
                    case 'between':     match = cellDate && from && to && cellDate >= from && cellDate <= to; break;
                    case 'empty':       match = ! cellDate; break;
                    case 'not_empty':   match = !! cellDate; break;
                }
                if (match) matchedCount++; else { toHide.push(r); heights[r] = 0; }
            }
            if (Object.keys(heights).length > 0) luckysheet.setRowHeight(heights);
            const colTitle = cols[colIndex].title;
            const opLabels = { overdue:'quá hạn', within_days:`≤ ${n} ngày tới`, due_today:'đúng hôm nay', between:'trong khoảng', empty:'trống', not_empty:'có giá trị' };
            dateFilterState = { active: true, hiddenRows: toHide, colKey, op, label: `${colTitle} · ${opLabels[op]}`, matched: matchedCount };
            updateFilterChip(matchedCount);
            toast(`Lọc xong: <strong>${matchedCount}</strong> dòng khớp.`);
        }
        window.clearDateFilter = function (notify = true) {
            if (dateFilterState.hiddenRows.length > 0) {
                const heights = {}; dateFilterState.hiddenRows.forEach(r => { heights[r] = 32; });
                luckysheet.setRowHeight(heights);
            }
            dateFilterState = { active: false, hiddenRows: [], colKey: null, op: null, label: null, matched: 0 };
            updateFilterChip(0);
            if (notify) toast('Đã xoá lọc — hiện tất cả dòng.', 'info');
        };
        function updateFilterChip(matched) {
            const chip = document.getElementById('filterChip'); const badge = document.getElementById('dfBadge');
            if (dateFilterState.active) {
                chip.innerHTML = `<span class="filter-chip"><i class="bi bi-funnel-fill"></i> ${dateFilterState.label} — <strong>${matched}</strong> dòng
                    <button type="button" onclick="clearDateFilter()" title="Xoá lọc"><i class="bi bi-x-circle-fill"></i></button></span>`;
                badge.classList.remove('d-none'); badge.textContent = matched;
            } else { chip.innerHTML = ''; badge.classList.add('d-none'); }
        }
        function populateDateFilterColumns() {
            const sel = document.getElementById('dfColumn');
            const seen = new Set(); const opts = ['<option value="">— Chọn cột —</option>'];
            [0, 1].forEach(o => colsForOrder(o).forEach(c => {
                if (c.type === 'date' && ! seen.has(c.key)) { seen.add(c.key); opts.push(`<option value="${c.key}">${c.title}</option>`); }
            }));
            sel.innerHTML = opts.join('');
        }
        function setupDateFilter() {
            const op = document.getElementById('dfOperator'); if (! op) return;
            op.addEventListener('change', () => {
                document.getElementById('dfParamN').classList.toggle('d-none', op.value !== 'within_days');
                document.getElementById('dfParamRange').classList.toggle('d-none', op.value !== 'between');
            });
            document.getElementById('btnApplyFilter').addEventListener('click', applyDateFilter);
            document.getElementById('btnClearFilter').addEventListener('click', () => clearDateFilter());
        }

        // ===== Cột hiển thị (ẩn/hiện cá nhân) =====
        window.toggleAllCols = function (show) {
            document.querySelectorAll('.col-pref-toggle').forEach(cb => cb.checked = show);
            updateCvCounters();
        };
        // Bật/tắt cả 1 NHÓM (theo nút bấm trong nhóm đó)
        window.toggleGroupCols = function (btn, show) {
            btn.closest('.cv-group').querySelectorAll('.col-pref-toggle').forEach(cb => cb.checked = show);
            updateCvCounters();
        };
        // Bật/tắt cả 1 SHEET (HẠ HPH / HẠ ICD)
        window.toggleSheetCols = function (btn, show) {
            btn.closest('.cv-sheet').querySelectorAll('.col-pref-toggle').forEach(cb => cb.checked = show);
            updateCvCounters();
        };
        function updateCvCounters() {
            let shown = 0;
            document.querySelectorAll('.col-pref-toggle').forEach(cb => { if (cb.checked) shown++; });
            const a = document.getElementById('cvShownNum'); if (a) a.textContent = shown;
            const b = document.getElementById('colsCountBadge'); if (b) b.textContent = shown;
        }
        function setupCvSearch() {
            const input = document.getElementById('cvSearch'); if (! input) return;
            input.addEventListener('input', (e) => {
                const q = e.target.value.trim().toLowerCase(); let any = false;
                document.querySelectorAll('#cvBody .cv-row').forEach(row => {
                    const match = ! q || (row.dataset.name || '').includes(q);
                    row.style.display = match ? '' : 'none'; if (match) any = true;
                });
                document.getElementById('cvEmpty').classList.toggle('d-none', any);
            });
        }

        // ===== Build celldata cho 1 sheet =====
        function buildCellData(rows, sheetOrder) {
            const cols = colsForOrder(sheetOrder);
            const celldata = [];

            // Formatting overlay (bg, fc…) bake-in theo (id, colKey)
            const overlayMap = new Map();
            if (snapshot?.formatting) {
                const dir = dirForOrder(sheetOrder);
                (snapshot.formatting[dir] || []).forEach(entry => {
                    if (! entry.id) return;
                    const id = parseInt(entry.id);
                    if (! overlayMap.has(id)) overlayMap.set(id, new Map());
                    overlayMap.get(id).set(entry.col, entry.fmt || {});
                });
            }

            // Header
            cols.forEach((c, ci) => {
                celldata.push({ r: 0, c: ci, v: {
                    v: c.title, m: c.title, bl: 1,
                    bg: GROUP_HEADER_BG[c.group] || '#e1e6f1', fc: '#000000',
                    ht: 0, vt: 0, tb: 2,
                }});
            });

            rows.forEach((row, ri) => {
                const sheetRow = ri + 1;
                const rowOverlay = row.id != null ? overlayMap.get(parseInt(row.id)) : null;

                // Precompute giá trị các ô CÔNG THỨC bằng JS (trái→phải).
                const computed = {};
                const getVal = (key) => {
                    if (key in computed) return computed[key];
                    const v = row[key];
                    if (v == null || v === '') return 0;
                    const n = parseCellValue({ v }, 'vnd');
                    return (typeof n === 'number' && ! isNaN(n)) ? n : 0;
                };
                cols.forEach(c => { if (c.formula) computed[c.key] = computeFormula(c.formula, getVal); });

                cols.forEach((c, ci) => {
                    const hasFormula = !! c.formula;
                    let raw = hasFormula ? computed[c.key] : row[c.key];
                    const isEmpty = raw == null || raw === '';
                    const readonly = !! c.readonly;
                    const overlayFmt = rowOverlay?.get(c.key) || null;

                    // Ô công thức: chỉ render khi có dữ liệu nguồn (giá trị != 0) để không
                    // làm sheet đầy "0 VNĐ"; ô thường rỗng + không overlay → bỏ qua.
                    const blankFormula = hasFormula && (raw === 0 || isEmpty);
                    if (isEmpty && ! readonly && ! overlayFmt && ! hasFormula) return;
                    if (blankFormula && ! overlayFmt) return;

                    let displayed;
                    if (c.type === 'vnd')         displayed = fmtVND(raw);
                    else if (c.type === 'number') displayed = fmtNumber(raw);
                    else if (c.type === 'date')   displayed = fmtDate(raw);
                    else                          displayed = (raw == null ? '' : String(raw));

                    const cell = { v: isEmpty ? '' : raw, m: displayed, ct: ctFor(c.type), tb: 2, vt: 0 };
                    if (c.type === 'vnd' || c.type === 'number') cell.ht = 2;
                    // Gắn công thức A1 (=AB2+AC2…) cho ô CÔNG THỨC → Luckysheet engine
                    // tự tính realtime mỗi khi ô nguồn đổi (như Google Sheets).
                    if (hasFormula) cell.f = buildA1Formula(c.formula, cols, sheetRow);
                    // Ô CÔNG THỨC và ô READONLY: nền xám + chữ xám + lock (lo:1) → user thấy
                    // rõ là ô tự sinh, không gõ vào được (cellEditBefore cũng chặn ngay khi click).
                    if (readonly || hasFormula) { cell.bg = '#f4f6fb'; cell.fc = '#7987a1'; cell.lo = 1; }
                    if (overlayFmt) Object.entries(overlayFmt).forEach(([k, v]) => { if (k === 'vt' || k === 'tb' || k === 'ht') return; cell[k] = v; });

                    celldata.push({ r: sheetRow, c: ci, v: cell });
                });
            });
            return celldata;
        }

        function parseCellValue(cell, type) {
            if (!cell) return '';
            let raw = cell.v;
            if (raw === undefined || raw === null) raw = cell.m;
            if (raw === undefined || raw === null) return '';
            const s = String(raw).trim();
            if (s === '') return '';
            if (type === 'vnd' || type === 'number') {
                // Hỗ trợ cả "3,000,000" (phẩy) lẫn "3.000.000" (chấm kiểu VN):
                let t = s.replace(/[^\d.,\-]/g, '');   // giữ số . , -
                t = t.replace(/,/g, '');                // phẩy = ngăn nghìn → bỏ
                if ((t.match(/\./g) || []).length > 1) t = t.replace(/\./g, '');  // nhiều chấm = ngăn nghìn → bỏ
                const n = parseFloat(t);
                return isNaN(n) ? null : n;
            }
            if (type === 'date') {
                if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
                const m = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
                if (m) return `${m[3]}-${m[2].padStart(2,'0')}-${m[1].padStart(2,'0')}`;
                return s;
            }
            return s;
        }

        function readSheetRows(sheetIndex) { return readSheetRowsWithMeta(sheetIndex).map(m => m.data); }

        function readSheetRowsWithMeta(sheetIndex) {
            const cols = colsForOrder(sheetIndex);
            const sheets = luckysheet.getAllSheets();
            const sheet  = sheets[sheetIndex];
            if (! sheet) return [];
            const cdMap = new Map();
            if (Array.isArray(sheet.celldata)) sheet.celldata.forEach(cd => { if (cd && cd.r != null && cd.c != null) cdMap.set(`${cd.r}:${cd.c}`, cd.v); });
            const rowIndices = new Set();
            if (Array.isArray(sheet.data)) for (let r = 1; r < sheet.data.length; r++) if (sheet.data[r]) rowIndices.add(r);
            cdMap.forEach((_, key) => { const r = parseInt(key.split(':')[0]); if (r > 0) rowIndices.add(r); });

            const rows = [];
            Array.from(rowIndices).sort((a, b) => a - b).forEach(r => {
                const obj = {}; const formulas = {};
                cols.forEach((c, ci) => {
                    let cell = sheet.data?.[r]?.[ci];
                    if (! cell || (cell.v == null && cell.m == null)) cell = cdMap.get(`${r}:${ci}`);
                    let v = parseCellValue(cell, c.type);
                    if (c.key === 'id') v = v === '' ? null : (parseInt(v) || null);
                    obj[c.key] = v;
                    if (cell && typeof cell.f === 'string' && cell.f.length > 0 && c.key !== 'id' && ! c.formula) formulas[c.key] = cell.f;
                });
                obj.cell_formulas = Object.keys(formulas).length > 0 ? formulas : null;
                rows.push({ data: obj, sheetRow: r });
            });
            return rows;
        }

        // ===== Formatting overlay (persist styling) =====
        const STYLE_KEYS = ['bg', 'fc', 'bd', 'bl', 'it', 'un', 'cl', 'fs', 'ff'];
        function extractFormatting(sheetOrder) {
            try {
                const cols = colsForOrder(sheetOrder);
                const idColIdx = cols.findIndex(c => c.key === 'id');
                if (idColIdx < 0) return [];
                const sheet = luckysheet.getAllSheets()[sheetOrder];
                if (! sheet) return [];
                const rowToId = new Map();
                if (Array.isArray(sheet.data)) for (let r = 1; r < sheet.data.length; r++) {
                    const v = sheet.data[r]?.[idColIdx]?.v ?? sheet.data[r]?.[idColIdx]?.m;
                    if (v != null && v !== '') rowToId.set(r, parseInt(v));
                }
                if (Array.isArray(sheet.celldata)) sheet.celldata.forEach(cd => {
                    if (cd.c === idColIdx && cd.v) { const v = cd.v.v ?? cd.v.m; if (v != null && v !== '' && ! rowToId.has(cd.r)) rowToId.set(cd.r, parseInt(v)); }
                });
                const cellMap = new Map();
                const processCell = (r, c, cellV) => {
                    if (! cellV || r === 0) return;
                    const col = cols[c]; if (! col || col.readonly) return;
                    const id = rowToId.get(r); if (! id) return;
                    const fmt = {};
                    STYLE_KEYS.forEach(k => { if (cellV[k] !== undefined && cellV[k] !== null && cellV[k] !== '') fmt[k] = cellV[k]; });
                    if (Object.keys(fmt).length === 0) return;
                    const key = `${r}:${c}`; const existing = cellMap.get(key);
                    cellMap.set(key, { id, col: col.key, fmt: existing ? { ...existing.fmt, ...fmt } : fmt });
                };
                if (Array.isArray(sheet.data)) for (let r = 1; r < sheet.data.length; r++) { const row = sheet.data[r]; if (! row) continue; for (let c = 0; c < row.length; c++) processCell(r, c, row[c]); }
                if (Array.isArray(sheet.celldata)) sheet.celldata.forEach(cd => processCell(cd.r, cd.c, cd.v));
                return Array.from(cellMap.values());
            } catch (e) { console.warn('extractFormatting failed:', e); return []; }
        }
        function applyFormattingOverlay(sheetOrder, overlay) {
            if (! Array.isArray(overlay) || overlay.length === 0) return;
            try {
                const cols = colsForOrder(sheetOrder);
                const idColIdx = cols.findIndex(c => c.key === 'id');
                if (idColIdx < 0) return;
                const sheet = luckysheet.getAllSheets()[sheetOrder];
                if (! sheet?.data) return;
                const idToRow = new Map();
                for (let r = 1; r < sheet.data.length; r++) { const v = sheet.data[r]?.[idColIdx]?.v ?? sheet.data[r]?.[idColIdx]?.m; if (v != null && v !== '') idToRow.set(parseInt(v), r); }
                if (Array.isArray(sheet.celldata)) sheet.celldata.forEach(cd => { if (cd.c === idColIdx && cd.v) { const v = cd.v.v ?? cd.v.m; if (v != null && v !== '' && ! idToRow.has(parseInt(v))) idToRow.set(parseInt(v), cd.r); } });
                overlay.forEach(entry => {
                    const sheetRow = idToRow.get(parseInt(entry.id)); if (sheetRow == null) return;
                    const colIdx = cols.findIndex(c => c.key === entry.col); if (colIdx < 0) return;
                    Object.entries(entry.fmt || {}).forEach(([k, v]) => { if (k === 'vt' || k === 'tb' || k === 'ht') return; try { markSystemCell(sheetOrder, sheetRow, colIdx, 400); luckysheet.setCellFormat(sheetRow, colIdx, k, v, { order: sheetOrder }); } catch (e) {} });
                });
            } catch (e) { console.warn('applyFormattingOverlay failed:', e); }
        }

        function cellValuesEqual(a, b) {
            const norm = (x) => { if (x == null || x === '') return ''; if (typeof x === 'number') return String(x); return String(x).trim(); };
            return norm(a) === norm(b);
        }
        function buildDbRowsById(dir) {
            const map = new Map();
            const rows = rowsByDir[dir] || [];
            (Array.isArray(rows) ? rows : Array.from(rows)).forEach(r => { if (r.id != null) map.set(parseInt(r.id), r); });
            return map;
        }

        let rowsByDir = { hph: [], icd: [] };
        let snapshot = null;
        let sheetVersion = 0;
        let _originalIds = { hph: new Set(), icd: new Set() };
        function recordOriginalIds() {
            _originalIds = { hph: new Set(), icd: new Set() };
            ['hph', 'icd'].forEach(dir => (rowsByDir[dir] || []).forEach(r => { if (r.id != null) _originalIds[dir].add(parseInt(r.id)); }));
        }

        async function loadData() {
            const res  = await fetch(ROUTES.data, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            rowsByDir    = json.data;
            snapshot     = json.snapshot;
            sheetVersion = json.version ?? 0;
            renderSheet();
            updateVersionBadge(json.editor, json.updatedAt);
            if (snapshot?.formatting) {
                setTimeout(() => {
                    applyFormattingOverlay(0, snapshot.formatting.hph || []);
                    applyFormattingOverlay(1, snapshot.formatting.icd || []);
                    try { luckysheet.setSheetActive(luckysheet.getSheet()?.order ?? 0); } catch (e) {}
                }, 300);
            }
            recordOriginalIds(); resetDirty(); _needsResync = false;
        }

        function flashCellSilent(sheetOrder, row, col) {
            try {
                const sheet = luckysheet.getluckysheetfile()[sheetOrder];
                if (! sheet?.data?.[row]?.[col]) return;
                const oldBg = sheet.data[row][col].bg;
                markSystemCell(sheetOrder, row, col, 200);
                luckysheet.setCellFormat(row, col, 'bg', '#dbeafe', { order: sheetOrder });
                setTimeout(() => { markSystemCell(sheetOrder, row, col, 200); try { luckysheet.setCellFormat(row, col, 'bg', oldBg || null, { order: sheetOrder }); } catch (e) {} }, 1500);
            } catch (e) {}
        }

        async function softMerge() {
            try {
                const res = await fetch(ROUTES.data, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                const newRowsByDir = json.data;
                sheetVersion = json.version ?? sheetVersion;
                let updated = 0, appended = 0, deleted = 0, skippedDirty = 0;
                const changedSheets = new Set();

                [0, 1].forEach((sheetOrder) => {
                    const cols = colsForOrder(sheetOrder);
                    const dir = dirForOrder(sheetOrder);
                    const newRows = (newRowsByDir[dir] || []).filter(r => r.id != null);
                    const newById = new Map(newRows.map(r => [parseInt(r.id), r]));
                    const toDeleteSheetRows = [];
                    const rendered = readSheetRowsWithMeta(sheetOrder);
                    const dirtyMap = dirtyCells[sheetOrder] || new Map();
                    const renderedIds = new Set();
                    let lastSheetRow = 0;

                    rendered.forEach(m => {
                        const id = m.data.id != null ? parseInt(m.data.id) : null;
                        const hasContent = id != null || Object.keys(m.data).some(k => { const v = m.data[k]; return v != null && v !== '' && k !== 'id'; });
                        if (hasContent && m.sheetRow > lastSheetRow) lastSheetRow = m.sheetRow;
                        if (! id) return;
                        renderedIds.add(id);
                        const newRow = newById.get(id);
                        if (! newRow) { const dk = dirtyMap.get(m.sheetRow); if (! dk || dk.size === 0) toDeleteSheetRows.push(m.sheetRow); return; }
                        const rowDirtyKeys = dirtyMap.get(m.sheetRow) || new Set();
                        let rowChanged = false;
                        cols.forEach((col, ci) => {
                            if (col.readonly || col.formula) return;   // skip id + auto-formula cols
                            if (rowDirtyKeys.has(col.key)) { skippedDirty++; return; }
                            const newVal = newRow[col.key]; const curVal = m.data[col.key];
                            if (! cellValuesEqual(newVal, curVal)) {
                                try { markSystemCell(sheetOrder, m.sheetRow, ci, 500); luckysheet.setCellValue(m.sheetRow, ci, newVal ?? '', { order: sheetOrder }); flashCellSilent(sheetOrder, m.sheetRow, ci); updated++; rowChanged = true; changedSheets.add(sheetOrder); }
                                catch (e) {}
                            }
                        });
                        if (rowChanged) setTimeout(() => recalcRow(sheetOrder, m.sheetRow), 40);
                    });

                    const toAppend = newRows.filter(r => ! renderedIds.has(parseInt(r.id)));
                    toAppend.forEach((row, i) => {
                        const targetRow = lastSheetRow + 1 + i;
                        cols.forEach((col, ci) => {
                            const val = row[col.key];
                            if (val == null || val === '') return;
                            try { markSystemCell(sheetOrder, targetRow, ci, 500); luckysheet.setCellValue(targetRow, ci, val, { order: sheetOrder }); flashCellSilent(sheetOrder, targetRow, ci); } catch (e) {}
                        });
                        setTimeout(() => recalcRow(sheetOrder, targetRow), 30);
                        appended++; changedSheets.add(sheetOrder);
                    });

                    if (toDeleteSheetRows.length > 0) {
                        toDeleteSheetRows.sort((a, b) => b - a);
                        toDeleteSheetRows.forEach(r => {
                            try { for (let c = 0; c < cols.length; c++) markSystemCell(sheetOrder, r, c, 800); luckysheet.deleteRowCol('row', r, 1, { order: sheetOrder }); deleted++; changedSheets.add(sheetOrder); }
                            catch (e) { try { for (let c = 0; c < cols.length; c++) { markSystemCell(sheetOrder, r, c, 500); luckysheet.setCellValue(r, c, '', { order: sheetOrder }); } deleted++; changedSheets.add(sheetOrder); } catch (e2) {} }
                        });
                    }
                });

                if (changedSheets.size > 0) setTimeout(() => { try { luckysheet.setSheetActive(luckysheet.getSheet()?.order ?? 0); } catch (e) {} }, 100);
                rowsByDir = newRowsByDir; snapshot = json.snapshot;
                recordOriginalIds(); updateVersionBadge(json.editor, json.updatedAt);
                if (json.snapshot?.formatting) { applyFormattingOverlay(0, json.snapshot.formatting.hph || []); applyFormattingOverlay(1, json.snapshot.formatting.icd || []); }
                return { updated, appended, deleted, skippedDirty };
            } catch (e) { console.warn('softMerge failed, fallback loadData:', e); await loadData(); return { updated: 0, appended: 0, deleted: 0, skippedDirty: 0, fallback: true }; }
        }

        function updateVersionBadge(editor, at) {
            const badge = document.getElementById('versionBadge');
            if (editor) {
                const t = at ? new Date(at).toLocaleString('vi-VN') : '';
                badge.innerHTML = `<i class="bi bi-clock-history"></i> v${sheetVersion} · ${editor.name} · ${t}`;
                badge.className = 'badge badge-soft-primary';
            } else { badge.innerHTML = `<i class="bi bi-clock-history"></i> Chưa có snapshot`; badge.className = 'badge badge-soft-warning'; }
        }

        function buildColumnlen(sheetDir) {
            const cols = colsForOrder(orderForDir(sheetDir));
            const columnlen = cols.reduce((acc, c, i) => (acc[i] = c.width, acc), {});
            const overlay = snapshot?.columnlen?.[sheetDir];
            if (overlay && typeof overlay === 'object') cols.forEach((c, i) => { if (overlay[c.key] != null) columnlen[i] = overlay[c.key]; });
            return columnlen;
        }
        function extractColumnWidths(sheetOrder) {
            const cols = colsForOrder(sheetOrder);
            const sheet = luckysheet.getAllSheets()[sheetOrder];
            if (! sheet?.config?.columnlen) return {};
            const result = {};
            Object.entries(sheet.config.columnlen).forEach(([idx, width]) => { const col = cols[parseInt(idx)]; if (col && col.key && width != null) result[col.key] = width; });
            return result;
        }

        function renderSheet() {
            const makeBorderInfo = (totalRows, nCols) => ([{ rangeType: 'range', borderType: 'border-all', style: '1', color: '#000000', range: [{ row: [0, totalRows - 1], column: [0, nCols - 1] }] }]);
            const sheets = [0, 1].map(order => {
                const cols = colsForOrder(order);
                const dir = dirForOrder(order);
                const rowCount = Math.max((rowsByDir[dir]?.length || 0) + 50, 80);
                return {
                    name: SHEET_NAMES[order], order, color: SHEET_COLORS[order],
                    config: { columnlen: buildColumnlen(dir), rowlen: { 0: 48 }, customHeight: { 0: 1 }, borderInfo: makeBorderInfo(rowCount, cols.length) },
                    celldata: buildCellData(rowsByDir[dir] || [], order),
                    row: rowCount, column: cols.length + 1,
                    frozen: { type: 'rangeBoth', range: { row_focus: 0, column_focus: 1 } },
                    defaultRowHeight: 36,
                };
            });

            luckysheet.destroy();
            luckysheet.create({
                container: 'luckysheet', lang: 'en',
                showinfobar: false, showsheetbar: true, showstatisticBar: false,
                enableAddRow: true, enableAddBackTop: false, allowEdit: true,
                data: sheets,
                showtoolbarConfig: {
                    undoRedo: true, paintFormat: true, currencyFormat: true, percentageFormat: true,
                    numberDecrease: true, numberIncrease: true, moreFormats: true,
                    font: true, fontSize: true, bold: true, italic: true, strikethrough: true, underline: true,
                    textColor: true, fillColor: true, border: true, mergeCell: true,
                    horizontalAlignMode: true, verticalAlignMode: true, textWrapMode: true, textRotateMode: false,
                    image: false, link: false, chart: false, postil: false, pivotTable: false, function: true,
                    frozenMode: true, sortAndFilter: false, conditionalFormat: false, dataVerification: true,
                    splitColumn: false, screenshot: false, findAndReplace: true, protection: false, print: true,
                },
                hook: {
                    workbookCreateAfter() {
                        try { setTimeout(recalcAllRows, 80); } catch (e) {}
                    },
                    cellMousedown() { setTimeout(updateFormulaHint, 10); },
                    rangeSelect()   { setTimeout(updateFormulaHint, 10); },
                    cellUpdateBefore(r, c, value, isRefresh) {
                        if (window.__DEBUG_RECALC) console.log('[cellUpdateBefore]', { r, c, value, isRefresh });
                    },
                    updated(operate) {
                        if (window.__DEBUG_RECALC) console.log('[updated]', operate, 'lastEdit=', _lastEditCell);
                        // Trên Luckysheet 2.1.13, `cellUpdated` không fire khi user inline-edit.
                        // Dùng `updated` (fires sau MỌI op: edit / paste / set / del).
                        // QUAN TRỌNG: sau khi user gõ + Enter, Luckysheet di chuyển selection xuống
                        // 1 dòng → operate.range trỏ vào ô DƯỚI ô vừa edit. Ta ƯU TIÊN
                        // `_lastEditCell` (capture trong cellEditBefore — đúng ô user nhập) để
                        // tránh recalc nhầm dòng kế bên.
                        try {
                            if (! operate || _systemUpdate || _recalcInProgress) return;
                            const sheetOrder = luckysheet.getSheet()?.order ?? 0;
                            const cols = colsForOrder(sheetOrder);
                            const cells = [];   // {r, c}
                            const seen = new Set();
                            const addCell = (r, c) => {
                                if (r == null || c == null || r < 1) return;
                                const k = `${r}:${c}`; if (seen.has(k)) return; seen.add(k);
                                cells.push({ r, c });
                            };
                            // 1) ƯU TIÊN ô vừa được inline-edit (chính xác 100%).
                            if (_lastEditCell && _lastEditCell.sheetOrder === sheetOrder) {
                                addCell(_lastEditCell.r, _lastEditCell.c);
                                _lastEditCell = null;   // consume — chỉ dùng 1 lần.
                            }
                            // 2) Mở rộng cho paste / setRangeValue (multi-cell ops) — range của
                            //    các op này KHÔNG bị shift sau khi commit.
                            const collectRange = (rg) => {
                                if (! rg?.row || ! rg?.column) return;
                                for (let r = Math.max(1, rg.row[0]); r <= rg.row[1]; r++)
                                    for (let c = rg.column[0]; c <= rg.column[1]; c++)
                                        addCell(r, c);
                            };
                            if (operate.range && cells.length === 0) {
                                (Array.isArray(operate.range) ? operate.range : [operate.range]).forEach(collectRange);
                            }
                            if (cells.length === 0) return;
                            const rows = new Set();
                            cells.forEach(({ r, c }) => {
                                if (isSystemCell(sheetOrder, r, c)) return;
                                const colDef = cols[c]; if (! colDef || colDef.formula || colDef.readonly) return;
                                markDirty(sheetOrder, r, colDef.key);
                                whisperCellEdit(sheetOrder, r, colDef.key);
                                if (colDef.type === 'vnd' || colDef.type === 'number') setTimeout(() => formatMoneyCell(sheetOrder, r, c, colDef.type), 20);
                                else if (colDef.type === 'date')                        setTimeout(() => formatDateCell(sheetOrder, r, c), 20);
                                rows.add(r);
                                if (colDef.key === 'id') setTimeout(scanDuplicateIds, 100);
                            });
                            rows.forEach(r => setTimeout(() => recalcRow(sheetOrder, r), 60));
                        } catch (e) { console.warn('updated hook failed:', e); }
                    },
                    cellEditBefore(range) {
                        const sheetOrder = luckysheet.getSheet()?.order ?? 0;
                        const cols = colsForOrder(sheetOrder);
                        const ranges = Array.isArray(range) ? range : [range];
                        // Capture ô đang được mở editor — đây là Single Source of Truth cho
                        // hook `updated` (xem giải thích ở `updated` về vụ shift selection).
                        let captured = null;
                        for (const r of ranges) {
                            if (r == null) continue;
                            let startCol, endCol, startRow;
                            if (typeof r === 'number') { startCol = endCol = r; startRow = null; }
                            else if (r.column) {
                                startCol = Array.isArray(r.column) ? r.column[0] : r.column;
                                endCol   = Array.isArray(r.column) ? (r.column[1] ?? startCol) : startCol;
                                startRow = r.row ? (Array.isArray(r.row) ? r.row[0] : r.row) : null;
                            } else continue;
                            for (let c = startCol; c <= endCol; c++) {
                                const colDef = cols[c]; if (! colDef) continue;
                                if (colDef.readonly) { toast(`Cột "<strong>${colDef.title}</strong>" tự động tạo, không sửa được.`, 'info'); return false; }
                                if (colDef.formula)  { toast(`Cột "<strong>${colDef.title}</strong>" là CÔNG THỨC tự cập nhật, không sửa trực tiếp.`, 'info'); return false; }
                            }
                            if (captured == null && startRow != null) captured = { sheetOrder, r: startRow, c: startCol };
                        }
                        if (captured) _lastEditCell = captured;
                    },
                    cellUpdated(r, c, oldValue, newValue, isRefresh) {
                        if (window.__DEBUG_RECALC) console.log('[cellUpdated]', { r, c, oldValue, newValue, isRefresh });
                        if (isRefresh) return;
                        const sheetOrder = luckysheet.getSheet()?.order ?? 0;
                        if (isSystemCell(sheetOrder, r, c)) { if (window.__DEBUG_RECALC) console.log('  → skipped (system cell)'); return; }
                        if (_systemUpdate) { if (window.__DEBUG_RECALC) console.log('  → skipped (_systemUpdate)'); return; }
                        const colDef = colsForOrder(sheetOrder)[c]; if (! colDef) return;
                        if (! colDef.readonly) {
                            markDirty(sheetOrder, r, colDef.key);
                            whisperCellEdit(sheetOrder, r, colDef.key);
                            if (! colDef.formula) {
                                // Định dạng lại ô tiền (dấu phẩy) / ngày (dd/mm/yyyy) ngay sau khi gõ,
                                // rồi TÍNH LẠI các ô tổng của dòng (sau khi format xong).
                                if (colDef.type === 'vnd' || colDef.type === 'number') setTimeout(() => formatMoneyCell(sheetOrder, r, c, colDef.type), 20);
                                else if (colDef.type === 'date')                        setTimeout(() => formatDateCell(sheetOrder, r, c), 20);
                                setTimeout(() => recalcRow(sheetOrder, r), 60);
                            }
                        }
                        if (colDef.key === 'id') setTimeout(scanDuplicateIds, 100);
                    },
                    pasted(range) { _lastEditCell = null; setTimeout(scanDuplicateIds, 150); markRangeDirty(range); },
                    pasteBefore(html, plain, range) { _lastEditCell = null; setTimeout(scanDuplicateIds, 200); markRangeDirty(range); return undefined; },
                }
            });
        }

        // Định dạng lại Ô TIỀN sau khi user gõ → hiện dấu phẩy "3,000,000 VNĐ",
        // đồng thời ép value về SỐ sạch (bỏ mọi separator) để lưu chính xác.
        function formatMoneyCell(sheetOrder, r, c, type) {
            try {
                const sheet = luckysheet.getluckysheetfile()?.[sheetOrder];
                const cell = sheet?.data?.[r]?.[c];
                const raw = cell?.v ?? cell?.m;
                if (raw == null || raw === '') return;
                const num = parseCellValue({ v: raw }, type);
                if (num == null || isNaN(num)) return;
                const fa = type === 'vnd' ? '#,##0" VNĐ"' : '#,##0';
                markSystemCell(sheetOrder, r, c, 400);
                lsSetValue(sheetOrder, r, c, {
                    v: num, m: (type === 'vnd' ? fmtVND(num) : fmtNumber(num)),
                    ct: { fa, t: 'n' }, tb: 2, vt: 0, ht: 2,
                });
            } catch (e) { console.warn('formatMoneyCell failed:', e); }
        }

        // Định dạng lại Ô NGÀY → luôn hiển thị kiểu Việt Nam dd/mm/yyyy.
        // Chấp nhận user gõ 20/10/2026 hoặc 2026-10-20; lưu xuống chuẩn hoá ở backend.
        function formatDateCell(sheetOrder, r, c) {
            try {
                const sheet = luckysheet.getluckysheetfile()?.[sheetOrder];
                const cell = sheet?.data?.[r]?.[c];
                const raw = cell?.v ?? cell?.m;
                if (raw == null || raw === '') return;
                const iso = parseCellValue({ v: raw }, 'date');   // → yyyy-mm-dd (nếu nhận diện được)
                const disp = fmtDate(iso);
                if (disp && disp !== String(raw)) {
                    markSystemCell(sheetOrder, r, c, 400);
                    lsSetValue(sheetOrder, r, c, { v: disp, m: disp, ct: { fa: 'General', t: 'g' }, tb: 2, vt: 0 });
                }
            } catch (e) { console.warn('formatDateCell failed:', e); }
        }

        function setupRealtime() {
            try {
                const isInstance = window.Echo && window.Echo.connector;
                if (! isInstance && typeof Echo === 'function') {
                    const EchoCtor = window.Echo;
                    window.Echo = new EchoCtor({ broadcaster: 'reverb', key: REVERB.key, wsHost: REVERB.host, wsPort: REVERB.port, wssPort: REVERB.port, forceTLS: REVERB.scheme === 'https', enabledTransports: ['ws', 'wss'], auth: { headers: { 'X-CSRF-TOKEN': CSRF } } });
                }
                if (! window.Echo || ! window.Echo.connector) throw new Error('Echo unavailable');
                const status = document.getElementById('liveStatus');
                const pc = window.Echo.connector.pusher.connection;
                pc.bind('connected',    () => { status.innerHTML = '<i class="bi bi-wifi"></i> Live'; status.className = 'badge badge-soft-success'; });
                pc.bind('disconnected', () => { status.innerHTML = '<i class="bi bi-wifi-off"></i> Offline'; status.className = 'badge badge-soft-warning'; });
                pc.bind('error',        (e) => { status.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Lỗi WS'; status.className = 'badge badge-soft-danger'; });

                _privateChan = window.Echo.private('items-sheet');

                _privateChan.listen('.sheet.updated', async (e) => {
                    if (e.sheetKey !== SHEET_KEY) return;
                    sheetVersion = e.version;
                    updateVersionBadge({ id: e.editorId, name: e.editorName }, new Date().toISOString());
                    const result = await softMerge();
                    const { updated, appended, deleted, skippedDirty, fallback } = result;
                    if (fallback) { toast(`<i class="bi bi-person-fill-gear"></i> <strong>${e.editorName}</strong> vừa lưu (v${e.version}). Đã reload.`, 'info'); return; }
                    let msg = `<i class="bi bi-person-fill-gear"></i> <strong>${e.editorName}</strong> vừa lưu (v${e.version}).`;
                    const parts = [];
                    if (updated > 0)      parts.push(`<strong>${updated}</strong> cell update`);
                    if (appended > 0)     parts.push(`<strong>${appended}</strong> dòng mới`);
                    if (deleted > 0)      parts.push(`<strong>${deleted}</strong> dòng xóa`);
                    if (skippedDirty > 0) parts.push(`giữ ${skippedDirty} cell bạn đang sửa`);
                    if (parts.length > 0) { msg += ' ' + parts.join(' • ') + '. <small class="text-muted">(Có thể ở sheet khác — kiểm tra HẠ HPH / HẠ ICD)</small>'; }
                    toast(msg, 'info');
                    if (updated + appended > 0) _showEditorActivity(e.editorName);
                });

                _privateChan.listenForWhisper('cell-edit', (e) => { if (! e || e.ns !== SHEET_KEY) return; if (e.editor?.id === CURRENT_USER.id) return; applyRemoteCellEdit(e, e.editor?.name); });
                _privateChan.listenForWhisper('cell-batch', (e) => { if (! e || e.ns !== SHEET_KEY || ! Array.isArray(e.edits)) return; if (e.editor?.id === CURRENT_USER.id) return; e.edits.forEach(edit => applyRemoteCellEdit(edit, e.editor?.name)); });
            } catch (err) { console.warn('Realtime init failed:', err); }
        }

        document.addEventListener('DOMContentLoaded', () => {
            populateDateFilterColumns();
            loadData();
            setupRealtime();
            setupDateFilter();
            document.getElementById('btnReload').addEventListener('click', loadData);

            // Fallback: click vào sheet → cập nhật dòng công thức (phòng khi hook không bắn)
            const lsEl = document.getElementById('luckysheet');
            if (lsEl) lsEl.addEventListener('click', () => setTimeout(updateFormulaHint, 30));

            // Cột hiển thị
            document.querySelectorAll('.col-pref-toggle').forEach(cb => cb.addEventListener('change', updateCvCounters));
            setupCvSearch();
            const btnApplyCols = document.getElementById('btnApplyCols');
            if (btnApplyCols) btnApplyCols.addEventListener('click', async () => {
                const hidden = [];
                document.querySelectorAll('.col-pref-toggle').forEach(cb => { if (! cb.checked) hidden.push(cb.dataset.key); });
                const res = await fetch(ROUTE_COLUMN_PREFS, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ hidden: [...new Set(hidden)] })
                });
                const json = await res.json();
                if (json.ok) { toast(`Đã lưu (ẩn ${json.hidden.length} cột). Đang tải lại…`); setTimeout(() => location.reload(), 600); }
                else toast('Lưu thất bại.', 'danger');
            });

            document.getElementById('btnSaveAll').addEventListener('click', async () => {
                if (dateFilterState.active) clearDateFilter(false);
                try { if (document.activeElement && document.activeElement.blur) document.activeElement.blur(); } catch (e) {}
                scanDuplicateIds();
                await new Promise(r => setTimeout(r, 300));

                const dirtyAtStart = snapshotDirty();
                const dirtyMeta = []; const seenIds = new Set(); let duplicateCount = 0;

                [0, 1].forEach((sheetOrder) => {
                    const cols = colsForOrder(sheetOrder);
                    const allRows = readSheetRowsWithMeta(sheetOrder);
                    const dirtyMap = dirtyAtStart[sheetOrder] || new Map();
                    const dbRowsById = buildDbRowsById(dirForOrder(sheetOrder));

                    allRows.forEach(m => {
                        const data = m.data;
                        const hasId = data.id != null && data.id !== '';
                        let effectiveId = hasId ? data.id : null;
                        if (effectiveId && seenIds.has(effectiveId)) { duplicateCount++; effectiveId = null; }
                        if (effectiveId) seenIds.add(effectiveId);

                        if (effectiveId) {
                            const dirtyKeys = new Set(dirtyMap.get(m.sheetRow) || []);
                            const dbRow = dbRowsById.get(parseInt(effectiveId));
                            let formulasDirty = false;
                            if (dbRow) {
                                cols.forEach(col => {
                                    if (col.readonly || col.key === 'id') return;
                                    if (! cellValuesEqual(data[col.key], dbRow[col.key])) dirtyKeys.add(col.key);
                                });
                                const a = JSON.stringify(data.cell_formulas || null);
                                const b = JSON.stringify(dbRow.cell_formulas || null);
                                if (a !== b) formulasDirty = true;
                            } else if (data.cell_formulas) formulasDirty = true;

                            if (dirtyKeys.size === 0 && ! formulasDirty) return;
                            const payload = { id: effectiveId };
                            dirtyKeys.forEach(key => { payload[key] = data[key] === undefined ? null : data[key]; });
                            if (formulasDirty || dirtyKeys.size > 0) payload.cell_formulas = data.cell_formulas || null;
                            dirtyMeta.push({ data: payload, sheetRow: m.sheetRow, sheetOrder, isNew: false });
                        } else {
                            const customer = (data.customer ?? '').toString().trim();
                            if (! customer) return;
                            const payload = { ...data, customer };
                            delete payload.id;
                            dirtyMeta.push({ data: payload, sheetRow: m.sheetRow, sheetOrder, isNew: true });
                        }
                    });
                });

                if (duplicateCount > 0) toast(`⚠️ Phát hiện <strong>${duplicateCount}</strong> dòng trùng STT — sẽ tạo thành dòng MỚI.`, 'warning');

                const hphRows = dirtyMeta.filter(m => m.sheetOrder === 0).map(m => m.data);
                const icdRows = dirtyMeta.filter(m => m.sheetOrder === 1).map(m => m.data);

                // Diff deletion
                const currentIds = { hph: new Set(), icd: new Set() };
                [0, 1].forEach((sheetOrder) => {
                    const dir = dirForOrder(sheetOrder);
                    readSheetRowsWithMeta(sheetOrder).forEach(m => { if (m.data.id != null) currentIds[dir].add(parseInt(m.data.id)); });
                });
                let deletedIds = [];
                ['hph', 'icd'].forEach(dir => { _originalIds[dir].forEach(id => { if (! currentIds[dir].has(id)) deletedIds.push(id); }); });
                let _deleteBlocked = 0;
                if (deletedIds.length > 0 && ! CAN_DELETE_ROWS) {
                    _deleteBlocked = deletedIds.length;
                    toast(`⚠️ Bạn KHÔNG có quyền xóa dòng. <strong>${_deleteBlocked}</strong> dòng sẽ được khôi phục sau khi lưu.`, 'danger');
                    deletedIds = [];
                }

                // Formatting + width changes — chỉ khi user KHÔNG bị admin hạn chế cột
                let currentFmt = null, currentColumnlen = null, formattingChanged = false, columnlenChanged = false;
                if (CAN_SAVE_FORMATTING) {
                    currentFmt = { hph: extractFormatting(0), icd: extractFormatting(1) };
                    formattingChanged = JSON.stringify(currentFmt) !== JSON.stringify(snapshot?.formatting || { hph: [], icd: [] });
                    currentColumnlen = { hph: extractColumnWidths(0), icd: extractColumnWidths(1) };
                    columnlenChanged = JSON.stringify(currentColumnlen) !== JSON.stringify(snapshot?.columnlen || { hph: {}, icd: {} });
                }

                if (dirtyMeta.length === 0 && deletedIds.length === 0 && ! formattingChanged && ! columnlenChanged) return toast('Không có thay đổi cần lưu.', 'info');

                const socketId = (() => { try { return window.Echo?.socketId?.() ?? ''; } catch (e) { return ''; } })();
                const formattingPayload = CAN_SAVE_FORMATTING
                    ? { formatting: currentFmt, formatting_scope: [...colsForOrder(0), ...colsForOrder(1)].map(c => c.key), columnlen: currentColumnlen }
                    : null;

                const res = await fetch(ROUTES.bulk, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Socket-ID': socketId, 'Accept': 'application/json' },
                    body: JSON.stringify({ rows: { hph: hphRows, icd: icdRows }, deleted_ids: deletedIds, snapshot: formattingPayload, client_version: sheetVersion })
                });
                const json = await res.json();
                if (! json.ok) { toast(json.message || 'Lưu thất bại.', 'danger'); return; }
                sheetVersion = json.version;

                // Gán STT mới cho dòng vừa tạo
                let updatedNew = 0; const newRowMetas = [];
                if (Array.isArray(json.ids)) {
                    for (let i = 0; i < dirtyMeta.length; i++) {
                        const m = dirtyMeta[i]; const newId = json.ids[i];
                        const idCol = colsForOrder(m.sheetOrder).findIndex(c => c.key === 'id');
                        if (m.isNew && newId != null && idCol >= 0) {
                            try { markSystemCell(m.sheetOrder, m.sheetRow, idCol, 600); luckysheet.setCellValue(m.sheetRow, idCol, newId, { order: m.sheetOrder }); newRowMetas.push({ ...m, newId }); updatedNew++; } catch (e) {}
                        }
                    }
                }

                removeDirtyMatching(dirtyAtStart);
                deletedIds.forEach(id => { _originalIds.hph.delete(id); _originalIds.icd.delete(id); });
                if (Array.isArray(json.ids)) json.ids.forEach((id, i) => { if (id != null && dirtyMeta[i]?.isNew) { _originalIds[dirForOrder(dirtyMeta[i].sheetOrder)].add(parseInt(id)); } });

                if (CAN_SAVE_FORMATTING) {
                    if (! snapshot) snapshot = {};
                    snapshot.formatting = currentFmt; snapshot.columnlen = currentColumnlen;
                }

                if (_deleteBlocked > 0) { updateVersionBadge(CURRENT_USER, new Date().toISOString()); setTimeout(loadData, 800); return; }

                if (updatedNew > 0) {
                    let msg = `Đã lưu — gán <strong>${updatedNew}</strong> STT mới.`;
                    if (json.deleted > 0) msg += ` Đã XÓA <strong>${json.deleted}</strong> dòng.`;
                    toast(msg, 'success');
                } else {
                    let msg = `Đã lưu ${json.saved} dòng`;
                    if (json.deleted > 0) msg += `, đã XÓA ${json.deleted} dòng`;
                    toast(msg + '.', 'success');
                }
                updateVersionBadge(CURRENT_USER, new Date().toISOString());
            });

            document.addEventListener('keydown', (e) => { if ((e.ctrlKey || e.metaKey) && (e.key === 'v' || e.key === 'V')) setTimeout(scanDuplicateIds, 300); });
        });
    </script>
@endpush
