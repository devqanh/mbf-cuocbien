---
name: trucking-redesign
description: Kế hoạch thiết kế lại tính năng Trucking từ Luckysheet sang mô hình record+popup
metadata:
  type: project
---

Đang thiết kế lại tính năng **Trucking** (Laravel app `cuocbien`, prod tại mbf.dewa.vn/trucking) — chuyển từ Luckysheet (bảng tính ~70 cột phẳng/dòng, real-time, per-column permission) sang mô hình **record + popup** theo prototype `dev/trucking.html` (React, 2395 dòng, hiện chạy localStorage).

Luồng mới: danh sách "lô hàng" gọn + 3 popup (Thông tin lô · Chi phí · Doanh thu) + 3 màn hình (Lô hàng · Bảng giá · Bảng kê). Khác biệt cốt lõi: **chi phí theo per-lô, doanh thu/công nợ theo Bảng kê** (gom nhiều lô theo khách + kỳ ngày cont ra). Chi phí là danh sách khoản linh hoạt (khoản·số tiền·người chi·tích chi hộ·màu theo dõi), không phải cột cố định.

Quyết định đã chốt (2026-06-12):
- **Data model: chuẩn hóa quan hệ** (bảng riêng, không JSON blob).
- **Migration: khởi tạo mới, nhập lại** — dữ liệu trucking_entries cũ giữ read-only/lưu trữ.
- **Phạm vi: cả 2 sheet HPH (Hải Phòng) + ICD (Quế Võ).**

Backend hiện tại: TruckingController + TruckingService + SheetSnapshotService, config `config/trucking_columns.php` là single source of 70 cột, bảng `trucking_entries`. App dùng Bootstrap 5.3 + jQuery + Vite, KHÔNG có React build pipeline (prototype dùng React UMD + Babel CDN).

Hai thứ mô hình mới sẽ bỏ so với Luckysheet: per-column permission và real-time cell collaboration (thay bằng save per-lô).

**Frontend (chốt 2026-06-12):** giữ dạng HTML self-contained — React UMD + Babel CDN trong 1 Blade view như prototype, KHÔNG build qua Vite. Lý do user chọn: dễ maintain sau này (1 file, không build step).

Pragmatic normalization: thực thể giàu thuộc tính (customer, vehicle, price list, statement) có bảng + FK riêng; giá trị tag nhẹ (địa điểm, payer, tên khoản) giữ là string trên shipment/line, có bảng `trucking_catalogs` để gợi ý autocomplete + đơn giá mặc định (hợp với UX Combo create-on-fly). shipment.customer_id là FK; frontend vẫn gửi/nhận theo tên khách.

## Tiến độ (cập nhật 2026-06-12) — Giai đoạn 1–4 XONG

Route mới: prefix `/trucking-v2`, tên `trucking2.*`, dùng lại middleware `permission:shipments.{view,update,delete}`. Nav: layout app.blade.php có link "Trucking v2" (icon truck-front-fill) cạnh "Trucking" cũ. Chạy SONG SONG với trang luckysheet cũ.

Files đã tạo:
- 3 migrations `2026_06_12_0000{01,02,03}_*` (đã migrate): 12 bảng `trucking_*` (master_data, shipment+children, statement+children).
- 12 models `app/Models/Trucking*.php` (Customer, Vehicle, Catalog, Setting, PriceRow, Shipment, CostLine, RevenueLine, Payment, Statement, StatementLine, StatementPayment).
- `app/Services/TruckingV2Service.php` — serialize DB⇄shape prototype + persist lồng nhau (delete+recreate dòng con). Tiền=chuỗi chữ số VND.
- `app/Http/Controllers/TruckingV2Controller.php` — index(view), bootstrap, CRUD shipment/config/statement.
- `resources/views/trucking2/index.blade.php` — sinh từ `dev/trucking.html` (generator PHP one-off), bọc CSS+JS trong `@verbatim` (JSX `style={{}}` đụng Blade), inject `window.__TRK` (csrf/routes/boot) ngoài verbatim. Đã thay localStorage→API: `patch`→PUT debounce 600ms/lô, `addRow`→POST, `setCfgKey/addCfg`→PUT /config debounce 700ms, bảng kê→POST/PUT(debounce)/DELETE. Đã xóa dead-code SEED/migrate/load.

Đã verify server-side (tinker): migrations chạy, service roundtrip, controller endpoints (store/update/saveConfig/destroy ok), view render đủ marker (verbatim giữ `{{}}`, blade vars compile). CHƯA test browser thật vì host local là `trucking.dewa.vn` (không poke production); `cuocbien.test` chỉ là dir-index. DB đã wipe sạch.

## Tái cấu trúc (2026-06-12, sau Giai đoạn 4) — XONG

