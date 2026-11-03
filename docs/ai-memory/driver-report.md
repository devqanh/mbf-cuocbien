---
name: driver-report
description: "Báo cáo lái xe /bao-cao-lai-xe (menu Quản trị, quyền reports.view) — số chuyến theo lái trong khoảng ngày, dựng từ Lộ trình; lái = lái MẶC ĐỊNH của xe (Cài đặt › Biển số xe), xe chưa gắn lái → cảnh báo"
metadata:
  node_type: memory
  type: project
  originSessionId: 206b6c0b-bf5a-4c12-a2c6-47ceb0102a2b
  modified: 2026-11-03T03:13:30.385Z
---

Trang **Báo cáo lái xe** `/trucking-v2/bao-cao-lai-xe` (route `trucking2.driverReport` + `.data`, `ReportController::driverIndex/driverData`, quyền `reports.view`, menu Quản trị dưới Báo cáo tài sản). Build 2026-11-03.

- Backend `HandlesShipments::driverTripReport($from,$to)`: lặp từng ngày vận hành gọi `routeTripByDate` (KHÔNG gồm chuyến chưa ra), tối đa 93 ngày (~1,4s/tháng). Chỉ xe `mbfFleet()`; xe ngoài chỉ đếm `extTrips`.
- **Lái = `trucking_vehicles.driver_id`** (user chốt: "dựa theo cài đặt #vehicles"), KHÔNG dùng `route_pays.driver` (lái nhận ở Lộ trình) — chỉ hiện cảnh báo nhỏ "lái nhận: X" khi khác. Hệ quả: đổi lái của xe thì cả kỳ cũ tính cho lái mới.
- Xe MBF có chuyến nhưng chưa gắn lái → `noDriver` + banner vàng "Gắn lái xe" (link cai-dat#vehicles); xe chưa gắn lái không chạy → `idleNoDriver`. Lái đã gắn xe mà 0 chuyến vẫn liệt kê.
- Đếm chuyến theo mode: self (kéo cont ra) / other (kéo cont khác ra) / none (ra không kéo cont); tổng = mọi leg (khớp số "trips" của kỳ lương). Bung từng lái → sổ theo ngày + link `lo-trinh?date=&bks=`.
- FE 1 file `pages/bao-cao-lai-xe.jsx` (entry Vite mới), dùng `report-ui.jsx` (card/KPI).

Liên quan [[route-trips]], [[payroll-period-plan]], [[cost-report]].
