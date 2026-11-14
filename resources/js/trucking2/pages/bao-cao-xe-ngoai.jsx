import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";
import { useIsMobile, fmtDate, fmtNum, fmtVND } from "@trk/lib.jsx";
import { card, CardTitle, KPI, Empty } from "@trk/components/report-ui.jsx";

const { useState, useEffect, useRef, useMemo } = React;

/* BÁO CÁO CÔNG NỢ XE NGOÀI — sổ tổng hợp công nợ phải trả nhà xe thuê theo tháng (dữ liệu: extPayableReport).
   Phát sinh TĂNG = bảng kê xe ngoài có ngày lập trong tháng; phát sinh GIẢM = đợt thanh toán có ngày trả trong tháng;
   dư cuối = dư đầu + tăng − giảm (âm = trả trước). Tab "Tích chi theo lô" đối chiếu các khoản tích đã chi với đợt thanh toán. */

const T = window.__TRK || {};
const ROUTES = T.routes || {};
const B = T.boot || {};
const CO = B.company || {};
const CO_NAME = CO.name || "MBF JOINT STOCK COMPANY";

const z = (n) => String(n).padStart(2, "0");
const stHref = (h) => (ROUTES.statement || "") + h;
const monthLabel = (y, m) => `${z(m)}/${y}`;
const btn = { display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 11px", fontSize: 12.5, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: "var(--ink-2)", cursor: "pointer", textDecoration: "none" };
const th = (align, extra) => ({ padding: "8px", fontWeight: 700, textAlign: align || "left", whiteSpace: "nowrap", ...extra });
const td = (align, extra) => ({ padding: "8px", textAlign: align || "left", borderTop: "1px solid var(--line)", ...extra });
const money = (v, opts = {}) => (v ? fmtNum(v) : (opts.dash === false ? "0" : "—"));

/* Dư cuối âm = đã trả nhiều hơn phải trả (trả trước). */
function Balance({ v, strong }) {
  if (!v) return <span style={{ color: "var(--ink-4)" }}>—</span>;
  const neg = v < 0;
  return (
    <span className="tnum" title={neg ? "Trả trước / trả thừa cho nhà xe" : ""} style={{ fontWeight: strong ? 800 : 600, color: neg ? "var(--good)" : "var(--ink)" }}>
      {neg ? "(" + fmtNum(-v) + ")" : fmtNum(v)}
    </span>
  );
}

const DocLink = ({ d }) => <a href={stHref(d.hashid)} target="_blank" rel="noopener" style={{ color: "var(--accent)", fontWeight: 600, textDecoration: "none" }}>{d.no}</a>;

/* Sổ chi tiết công nợ 1 nhà xe trong tháng: dư đầu → từng chứng từ (lũy kế) → cộng phát sinh → dư cuối. */
function ledgerOf(rep, r) {
  const ev = [
    ...rep.incDocs.filter((d) => d.vendor === r.vendor).map((d) => ({ ...d, inc: d.total, dec: 0, desc: `Bảng kê xe ngoài${d.from || d.to ? ` kỳ ${fmtDate(d.from) || "…"} – ${fmtDate(d.to) || "…"}` : ""} · ${d.count} lô` })),
    ...rep.decDocs.filter((d) => d.vendor === r.vendor).map((d) => ({ ...d, inc: 0, dec: d.amount, desc: "Thanh toán" + (d.note ? ": " + d.note : "") })),
  ].sort((a, b) => (a.date < b.date ? -1 : a.date > b.date ? 1 : b.inc - a.inc));
  let bal = r.opening;
  return ev.map((e) => { bal += e.inc - e.dec; return { ...e, bal }; });
}

function Ledger({ rep, r }) {
  const ev = ledgerOf(rep, r);
  return (
    <div style={{ overflowX: "auto" }}>
      <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12.5, background: "#fff" }}>
        <thead>
          <tr style={{ color: "var(--ink-4)", fontSize: 11 }}>
            <th style={th()}>Ngày</th><th style={th()}>Chứng từ</th><th style={th()}>Diễn giải</th>
            <th style={th("right")}>Phát sinh tăng</th><th style={th("right")}>Phát sinh giảm</th><th style={th("right")}>Số dư</th>
          </tr>
        </thead>
        <tbody>
          <tr style={{ color: "var(--ink-3)" }}>
            <td style={td()} colSpan={3}><i>Dư đầu kỳ</i></td><td style={td()} /><td style={td()} /><td style={td("right")}><Balance v={r.opening} /></td>
          </tr>
          {ev.map((e, i) => (
            <tr key={i}>
              <td className="tnum" style={td(null, { whiteSpace: "nowrap" })}>{fmtDate(e.date)}</td>
              <td style={td(null, { whiteSpace: "nowrap" })}><DocLink d={e} /></td>
              <td style={td(null, { color: "var(--ink-2)" })}>{e.desc}</td>
              <td className="tnum" style={td("right")}>{e.inc ? fmtNum(e.inc) : ""}</td>
              <td className="tnum" style={td("right", { color: "var(--good)" })}>{e.dec ? fmtNum(e.dec) : ""}</td>
              <td style={td("right")}><Balance v={e.bal} /></td>
            </tr>
          ))}
          <tr style={{ fontWeight: 700 }}>
            <td style={td()} colSpan={3}>Cộng phát sinh</td>
            <td className="tnum" style={td("right")}>{money(r.inc, { dash: false })}</td>
            <td className="tnum" style={td("right", { color: "var(--good)" })}>{money(r.dec, { dash: false })}</td>
            <td style={td()} />
          </tr>
          <tr style={{ fontWeight: 800 }}>
            <td style={td()} colSpan={5}>Dư cuối kỳ</td><td style={td("right")}><Balance v={r.closing} strong /></td>
          </tr>
        </tbody>
      </table>
    </div>
  );
}

