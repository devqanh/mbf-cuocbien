---
name: trucking-clear-scope
description: trucking:clear CHỈ xóa nghiệp vụ (lô/bảng kê/phí xe), GIỮ danh mục+cấu hình+bảng giá
metadata:
  type: feedback
---

`php artisan trucking:clear` (App\Console\Commands\TruckingClear) **CHỈ xóa dữ liệu NGHIỆP VỤ**: Lô hàng (shipment + cost/revenue lines + payments + shipment_warehouses + ảnh lô attachments owner=Shipment), Bảng kê (statement + lines + payments), Phí xe (trip_cost_batches + lines).

**TUYỆT ĐỐI GIỮ NGUYÊN** (đừng đưa vào $order): toàn bộ **danh mục/cấu hình** trang Cài đặt (TruckingLocation, TruckingCustomer, TruckingVehicle, TruckingPayer, TruckingDriver, TruckingContType, TruckingWarehouse, TruckingCostItem, TruckingChohoItem, TruckingRevenueItem, TruckingSalaryItem, TruckingVehicleCostType, TruckingAssetCategory, TruckingSetting) + **Bảng giá** (TruckingPriceRow).

**Why:** danh mục + bảng giá + cấu hình tốn công nhập, là dữ liệu nền — xóa khi reset test là sai. Bản CŨ của command từng xóa hết (gồm cả customers/pricerows/settings) → user mất dữ liệu, nên sửa lại chỉ-nghiệp-vụ.

**How to apply:** khi thêm bảng nghiệp vụ mới (vd domain mới có dữ liệu test), thêm vào $order theo thứ tự con→cha; KHÔNG thêm bảng danh mục/cấu hình/bảng giá. Liên quan [[no-seed-demo]] [[db-backup]].
