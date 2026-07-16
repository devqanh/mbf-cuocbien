import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useEffect } = React;
import { I, fmtVND, fmtNum, fmtShort, useIsMobile } from "@trk/lib.jsx";

/* BÁO CÁO TÀI SẢN — theo từng xe/tài sản trong khoảng THÁNG:
   Chi phí thường (theo Ngày chi) · Chi phí phân bổ (phần/tháng rơi trong kỳ) · Khấu hao (theo ngày).
   Tất cả chỉ tính phần ĐÃ PHÁT SINH (không tính tương lai). */

const T = window.__TRK || {};
const ROUTES = T.routes || {};
const B = T.boot || {};
const api = (m, u) => window.trkApi(m, u);

const ymNow = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`; };
const ymLabel = (ym) => { const p = String(ym || "").split("-"); return p.length === 2 ? `${p[1]}/${p[0]}` : ym; };
const ymSplit = (ym) => { const p = String(ym || "").split("-"); return [p[0] || String(new Date().getFullYear()), p[1] || "01"]; };
const MONTHS = Array.from({ length: 12 }, (_, i) => String(i + 1).padStart(2, "0"));
// NĂM lấy theo dữ liệu thật (boot.years: mới → cũ); fallback năm hiện tại.
const YEARS = (B.years && B.years.length ? B.years : [new Date().getFullYear()]).map(String);

const selBox = { appearance: "none", WebkitAppearance: "none", padding: "7px 24px 7px 10px", fontSize: 13, fontWeight: 700, border: "1px solid var(--line)", borderRadius: 9, background: "#fff", cursor: "pointer", color: "var(--ink)" };
const chev = { position: "absolute", right: 8, top: "50%", transform: "translateY(-50%)", color: "var(--ink-3)", pointerEvents: "none", fontSize: 10 };

/* Chọn THÁNG + NĂM riêng (thay vì 1 select dài liệt kê từng tháng) */
function MonthYear({ value, onChange }) {
  const [y, m] = ymSplit(value);
  const years = YEARS.includes(y) ? YEARS : [y, ...YEARS];   // giữ năm đang chọn dù ngoài danh sách
  return (
    <span style={{ display: "inline-flex", gap: 5 }}>
      <span style={{ position: "relative" }}>
        <select value={m} onChange={(e) => onChange(`${y}-${e.target.value}`)} style={selBox} title="Tháng">
          {MONTHS.map((o) => <option key={o} value={o}>Th {o}</option>)}
        </select>
        <span style={chev}><i className="bi bi-chevron-down" /></span>
      </span>
      <span style={{ position: "relative" }}>
        <select value={y} onChange={(e) => onChange(`${e.target.value}-${m}`)} style={selBox} title="Năm">
          {years.map((o) => <option key={o} value={o}>{o}</option>)}
        </select>
        <span style={chev}><i className="bi bi-chevron-down" /></span>
      </span>
    </span>
  );
}

const COLS = [
  { k: "costNormal", label: "Chi phí thường", color: "#2a6fdb", hint: "Phiếu chi không phân bổ · theo Ngày chi" },
  { k: "costAlloc", label: "Chi phí phân bổ", color: "#9333ea", hint: "Phần phân bổ rơi vào kỳ (Số tiền ÷ số tháng)" },
  { k: "deprec", label: "Khấu hao", color: "#e08600", hint: "Nguyên giá ÷ (30 × số tháng) × số ngày trong kỳ" },
];

function Stat({ label, value, color, hint }) {
  return (
    <div style={{ flex: 1, minWidth: 168, background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "12px 15px" }}>
      <div style={{ fontSize: 11, color: "var(--ink-4)", fontWeight: 700, textTransform: "uppercase", letterSpacing: ".03em" }}>{label}</div>
      <div className="tnum" style={{ fontSize: 20, fontWeight: 800, marginTop: 3, color: color || "var(--ink)" }}>{fmtVND(value)}</div>
      {hint && <div style={{ fontSize: 10.5, color: "var(--ink-4)", marginTop: 3, lineHeight: 1.4 }}>{hint}</div>}
    </div>
  );
}

/* Thanh tỉ lệ 3 nhóm trong 1 dòng xe */
function MiniBar({ row }) {
  const t = row.total || 1;
  return (
    <div style={{ display: "flex", height: 6, borderRadius: 999, overflow: "hidden", background: "var(--line-2)", minWidth: 90 }}>
      {COLS.map((c) => { const w = (row[c.k] || 0) * 100 / t; return w > 0 ? <div key={c.k} title={`${c.label}: ${fmtVND(row[c.k])}`} style={{ width: w + "%", background: c.color }} /> : null; })}
    </div>
  );
}

function AssetReportApp() {
  const isMobile = useIsMobile();
  const [from, setFrom] = useState((B.report && B.report.from) || ymNow());
  const [to, setTo] = useState((B.report && B.report.to) || ymNow());
  const [rep, setRep] = useState(B.report || { rows: [], totals: {}, months: 1 });
  const [loading, setLoading] = useState(false);
  const [open, setOpen] = useState(() => new Set());   // xe đang mở chi tiết
  const [kind, setKind] = useState("all");             // all | vehicle | asset
  const [q, setQ] = useState("");

  const load = (f, t) => {
    setLoading(true);
    api("GET", ROUTES.data + "?from=" + encodeURIComponent(f) + "&to=" + encodeURIComponent(t))
      .then((r) => { if (r && r.ok) setRep(r.report); setLoading(false); })
      .catch(() => setLoading(false));
  };
  const first = React.useRef(true);
  useEffect(() => { if (first.current) { first.current = false; return; } load(from, to); }, [from, to]);

  const toggle = (id) => setOpen((s) => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const rows = (rep.rows || []).filter((r) => (kind === "all" || r.kind === kind)
    && (!q.trim() || (r.plate || "").toLowerCase().includes(q.trim().toLowerCase())));
  const shownTot = rows.reduce((a, r) => ({
    costNormal: a.costNormal + r.costNormal, costAlloc: a.costAlloc + r.costAlloc, deprec: a.deprec + r.deprec, total: a.total + r.total,
  }), { costNormal: 0, costAlloc: 0, deprec: 0, total: 0 });

  const cellR ={ padding: "10px 12px", textAlign: "right", borderBottom: "1px solid var(--line-2)", whiteSpace: "nowrap" };
  const th = (t, al) => <th style={{ textAlign: al || "left", padding: "9px 12px", fontSize: 10.5, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: ".03em", borderBottom: "1px solid var(--line)", whiteSpace: "nowrap", background: "#fafbfc", position: "sticky", top: 0, zIndex: 1 }}>{t}</th>;

  const detail = (row) => (
    <tr key={row.id + "-d"}>
      <td colSpan={6} style={{ padding: 0, background: "#fafbfc", borderBottom: "1px solid var(--line)" }}>
        <div style={{ padding: "12px 14px", display: "grid", gridTemplateColumns: isMobile ? "1fr" : "1fr 1fr 1fr", gap: 12 }}>
          {/* Chi phí thường */}
          <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 10, padding: "10px 12px" }}>
            <div style={{ fontSize: 11.5, fontWeight: 800, color: COLS[0].color, marginBottom: 6 }}>CHI PHÍ THƯỜNG · {fmtVND(row.costNormal)}</div>
            {row.costItems.length === 0 && <div style={{ fontSize: 12, color: "var(--ink-4)" }}>Không phát sinh.</div>}
            {row.costItems.map((it, i) => (
              <div key={i} style={{ display: "flex", justifyContent: "space-between", gap: 8, fontSize: 12.5, padding: "3px 0", borderTop: i ? "1px solid var(--line-2)" : "none" }}>
                <span style={{ color: "var(--ink-2)", minWidth: 0, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                  {it.name}{it.count > 1 ? <span style={{ color: "var(--ink-4)" }}> ×{it.count}</span> : ""}
                  {it.material && <span title="Vật tư" style={{ marginLeft: 5, fontSize: 9.5, fontWeight: 700, color: "#7c5b16", background: "#fbf0d3", padding: "0 5px", borderRadius: 999 }}>VT</span>}
                </span>
                <b className="tnum" style={{ whiteSpace: "nowrap" }}>{fmtNum(it.amount)}</b>
              </div>
            ))}
          </div>
          {/* Chi phí phân bổ */}
          <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 10, padding: "10px 12px" }}>
            <div style={{ fontSize: 11.5, fontWeight: 800, color: COLS[1].color, marginBottom: 6 }}>CHI PHÍ PHÂN BỔ · {fmtVND(row.costAlloc)}</div>
            {row.allocItems.length === 0 && <div style={{ fontSize: 12, color: "var(--ink-4)" }}>Không phát sinh.</div>}
            {row.allocItems.map((it, i) => (
              <div key={i} style={{ fontSize: 12.5, padding: "4px 0", borderTop: i ? "1px solid var(--line-2)" : "none" }}>
                <div style={{ display: "flex", justifyContent: "space-between", gap: 8 }}>
                  <span style={{ color: "var(--ink-2)", minWidth: 0, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{it.name}</span>
                  <b className="tnum" style={{ whiteSpace: "nowrap" }}>{fmtNum(it.inPeriod)}</b>
                </div>
                <div className="tnum" style={{ fontSize: 10.5, color: "var(--ink-4)" }}>
                  {fmtNum(it.amount)} ÷ {it.months} th = {fmtNum(it.perMonth)}/th × {it.monthsInPeriod} th trong kỳ
                </div>
              </div>
            ))}
          </div>
          {/* Khấu hao */}
          <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 10, padding: "10px 12px" }}>
            <div style={{ fontSize: 11.5, fontWeight: 800, color: COLS[2].color, marginBottom: 6 }}>KHẤU HAO · {fmtVND(row.deprec)}</div>
            {row.deprecItems.length === 0 && <div style={{ fontSize: 12, color: "var(--ink-4)" }}>Không phát sinh.</div>}
            {row.deprecItems.map((it, i) => (
              <div key={i} style={{ fontSize: 12.5, padding: "4px 0", borderTop: i ? "1px solid var(--line-2)" : "none" }}>
                <div style={{ display: "flex", justifyContent: "space-between", gap: 8 }}>
                  <span style={{ color: "var(--ink-2)", minWidth: 0, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{it.name}</span>
                  <b className="tnum" style={{ whiteSpace: "nowrap" }}>{fmtNum(it.inPeriod)}</b>
                </div>
                <div className="tnum" style={{ fontSize: 10.5, color: "var(--ink-4)" }}>
                  NG {fmtShort(it.origPrice)} ÷ (30×{it.months}) = {fmtNum(it.perDay)}/ngày × {it.daysInPeriod} ngày
                </div>
              </div>
            ))}
          </div>
        </div>
      </td>
    </tr>
  );

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: isMobile ? "10px 14px" : "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 12, height: isMobile ? "auto" : 58, flexWrap: "wrap", padding: isMobile ? "4px 0" : 0 }}>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center", flexShrink: 0 }}><i className="bi bi-truck-front-fill" /></div>
          <div>
            <div style={{ fontSize: 15.5, fontWeight: 700 }}>Báo cáo tài sản</div>
            <div style={{ fontSize: 11.5, color: "var(--ink-3)" }}>Chi phí · phân bổ · khấu hao theo từng xe / tài sản</div>
          </div>
          <div style={{ flex: 1 }} />
          <span style={{ fontSize: 12.5, color: "var(--ink-3)", fontWeight: 600 }}>Từ tháng</span>
          <MonthYear value={from} onChange={setFrom} />
          <span style={{ fontSize: 12.5, color: "var(--ink-3)", fontWeight: 600 }}>đến</span>
          <MonthYear value={to} onChange={setTo} />
          {loading && <span style={{ fontSize: 12, color: "var(--ink-4)" }}><i className="bi bi-arrow-repeat" style={{ animation: "trk-spin .7s linear infinite" }} /> Đang tính…</span>}
        </div>
        <div style={{ display: "flex", gap: 7, alignItems: "center", flexWrap: "wrap", padding: "0 0 12px" }}>
          {[["all", "Tất cả"], ["vehicle", "Xe"], ["asset", "Tài sản"]].map(([k, l]) => (
            <button key={k} type="button" onClick={() => setKind(k)}
              style={{ padding: "6px 11px", fontSize: 12.5, fontWeight: 600, borderRadius: 8, cursor: "pointer",
                border: "1px solid " + (kind === k ? "var(--accent)" : "var(--line)"), background: kind === k ? "var(--accent-weak-2)" : "#fff", color: kind === k ? "var(--accent)" : "var(--ink-3)" }}>{l}</button>
          ))}
          <div style={{ position: "relative", width: isMobile ? "100%" : 220 }}>
            <i className="bi bi-search" style={{ position: "absolute", left: 11, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 12 }} />
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Tìm biển số / tên tài sản…"
              style={{ width: "100%", padding: "7px 12px 7px 30px", fontSize: 12.5, border: "1px solid var(--line)", borderRadius: 9, outline: "none" }} />
          </div>
          <div style={{ flex: 1 }} />
          <span style={{ fontSize: 12, color: "var(--ink-4)" }}>Kỳ <b style={{ color: "var(--ink-3)" }}>{ymLabel(rep.from)} → {ymLabel(rep.to)}</b> · {rep.months} tháng · {rows.length} xe</span>
        </div>
      </header>

      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: isMobile ? "12px 12px 24px" : "16px 22px 24px" }}>
        <div style={{ maxWidth: 1180, margin: "0 auto", display: "flex", flexDirection: "column", gap: 14 }}>
          <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
            {COLS.map((c) => <Stat key={c.k} label={c.label} value={shownTot[c.k]} color={c.color} hint={c.hint} />)}
            <Stat label="Tổng cộng" value={shownTot.total} />
          </div>

          <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, overflow: "hidden" }}>
            <div style={{ overflowX: "auto" }}>
              <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13, minWidth: 820 }}>
                <thead><tr>
                  {th("Xe / Tài sản")}{th("Cơ cấu")}
                  {th("Chi phí thường", "right")}{th("Chi phí phân bổ", "right")}{th("Khấu hao", "right")}{th("Tổng", "right")}
                </tr></thead>
                <tbody>
                  {rows.length === 0 && <tr><td colSpan={6} style={{ padding: 34, textAlign: "center", color: "var(--ink-4)" }}>Không có xe/tài sản nào phát sinh trong kỳ.</td></tr>}
                  {rows.map((r) => (
                    <React.Fragment key={r.id}>
                      <tr onClick={() => toggle(r.id)} style={{ cursor: "pointer", background: open.has(r.id) ? "var(--accent-weak-2)" : "transparent" }}>
                        <td style={{ padding: "10px 12px", borderBottom: "1px solid var(--line-2)" }}>
                          <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                            <i className={"bi " + (open.has(r.id) ? "bi-chevron-down" : "bi-chevron-right")} style={{ fontSize: 11, color: "var(--ink-4)" }} />
                            <b className="tnum">{r.plate}</b>
                            <span style={{ fontSize: 10, fontWeight: 700, color: r.kind === "asset" ? "#7c5b16" : "var(--accent)", background: r.kind === "asset" ? "#fbf0d3" : "var(--accent-weak)", padding: "1px 7px", borderRadius: 999 }}>{r.kind === "asset" ? "Tài sản" : "Xe"}</span>
                            <a href={ROUTES.fleet + "#" + r.hashid + "/cost"} onClick={(e) => e.stopPropagation()} title="Mở hồ sơ" style={{ color: "var(--accent)", fontSize: 11 }}><i className="bi bi-box-arrow-up-right" /></a>
                          </div>
                        </td>
                        <td style={{ padding: "10px 12px", borderBottom: "1px solid var(--line-2)", width: 130 }}><MiniBar row={r} /></td>
                        <td className="tnum" style={{ ...cellR, color: COLS[0].color, fontWeight: 600 }}>{r.costNormal ? fmtNum(r.costNormal) : "—"}</td>
                        <td className="tnum" style={{ ...cellR, color: COLS[1].color, fontWeight: 600 }}>{r.costAlloc ? fmtNum(r.costAlloc) : "—"}</td>
                        <td className="tnum" style={{ ...cellR, color: COLS[2].color, fontWeight: 600 }}>{r.deprec ? fmtNum(r.deprec) : "—"}</td>
                        <td className="tnum" style={{ ...cellR, fontWeight: 800 }}>{fmtNum(r.total)}</td>
                      </tr>
                      {open.has(r.id) && detail(r)}
                    </React.Fragment>
                  ))}
                </tbody>
                {rows.length > 0 && (
                  <tfoot><tr style={{ background: "#fafbfc" }}>
                    <td colSpan={2} style={{ padding: "11px 12px", fontWeight: 800, borderTop: "2px solid var(--line)" }}>TỔNG CỘNG · {rows.length} xe</td>
                    {COLS.map((c) => <td key={c.k} className="tnum" style={{ ...cellR, borderTop: "2px solid var(--line)", fontWeight: 800, color: c.color }}>{fmtNum(shownTot[c.k])}</td>)}
                    <td className="tnum" style={{ ...cellR, borderTop: "2px solid var(--line)", fontWeight: 800, fontSize: 14 }}>{fmtNum(shownTot.total)}</td>
                  </tr></tfoot>
                )}
              </table>
            </div>
          </div>

          <div style={{ fontSize: 11.5, color: "var(--ink-4)", lineHeight: 1.6 }}>
            <i className="bi bi-info-circle" /> <b style={{ color: "var(--ink-3)" }}>Chi phí thường</b> = phiếu chi không phân bổ, tính theo <b>Ngày chi</b> rơi trong kỳ (bỏ phiếu đã hủy).
            <b style={{ color: "var(--ink-3)" }}> Chi phí phân bổ</b> = (Số tiền ÷ số tháng) × số tháng phân bổ rơi trong kỳ, tính từ tháng Ngày chi.
            <b style={{ color: "var(--ink-3)" }}> Khấu hao</b> = Nguyên giá ÷ (30 × số tháng) × số ngày của kỳ khấu hao rơi trong kỳ.
            Tất cả chỉ tính phần <b>đã phát sinh đến hôm nay</b> — tháng/ngày tương lai không cộng. Bấm 1 dòng để xem chi tiết.
          </div>
        </div>
      </div>
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<AssetReportApp />);
