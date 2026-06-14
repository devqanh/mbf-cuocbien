---
name: phi-xe-batch-model
description: Phí xe nội bộ dùng mô hình kỳ/snapshot (như Bảng kê), không phải per-lô
metadata:
  type: project
---

Tính năng **Phí xe nội bộ** (`/trucking-v2/phi-xe`) theo mô hình **KỲ/ĐỢT snapshot**, giống Bảng kê — KHÔNG phải per-lô.

- Trang chủ `/phi-xe` = danh sách các kỳ (lịch sử). Nút "Tạo kỳ phí xe" → `/phi-xe/tao` (chọn khoảng *ngày xe ra* → Tính → sửa → Lưu). Click kỳ → `/phi-xe/{id}` xem/sửa.
- DB: `trucking_trip_cost_batches` (kỳ: no PX-0001…, period_from/to, total) + `trucking_trip_cost_lines` (snapshot mỗi lô: tuyến/BKS/cầu/lái xe + các khoản phí + extras JSON + line_total). Bảng cũ `trucking_trip_costs` (per-lô) đã bị drop.
- Service: `computeTripCosts(from,to)` (gom lô + gợi ý, kèm `usedIn` cảnh báo lô đã thuộc kỳ khác → tránh cộng trùng lương/dầu), `saveTripBatch`, `tripBatchToArray`, `tripBatchContext` (Tính lại lazy + confirm, báo lô đã xóa), `tripBatchesForList`. JSX dùng chung `components/trip-cost.jsx` (TripEditor).

**Why:** User yêu cầu trang phải ra lịch sử, nút Tính chỉ là tạo phí; chọn snapshot để khớp pattern Bảng kê đã quen.
**How to apply:** Khi sửa phí xe, giữ mô hình kỳ; double-count chấp nhận được nhưng phải cảnh báo qua `usedIn`. Liên quan [[trucking-redesign]], [[trucking-partial-save]].
