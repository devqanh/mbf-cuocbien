import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useEffect, useRef } = React;
import { I, useIsMobile, DateField } from "@trk/lib.jsx";

const z = (n) => String(n).padStart(2, "0");
const fmtClock = (ts) => { if (!ts) return "—"; const d = new Date(ts); return `${z(d.getHours())}:${z(d.getMinutes())} ${z(d.getDate())}/${z(d.getMonth() + 1)}/${d.getFullYear()}`; };
const fmtDur = (ms) => { if (!ms || ms < 0) return "—"; const m = Math.round(ms / 60000); if (m < 60) return m + " phút"; const h = Math.floor(m / 60); return h + "h" + z(m % 60); };
const fmtMin = (min) => fmtDur((min || 0) * 60000);
const toYmd = (d) => `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}`;

function VisitsPage() {
  const isMobile = useIsMobile();
  const T = window.__TRK || {}; const ROUTES = T.routes || {};
  const api = (m, u) => window.trkApi(m, u);

  const [view, setView] = useState("list");        // "list" | "stats"
  const [rows, setRows] = useState([]);
  const [info, setInfo] = useState({ page: 1, perPage: 30, total: 0, lastPage: 1 });
  const [stats, setStats] = useState({ rows: [], totals: { vehicles: 0, trips: 0 } });
  const [loading, setLoading] = useState(true);
  const [q, setQ] = useState("");
  const [qDeb, setQDeb] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [page, setPage] = useState(1);
  const reqId = useRef(0);

  useEffect(() => { const t = setTimeout(() => { setQDeb(q); setPage(1); }, 350); return () => clearTimeout(t); }, [q]);

  const params = () => { const p = new URLSearchParams(); if (qDeb.trim()) p.set("q", qDeb.trim()); if (from) p.set("from", from); if (to) p.set("to", to); return p; };

  const load = () => {
    const my = ++reqId.current; setLoading(true);
    if (view === "stats") {
      api("GET", ROUTES.visitStats + "?" + params().toString()).then((r) => {
        if (my !== reqId.current) return;
        if (r && r.ok) setStats({ rows: r.rows || [], totals: r.totals || { vehicles: 0, trips: 0 } });
      }).catch(() => {}).finally(() => { if (my === reqId.current) setLoading(false); });
    } else {
      const p = params(); p.set("page", String(page)); p.set("perPage", "30");
      api("GET", ROUTES.visits + "?" + p.toString()).then((r) => {
        if (my !== reqId.current) return;
        if (r && r.ok) { setRows(r.visits || []); setInfo({ page: r.page, perPage: r.perPage, total: r.total, lastPage: r.lastPage }); }
      }).catch(() => {}).finally(() => { if (my === reqId.current) setLoading(false); });
    }
  };
  useEffect(() => { load(); }, [view, page, qDeb, from, to]);

  const setRange = (f, t) => { setFrom(f); setTo(t); setPage(1); };
  const presetDays = (n) => { const now = new Date(); const f = new Date(now); f.setDate(now.getDate() - (n - 1)); setRange(toYmd(f), toYmd(now)); };
  const presetMonth = () => { const now = new Date(); setRange(toYmd(new Date(now.getFullYear(), now.getMonth(), 1)), toYmd(now)); };

  const th = { textAlign: "left", fontSize: 11, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.03em", padding: "10px 12px", background: "#fafbfc", borderBottom: "1px solid var(--line)", whiteSpace: "nowrap", position: "sticky", top: 0 };
  const td = { padding: "10px 12px", fontSize: 13, borderBottom: "1px solid var(--line-2)", verticalAlign: "top" };

  const pageList = () => {
    const last = info.lastPage, cur = info.page;
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
    const s = new Set([1, last, cur, cur - 1, cur + 1]); const arr = [...s].filter((n) => n >= 1 && n <= last).sort((a, b) => a - b);
    const out = []; let prev = 0; arr.forEach((n) => { if (n - prev > 1) out.push("…"); out.push(n); prev = n; }); return out;
  };

  const plateCell = (v) => v.matched
    ? <><span className="tnum" style={{ fontWeight: 700 }}>{v.plate}</span> <span title="Khớp xe hệ thống" style={{ fontSize: 10, fontWeight: 700, color: "var(--good)", background: "var(--good-weak)", padding: "1px 6px", borderRadius: 999 }}>✓</span></>
    : <><span className="tnum" style={{ fontWeight: 700 }}>{v.plate || v.gpsPlate || "—"}</span> <span style={{ fontSize: 10, color: "var(--ink-4)" }}>(chưa gán)</span></>;

  const presetBtn = (label, onClick) => (
    <button type="button" onClick={onClick}
      style={{ padding: "6px 10px", fontSize: 12, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-2)", cursor: "pointer", whiteSpace: "nowrap" }}>{label}</button>
  );
  const tabBtn = (k, label, icon) => {
    const on = view === k;
    return (
      <button type="button" onClick={() => { setView(k); setPage(1); }}
        style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 14px", fontSize: 13, fontWeight: 600, border: "none", borderRadius: 7, cursor: "pointer", whiteSpace: "nowrap", background: on ? "#fff" : "transparent", color: on ? "#4f46e5" : "var(--ink-3)", boxShadow: on ? "0 1px 2px rgba(16,19,23,.12)" : "none" }}>
        <i className={"bi " + icon} /> {label}
      </button>
    );
  };

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: isMobile ? "10px 14px" : "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 12, height: isMobile ? "auto" : 58, flexWrap: "wrap", paddingTop: isMobile ? 0 : 0 }}>
          <a href={ROUTES.back} title="Về Theo dõi xe"
            style={{ display: "inline-flex", alignItems: "center", gap: 6, flexShrink: 0, padding: "7px 12px", fontSize: 13, fontWeight: 600, color: "var(--ink-2)", textDecoration: "none", border: "1px solid var(--line)", borderRadius: 9 }}>
            <span style={{ transform: "rotate(180deg)", display: "inline-flex" }}><I.arrow /></span> Theo dõi xe
          </a>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "#4f46e5", color: "#fff", display: "grid", placeItems: "center", flexShrink: 0 }}><i className="bi bi-clock-history" /></div>
          <div>
            <div style={{ fontSize: 15.5, fontWeight: 700 }}>Lịch sử đến / rời kho</div>
            <div style={{ fontSize: 11.5, color: "var(--ink-3)" }}>{view === "stats" ? `${stats.totals.vehicles} xe · ${stats.totals.trips} chuyến trong kỳ` : `${info.total} lượt ghé`}</div>
          </div>
          <div style={{ flex: 1 }} />
          <div style={{ position: "relative", flex: isMobile ? "1 1 100%" : "0 0 auto" }}>
            <span style={{ position: "absolute", left: 11, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)" }}><I.search /></span>
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Tìm biển số, tài xế, kho…"
              style={{ width: isMobile ? "100%" : 260, padding: "9px 12px 9px 34px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 10, outline: "none", background: "#fafbfc", boxSizing: "border-box" }} />
          </div>
        </div>
        {/* thanh lọc: tab + khoảng ngày + preset */}
        <div style={{ display: "flex", alignItems: "center", gap: 10, flexWrap: "wrap", padding: "0 0 11px" }}>
          <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 8, padding: 2 }}>
            {tabBtn("list", "Lượt ghé", "bi-list-ul")}
            {tabBtn("stats", "Thống kê xe", "bi-bar-chart-line")}
          </div>
          <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
            <span style={{ fontSize: 12, color: "var(--ink-3)" }}>Từ</span>
            <div style={{ width: 150 }}><DateField value={from} onChange={(v) => setRange(v, to)} /></div>
            <span style={{ fontSize: 12, color: "var(--ink-3)" }}>đến</span>
            <div style={{ width: 150 }}><DateField value={to} onChange={(v) => setRange(from, v)} /></div>
          </div>
          {presetBtn("7 ngày", () => presetDays(7))}
          {presetBtn("30 ngày", () => presetDays(30))}
          {presetBtn("Tháng này", presetMonth)}
          {(from || to) && presetBtn("✕ Tất cả", () => setRange("", ""))}
        </div>
      </header>

      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: isMobile ? "12px 12px 24px" : "16px 22px 24px" }}>
        <div style={{ maxWidth: 1100, margin: "0 auto", background: "#fff", border: "1px solid var(--line)", borderRadius: 12, overflow: "hidden" }}>
          {loading && (view === "stats" ? stats.rows.length === 0 : rows.length === 0) ? (
            <div style={{ padding: "40px", textAlign: "center", color: "var(--ink-4)" }}><i className="bi bi-arrow-repeat" style={{ animation: "trk-spin .7s linear infinite" }} /> Đang tải…</div>
          ) : view === "stats" ? (
            stats.rows.length === 0 ? (
              <div style={{ padding: "40px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5 }}>Không có chuyến nào trong khoảng đã chọn.</div>
            ) : (
              <div style={{ overflowX: "auto" }}>
                <table style={{ width: "100%", borderCollapse: "collapse" }}>
                  <thead><tr>
                    <th style={{ ...th, width: 40, textAlign: "center" }}>#</th>
                    <th style={th}>Xe</th><th style={th}>Tài xế</th>
                    <th style={{ ...th, textAlign: "center" }}>Số chuyến</th>
                    <th style={{ ...th, textAlign: "center" }}>Số kho</th>
                    <th style={{ ...th, textAlign: "right" }}>Tổng ở kho</th>
                    <th style={{ ...th, textAlign: "right" }}>TB/chuyến</th>
                    <th style={th}>Ghé cuối</th>
                  </tr></thead>
                  <tbody>
                    {stats.rows.map((s, i) => (
                      <tr key={i}>
                        <td style={{ ...td, textAlign: "center", color: "var(--ink-4)", fontWeight: 600 }} className="tnum">{i + 1}</td>
                        <td style={td}>{plateCell(s)}</td>
                        <td style={{ ...td, color: "var(--ink-2)" }}>{s.driver || "—"}</td>
                        <td style={{ ...td, textAlign: "center", fontWeight: 700, fontSize: 15, color: "#4f46e5" }} className="tnum">{s.trips}</td>
                        <td style={{ ...td, textAlign: "center" }} className="tnum">{s.warehouses}</td>
                        <td style={{ ...td, textAlign: "right" }} className="tnum">{fmtMin(s.dwellMin)}</td>
                        <td style={{ ...td, textAlign: "right", color: "var(--ink-3)" }} className="tnum">{fmtMin(Math.round((s.dwellMin || 0) / Math.max(1, s.trips)))}</td>
                        <td style={{ ...td, color: "var(--ink-3)" }} className="tnum">{fmtClock(s.lastVisit)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )
          ) : rows.length === 0 ? (
            <div style={{ padding: "40px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5 }}>Không có lượt ghé kho trong khoảng đã chọn. (Hệ thống quét mỗi 5 phút; cần đã ghim tọa độ kho.)</div>
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
                          {plateCell(v)}
                          {v.matched && v.gpsPlate && v.gpsPlate !== v.plate && <div style={{ fontSize: 10.5, color: "var(--ink-4)" }} className="tnum">GPS: {v.gpsPlate}</div>}
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

        {/* phân trang — chỉ ở tab Lượt ghé */}
        {view === "list" && info.lastPage > 1 && (
          <div style={{ maxWidth: 1100, margin: "12px auto 0", display: "flex", alignItems: "center", justifyContent: "center", gap: 6, flexWrap: "wrap" }}>
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
