---
name: trucking-partial-save
description: Lưu lô hàng theo TỪNG FIELD (chỉ field đã sửa) để tránh lost-update khi nhiều người sửa
metadata: 
  node_type: memory
  type: project
  originSessionId: e45e4c6a-42d4-4a22-83c9-05cf2e456c1e
---

Lưu lô hàng (`updateShipment`) dùng **field-level partial save** để tránh **lost update**: khi A sửa field X, B (phiên cũ) sửa field Y rồi lưu — chỉ field Y được ghi, X của A giữ nguyên (không bị đè).

**Cách hoạt động:**
- Client (`lo-hang.jsx`): `dirtyFields` (useRef, id→Set field) ghi lại field nào `patch()` đã đổi. Khi Lưu (PUT), chỉ gửi `ship` = các field đã sửa + mảng `fields`.
- Server (`saveShipment($data, $sheet, $s, $only)`): `$only` = danh sách field được phép ghi; field ngoài danh sách GIỮ NGUYÊN giá trị DB. Dòng con `cost`/`rev` chỉ resync khi nhóm đó nằm trong `$only`. `$only = null` → ghi toàn bộ (lô mới POST / import).

**Giới hạn:** vẫn last-write-wins nếu 2 người sửa **CÙNG 1 field** (hiếm). Granularity = key top-level (booking, from, to, cost, rev…). Sau khi lưu, `commitDirty` gọi `load()` nạp lại trang → thấy bản đã merge.

**CHƯA áp dụng cho bảng kê (statement)** — vẫn ghi đè toàn bộ; mở rộng tương tự nếu cần. Liên quan [[trucking-vite-architecture]] [[trucking-redesign]].
