import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useEffect } = React;
import { I } from "@trk/lib.jsx";
import { BangGiaPage } from "@trk/ui.jsx";

function PricesApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const DEFAULT_CFG = { locations: [], locationCode: {}, customers: [], customerInfo: {}, prices: {} };
  const api = (method, url, body) => window.trkApi(method, url, body);
  const [cfg, setCfgState] = useState(() => ({ ...DEFAULT_CFG, ...(B.cfg || {}) }));
  const [dirty, setDirty] = useState(false);
  const [saving, setSaving] = useState(false);

  // Sửa tay → chỉ cập nhật state + đánh dấu "có thay đổi" (KHÔNG tự lưu)
  const setCfgKey = (key, val) => { setCfgState((c) => ({ ...c, [key]: val })); setDirty(true); };
  // Import đã lưu ở server → cập nhật bảng giá, không coi là thay đổi chưa lưu
  const onImported = (cust, arr) => setCfgState((c) => ({ ...c, customerInfo: { ...(c.customerInfo || {}), [cust]: { ...((c.customerInfo || {})[cust] || {}), priceList: arr } } }));
  // Lazy-load bảng giá của 1 khách khi mở (không đánh dấu dirty). Tránh nạp toàn bộ khi vào trang.
  const loadPrices = async (cust) => {
    if (!cust) return;
    try {
      const r = await window.trkApi("GET", ROUTES.customerPrices + "?customer=" + encodeURIComponent(cust));
      if (r && r.ok) onImported(cust, r.priceList || []);
    } catch (e) { /* giữ nguyên, người dùng có thể chọn lại */ }
  };

  const save = () => {
    if (!dirty || saving) return;
    setSaving(true);
    // Trang Bảng giá chỉ sửa bảng giá của khách → lưu qua endpoint Khách hàng (kèm priceList)
    api("PUT", ROUTES.customers, { cfg: { customers: cfg.customers, customerInfo: cfg.customerInfo } }).then((r) => { setSaving(false); if (r && r.ok) setDirty(false); }).catch(() => setSaving(false));
  };

  // Cảnh báo khi rời trang lúc còn thay đổi chưa lưu
  useEffect(() => {
    const h = (e) => { if (dirty) { e.preventDefault(); e.returnValue = ""; } };
    window.addEventListener("beforeunload", h);
    return () => window.removeEventListener("beforeunload", h);
  }, [dirty]);

  const statusEl = saving
    ? <span style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Đang lưu…</span>
    : dirty
      ? <span style={{ display: "inline-flex", alignItems: "center", gap: 6, fontSize: 12.5, fontWeight: 600, color: "var(--warn)" }}><span style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} /> Có thay đổi chưa lưu</span>
      : <span style={{ display: "inline-flex", alignItems: "center", gap: 5, fontSize: 12.5, fontWeight: 600, color: "var(--good)" }}><I.check /> Đã lưu</span>;

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 14, height: 58 }}>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center" }}><I.truck /></div>
          <div style={{ fontSize: 15.5, fontWeight: 700, letterSpacing: "-0.01em" }}>Bảng giá đã gửi</div>
          <div style={{ flex: 1 }} />
          {statusEl}
          <button type="button" onClick={save} disabled={!dirty || saving}
            style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "9px 16px", fontSize: 13.5, fontWeight: 600, cursor: dirty && !saving ? "pointer" : "default",
              color: dirty && !saving ? "#fff" : "var(--ink-4)", background: dirty && !saving ? "var(--accent)" : "var(--line-2)", border: "none", borderRadius: 10,
              boxShadow: dirty && !saving ? "0 1px 2px rgba(42,111,219,.4)" : "none" }}>
            <I.check /> Lưu thay đổi
          </button>
        </div>
      </header>
      <BangGiaPage cfg={cfg} setCfg={setCfgKey} onImported={onImported} loadPrices={loadPrices} />
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<PricesApp />);

