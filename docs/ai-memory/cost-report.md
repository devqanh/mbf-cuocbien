---
name: cost-report
description: "/bao-cao nay là Báo cáo DOANH THU PHẢI THU (công nợ phải thu khách từ bảng kê thu, mẫu như báo cáo xe ngoài); P&L chi phí cũ đã gỡ UI"
metadata:
  node_type: memory
  type: project
  originSessionId: e4e2c5db-8a59-4fc6-88ee-68cb3b2cba14
  modified: 2026-11-14T07:01:24.555Z
---

**2026-11-14 — /bao-cao làm lại thành "Báo cáo doanh thu phải thu"** (user: "không cần phức tạp nhiều tab… lấy dữ liệu từ bảng kê", mẫu = /bao-cao-xe-ngoai [[ext-truck-payable]]). Route/tên giữ nguyên `trucking2.report` + `report.data`, quyền `reports.view`, menu "Báo cáo doanh thu phải thu".

- Backend `receivableReport($y,$m)` (trait `HandlesReceivableReport`, chỉ đọc), cùng cách tính `extPayableReport`: bảng kê thuộc tháng của `period_to` (chưa có → ngày lập); số tiền = số ĐÃ CHỐT (`statementListAmounts`: base·vat·choho·extra[=phí riêng+phí tờ khai]); đã thu = payments theo ngày thu; dư đầu/cuối theo khách; `openDocs` = bảng kê còn nợ tới cuối tháng + hạn TT (`statementDueDate`) + số ngày quá hạn (mốc = cuối tháng, tháng hiện tại = hôm nay); `prev` so tháng trước.
- FE `pages/bao-cao.jsx` tự chứa: 4 KPI + 4 tab (Tổng hợp công nợ có sổ chi tiết mở rộng · Phải thu theo bảng kê có thanh Stacked cước/VAT/chi hộ/khác · Đã thu · Còn phải thu có chip hạn), xuất Excel SheetJS 5 sheet, In. **Thuật ngữ user chốt (áp cả bảng kê + báo cáo + Excel xem):** "**Doanh thu (cước+dầu)**" = nền cước+dầu+sà lan; "**Tổng phải thu**" / "Phải thu" = tổng bảng kê (doanh thu + VAT + chi hộ + phí khác). Báo cáo có cả cột Doanh thu lẫn Phải thu, KPI Doanh thu so tháng trước (`prev.base`). Mẫu Excel chính thức gửi khách (StatementExcelExporter) KHÔNG đổi.
- ĐÃ XÓA: route `report.trend`, `ReportController::trend`, components `bao-cao/{ReportApp,OverviewTab,FleetTab,CustomersTab,TrendTab}.jsx`. GIỮ `bao-cao/OfficeTab.jsx` (dùng ở [[asset-report]]). Service P&L cũ `monthlyCostReport`/`costTrend` (HandlesTripAndDrivers) còn nhưng KHÔNG còn nơi gọi — chi tiết thiết kế cũ xem git history.
