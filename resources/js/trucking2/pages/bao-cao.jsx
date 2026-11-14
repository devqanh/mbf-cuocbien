import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";
import { useIsMobile, fmtDate, fmtNum, fmtVND } from "@trk/lib.jsx";
import { card, KPI, Empty, Stacked } from "@trk/components/report-ui.jsx";

const { useState, useEffect, useRef, useMemo } = React;

/* BÁO CÁO DOANH THU PHẢI THU — sổ công nợ phải thu khách theo tháng, dựng từ BẢNG KÊ THU (dữ liệu: receivableReport).
   Doanh thu phát sinh = bảng kê có "đến ngày" của kỳ cont ra trong kỳ (chưa có kỳ thì ngày lập), tách cước / VAT / chi hộ / phí khác;
   Đã thu = đợt khách thanh toán có ngày thu trong kỳ; dư cuối = dư đầu + doanh thu − đã thu (âm = khách trả trước).
   Cùng cách trình bày với Báo cáo công nợ xe ngoài (chiều ngược lại: phải THU). */

const T = window.__TRK || {};
const ROUTES = T.routes || {};
const B = T.boot || {};
const CO = B.company || {};
const CO_NAME = CO.name || "MBF JOINT STOCK COMPANY";

const z = (n) => String(n).padStart(2, "0");
const stHref = (h) => (ROUTES.statement || "") + h;
/* KỲ báo cáo = khoảng tháng {from, to} dạng "YYYY-MM" — 1 tháng, 1 quý hoặc tùy chọn từ tháng này đến tháng kia. */
const ymStr = (y, m) => `${y}-${z(m)}`;
const ymParts = (s) => { const [y, m] = String(s).split("-").map(Number); return { y, m }; };
const ymShift = (s, d) => { const { y, m } = ymParts(s); const x = new Date(y, m - 1 + d, 1); return ymStr(x.getFullYear(), x.getMonth() + 1); };
const ymLabel = (s) => { const { y, m } = ymParts(s); return `${z(m)}/${y}`; };
const monthsOf = (p) => { const a = ymParts(p.from), b = ymParts(p.to); return (b.y - a.y) * 12 + b.m - a.m + 1; };
const isQuarter = (p) => { const a = ymParts(p.from); return monthsOf(p) === 3 && (a.m - 1) % 3 === 0; };
const quarterOf = (s) => { const { y, m } = ymParts(s); return { y, q: Math.floor((m - 1) / 3) + 1 }; };
const quarterRange = (y, q) => ({ from: ymStr(y, q * 3 - 2), to: ymStr(y, q * 3) });
const periodLabel = (p) => (p.from === p.to ? `Tháng ${ymLabel(p.from)}`
  : isQuarter(p) ? `Quý ${quarterOf(p.from).q}/${ymParts(p.from).y}` : `Tháng ${ymLabel(p.from)} – ${ymLabel(p.to)}`);
