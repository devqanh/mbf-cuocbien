import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState } = React;
import { I, Btn, fmtVND } from "@trk/lib.jsx";

function TripBatchesApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const api = (method, url, body) => window.trkApi(method, url, body);
  const [batches, setBatches] = useState(B.batches || []);

  const del = async (b, e) => {
    e.stopPropagation();
    const ok = await window.confirmAction({
      title: "Xóa kỳ phí xe?",
      text: `Kỳ <b>${b.no}</b> · <b>${b.count}</b> lô · tổng <b>${(b.total || 0).toLocaleString("vi-VN")} ₫</b> sẽ bị xóa vĩnh viễn.`,
      confirmText: '<i class="bi bi-trash me-1"></i> Xóa kỳ', danger: true,
    });
    if (!ok) return;
    const res = await api("DELETE", ROUTES.batch + b.id);
    if (res && res.ok) { setBatches((bs) => bs.filter((x) => x.id !== b.id)); window.trkToast && window.trkToast("Đã xóa kỳ phí xe"); }
    else window.trkToast && window.trkToast("Xóa thất bại", "error");
  };

  const th = { textAlign: "left", fontSize: 11.5, fontWeight: 600, color: "var(--ink-3)", padding: "12px 14px 8px", textTransform: "uppercase", letterSpacing: "0.03em" };
  const td = { padding: "12px 14px", fontSize: 13.5, borderTop: "1px solid var(--line-2)" };

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: "0 22px", flexShrink: 0 }}>
        <div className="trk-head" style={{ display: "flex", alignItems: "center", gap: 12, height: 58 }}>
          <div className="trk-head-lead" style={{ display: "flex", alignItems: "center", gap: 12, flex: 1, minWidth: 0 }}>
            <div style={{ width: 32, height: 32, flexShrink: 0, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center" }}><I.truck /></div>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontSize: 15.5, fontWeight: 700, lineHeight: 1.1 }}>Chi phí & lương lái xe</div>
              <div style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Lịch sử các kỳ tính phí chuyến (theo ngày xe ra)</div>
            </div>
          </div>
          {T.canEdit && <Btn variant="primary" onClick={() => { window.location.href = ROUTES.create; }}><I.plus /> Tạo kỳ phí xe</Btn>}
        </div>
      </header>

      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: "18px 22px 40px" }}>
        <div style={{ maxWidth: 1000, margin: "0 auto", background: "#fff", border: "1px solid var(--line)", borderRadius: 14, overflow: "hidden" }}>
          {batches.length === 0 ? (
            <div style={{ padding: "48px 20px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5 }}>
              Chưa có kỳ phí xe nào. Bấm <b>Tạo kỳ phí xe</b> để chọn khoảng ngày xe ra và tính phí.
            </div>
          ) : (
            <table style={{ width: "100%", borderCollapse: "collapse" }}>
              <thead><tr style={{ background: "var(--bg)" }}>
                <th style={th}>Số kỳ</th>
                <th style={th}>Tên / ghi chú</th>
                <th style={th}>Kỳ (ngày xe ra)</th>
                <th style={{ ...th, textAlign: "center" }}>Số lô</th>
                <th style={{ ...th, textAlign: "right" }}>Tổng phí</th>
                <th style={{ ...th, width: 44 }}></th>
              </tr></thead>
              <tbody>
                {batches.map((b) => (
                  <tr key={b.id} onClick={() => { window.location.href = ROUTES.view + b.id; }}
                    style={{ cursor: "pointer" }}
                    onMouseEnter={(e) => (e.currentTarget.style.background = "var(--bg)")} onMouseLeave={(e) => (e.currentTarget.style.background = "#fff")}>
                    <td style={{ ...td, fontWeight: 700 }} className="tnum">{b.no}</td>
                    <td style={{ ...td, color: "var(--ink-3)" }}>{b.name || "—"}</td>
                    <td style={td} className="tnum">{b.from || "?"} → {b.to || "?"}</td>
                    <td style={{ ...td, textAlign: "center" }} className="tnum">{b.count}</td>
                    <td style={{ ...td, textAlign: "right", fontWeight: 700, color: "var(--accent)" }} className="tnum">{fmtVND(b.total)}</td>
                    <td style={{ ...td, textAlign: "center" }}>
                      {T.canDelete && <button type="button" onClick={(e) => del(b, e)} title="Xóa kỳ"
                        style={{ width: 30, height: 30, display: "grid", placeItems: "center", border: "none", borderRadius: 7, background: "transparent", color: "var(--ink-4)", cursor: "pointer" }}
                        onMouseEnter={(ev) => { ev.currentTarget.style.background = "#fce8e8"; ev.currentTarget.style.color = "var(--danger)"; }}
                        onMouseLeave={(ev) => { ev.currentTarget.style.background = "transparent"; ev.currentTarget.style.color = "var(--ink-4)"; }}><I.trash /></button>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<TripBatchesApp />);
