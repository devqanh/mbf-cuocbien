---
name: trucking-vite-architecture
description: Trucking v2 React giờ build bằng Vite (không còn Babel in-browser); code ở resources/js/trucking2
metadata: 
  node_type: memory
  type: project
  originSessionId: e45e4c6a-42d4-4a22-83c9-05cf2e456c1e
---

Trucking v2 (trang `/trucking-v2/*`) đã **migrate khỏi Babel in-browser** sang **Vite build sẵn** (ngày 2026-06-13) để hết giật/đơ và dễ maintain.

**Cấu trúc code React (resources/js/trucking2/):**
- `lib.jsx` — helpers, fields, Combo, Modal, Money, calc*, fmt*, icons (cũ: `window.__lib`). Export named.
- `pop.jsx` — **barrel** re-export (giữ `import ... from "@trk/pop.jsx"` cho mọi nơi). Code thực ở `components/`: `shared.jsx` (Field, ChkBox, FlagPicker, Seg, *Rows, colorHex, TRACK_COLORS — primitives), `popups.jsx` (CostPopup, RevenuePopup*, InfoPopup — popup lô), `config.jsx` (ConfigBody, CFG_GROUPS, RouteFees, FuelPrices, PriceList, CustomerManager — Cài đặt). Phụ thuộc: shared ← popups & config.
- `ui.jsx` — Badge, TH/TD, makePricer, StatementForm, StatementDetailBody, SavedStatementPage, KePage, BangGiaPage… (cũ: `window.__ui`). Import từ lib + pop.
- `shared.js` — vanilla: `window.confirmAction`, `window.trkToast`, fit `#trk-root`. Mỗi page entry `import "@trk/shared.js"`.
- `pages/<page>.jsx` — entry mỗi trang (lo-hang, bang-gia, bang-ke, bang-ke-tao, bang-ke-xem, cai-dat, **quan-ly-xe**): `import { createRoot } from "react-dom/client"` + mount `#trk-root`. Dữ liệu vẫn qua `window.__TRK` (blade @section content).

Alias `@trk` = `resources/js/trucking2` (cấu hình ở vite.config.js + 6 entry trong laravel input). JSX do esbuild của Vite transpile (classic, `jsxFactory: React.createElement`) — KHÔNG dùng @vitejs/plugin-react (nó kẹt peer với Vite 7). Blade dùng `@vite('resources/js/trucking2/pages/<page>.jsx')` trong `@push('scripts')`.

**Quy trình dev BẮT BUỘC:** sau khi sửa file .jsx phải `npm run build` (hoặc `npm run dev` để HMR) — KHÔNG còn sửa JSX trong blade nữa. Khi deploy phải chạy `npm run build`. Manifest ở `public/build`.

**File chết:** `resources/views/trucking2/partials/_runtime.blade.php` (2566 dòng, Babel) không còn được include — giữ tạm làm tham chiếu, có thể xóa khi đã chạy ổn. Liên quan [[trucking-redesign]] [[no-seed-demo]].
