import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";
import { useIsMobile, fmtDate, DateField } from "@trk/lib.jsx";
import { card, CardTitle, KPI, Empty } from "@trk/components/report-ui.jsx";

const { useState, useEffect, useRef, useMemo } = React;

/* BÁO CÁO LÁI XE — số chuyến HOÀN THÀNH theo lái xe trong khoảng ngày vận hành (08:00 → 08:00), dựng từ Lộ trình.
   Lái = lái mặc định của xe (Cài đặt › Biển số xe); xe MBF chưa gắn lái → cảnh báo nhắc gắn. Xe ngoài không tính. */

const T = window.__TRK || {};
const ROUTES = T.routes || {};
const B = T.boot || {};

const z = (n) => String(n).padStart(2, "0");
const ymd = (d) => `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}`;
const PRESETS = [
  ["Tháng này", () => { const d = new Date(); return [ymd(new Date(d.getFullYear(), d.getMonth(), 1)), ymd(d)]; }],
  ["Tháng trước", () => { const d = new Date(); return [ymd(new Date(d.getFullYear(), d.getMonth() - 1, 1)), ymd(new Date(d.getFullYear(), d.getMonth(), 0))]; }],
  ["7 ngày", () => { const d = new Date(); return [ymd(new Date(d.getFullYear(), d.getMonth(), d.getDate() - 6)), ymd(d)]; }],
];
const MODE_LABEL = { self: "Kéo cont ra", other: "Kéo cont khác ra", none: "Không kéo cont" };
const MODE_COLOR = { self: "#2563eb", other: "#7c3aed", none: "#94a3b8" };
const routeHref = (date, bks) => (ROUTES.loTrinh || "") + "?date=" + date + "&bks=" + encodeURIComponent(bks);