/* Tab 1 — Tổng hợp công nợ theo nhà xe (bấm 1 dòng để xem sổ chi tiết). */
function SummaryTab({ rep, rows, isMobile }) {
  const [openV, setOpenV] = useState(null);
  const t = rep.totals;
  if (!rows.length) return <Empty>Không có công nợ nhà xe ngoài nào trong tháng này.</Empty>;
  const cols = isMobile ? 4 : 8;
  return (
    <div style={{ overflowX: "auto" }}>
      <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13 }}>
        <thead>
          <tr style={{ color: "var(--ink-4)", fontSize: 11.5, textTransform: "uppercase", letterSpacing: ".02em" }}>
            <th style={th(null, { width: 30 })}>#</th>
            <th style={th()}>Nhà xe</th>
            {!isMobile && <th style={th("right")}>Dư đầu kỳ</th>}
            <th style={th("right")}>Phát sinh tăng</th>
            <th style={th("right")}>Phát sinh giảm</th>
            {!isMobile && <th style={th("right")}>Dư cuối kỳ</th>}
            {!isMobile && <th style={th("right")} title="Số bảng kê lập / số đợt thanh toán trong tháng">Chứng từ</th>}
            {!isMobile && <th style={th("right")} title="Các khoản tích đã chi theo lô có ngày chi trong tháng">Đã tích chi</th>}
          </tr>
        </thead>
        <tbody>
          {rows.map((r, i) => {
            const open = openV === r.vendor;
            const gap = r.ticked - r.dec;
            return (
              <React.Fragment key={r.vendor}>
                <tr onClick={() => setOpenV(open ? null : r.vendor)} style={{ cursor: "pointer", background: open ? "var(--accent-weak-2)" : undefined }}>
                  <td style={td(null, { color: "var(--ink-4)" })}>{i + 1}</td>
                  <td style={td(null, { fontWeight: 700 })}>
                    <i className={"bi ke-noprint " + (open ? "bi-chevron-down" : "bi-chevron-right")} style={{ fontSize: 10, marginRight: 6, color: "var(--ink-4)" }} />{r.vendor}
                    {isMobile && <div style={{ fontSize: 11, fontWeight: 500, color: "var(--ink-3)" }}>Đầu kỳ <Balance v={r.opening} /> · Cuối kỳ <Balance v={r.closing} strong /></div>}
                  </td>
                  {!isMobile && <td style={td("right")}><Balance v={r.opening} /></td>}
                  <td className="tnum" style={td("right")}>{money(r.inc)}</td>
                  <td className="tnum" style={td("right", { color: r.dec ? "var(--good)" : undefined })}>{money(r.dec)}</td>
                  {!isMobile && <td style={td("right")}><Balance v={r.closing} strong /></td>}
                  {!isMobile && <td className="tnum" style={td("right", { fontSize: 12, color: "var(--ink-3)", whiteSpace: "nowrap" })}>{r.nInc} BK · {r.nDec} đợt</td>}
                  {!isMobile && (
                    <td className="tnum" style={td("right", { fontSize: 12, whiteSpace: "nowrap" })}>
                      {money(r.ticked)}
                      {gap !== 0 && <div className="ke-noprint" title="Số đã tích chi theo lô khác số đợt thanh toán đã ghi trong tháng" style={{ fontSize: 10.5, fontWeight: 600, color: "#b45309" }}>lệch {gap > 0 ? "+" : "−"}{fmtNum(Math.abs(gap))}</div>}
                    </td>
                  )}
                </tr>
                {open && (
                  <tr><td colSpan={cols} style={{ padding: isMobile ? "6px 2px 12px" : "4px 8px 14px 30px", background: "#fafbfc" }}>
                    <Ledger rep={rep} r={r} />
                  </td></tr>
                )}
              </React.Fragment>
            );
          })}
        </tbody>
        <tfoot>
          <tr style={{ fontWeight: 800, background: "#fafbfc" }}>
            <td style={td()} />
            <td style={td()}>TỔNG CỘNG</td>
            {!isMobile && <td style={td("right")}><Balance v={t.opening} strong /></td>}
            <td className="tnum" style={td("right")}>{money(t.inc, { dash: false })}</td>
            <td className="tnum" style={td("right", { color: "var(--good)" })}>{money(t.dec, { dash: false })}</td>
            {!isMobile && <td style={td("right")}><Balance v={t.closing} strong /></td>}
            {!isMobile && <td style={td()} />}
            {!isMobile && <td className="tnum" style={td("right")}>{money(t.ticked, { dash: false })}</td>}
          </tr>
        </tfoot>
      </table>
    </div>
  );
}

