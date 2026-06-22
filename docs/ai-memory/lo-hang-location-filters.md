---
name: lo-hang-location-filters
description: Bộ lọc /lo-hang theo địa điểm — Nơi hạ (gồm) + Nơi lấy (Gồm/Loại trừ) đều theo KÝ HIỆU; lọc Ngày đóng hàng = gio_den_du_kien (1 ngày)
metadata: 
  node_type: memory
  type: project
  originSessionId: b68a982e-c908-47ea-ac62-63eae4bc49de
---

Bộ lọc trang Lô hàng (`pagedShipments` sheet `icd`, ShipmentsApp.jsx) cho kế toán:

- **Nơi hạ / Nơi lấy theo KÝ HIỆU** (không theo tên dài): options gom từ to_loc/from_loc thực qua `normalizedCodeMap` (norm(tên|mã)→mã). Lọc gửi ký hiệu → backend mở rộng ra các giá trị raw (`whereIn`). Output `toLocs`/`fromLocs` = list ký hiệu.
- **Nơi hạ** = GỒM (whereIn). **Nơi lấy** có 2 chế độ: `fromMode` include→whereIn; exclude→whereNotIn + orWhereNull (giữ lô KHÔNG có nơi lấy). Use case kế toán: "lọc nơi hạ HPP, LOẠI TRỪ nơi lấy nội bộ" (vd ICDQV). Params `toLoc[]`, `fromLoc[]`, `fromMode`.
- **Ngày đóng hàng** = **Giờ đến kế hoạch** (`gio_den_du_kien`, cột dateTime indexed) — CHỌN 1 NGÀY (param `denDate` → `whereDate`). KHÔNG phải cut_off (cắt máng).

Tất cả áp trong closure `$searched` nên cả ĐẾM (filterCounts/total) + danh sách đều đúng. ShipmentController::page truyền `toLoc,fromLoc,fromMode,denDate`.

Liên quan [[coded-catalog-edit]], [[ra-status-rule]], [[trucking-report-schema]].
