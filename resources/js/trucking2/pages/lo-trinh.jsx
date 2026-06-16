import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useEffect, useRef } = React;
import { I, useIsMobile, DateField } from "@trk/lib.jsx";

const z = (n) => String(n).padStart(2, "0");
const toYmd = (d) => `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}`;

// self = lấy chính cont này ra · none = ra xe không kéo cont (chạy xe không) · other = kéo cont khác ra
const MODE = {
  self:  { label: "Kéo cont ra",     color: "#1f8a5b", bg: "var(--good-weak)", icon: "bi-box-arrow-right" },
  none:  { label: "Ra xe không cont", color: "#8a94a6", bg: "#eef0f3",          icon: "bi-truck" },
  other: { label: "Kéo cont khác",   color: "#e08600", bg: "#fcf3e2",          icon: "bi-arrow-left-right" },
};
// điểm hành trình: nơi lấy → kho → nơi hạ
const PT = {
  pickup: { color: "#2a6fdb", icon: "bi-box-arrow-up-right" },
  kho:    { color: "#4f46e5", icon: "bi-buildings-fill" },
  drop:   { color: "#1f8a5b", icon: "bi-geo-alt-fill" },
};

// Câu mô tả hành động của 1 hoạt động trong ngày
function actionNode(l) {
  if (l.mode === "none") return l.from
    ? <>Vào <span className="tnum">{l.from}</span> <span style={{ color: "var(--ink-4)", fontWeight: 400 }}>— ra xe không kéo cont</span></>
    : <>Ra xe <span style={{ color: "var(--ink-3)", fontWeight: 500 }}>— chạy xe không (chưa kéo cont)</span></>;
  if (l.mode === "other") return <>Kéo cont khác <span className="tnum">{l.refCont || "—"}</span> ra <span style={{ color: "var(--ink-4)", fontWeight: 400 }}>· cont {l.cont || "—"} còn chờ</span></>;
  return <>Lấy cont <span className="tnum">{l.cont || "—"}</span> ra</>;
}

// Chuỗi điểm hành trình (Nơi lấy → Kho → Nơi hạ) với mũi tên
function PointChain({ points }) {
  if (!points || !points.length) return <span style={{ color: "var(--ink-4)" }}>—</span>;
  return (
    <span style={{ display: "inline-flex", alignItems: "center", gap: 4, flexWrap: "wrap" }}>
      {points.map((p, i) => { const c = PT[p.kind] || PT.kho; return (
        <React.Fragment key={i}>
          {i > 0 && <i className="bi bi-arrow-right" style={{ color: "var(--ink-4)", fontSize: 11 }} />}
          <span style={{ display: "inline-flex", alignItems: "center", gap: 4, fontSize: 12, fontWeight: 600, color: c.color, background: "var(--bg)", border: "1px solid var(--line-2)", borderRadius: 999, padding: "2px 9px" }}>
            <i className={"bi " + c.icon} style={{ fontSize: 11 }} />{p.label}
          </span>
        </React.Fragment>
      ); })}
    </span>
  );
}

