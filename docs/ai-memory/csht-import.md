---
name: csht-import
description: Import CSHT — nạp phí CSHT + Số tiền thanh lý vào chi phí lô hàng theo số cont (nút ở /lo-hang)
metadata: 
  node_type: memory
  type: project
  originSessionId: 7f0d2428-dc59-47c9-b3b0-a89c0b9bf3a0
---

Nút **Import CSHT** ở toolbar /lo-hang: popup tải mẫu + chọn file + Kiểm tra (dry-run) → hợp lệ mới cho Import (giống luồng Import lô).

Cột file: Ngày HĐ | Số cont* | Nhập/Xuất | Phí CSHT | Số tiền thanh lý | Ghi chú | Số HĐ. Khớp lô theo **cont_no** (trong sheet icd). Ghi vào CHI PHÍ LÔ HÀNG:
- Phí CSHT → khoản tên **"CSHT"**; Số tiền thanh lý → khoản **"Thanh lí"** (đúng tên trong trucking_cost_items, xem [[cost-item-auto-vat]]).
- Ngày HĐ→date, Số HĐ→invoice_no, Ghi chú→note (áp cho cả 2 khoản).
- Nhập/Xuất **lệch = lỗi chặn import** (để trống thì bỏ qua đối chiếu). Cont không có / trùng nhiều lô = lỗi.
- Import lại **GHI ĐÈ** dòng CSHT/Thanh lí sẵn có (upsert theo cost_item_id/tên, không nhân đôi). Số tiền trống = bỏ qua khoản đó.

Kiến trúc (theo [[trucking-architecture]]): concern **HandlesCshtImport** (validateCshtImport/importCshtImport) trong TruckingV2Service; controller ShipmentController@cshtCheck/@cshtImport; route csht-import[/check] quyền shipments.update; frontend excel.js (parseCshtRows/buildCshtTemplateWb) + modal trong ShipmentsApp.jsx.

Kèm theo: **thêm cột "Ghi chú"** vào bảng chi phí lô hàng (CostLineRows shared.jsx) — field note đã có sẵn DB, trước đây không hiển thị; CostPopup nới width 1060 cho vừa. Không cần migration.
