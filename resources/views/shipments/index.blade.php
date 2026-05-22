@extends('layouts.app')

@section('title', 'Follow Up Shipment — ' . $period)

@push('styles')
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/plugins/css/pluginsCss.css' />
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/plugins/plugins.css' />
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/css/luckysheet.css' />
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/assets/iconfont/iconfont.css' />
<style>
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
            <button class="btn btn-outline-secondary" id="btnReload">
                <i class="bi bi-arrow-clockwise me-1"></i> Tải lại
            </button>
            <button class="btn btn-outline-secondary" id="btnResetFormat" title="Xoá định dạng đã lưu">
                <i class="bi bi-eraser me-1"></i> Reset định dạng
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
            data:           @json(route('shipments.data',           ['period' => $period])),
            bulk:           @json(route('shipments.bulk',           ['period' => $period])),
            resetSnapshot:  @json(route('shipments.resetSnapshot',  ['period' => $period])),
            createPeriod:   @json(route('shipments.createPeriod')),
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

        // Định nghĩa cột — khớp schema shipments. 47 cột tổng.
        // type: 'text' (default) | 'date' | 'vnd' | 'number'
        // group: 1|2|3|4  → quyết định màu header
        const COLS = [
            // === NHÓM 1 — THÔNG TIN LÔ HÀNG (14 cột) ===
            { key: 'id',             title: 'No.',         width: 50,  readonly: true, group: 1 },
            { key: 'client',         title: 'Client',      width: 140, group: 1 },
            { key: 'hbl',            title: 'HB/L',        width: 120, group: 1 },
            { key: 'mbl_no',         title: 'MB/L NO',     width: 130, group: 1 },
            { key: 'bkg_no',         title: 'BKG NO.',     width: 130, group: 1 },
            { key: 'pol',            title: 'POL',         width: 70,  group: 1 },
            { key: 'pod',            title: 'POD',         width: 70,  group: 1 },
            { key: 'vol',            title: 'VOL',         width: 55,  group: 1 },
            { key: 'container_type', title: 'TYPE',        width: 65,  group: 1 },
            { key: 'etd',            title: 'ETD',         width: 85,  type: 'date', group: 1 },
            { key: 'eta',            title: 'ETA',         width: 85,  type: 'date', group: 1 },
            { key: 'vessel_name',    title: 'VESSEL NAME', width: 140, group: 1 },
            { key: 'note',           title: 'NOTE',        width: 130, group: 1 },
            { key: 'line',           title: 'LINE',        width: 80,  group: 1 },

            // === NHÓM 2 — CHỨNG TỪ VẬN CHUYỂN (8 cột) ===
            { key: 'vgm',            title: 'VGM',           width: 70,  group: 2 },
            { key: 'si',             title: 'SI',            width: 70,  group: 2 },
            { key: 'bl_draft',       title: 'BL DRAFT',      width: 80,  group: 2 },
            { key: 'bl_confirm',     title: 'BL CONFIRM',    width: 90,  group: 2 },
            { key: 'obl',            title: 'OBL',           width: 70,  group: 2 },
            { key: 'tlx',            title: 'TLX',           width: 70,  group: 2 },
            { key: 'swb',            title: 'SWB',           width: 70,  group: 2 },
            { key: 'shipment_done',  title: 'SHIPMENT DONE', width: 100, group: 2 },

            // === NHÓM 3 — THANH TOÁN NCC & AGENT (16 cột) ===
            { key: 'purchase_note',         title: 'Note giá mua',          width: 130, group: 3 },
            { key: 'payment_amount',        title: 'Số tiền thanh toán',    width: 140, type: 'vnd',    group: 3 },
            { key: 'supplier',              title: 'NCC',                    width: 130, group: 3 },
            { key: 'supplier_due_date',          title: 'Hạn phải trả',                       width: 110, type: 'date', group: 3 },
            { key: 'report_close_date_increase', title: 'Ngày chốt báo cáo phát sinh tăng',  width: 140, type: 'date', group: 3 },
            { key: 'report_close_date_decrease', title: 'Ngày chốt báo cáo phát sinh giảm',  width: 140, type: 'date', group: 3 },
            { key: 'supplier_paid_date',         title: 'Ngày trả',                            width: 110, type: 'date', group: 3 },
            { key: 'cost_recognized',       title: 'Chi phí ghi nhận',       width: 140, type: 'vnd',    group: 3 },
            { key: 'trucking_cost',         title: 'Trucking nếu có',        width: 130, type: 'vnd',    group: 3 },
            { key: 'purchase_invoice_no',   title: 'Số hóa đơn đầu vào',    width: 130, group: 3 },
            { key: 'purchase_invoice_date', title: 'Ngày hóa đơn đầu vào', width: 130, type: 'date',   group: 3 },
            { key: 'driver_hoa',            title: 'Driver Hoa',             width: 110, group: 3 },
            { key: 'agent_fee',             title: 'Phí agent',              width: 110, type: 'number', group: 3 },
            { key: 'agent_name',            title: 'Tên Agent',              width: 130, group: 3 },
            { key: 'agent_fee_vnd',         title: 'Quy đổi Agent sang VNĐ', width: 150, type: 'vnd',    group: 3 },
            { key: 'agent_due_date',        title: 'Hạn phải trả (Agent)',  width: 130, type: 'date',   group: 3 },
            { key: 'agent_paid_date',       title: 'Ngày trả (Agent)',       width: 120, type: 'date',   group: 3 },

            // === NHÓM 4 — DOANH THU KHÁCH HÀNG (9 cột) ===
            { key: 'sale_note',           title: 'Note giá bán',         width: 130, group: 4 },
            { key: 'receivable_amount',   title: 'Phải thu khách',       width: 140, type: 'vnd',  group: 4 },
            { key: 'customer',            title: 'Khách hàng',           width: 130, group: 4 },
            { key: 'received_amount',     title: 'Tiền đã thu',          width: 140, type: 'vnd',  group: 4 },
            { key: 'receivable_due_date', title: 'Hạn phải thu',         width: 110, type: 'date', group: 4 },
            { key: 'received_date',       title: 'Ngày thu',             width: 110, type: 'date', group: 4 },
            { key: 'revenue_recognized',  title: 'Doanh thu ghi nhận',   width: 140, type: 'vnd',  group: 4 },
            { key: 'sale_invoice_no',     title: 'Số hóa đơn đầu ra',   width: 130, group: 4 },
            { key: 'sale_invoice_date',   title: 'Ngày hóa đơn đầu ra', width: 130, type: 'date', group: 4 },
        ];

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

        function buildCellData(rows) {
            const celldata = [];
            // Header row — màu theo group, text đen bold, wrap + center
            // Luckysheet alignment: ht (0=center, 1=left, 2=right) — vt (0=middle, 1=top, 2=bottom)
            COLS.forEach((c, ci) => {
                celldata.push({
                    r: 0, c: ci,
                    v: {
                        v: c.title, m: c.title,
                        bl: 1,                                       // bold
                        bg: GROUP_HEADER_BG[c.group] || '#e1e6f1',  // bg theo nhóm
                        fc: '#000000',                               // text đen
                        ht: 0, vt: 0,                                // 0=horizontal center, 0=vertical middle
                        tb: 2,                                       // wrap
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
                        v: raw == null ? '' : raw,                  // value gốc
                        m: displayed,                                // hiển thị
                        ct: ctFor(c.type),
                        tb: 2,
                        vt: 0,
                    };
                    // Căn phải cho số/tiền
                    if (c.type === 'vnd' || c.type === 'number') cell.ht = 2;
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

            // Có snapshot → dùng nguyên si (giữ format user đã đặt)
            const sheets = (snapshot && Array.isArray(snapshot) && snapshot.length >= 2)
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
                const importRows = readSheetRows(0).filter(r => r.client);
                const exportRows = readSheetRows(1).filter(r => r.client);
                if (importRows.length === 0 && exportRows.length === 0) {
                    return toast('Không có dòng hợp lệ để lưu (cần ít nhất Client).', 'warning');
                }

                const fullSheets = luckysheet.getAllSheets();

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

            document.getElementById('btnResetFormat').addEventListener('click', async () => {
                if (!await confirmAction({
                    danger: true,
                    title: 'Reset định dạng?',
                    text: 'Toàn bộ định dạng đã lưu (màu nền, font…) sẽ bị xoá và build lại từ DB.',
                    confirmText: '<i class="bi bi-arrow-counterclockwise me-1"></i> Reset',
                })) return;
                await fetch(ROUTES.resetSnapshot, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } });
                toast('Đã reset định dạng.');
                loadData();
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
