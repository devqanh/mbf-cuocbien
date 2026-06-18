// Barrel: gom lại để các nơi vẫn `import ... from "@trk/pop.jsx"` như cũ.
// Code thực ở components/: shared (primitives) · popups (lô) · config (cài đặt).
export { Field, ChkBox, Seg, TRACK_COLORS, colorHex } from "./components/shared.jsx";
export { CostPopup, RevenuePopup, CostPopupICD, RevenuePopupICD, InfoPopup } from "./components/popups.jsx";
export { ConfigBody, ConfigPopup, CFG_GROUPS } from "./components/config.jsx";
export { PriceList } from "./components/price-list.jsx";
