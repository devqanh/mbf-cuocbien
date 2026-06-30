---
name: location-value-is-name
description: "Combo Nơi lấy/hạ phải lưu TÊN địa điểm (duy nhất), KHÔNG lưu ký hiệu — nhiều tên chung 1 ký hiệu"
metadata: 
  node_type: memory
  type: project
  originSessionId: 7f0d2428-dc59-47c9-b3b0-a89c0b9bf3a0
---

Danh mục Địa điểm: **ký hiệu (code) KHÔNG duy nhất** — nhiều địa điểm chung 1 mã (vd HÀ HƯNG HẢI, TÂN VŨ, GIC, SAO Á… đều = HPP; còn có 1 địa điểm tên đúng "HPP"). Vì vậy `locOptions` (popups.jsx) phải dùng `value = TÊN` (duy nhất), label = "Tên — Ký hiệu". Nếu lưu value=code: chọn "HÀ HƯNG HẢI — HPP" sẽ lưu "HPP" rồi curLabel tra ngược ra option đầu trùng mã → nhảy về "HPP — HPP", và from_location_id gộp nhầm mọi depot HPP.

**Lưu tên là đúng & an toàn** vì backend luôn tự quy TÊN→KÝ HIỆU khi cần:
- Định giá: `$rc($s->from_loc)` (HandlesStatementPricing) name/code → code.
- from_location_id: `locationIdMap()` key cả name lẫn code → ra đúng location cụ thể.
- Hiển thị TUYẾN: dùng from_loc trực tiếp = tên; ký hiệu suy bằng `locCode()`. Xem [[trucking-report-schema]], [[coded-catalog-edit]].

Lưu ý dữ liệu: lô đã lưu trong lúc còn bug (from_loc = "HPP") hiển thị "HPP — HPP", phải chọn lại tên. Lô MBF/cũ vốn lưu tên thì đúng ngay.
