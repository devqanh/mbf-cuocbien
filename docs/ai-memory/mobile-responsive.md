---
name: mobile-responsive
description: "Mô hình responsive mobile cho trucking2 — hook useIsMobile + foundation CSS, bảng→card trên điện thoại"
metadata: 
  node_type: memory
  type: project
  originSessionId: abfdb9c6-78f0-4c41-acce-4ab00891767b
---

Đã tối ưu mobile toàn diện cho hệ thống (yêu cầu user 2026-06-14). Quy ước khi làm trang/tính năng mới:

- **Hook dùng chung**: `useIsMobile(bp=640)` trong [[trucking-vite-architecture]] (lib.jsx, đã export). Dùng nó để đổi layout: `isMobile ? "1fr" : "..."` cho gridTemplateColumns, bảng→card, sidebar→thanh chọn ngang, padding nhỏ hơn.
- **Foundation CSS** (không cần JSX): `partials/_styles.blade.php` có block `@media (max-width:640px)` ép `#trk-root input/select/textarea { font-size:16px !important }` chống iOS auto-zoom — áp cho TẤT CẢ feature page React mà không phải sửa từng inline style. `public/css/app.css` có block `@media (max-width:576px)` cho các trang Blade (`.table` → block + overflow-x scroll, `.page-header` stack, dropdown cap width, body padding 14px, touch target 40px).
- **Modal** (lib.jsx) đã tự full-width + bo góc trên + dính đáy màn hình khi mobile.
- **Mẫu bảng→card**: trang Lô hàng & Bảng kê render card list khi `isMobile`, bảng khi desktop (xem lo-hang.jsx, ui.jsx KePage).
- **Repeater rộng** (CostLineRows, DriverSpendRows trong shared.jsx): bọc trong `overflowX:auto` + `minWidth` thay vì stack.
- Trang `/yeu-cau-chi` là chuẩn mobile-first sẵn (blade riêng, KHÔNG nạp _styles) — đừng đụng quy tắc global vào nó.

Sửa .jsx phải `npm run build`; sửa app.css/_styles thì không cần build.
