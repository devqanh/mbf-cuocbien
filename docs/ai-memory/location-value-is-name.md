---
name: location-value-is-name
description: "Combo Nơi lấy/hạ phải lưu TÊN địa điểm (duy nhất), KHÔNG lưu ký hiệu — nhiều tên chung 1 ký hiệu"
metadata: 
  node_type: memory
  type: project
  originSessionId: 7f0d2428-dc59-47c9-b3b0-a89c0b9bf3a0
  modified: 2026-09-30T04:20:59.980Z
---

Danh mục Địa điểm: **ký hiệu (code) KHÔNG duy nhất** — nhiều địa điểm chung 1 mã (vd HÀ HƯNG HẢI, TÂN VŨ, GIC, SAO Á… đều = HPP; còn có 1 địa điểm tên đúng "HPP"). Vì vậy `locOptions` (popups.jsx) phải dùng `value = TÊN` (duy nhất), label = "Tên — Ký hiệu". Nếu lưu value=code: chọn "HÀ HƯNG HẢI — HPP" sẽ lưu "HPP" rồi curLabel tra ngược ra option đầu trùng mã → nhảy về "HPP — HPP", và from_location_id gộp nhầm mọi depot HPP.

**Lưu tên là đúng & an toàn** vì backend luôn tự quy TÊN→KÝ HIỆU khi cần:
- Định giá: `$rc($s->from_loc)` (HandlesStatementPricing) name/code → code.
- from_location_id: `locationIdMap()` key cả name lẫn code → ra đúng location cụ thể.
- Hiển thị TUYẾN: dùng from_loc trực tiếp = tên; ký hiệu suy bằng `locCode()`. Xem [[trucking-report-schema]], [[coded-catalog-edit]].

**Popup "Thao tác hàng loạt" (/lo-hang, 2026-09-30)** cũng dùng chung `locOptions` / `bargeDropOptions` (export từ popups.jsx qua `@trk/pop.jsx`) — trước đó gom theo ký hiệu (`locCodeList`) nên chỉ hiện BG/HPP/HN…; bulk lưu qua cùng `saveShipment` nên tên hoạt động y như popup từng lô. Thêm ô chọn địa điểm mới → DÙNG LẠI 2 helper này, đừng dựng list theo mã.

Lưu ý dữ liệu: lô đã lưu trong lúc còn bug (from_loc = "HPP") hiển thị "HPP — HPP", phải chọn lại tên. Lô MBF/cũ vốn lưu tên thì đúng ngay.

**Kho (nhà máy) cũng lưu TÊN (2026-09-21):** ô Kho trong popup lô hàng trước đây gom option theo KÝ HIỆU (`whCodes`, dedupe) nên mất các kho con chung mã (1, 2, 3, 4, F9, A, B, C, D đều mã QV; WOLONG OLI/GIA DỤNG mã wolong…) — 43 kho chỉ hiện 30 mục. Nay `whNames` = mọi tên kho, lưu TÊN, hiển thị "Tên — Ký hiệu" qua prop mới `labelOf` của `MultiCombo` (lib.jsx; nhãn dùng cho chip + gợi ý + TÌM KIẾM nên gõ "QV" ra mọi kho con). Lý do cần kho con: phí tuyến lái xe (`routeKey($s->kho)`) khớp theo chuỗi kho nguyên văn, còn định giá khách/báo cáo thì backend tự quy tên→mã (`normalizedCodeMap`, `warehouseIdMap`, `khoResolve` đều nhận cả tên lẫn mã). `saveShipment` lưu kho nguyên văn, không chuẩn hóa. Lô cũ lưu mã (QV/TL/wolong/tiên sơn) vẫn hiện & tính như trước — không cần migration.