// 1 hoạt động = 1 nút trên đường rail dọc (nối liền nhau thành lộ trình cả ngày)
function TripNode({ l, isFirst, isLast, bks, href }) {
  const m = MODE[l.mode] || MODE.self;
  // Xe không kéo cont → chỉ hiện NƠI LẤY (chỗ xe vào), không vẽ tới nơi hạ vì chưa giao cont nào.
  const pts = l.mode === "none" ? (l.points || []).filter((p) => p.kind === "pickup") : l.points;
  return (
    <a href={href} title="Xem lô hàng"
      style={{ display: "flex", alignItems: "stretch", gap: 12, textDecoration: "none", color: "inherit" }}
      onMouseEnter={(e) => (e.currentTarget.querySelector(".trk-trip-body").style.background = "var(--accent-weak-2)")}
      onMouseLeave={(e) => (e.currentTarget.querySelector(".trk-trip-body").style.background = "transparent")}>
      {/* RAIL: đường dọc + chấm mốc */}
      <div style={{ position: "relative", width: 30, flexShrink: 0 }}>
        <div style={{ position: "absolute", left: 14, width: 2, background: "var(--line-2)", top: isFirst ? 22 : 0, bottom: isLast ? "calc(100% - 22px)" : 0 }} />
        <div style={{ position: "absolute", left: 7, top: 15, width: 16, height: 16, borderRadius: "50%", background: m.color, border: "3px solid #fff", boxShadow: "0 0 0 1.5px " + m.color, display: "grid", placeItems: "center" }}>
          <i className={"bi " + m.icon} style={{ fontSize: 8, color: "#fff" }} />
        </div>
      </div>
      {/* NỘI DUNG */}
      <div className="trk-trip-body" style={{ flex: 1, minWidth: 0, padding: "10px 12px", borderRadius: 10, marginBottom: 2, transition: "background .12s" }}>
        <div style={{ display: "flex", alignItems: "baseline", gap: 10, flexWrap: "wrap" }}>
          <span className="tnum" style={{ fontWeight: 700, fontSize: 14, color: m.color }}>{l.timeLabel}</span>
          <span style={{ fontSize: 13.5, fontWeight: 600 }}>{actionNode(l)}</span>
        </div>
        <div style={{ marginTop: 6 }}><PointChain points={pts} /></div>
        {/* CHI TIẾT: xe đưa cont VÀO + xe đưa cont (số nào) RA */}
        <div style={{ marginTop: 7, display: "flex", flexDirection: "column", gap: 3, fontSize: 11.5, lineHeight: 1.5 }}>
          <div style={{ color: "var(--ink-3)" }}>
            <i className="bi bi-box-arrow-in-down" style={{ color: "#2a6fdb" }} /> <span style={{ color: "var(--ink-4)" }}>Xe vào:</span>{" "}
            {l.mode === "none"
              ? <><b className="tnum">{l.bks}</b> đã vào nơi lấy <b className="tnum">{l.from || "—"}</b>{l.gioDenLabel ? <> lúc <b className="tnum">{l.gioDenLabel}</b></> : <span style={{ color: "var(--ink-4)" }}> (chưa có giờ xe đến)</span>}</>
              : <><b className="tnum">{l.bks}</b> đưa cont <b className="tnum">{l.cont || "—"}</b> vào{l.gioDenLabel && <span style={{ color: "var(--ink-4)" }}> · vào kho {l.gioDenLabel}</span>}</>}
          </div>
          <div style={{ color: "var(--ink-3)" }}>
            <i className="bi bi-box-arrow-up" style={{ color: m.color }} /> <span style={{ color: "var(--ink-4)" }}>Xe ra:</span>{" "}
            {l.mode === "none"
              ? <><b className="tnum">{l.bks}</b> ra <span style={{ color: "var(--ink-4)" }}>không kéo cont — cont {l.cont || "—"} vẫn chưa ra</span> · <b className="tnum">{l.timeLabel}</b></>
              : l.mode === "other"
                ? <><b className="tnum">{l.bks}</b> kéo cont khác <b className="tnum">{l.refCont || "—"}</b> ra · <b className="tnum">{l.timeLabel}</b>{l.refBksVao && <span style={{ color: "var(--ink-4)" }}> (cont {l.refCont} do xe {l.refBksVao} đưa vào)</span>}</>
                : <><b className="tnum">{(l.bksRa && l.bksRa !== l.bks) ? l.bksRa : l.bks}</b> đưa cont <b className="tnum">{l.cont || "—"}</b> ra · <b className="tnum">{l.timeLabel}</b>{(l.bksRa && l.bksRa !== l.bks) && <span style={{ color: "var(--warn)" }}> (khác xe vào {l.bks})</span>}</>}
          </div>
        </div>
        {(l.customer || l.booking) && <div style={{ fontSize: 11, color: "var(--ink-4)", marginTop: 6 }}>{l.customer}{l.booking ? <span className="tnum"> · {l.booking}</span> : null}</div>}
      </div>
    </a>
  );
}

