---
name: driver-report
description: "Báo cáo lái xe /bao-cao-lai-xe (menu Quản trị, quyền reports.view) — số chuyến theo lái trong khoảng ngày, dựng từ Lộ trình; lái = lái MẶC ĐỊNH của xe (Cài đặt › Biển số xe), xe chưa gắn lái → cảnh báo"
metadata:
  node_type: memory
  type: project
  originSessionId: 206b6c0b-bf5a-4c12-a2c6-47ceb0102a2b
  modified: 2026-11-05T07:27:01.428Z
---

Trang **Báo cáo lái xe** `/trucking-v2/bao-cao-lai-xe` (route `trucking2.driverReport` + `.data`, `ReportController::driverIndex/driverData`, quyền `reports.view`, menu Quản trị dưới Báo cáo tài sản). Build 2026-11-03.

- Backend `HandlesShipments::driverTripReport($from,$to)`: lặp từng ngày vận hành gọi `routeTripByDate` (KHÔNG gồm chuyến chưa ra), tối đa 93 ngày (~1,4s/tháng). Chỉ xe `mbfFleet()`; xe ngoài chỉ đếm `extTrips`.
- **Lái THEO TỪNG NGÀY** (2026-11-03, user: lái thay giữa kỳ / đổi chéo xe): nguồn chính = Quản lý xe › tab **Thời gian sử dụng** (`trucking_vehicle_usages`: driver_id, from_date, to_date) — helper `usageOn()` (HandlesTripAndDrivers, dùng chung gợi ý lái Phí xe): dòng có Từ ngày MỚI NHẤT ≤ ngày chạy thắng, người kế tiếp nối tiếp từ ngày bắt đầu. Ô **Chạy đến** (= to_date) là DateField TÙY CHỌN: trống = tự tính (placeholder = ngày trước người kế tiếp / "Hiện tại"); nhập tay = lái nghỉ sớm (to < ngày → usageOn trả null → xe về lái mặc định / chưa gắn lái); nhập muộn hơn Từ ngày người kế tiếp thì người kế tiếp vẫn thắng (nhãn "lái sau nhận từ dd/mm"); to < from → nhãn đỏ. Không khớp → fallback `trucking_vehicles.driver_id` (Cài đặt › Biển số xe). Đổi chéo = mỗi xe thêm 1 dòng Từ ngày = ngày đổi. KHÔNG dùng `route_pays.driver` (lái nhận ở Lộ trình) — chỉ cảnh báo nhỏ "lái nhận: X" khi khác. Row gom theo `key` ('d'+id, hoặc 'n:'+tên nếu lái chưa có trong danh mục).
- Ngày xe chạy mà không ra lái → `noDriver` (chip link `quan-ly-xe#<hashid>/usage`); xe không chạy + cuối kỳ chưa có lái → `idleNoDriver`; lái có dòng sử dụng giao kỳ mà 0 chuyến vẫn liệt kê. `conflicts` = 1 lái bị tính ≥2 xe cùng ngày (quên gắn lái mới cho xe cũ) → banner đỏ.
- Đếm chuyến theo mode: self (kéo cont ra) / other (kéo cont khác ra) / none (ra không kéo cont); tổng = mọi leg (khớp số "trips" của kỳ lương). Bung từng lái → sổ theo ngày + link `lo-trinh?date=&bks=`.
- FE 1 file `pages/bao-cao-lai-xe.jsx` (entry Vite mới), dùng `report-ui.jsx` (card/KPI).

Liên quan [[route-trips]], [[payroll-period-plan]], [[cost-report]].