User yêu cầu: (a) mỗi danh mục Cài đặt là 1 BẢNG DB RIÊNG (vì sau xử lý quan hệ link phức tạp); (b) tách Lô hàng/Bảng giá/Bảng kê/Cài đặt thành ROUTE+BLADE RIÊNG. Quyết định: nav = link top-level riêng; Bảng kê trang riêng; Cài đặt 1 trang có sidebar danh mục.

DB: bỏ `trucking_catalogs` (gộp); migration `..._000004_split_trucking_catalogs_into_tables.php` tạo 9 bảng riêng: trucking_locations(+code), trucking_payers, trucking_drivers, trucking_cont_types, trucking_warehouses (5 bảng name+sort), trucking_cost_items, trucking_choho_items, trucking_revenue_items, trucking_veh_items (4 bảng name+default_price+sort). 9 models tương ứng; xóa model TruckingCatalog. Service dùng `lookups()` map cfgKey=>[modelClass,priced,coded]; cfg.prices build từ default_price các bảng priced.

Frontend (thay 1 SPA `index.blade.php` — đã xóa):
- `trucking2/partials/_runtime.blade.php` — React CDN + lib(window.__lib) + popups(window.__pop, đã tách ConfigBody khỏi ConfigPopup) + components(window.__ui: SortBtn,CellBtn,Badge,EditCell,TH,TD,StatementModal,SavedStatementModal,BangGiaPage,KePage). KHÔNG có App/mount/seed. + script fit chiều cao #trk-root.
- `trucking2/partials/_styles.blade.php` — fonts + CSS prototype (verbatim) + override layout.
- 4 page blade: `lo-hang` (ShipmentsApp, sheet switcher HPH/ICD), `bang-gia` (PricesApp→BangGiaPage), `bang-ke` (StatementsApp→KePage+modals), `cai-dat` (SettingsApp→ConfigBody). Mỗi page @include 2 partial + script app riêng + mount #trk-root.
- Routes: `/trucking-v2` redirect→shipments; `/lo-hang`(trucking2.shipments), `/bang-gia`(prices), `/bang-ke`(statements), `/cai-dat`(settings). API CRUD giữ nguyên. Controller: shipments()/prices()/statements()/settings() + pageData().
- Nav: 4 link top-level (Lô hàng/Bảng giá/Bảng kê/Cài đặt trucking) trong layout app.blade.php.

Đã verify server-side: migrate OK, config roundtrip + reconcile 9 bảng, full bootstrap+saveShipment, render cả 4 trang đủ marker. DB wiped sạch. CHƯA test browser thật (host local trucking.dewa.vn — không poke).

## Gỡ trang Luckysheet cũ (2026-06-12) — XONG

User: "xóa /trucking cũ, dùng v2". Đã làm:
- Route `/trucking` giờ là redirect closure → `trucking2.shipments` (GIỮ tên `trucking.index` vì rất nhiều view dùng làm nút "Trang chủ" + brand-logo + LoginController redirect). Gỡ route luckysheet API: `/trucking/data`, `/me/trucking-column-prefs`, `/trucking/bulk`, `/trucking/reset-snapshot`.
- Root `/` redirect → `trucking2.shipments`.
- Gỡ nav link "Trucking" (icon truck-front) cũ; nav giờ chỉ còn 4 link v2.
- `TruckingController` rút còn 3 method docs (`docs`/`saveNotes`/`docsDownload`) phục vụ `/tailieu`; constructor chỉ còn TruckingService. Xóa view `resources/views/trucking/index.blade.php`.

GIỮ LẠI (chưa xóa, vì /tailieu còn dùng & tránh mất dữ liệu): `/tailieu` docs, `TruckingService`, `TruckingEntry` model, bảng `trucking_entries`, `config/trucking_columns.php`, route `users/{user}/trucking-column-permissions` (obsolete nhưng vô hại). Nếu muốn purge hẳn cần xác nhận (drop bảng = mất data).

## Import bảng giá từ Excel (2026-06-12) — XONG

File mẫu `dev/banggia.xlsx`, sheet "Import Data", cột: Loại | Điểm Hạ | KIND | FROM | TO 1..4 | Tuyến | Distance (km) | Transport fee 40FT/20FT | Fuel fee 40FT/20FT | Total 40/20FT.

Schema (migration `..._000005_extend_trucking_price_rows_for_import`): price_rows thêm `location_id` (FK→trucking_locations), tách phí `trans_fee_40/20`, `fuel_fee_40/20`; BỎ `trans_fee`/`fuel_fee` đơn. (locations.name vẫn NOT NULL — resolveLocationId tạo location với name=code khi thiếu.)