/* Tab 2 — Phát sinh tăng: bảng kê lập trong tháng (tách cước / VAT / chi hộ cho hạch toán). */
function IncTab({ docs, t }) {
  if (!docs.length) return <Empty>Không lập bảng kê xe ngoài nào trong tháng.</Empty>;
  return (
    <div style={{ overflowX: "auto" }}>
      <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13 }}>
        <thead><tr style={{ color: "var(--ink-4)", fontSize: 11.5, textTransform: "uppercase" }}>
          <th style={th()}>Ngày lập</th><th style={th()}>Số bảng kê</th><th style={th()}>Nhà xe</th><th style={th()}>Kỳ (Giờ xe đến)</th>
          <th style={th("right")}>Số lô</th><th style={th("right")}>Cước</th><th style={th("right")}>VAT</th><th style={th("right")}>Chi hộ</th><th style={th("right")}>Tổng</th>
        </tr></thead>
        <tbody>
          {docs.map((d) => (
            <tr key={d.hashid}>
              <td className="tnum" style={td(null, { whiteSpace: "nowrap" })}>{fmtDate(d.date)}</td>
              <td style={td(null, { whiteSpace: "nowrap" })}><DocLink d={d} /></td>
              <td style={td(null, { fontWeight: 600 })}>{d.vendor}</td>
              <td className="tnum" style={td(null, { fontSize: 12, color: "var(--ink-3)", whiteSpace: "nowrap" })}>{d.from || d.to ? `${fmtDate(d.from) || "…"} – ${fmtDate(d.to) || "…"}` : "—"}</td>
              <td className="tnum" style={td("right")}>{d.count}</td>
              <td className="tnum" style={td("right")}>{money(d.fee)}</td>
              <td className="tnum" style={td("right")}>{money(d.vat)}</td>
              <td className="tnum" style={td("right")}>{money(d.choho)}</td>
              <td className="tnum" style={td("right", { fontWeight: 700 })}>{fmtNum(d.total)}</td>
            </tr>
          ))}
        </tbody>
        <tfoot><tr style={{ fontWeight: 800, background: "#fafbfc" }}>
          <td style={td()} colSpan={4}>TỔNG CỘNG ({docs.length} bảng kê)</td>
          <td className="tnum" style={td("right")}>{docs.reduce((a, d) => a + d.count, 0)}</td>
          <td className="tnum" style={td("right")}>{fmtNum(t.fee)}</td>
          <td className="tnum" style={td("right")}>{fmtNum(t.vat)}</td>
          <td className="tnum" style={td("right")}>{fmtNum(t.choho)}</td>
          <td className="tnum" style={td("right")}>{fmtNum(t.inc)}</td>
        </tr></tfoot>
      </table>
    </div>
  );
}

