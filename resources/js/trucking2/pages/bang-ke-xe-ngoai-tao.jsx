import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

import { I } from "@trk/lib.jsx";
import { ExtStatementForm } from "@trk/ui/ext-statement.jsx";

function CreateExtStatementApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const api = (method, url, body) => window.trkApi(method, url, body);
  const cfg = B.cfg || {};
  const back = () => { window.location.href = ROUTES.extStatements; };

  const onSaved = async (st) => {
    const esc = (s) => String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
    const total = (st.lines || []).reduce((a, l) => a + (l.fee || 0), 0);
    const ok = await window.confirmAction({
      title: "Lưu bảng kê xe ngoài?",
      text: `Bảng kê <b>${esc(st.no || "(chưa có số)")}</b> · <b>${(st.lines || []).length}</b> lô · tổng cước <b>${total.toLocaleString("vi-VN")} ₫</b> sẽ được tạo.`,
      confirmText: '<i class="bi bi-save me-1"></i> Lưu bảng kê',
    });
    if (!ok) return false;
    const res = await api("POST", ROUTES.extStatementStore, { statement: st });
    if (res && res.ok) { window.location.href = ROUTES.extStatements; return; }
    return false;
  };

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 12, height: 58 }}>
          <button type="button" onClick={back} title="Quay lại danh sách bảng kê xe ngoài"
            style={{ width: 34, height: 34, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: "var(--ink-2)", cursor: "pointer" }}
            onMouseEnter={(e) => (e.currentTarget.style.background = "var(--line-2)")} onMouseLeave={(e) => (e.currentTarget.style.background = "#fff")}>
            <span style={{ transform: "rotate(180deg)", display: "grid" }}><I.arrow /></span>
          </button>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center" }}><I.fx /></div>
          <div>
            <div style={{ fontSize: 15.5, fontWeight: 700, letterSpacing: "-0.01em", lineHeight: 1.1 }}>Tạo bảng kê xe ngoài</div>
            <div style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Chọn nhà xe + kỳ Giờ xe đến → lưu bảng kê phải trả</div>
          </div>
        </div>
      </header>
      <div style={{ flex: 1, minHeight: 0, overflow: "auto", padding: "20px 22px 40px" }}>
        <div style={{ maxWidth: 960, margin: "0 auto", background: "#fff", border: "1px solid var(--line)", borderRadius: 14, padding: "16px 22px 18px" }}>
          <ExtStatementForm cfg={cfg} onSaved={onSaved} onCancel={back} />
        </div>
      </div>
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<CreateExtStatementApp />);
