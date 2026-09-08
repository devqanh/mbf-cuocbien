---
name: office-cost-center
description: Chi phí văn phòng (G&A) = trung tâm chi phí kind='office' trên trucking_vehicles, dùng chung máy phiếu chi; danh mục officeCostTypes riêng; báo cáo chi phí có nhóm 'office', báo cáo tài sản loại trừ
metadata:
  type: project
---

**Chi phí văn phòng** (2026-09-08) = chi phí quản lý doanh nghiệp không gắn xe/tài sản (thuê VP, điện nước, internet, VPP, lương khối VP, phí ngân hàng…). Mô hình: **1 dòng `trucking_vehicles` kind='office'** (`plate='VAN-PHONG'`, `type='office'`, `info.name='Văn phòng'`), tự tạo bằng `officeEntity()` (firstOrCreate) khi mở tab lần đầu — cùng cách ghép như Tài sản (`kind='asset'`), để tái dùng toàn bộ máy phiếu chi: duyệt → thanh toán, ảnh hóa đơn, khoản định kỳ (gia hạn), phân bổ trả trước theo tháng, tài liệu.

**Why:** kế toán cần P&L đủ 5 nhóm (lái xe · xe · tài sản · **văn phòng** · lô hàng); overhead trước đây không có chỗ nhập nên P&L thiếu chi phí quản lý. Dùng chung bảng thay vì bảng mới để không nhân đôi luồng duyệt/thanh toán/Quản lý chi phí/báo cáo.

**How to apply:**
- Tab "Chi phí văn phòng" ở `/quan-ly-xe` (`pages/quan-ly-xe.jsx` mode `office`, component `components/quan-ly-xe/office.jsx`, hash `#office`) → vào thẳng `CostTab` + tab Tài liệu; endpoint `office.data` (`FleetController::officeData` → `HandlesVehicleDetail::officeData`). Lưu/ảnh/hủy/tài liệu đi qua đúng các route `{vehicle}` sẵn có với hashid văn phòng.
- Danh mục riêng **`officeCostTypes`** (`trucking_office_cost_types`, model `TruckingOfficeCostType`, seed 12 loại theo phân loại kế toán) — khai ở `lookups()`, `groups.js`, `cai-dat.jsx`. `costTypesForVehicle`/`saveVehicleManagement`/`updateVehicleCost` phân giải `cost_type_id` theo `kind` bằng `match` (vehicle/asset/office).
- Mỗi chỗ phân loại theo `kind` PHẢI xét đủ 3 giá trị: Quản lý chi phí lọc `kind=office` (và `vehicle` giờ là `kind='vehicle'` chặt, không còn `!= asset`), `costMgmtRow`/`spendRequestHistory` trả `kind`+`targetName`, `monthlyCostReport` có `COST_GROUPS['office']` (không vào bảng "theo xe"), `assetReport` **loại trừ** office (`whereIn kind [vehicle, asset]`). FE `OverviewTab.GROUP_COLORS.office`, `CostManagementApp.KIND`.
- Office KHÔNG lọt vào: danh mục Biển số xe, `assetList`, Yêu cầu chi public, cảnh báo hết hạn ở trang xe (`type='MBF'`). Muốn thêm bộ phận/trung tâm chi phí khác → thêm dòng kind='office' (UI hiện tại singleton).
- **Báo cáo**: CẢ `/bao-cao` (1 tháng) LẪN `/bao-cao-tai-san` (khoảng kỳ from→to) đều có tab "Văn phòng" dùng chung `OfficeTab.jsx`; backend `officeReportRange(fromIdx,toIdx)` (kỳ trước = khoảng liền trước cùng độ dài, alloc tính tiến độ đến cuối kỳ, trend 12 tháng kết thúc ở `to`), `officeReport(y,m)` chỉ là from=to; `assetReport()` trả `office` riêng, KHÔNG cộng vào `split`/`rows` xe-tài sản. `/bao-cao` có tab "Văn phòng" (`components/bao-cao/OfficeTab.jsx`, dữ liệu `officeReport()` trong trait `HandlesOfficeReport`, trả về trong `monthlyCostReport()['office']`). Hai cách nhìn ghi rõ: **spent** = theo ngày chi, KHỚP nhóm office của P&L; **accrual** = phiếu thường + phần phân bổ trả trước rải theo tháng. Kèm tỷ lệ overhead (spent/tổng chi phí, /doanh thu), cơ cấu theo loại, top NCC, xu hướng 12 tháng, khoản trả trước đang phân bổ, định kỳ sắp/quá hạn (45 ngày, quá hạn lên đầu), bảng phiếu. Test phải so CHÊNH LỆCH vì DB có phiếu văn phòng thật.
- Liên quan [[asset-management]], [[data-safety-reconcile]], [[cost-management-page]], [[cost-report]].
