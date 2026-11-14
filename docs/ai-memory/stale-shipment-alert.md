---
name: stale-shipment-alert
description: "Cảnh báo topbar \"quên nhập Giờ xe ra\" — lô có Giờ xe đến quá N ngày (mặc định 15) mà chưa có Giờ xe ra; bấm → /lo-hang?late=1 (tab Chưa ra)"
metadata:
  node_type: memory
  type: project
  originSessionId: e4e2c5db-8a59-4fc6-88ee-68cb3b2cba14
  modified: 2026-11-14T09:03:10.173Z
---

**Quy tắc user chốt (2026-11-14):** mốc = **Giờ xe đến** (gio_xe_den) + 15 ngày, chưa nhập **Giờ xe ra (cont này)** (gio_xe_ra) → cảnh báo. 15 ngày vì xe có thể nằm chờ tới 15 ngày. KHÔNG dùng ngày tạo lô / Giờ đến kế hoạch (mình từng đề xuất, user bác). Lô chưa có Giờ xe đến thì không thuộc cảnh báo này.

- Trait `HandlesStaleShipments`: `staleDays()` (setting `sys.stale_ra_days`, chỉnh ở /system-settings › Tính năng), `applyStale($b)`, `staleShipmentCount()`.
- Topbar `layouts/app.blade.php` (@can shipments.view): icon tam giác + badge, CHỈ hiện khi >0, link `route('trucking2.shipments', ['late'=>1])`. 1 query count/trang.
- /lo-hang: `?late=1` → bỏ bộ lọc đã lưu (như ?q), tab "Chưa ra" + chip đỏ "Quá hạn chưa nhập Giờ xe ra ✕"; rời tab Chưa ra / Xóa lọc thì bỏ late. Backend `pagedShipments` nhận `late`.
Liên quan [[ra-status-rule]] (đã ra = có gio_xe_ra).
