---
name: payroll-period-plan
description: "Kỳ lương lái xe (/phi-xe) — ĐÃ BUILD; gom theo BIỂN SỐ XE qua khoảng ngày; chưa-chi-theo-ngày=lương phải trả, chi-theo-ngày=đã trả"
metadata: 
  node_type: memory
  type: project
  originSessionId: b68a982e-c908-47ea-ac62-63eae4bc49de
---

**ĐÃ BUILD** (2026-06-18, commit `c87285e`). `/phi-xe` (route name `tripCost.*` giữ nguyên) nay = **Kỳ lương lái xe**, thay hẳn "Phí xe nội bộ theo lô" cũ (TripEditor/computeTripCosts/tripSuggest KHÔNG còn dùng ở đây).

**Mô hình:** phí tuyến = thu nhập lái. Khoản **TÍCH "chi theo ngày"** = đã trả trong ngày (popup Lộ trình [[route-pays-lo-trinh]]). Khoản **KHÔNG tích** = **lương gom trả 1 đợt** = tiền phải trả kỳ lương.

**Backend (HandlesShipments):**
- `legPayGroup` tính TẤT CẢ khoản, gắn cờ `perDay` (∈ salaryParts / extra.perDay) → tách 2 rổ: `items`+`sub` (chi theo ngày) và `payrollItems`+`payrollSub` (lương gom). `routeTripByDate` trả thêm `payrollTotal`/xe; manual extras (route_pays.extra_items) định tuyến theo perDay.
- `computePayroll($from,$to)`: loop từng ngày gọi routeTripByDate → gom theo **bks**: `payroll`(=Σ payrollTotal=lương phải trả) + `paidDaily`(=Σ payTotal, tham khảo) + days/trips; **lái auto** = route_pays.driver các ngày (comma-join). Trả rows + grandPayroll/grandPaidDaily + drivers.

**Snapshot (HandlesTripAndDrivers):** bảng `trucking_payroll_periods` (migration `2026_06_18_000007`, lines JSON theo bks, no=LG-####). Model `TruckingPayrollPeriod` (HasHashid). `payrollList()`/`savePayroll()`/`payrollToArray()`. Controller `TripCostController` chuyển sang payroll; route binding `{tripCost}` → TruckingPayrollPeriod; ĐÃ BỎ method/route `context` (Tính lại cũ).

**Frontend:** `phi-xe-tao.jsx` (bảng theo bks: lái auto sửa được via Combo, đã chi ngày tham khảo, lương phải trả), `phi-xe.jsx` (list kỳ lương), `phi-xe-xem.jsx` (snapshot, sửa lái+no/name+Lưu). Nav + title = "Lương lái xe".

**Quyết định (user):** gom theo BKS (không theo lái); lái tự gán theo thời điểm; lương đợt = MỌI khoản không tích chi theo ngày.

**Còn để ngỏ:** "Tính lại" cho kỳ đã lưu (hiện snapshot tĩnh); perf loop-từng-ngày (ok cho generate, có thể tối ưu query gom nếu kỳ dài). TripCostBatch tables cũ bỏ không dùng (chưa drop).

Liên quan [[route-pays-lo-trinh]], [[phi-xe-batch-model]], [[json-schema-evolution]].
