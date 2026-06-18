import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useEffect } = React;
import { I, fmtVND, fmtNum, fmtShort } from "@trk/lib.jsx";

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

/* Danh sách top (sản lượng theo tuyến/kho) — bar CSS ngang */
function TopList({ title, icon, data, unit }) {
  const top = (data || []).slice(0, 8);
  const max = Math.max(1, ...top.map((d) => d.count));
  return (
    <div style={{ flex: 1, minWidth: 300, background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "16px" }}>
      <div style={{ fontSize: 13.5, fontWeight: 700, marginBottom: 12 }}><i className={"bi " + icon} style={{ color: "var(--accent)" }} /> {title}</div>
      {top.length === 0 ? <div style={{ color: "var(--ink-4)", fontSize: 13 }}>Không có dữ liệu.</div> : (
        <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
          {top.map((d, i) => (
            <div key={i} style={{ display: "flex", alignItems: "center", gap: 10 }}>
              <span className="tnum" style={{ flex: 1, fontSize: 12.5, minWidth: 0, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{d.label}</span>
              <div style={{ width: 120, background: "var(--line-2)", borderRadius: 5, height: 16, overflow: "hidden" }}>
                <div style={{ width: (d.count / max * 100) + "%", height: "100%", background: "var(--accent)", borderRadius: 5, minWidth: 2 }} />
              </div>
              <span className="tnum" style={{ width: 64, textAlign: "right", fontSize: 12.5, fontWeight: 700 }}>{d.count} {unit}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

/* Xu hướng 12 tháng — cột DT vs CP mỗi tháng + đường lợi nhuận (số) */
function TrendChart({ rows }) {
  const max = Math.max(1, ...rows.map((r) => Math.max(r.revenue, r.cost)));
  const H = 130;
  return (
    <div>
      <div style={{ display: "flex", gap: 16, fontSize: 12, marginBottom: 10 }}>
        <span><span style={{ display: "inline-block", width: 10, height: 10, background: "var(--accent)", borderRadius: 2, marginRight: 5 }} />Doanh thu</span>
        <span><span style={{ display: "inline-block", width: 10, height: 10, background: "#dc2626", borderRadius: 2, marginRight: 5 }} />Chi phí</span>
        <span style={{ color: "var(--ink-4)" }}>Lợi nhuận hiện dưới mỗi cột</span>
      </div>
      <div style={{ display: "flex", alignItems: "flex-end", gap: 6, overflowX: "auto", paddingBottom: 4 }}>
        {rows.map((r, i) => (
          <div key={i} style={{ flex: 1, minWidth: 52, display: "flex", flexDirection: "column", alignItems: "center" }}>
            <div style={{ display: "flex", alignItems: "flex-end", gap: 3, height: H, width: "100%", justifyContent: "center" }}>
              <div title={"DT " + fmtVND(r.revenue)} style={{ width: 14, height: Math.max(2, r.revenue / max * H), background: "var(--accent)", borderRadius: "3px 3px 0 0" }} />
              <div title={"CP " + fmtVND(r.cost)} style={{ width: 14, height: Math.max(2, r.cost / max * H), background: "#dc2626", borderRadius: "3px 3px 0 0" }} />
            </div>
            <div className="tnum" style={{ fontSize: 10, fontWeight: 700, marginTop: 4, color: r.profit >= 0 ? "var(--good)" : "#dc2626" }}>{fmtShort(r.profit)}</div>
            <div className="tnum" style={{ fontSize: 10.5, color: "var(--ink-4)" }}>{r.label}</div>
          </div>
        ))}
      </div>
    </div>
  );
}

function ReportApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const init = B.report || {};
  const [year, setYear] = useState(init.year || new Date().getFullYear());
  const [month, setMonth] = useState(init.month || (new Date().getMonth() + 1));
  const [rep, setRep] = useState(init);
  const [loading, setLoading] = useState(false);
  const [trend, setTrend] = useState(null);   // 12 tháng — lazy (có cộng route-pay theo ngày)
  const [trendLoading, setTrendLoading] = useState(false);
  // Tải xu hướng 12 tháng (neo theo tháng đang xem) — bấm mới tải vì hơi nặng.
  const loadTrend = () => {
    setTrendLoading(true);
    window.trkApi("GET", ROUTES.trend + `?year=${year}&month=${month}`)
      .then((r) => { if (r && r.ok) setTrend(r.rows || []); setTrendLoading(false); })
      .catch(() => { setTrendLoading(false); window.trkToast && window.trkToast("Lỗi tải xu hướng", "error"); });
  };
  useEffect(() => { setTrend(null); }, [year, month]);   // đổi tháng → ẩn trend cũ, bấm tải lại

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

          {/* Hiệu suất đội xe */}
          <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "16px" }}>
            <div style={{ fontSize: 13.5, fontWeight: 700, marginBottom: 12 }}><i className="bi bi-truck" style={{ color: "var(--accent)" }} /> Hiệu suất đội xe <span style={{ fontWeight: 400, fontSize: 12, color: "var(--ink-4)" }}>(doanh thu · chi phí · lợi nhuận mỗi xe)</span></div>
            {vehicles.length === 0 ? <div style={{ color: "var(--ink-4)", fontSize: 13 }}>Không có dữ liệu.</div> : (
            <div style={{ overflowX: "auto" }}>
              <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13 }}>
                <thead><tr style={{ color: "var(--ink-4)", fontSize: 11, textTransform: "uppercase", letterSpacing: ".03em" }}>
                  {["Biển số", "Doanh thu", "Chi phí", "Lợi nhuận", "Chuyến", "Cont", "CP/DT", "CP/chuyến"].map((h, i) => (
                    <th key={i} style={{ textAlign: i ? "right" : "left", padding: "6px 9px", borderBottom: "1px solid var(--line)", fontWeight: 700, whiteSpace: "nowrap" }}>{h}</th>
                  ))}
                </tr></thead>
                <tbody>
                  {vehicles.map((v) => (
                    <tr key={v.bks}>
                      <td style={{ padding: "7px 9px", borderBottom: "1px solid var(--line-2)", fontWeight: 700 }} className="tnum">{v.bks}</td>
                      <td style={{ padding: "7px 9px", borderBottom: "1px solid var(--line-2)", textAlign: "right" }} className="tnum">{fmtVND(v.revenue || 0)}</td>
                      <td style={{ padding: "7px 9px", borderBottom: "1px solid var(--line-2)", textAlign: "right" }} className="tnum">{fmtVND(v.cost)}</td>
                      <td style={{ padding: "7px 9px", borderBottom: "1px solid var(--line-2)", textAlign: "right", fontWeight: 700, color: (v.profit || 0) >= 0 ? "var(--good)" : "#dc2626" }} className="tnum">{fmtVND(v.profit || 0)}</td>
                      <td style={{ padding: "7px 9px", borderBottom: "1px solid var(--line-2)", textAlign: "right" }} className="tnum">{v.trips}</td>
                      <td style={{ padding: "7px 9px", borderBottom: "1px solid var(--line-2)", textAlign: "right" }} className="tnum">{v.conts || 0}</td>
                      <td style={{ padding: "7px 9px", borderBottom: "1px solid var(--line-2)", textAlign: "right", color: (v.costRatio || 0) > 100 ? "#dc2626" : "var(--ink-3)" }} className="tnum">{v.revenue ? v.costRatio + "%" : "—"}</td>
                      <td style={{ padding: "7px 9px", borderBottom: "1px solid var(--line-2)", textAlign: "right", color: "var(--ink-4)" }} className="tnum">{fmtVND(v.perTrip)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            )}
          </div>

          {/* Sản lượng theo tuyến / kho */}
          <div style={{ display: "flex", gap: 14, flexWrap: "wrap" }}>
            <TopList title="Sản lượng theo tuyến" icon="bi-signpost-2" data={rep.byRoute || []} unit="chuyến" />
            <TopList title="Sản lượng theo kho" icon="bi-buildings" data={rep.byKho || []} unit="lượt" />
          </div>

          {/* Xu hướng 12 tháng (lazy) */}
          <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "16px" }}>
            <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 12 }}>
              <div style={{ fontSize: 13.5, fontWeight: 700, flex: 1 }}><i className="bi bi-graph-up" style={{ color: "var(--accent)" }} /> Xu hướng 12 tháng <span style={{ fontWeight: 400, fontSize: 12, color: "var(--ink-4)" }}>(đến tháng {String(month).padStart(2, "0")}/{year})</span></div>
              {trend === null && <button type="button" onClick={loadTrend} disabled={trendLoading} style={{ ...btnIcon, width: "auto", padding: "0 12px", fontSize: 12.5, fontWeight: 600 }}>{trendLoading ? "Đang tính…" : "Tải biểu đồ"}</button>}
            </div>
            {trend === null ? <div style={{ color: "var(--ink-4)", fontSize: 12.5 }}>Bấm <b>Tải biểu đồ</b> để xem xu hướng doanh thu – chi phí – lợi nhuận 12 tháng.</div> : <TrendChart rows={trend} />}
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
