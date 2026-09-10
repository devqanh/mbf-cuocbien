---
name: thanh-ly-to-khai
description: "\"Đã thanh lý tờ khai\" suy từ thanh_ly_date (không thêm cột); tích ngay trên danh sách /lo-hang + lọc Đã/Chưa + thao tác hàng loạt"
metadata:
  type: project
---

**Thanh lý tờ khai** (2026-09-10, khách yêu cầu qua chị Bé): cần tích nhanh xem cont đã thanh lý tờ khai chưa + lọc theo trạng thái đó.

**"Đã thanh lý" = lô CÓ `thanh_ly_date`** — không thêm cột mới, dùng lại đúng field sẵn có (trước đó 0/268 lô được điền vì bắt nhập ngày). Cùng kiểu suy diễn với "đã ra" = có `gio_xe_ra` ([[ra-status-rule]]): 1 mốc ngày vừa là cờ đã/chưa, vừa cho biết thanh lý hôm nào.

**Why:** khách cần thao tác nhanh hàng loạt, không muốn mở popup từng lô nhập ngày. Tích = ghi ngày HÔM NAY, vẫn sửa lại ngày cụ thể ở popup.

**How to apply:**
- Danh sách `/lo-hang`: cột **Thanh lý** (checkbox + ngày dd/mm) giữa cột Cont và Tuyến; mobile có nhãn "TL" ở góc thẻ. Tích lưu NGAY qua `POST shipments/bulk` với 1 id → không đụng field khác, không cần commitDirty. Optimistic + rollback khi lỗi (`toggleTl`, `TlBox` trong ShipmentsApp).
- Lọc: tham số `tl` = `all|done|pending` (pagedShipments), trả kèm `tlCounts` (đếm trên tập đã tìm, trước khi áp `tl` — cùng cách với `follow`); UI ở panel bộ lọc chi tiết, tính vào `activeFilters` + `clearFilters` + localStorage.
- Hàng loạt: modal "Thao tác hàng loạt" thêm select Đánh dấu / Bỏ đánh dấu. `bulkUpdateShipments` xét `thanhLy` bằng **`array_key_exists`** (không phải "khác rỗng") vì bỏ đánh dấu = gửi `null`; controller validate `ship.thanhLy` nullable.
- Popup lô: checkbox "Đã thanh lý tờ khai" cạnh ô Ngày thanh lý trong khối Hải quan.
- Đừng nhầm với **khoản chi phí** tên "Thanh lí" (tiền, từ [[csht-import]]) và `src=thanhLyFee` (phí tờ khai) — khác hẳn field ngày này.
