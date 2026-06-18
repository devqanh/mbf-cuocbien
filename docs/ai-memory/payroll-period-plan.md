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

**Trang xem kỳ (phi-xe-xem) — ĐÃ thêm (commit `9c14983`):**
- **Lương phát sinh** (repeater `ExtraPayEditor`): mỗi xe thêm khoản {name,amount} → lương phải trả = gốc(payroll) + Σ extraPay (line.total). savePayroll lưu `extraPay`.
- **Các đợt thanh toán** (`PaymentsEditor`): {date,amount,note} trả chậm/chia đợt → cột Đã trả/Còn lại (= lương − Σ đợt). savePayroll lưu `payments` (paymentsIn).
- **Chốt lương** (locked + locked_at, migration `2026_06_18_000009`): đóng băng, khóa sửa lương + Tính lại (vẫn ghi đợt thanh toán được); badge "Đã chốt".
- **Tính lại** (`recomputePayroll` + route `tripCost.recompute`): tính lại theo khoảng ngày của kỳ, GIỮ lái/extraPay/payments, cập nhật lương gốc + detail. **Chốt + Tính lại ĐỀU confirmAction trước khi thao tác** (user yêu cầu "nhớ hỏi mới được thao tác").
- Component dùng chung `components/payroll-detail.jsx` (PayrollDetail/ExtraPayEditor/PaymentsEditor). Format ngày d/m/Y (fmtDate); Txt thêm prop `disabled`.

**Còn để ngỏ:** perf loop-từng-ngày (ok cho generate). TripCostBatch tables cũ bỏ không dùng (chưa drop).

Liên quan [[route-pays-lo-trinh]], [[phi-xe-batch-model]], [[json-schema-evolution]].
