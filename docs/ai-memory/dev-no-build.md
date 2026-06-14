---
name: dev-no-build
description: Đang chạy npm run dev — KHÔNG cần npm run build sau khi sửa .jsx
metadata:
  type: feedback
---

User chạy sẵn **`npm run dev`** (Vite HMR). Sửa file `.jsx`/`.js`/CSS xong **KHÔNG cần `npm run build`** — Vite tự rebuild/hot-reload.

**Why:** trước đây phải build vì dev server (5173) không lộ qua tunnel ([[dev-tunnel-vite]]); giờ đã chạy dev được nên build thủ công là thừa, mất thời gian.

**How to apply:** sau khi sửa frontend, báo "đã sửa, dev tự nạp" — đừng chạy `npm run build`. Chỉ build khi user yêu cầu rõ hoặc khi deploy production (`git pull && npm run build`).
