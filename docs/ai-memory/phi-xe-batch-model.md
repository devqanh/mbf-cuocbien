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

**Tuyến hiển thị = TUYẾN KHO, KHÔNG phải nơi lấy/nơi hạ:** phí xe khớp phí tuyến theo cột `kho` (`routeKey($s->kho)`), nên hiển thị tuyến cũng là tuyến kho. `khoRouteDisplay($kho)` (HandlesTripAndDrivers) tách các điểm kho, mỗi điểm "tên (ký hiệu)" nếu danh mục Kho có name≠code (hiện name==code nên ra chính ký hiệu), nối " → ". Trả qua field `khoRoute` ở computeTripCosts + tripBatchToArray; trip-cost.jsx dòng tiêu đề hiện `x.khoRoute` (bỏ from→to). `route` (from→to) vẫn lưu nhưng không hiển thị ở phí xe.

**Lương theo CRU + bỏ Phí khác (06/2026):** route fee (`trucking_route_fees`, cấu hình `cai-dat#routeFees`) có 2 mức lương: `luong` (lô **TÍCH CRU**) và `luong_no_cru` (lô **KHÔNG tích CRU**). `tripSuggest` chọn mức theo `$s->cru` → đổ vào 1 field `luong` của dòng (line chỉ giữ 1 cột `luong` = lương đã áp). Lương giờ LUÔN cộng vào tổng (bỏ gate `($cru?luong:0)` cũ) — an toàn vì data cũ không có dòng cru=0&luong>0.

**Phí khác (`phi_khac`) ĐÃ BỎ** khỏi form cấu hình tuyến + ngừng gợi ý (sug phiKhac='0', applyRoute set phiKhac="0"). NHƯNG **GIỮ NGUYÊN cột `phi_khac` + công thức** ở `rowTotal`/`saveTripBatch`/`tripBatchToArray` vì 24/25 dòng + 9/10 route cũ có phí khác — bỏ khỏi tính toán sẽ sai tổng kỳ lịch sử (đã verify tổng kỳ cũ KHỚP với công thức mới). UI editor: field phiKhac đánh `legacy:true`, chỉ hiện khi `n(c.phiKhac)>0` (dòng cũ); field `luong` bỏ `onlyCru` (luôn hiện). ĐỪNG xóa cột phi_khac hay bỏ khỏi rowTotal.

**Why:** User yêu cầu trang phải ra lịch sử, nút Tính chỉ là tạo phí; chọn snapshot để khớp pattern Bảng kê đã quen.
**How to apply:** Khi sửa phí xe, giữ mô hình kỳ; double-count chấp nhận được nhưng phải cảnh báo qua `usedIn`. Liên quan [[trucking-redesign]], [[trucking-partial-save]].
