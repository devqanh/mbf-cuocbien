---
name: cost-report
description: Báo cáo chi phí công ty theo tháng (/bao-cao) — P&L + cơ cấu chi phí (donut) + chi phí theo xe; biểu đồ không cần thư viện
metadata: 
  node_type: memory
  type: project
  originSessionId: b68a982e-c908-47ea-ac62-63eae4bc49de
---

**Báo cáo chi phí tháng** `/bao-cao` (ĐÃ build 2026-06-18, commit `4231689`). Trang `trucking2.report` (permission `tripCost.view`), nav "Báo cáo chi phí".

**Backend** `monthlyCostReport($year,$month)` (HandlesTripAndDrivers, **chỉ ĐỌC**) gộp 4 nguồn (thiết kế KHÔNG trùng nhau):
1. Doanh thu = `revenue_lines(doanhThu)` của lô có `gio_xe_ra` trong tháng.
2. Lương & vận hành lái xe = loop `routeTripByDate` từng ngày → gom items+payrollItems+manual theo loại (Dầu/Lương/Cầu đường+vé trạm/Trợ cấp/Phụ phí tuyến/Phát sinh). Tôn trọng số ĐÃ CHỐT (frozen).
3. Chi phí xe/tài sản = `vehicle_costs` theo `spend_date`. **NHÓM THEO THAM CHIẾU** `cost_type_id` phân giải theo ĐÚNG NGUỒN (xe→catalog `TruckingVehicleCostType`; tài sản `vehicle.kind='asset'`→catalog `TruckingAssetCostType` RIÊNG), fallback tên chuỗi. Nhãn "Chi phí xe · {loại}" / "Chi phí tài sản · {loại}" (commit `1bc2a82`). Xem [[asset-management]].
4. Chi phí lô = `cost_lines` billable=false (label "Chi phí lô · {item}").
Trả: revenue/totalCost/profit/margin, trips/conts/vehicles, costByCategory[{label,amount,pct}], costByVehicle[{bks,cost,trips,perTrip}]. `ReportController::index/data`, route `report`/`report.data`.

**Frontend** `pages/bao-cao.jsx`: KPI P&L (DT/CP/LN/biên), **Donut SVG tự vẽ** (stroke-dasharray, KHÔNG thư viện) + legend %, **bar CSS** chi phí theo xe + chi phí/chuyến, chọn tháng prev/next. Palette màu cố định.

**Báo cáo giám đốc (thêm, commit `b074038`):** `fleet[]` (DT/CP/LN/chuyến/cont/CP-DT% mỗi xe), `byRoute`/`byKho` (sản lượng đếm lô), `costTrend(year,month)` (route `report.trend`, LAZY — DT+cost_lines+vehicle_costs gom SQL, route-pay loop ngày). FE: bảng Hiệu suất đội xe, TopList tuyến/kho, TrendChart (cột DT vs CP + LN, tải bằng nút). Doanh thu theo khách: user CHƯA chọn làm.

**Lưu ý double-count:** cost_lines có thể trùng route-pay (vd cầu đường) nếu user nhập 2 nơi — hiện để 4 bucket riêng, chưa khử trùng tự động.

Liên quan [[payroll-period-plan]], [[route-pays-lo-trinh]], [[trucking-report-schema]].
