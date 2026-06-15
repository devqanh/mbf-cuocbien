import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useEffect, useRef } = React;
import { I, useIsMobile } from "@trk/lib.jsx";

const z = (n) => String(n).padStart(2, "0");
const fmtClock = (ts) => { if (!ts) return "—"; const d = new Date(ts); return `${z(d.getHours())}:${z(d.getMinutes())} ${z(d.getDate())}/${z(d.getMonth() + 1)}/${d.getFullYear()}`; };
const fmtDur = (ms) => { if (!ms || ms < 0) return "—"; const m = Math.round(ms / 60000); if (m < 60) return m + " phút"; const h = Math.floor(m / 60); return h + "h" + z(m % 60); };

function VisitsPage() {
  const isMobile = useIsMobile();
  const T = window.__TRK || {}; const ROUTES = T.routes || {};
  const api = (m, u) => window.trkApi(m, u);

  const [rows, setRows] = useState([]);
  const [info, setInfo] = useState({ page: 1, perPage: 30, total: 0, lastPage: 1 });
  const [loading, setLoading] = useState(true);
  const [q, setQ] = useState("");
  const [qDeb, setQDeb] = useState("");
  const [page, setPage] = useState(1);
  const reqId = useRef(0);

  useEffect(() => { const t = setTimeout(() => { setQDeb(q); setPage(1); }, 350); return () => clearTimeout(t); }, [q]);

  const load = () => {
    const my = ++reqId.current; setLoading(true);
    const p = new URLSearchParams({ page: String(page), perPage: "30" });
    if (qDeb.trim()) p.set("q", qDeb.trim());
    api("GET", ROUTES.visits + "?" + p.toString()).then((r) => {
      if (my !== reqId.current) return;
      if (r && r.ok) { setRows(r.visits || []); setInfo({ page: r.page, perPage: r.perPage, total: r.total, lastPage: r.lastPage }); }
    }).catch(() => {}).finally(() => { if (my === reqId.current) setLoading(false); });
  };
  useEffect(() => { load(); }, [page, qDeb]);

  const th = { textAlign: "left", fontSize: 11, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.03em", padding: "10px 12px", background: "#fafbfc", borderBottom: "1px solid var(--line)", whiteSpace: "nowrap" };
  const td = { padding: "10px 12px", fontSize: 13, borderBottom: "1px solid var(--line-2)", verticalAlign: "top" };

  const pageList = () => {
    const last = info.lastPage, cur = info.page;
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
    const s = new Set([1, last, cur, cur - 1, cur + 1]); const arr = [...s].filter((n) => n >= 1 && n <= last).sort((a, b) => a - b);
    const out = []; let prev = 0; arr.forEach((n) => { if (n - prev > 1) out.push("…"); out.push(n); prev = n; }); return out;
  };

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: isMobile ? "10px 14px" : "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 12, height: isMobile ? "auto" : 58, flexWrap: "wrap" }}>
          <a href={ROUTES.back} title="Về Theo dõi xe"
            style={{ display: "inline-flex", alignItems: "center", gap: 6, flexShrink: 0, padding: "7px 12px", fontSize: 13, fontWeight: 600, color: "var(--ink-2)", textDecoration: "none", border: "1px solid var(--line)", borderRadius: 9 }}>
            <span style={{ transform: "rotate(180deg)", display: "inline-flex" }}><I.arrow /></span> Theo dõi xe
          </a>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "#4f46e5", color: "#fff", display: "grid", placeItems: "center", flexShrink: 0 }}><i className="bi bi-clock-history" /></div>
          <div>
            <div style={{ fontSize: 15.5, fontWeight: 700 }}>Lịch sử đến / rời kho</div>
            <div style={{ fontSize: 11.5, color: "var(--ink-3)" }}>{info.total} lượt ghé · giờ đến dùng để tính thời gian lô hàng tới kho</div>
          </div>
          <div style={{ flex: 1 }} />
          <div style={{ position: "relative", flex: isMobile ? "1 1 100%" : "0 0 auto" }}>
            <span style={{ position: "absolute", left: 11, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)" }}><I.search /></span>
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Tìm biển số, tài xế, kho…"
              style={{ width: isMobile ? "100%" : 280, padding: "9px 12px 9px 34px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 10, outline: "none", background: "#fafbfc", boxSizing: "border-box" }} />
          </div>
        </div>
      </header>

      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: isMobile ? "12px 12px 24px" : "16px 22px 24px" }}>
        <div style={{ maxWidth: 1040, margin: "0 auto", background: "#fff", border: "1px solid var(--line)", borderRadius: 12, overflow: "hidden" }}>
          {loading && rows.length === 0 ? (
            <div style={{ padding: "40px", textAlign: "center", color: "var(--ink-4)" }}><i className="bi bi-arrow-repeat" style={{ animation: "trk-spin .7s linear infinite" }} /> Đang tải…</div>
          ) : rows.length === 0 ? (
            <div style={{ padding: "40px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5 }}>Chưa có lịch sử ghé kho. Hệ thống quét mỗi 5 phút (cần đã ghim tọa độ kho + xe vào trong bán kính).</div>
          ) : (
            <div style={{ overflowX: "auto" }}>
              <table style={{ width: "100%", borderCollapse: "collapse" }}>
                <thead><tr>
                  <th style={th}>Xe</th><th style={th}>Tài xế</th><th style={th}>Kho</th>
                  <th style={th}>Đến</th><th style={th}>Rời</th><th style={{ ...th, textAlign: "right" }}>Ở kho</th>
                </tr></thead>
                <tbody>
                  {rows.map((v) => {
                    const dur = v.open ? (Date.now() - (v.arrivedAt || Date.now())) : ((v.departedAt || 0) - (v.arrivedAt || 0));
                    return (
                      <tr key={v.id}>
                        <td style={td}>
                          {v.matched
                            ? <><span className="tnum" style={{ fontWeight: 700 }}>{v.plate}</span> <span title="Khớp xe hệ thống" style={{ fontSize: 10, fontWeight: 700, color: "var(--good)", background: "var(--good-weak)", padding: "1px 6px", borderRadius: 999 }}>✓ hệ thống</span>
                                {v.gpsPlate && v.gpsPlate !== v.plate && <div style={{ fontSize: 10.5, color: "var(--ink-4)" }} className="tnum">GPS: {v.gpsPlate}</div>}</>
                            : <><span className="tnum" style={{ fontWeight: 700 }}>{v.gpsPlate || "—"}</span> <span style={{ fontSize: 10, color: "var(--ink-4)" }}>(chưa gán)</span></>}
                        </td>
                        <td style={{ ...td, color: "var(--ink-2)" }}>{v.driver || "—"}</td>
                        <td style={{ ...td, fontWeight: 600 }}>🏭 {v.warehouse}</td>
                        <td style={{ ...td, color: "var(--ink-2)" }} className="tnum">{fmtClock(v.arrivedAt)}</td>
                        <td style={td} className="tnum">{v.open ? <span style={{ color: "var(--good)", fontWeight: 700 }}>● Đang ở kho</span> : <span style={{ color: "var(--ink-2)" }}>{fmtClock(v.departedAt)}</span>}</td>
                        <td style={{ ...td, textAlign: "right", fontWeight: 600, color: v.open ? "var(--good)" : "var(--ink)" }} className="tnum">{fmtDur(dur)}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* phân trang */}
        {info.lastPage > 1 && (
          <div style={{ maxWidth: 1040, margin: "12px auto 0", display: "flex", alignItems: "center", justifyContent: "center", gap: 6, flexWrap: "wrap" }}>
            <button type="button" disabled={info.page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))}
              style={{ padding: "7px 12px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, background: "#fff", cursor: info.page <= 1 ? "default" : "pointer", opacity: info.page <= 1 ? 0.5 : 1 }}>‹ Trước</button>
            {pageList().map((n, i) => n === "…"
              ? <span key={"e" + i} style={{ padding: "0 4px", color: "var(--ink-4)" }}>…</span>
              : <button key={n} type="button" onClick={() => setPage(n)}
                  style={{ minWidth: 34, padding: "7px 10px", fontSize: 13, fontWeight: n === info.page ? 700 : 500, border: `1px solid ${n === info.page ? "var(--accent)" : "var(--line)"}`, borderRadius: 8, background: n === info.page ? "var(--accent-weak)" : "#fff", color: n === info.page ? "var(--accent)" : "var(--ink-2)", cursor: "pointer" }}>{n}</button>)}
            <button type="button" disabled={info.page >= info.lastPage} onClick={() => setPage((p) => Math.min(info.lastPage, p + 1))}
              style={{ padding: "7px 12px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, background: "#fff", cursor: info.page >= info.lastPage ? "default" : "pointer", opacity: info.page >= info.lastPage ? 0.5 : 1 }}>Sau ›</button>
          </div>
        )}
      </div>
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<VisitsPage />);
