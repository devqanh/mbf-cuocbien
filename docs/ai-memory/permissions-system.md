---
name: permissions-system
description: Cách phân quyền hoạt động (Spatie) + danh sách quyền + lưu ý khi thêm quyền mới
metadata:
  node_type: memory
  type: reference
  originSessionId: 6eb3e0f6-7180-4f33-af1f-629eb483db2d
  modified: 2026-09-30T02:55:09.183Z
---

Phân quyền dùng **Spatie**. `/roles` (RoleController@index) liệt kê **TẤT CẢ `Permission` trong DB**, gom theo prefix `<module>.<action>`; nhãn/nhóm tiếng Việt lấy từ **`config/permissions.php`** (`modules` + `permissions` + `roles`).

**QUAN TRỌNG — KHÔNG có `Gate::before` cho super_admin.** Super admin chỉ có quyền nhờ `syncPermissions(Permission::all())` trong `DatabaseSeeder`. → **Thêm quyền mới PHẢI gán tường minh**, nếu không cả super admin cũng bị chặn (403). Trên production: dùng **migration** `Permission::firstOrCreate` + `Role::givePermissionTo` (KHÔNG xóa quyền cũ) + `app(PermissionRegistrar)->forgetCachedPermissions()`. Đồng thời cập nhật `DatabaseSeeder` ($permissions + sync role) cho cài mới, và `config/permissions.php` (module + nhãn) cho /roles.

**Bộ vai trò (rework 2026-06-17):** chỉ còn 4 — `super_admin` (toàn quyền, vai trò hệ thống), `admin` (NGANG super_admin = `Permission::all()`), `ke_toan` (tài chính: prices, statements, tripCost, spend.request, fleet + dashboard = 14 quyền), `chung_tu` (lô hàng + tracking + settings.view + tasks view/create/update + dashboard = 11 quyền). **Đã BỎ `editor` và `user`**.

**Thiết kế deploy-safe (`migrate --seed` chạy mỗi deploy):** tách 2 phần để gán quyền chỉ MỘT LẦN, sau đó admin tự bật/tắt ở /roles không bị ghi đè:
- Migration `2026_06_17_000001_rework_roles_acct_docs` = phần PHÁ HỦY 1 lần: tạo ke_toan/chung_tu (rỗng) làm đích, chuyển user editor/user → chung_tu, xóa editor/user.
- `DatabaseSeeder` = gán quyền: **super_admin LUÔN** `syncPermissions(Permission::all())` (an toàn, tự nhận quyền mới). admin/ke_toan/chung_tu gán mặc định **chỉ khi role đang RỖNG quyền** (`$role->permissions()->count()===0` → `$seedIfEmpty`); role đã có quyền thì GIỮ NGUYÊN (tôn trọng bật/tắt ở /roles). ⚠️ Đã BỎ cờ `sys.roles_initialized` (cờ toàn cục dễ kẹt: bật rồi mà role vẫn rỗng → không bao giờ gán lại — đã gây lỗi role rỗng trên VPS 2026-06-17). Cách seed-if-empty tự sửa được. Hệ quả: thêm permission mới về sau, admin/ke_toan/chung_tu KHÔNG tự nhận — phải bật tay ở /roles (chỉ super_admin auto).
- Dữ liệu DEMO trong seeder (3 user mẫu mật khẩu `password` + lô hàng mẫu) đã chặn `app()->environment('local','testing')` → KHÔNG tạo trên production. Riêng super_admin user (devqanh@gmail.com) vẫn updateOrCreate mọi lần (⚠️ reset mật khẩu về Quyenanh_2016 mỗi seed).

**Module quyền (prefix):** dashboard, users, roles, shipments (+ view_* theo cột), prices, statements, extStatements, settings, tracking, tripCost, driverPay, reports, fleet, system, spend, tasks.