Service TruckingV2Service:
- priceRowToArray/priceRowAttrs (shape frontend: loc, conn, kind, from, to1-4, distance, transFee40/20, fuelFee40/20, locationId).
- resolveLocationId($code): "Điểm Hạ" = KÝ HIỆU; khớp code→else name→else tạo mới (name=code,code=code); trả id. Dùng cho cả lưu tay & import.
- importPriceRows(customerName, rows): upsert, KHÓA định danh = (conn,loc,kind,from,to1..to4) [null-safe whereNull]; trùng→update distance+4 phí+location_id; chưa có→tạo. Trả {created,updated,imported,priceList}.

Controller `importPrices` + route POST `trucking-v2/price-import` (trucking2.priceImport, quyền shipments.update).

Frontend: bang-gia.blade thêm SheetJS CDN (xlsx@0.18.5) + route priceImport. PriceList (trong _runtime, window.__pop) viết lại: BỎ "Dán Excel", thêm nút **Import Excel** → đọc file (FileReader+XLSX.read) → panel chọn sheet → doImport parse header (norm tiếng Việt, map cột) → POST → onChange(priceList). Cột hiển thị: FROM/TO1-4/KM + 4 ô tiền (Cước 40FT, Cước 20FT, Dầu 40FT, Dầu 20FT). BangGiaPage truyền customer={cur}.

Verify: parse 528 dòng từ file thật → 432 tạo + 96 update (file có route trùng identity → last-wins), re-import idempotent (0 tạo/528 update), location auto-tạo theo code, location_id link OK.

## Bảng giá: lưu thủ công (2026-06-12)
Trang bang-gia BỎ auto-save: sửa tay (setCfgKey) chỉ cập nhật state + setDirty(true); có nút **"Lưu thay đổi"** (disabled khi !dirty) PUT /config; có cảnh báo beforeunload khi dirty. Import vẫn lưu ngay (server upsert) qua callback riêng `onImported`/`priceImported` (BangGiaPage) → cập nhật state KHÔNG đánh dấu dirty. CẬP NHẬT: trang Cài đặt (cai-dat) cũng bỏ auto-save — MỖI TAB có nút "Lưu mục này" RIÊNG. SettingsApp lift `sel` + dirty theo từng cat ({cat:true}) + saveCat() gửi PARTIAL cfg chỉ các key của tab (CAT_KEYS map); tab customers strip priceList khi lưu (tránh ghi đè bảng giá quản lý ở trang Bảng giá). ConfigBody nhận sel/setSel/dirty/saving/onSave/dirtyMap; nút Lưu + trạng thái nằm trong từng tab; sidebar có chấm cam cho tab chưa lưu; beforeunload khi anyDirty.

KHÓA địa điểm đang link: service config() trả `locationLocked` = tên các location có price_rows.location_id tham chiếu. ConfigBody: location trong locationLocked → input tên + ký hiệu readOnly (nền xám), nút xóa thay bằng 🔒. Bảo vệ data đang link (Điểm Hạ ở bảng giá link theo location).

LƯU Ý wipe DB: dưới `php artisan tinker --execute` trong Bash, chuỗi `'App\Models\'.$m` bị nuốt dấu `\` → class sai → wipe thất bại thầm lặng. Wipe đúng: dùng class FQN tường minh trong heredoc, hoặc script .php riêng (require vendor/autoload + bootstrap). DB đã wipe sạch hoàn toàn.

## Tạo bảng kê = trang riêng (2026-06-12)
"Tạo bảng kê mới" tách khỏi modal thành TRANG riêng (dễ maintain). `StatementModal` (runtime) đổi thành `StatementForm({hph,icd,cfg,onSaved,onCancel})` — bỏ wrapper Modal, render controls + bảng in + action bar (Hủy/In thử/Lưu); export window.__ui.StatementForm. Route `GET /trucking-v2/bang-ke/tao` (trucking2.statements.create) → controller createStatement() → blade `bang-ke-tao.blade.php` (CreateStatementApp). bang-ke: KePage onNew → window.location = ROUTES.create; bỏ StatementModal/showKe. Lưu: confirmAction → POST statements.store → redirect về /bang-ke. Xem bảng kê đã lưu vẫn là SavedStatementModal (modal) — chưa tách (user chưa yêu cầu).

window.confirmAction: dialog xác nhận dùng chung (vanilla JS trong _runtime, z-index 3000) — dùng cho xóa lô (danger) + lưu bảng kê. Xóa lô: InfoPopup nhận onDelete/canDelete; lo-hang delShip dùng confirmAction.

Còn lại: test browser; tùy chọn seed danh mục mặc định; gate UI theo canEdit (hiện chỉ chặn API); cân nhắc tách trang xem/sửa bảng kê đã lưu (hiện còn modal).
