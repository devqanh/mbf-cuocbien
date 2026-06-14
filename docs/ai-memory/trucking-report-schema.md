---
name: trucking-report-schema
description: Cột/tham chiếu chốt sẵn cho báo cáo lô hàng + lệnh backfill
metadata:
  type: reference
---

Lô hàng đã có **số liệu + tham chiếu chốt sẵn** để query báo cáo bằng SQL thuần (không cộng dồn dòng con / parse JSON):

- `trucking_shipments`: `rev_base, vat_amount, choho_revenue, phai_thu, da_thu, con_no, cost_total, cost_billable, cost_company, profit` (profit = rev_base − cost_company) + khóa `vehicle_id, from_location_id, to_location_id`.
- `trucking_cost_lines`: `cost_item_id`, `payer_id` (giữ cả chuỗi `item`/`payer`). `trucking_revenue_lines`: `item_id` (revItems nếu kind=doanhThu, choHoItems nếu choHo).
- `trucking_shipment_warehouses`: mỗi kho của lô 1 dòng (warehouse_id + name) → báo cáo theo kho/tuyến.
- Phí xe: `trucking_trip_cost_lines` đã có `salary_total, cost_total, fuel_amount, driver_id, vehicle_id` (xem [[phi-xe-batch-model]]).
- Quản lý xe: `vehicle_usages.driver_id` (gán theo tên); `vehicle_depreciations.monthly_amount`/`daily_amount` (chốt = orig_price/months) → khấu hao theo tháng = SUM; `vehicle_costs.cost_type_id` link danh mục mới `trucking_vehicle_cost_types` (chọn ở Quản lý xe tab Chi phí). Maintain trong `saveVehicleManagement`; backfill chạy ở migration.

**Duy trì:** `TruckingV2Service::recomputeShipmentDerived($s)` gọi tự động trong `saveShipment` (mọi lần lưu, kể cả partial). Backfill lô cũ: `php artisan trucking:recompute-derived`.

**Why:** user muốn sau này dựng báo cáo (doanh thu/công nợ/lợi nhuận/chi phí theo khoản/xe/kho) query cho dễ. Cột chuỗi cũ giữ làm lịch sử; id là khóa join thêm. **How to apply:** dựng trang Báo cáo thì GROUP BY các cột/id này, đừng cộng lại từ dòng con.