**Map route → quyền (Trucking v2, prefix `trucking-v2` name `trucking2.`):**
- Lô hàng: `shipments.view/create/update/delete`. Bảng giá: `prices.view/update`. Bảng kê: `statements.view/create/update/delete`. Cài đặt danh mục/khách/giá dầu/tuyến: `settings.view/update`.
- **Phí xe & lương** (`/phi-xe`, trip-costs): `tripCost.view/create/update/delete` (đã TÁCH khỏi shipments.*).
- **Quản lý tài sản & đội xe** (`/quan-ly-xe`, fleet, assets): `fleet.view/manage` (đã TÁCH khỏi settings.*).
- **Theo dõi xe GPS**: `tracking.view` (bản đồ/danh sách/lịch sử kho) + `tracking.manage` (ghim tọa độ kho + cấu hình GPS). Tài khoản GPS ở /system-settings vẫn `system.settings`.
- Tasks: `tasks.view`, `tasks.create`, **`tasks.update`** (sửa/đổi trạng thái/comment), **`tasks.delete`** (đã tách khỏi tasks.create), `tasks.assign_others`, `tasks.manage_all`.
- Link kế hoạch (admin) vẫn dùng `shipments.update`; trang công khai lái xe dùng token (không quyền).

**Rà soát 2026-06-16 (đợt tách quyền):** tách tripCost.*/fleet.*/tasks.update+delete; bổ sung `system.settings` + `spend.request` vào seeder (trước chỉ tạo tay trên prod). Gán giữ nguyên quyền hiệu lực cũ (migration `2026_06_16_000002` cho tracking, `..._000003` cho tripCost/fleet/tasks): super/admin = đủ; editor = view + create/update (KHÔNG delete tripCost, KHÔNG fleet.manage); user = chỉ *.view (+ tasks.update/delete do trước có tasks.create). `canEdit/canDelete` ở trang React lấy từ `pageData(editPerm, deletePerm)` của controller.

**Rà soát QA 2026-09-30 (user duyệt):**
- **Chống leo thang quyền** — `App\Support\PermissionScope` gọi ở UserController (store/update/destroy/reset2FA) + RoleController (store/update/destroy). Người KHÔNG phải super_admin chỉ cấp/sửa trong phạm vi quyền của mình: không gán/sửa/hạ super_admin, không sửa user có quyền vượt mình, không sửa vai trò mình đang giữ, không cấp quyền mình không có. KHÔNG AI (kể cả super_admin) tự đổi vai trò của chính mình. /users chỉ liệt kê vai trò gán được (`assignableRoles`). Lỗi trả `back()->with('error')` (toast).
- **Quyền mới** (migration `2026_09_30_000001`): `driverPay.manage` = chi cho lái + chốt ngày ở /lo-trinh (trước là shipments.update; trang Lộ trình mở bằng `shipments.view|driverPay.manage`); `reports.view` = /bao-cao + /bao-cao-tai-san (trước là tripCost.view). Cấp mặc định cho vai trò đang có quyền cũ + ke_toan có driverPay.
- **Trang chủ theo quyền**: `User::homeUrl()` (thứ tự = menu); `/` và `/trucking` (trucking.index, đích sau login) redirect về đó → Kế toán không còn 403 sau đăng nhập. Thêm trang mới có quyền riêng → thêm vào map này.
- **File đính kèm**: settings.view HOẶC theo owner (TruckingVehicle → fleet.view, TruckingShipment → shipments.view) HOẶC costPhoto của chính phiếu spend.request.
- **2FA mobile**: đăng nhập /yeu-cau-chi của user ĐÃ bật 2FA → session `auth.spend_only` + không remember; middleware `RestrictSpendOnlySession` trên cả nhóm auth (trừ route trucking2.attachment) đăng xuất + đẩy về /login. User chưa bật 2FA không bị giới hạn.
- Lô hàng: blade có `canCreate` (shipments.create) → ẩn Thêm lô/Import lô; các nút import sửa + Link kế hoạch theo canEdit.
- Test: script probe HTTP theo vai trò trong transaction rollback (tạo user tạm + gọi kernel) — xem [[verify-no-destructive-save]].

**CÒN TỒN ĐỌNG (chưa đổi, user chấp nhận):** `POST /tailieu/notes` công khai (ghi chú trang tài liệu — cố ý mở cho kế toán không có tài khoản); `dashboard.view` seed nhưng chưa có route dùng. Liên quan [[gps-tracking]], [[trucking-architecture]].
