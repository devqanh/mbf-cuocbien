---
name: shipment-column-permissions
description: Quyền xem TỪNG CỘT bảng Lô hàng (shipments.view_*) — 2 cột tiền bị cắt ở server, 5 cột còn lại chỉ ẩn trên giao diện
metadata:
  type: project
---

Bảng Lô hàng (/trucking-v2/lo-hang) có quyền xem theo cột, cấu hình ở /roles. Nguồn sự thật:
`App\Support\ShipmentColumns` (MAP cột→permission + STRIP field). Cột **Khách hàng** và **Cont**
KHÔNG có quyền — ẩn đi thì bảng vô nghĩa.

**Hai mức hiệu lực, cố ý khác nhau — đừng "sửa cho đồng bộ":**
- `view_cost`, `view_revenue`: dữ liệu tiền tự chứa → `shipmentToArray()` cắt hẳn `cost` / `rev`
  + `cuocDau` + `priceMatched`; `pagedShipments()` bỏ luôn truy vấn tổng chi phí và bước định giá.
  Không có quyền = số tiền không rời khỏi server.
- `view_id`, `view_customs`, `view_route`, `view_plate`, `view_schedule`: các field này là dữ liệu
  vận hành của chính bảng (bộ lọc nơi lấy/nơi hạ, chip "đã ra", free-time, popup sửa lô) nên vẫn
  gửi; quyền chỉ ẩn cột cho gọn theo vai trò. Xem devtools vẫn thấy — nói rõ khi ai đó hỏi.

**Bảng kê không bị cắt**: `shipmentsByIds()` gọi `shipmentToArray($s, false)` vì module bảng kê có
quyền riêng (`statements.*`) và cần số chi hộ để tính. Thêm chỗ gọi mới thì cân nhắc cờ này.

Client đọc cờ qua `window.__TRK.cols` → helper `canCol(k)` ở `lib.jsx` (thiếu cờ = được xem).
Dùng ở ShipmentsApp (bảng desktop + card mobile + ô Tổng chi phí + popup chi phí) và popups.jsx
(nút "Thuê xe ngoài" theo `canCol("cost")` vì nó tạo 1 dòng chi phí).

Migration `2026_09_11_000001` cấp đủ 7 quyền cho mọi vai trò đang có `shipments.view` → deploy
không ai mất cột; admin tự gỡ ở /roles. Liên quan [[permissions-system]] [[trucking-architecture]].
