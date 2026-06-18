import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState } = React;
import { I, fmtVND, fmtNum } from "@trk/lib.jsx";

const PALETTE = ["#2a6fdb", "#1f8a5b", "#e08600", "#9333ea", "#dc2626", "#0891b2", "#65a30d", "#db2777", "#64748b"];

/* Donut SVG không cần thư viện — data: [{label,value,color}] */
function Donut({ data, size = 190, thick = 28 }) {
  const total = data.reduce((s, d) => s + d.value, 0) || 1;
  const r = (size - thick) / 2; const C = 2 * Math.PI * r;
  let acc = 0;
  return (
    <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} style={{ flexShrink: 0 }}>
      <g transform={`translate(${size / 2},${size / 2}) rotate(-90)`}>
        <circle r={r} fill="none" stroke="var(--line-2)" strokeWidth={thick} />
        {data.map((d, i) => {
          const len = (d.value / total) * C;
          const el = <circle key={i} r={r} fill="none" stroke={d.color} strokeWidth={thick} strokeDasharray={`${len} ${C - len}`} strokeDashoffset={-acc} strokeLinecap="butt" />;
          acc += len; return el;
        })}
      </g>
    </svg>
  );
}

const KPI = ({ label, value, sub, color }) => (
  <div style={{ flex: 1, minWidth: 150, background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "13px 15px" }}>
    <div style={{ fontSize: 11.5, color: "var(--ink-4)", fontWeight: 600, textTransform: "uppercase", letterSpacing: ".03em" }}>{label}</div>
    <div className="tnum" style={{ fontSize: 21, fontWeight: 800, marginTop: 3, color: color || "var(--ink-1)" }}>{value}</div>
    {sub ? <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 2 }}>{sub}</div> : null}
  </div>
);

function ReportApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const init = B.report || {};
  const [year, setYear] = useState(init.year || new Date().getFullYear());
  const [month, setMonth] = useState(init.month || (new Date().getMonth() + 1));
  const [rep, setRep] = useState(init);
  const [loading, setLoading] = useState(false);

  const loadMonth = (y, m) => {
    setLoading(true); setYear(y); setMonth(m);
    window.trkApi("GET", ROUTES.data + `?year=${y}&month=${m}`)
      .then((r) => { if (r && r.ok) setRep(r.report); setLoading(false); })
      .catch(() => { setLoading(false); window.trkToast && window.trkToast("Lỗi tải báo cáo", "error"); });
  };
  const shift = (n) => { let m = month + n, y = year; if (m < 1) { m = 12; y--; } if (m > 12) { m = 1; y++; } loadMonth(y, m); };

  const cats = (rep.costByCategory || []);
  const donutData = cats.slice(0, 8).map((c, i) => ({ label: c.label, value: c.amount, color: PALETTE[i % PALETTE.length] }));
  if (cats.length > 8) donutData.push({ label: "Khác", value: cats.slice(8).reduce((s, c) => s + c.amount, 0), color: "#cbd5e1" });
  const vehicles = rep.costByVehicle || [];
  const maxVeh = Math.max(1, ...vehicles.map((v) => v.cost));
  const profit = rep.profit || 0;

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: "0 22px", flexShrink: 0 }}>
        <div className="trk-head" style={{ display: "flex", alignItems: "center", gap: 12, height: 58 }}>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center", flexShrink: 0 }}><i className="bi bi-bar-chart-line-fill" /></div>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 15.5, fontWeight: 700, lineHeight: 1.1 }}>Báo cáo chi phí công ty</div>
            <div style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Tổng quan doanh thu · chi phí · lợi nhuận theo tháng</div>
          </div>
          <div style={{ display: "inline-flex", alignItems: "center", gap: 6 }}>
            <button type="button" onClick={() => shift(-1)} style={btnIcon}>‹</button>
            <span className="tnum" style={{ fontSize: 14, fontWeight: 700, minWidth: 90, textAlign: "center" }}>Tháng {String(month).padStart(2, "0")}/{year}</span>
            <button type="button" onClick={() => shift(1)} style={btnIcon}>›</button>
            <button type="button" onClick={() => { const d = new Date(); loadMonth(d.getFullYear(), d.getMonth() + 1); }} style={{ ...btnIcon, width: "auto", padding: "0 12px", fontSize: 13, fontWeight: 600 }}>Tháng này</button>
          </div>
        </div>
      </header>

      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: "18px 22px 40px", opacity: loading ? 0.55 : 1 }}>
        <div style={{ maxWidth: 1080, margin: "0 auto", display: "flex", flexDirection: "column", gap: 14 }}>
          {/* P&L KPI */}
          <div style={{ display: "flex", gap: 12, flexWrap: "wrap" }}>
            <KPI label="Doanh thu" value={fmtVND(rep.revenue || 0)} color="var(--accent)" />
            <KPI label="Tổng chi phí" value={fmtVND(rep.totalCost || 0)} color="#dc2626" />
            <KPI label="Lợi nhuận gộp" value={fmtVND(profit)} sub={`Biên LN ${rep.margin || 0}%`} color={profit >= 0 ? "var(--good)" : "#dc2626"} />
            <KPI label="Sản lượng" value={`${rep.trips || 0} chuyến`} sub={`${rep.conts || 0} cont · ${rep.vehicles || 0} xe`} />
          </div>

          {/* Cơ cấu chi phí: donut + bảng */}
          <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "16px" }}>
            <div style={{ fontSize: 13.5, fontWeight: 700, marginBottom: 12 }}><i className="bi bi-pie-chart-fill" style={{ color: "var(--accent)" }} /> Cơ cấu chi phí theo loại</div>
            {cats.length === 0 ? <div style={{ color: "var(--ink-4)", fontSize: 13, padding: "10px 0" }}>Không có chi phí trong tháng.</div> : (
            <div style={{ display: "flex", gap: 26, flexWrap: "wrap", alignItems: "center" }}>
              <div style={{ position: "relative" }}>
                <Donut data={donutData} />
                <div style={{ position: "absolute", inset: 0, display: "grid", placeItems: "center", textAlign: "center" }}>
                  <div><div style={{ fontSize: 10.5, color: "var(--ink-4)" }}>Tổng CP</div><div className="tnum" style={{ fontSize: 14, fontWeight: 800 }}>{fmtVND(rep.totalCost || 0)}</div></div>
                </div>
              </div>
              <div style={{ flex: 1, minWidth: 280 }}>
                {donutData.map((d, i) => (
                  <div key={i} style={{ display: "flex", alignItems: "center", gap: 9, padding: "5px 0", borderBottom: i < donutData.length - 1 ? "1px solid var(--line-2)" : "none" }}>
                    <span style={{ width: 11, height: 11, borderRadius: 3, background: d.color, flexShrink: 0 }} />
                    <span style={{ flex: 1, fontSize: 13 }}>{d.label}</span>
                    <span className="tnum" style={{ fontSize: 13, fontWeight: 600 }}>{fmtVND(d.value)}</span>
                    <span className="tnum" style={{ fontSize: 11.5, color: "var(--ink-4)", width: 46, textAlign: "right" }}>{rep.totalCost ? Math.round(d.value * 100 / rep.totalCost) : 0}%</span>
                  </div>
                ))}
              </div>
            </div>
            )}
          </div>

          {/* Chi phí theo xe */}
          <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "16px" }}>
            <div style={{ fontSize: 13.5, fontWeight: 700, marginBottom: 12 }}><i className="bi bi-truck" style={{ color: "var(--accent)" }} /> Chi phí theo xe <span style={{ fontWeight: 400, fontSize: 12, color: "var(--ink-4)" }}>(và chi phí/chuyến)</span></div>
            {vehicles.length === 0 ? <div style={{ color: "var(--ink-4)", fontSize: 13 }}>Không có dữ liệu.</div> : (
            <div style={{ display: "flex", flexDirection: "column", gap: 9 }}>
              {vehicles.map((v) => (
                <div key={v.bks} style={{ display: "flex", alignItems: "center", gap: 10 }}>
                  <span className="tnum" style={{ width: 92, fontSize: 12.5, fontWeight: 700, flexShrink: 0 }}>{v.bks}</span>
                  <div style={{ flex: 1, background: "var(--line-2)", borderRadius: 6, height: 22, position: "relative", overflow: "hidden" }}>
                    <div style={{ width: (v.cost / maxVeh * 100) + "%", height: "100%", background: "var(--accent)", borderRadius: 6, minWidth: 2 }} />
                  </div>
                  <span className="tnum" style={{ width: 110, textAlign: "right", fontSize: 13, fontWeight: 700 }}>{fmtVND(v.cost)}</span>
                  <span className="tnum" style={{ width: 120, textAlign: "right", fontSize: 11.5, color: "var(--ink-4)" }}>{fmtVND(v.perTrip)}/chuyến</span>
                </div>
              ))}
            </div>
            )}
          </div>

          <div style={{ fontSize: 11.5, color: "var(--ink-4)", display: "flex", gap: 7, alignItems: "flex-start" }}>
            <i className="bi bi-info-circle" style={{ marginTop: 1 }} />
            <span>Doanh thu = doanh thu lô có giờ xe ra trong tháng. Chi phí gồm: lương & vận hành lái xe (dầu/cầu đường/trợ cấp…), chi phí xe (sửa chữa/khấu hao theo ngày chi) và chi phí lô hàng (không tính chi hộ khách). Chốt số ở Lộ trình để đóng băng tránh lệch khi sửa cấu hình.</span>
          </div>
        </div>
      </div>
    </div>
  );
}

const btnIcon = { width: 32, height: 32, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: "var(--ink-2)", cursor: "pointer", fontSize: 16 };

createRoot(document.getElementById("trk-root")).render(<ReportApp />);