/* Tab 3 — Phát sinh giảm: các đợt thanh toán cho nhà xe trong tháng. */
function DecTab({ docs, total }) {
  if (!docs.length) return <Empty>Không có đợt thanh toán nào cho nhà xe ngoài trong tháng.</Empty>;
  return (
    <div style={{ overflowX: "auto" }}>
      <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13 }}>
        <thead><tr style={{ color: "var(--ink-4)", fontSize: 11.5, textTransform: "uppercase" }}>
          <th style={th(null, { width: 30 })}>#</th><th style={th()}>Ngày trả</th><th style={th()}>Nhà xe</th><th style={th()}>Bảng kê</th><th style={th()}>Diễn giải</th><th style={th("right")}>Số tiền</th>
        </tr></thead>
        <tbody>
          {docs.map((d, i) => (
            <tr key={i}>
              <td style={td(null, { color: "var(--ink-4)" })}>{i + 1}</td>
              <td className="tnum" style={td(null, { whiteSpace: "nowrap" })}>{fmtDate(d.date)}</td>
              <td style={td(null, { fontWeight: 600 })}>{d.vendor}</td>
              <td style={td(null, { whiteSpace: "nowrap" })}><DocLink d={d} /></td>
              <td style={td(null, { color: "var(--ink-2)" })}>{d.note || "Thanh toán"}</td>
              <td className="tnum" style={td("right", { fontWeight: 700, color: "var(--good)" })}>{fmtNum(d.amount)}</td>
            </tr>
          ))}
        </tbody>
        <tfoot><tr style={{ fontWeight: 800, background: "#fafbfc" }}>
          <td style={td()} colSpan={5}>TỔNG ĐÃ TRẢ ({docs.length} đợt)</td><td className="tnum" style={td("right", { color: "var(--good)" })}>{fmtNum(total)}</td>
        </tr></tfoot>
      </table>
    </div>
  );
}

/* Tab 4 — Các khoản tích "đã chi" theo lô có ngày chi trong tháng (chi tiết đến từng khoản). */
function TickTab({ ticks, total }) {
  if (!ticks.length) return <Empty>Không có khoản nào được tích đã chi trong tháng.</Empty>;
  return (
    <div style={{ overflowX: "auto" }}>
      <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12.5 }}>
        <thead><tr style={{ color: "var(--ink-4)", fontSize: 11.5, textTransform: "uppercase" }}>
          <th style={th()}>Ngày chi</th><th style={th()}>Nhà xe</th><th style={th()}>Bảng kê</th><th style={th()}>Lô / Khách hàng</th><th style={th()}>Khoản</th><th style={th("right")}>Số tiền</th>
        </tr></thead>
        <tbody>
          {ticks.map((x, i) => (
            <tr key={i}>
              <td className="tnum" style={td(null, { whiteSpace: "nowrap" })}>{fmtDate(x.date)}</td>
              <td style={td(null, { fontWeight: 600 })}>{x.vendor}</td>
              <td style={td(null, { whiteSpace: "nowrap" })}><DocLink d={x} /></td>
              <td style={td()}>
                <b className="tnum">{x.booking || "—"}</b>{x.customer && <span style={{ color: "var(--accent)", marginLeft: 6 }}>{x.customer}</span>}
                {x.contLabel && <div style={{ fontSize: 11, color: "var(--ink-4)" }}>{x.contLabel}</div>}
              </td>
              <td style={td(null, { color: x.kind === "fee" ? "var(--ink)" : "var(--ink-3)" })}>{x.item}</td>
              <td className="tnum" style={td("right", { fontWeight: 600 })}>{fmtNum(x.amount)}</td>
            </tr>
          ))}
        </tbody>
        <tfoot><tr style={{ fontWeight: 800, background: "#fafbfc" }}>
          <td style={td()} colSpan={5}>TỔNG ĐÃ TÍCH CHI ({ticks.length} khoản)</td><td className="tnum" style={td("right")}>{fmtNum(total)}</td>
        </tr></tfoot>
      </table>
    </div>
  );
}

