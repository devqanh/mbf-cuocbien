@extends('layouts.app')

@section('title', 'Quản lý Items')

@push('styles')
    {{-- Luckysheet CSS --}}
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/plugins/css/pluginsCss.css' />
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/plugins/plugins.css' />
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/css/luckysheet.css' />
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/assets/iconfont/iconfont.css' />
@endpush

@section('content')
    <div class="page-header">
        <div>
            <h1>Quản lý Items</h1>
            <nav class="breadcrumb mt-1">
                <a href="{{ route('dashboard') }}">Trang chủ</a>
                <span class="mx-2">/</span>
                <span>Pages</span>
                <span class="mx-2">/</span>
                <span>Items (Luckysheet)</span>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" id="btnReload">
                <i class="bi bi-arrow-clockwise me-1"></i> Tải lại
            </button>
            <button class="btn btn-outline-secondary" id="btnResetFormat" title="Xoá định dạng đã lưu, build lại từ DB">
                <i class="bi bi-eraser me-1"></i> Reset định dạng
            </button>
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#itemModal" id="btnAdd">
                <i class="bi bi-plus-lg me-1"></i> Thêm dòng
            </button>
            <button class="btn btn-primary" id="btnSaveAll">
                <i class="bi bi-cloud-arrow-up me-1"></i> Lưu thay đổi
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <div>Bảng dữ liệu</div>
                <div class="small text-muted fw-normal">
                    Chỉnh sửa trực tiếp như Excel (bao gồm <strong>màu nền, font, border…</strong>), ấn <kbd>Lưu thay đổi</kbd> để đồng bộ cả dữ liệu lẫn định dạng lên server.
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-warning" id="btnEditSelected">
                    <i class="bi bi-pencil"></i> Sửa dòng đã chọn
                </button>
                <button class="btn btn-sm btn-outline-danger" id="btnDeleteSelected">
                    <i class="bi bi-trash"></i> Xoá dòng đã chọn
                </button>
            </div>
        </div>
        <div class="card-body">
            <div id="luckysheet"></div>
        </div>
    </div>

    {{-- Modal thêm / sửa --}}
    <div class="modal fade" id="itemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="itemForm">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-1"></i> <span id="modalTitle">Thêm sản phẩm</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="f_id">
                        <div class="mb-3">
                            <label class="form-label">Mã sản phẩm <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="f_code" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tên sản phẩm <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="f_name" class="form-control" required>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nhóm</label>
                                <input type="text" name="category" id="f_category" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Đơn vị</label>
                                <input type="text" name="unit" id="f_unit" class="form-control" placeholder="kg, chai, hộp...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Giá (₫)</label>
                                <input type="number" name="price" id="f_price" class="form-control" min="0" step="1000">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tồn kho</label>
                                <input type="number" name="stock" id="f_stock" class="form-control" min="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ghi chú</label>
                            <textarea name="note" id="f_note" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_active" id="f_is_active" class="form-check-input" checked value="1">
                            <label for="f_is_active" class="form-check-label">Đang kinh doanh</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Lưu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- Luckysheet JS --}}
    <script src="https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/plugins/js/plugin.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/luckysheet.umd.js"></script>

    <script>
        const ROUTES = {
            data:          @json(route('items.data')),
            store:         @json(route('items.store')),
            bulk:          @json(route('items.bulk')),
            resetSnapshot: @json(route('items.resetSnapshot')),
            update:        @json(url('items')),
            destroy:       @json(url('items')),
        };
        const CSRF = document.querySelector('meta[name="csrf-token"]').content;

        // Cấu hình cột (header) cho Luckysheet
        const COLS = [
            { key: 'id',        title: 'ID',         width: 60,  readonly: true },
            { key: 'code',      title: 'Mã',         width: 100 },
            { key: 'name',      title: 'Tên SP',     width: 220 },
            { key: 'category',  title: 'Nhóm',       width: 130 },
            { key: 'price',     title: 'Giá',        width: 110, format: 'number' },
            { key: 'stock',     title: 'Tồn',        width: 80,  format: 'number' },
            { key: 'unit',      title: 'ĐVT',        width: 80 },
            { key: 'note',      title: 'Ghi chú',    width: 200 },
            { key: 'is_active', title: 'Active',     width: 80,  format: 'bool' },
        ];

        function toast(msg, type = 'success') {
            const el = document.createElement('div');
            el.className = `toast align-items-center text-bg-${type} border-0 show`;
            el.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
            document.querySelector('.toast-container').appendChild(el);
            setTimeout(() => bootstrap.Toast.getOrCreateInstance(el).hide(), 3500);
        }

        function buildCellData(items) {
            const celldata = [];
            // Header row
            COLS.forEach((c, ci) => {
                celldata.push({
                    r: 0, c: ci,
                    v: { v: c.title, m: c.title, bl: 1, bg: '#0153a9', fc: '#ffffff', ht: 1, vt: 0, ff: 0 }
                });
            });
            // Data rows
            items.forEach((row, ri) => {
                COLS.forEach((c, ci) => {
                    let val = row[c.key];
                    if (c.format === 'bool') val = val ? 'Yes' : 'No';
                    if (val === null || val === undefined) val = '';
                    celldata.push({
                        r: ri + 1, c: ci,
                        v: { v: val, m: String(val), ct: { fa: 'General', t: c.format === 'number' ? 'n' : 'g' } }
                    });
                });
            });
            return celldata;
        }

        function readRowFromSheet(rIdx) {
            const sheet = luckysheet.getSheetData();
            const row = sheet[rIdx];
            if (!row) return null;
            const obj = {};
            COLS.forEach((c, ci) => {
                const cell = row[ci];
                let v = cell ? (cell.v ?? cell.m) : '';
                if (c.format === 'bool')   v = (String(v).toLowerCase() === 'yes' || v === true || v === 1 || v === '1');
                if (c.format === 'number') v = v === '' || v === null ? 0 : Number(v);
                obj[c.key] = v;
            });
            return obj;
        }

        let allItems = [];
        let snapshot = null;     // workbook đã lưu (giữ format)

        async function loadData() {
            const res  = await fetch(ROUTES.data, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            allItems   = json.data;
            snapshot   = json.snapshot;   // có thể null
            renderSheet();
        }

        function renderSheet() {
            const defaultSheet = {
                name: 'Items',
                color: '#0153a9',
                config: {
                    columnlen: COLS.reduce((acc, c, i) => (acc[i] = c.width, acc), {}),
                    rowlen: { 0: 32 },
                },
                celldata: buildCellData(allItems),
                row: Math.max(allItems.length + 50, 100),
                column: COLS.length + 2,
                frozen: { type: 'row', range: { row_focus: 0 } },
            };

            // Nếu có snapshot đã lưu → dùng nguyên si (giữ màu nền, font, border…)
            const sheets = (snapshot && Array.isArray(snapshot) && snapshot.length)
                ? snapshot
                : [defaultSheet];

            const options = {
                container: 'luckysheet',
                lang: 'en',
                showinfobar: false,
                showsheetbar: false,
                showstatisticBar: false,
                enableAddRow: true,
                enableAddBackTop: false,
                allowEdit: true,
                data: sheets,
            };
            luckysheet.destroy();
            luckysheet.create(options);
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadData();

            document.getElementById('btnReload').addEventListener('click', loadData);

            // Lưu toàn bộ thay đổi (bulk upsert theo "code") + snapshot (định dạng)
            document.getElementById('btnSaveAll').addEventListener('click', async () => {
                const data = luckysheet.getSheetData();
                const rows = [];
                for (let r = 1; r < data.length; r++) {
                    const obj = readRowFromSheet(r);
                    if (!obj || !obj.code || !obj.name) continue;
                    rows.push(obj);
                }
                if (!rows.length) return toast('Không có dòng hợp lệ để lưu (cần Mã và Tên).', 'warning');

                // Snapshot: lấy toàn bộ workbook (gồm style — bg, font, border…)
                const fullSheets = luckysheet.getAllSheets();

                const res = await fetch(ROUTES.bulk, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ rows, snapshot: fullSheets })
                });
                const json = await res.json();
                if (json.ok) {
                    toast(`Đã lưu ${json.saved} dòng (kèm định dạng).`);
                    // KHÔNG loadData lại — sheet đang hiện đã đúng cả data lẫn format
                } else {
                    toast('Lưu thất bại.', 'danger');
                }
            });

            // Reset định dạng đã lưu, build lại sheet từ DB
            document.getElementById('btnResetFormat').addEventListener('click', async () => {
                if (!confirm('Xoá toàn bộ định dạng đã lưu (màu nền, font…) và build lại từ DB?')) return;
                await fetch(ROUTES.resetSnapshot, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                });
                toast('Đã reset định dạng.');
                loadData();
            });

            // Sửa dòng đã chọn -> mở modal
            document.getElementById('btnEditSelected').addEventListener('click', () => {
                const range = luckysheet.getRange();
                if (!range || !range.length) return toast('Hãy chọn 1 dòng để sửa.', 'warning');
                const r = range[0].row[0];
                if (r === 0) return toast('Không thể sửa dòng tiêu đề.', 'warning');
                const obj = readRowFromSheet(r);
                if (!obj || !obj.id) return toast('Dòng này chưa có ID, hãy dùng "Lưu thay đổi" để tạo mới.', 'warning');
                openModal(obj);
            });

            // Xoá dòng đã chọn
            document.getElementById('btnDeleteSelected').addEventListener('click', async () => {
                const range = luckysheet.getRange();
                if (!range || !range.length) return toast('Hãy chọn dòng cần xoá.', 'warning');
                const rows = [];
                for (let r = range[0].row[0]; r <= range[0].row[1]; r++) {
                    if (r === 0) continue;
                    const obj = readRowFromSheet(r);
                    if (obj && obj.id) rows.push(obj);
                }
                if (!rows.length) return toast('Không có dòng nào có ID để xoá.', 'warning');
                if (!confirm(`Xoá ${rows.length} dòng?`)) return;

                for (const o of rows) {
                    await fetch(`${ROUTES.destroy}/${o.id}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                    });
                }
                // Cấu trúc dòng thay đổi → snapshot cũ không khớp, reset để build lại
                await fetch(ROUTES.resetSnapshot, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } });
                toast(`Đã xoá ${rows.length} dòng.`);
                loadData();
            });

            // Thêm mới qua nút (modal)
            document.getElementById('btnAdd').addEventListener('click', () => openModal(null));

            // Submit modal
            document.getElementById('itemForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const id = document.getElementById('f_id').value;
                const payload = {
                    code:      document.getElementById('f_code').value.trim(),
                    name:      document.getElementById('f_name').value.trim(),
                    category:  document.getElementById('f_category').value.trim() || null,
                    unit:      document.getElementById('f_unit').value.trim() || null,
                    price:     Number(document.getElementById('f_price').value || 0),
                    stock:     Number(document.getElementById('f_stock').value || 0),
                    note:      document.getElementById('f_note').value.trim() || null,
                    is_active: document.getElementById('f_is_active').checked,
                };

                const url    = id ? `${ROUTES.update}/${id}` : ROUTES.store;
                const method = id ? 'PUT' : 'POST';

                const res = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                if (res.ok && json.ok) {
                    // Thêm dòng mới → cấu trúc khác snapshot cũ, reset để build lại từ DB
                    if (!id) {
                        await fetch(ROUTES.resetSnapshot, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } });
                    }
                    toast(id ? 'Đã cập nhật.' : 'Đã thêm mới.');
                    bootstrap.Modal.getInstance(document.getElementById('itemModal')).hide();
                    loadData();
                } else {
                    const msg = json.message || (json.errors && Object.values(json.errors)[0][0]) || 'Lưu thất bại';
                    toast(msg, 'danger');
                }
            });
        });

        function openModal(obj) {
            document.getElementById('modalTitle').textContent = obj ? 'Sửa sản phẩm' : 'Thêm sản phẩm';
            document.getElementById('f_id').value        = obj?.id || '';
            document.getElementById('f_code').value      = obj?.code || '';
            document.getElementById('f_name').value      = obj?.name || '';
            document.getElementById('f_category').value  = obj?.category || '';
            document.getElementById('f_unit').value      = obj?.unit || '';
            document.getElementById('f_price').value     = obj?.price || 0;
            document.getElementById('f_stock').value     = obj?.stock || 0;
            document.getElementById('f_note').value      = obj?.note || '';
            document.getElementById('f_is_active').checked = obj ? !!obj.is_active : true;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('itemModal')).show();
        }
    </script>
@endpush