function LoTrinhApp() {
  const isMobile = useIsMobile();
  const T = window.__TRK || {}; const ROUTES = T.routes || {};
  const api = (m, u) => window.trkApi(m, u);

  const [date, setDate] = useState(toYmd(new Date()));
  const [data, setData] = useState(null);   // null = đang tải
  const reqId = useRef(0);

  const load = () => {
    const my = ++reqId.current; setData(null);
    api("GET", ROUTES.data + "?date=" + encodeURIComponent(date)).then((r) => {
      if (my !== reqId.current) return;
      setData(r && r.ok ? r : { trucks: [], totalLegs: 0, start: 0, end: 0 });
    }).catch(() => { if (my === reqId.current) setData({ trucks: [], totalLegs: 0 }); });
  };
  useEffect(() => { load(); }, [date]);

  const shiftDay = (n) => { const d = new Date(date + "T12:00:00"); d.setDate(d.getDate() + n); setDate(toYmd(d)); };
  const trucks = data?.trucks || [];

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: isMobile ? "10px 14px" : "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 12, height: isMobile ? "auto" : 58, flexWrap: "wrap" }}>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center", flexShrink: 0 }}><i className="bi bi-signpost-split-fill" /></div>
          <div>
            <div style={{ fontSize: 15.5, fontWeight: 700 }}>Lộ trình lái xe trong ngày</div>
            <div style={{ fontSize: 11.5, color: "var(--ink-3)" }}>
              {data ? <>Ngày vận hành <b>{data.startLabel} 08:00</b> → <b>{data.endLabel} 08:00</b> · {trucks.length} xe · {data.totalLegs} hoạt động</> : "Đang tải…"}
            </div>
          </div>
          <div style={{ flex: 1 }} />
          <div style={{ display: "inline-flex", alignItems: "center", gap: 6 }}>
            <button type="button" onClick={() => shiftDay(-1)} title="Ngày trước" style={btnIcon}>‹</button>
            <div style={{ width: 150 }}><DateField value={date} onChange={setDate} /></div>
            <button type="button" onClick={() => shiftDay(1)} title="Ngày sau" style={btnIcon}>›</button>
            <button type="button" onClick={() => setDate(toYmd(new Date()))} style={{ ...btnIcon, width: "auto", padding: "0 12px", fontSize: 13, fontWeight: 600 }}>Hôm nay</button>
          </div>
        </div>
      </header>

      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: isMobile ? "12px 12px 24px" : "16px 22px 24px" }}>
        <div style={{ maxWidth: 1040, margin: "0 auto" }}>
          {!data ? (
            <div style={{ padding: "40px", textAlign: "center", color: "var(--ink-4)" }}><i className="bi bi-arrow-repeat" style={{ animation: "trk-spin .7s linear infinite" }} /> Đang tải…</div>
          ) : trucks.length === 0 ? (
            <div style={{ padding: "44px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5, background: "#fff", border: "1px solid var(--line)", borderRadius: 12 }}>
              Không có xe nào hoạt động trong ngày này (theo giờ xe ra). Chọn ngày khác hoặc kiểm tra giờ xe ra của lô.
            </div>
          ) : (
            <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
              {trucks.map((tr) => (
                <div key={tr.bks} style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, overflow: "hidden" }}>
                  <div style={{ display: "flex", alignItems: "center", gap: 10, padding: "11px 14px", borderBottom: "1px solid var(--line-2)", background: "#fafbfc" }}>
                    <i className="bi bi-truck-front-fill" style={{ color: "var(--accent)" }} />
                    <span className="tnum" style={{ fontWeight: 700, fontSize: 15 }}>{tr.bks}</span>
                    {tr.matched
                      ? <span style={{ fontSize: 10.5, fontWeight: 700, color: "var(--good)", background: "var(--good-weak)", padding: "1px 7px", borderRadius: 999 }}>✓ {tr.type === "Ngoài" ? "Xe ngoài" : "Xe MBF"}</span>
                      : <span style={{ fontSize: 10.5, color: "var(--ink-4)" }}>(ngoài hệ thống)</span>}
                    <span style={{ flex: 1 }} />
                    <span style={{ fontSize: 12, color: "var(--ink-3)", fontWeight: 600 }}>{tr.legs.length} hoạt động</span>
                  </div>
                  {/* LỘ TRÌNH 1 NGÀY: timeline dọc nối liền các hoạt động */}
                  <div style={{ padding: "8px 12px 10px" }}>
                    {tr.legs.map((l, i) => (
                      <TripNode key={i} l={l} isFirst={i === 0} isLast={i === tr.legs.length - 1} bks={tr.bks} href={ROUTES.shipment} />
                    ))}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

const btnIcon = { width: 32, height: 32, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 9, background: "#fff", cursor: "pointer", fontSize: 16, color: "var(--ink-2)", flexShrink: 0 };

createRoot(document.getElementById("trk-root")).render(<LoTrinhApp />);
