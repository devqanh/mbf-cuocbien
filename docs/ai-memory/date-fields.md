---
name: date-fields
description: "Mọi input ngày/giờ trong React app dùng DateField/DTField (Flatpickr VN), không dùng native type=date"
metadata: 
  node_type: memory
  type: project
  originSessionId: abfdb9c6-78f0-4c41-acce-4ab00891767b
---

Date/giờ trong [[trucking-vite-architecture]] dùng component dùng chung, KHÔNG dùng `<input type="date"/datetime-local>` native:

- **`DateField`** (lib.jsx) — ngày, lưu ISO `Y-m-d`, hiển thị `d/m/Y`.
- **`DTField`** (components/shared.jsx) — ngày+giờ, lưu `Y-m-d\TH:i` (giữ format datetime-local cũ), hiển thị `d/m/Y · H:i` 24h.
- Cả hai bọc **Flatpickr** (đã nạp sẵn ở layouts/app.blade.php, đã `localize(vn)`), `disableMobile: true` để dùng cùng UI lịch trên điện thoại. Style qua class `.trk-fp` + `.flatpickr-calendar{z-index:1200}` trong partials/_styles.blade.php.
- Có **fallback native** khi `window.flatpickr` không có (an toàn) → trang nào không nạp Flatpickr vẫn chạy.
- Đã thay toàn bộ input ngày native trong app (ui.jsx bảng kê, lo-hang export popup, config, quan-ly-xe, popups). Khi thêm field ngày mới → import DateField/DTField, đừng viết `type=date` native.
- **Ngoại lệ**: 2 trang public cho lái xe (`yeu-cau-chi`, `ke-hoach-public`) KHÔNG nạp Flatpickr/_styles → vẫn dùng native (picker OS trên mobile vốn tốt). Muốn đổi phải nạp Flatpickr + .trk-fp vào blade riêng của chúng.
