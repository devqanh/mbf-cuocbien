---
name: asset-management
description: "Quản lý tài sản dùng chung trang/bảng với Quản lý xe (cột kind), tái dùng tab chi phí/khấu hao"
metadata: 
  node_type: memory
  type: project
  originSessionId: b68a982e-c908-47ea-ac62-63eae4bc49de
---

Trang `/trucking-v2/quan-ly-xe` có toggle **Xe | Tài sản** (component `Root` + `ModeToggle`, nhớ lựa chọn ở localStorage `trk-fleet-mode`). Tài sản = bản ghi cùng bảng **`trucking_vehicles`** với cột **`kind`** ('vehicle' mặc định | 'asset'), `type='asset'` (≠ 'MBF' nên KHÔNG lọt vào phí xe nội bộ — phí xe vẫn lọc `type='MBF'`, không bị ảnh hưởng).

**Vì sao dùng chung bảng:** tái dùng nguyên cơ chế tab Chi phí (`TruckingVehicleCost`), Khấu hao (`TruckingVehicleDepreciation`), Tài liệu (attachments group='doc') + route `/quan-ly-xe/{vehicle}/data|section|save|docs|cost-photo` (asset là 1 vehicle-row nên binding chạy luôn). Tab Định mức + Thời gian sử dụng KHÔNG hiện cho tài sản (chỉ của xe, gắn tài xế/phí xe).

**Frontend** (`resources/js/trucking2/pages/quan-ly-xe.jsx`): `FleetApp` (xe, GIỮ NGUYÊN, chỉ thêm prop `modeSwitch`) + `AssetApp` (tài sản, tách riêng để không đụng code xe) + tái dùng leaf `CostTab/DeprecTab/DocsBlock`. Tab tài sản: Thông tin / Khấu hao / Chi phí / Tài liệu. `AssetInfoTab` lưu mọi field trong `info` json (name, category, serial, purchaseDate, origValue, supplier, location, manager, status, warrantyDue, inspectionDue, note); cảnh báo hết hạn theo warrantyDue+inspectionDue (như đăng kiểm/bảo hiểm xe). Mã tài sản = cột `plate` (unique, tự sinh `TS-XXXX` nếu trống). Loại tài sản = bảng `trucking_asset_categories` (Combo có sẵn + thêm nhanh tại chỗ). **Quản lý danh mục Loại tài sản ở Cài đặt** (tab "Loại tài sản") — đã nối vào hệ thống danh mục generic: thêm `'assetCategories' => [TruckingAssetCategory::class,false,false,false]` vào `lookups()`, thêm group vào `CFG_GROUPS` (config.jsx) + `CAT_KEYS`/`TAB_LABELS`/`DEFAULT_CFG` (cai-dat.jsx) → tự có load/save/đếm như các danh mục khác.

**Backend** (`TruckingV2Service`): `assetList/createAsset/destroyAsset/assetCategories/addAssetCategory`. `destroyAsset` chặn xóa nếu `kind!=='asset'` (bảo vệ xe). `assetList` đếm docCount từ attachments (1 query gộp). Controller `createAsset/addAssetCategory/destroyAsset/assetListData`; routes `trucking2.asset.create|category|destroy|list`. Migrations `2026_06_14_000005` (kind) + `_000006` (asset_categories).

**Loại chi phí TÀI SẢN (catalog RIÊNG, commit `1bc2a82`):** phiếu chi (`TruckingVehicleCost`) tham chiếu danh mục loại chi phí theo NGUỒN — xe→`TruckingVehicleCostType` ("Loại chi phí xe"); tài sản (`kind='asset'`)→`TruckingAssetCostType` ("Loại chi phí tài sản", catalog mới, migration `2026_06_25_000007` seed 6 loại). `cost_type_id` 1 cột nhưng phân giải theo `vehicle.kind`. `costTypesForVehicle($v)` chọn catalog; `saveVehicleManagement`+`updateVehicleCost` set `cost_type_id` theo kind; trang Quản lý chi phí (`/quan-ly-chi-phi`) truyền cả 2 catalog (`costTypes`+`assetCostTypes`), CostModal chọn theo `row.kind`. Khai báo `'assetCostTypes'` trong `lookups()` → tự có ở Cài đặt (groups.js+cai-dat.jsx). Báo cáo nhóm theo id: xem [[cost-report]].

**Lazy-load (performance):** fleet() boot CHỈ có dữ liệu xe (vehicles/expiringCosts/pendingCosts/costItems) — KHÔNG query tài sản. Danh sách tài sản lazy qua `GET trucking2.asset.list` (`assetListData`), fetch lần đầu khi `Root` chuyển sang chế độ Tài sản; `Root` giữ state assets/categories (không refetch khi toggle qua lại). Mở trang ở chế độ Xe = 0 query tài sản.

**Menu:** "Quản lý xe" đã đổi nhãn thành **"Quản lý tài sản"** (route vẫn `trucking2.fleet` = /quan-ly-xe).

**Gửi yêu cầu chi cho tài sản:** `/yeu-cau-chi` có toggle **Xe | Tài sản** (chỉ hiện khi có tài sản). `publicRequestData()` trả thêm `assets`; `createSpendRequest` nhận target là xe MBF HOẶC asset (`type='MBF' OR kind='asset'`), tài sản bỏ qua check định mức km (ẩn ô KM). Phiếu chi gắn vào tài sản (vehicle_cost.vehicle_id = asset id) → hiện ở tab Chi phí của tài sản đó.

**Combo dropdown (lib.jsx)** đã đổi sang **portal + position fixed** (tự lật lên khi thiếu chỗ) để KHÔNG bị cắt trong modal có overflow (vd modal Thêm tài sản).

Liên quan [[trucking-report-schema]] [[file-attachments]] [[phi-xe-batch-model]]. Sửa .jsx phải `npm run build`. Deploy: `git pull && php artisan migrate --force && npm run build`.
