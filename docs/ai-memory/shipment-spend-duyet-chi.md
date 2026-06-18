---
name: shipment-spend-duyet-chi
description: "ĐÃ BỎ \"Duyệt chi theo lô\" (trucking_shipment_spends dropped); chi cho lái xe nay quản lý ở Lộ trình qua trucking_route_pays"
metadata: 
  node_type: memory
  type: project
  originSessionId: b68a982e-c908-47ea-ac62-63eae4bc49de
---

**ĐÃ GỠ HẲN "Duyệt chi theo lô"** (2026-06-18, commit `50f0316`). Tính năng cũ (popup `spend`/`SpendPopup` ở Lô hàng + bảng `trucking_shipment_spends`) **không còn**. Chi cho lái xe nay quản lý ở **Lộ trình** (`/lo-trinh`) theo từng chuyến/ngày.

**Đã xóa:**
- `SpendPopup` (popups.jsx) + export ở pop.jsx/barrel; nút "Chi cho tài xế" ở Lô hàng (card + bảng). Lô hàng giờ chỉ còn nút "Chi cho lô hàng" (CostPopup).
- Backend: bỏ `spends` khỏi `shipmentToArray`/`saveShipment`/eager-load; gỡ `shipmentSpendSuggest` + route `trucking-v2/shipments/{shipment}/spend-suggest`.
- Bảng `trucking_shipment_spends` DROP (migration `2026_06_18_000003`); model `TruckingShipmentSpend` + relation `TruckingShipment::spends()` xóa.
- `spendsByShipment(ids)` (HandlesTripAndDrivers) giờ **trả rỗng** → cột "Đã chi" ở /phi-xe hiện 0. EMPTY_SPENT giữ làm default. **Phần tính lương lái xe sẽ làm lại sau** (user chốt: "shipment_spend cứ xóa đi sau này làm phần tính lương lái xe sau").

**Mô hình MỚI — chi cho lái xe ở Lộ trình:** xem [[route-pays-lo-trinh]].

**CẦN chạy trên VPS:** `php artisan migrate` (drop `trucking_shipment_spends` + tạo `trucking_route_pays`).

Liên quan [[route-pays-lo-trinh]], [[phi-xe-batch-model]], [[route-trips]], [[ra-status-rule]].
