---
name: trucking-architecture
description: Trucking v2 — controller tách theo domain (App\Http\Controllers\Trucking); service đang tách trait
metadata:
  type: reference
---

**Controller ĐÃ TÁCH** (trước là 1 file TruckingV2Controller ~870 dòng — đã XÓA). Giờ nằm trong `app/Http/Controllers/Trucking/`, mỗi domain 1 file, đều extends `BaseTruckingController` (giữ `pageData()/user()`, inject `TruckingV2Service $svc`):
- `ShipmentController` (lô hàng: index/page/store/update/destroy/check/import/configData/bootstrap) — route alias `TruckingShipmentController` (tránh đụng `App\Http\Controllers\ShipmentController` của Follow-Up).
- `StatementController` (bảng kê: index/create/view/context/export/store/update/destroy). Excel tách ra `App\Services\Trucking\StatementExcelExporter`.
- `TripCostController`, `FleetController` (xe+tài sản), `CatalogController` (cài đặt/danh mục), `DriverController`, `PriceController`, `AttachmentController` (show), `SpendRequestController` (yêu cầu chi), `PlanLinkController` (admin + public lái xe).
- **Tên route GIỮ NGUYÊN** (`trucking2.*`) — chỉ đổi class+method trong `routes/web.php`. Frontend/blade dùng route name nên không phải sửa.

**Service ĐÃ TÁCH** (`TruckingV2Service` giờ chỉ **59 dòng** — class mỏng chỉ `use` các trait; call-site `$this->svc->x()` KHÔNG đổi gì). Các trait ở `app/Services/Trucking/Concerns/` (tổng ~3088 dòng, mỗi file 1 domain):
- `FormatsTruckingValues` (in/out tiền/số/ngày).
- `HandlesShipments` (lookups, bootstrap, shipments, pagedShipments, shipmentToArray, saveShipment, recomputeShipmentDerived…).
- `HandlesCatalog` (config, catalogCounts, catalogData).
- `HandlesFleetAssets` (mbfVehicles, expiring/pending costs, costItems, asset CRUD).
- `HandlesPlanLinks` (plan link admin + public).
- `HandlesSpendRequests` (yêu cầu chi + vehicleCostStatus + nextCostInvoiceNo).
- `HandlesVehicleDetail` (vehicleBase/section/saveVehicleManagement + companyInfo/sellerInfo + attachments/storage: storeAttachments/applyS3Config/disk… + cost photos + vehicle docs).
- `HandlesTripAndDrivers` (phí xe: routeKey/tripSuggest/computeTripCosts/saveTripBatch/fuelPrices/routeFees + hồ sơ lái xe).
- `HandlesPricingAndImport` (customerPriceList, shipmentBoardConfig, priceBookConfig, saveConfig/saveCatalog/reconcile*, validate/import shipments, importPriceRows).
- `HandlesStatements` (statements, statementToArray, saveStatement).
- `HandlesStatementPricing` (**SO KHỚP GIÁ Ở BACKEND** — nguồn chân lý): `priceShipment` (port từ makePricer/priceFor ui.jsx) + `freeTimeOf` (port calcFreeTime) + `statementCandidates(khách,từ,đến)` + `statementReprice(st)`. So khớp KIND **lowercase+trim** (không phân biệt hoa/thường). Bảng giá order `orderBy('sort')` (cả quan hệ priceRows lẫn customerPriceList) → khớp dòng đầu y client.

**ĐỊNH GIÁ BẢNG KÊ giờ Ở BACKEND** (trước ở client `makePricer`): Trang Tạo (`/bang-ke/tao`) KHÔNG còn nạp toàn bộ lô — chỉ boot `config(withPrices:false)` (khách + customerInfo, không priceList); StatementForm fetch `GET statement-candidates?customer&from&to` → lô của 1 khách trong khoảng cont-ra, đã định giá (matched/cước/dầu/phải thu). "Tính lại" ở bảng kê đã lưu gọi `GET bang-ke/{id}/reprice`. → scale tốt cho 20k–50k lô (không đẩy hết về client). `makePricer` trong ui.jsx còn export nhưng không còn dùng (dead, giữ lại). Excel export + saveStatement vẫn dùng snapshot đã lưu.

**Lưu ý khi maintain:** mọi method vẫn là 1 class (chung `$this`) nên gọi chéo trait tự nhiên. Sửa 1 domain = mở đúng 1 trait file. Khi THÊM method mới: đặt vào trait đúng domain (không nhồi lại vào TruckingV2Service). Lý do chọn trait (không tách Service DI): giữ nguyên call-site, rủi ro thấp, file nhỏ cho AI. Liên quan [[hashid-routes]] [[plan-link]].
