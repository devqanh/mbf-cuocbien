---
name: cost-management-page
description: Trang /quan-ly-chi-phi — tổng hợp MỌI phiếu chi xe+tài sản để duyệt/thanh toán/sửa/hủy tập trung
metadata: 
  node_type: memory
  type: project
  originSessionId: 796005c8-78dc-47ff-827f-1058cd1cdbe3
---

**Trang "Quản lý chi phí"** (`/trucking-v2/quan-ly-chi-phi`, route `trucking2.costManagement`, menu cạnh "Quản lý tài sản", quyền `fleet.view` xem / `fleet.manage` thao tác) — gom TẤT CẢ phiếu chi (`TruckingVehicleCost`) của xe MBF + tài sản vào 1 nơi, KHỎI phải vào từng xe (user 2026-06-22).

**Backend (HandlesFleetAssets):**
- `costManagementData($f)` — list phân trang server-side: filter `status` (action|pending|pay|paid|cancelled|all) + `kind` (all|vehicle|asset) + `q` (name/invoice/supplier/plate) + page/perPage(20). Trả `rows` (map qua `costMgmtRow`) + `total` + `counts` (badge mỗi tab, theo q/kind). action = chưa duyệt HOẶC chưa chi.
- `costMgmtRow($c)` — kèm vehicle/asset (kind, targetName), requester, status, estAmount, supplier, photos, vehicleHashid (deep-link).
- `updateVehicleCost($c,$in)` — cập nhật 1 PHIẾU đơn lẻ (duyệt/thanh toán/sửa); KHÔNG đụng est_amount/created_by/cancelled; paid chỉ true khi approved; trả `row` đã map. **Đây là endpoint per-phiếu — KHÁC saveVehicleManagement (delete+recreate cả xe).**

**Routes (FleetController):** GET `costManagement`(page) / `costManagement.list`(json) [fleet.view]; PUT `/quan-ly-xe/cost/{cost}` = `fleet.updateCost` [fleet.manage]; hủy tái dùng `fleet.cancelCost`. Binding `{cost}` theo HASHID ([[hashid-routes]]).

**Frontend** (`pages/quan-ly-chi-phi.jsx` → `components/cost-management/CostManagementApp.jsx`): tabs trạng thái + counts, lọc xe/tài sản, tìm kiếm (debounce), danh sách dạng CARD (responsive), phân trang. Hành động: Duyệt (quick), Duyệt&chi / Thanh toán (**tái dùng PayModal** — có ô số tiền thực tế [[spend-request-flow]]), Sửa (**tái dùng CostModal**), Hủy. Mỗi card có link "hồ sơ" deep-link `#<vehicleHashid>/cost` sang trang xe. PayModal/CostModal export sẵn từ `components/quan-ly-xe/parts.jsx`.

Deploy: `npm run build` (KHÔNG cần migrate). Liên quan [[spend-request-flow]] [[asset-management]].