const periodSlug = (p) => (p.from === p.to ? p.from : `${p.from}_${p.to}`);
const btn = { display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 11px", fontSize: 12.5, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: "var(--ink-2)", cursor: "pointer", textDecoration: "none" };
const th = (align, extra) => ({ padding: "8px", fontWeight: 700, textAlign: align || "left", whiteSpace: "nowrap", ...extra });
const td = (align, extra) => ({ padding: "8px", textAlign: align || "left", borderTop: "1px solid var(--line)", ...extra });
const money = (v, opts = {}) => (v ? fmtNum(v) : (opts.dash === false ? "0" : "—"));
const SPLIT = [["base", "Doanh thu (cước+dầu)", "#2563eb"], ["vat", "VAT", "#7c3aed"], ["choho", "Chi hộ", "#16a34a"], ["other", "Phí khác (tờ khai, phí riêng)", "#d97706"]];

/* Dư âm = khách trả nhiều hơn phải thu (trả trước). */
function Balance({ v, strong }) {
  if (!v) return <span style={{ color: "var(--ink-4)" }}>—</span>;
  const neg = v < 0;
  return (
    <span className="tnum" title={neg ? "Khách trả trước / trả thừa" : ""} style={{ fontWeight: strong ? 800 : 600, color: neg ? "var(--good)" : "var(--ink)" }}>
      {neg ? "(" + fmtNum(-v) + ")" : fmtNum(v)}
    </span>
  );
}

const DocLink = ({ d }) => <a href={stHref(d.hashid)} target="_blank" rel="noopener" style={{ color: "var(--accent)", fontWeight: 600, textDecoration: "none" }}>{d.no || "(chưa có số)"}</a>;
const period = (d) => (d.from || d.to ? `${fmtDate(d.from) || "…"} – ${fmtDate(d.to) || "…"}` : "");

/* Trạng thái hạn thanh toán của 1 bảng kê còn nợ. */
function dueState(d, asOf) {
  if (!d.due) return { label: "Chưa có hạn", color: "var(--ink-4)", bg: "#f1f5f9" };
  if (d.late > 0) return { label: `Quá hạn ${d.late} ngày`, color: "#b91c1c", bg: "#fee2e2" };
  const left = Math.round((new Date(d.due) - new Date(asOf)) / 86400000);
  if (left <= 7) return { label: left <= 0 ? "Đến hạn hôm nay" : `Còn ${left} ngày`, color: "#92400e", bg: "#fef3c7" };
  return { label: "Trong hạn", color: "#166534", bg: "#dcfce7" };
}
const DueChip = ({ d, asOf }) => { const s = dueState(d, asOf); return <span style={{ fontSize: 11.5, fontWeight: 700, color: s.color, background: s.bg, borderRadius: 999, padding: "2px 9px", whiteSpace: "nowrap" }}>{s.label}</span>; };

/* Sổ chi tiết công nợ 1 khách trong kỳ: dư đầu → từng chứng từ (lũy kế) → cộng phát sinh → dư cuối. */
function ledgerOf(rep, r) {
  const ev = [
    ...rep.incDocs.filter((d) => d.customer === r.customer).map((d) => ({ ...d, inc: d.total, dec: 0, desc: `Bảng kê${period(d) ? " kỳ " + period(d) : ""} · ${d.count} lô` })),
    ...rep.decDocs.filter((d) => d.customer === r.customer).map((d) => ({ ...d, inc: 0, dec: d.amount, desc: "Khách thanh toán" + (d.note ? ": " + d.note : "") })),
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
            <th style={th("right")}>Phải thu</th><th style={th("right")}>Đã thu</th><th style={th("right")}>Số dư</th>
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

/* Tab 1 — Tổng hợp công nợ phải thu theo khách (bấm 1 dòng để xem sổ chi tiết). */
function SummaryTab({ rep, rows, isMobile }) {
  const [openC, setOpenC] = useState(null);
  if (!rows.length) return <Empty>Không có công nợ phải thu nào trong kỳ này.</Empty>;
  const sum = (k) => rows.reduce((a, r) => a + (r[k] || 0), 0);
  const cols = isMobile ? 4 : 9;
  return (
    <div style={{ overflowX: "auto" }}>
      <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13 }}>
        <thead>
          <tr style={{ color: "var(--ink-4)", fontSize: 11.5, textTransform: "uppercase", letterSpacing: ".02em" }}>
            <th style={th(null, { width: 30 })}>#</th>
            <th style={th()}>Khách hàng</th>
            {!isMobile && <th style={th("right")}>Dư đầu kỳ</th>}
            {!isMobile && <th style={th("right")} title="Doanh thu = cước + dầu (+ sà lan) của các bảng kê trong kỳ, chưa VAT / chi hộ / phí khác">Doanh thu<br />(cước+dầu)</th>}
            <th style={th("right")} title="Tổng phải thu của các bảng kê trong kỳ = doanh thu + VAT + chi hộ + phí khác">Phải thu</th>
            <th style={th("right")}>Đã thu</th>
            {!isMobile && <th style={th("right")}>Dư cuối kỳ</th>}
            {!isMobile && <th style={th("right")} title="Phần còn nợ của các bảng kê đã quá hạn thanh toán">Quá hạn</th>}
            {!isMobile && <th style={th("right")} title="Số bảng kê / số đợt thu trong kỳ">Chứng từ</th>}
          </tr>
        </thead>
        <tbody>
          {rows.map((r, i) => {
            const open = openC === r.customer;
            return (
              <React.Fragment key={r.customer}>
                <tr onClick={() => setOpenC(open ? null : r.customer)} style={{ cursor: "pointer", background: open ? "var(--accent-weak-2)" : undefined }}>
                  <td style={td(null, { color: "var(--ink-4)" })}>{i + 1}</td>
                  <td style={td(null, { fontWeight: 700 })}>
                    <i className={"bi ke-noprint " + (open ? "bi-chevron-down" : "bi-chevron-right")} style={{ fontSize: 10, marginRight: 6, color: "var(--ink-4)" }} />{r.customer}
                    {isMobile && <div style={{ fontSize: 11, fontWeight: 500, color: "var(--ink-3)" }}>Đầu kỳ <Balance v={r.opening} /> · Cuối kỳ <Balance v={r.closing} strong /></div>}
                  </td>
                  {!isMobile && <td style={td("right")}><Balance v={r.opening} /></td>}
                  {!isMobile && <td className="tnum" style={td("right", { color: "#1d4ed8" })}>{money(r.base)}</td>}
                  <td className="tnum" style={td("right", { fontWeight: 600 })}>{money(r.inc)}</td>
                  <td className="tnum" style={td("right", { color: r.dec ? "var(--good)" : undefined })}>{money(r.dec)}</td>
                  {!isMobile && <td style={td("right")}><Balance v={r.closing} strong /></td>}
                  {!isMobile && <td className="tnum" style={td("right", { fontWeight: r.overdue ? 700 : 400, color: r.overdue ? "#b91c1c" : "var(--ink-4)" })}>{money(r.overdue)}</td>}
                  {!isMobile && <td className="tnum" style={td("right", { fontSize: 12, color: "var(--ink-3)", whiteSpace: "nowrap" })}>{r.nInc} BK · {r.nDec} đợt</td>}
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
            {!isMobile && <td style={td("right")}><Balance v={sum("opening")} strong /></td>}
            {!isMobile && <td className="tnum" style={td("right", { color: "#1d4ed8" })}>{money(sum("base"), { dash: false })}</td>}
            <td className="tnum" style={td("right")}>{money(sum("inc"), { dash: false })}</td>
            <td className="tnum" style={td("right", { color: "var(--good)" })}>{money(sum("dec"), { dash: false })}</td>
            {!isMobile && <td style={td("right")}><Balance v={sum("closing")} strong /></td>}
            {!isMobile && <td className="tnum" style={td("right", { color: "#b91c1c" })}>{money(sum("overdue"), { dash: false })}</td>}
            {!isMobile && <td style={td()} />}
          </tr>
        </tfoot>
      </table>
    </div>
  );
}

/* Tab 2 — Doanh thu theo bảng kê: các bảng kê có kỳ kết thúc trong kỳ (tách cước / VAT / chi hộ / phí khác). */
function IncTab({ docs }) {
  if (!docs.length) return <Empty>Không có bảng kê nào có kỳ kết thúc trong kỳ.</Empty>;
  const s = (k) => docs.reduce((a, d) => a + (d[k] || 0), 0);
  return (
    <>
      <div style={{ marginBottom: 14 }}><Stacked segments={SPLIT.map(([k, label, color]) => ({ label, value: s(k), color }))} /></div>
      <div style={{ overflowX: "auto" }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13 }}>
          <thead><tr style={{ color: "var(--ink-4)", fontSize: 11.5, textTransform: "uppercase" }}>
            <th style={th()}>Số bảng kê</th><th style={th()}>Khách hàng</th><th style={th()}>Kỳ cont ra</th>
            <th style={th("right")}>Số lô</th><th style={th("right")}>Doanh thu<br />(cước+dầu)</th><th style={th("right")}>VAT</th><th style={th("right")}>Chi hộ</th>
            <th style={th("right")} title="Phí tờ khai + các cột phí riêng của bảng kê">Phí khác</th><th style={th("right")}>Tổng phải thu</th>
          </tr></thead>
          <tbody>
            {docs.map((d) => (
              <tr key={d.hashid}>
                <td style={td(null, { whiteSpace: "nowrap" })}><DocLink d={d} /><div className="tnum" style={{ fontSize: 11, color: "var(--ink-4)" }}>lập {fmtDate(d.created)}</div></td>
                <td style={td(null, { fontWeight: 600 })}>{d.customer}</td>
                <td className="tnum" style={td(null, { fontSize: 12, color: "var(--ink-3)", whiteSpace: "nowrap" })}>{period(d) || "—"}</td>
                <td className="tnum" style={td("right")}>{d.count}</td>
                <td className="tnum" style={td("right", { color: "#1d4ed8" })}>{money(d.base)}</td>
                <td className="tnum" style={td("right")}>{money(d.vat)}</td>
                <td className="tnum" style={td("right")}>{money(d.choho)}</td>
                <td className="tnum" style={td("right")}>{money(d.other)}</td>
                <td className="tnum" style={td("right", { fontWeight: 700 })}>{fmtNum(d.total)}</td>
              </tr>
            ))}
          </tbody>
          <tfoot><tr style={{ fontWeight: 800, background: "#fafbfc" }}>
            <td style={td()} colSpan={3}>TỔNG CỘNG ({docs.length} bảng kê)</td>
            <td className="tnum" style={td("right")}>{s("count")}</td>
            <td className="tnum" style={td("right")}>{fmtNum(s("base"))}</td>
            <td className="tnum" style={td("right")}>{fmtNum(s("vat"))}</td>
            <td className="tnum" style={td("right")}>{fmtNum(s("choho"))}</td>
            <td className="tnum" style={td("right")}>{fmtNum(s("other"))}</td>
            <td className="tnum" style={td("right")}>{fmtNum(s("total"))}</td>
          </tr></tfoot>
        </table>
      </div>
    </>
  );
}

/* Tab 3 — Đã thu: các đợt khách thanh toán trong kỳ. */
function DecTab({ docs }) {
  if (!docs.length) return <Empty>Chưa ghi nhận đợt thanh toán nào của khách trong kỳ (nhập ở từng bảng kê → Thanh toán).</Empty>;
  return (
    <div style={{ overflowX: "auto" }}>
      <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13 }}>
        <thead><tr style={{ color: "var(--ink-4)", fontSize: 11.5, textTransform: "uppercase" }}>
          <th style={th(null, { width: 30 })}>#</th><th style={th()}>Ngày thu</th><th style={th()}>Khách hàng</th><th style={th()}>Bảng kê</th><th style={th()}>Diễn giải</th><th style={th("right")}>Số tiền</th>
        </tr></thead>
        <tbody>
          {docs.map((d, i) => (
            <tr key={i}>
              <td style={td(null, { color: "var(--ink-4)" })}>{i + 1}</td>
              <td className="tnum" style={td(null, { whiteSpace: "nowrap" })}>{fmtDate(d.date)}</td>
              <td style={td(null, { fontWeight: 600 })}>{d.customer}</td>
              <td style={td(null, { whiteSpace: "nowrap" })}><DocLink d={d} /></td>
              <td style={td(null, { color: "var(--ink-2)" })}>{d.note || "Khách thanh toán"}</td>
              <td className="tnum" style={td("right", { fontWeight: 700, color: "var(--good)" })}>{fmtNum(d.amount)}</td>
            </tr>
          ))}
        </tbody>
        <tfoot><tr style={{ fontWeight: 800, background: "#fafbfc" }}>
          <td style={td()} colSpan={5}>TỔNG ĐÃ THU ({docs.length} đợt)</td><td className="tnum" style={td("right", { color: "var(--good)" })}>{fmtNum(docs.reduce((a, d) => a + d.amount, 0))}</td>
        </tr></tfoot>
      </table>
    </div>
  );
}

/* Tab 4 — Còn phải thu: bảng kê còn nợ tính đến cuối kỳ, quá hạn lên đầu (để đi thu). */
function OpenTab({ docs, asOf }) {
  if (!docs.length) return <Empty>Không còn bảng kê nào phải thu tính đến cuối kỳ.</Empty>;
  return (
    <div style={{ overflowX: "auto" }}>
      <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13 }}>
        <thead><tr style={{ color: "var(--ink-4)", fontSize: 11.5, textTransform: "uppercase" }}>
          <th style={th()}>Số bảng kê</th><th style={th()}>Khách hàng</th><th style={th()}>Kỳ đến</th>
          <th style={th("right")}>Tổng phải thu</th><th style={th("right")}>Đã thu</th><th style={th("right")}>Còn phải thu</th><th style={th()}>Hạn thanh toán</th>
        </tr></thead>
        <tbody>
          {docs.map((d) => (
            <tr key={d.hashid}>
              <td style={td(null, { whiteSpace: "nowrap" })}><DocLink d={d} /></td>
              <td style={td(null, { fontWeight: 600 })}>{d.customer}</td>
              <td className="tnum" style={td(null, { whiteSpace: "nowrap", color: "var(--ink-3)" })}>{fmtDate(d.date)}</td>
              <td className="tnum" style={td("right")}>{fmtNum(d.total)}</td>
              <td className="tnum" style={td("right", { color: d.paid ? "var(--good)" : "var(--ink-4)" })}>{money(d.paid)}</td>
              <td className="tnum" style={td("right", { fontWeight: 800, color: d.late > 0 ? "#b91c1c" : "var(--ink)" })}>{fmtNum(d.remaining)}</td>
              <td style={td(null, { whiteSpace: "nowrap" })}>
                <span className="tnum" style={{ marginRight: 8, color: "var(--ink-3)" }}>{d.due ? fmtDate(d.due) : ""}</span><DueChip d={d} asOf={asOf} />
              </td>
            </tr>
          ))}
        </tbody>
        <tfoot><tr style={{ fontWeight: 800, background: "#fafbfc" }}>
          <td style={td()} colSpan={3}>TỔNG ({docs.length} bảng kê)</td>
          <td className="tnum" style={td("right")}>{fmtNum(docs.reduce((a, d) => a + d.total, 0))}</td>
          <td className="tnum" style={td("right", { color: "var(--good)" })}>{fmtNum(docs.reduce((a, d) => a + d.paid, 0))}</td>
          <td className="tnum" style={td("right")}>{fmtNum(docs.reduce((a, d) => a + d.remaining, 0))}</td>
          <td style={td()} />
        </tr></tfoot>
      </table>
    </div>
  );
}

