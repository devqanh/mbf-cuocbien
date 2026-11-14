---
name: stale-shipment-alert
description: "Cảnh báo topbar \"quên nhập Giờ xe ra\" — lô có Giờ xe đến quá N ngày (mặc định 15) mà chưa có Giờ xe ra; bấm → /lo-hang?late=1 (tab Chưa ra)"
metadata:
  node_type: memory
  type: project
  originSessionId: e4e2c5db-8a59-4fc6-88ee-68cb3b2cba14
  modified: 2026-11-14T09:06:08.205Z
---

**Quy tắc user chốt (2026-11-14):** mốc = **Giờ xe đến** (gio_xe_den) + 15 ngày, chưa nhập **Giờ xe ra (cont này)** (gio_xe_ra) → cảnh báo. 15 ngày vì xe có thể nằm chờ tới 15 ngày. KHÔNG dùng ngày tạo lô / Giờ đến kế hoạch (mình từng đề xuất, user bác). Lô chưa có Giờ xe đến thì không thuộc cảnh báo này.

- Trait `HandlesStaleShipments`: `staleDays()` (setting `sys.stale_ra_days`, chỉnh ở /system-settings › Tính năng), `applyStale($b)`, `staleShipmentCount()`.
- `layouts/app.blade.php`: THANH CẢNH BÁO vàng ngang trong `<header>` (dính trên cùng, user bác icon nhỏ "không rõ"), @auth — AI ĐĂNG NHẬP CŨNG THẤY; nút "Xem và cập nhật" chỉ khi @can shipments.view → `route('trucking2.shipments', ['late'=>1])`. Tự ẩn khi 0 lô. 1 query count/trang. #trk-root tự fit chiều cao theo top nên chèn thanh không vỡ bố cục.
- Local đang để `sys.stale_ra_days=3` để user xem thử (2026-11-14) — prod mặc định 15.
- /lo-hang: `?late=1` → bỏ bộ lọc đã lưu (như ?q), tab "Chưa ra" + chip đỏ "Quá hạn chưa nhập Giờ xe ra ✕"; rời tab Chưa ra / Xóa lọc thì bỏ late. Backend `pagedShipments` nhận `late`.
Liên quan [[ra-status-rule]] (đã ra = có gio_xe_ra).
