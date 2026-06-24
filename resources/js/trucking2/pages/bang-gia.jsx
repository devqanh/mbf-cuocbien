import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

import { I } from "@trk/lib.jsx";
import { BangGiaPage } from "@trk/ui.jsx";

const { useState } = React;

function PricesApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const DEFAULT_CFG = { locations: [], locationCode: {}, customers: [], customerInfo: {} };
  const api = (method, url, body) => window.trkApi(method, url, body);
  const [cfg, setCfgState] = useState(() => ({ ...DEFAULT_CFG, ...(B.cfg || {}) }));

  // Cập nhật danh sách BẢNG GIÁ (book) của 1 khách sau khi tạo/sửa/xóa/lưu.
  const setBooks = (cust, books) => setCfgState((c) => ({
    ...c, customerInfo: { ...(c.customerInfo || {}), [cust]: { ...((c.customerInfo || {})[cust] || {}), priceBooks: books } },
  }));

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: "0 22px", flexShrink: 0 }}>
        <div className="trk-head" style={{ display: "flex", alignItems: "center", gap: 14, height: 58 }}>
          <div style={{ width: 32, height: 32, flexShrink: 0, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center" }}><I.truck /></div>
          <div style={{ fontSize: 15.5, fontWeight: 700, letterSpacing: "-0.01em" }}>Bảng giá theo khoảng ngày</div>
          <div style={{ flex: 1 }} />
          <div style={{ fontSize: 12, color: "var(--ink-4)" }}>Mỗi khách nhiều bảng giá · bảng kê lấy theo ngày cont ra</div>
        </div>
      </header>
      <BangGiaPage cfg={cfg} setBooks={setBooks} api={api} routes={ROUTES} />
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<PricesApp />);
