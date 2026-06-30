---
name: vite-port-conflict
description: "Trắng trang qua tunnel có thể do Vite chặn host (allowedHosts) HOẶC dự án khác chiếm port 5173 trên [::1]"
metadata: 
  node_type: memory
  type: project
  originSessionId: 7f0d2428-dc59-47c9-b3b0-a89c0b9bf3a0
---

Trang trucking trắng khi chạy `npm run dev` qua tunnel (vite-trucking.dewa.vn) thường KHÔNG do code — production build vẫn pass. Hai nguyên nhân hạ tầng:

1. **Vite chặn Host lạ (allowedHosts)** → tunnel trả 403 cho mọi module ⇒ trắng. Fix: `server.allowedHosts: [tunnelHost]` trong vite.config.js (đã thêm trong block tunnel, chỉ áp khi có VITE_TUNNEL_HOST). Xem [[dev-tunnel-vite]].

2. **Xung đột port 5173**: dự án khác (vd C:\laragon\www\thanhtoanbilltudong) chạy dev server riêng chiếm 5173 trên IPv6 loopback `[::1]`. Tunnel phân giải `localhost → ::1` trước ⇒ request rơi vào server SAI (không có allowedHosts) ⇒ 403. cuocbien bind `0.0.0.0`+`[::]` vẫn 200 trên IPv4 nhưng thua bind cụ thể `[::1]`.

**Chẩn đoán nhanh:** so HTTP code IPv4 vs IPv6:
`curl --resolve host:5173:127.0.0.1 ...` (cuocbien) vs `curl --resolve host:5173:[::1] ...` (kẻ chiếm). 200 vs 403 = đúng bệnh.
Tìm thủ phạm: `netstat -ano | grep :5173` rồi `wmic process where ProcessId=<pid> get CommandLine`.

**Fix:** kill PID dự án kia (`taskkill //PID <pid> //F`) — server cuocbien `[::]` sẽ phục vụ luôn `[::1]`; chỉ cần refresh, không cần restart npm run dev. Lâu dài: cho dự án kia chạy port khác (`--port 5174`).