const btn = { display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 11px", fontSize: 12.5, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: "var(--ink-2)", cursor: "pointer", textDecoration: "none" };

/* Thanh 3 màu: kéo cont ra · kéo cont khác ra · không kéo cont */
function ModeBar({ r, max }) {
  const w = max ? (r.trips * 100) / max : 0;
  return (
    <div style={{ height: 7, borderRadius: 4, background: "#f1f2f4", overflow: "hidden", minWidth: 60 }}>
      <div style={{ display: "flex", width: w + "%", height: "100%" }}>
        {["self", "other", "none"].map((m) => r[m] > 0 && <div key={m} style={{ flex: r[m], background: MODE_COLOR[m] }} />)}
      </div>
    </div>
  );
}

/* Sổ theo ngày của 1 lái / 1 xe: ngày · xe · số chuyến · cont · link Lộ trình */
function ContChips({ conts }) {
  return (
    <div style={{ display: "flex", flexWrap: "wrap", gap: 4 }}>
      {conts.map((c, j) => (
        <span key={j} title={MODE_LABEL[c.mode]} style={{ fontSize: 11.5, padding: "1px 7px", borderRadius: 6, border: "1px solid " + MODE_COLOR[c.mode] + "55", color: c.mode === "none" ? "var(--ink-3)" : MODE_COLOR[c.mode], background: "#fff", whiteSpace: "nowrap" }}>
          {c.mode === "none" ? "Ra không cont" : (c.cont || "(chưa có số cont)")} · {c.time}
        </span>
      ))}
    </div>
  );
}

const otherPayDriver = (l, driverName) => driverName && l.payDriver && l.payDriver.trim().toLowerCase() !== driverName.trim().toLowerCase();

function DayLines({ lines, driverName, isMobile }) {
  if (!lines.length) return <Empty>Không chạy chuyến nào trong kỳ.</Empty>;
  // Điện thoại: mỗi ngày 1 thẻ (bảng 5 cột không vừa màn hình)
  if (isMobile) return (
    <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
      {lines.map((l, i) => (
        <div key={i} style={{ border: "1px solid var(--line)", borderRadius: 9, padding: "8px 10px", background: "#fff" }}>
          <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 5, fontSize: 12.5 }}>
            <b>{fmtDate(l.date)}</b><span style={{ color: "var(--ink-3)" }}>{l.bks}</span>
            <span className="tnum" style={{ fontWeight: 700 }}>· {l.trips} chuyến</span>
            <div style={{ flex: 1 }} />
            <a href={routeHref(l.date, l.bks)} target="_blank" rel="noopener" style={{ fontSize: 12, color: "var(--accent)" }}>Lộ trình <i className="bi bi-box-arrow-up-right" /></a>
          </div>
          {otherPayDriver(l, driverName) && <div style={{ fontSize: 11, color: "#b45309", marginBottom: 4 }}><i className="bi bi-exclamation-triangle" /> lái nhận: {l.payDriver}</div>}
          <ContChips conts={l.conts} />
        </div>
      ))}
    </div>
  );
  return (
    <div style={{ overflowX: "auto" }}>
      <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12.5 }}>
        <thead>
          <tr style={{ color: "var(--ink-4)", textAlign: "left" }}>
            <th style={{ padding: "6px 8px", fontWeight: 600 }}>Ngày</th>
            <th style={{ padding: "6px 8px", fontWeight: 600 }}>Xe</th>
            <th style={{ padding: "6px 8px", fontWeight: 600, textAlign: "right" }}>Chuyến</th>
            <th style={{ padding: "6px 8px", fontWeight: 600 }}>Cont (giờ ra)</th>
            <th style={{ padding: "6px 8px" }} />
          </tr>
        </thead>
        <tbody>
          {lines.map((l, i) => (
            <tr key={i} style={{ borderTop: "1px solid var(--line)" }}>
              <td style={{ padding: "6px 8px", whiteSpace: "nowrap" }}>{fmtDate(l.date)}</td>
              <td style={{ padding: "6px 8px", whiteSpace: "nowrap", fontWeight: 600 }}>
                {l.bks}
                {otherPayDriver(l, driverName) && (
                  <div style={{ fontSize: 11, fontWeight: 500, color: "#b45309" }} title="Lái nhận tiền ở Lộ trình khác lái mặc định của xe">
                    <i className="bi bi-exclamation-triangle" /> lái nhận: {l.payDriver}
                  </div>
                )}
              </td>
              <td className="tnum" style={{ padding: "6px 8px", textAlign: "right", fontWeight: 700 }}>{l.trips}</td>
              <td style={{ padding: "6px 8px" }}><ContChips conts={l.conts} /></td>
              <td style={{ padding: "6px 8px", textAlign: "right", whiteSpace: "nowrap" }}>
                <a href={routeHref(l.date, l.bks)} target="_blank" rel="noopener" style={{ fontSize: 12, color: "var(--accent)" }}>Lộ trình <i className="bi bi-box-arrow-up-right" /></a>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/* Cảnh báo xe MBF chưa gắn lái xe — nhắc nhân viên gắn ở Cài đặt › Biển số xe */
function NoDriverWarning({ rep, isMobile }) {
  const [open, setOpen] = useState(false);
  const [allChips, setAllChips] = useState(false);
  const CHIPS = isMobile ? 6 : 18;
  const list = rep.noDriver || [];
  const idle = rep.idleNoDriver || [];
  if (!list.length && !idle.length) return null;
  const hasTrips = list.length > 0;
  return (
    <div style={{ ...card, borderColor: hasTrips ? "#f59e0b" : "var(--line)", background: hasTrips ? "#fffbeb" : "#fff", marginBottom: 14 }}>
      <div style={{ display: "flex", gap: 10, alignItems: "flex-start", flexWrap: "wrap" }}>
        <i className="bi bi-exclamation-triangle-fill" style={{ color: "#d97706", fontSize: 18, marginTop: 1 }} />
        <div style={{ flex: 1, minWidth: 220 }}>
          {hasTrips ? (
            <div style={{ fontSize: 13.5, fontWeight: 700, color: "#92400e" }}>
              {list.length} xe chưa gắn lái xe đã chạy <span className="tnum">{rep.totals.noDriverTrips}</span> chuyến — số chuyến này chưa tính cho lái nào
            </div>
          ) : (
            <div style={{ fontSize: 13.5, fontWeight: 700, color: "var(--ink-2)" }}>{idle.length} xe MBF chưa gắn lái xe</div>
          )}
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginTop: 3 }}>
            Gắn lái xe mặc định cho xe ở <b>Cài đặt › Biển số xe</b> để báo cáo tính đúng chuyến cho từng lái.
          </div>
          {hasTrips && (
            <div style={{ display: "flex", flexWrap: "wrap", gap: 6, marginTop: 8 }}>
              {list.slice(0, allChips ? list.length : CHIPS).map((v) => (
                <span key={v.vehicleId} style={{ fontSize: 12, fontWeight: 600, padding: "2px 9px", borderRadius: 7, background: "#fff", border: "1px solid #fcd34d", color: "#92400e" }}>
                  {v.bks} · <span className="tnum">{v.trips}</span> chuyến
                </span>
              ))}
              {list.length > CHIPS && (
                <button type="button" onClick={() => setAllChips((o) => !o)} style={{ border: "none", background: "none", color: "var(--accent)", cursor: "pointer", fontSize: 12, fontWeight: 600, padding: "2px 4px" }}>
                  {allChips ? "thu gọn" : `+${list.length - CHIPS} xe`}
                </button>
              )}
            </div>
          )}
          {idle.length > 0 && (
            <div style={{ fontSize: 12, color: "var(--ink-3)", marginTop: 8 }}>
              {hasTrips && <>Ngoài ra {idle.length} xe MBF chưa gắn lái (không chạy trong kỳ): </>}
              {idle.slice(0, open ? idle.length : 12).map((v) => v.bks).join(", ")}
              {idle.length > 12 && <button type="button" onClick={() => setOpen((o) => !o)} style={{ border: "none", background: "none", color: "var(--accent)", cursor: "pointer", fontSize: 12, padding: "0 4px" }}>{open ? "thu gọn" : `+${idle.length - 12} xe`}</button>}
            </div>
          )}
        </div>
        <a href={ROUTES.vehicles} style={{ ...btn, borderColor: "#f59e0b", color: "#92400e" }}><i className="bi bi-person-plus" /> Gắn lái xe</a>
      </div>
      {hasTrips && (
        <details style={{ marginTop: 10 }}>
          <summary style={{ fontSize: 12, color: "var(--ink-3)", cursor: "pointer" }}>Xem chuyến của các xe chưa gắn lái</summary>
          <div style={{ marginTop: 8 }}><DayLines lines={list.flatMap((v) => v.lines)} isMobile={isMobile} /></div>
        </details>
      )}
    </div>
  );
}

function DriverReportApp() {
  const isMobile = useIsMobile();
  const init = B.report || {};
  const [from, setFrom] = useState(init.from || PRESETS[0][1]()[0]);
  const [to, setTo] = useState(init.to || PRESETS[0][1]()[1]);
  const [rep, setRep] = useState(init.rows ? init : { rows: [], noDriver: [], idleNoDriver: [], totals: {} });
  const [loading, setLoading] = useState(false);
  const [q, setQ] = useState("");
  const [openId, setOpenId] = useState(null);

  const load = (f, t) => {
    if (!f || !t) return;
    setLoading(true);
    window.trkApi("GET", ROUTES.data + "?from=" + f + "&to=" + t)
      .then((r) => { if (r && r.ok) setRep(r.report); setLoading(false); })
      .catch(() => { setLoading(false); window.trkToast && window.trkToast("Lỗi tải báo cáo", "error"); });
  };
  const first = useRef(true);
  useEffect(() => { if (first.current) { first.current = false; return; } load(from, to); }, [from, to]);

  const tt = rep.totals || {};
  const rows = useMemo(() => {
    const s = q.trim().toLowerCase();
    return (rep.rows || []).filter((r) => !s || r.name.toLowerCase().includes(s) || r.plates.some((p) => p.toLowerCase().includes(s)));
  }, [rep, q]);
  const max = Math.max(1, ...(rep.rows || []).map((r) => r.trips));
  const avg = tt.activeDrivers ? (tt.trips / tt.activeDrivers).toFixed(1).replace(".", ",") : "0";
  const periodLabel = rep.from === rep.to ? fmtDate(rep.from) : `${fmtDate(rep.from)} → ${fmtDate(rep.to)} (${rep.days} ngày)`;

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <style>{`@media print { .trk-report-scroll { overflow: visible !important; height: auto !important; } }`}</style>
      <header className="ke-noprint" style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: isMobile ? "10px 14px" : "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 10, minHeight: 58, flexWrap: "wrap", padding: isMobile ? "4px 0" : "8px 0" }}>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center", flexShrink: 0 }}><i className="bi bi-person-badge" /></div>
          <div>
            <div style={{ fontSize: 15.5, fontWeight: 700 }}>Báo cáo lái xe</div>
            <div style={{ fontSize: 11.5, color: "var(--ink-3)" }}>Số chuyến theo lái xe · dựa trên Lộ trình (ngày vận hành 08:00 → 08:00)</div>
          </div>
          <div style={{ flex: 1 }} />
          <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap", width: isMobile ? "100%" : "auto" }}>
            {PRESETS.map(([l, fn]) => (
              <button key={l} type="button" style={btn} onClick={() => { const [f, t] = fn(); setFrom(f); setTo(t); }}>{l}</button>
            ))}
            <button type="button" style={btn} onClick={() => window.print()} title="In / xuất PDF"><i className="bi bi-printer" /> In</button>
            <div style={{ display: "flex", alignItems: "center", gap: 8, width: isMobile ? "100%" : "auto" }}>
              <span style={{ fontSize: 12.5, color: "var(--ink-3)", fontWeight: 600 }}>Từ</span>
              <div style={{ width: isMobile ? "auto" : 130, flex: isMobile ? 1 : "none", minWidth: 0 }}><DateField value={from} onChange={setFrom} /></div>
              <span style={{ fontSize: 12.5, color: "var(--ink-3)", fontWeight: 600 }}>đến</span>
              <div style={{ width: isMobile ? "auto" : 130, flex: isMobile ? 1 : "none", minWidth: 0 }}><DateField value={to} onChange={setTo} /></div>
            </div>
            {loading && <span style={{ fontSize: 12, color: "var(--ink-4)" }}><i className="bi bi-arrow-repeat" style={{ animation: "trk-spin .7s linear infinite" }} /> Đang tính…</span>}
            {!loading && rep.to && to && from && rep.to < (from > to ? from : to) && (
              <span style={{ fontSize: 12, color: "#b45309" }}><i className="bi bi-info-circle" /> Tối đa {rep.days} ngày — đã tính đến {fmtDate(rep.to)}</span>
            )}
          </div>
        </div>
      </header>

      <div className="ke-print trk-report-scroll" style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: isMobile ? "12px 12px 24px" : "16px 22px 28px", opacity: loading ? 0.55 : 1 }}>
        <div style={{ maxWidth: 1180, margin: "0 auto" }}>
          <div className="ke-printonly" style={{ display: "none", marginBottom: 14 }}>
            <div style={{ fontSize: 17, fontWeight: 800 }}>Báo cáo lái xe</div>
            <div style={{ fontSize: 12, color: "var(--ink-3)" }}>Kỳ {periodLabel} · in ngày {new Date().toLocaleDateString("vi-VN")}</div>
          </div>

          <NoDriverWarning rep={rep} isMobile={isMobile} />

          <div style={{ display: "flex", gap: 10, flexWrap: "wrap", marginBottom: 14 }}>
            <KPI label="Tổng chuyến" value={tt.trips || 0} sub={`Kéo cont ${tt.self || 0} · kéo cont khác ${tt.other || 0} · không kéo cont ${tt.none || 0}`} />
            <KPI label="Lái xe có chạy" value={`${tt.activeDrivers || 0} / ${(rep.rows || []).length}`} sub="lái đã gắn xe MBF" />
            <KPI label="TB chuyến / lái" value={avg} sub={periodLabel} />
            <KPI label="Chuyến chưa gắn lái" value={tt.noDriverTrips || 0} color={tt.noDriverTrips ? "#d97706" : undefined}
              sub={(rep.noDriver || []).length ? `${rep.noDriver.length} xe chưa gắn lái` : "Mọi xe có chạy đều đã gắn lái"} />
          </div>

          <div style={card}>
            <CardTitle icon="bi-list-ol" sub={periodLabel}
              right={<input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Tìm lái xe / biển số…" className="ke-noprint"
                style={{ fontSize: isMobile ? 16 : 13, padding: "6px 10px", border: "1px solid var(--line)", borderRadius: 8, width: isMobile ? "100%" : 220 }} />}>
              Số chuyến theo lái xe
            </CardTitle>
            <div style={{ display: "flex", gap: 14, fontSize: 11.5, color: "var(--ink-3)", marginBottom: 8, flexWrap: "wrap" }}>
              {["self", "other", "none"].map((m) => <span key={m}><i style={{ display: "inline-block", width: 9, height: 9, borderRadius: 2, background: MODE_COLOR[m], marginRight: 5 }} />{MODE_LABEL[m]}</span>)}
            </div>
            {!rows.length ? <Empty>{(rep.rows || []).length ? "Không có lái xe khớp tìm kiếm." : "Chưa có lái xe nào được gắn cho xe MBF."}</Empty> : (
              <div style={{ overflowX: "auto" }}>
                <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13 }}>
                  <thead>
                    <tr style={{ color: "var(--ink-4)", fontSize: 11.5, textTransform: "uppercase", textAlign: "left", letterSpacing: ".02em" }}>
                      <th style={{ padding: "8px", fontWeight: 700, width: 30 }}>#</th>
                      <th style={{ padding: "8px", fontWeight: 700 }}>Lái xe</th>
                      {!isMobile && <th style={{ padding: "8px", fontWeight: 700 }}>Xe</th>}
                      <th style={{ padding: "8px", fontWeight: 700, textAlign: "right" }}>Ngày chạy</th>
                      <th style={{ padding: "8px", fontWeight: 700, textAlign: "right" }}>Chuyến</th>
                      {!isMobile && <th style={{ padding: "8px", fontWeight: 700, textAlign: "right" }}>Kéo cont</th>}
                      {!isMobile && <th style={{ padding: "8px", fontWeight: 700, textAlign: "right" }}>Kéo cont khác</th>}
                      {!isMobile && <th style={{ padding: "8px", fontWeight: 700, textAlign: "right" }}>Không kéo</th>}
                      {!isMobile && <th style={{ padding: "8px", fontWeight: 700, textAlign: "right" }}>TB/ngày</th>}
                      {!isMobile && <th style={{ padding: "8px", width: 140 }} />}
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((r, i) => {
                      const open = openId === r.driverId;
                      return (
                        <React.Fragment key={r.driverId}>
                          <tr onClick={() => setOpenId(open ? null : r.driverId)}
                            style={{ borderTop: "1px solid var(--line)", cursor: "pointer", background: open ? "var(--accent-weak-2)" : undefined, color: r.trips ? undefined : "var(--ink-4)" }}>
                            <td style={{ padding: "9px 8px", color: "var(--ink-4)" }}>{i + 1}</td>
                            <td style={{ padding: "9px 8px", fontWeight: 700 }}>
                              <i className={"bi " + (open ? "bi-chevron-down" : "bi-chevron-right")} style={{ fontSize: 10, marginRight: 6, color: "var(--ink-4)" }} />{r.name}
                              {isMobile && <div style={{ fontSize: 11, fontWeight: 500, color: "var(--ink-4)" }}>{r.plates.join(", ")}</div>}
                            </td>
                            {!isMobile && <td style={{ padding: "9px 8px", fontSize: 12, color: "var(--ink-3)" }}>{r.plates.join(", ") || "—"}</td>}
                            <td className="tnum" style={{ padding: "9px 8px", textAlign: "right" }}>{r.days}</td>
                            <td className="tnum" style={{ padding: "9px 8px", textAlign: "right", fontWeight: 800, fontSize: 14 }}>{r.trips}</td>
                            {!isMobile && <td className="tnum" style={{ padding: "9px 8px", textAlign: "right" }}>{r.self || "—"}</td>}
                            {!isMobile && <td className="tnum" style={{ padding: "9px 8px", textAlign: "right" }}>{r.other || "—"}</td>}
                            {!isMobile && <td className="tnum" style={{ padding: "9px 8px", textAlign: "right" }}>{r.none || "—"}</td>}
                            {!isMobile && <td className="tnum" style={{ padding: "9px 8px", textAlign: "right" }}>{r.days ? (r.trips / r.days).toFixed(1).replace(".", ",") : "—"}</td>}
                            {!isMobile && <td style={{ padding: "9px 8px" }}><ModeBar r={r} max={max} /></td>}
                          </tr>
                          {open && (
                            <tr><td colSpan={isMobile ? 4 : 10} style={{ padding: isMobile ? "6px 4px 10px" : "4px 8px 12px 30px", background: "#fafbfc" }}>
                              <DayLines lines={r.lines} driverName={r.name} isMobile={isMobile} />
                            </td></tr>
                          )}
                        </React.Fragment>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
            <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 10, lineHeight: 1.5 }}>
              Chỉ tính chuyến đã có giờ ra (hoàn thành) của xe MBF; lái xe theo lái mặc định của xe ở Cài đặt › Biển số xe.
              {rep.extTrips > 0 && <> Không tính <b className="tnum">{rep.extTrips}</b> chuyến của xe ngoài.</>}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<DriverReportApp />);
