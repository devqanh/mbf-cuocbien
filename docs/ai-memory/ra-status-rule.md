---
name: ra-status-rule
description: "Quy tắc \"đã ra/chưa ra\" của lô = có Giờ xe ra (gio_xe_ra) HOẶC Biển số ra (bks_ra), KHÔNG chỉ dựa BKS"
metadata: 
  node_type: memory
  type: project
  originSessionId: abfdb9c6-78f0-4c41-acce-4ab00891767b
---

Trạng thái **"đã ra"** của lô hàng = **có `gio_xe_ra` (Giờ xe ra) HOẶC `bks_ra` (Biển số ra)**.

**Why:** xe thuê ngoài nhiều khi không cập nhật được biển số, nên chỉ cần có **Giờ xe ra** là coi như đã ra. Trước đây hệ thống chỉ dựa `bks_ra` → cont có giờ ra nhưng thiếu BKS bị tính nhầm "chưa ra".

**How to apply:** dùng quy tắc này nhất quán ở:
- Badge "Đã ra/Chưa ra" + cột danh sách lô (resources/js/trucking2/pages/lo-hang.jsx — table + card).
- Tab lọc & đếm `filterCounts` out/notout (HandlesShipments::pagedShipments — `$applyOut`/`$applyNotOut`).
- Ô "Chọn cont ra cùng chuyến" trong popup Thông tin lô (popups.jsx): chỉ liệt kê cont CHƯA RA = chưa có cả gio_xe_ra lẫn bks_ra.

Phân biệt 3 field dễ nhầm: `gio_xe_ra` (Giờ xe ra, tính free time) ≠ `cont_ra` (Ngày cont ra — ĐÃ BỎ khỏi UI) ≠ `bks_ra` (Biển số ra). siblingsList trả kèm gioXeRa+bksRa cho popup lọc. Liên quan [[trucking-report-schema]].

**Cập nhật:** đã **bỏ field "Ngày cont ra"** khỏi popup Lô hàng (gio_xe_ra là mốc cont rời đi). Cột "Ra:" trong list + badge hiện theo `gio_xe_ra`. **Bảng kê** (trang Tạo) lấy NGÀY KỲ = ngày của `gio_xe_ra` (fallback sail_date/cont_den) — sửa ở `HandlesStatementPricing::candidatesForStatement` + `statementReprice` (đã ghi chú trong code). Trang Tạo bảng kê: **chưa chọn kỳ (ngày ra) thì không tải lô**, hiện ghi chú "Vui lòng chọn ngày ra của lô hàng".