/* Xuất Excel: Tổng hợp · Sổ chi tiết · Phát sinh tăng · Phát sinh giảm · Tích chi theo lô (số dạng #,##0). */
function exportExcel(rep) {
  if (typeof XLSX === "undefined") { window.trkToast && window.trkToast("Thư viện Excel chưa tải xong, thử lại sau giây lát.", "error"); return; }
  const ml = monthLabel(rep.year, rep.month);
  const head = (title) => [[CO_NAME], [title], [`Tháng ${ml} (${fmtDate(rep.from)} – ${fmtDate(rep.to)})`], []];
  const sheet = (aoa, widths) => {
    const ws = XLSX.utils.aoa_to_sheet(aoa);
    Object.keys(ws).forEach((k) => { if (k[0] !== "!" && ws[k].t === "n") ws[k].z = "#,##0"; });
    ws["!cols"] = widths.map((wch) => ({ wch }));
    return ws;
  };
  const t = rep.totals;
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, sheet([
    ...head("BẢNG TỔNG HỢP CÔNG NỢ PHẢI TRẢ NHÀ XE NGOÀI"),
    ["STT", "Nhà xe", "Dư đầu kỳ", "Phát sinh tăng", "Phát sinh giảm", "Dư cuối kỳ", "Số bảng kê", "Số đợt thanh toán", "Đã tích chi theo lô"],
    ...rep.rows.map((r, i) => [i + 1, r.vendor, r.opening, r.inc, r.dec, r.closing, r.nInc, r.nDec, r.ticked]),
    ["", "TỔNG CỘNG", t.opening, t.inc, t.dec, t.closing, "", "", t.ticked],
    [], ["", "Ghi chú: Phát sinh tăng = bảng kê lập trong tháng; Phát sinh giảm = đợt thanh toán trong tháng; số âm = trả trước."],
  ], [6, 30, 16, 16, 16, 16, 11, 11, 16]), "Tổng hợp");

  const det = [...head("SỔ CHI TIẾT CÔNG NỢ PHẢI TRẢ THEO NHÀ XE"), ["Ngày", "Chứng từ", "Diễn giải", "Phát sinh tăng", "Phát sinh giảm", "Số dư"]];
  rep.rows.forEach((r) => {
    det.push([], [r.vendor], ["", "", "Dư đầu kỳ", "", "", r.opening]);
    ledgerOf(rep, r).forEach((e) => det.push([fmtDate(e.date), e.no, e.desc, e.inc || "", e.dec || "", e.bal]));
    det.push(["", "", "Cộng phát sinh", r.inc, r.dec, ""], ["", "", "Dư cuối kỳ", "", "", r.closing]);
  });
  XLSX.utils.book_append_sheet(wb, sheet(det, [12, 18, 50, 16, 16, 16]), "Sổ chi tiết");

  XLSX.utils.book_append_sheet(wb, sheet([
    ...head("PHÁT SINH TĂNG — BẢNG KÊ XE NGOÀI LẬP TRONG THÁNG"),
    ["Ngày lập", "Số bảng kê", "Nhà xe", "Kỳ từ", "Kỳ đến", "Số lô", "Cước", "VAT", "Chi hộ", "Tổng"],
    ...rep.incDocs.map((d) => [fmtDate(d.date), d.no, d.vendor, fmtDate(d.from), fmtDate(d.to), d.count, d.fee, d.vat, d.choho, d.total]),
    ["", "TỔNG CỘNG", "", "", "", rep.incDocs.reduce((a, d) => a + d.count, 0), t.fee, t.vat, t.choho, t.inc],
  ], [12, 18, 26, 12, 12, 8, 15, 13, 15, 16]), "Phát sinh tăng");

  XLSX.utils.book_append_sheet(wb, sheet([
    ...head("PHÁT SINH GIẢM — THANH TOÁN CHO NHÀ XE TRONG THÁNG"),
    ["STT", "Ngày trả", "Nhà xe", "Số bảng kê", "Diễn giải", "Số tiền"],
    ...rep.decDocs.map((d, i) => [i + 1, fmtDate(d.date), d.vendor, d.no, d.note || "Thanh toán", d.amount]),
    ["", "", "", "", "TỔNG CỘNG", t.dec],
  ], [6, 12, 26, 18, 40, 16]), "Phát sinh giảm");

  XLSX.utils.book_append_sheet(wb, sheet([
    ...head("CÁC KHOẢN ĐÃ TÍCH CHI THEO LÔ TRONG THÁNG"),
    ["Ngày chi", "Nhà xe", "Số bảng kê", "Lô", "Khách hàng", "Cont", "Khoản", "Số tiền"],
    ...rep.ticks.map((x) => [fmtDate(x.date), x.vendor, x.no, x.booking, x.customer, x.contLabel, x.item, x.amount]),
    ["", "", "", "", "", "", "TỔNG CỘNG", t.ticked],
  ], [12, 24, 18, 18, 18, 24, 30, 15]), "Tích chi theo lô");

  XLSX.writeFile(wb, `cong-no-xe-ngoai-${rep.year}-${z(rep.month)}.xlsx`);
}

