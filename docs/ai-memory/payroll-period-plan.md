---
name: payroll-period-plan
description: "KẾ HOẠCH (chưa build) kỳ lương lái xe — gom theo khoảng ngày + lái xe; \"chi theo ngày\"=đã trả, \"chưa chi theo ngày\"=lương gom đợt"
metadata: 
  node_type: memory
  type: project
  originSessionId: b68a982e-c908-47ea-ac62-63eae4bc49de
---

**Chưa build** (user chốt 2026-06-18 "chỉ sửa chi khác trước"). Đây là hướng đã nghiên cứu để sau làm cho khớp.

**Mô hình lương (user):** phí tuyến = thu nhập lái xe, chia 2:
- Khoản **TÍCH "chi theo ngày"** (salaryParts / extra perDay=true) = **đã thanh toán trong ngày** (vé trạm, dầu…). Đang xử lý ở popup Lộ trình ([[route-pays-lo-trinh]]).
- Khoản **KHÔNG tích "chi theo ngày"** (vd Lương) = **gom trả 1 đợt** theo kỳ lương.

**⚠️ Điểm hở hiện tại:** `legPayGroup` (HandlesShipments) CHỈ tính khoản perDay=true; khoản KHÔNG tích bị BỎ hẳn → lương không tích sẽ mất. Khi làm payroll phải GIỮ các khoản chưa-chi-theo-ngày làm "lương gom đợt" (tách rổ `payrollItems` song song `items`).

**Đề xuất:**
1. Backend tách 2 rổ mỗi chuyến: `items`(chi theo ngày) + `payrollItems`(chưa chi theo ngày = lương đợt) + `payrollTotal`/xe/ngày.
2. Trang kỳ lương ở /phi-xe: chọn from–to → **1 query gom toàn kỳ** (KHÔNG loop routeTripByDate 30×, tránh nặng) → gom THEO LÁI XE: lương phải trả=Σ payrollItems; đã chi theo ngày=Σ items (tham khảo).
3. Lưu kỳ snapshot giống [[phi-xe-batch-model]]: bảng `trucking_payroll_periods` + lines (từ–đến, theo lái, tổng, đã trả).

**Cần chốt trước khi build:**
1. 1 xe/ngày có thể 2 lái? (hiện route_pays lưu 1 lái/xe/ngày — nếu cần thì lưu lái theo chuyến).
2. "Lương gom đợt" = chỉ `Lương` (4 mức) hay MỌI khoản không tích chi theo ngày?
3. Kỳ lương gom theo LÁI XE (xác nhận, không theo xe).

Liên quan [[route-pays-lo-trinh]], [[phi-xe-batch-model]], [[json-schema-evolution]].