/* Xuất Excel: Tổng hợp · Sổ chi tiết · Doanh thu theo bảng kê · Đã thu · Còn phải thu (số dạng #,##0). */
function exportExcel(rep) {
  if (typeof XLSX === "undefined") { window.trkToast && window.trkToast("Thư viện Excel chưa tải xong, thử lại sau giây lát.", "error"); return; }
  const per = { from: rep.fromYm, to: rep.toYm };
  const head = (title) => [[CO_NAME], [title], [`${periodLabel(per)} (${fmtDate(rep.from)} – ${fmtDate(rep.to)})`], []];
  const sheet = (aoa, widths) => {
    const ws = XLSX.utils.aoa_to_sheet(aoa);
    Object.keys(ws).forEach((k) => { if (k[0] !== "!" && ws[k].t === "n") ws[k].z = "#,##0"; });
    ws["!cols"] = widths.map((wch) => ({ wch }));
    return ws;
  };
  const t = rep.totals;
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, sheet([
    ...head("BẢNG TỔNG HỢP CÔNG NỢ PHẢI THU KHÁCH HÀNG"),
    ["STT", "Khách hàng", "Dư đầu kỳ", "Doanh thu (cước+dầu)", "Phải thu", "Đã thu", "Dư cuối kỳ", "Quá hạn", "Số bảng kê", "Số đợt thu"],
    ...rep.rows.map((r, i) => [i + 1, r.customer, r.opening, r.base, r.inc, r.dec, r.closing, r.overdue, r.nInc, r.nDec]),
    ["", "TỔNG CỘNG", t.opening, t.base, t.inc, t.dec, t.closing, t.overdue, "", ""],
    [], ["", "Ghi chú: Doanh thu = cước + dầu (+ sà lan); Phải thu = tổng bảng kê (doanh thu + VAT + chi hộ + phí khác) có \"đến ngày\" của kỳ cont ra trong kỳ; Đã thu = đợt khách thanh toán trong kỳ; số âm = khách trả trước."],
  ], [6, 30, 16, 18, 16, 16, 16, 15, 11, 11]), "Tổng hợp");

  const det = [...head("SỔ CHI TIẾT CÔNG NỢ PHẢI THU THEO KHÁCH HÀNG"), ["Ngày", "Chứng từ", "Diễn giải", "Phải thu", "Đã thu", "Số dư"]];
  rep.rows.forEach((r) => {
    det.push([], [r.customer], ["", "", "Dư đầu kỳ", "", "", r.opening]);
    ledgerOf(rep, r).forEach((e) => det.push([fmtDate(e.date), e.no, e.desc, e.inc || "", e.dec || "", e.bal]));
    det.push(["", "", "Cộng phát sinh", r.inc, r.dec, ""], ["", "", "Dư cuối kỳ", "", "", r.closing]);
  });
  XLSX.utils.book_append_sheet(wb, sheet(det, [12, 18, 50, 16, 16, 16]), "Sổ chi tiết");

  XLSX.utils.book_append_sheet(wb, sheet([
    ...head("PHẢI THU THEO BẢNG KÊ — KỲ CONT RA KẾT THÚC TRONG KỲ"),
    ["Số bảng kê", "Ngày lập", "Khách hàng", "Kỳ từ", "Kỳ đến", "Số lô", "Doanh thu (cước+dầu)", "VAT", "Chi hộ", "Phí khác", "Tổng phải thu"],
    ...rep.incDocs.map((d) => [d.no, fmtDate(d.created), d.customer, fmtDate(d.from), fmtDate(d.to), d.count, d.base, d.vat, d.choho, d.other, d.total]),
    ["TỔNG CỘNG", "", "", "", "", t.lots, t.base, t.vat, t.choho, t.other, t.inc],
  ], [18, 12, 26, 12, 12, 8, 15, 13, 15, 14, 16]), "Phải thu theo BK");

  XLSX.utils.book_append_sheet(wb, sheet([
    ...head("ĐÃ THU — KHÁCH THANH TOÁN TRONG KỲ"),
    ["STT", "Ngày thu", "Khách hàng", "Số bảng kê", "Diễn giải", "Số tiền"],
    ...rep.decDocs.map((d, i) => [i + 1, fmtDate(d.date), d.customer, d.no, d.note || "Khách thanh toán", d.amount]),
    ["", "", "", "", "TỔNG CỘNG", t.dec],
  ], [6, 12, 26, 18, 40, 16]), "Đã thu");

  XLSX.utils.book_append_sheet(wb, sheet([
    ...head("BẢNG KÊ CÒN PHẢI THU TÍNH ĐẾN CUỐI KỲ"),
    ["Số bảng kê", "Khách hàng", "Kỳ đến", "Tổng phải thu", "Đã thu", "Còn phải thu", "Hạn thanh toán", "Trạng thái"],
    ...rep.openDocs.map((d) => [d.no, d.customer, fmtDate(d.date), d.total, d.paid, d.remaining, d.due ? fmtDate(d.due) : "", dueState(d, rep.asOf).label]),
    ["TỔNG", "", "", rep.openDocs.reduce((a, d) => a + d.total, 0), rep.openDocs.reduce((a, d) => a + d.paid, 0), rep.openDocs.reduce((a, d) => a + d.remaining, 0), "", ""],
  ], [18, 26, 12, 16, 16, 16, 14, 18]), "Còn phải thu");

  XLSX.writeFile(wb, `doanh-thu-phai-thu-${periodSlug(per)}.xlsx`);
}

