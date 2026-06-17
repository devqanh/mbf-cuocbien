---
name: product-tour
description: Product tour/onboarding tái dùng bằng driver.js — helper @trk/tour.js, áp cho mọi trang qua data-tour + steps
metadata:
  type: project
---

Tour giới thiệu tính năng dùng **driver.js** (1.4.0, MIT, ~5KB) — chọn vì tái dùng được mọi trang (theo CSS selector) + giao diện chuyên nghiệp + free thương mại (khác intro.js phải mua).

**Helper chung:** `resources/js/trucking2/tour.js` (alias `@trk/tour.js`) export:
- `runTour(steps, { key, onDone })` — chạy tour; Việt hóa Tiếp/Quay lại/Xong + nút **"Bỏ qua toàn bộ"** (chèn qua `onPopoverRender`); tự bỏ bước có `element` đang ẩn; đánh dấu đã xem vào localStorage[key] khi đóng.
- `tourSeen(key)` — đã xem chưa.
- `steps = [{ element: '[data-tour="x"]', title, description, side?, align? }]`; bước KHÔNG có `element` → popover giữa màn hình.

**Dùng cho 1 trang (mẫu ở `pages/theo-doi-xe.jsx`):**
1. Gắn `data-tour="..."` vào các phần tử cần highlight.
2. Khai báo `tourSteps` + `const TOUR_KEY="trk_tour_<page>_v1"`.
3. Nút "Hướng dẫn": `import("@trk/tour.js").then(({runTour})=>runTour(tourSteps,{key:TOUR_KEY}))`.
4. Tự mở lần đầu: `useEffect khi booted → import(...).then(({runTour,tourSeen})=>{ if(!tourSeen(KEY)) runTour(...) })`.
- **Lazy-load:** luôn `import("@trk/tour.js")` động → driver.js tách chunk riêng (`tour-*.js`), chỉ tải khi mở tour (không phình bundle trang).
- Đổi nội dung tour mà giữ key cũ thì user đã xem sẽ không tự hiện lại → tăng version trong key (vd `_v2`) nếu muốn hiện lại cho mọi người.

Mới áp cho trang Theo dõi xe; các trang khác (Lô hàng, Cài đặt…) chỉ cần lặp lại 4 bước trên. Liên quan [[gps-tracking]].
