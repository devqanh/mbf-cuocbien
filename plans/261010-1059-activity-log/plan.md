# Nhật ký thao tác nhân viên (activity log)

**Status:** done (2026-10-10) · **Ngày:** 2026-10-10

## Outcome
Mọi thao tác ghi dữ liệu của nhân viên được lưu lại: ai, lúc nào, ở trang/chức năng nào, đổi gì (giá trị cũ → mới).
Người quản trị tra cứu trên web. **Claude Code** đọc nhanh qua lệnh artisan khi rà lỗi kiểu "vì sao xe 29C-18195 thành Xe ngoài".

## Quyết định đã chốt (user, 2026-10-10)
- Mức chi tiết: thao tác của mọi route ghi + diff từng trường cho model quan trọng.
- Giữ 12 tháng, tự xóa hằng đêm; số ngày chỉnh ở /system-settings.
- Trang riêng `/nhat-ky-thao-tac` (quyền `activity.view`) + tab **Nhật ký** ở /system-settings (bật/tắt, thời hạn).
- Không làm AI trong UI. "AI rà soát" = lệnh artisan cho Claude Code đọc.

## Non-goals
Không ghi thao tác chỉ đọc (GET), không hoàn tác từ nhật ký, không gọi API AI.

## Thiết kế
**Bảng `activity_logs`**: `id, request_id (uuid), user_id null, actor (tên / "lái xe qua link"), event (request|created|updated|deleted),
module (lo-hang, bang-ke, phi-xe, cai-dat…), route, method, subject_type, subject_id, subject_label ("Lô #1234 · cont ABCU123…", "Xe 29C-18195"),
changes JSON {field: [cũ, mới]}, summary TEXT (câu tiếng Việt), ip, user_agent, status, created_at`.
Index: `(subject_type, subject_id)`, `(user_id, created_at)`, `created_at`, FULLTEXT(`summary`, `subject_label`).

**Hai lớp ghi, gom chung theo `request_id`:**
1. Middleware `LogActivity` (nhóm web, có auth): mọi POST/PUT/PATCH/DELETE → 1 dòng `event=request` gồm route name, các key payload đã che
   (password, token, secret, 2fa, s3…), status code. Bao phủ cả ~116 route ngay, kể cả chỗ cập nhật hàng loạt bằng query builder (không có event model).
2. Trait `LogsActivity` trên model quan trọng: TruckingShipment, TruckingStatement(+Line), TruckingVehicle, TruckingVehicleCost, TruckingVehicleDepreciation,
   TruckingPriceBook/PriceRow, TruckingRoutePay, TruckingRouteFeeBook, TruckingPayrollPeriod, TruckingExtStatement, TruckingCustomer, TruckingDriver,
   TruckingLocation, TruckingWarehouse, TruckingSetting, User/Role. Event created/updated/deleted → diff `getDirty()` vs `getOriginal()`, bỏ `updated_at`,
   cắt JSON dài, nhãn trường tiếng Việt qua map `$activityLabels`.
- Ghi **đệm trong bộ nhớ** rồi insert 1 lần ở `terminate` và chỉ khi response < 400 → transaction rollback (vd chặn hạ xe MBF) không để lại log ảo,
  không thêm query vào giữa request (Debugbar gọn, xem [[trucking-perf-lazy-load]]).
- Lỗi khi ghi log không bao giờ làm hỏng thao tác chính (try/catch + Log::warning).
- Route công khai (link kế hoạch lái xe, /yeu-cau-chi) ghi actor = lái xe/token thay vì user_id.

**Cho Claude Code — `php artisan activity:log`**
`--user= --subject="29C-18195" --module= --from= --to= --grep= --limit=200 --json`
In mỗi dòng gọn: `2026-09-29 11:44:45 · Nguyễn A · cai-dat · Xe 29C-18195: Loại xe MBF → Ngoài; GPS dvbk:… → (trống)`.
Ghi cách dùng vào docs/ai-memory để phiên sau tự biết tra.

**UI**: trang `/nhat-ky-thao-tac` (React, Vite) — lọc nhân viên/ngày/chức năng/ô tìm, phân trang server, bấm dòng xem bảng cũ → mới, xuất Excel theo bộ lọc.
Tab "Nhật ký" ở /system-settings: bật/tắt (`sys.activity_enabled`), số ngày giữ (`sys.activity_retention_days`, mặc định 365), link sang trang tra cứu.

**Dọn dẹp**: `activity:prune` daily 03:00 trong `routes/console.php` (tránh trùng giờ db:backup 02:00), xóa theo lô 5.000 dòng.

## Phases
1. Migration bảng + permission `activity.view` (gán role admin) · service `ActivityLogger` (buffer/flush/mask) · middleware.
2. Trait `LogsActivity` + gắn vào model trên + nhãn trường tiếng Việt.
3. Lệnh `activity:log` + `activity:prune` + schedule + ghi docs/ai-memory.
4. Trang `/nhat-ky-thao-tac` + tab cấu hình /system-settings + `npm run build`.

## Acceptance
- Đổi loại xe ở Cài đặt → `activity:log --subject=29C-18195` hiện người đổi, giờ, MBF → Ngoài.
- Lưu bị server từ chối (422/rollback) → không có dòng diff nào.
- Mật khẩu/secret không bao giờ xuất hiện trong log.
- Số query thêm vào mỗi request ghi = 1 insert gộp; request GET = 0.
- Prune xóa đúng bản ghi cũ hơn thời hạn cấu hình.

## Rủi ro
- Cập nhật hàng loạt qua query builder (`->update()` trên builder, `DB::table`) không có diff, chỉ có dòng `request` → ghi rõ trong summary để không hiểu nhầm.
- reconcile danh mục sinh nhiều event một lần lưu → gộp theo request_id, giới hạn số dòng diff/request (vd 500) để không phình bảng.

## Đã thực hiện (khác bản nháp)
- Không dùng trait trên từng model: event gắn tập trung ở AppServiceProvider theo `config/activity.php` (không đụng 25 file model).
- Trang tra cứu làm bằng Blade (cùng kiểu /system-settings, /users), không cần build Vite.
- Thêm: chuẩn hóa biển số đặt lại gạch đúng chỗ (`15H308-58` → `15H-30858`) — nhật ký thử nghiệm cho thấy lưu Cài đặt gộp xe trùng xong lại tạo lại xe trùng.
