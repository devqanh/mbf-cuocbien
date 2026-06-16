---
name: permissions-system
description: Cách phân quyền hoạt động (Spatie) + danh sách quyền + lưu ý khi thêm quyền mới
metadata:
  type: reference
---

Phân quyền dùng **Spatie**. `/roles` (RoleController@index) liệt kê **TẤT CẢ `Permission` trong DB**, gom theo prefix `<module>.<action>`; nhãn/nhóm tiếng Việt lấy từ **`config/permissions.php`** (`modules` + `permissions` + `roles`).

**QUAN TRỌNG — KHÔNG có `Gate::before` cho super_admin.** Super admin chỉ có quyền nhờ `syncPermissions(Permission::all())` trong `DatabaseSeeder`. → **Thêm quyền mới PHẢI gán tường minh**, nếu không cả super admin cũng bị chặn (403). Trên production: dùng **migration** `Permission::firstOrCreate` + `Role::givePermissionTo` (KHÔNG xóa quyền cũ) + `app(PermissionRegistrar)->forgetCachedPermissions()`. Đồng thời cập nhật `DatabaseSeeder` ($permissions + sync 4 role) cho cài mới, và `config/permissions.php` (module + nhãn) cho /roles.

**Module quyền (prefix):** dashboard, users, roles, shipments, prices, statements, settings, tracking, tripCost, fleet, system, spend, tasks.

**Map route → quyền (Trucking v2, prefix `trucking-v2` name `trucking2.`):**
- Lô hàng: `shipments.view/create/update/delete`. Bảng giá: `prices.view/update`. Bảng kê: `statements.view/create/update/delete`. Cài đặt danh mục/khách/giá dầu/tuyến: `settings.view/update`.
- **Phí xe & lương** (`/phi-xe`, trip-costs): `tripCost.view/create/update/delete` (đã TÁCH khỏi shipments.*).
- **Quản lý tài sản & đội xe** (`/quan-ly-xe`, fleet, assets): `fleet.view/manage` (đã TÁCH khỏi settings.*).
- **Theo dõi xe GPS**: `tracking.view` (bản đồ/danh sách/lịch sử kho) + `tracking.manage` (ghim tọa độ kho + cấu hình GPS). Tài khoản GPS ở /system-settings vẫn `system.settings`.
- Tasks: `tasks.view`, `tasks.create`, **`tasks.update`** (sửa/đổi trạng thái/comment), **`tasks.delete`** (đã tách khỏi tasks.create), `tasks.assign_others`, `tasks.manage_all`.
- Link kế hoạch (admin) vẫn dùng `shipments.update`; trang công khai lái xe dùng token (không quyền).

**Rà soát 2026-06-16 (đợt tách quyền):** tách tripCost.*/fleet.*/tasks.update+delete; bổ sung `system.settings` + `spend.request` vào seeder (trước chỉ tạo tay trên prod). Gán giữ nguyên quyền hiệu lực cũ (migration `2026_06_16_000002` cho tracking, `..._000003` cho tripCost/fleet/tasks): super/admin = đủ; editor = view + create/update (KHÔNG delete tripCost, KHÔNG fleet.manage); user = chỉ *.view (+ tasks.update/delete do trước có tasks.create). `canEdit/canDelete` ở trang React lấy từ `pageData(editPerm, deletePerm)` của controller.

**CÒN TỒN ĐỌNG (chưa đổi, user chấp nhận):** `POST /tailieu/notes` công khai (ghi chú trang tài liệu — cố ý mở cho kế toán không có tài khoản); `dashboard.view` seed nhưng chưa có route dùng. Liên quan [[gps-tracking]], [[trucking-architecture]].
