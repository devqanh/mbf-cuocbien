---
name: dev-tunnel-vite
description: Local map qua Cloudflare Tunnel/ngrok (chỉ 443) → cách chạy npm run dev cho đúng
metadata:
  type: project
---

User chạy site local nhưng ánh xạ ra domain **https://trucking.dewa.vn** qua **Cloudflare Tunnel/ngrok** (chỉ forward cổng 443 về PHP). Cổng **5173** của Vite dev server KHÔNG lộ ra ngoài.

**Hệ quả:** `npm run dev` mở qua domain tunnel sẽ "lỗi" — asset + HMR `http://localhost:5173` bị chặn mixed-content trên trang https và không với tới được. KHÔNG phải lỗi cú pháp config (`vite build` vẫn chạy bình thường).

**Cách dùng (đã cấu hình trong `vite.config.js`):**
- `server.host: true` + `server.cors: true` luôn bật.
- Có biến `VITE_TUNNEL_HOST` trong `.env`: để trống = dev local bình thường (mở site qua URL Laragon local như `cuocbien.test`/`localhost`, không qua tunnel). Set = `vite-trucking.dewa.vn` (hoặc URL ngrok cổng 5173) thì config tự thêm `origin: https://<host>` + `hmr {host, protocol:'wss', clientPort:443}`.
- Nếu set biến: phải thêm 1 ingress hostname trong Cloudflare Tunnel trỏ `vite-trucking.dewa.vn → http://localhost:5173` (ngrok thì mở thêm `ngrok http 5173`).

**Khuyến nghị mặc định:** dev/HMR thì mở site qua URL local, để trống `VITE_TUNNEL_HOST`; tunnel chỉ để demo. Build production không bị ảnh hưởng bởi cấu hình này.