const TABS = [
  ["sum", "Tổng hợp công nợ", "bi-journal-text"],
  ["inc", "Phải thu theo bảng kê", "bi-receipt"],
  ["dec", "Đã thu", "bi-cash-coin"],
  ["open", "Còn phải thu", "bi-hourglass-split"],
];

/* Chọn KỲ: Tháng (‹ › từng tháng) · Quý (‹ › từng quý) · Khoảng tháng (từ tháng … đến tháng …). */
const sel = { fontSize: 13, padding: "6px 8px", border: "1px solid var(--line)", borderRadius: 8, background: "#fff", cursor: "pointer" };
function MonthSelect({ value, onChange, years }) {
  const { y, m } = ymParts(value);
  return (
    <span style={{ display: "inline-flex", gap: 4 }}>
      <select value={m} onChange={(e) => onChange(ymStr(y, +e.target.value))} style={sel}>
        {Array.from({ length: 12 }, (_, i) => <option key={i} value={i + 1}>Tháng {i + 1}</option>)}
      </select>
      <select value={y} onChange={(e) => onChange(ymStr(+e.target.value, m))} style={sel}>{years.map((x) => <option key={x} value={x}>{x}</option>)}</select>
    </span>
  );
}
function PeriodPicker({ value, onChange, years }) {
  const [mode, setMode] = useState(() => (value.from === value.to ? "month" : isQuarter(value) ? "quarter" : "range"));
  const nowYm = ymStr(new Date().getFullYear(), new Date().getMonth() + 1);
  const pick = (k) => {
    setMode(k);
    if (k === "month") onChange({ from: value.to, to: value.to });
    if (k === "quarter") { const { y, q } = quarterOf(value.to); onChange(quarterRange(y, q)); }
  };
  const step = mode === "quarter" ? 3 : 1;
  const shift = (d) => onChange({ from: ymShift(value.from, d * step), to: ymShift(value.to, d * step) });
  const qv = quarterOf(value.from);
  return (
    <div style={{ display: "flex", alignItems: "center", gap: 6, flexWrap: "wrap" }}>
      <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 9, padding: 3, gap: 1 }}>
        {[["month", "Tháng"], ["quarter", "Quý"], ["range", "Khoảng tháng"]].map(([k, lb]) => (
          <button key={k} type="button" onClick={() => pick(k)}
            style={{ border: "none", cursor: "pointer", fontSize: 12, fontWeight: 600, padding: "5px 10px", borderRadius: 7, background: mode === k ? "#fff" : "transparent", color: mode === k ? "var(--accent)" : "var(--ink-3)", boxShadow: mode === k ? "0 1px 2px rgba(16,19,23,.14)" : "none" }}>{lb}</button>
        ))}
      </div>
      {mode !== "range" && <button type="button" style={btn} onClick={() => shift(-1)} title={mode === "quarter" ? "Quý trước" : "Tháng trước"}><i className="bi bi-chevron-left" /></button>}
      {mode === "month" && <MonthSelect value={value.from} years={years} onChange={(v) => onChange({ from: v, to: v })} />}
      {mode === "quarter" && (
        <span style={{ display: "inline-flex", gap: 4 }}>
          <select value={qv.q} onChange={(e) => onChange(quarterRange(qv.y, +e.target.value))} style={sel}>{[1, 2, 3, 4].map((x) => <option key={x} value={x}>Quý {x}</option>)}</select>
          <select value={qv.y} onChange={(e) => onChange(quarterRange(+e.target.value, qv.q))} style={sel}>{years.map((x) => <option key={x} value={x}>{x}</option>)}</select>
        </span>
      )}
      {mode === "range" && (
        <>
          <span style={{ fontSize: 12.5, color: "var(--ink-3)", fontWeight: 600 }}>Từ</span>
          <MonthSelect value={value.from} years={years} onChange={(v) => onChange({ from: v, to: v > value.to ? v : value.to })} />
          <span style={{ fontSize: 12.5, color: "var(--ink-3)", fontWeight: 600 }}>đến</span>
          <MonthSelect value={value.to} years={years} onChange={(v) => onChange({ from: v < value.from ? v : value.from, to: v })} />
        </>
      )}
      {mode !== "range" && <button type="button" style={btn} onClick={() => shift(1)} title={mode === "quarter" ? "Quý sau" : "Tháng sau"}><i className="bi bi-chevron-right" /></button>}
      <button type="button" style={btn} onClick={() => (mode === "quarter" ? onChange(quarterRange(quarterOf(nowYm).y, quarterOf(nowYm).q)) : onChange({ from: mode === "range" ? value.from : nowYm, to: nowYm }))}>
        {mode === "quarter" ? "Quý này" : mode === "range" ? "Đến tháng này" : "Tháng này"}
      </button>
    </div>
  );
}

