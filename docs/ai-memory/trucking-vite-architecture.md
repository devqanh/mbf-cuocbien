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
- `pop.jsx` — **barrel** re-export (giữ `import ... from "@trk/pop.jsx"` cho mọi nơi). Code thực ở `components/`: `shared.jsx` (primitives), `popups.jsx` (popup lô), `price-list.jsx` (PriceList), `config.jsx` (ConfigBody/ConfigPopup/CFG_GROUPS — Cài đặt).
- **`components/config/` (tách 2026-06, config.jsx 1256→500d):** `MapPicker.jsx` (AddrInput+MapPicker), `RouteFees.jsx`, `FuelPrices.jsx`, `CustomerManager.jsx`, `DriversManager.jsx`, `groups.js` (CFG_GROUPS). config.jsx chỉ còn ConfigBody+ConfigPopup, import lại từ config/ rồi re-export → call-site không đổi.
- `ui.jsx` — **barrel** (tách 2026-06, 728→5d) re-export từ `ui/`: `primitives.jsx` (SortBtn/CellBtn/Badge/EditCell/TH/TD), `pricer.js` (makePricer — legacy), `statement.jsx` (StatementForm/StatementDetailBody/Saved*/KePage/DriftChip + CO consts), `bang-gia.jsx` (BangGiaPage). Mọi `import ... from "@trk/ui.jsx"` giữ nguyên.
- **Tách file con = thuần component (không phải vite entry)** → KHÔNG sửa vite.config.js; chỉ entry ở `pages/` mới cần khai báo. Barrel giữ đường import cũ → an toàn.
- `shared.js` — vanilla: `window.confirmAction`, `window.trkToast`, fit `#trk-root`. Mỗi page entry `import "@trk/shared.js"`.
- `pages/<page>.jsx` — entry mỗi trang: `import { createRoot }` + mount `#trk-root`. Dữ liệu qua `window.__TRK`. **Page lớn đã tách thân ra `components/<page>/`, entry chỉ còn import + render (2026-06):** `quan-ly-xe` → components/quan-ly-xe/{parts,FleetApp,asset}; `theo-doi-xe` → components/theo-doi-xe/{helpers.js, TrackingApp.jsx}; `lo-hang` → components/lo-hang/ShipmentsApp.jsx. (TrackingApp/ShipmentsApp vẫn là 1 component to ~900d — pha sau có thể xẻ tiếp child component nếu cần.)

Alias `@trk` = `resources/js/trucking2` (cấu hình ở vite.config.js + 6 entry trong laravel input). JSX do esbuild của Vite transpile (classic, `jsxFactory: React.createElement`) — KHÔNG dùng @vitejs/plugin-react (nó kẹt peer với Vite 7). Blade dùng `@vite('resources/js/trucking2/pages/<page>.jsx')` trong `@push('scripts')`.

**Quy trình dev BẮT BUỘC:** sau khi sửa file .jsx phải `npm run build` (hoặc `npm run dev` để HMR) — KHÔNG còn sửa JSX trong blade nữa. Khi deploy phải chạy `npm run build`. Manifest ở `public/build`.

**File chết:** `resources/views/trucking2/partials/_runtime.blade.php` (2566 dòng, Babel) không còn được include — giữ tạm làm tham chiếu, có thể xóa khi đã chạy ổn. Liên quan [[trucking-redesign]] [[no-seed-demo]].
