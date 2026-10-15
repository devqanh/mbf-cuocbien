---
name: fuel-log
description: Nhật ký đổ dầu /trucking-v2/nhat-ky-do-dau (menu Lô hàng) dùng chung bảng trucking_fuel_refills với tab Dầu ở Quản lý xe
metadata:
  node_type: memory
  type: project
  originSessionId: 3629f4f2-edea-4851-a2a7-3c96665ae6af
  modified: 2026-10-15T03:00:03.043Z
---

Trang **Nhật ký đổ dầu** (menu con Lô hàng, 2026-10-15) ghi xe MBF đổ dầu ngày nào, bao nhiêu lít. KHÔNG có bảng riêng: đọc/ghi `trucking_fuel_refills` qua `saveFuelRefill`/`deleteFuelRefill` (HandlesVehicleDetail) — ghi ở đâu cũng hiện ở tab Dầu của [[asset-management]] Quản lý xe.

- Backend: trait `HandlesFuelLog` (fuelLogBoot/fuelLogData) + `FuelLogController`; route `trucking2.fuelLog*`. Xem = shipments.view, ghi/xóa = shipments.update (khác tab Dầu dùng fleet.manage).
- Chỉ xe `mbfFleet()`; tìm biển số bỏ qua `-`, `.`, khoảng trắng.
- UI: ô ghi nhanh (giữ ngày + trạm, Enter để ghi, đơn giá gợi ý theo Bảng giá dầu) → **luôn có popup xác nhận** (user yêu cầu), cảnh báo trong popup nếu xe đã có phiếu cùng ngày; xem Theo ngày / Theo xe (+ danh sách xe chưa đổ trong kỳ); xuất CSV.
- Số lít: client đổi dấu phẩy → chấm trước khi gửi vì `inNum` xóa dấu phẩy (150,5 thành 1505).

**Why:** tránh 2 nguồn dữ liệu dầu lệch nhau. **How to apply:** mọi tính năng dầu (định mức, tiêu thụ) đọc trucking_fuel_refills.