const TABS = [
  ["sum", "Tổng hợp công nợ", "bi-journal-text"],
  ["inc", "Phát sinh tăng", "bi-plus-circle"],
  ["dec", "Phát sinh giảm (đã trả)", "bi-dash-circle"],
  ["tick", "Tích chi theo lô", "bi-check2-square"],
];

function ExtPayableReportApp() {
  const isMobile = useIsMobile();
  const init = B.report || {};
  const now = new Date();
  const [ym, setYm] = useState({ y: init.year || now.getFullYear(), m: init.month || now.getMonth() + 1 });
  const [rep, setRep] = useState(init.rows ? init : null);
  const [loading, setLoading] = useState(false);
  const [tab, setTab] = useState("sum");
  const [q, setQ] = useState("");

  const first = useRef(true);
  useEffect(() => {
    if (first.current && rep) { first.current = false; return; }
    first.current = false;
    setLoading(true);
    window.trkApi("GET", ROUTES.data + "?year=" + ym.y + "&month=" + ym.m)
      .then((r) => { if (r && r.ok) setRep(r.report); setLoading(false); })
      .catch(() => { setLoading(false); window.trkToast && window.trkToast("Lỗi tải báo cáo", "error"); });
  }, [ym.y, ym.m]);

  const shift = (d) => setYm(({ y, m }) => { const x = new Date(y, m - 1 + d, 1); return { y: x.getFullYear(), m: x.getMonth() + 1 }; });
  const match = (v) => !q.trim() || (v || "").toLowerCase().includes(q.trim().toLowerCase());
  const R = rep || { rows: [], incDocs: [], decDocs: [], ticks: [], totals: {}, prev: {} };
  const rows = useMemo(() => R.rows.filter((r) => match(r.vendor)), [rep, q]);
  const incDocs = useMemo(() => R.incDocs.filter((d) => match(d.vendor) || match(d.no)), [rep, q]);
  const decDocs = useMemo(() => R.decDocs.filter((d) => match(d.vendor) || match(d.no)), [rep, q]);
  const ticks = useMemo(() => R.ticks.filter((x) => match(x.vendor) || match(x.no) || match(x.booking)), [rep, q]);
  const t = R.totals;
  const tickGap = (t.ticked || 0) - (t.dec || 0);
  const ml = monthLabel(ym.y, ym.m);
  const years = []; for (let y = now.getFullYear() + 1; y >= 2024; y--) years.push(y);
  const sel = { fontSize: 13, padding: "6px 8px", border: "1px solid var(--line)", borderRadius: 8, background: "#fff", cursor: "pointer" };

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <style>{`@media print { .trk-report-scroll { overflow: visible !important; height: auto !important; } .ke-print table { font-size: 11px !important; } }`}</style>
      <header className="ke-noprint" style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: isMobile ? "10px 14px" : "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 10, minHeight: 58, flexWrap: "wrap", padding: isMobile ? "4px 0" : "8px 0" }}>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center", flexShrink: 0 }}><i className="bi bi-journal-text" /></div>
          <div>
            <div style={{ fontSize: 15.5, fontWeight: 700 }}>Báo cáo công nợ xe ngoài</div>
            <div style={{ fontSize: 11.5, color: "var(--ink-3)" }}>Công nợ phải trả nhà xe thuê theo tháng · dư đầu kỳ, phát sinh tăng / giảm, dư cuối kỳ</div>
          </div>
          <div style={{ flex: 1 }} />
          <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
            <button type="button" style={btn} onClick={() => shift(-1)} title="Tháng trước"><i className="bi bi-chevron-left" /></button>
            <select value={ym.m} onChange={(e) => setYm({ ...ym, m: +e.target.value })} style={sel}>
              {Array.from({ length: 12 }, (_, i) => <option key={i} value={i + 1}>Tháng {i + 1}</option>)}
            </select>
            <select value={ym.y} onChange={(e) => setYm({ ...ym, y: +e.target.value })} style={sel}>
              {years.map((y) => <option key={y} value={y}>{y}</option>)}
            </select>
            <button type="button" style={btn} onClick={() => shift(1)} title="Tháng sau"><i className="bi bi-chevron-right" /></button>
            <button type="button" style={btn} onClick={() => setYm({ y: now.getFullYear(), m: now.getMonth() + 1 })}>Tháng này</button>
            <button type="button" style={btn} onClick={() => rep && exportExcel(rep)} disabled={!rep}><i className="bi bi-file-earmark-excel" /> Xuất Excel</button>
            <button type="button" style={btn} onClick={() => window.print()} title="In / xuất PDF"><i className="bi bi-printer" /> In</button>
            {loading && <span style={{ fontSize: 12, color: "var(--ink-4)" }}><i className="bi bi-arrow-repeat" style={{ animation: "trk-spin .7s linear infinite" }} /> Đang tính…</span>}
          </div>
        </div>
      </header>

      <div className="ke-print trk-report-scroll" style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: isMobile ? "12px 12px 24px" : "16px 22px 28px", opacity: loading ? 0.55 : 1 }}>
        <div style={{ maxWidth: 1180, margin: "0 auto" }}>
          <div className="ke-printonly" style={{ display: "none", marginBottom: 14 }}>
            <div style={{ fontSize: 12, fontWeight: 700 }}>{CO_NAME}</div>
            <div style={{ fontSize: 17, fontWeight: 800, marginTop: 6 }}>BÁO CÁO CÔNG NỢ PHẢI TRẢ NHÀ XE NGOÀI — THÁNG {ml}</div>
            <div style={{ fontSize: 12, color: "var(--ink-3)" }}>Từ {fmtDate(R.from)} đến {fmtDate(R.to)} · in ngày {new Date().toLocaleDateString("vi-VN")} · {TABS.find((x) => x[0] === tab)[1]}</div>
          </div>

          <div style={{ display: "flex", gap: 10, flexWrap: "wrap", marginBottom: 14 }}>
            <KPI label="Dư đầu kỳ" value={fmtVND(t.opening || 0)} sub={`Còn phải trả trước ngày ${fmtDate(R.from) || "đầu tháng"}`} />
            <KPI label="Phát sinh tăng" value={fmtVND(t.inc || 0)} cur={t.inc} prev={R.prev && R.prev.inc} deltaLabel="so tháng trước"
              sub={`${R.incDocs.length} bảng kê · cước ${fmtNum(t.fee || 0)} · VAT ${fmtNum(t.vat || 0)} · chi hộ ${fmtNum(t.choho || 0)}`} />
            <KPI label="Phát sinh giảm (đã trả)" value={fmtVND(t.dec || 0)} color="var(--good)" cur={t.dec} prev={R.prev && R.prev.dec} goodWhen="up" deltaLabel="so tháng trước"
              sub={`${R.decDocs.length} đợt thanh toán`} />
            <KPI label="Dư cuối kỳ" value={fmtVND(t.closing || 0)} color={(t.closing || 0) > 0 ? "var(--warn)" : "var(--good)"}
              sub={`Còn phải trả ${R.rows.filter((r) => r.closing > 0).length} nhà xe`} />
          </div>

          {tickGap !== 0 && (t.ticked || t.dec) ? (
            <div className="ke-noprint" style={{ ...card, padding: "10px 14px", borderColor: "#f3e2bf", background: "#fffaf0", marginBottom: 14, display: "flex", gap: 10, alignItems: "flex-start", fontSize: 12.5, color: "var(--ink-2)" }}>
              <i className="bi bi-lightbulb" style={{ color: "var(--warn)", fontSize: 16 }} />
              <div style={{ flex: 1 }}>
                Trong tháng đã tích chi theo lô <b className="tnum">{fmtVND(t.ticked || 0)}</b> nhưng đợt thanh toán ghi <b className="tnum">{fmtVND(t.dec || 0)}</b>
                {" "}— lệch <b className="tnum" style={{ color: "var(--warn)" }}>{fmtVND(Math.abs(tickGap))}</b>. Công nợ tính theo <b>đợt thanh toán</b>; xem cột "Đã tích chi" để biết nhà xe nào chưa khớp.
              </div>
            </div>
          ) : null}

          <div style={card}>
            <div className="ke-noprint" style={{ display: "flex", alignItems: "center", gap: 4, marginBottom: 12, flexWrap: "wrap", borderBottom: "1px solid var(--line)" }}>
              {TABS.map(([k, label, icon]) => {
                const n = { sum: rows.length, inc: incDocs.length, dec: decDocs.length, tick: ticks.length }[k];
                return (
                  <button key={k} type="button" onClick={() => setTab(k)}
                    style={{ border: "none", background: "transparent", cursor: "pointer", padding: "8px 12px 11px", fontSize: 13, fontWeight: 600, marginBottom: -1,
                      color: tab === k ? "var(--accent)" : "var(--ink-3)", borderBottom: "2px solid " + (tab === k ? "var(--accent)" : "transparent") }}>
                    <i className={"bi " + icon} style={{ marginRight: 5 }} />{label}<span className="tnum" style={{ marginLeft: 6, fontSize: 11.5, color: "var(--ink-4)" }}>{n}</span>
                  </button>
                );
              })}
              <div style={{ flex: 1 }} />
              <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Tìm nhà xe / số bảng kê / lô…"
                style={{ fontSize: isMobile ? 16 : 13, padding: "6px 10px", border: "1px solid var(--line)", borderRadius: 8, width: isMobile ? "100%" : 230, marginBottom: 6 }} />
            </div>
            {!rep ? <Empty>Đang tải…</Empty>
              : tab === "sum" ? <SummaryTab rep={R} rows={rows} isMobile={isMobile} />
              : tab === "inc" ? <IncTab docs={incDocs} t={q ? { fee: incDocs.reduce((a, d) => a + d.fee, 0), vat: incDocs.reduce((a, d) => a + d.vat, 0), choho: incDocs.reduce((a, d) => a + d.choho, 0), inc: incDocs.reduce((a, d) => a + d.total, 0) } : t} />
              : tab === "dec" ? <DecTab docs={decDocs} total={decDocs.reduce((a, d) => a + d.amount, 0)} />
              : <TickTab ticks={ticks} total={ticks.reduce((a, x) => a + x.amount, 0)} />}
            <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 12, lineHeight: 1.55 }}>
              <b>Phát sinh tăng</b> = bảng kê xe ngoài có <b>ngày lập</b> trong tháng (cước + VAT + chi hộ). <b>Phát sinh giảm</b> = các <b>đợt thanh toán</b> có ngày trả trong tháng
              (nhập ở từng bảng kê). Dư cuối kỳ = dư đầu kỳ + phát sinh tăng − phát sinh giảm; số trong ngoặc là trả trước cho nhà xe. Bảng kê sửa / làm mới sau khi lập sẽ cập nhật số liệu của tháng lập.
            </div>
          </div>

          <div className="ke-printonly" style={{ display: "none" }}>
            <div style={{ display: "flex", justifyContent: "space-around", marginTop: 34, textAlign: "center", fontSize: 12.5 }}>
              <div><b>Người lập biểu</b><div style={{ fontSize: 11, color: "var(--ink-4)" }}>(Ký, ghi rõ họ tên)</div></div>
              <div><b>Kế toán trưởng</b><div style={{ fontSize: 11, color: "var(--ink-4)" }}>(Ký, ghi rõ họ tên)</div></div>
              <div><b>Giám đốc</b><div style={{ fontSize: 11, color: "var(--ink-4)" }}>(Ký, đóng dấu)</div></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<ExtPayableReportApp />);