function ReceivableReportApp() {
  const isMobile = useIsMobile();
  const init = B.report || {};
  const now = new Date();
  const nowYm = ymStr(now.getFullYear(), now.getMonth() + 1);
  const [per, setPer] = useState({ from: init.fromYm || nowYm, to: init.toYm || nowYm });
  const [rep, setRep] = useState(init.rows ? init : null);
  const [loading, setLoading] = useState(false);
  const [tab, setTab] = useState("sum");
  const [q, setQ] = useState("");

  const first = useRef(true);
  useEffect(() => {
    if (first.current && rep) { first.current = false; return; }
    first.current = false;
    setLoading(true);
    window.trkApi("GET", ROUTES.data + "?from=" + per.from + "&to=" + per.to)
      .then((r) => { if (r && r.ok) setRep(r.report); setLoading(false); })
      .catch(() => { setLoading(false); window.trkToast && window.trkToast("Lỗi tải báo cáo", "error"); });
  }, [per.from, per.to]);

  const match = (v) => !q.trim() || (v || "").toLowerCase().includes(q.trim().toLowerCase());
  const R = rep || { rows: [], incDocs: [], decDocs: [], openDocs: [], totals: {}, prev: {} };
  const rows = useMemo(() => R.rows.filter((r) => match(r.customer)), [rep, q]);
  const incDocs = useMemo(() => R.incDocs.filter((d) => match(d.customer) || match(d.no)), [rep, q]);
  const decDocs = useMemo(() => R.decDocs.filter((d) => match(d.customer) || match(d.no)), [rep, q]);
  const openDocs = useMemo(() => R.openDocs.filter((d) => match(d.customer) || match(d.no)), [rep, q]);
  const t = R.totals;
  const nLate = R.openDocs.filter((d) => d.late > 0).length;
  const pl = periodLabel(per);
  const years = []; for (let y = now.getFullYear() + 1; y >= 2024; y--) years.push(y);
  const prevLabel = "so " + (per.from === per.to ? "tháng" : isQuarter(per) ? "quý" : `${monthsOf(per)} tháng`) + " trước";

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <style>{`@media print { .trk-report-scroll { overflow: visible !important; height: auto !important; } .ke-print table { font-size: 11px !important; } }`}</style>
      <header className="ke-noprint" style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: isMobile ? "10px 14px" : "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 10, minHeight: 58, flexWrap: "wrap", padding: isMobile ? "4px 0" : "8px 0" }}>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center", flexShrink: 0 }}><i className="bi bi-graph-up-arrow" /></div>
          <div>
            <div style={{ fontSize: 15.5, fontWeight: 700 }}>Báo cáo doanh thu phải thu</div>
            <div style={{ fontSize: 11.5, color: "var(--ink-3)" }}>Từ bảng kê thu theo tháng / quý / khoảng tháng · dư đầu kỳ, phải thu, đã thu, còn phải thu</div>
          </div>
          <div style={{ flex: 1 }} />
          <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
            <PeriodPicker value={per} onChange={setPer} years={years} />
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
            <div style={{ fontSize: 17, fontWeight: 800, marginTop: 6 }}>BÁO CÁO DOANH THU PHẢI THU — {pl.toUpperCase()}</div>
            <div style={{ fontSize: 12, color: "var(--ink-3)" }}>Từ {fmtDate(R.from)} đến {fmtDate(R.to)} · in ngày {new Date().toLocaleDateString("vi-VN")} · {TABS.find((x) => x[0] === tab)[1]}</div>
          </div>

          <div style={{ display: "flex", gap: 10, flexWrap: "wrap", marginBottom: 14 }}>
            <KPI label="Dư đầu kỳ" value={fmtVND(t.opening || 0)} sub={`Khách còn nợ trước ngày ${fmtDate(R.from) || "đầu kỳ"}`} />
            <KPI label="Doanh thu (cước+dầu)" value={fmtVND(t.base || 0)} color="#1d4ed8" cur={t.base} prev={R.prev && R.prev.base} goodWhen="up" deltaLabel={prevLabel}
              sub={`${R.incDocs.length} bảng kê · ${t.lots || 0} lô · chưa gồm VAT, chi hộ, phí khác`} />
            <KPI label="Phải thu phát sinh" value={fmtVND(t.inc || 0)} cur={t.inc} prev={R.prev && R.prev.inc} goodWhen="up" deltaLabel={prevLabel}
              sub={`Doanh thu ${fmtNum(t.base || 0)} + VAT ${fmtNum(t.vat || 0)} + chi hộ ${fmtNum(t.choho || 0)} + khác ${fmtNum(t.other || 0)}`} />
            <KPI label="Đã thu" value={fmtVND(t.dec || 0)} color="var(--good)" cur={t.dec} prev={R.prev && R.prev.dec} goodWhen="up" deltaLabel={prevLabel}
              sub={`${R.decDocs.length} đợt khách thanh toán`} />
            <KPI label="Còn phải thu cuối kỳ" value={fmtVND(t.closing || 0)} color={(t.closing || 0) > 0 ? "var(--warn)" : "var(--good)"}
              sub={<>{R.rows.filter((r) => r.closing > 0).length} khách còn nợ{t.overdue ? <> · <b style={{ color: "#b91c1c" }}>quá hạn {fmtNum(t.overdue)} ({nLate} bảng kê)</b></> : null}</>} />
          </div>

          <div style={card}>
            <div className="ke-noprint" style={{ display: "flex", alignItems: "center", gap: 4, marginBottom: 12, flexWrap: "wrap", borderBottom: "1px solid var(--line)" }}>
              {TABS.map(([k, label, icon]) => {
                const n = { sum: rows.length, inc: incDocs.length, dec: decDocs.length, open: openDocs.length }[k];
                return (
                  <button key={k} type="button" onClick={() => setTab(k)}
                    style={{ border: "none", background: "transparent", cursor: "pointer", padding: "8px 12px 11px", fontSize: 13, fontWeight: 600, marginBottom: -1,
                      color: tab === k ? "var(--accent)" : "var(--ink-3)", borderBottom: "2px solid " + (tab === k ? "var(--accent)" : "transparent") }}>
                    <i className={"bi " + icon} style={{ marginRight: 5 }} />{label}<span className="tnum" style={{ marginLeft: 6, fontSize: 11.5, color: k === "open" && nLate ? "#b91c1c" : "var(--ink-4)" }}>{n}</span>
                  </button>
                );
              })}
              <div style={{ flex: 1 }} />
              <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Tìm khách hàng / số bảng kê…"
                style={{ fontSize: isMobile ? 16 : 13, padding: "6px 10px", border: "1px solid var(--line)", borderRadius: 8, width: isMobile ? "100%" : 230, marginBottom: 6 }} />
            </div>
            {!rep ? <Empty>Đang tải…</Empty>
              : tab === "sum" ? <SummaryTab rep={R} rows={rows} isMobile={isMobile} />
              : tab === "inc" ? <IncTab docs={incDocs} />
              : tab === "dec" ? <DecTab docs={decDocs} />
              : <OpenTab docs={openDocs} asOf={R.asOf} />}
            <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 12, lineHeight: 1.55 }}>
              <b>Doanh thu</b> = cước + dầu (+ sà lan) của các lô. <b>Phải thu</b> = tổng phải thu các <b>bảng kê thu</b> có <b>"đến ngày" của kỳ cont ra</b> trong kỳ (chưa có kỳ thì theo ngày lập) = doanh thu + VAT + chi hộ + phí khác (phí tờ khai, cột phí riêng), đúng số đã chốt khi lưu bảng kê.
              {" "}<b>Đã thu</b> = các <b>đợt thanh toán</b> có ngày thu trong kỳ (nhập ở từng bảng kê). Dư cuối kỳ = dư đầu kỳ + phải thu − đã thu; số trong ngoặc là khách trả trước.
              {" "}<b>Quá hạn</b> tính theo hạn thanh toán của từng bảng kê, mốc là cuối kỳ (kỳ chưa hết: hôm nay).
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

createRoot(document.getElementById("trk-root")).render(<ReceivableReportApp />);
