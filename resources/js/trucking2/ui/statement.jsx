import React from "react";
const { useState, useMemo, useEffect } = React;
import { I, fmtVND, fmtNum, fmtShort, fmtDate, calcCost, calcRev, calcVeh, calcVehICD, calcRevICD, calcFreeTime, fmtHours, toNum, Modal, Btn, Combo, useIsMobile, DateField, statementAmounts, lineAmounts, STATEMENT_VAT_RATES } from "@trk/lib.jsx";
import { CostPopup, RevenuePopup, CostPopupICD, RevenuePopupICD, InfoPopup, ConfigPopup, PriceList, TRACK_COLORS, colorHex } from "@trk/pop.jsx";
import { SortBtn, CellBtn, Badge, EditCell, TH, TD } from "./primitives.jsx";

/* Nhãn ngắn cho 1 bảng giá (price book) đã dùng định giá lô — hiện kỳ giá ở bảng kê. */
const _bd = (s) => { if (!s) return ""; const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s); return m ? `${m[3]}/${m[2]}/${m[1]}` : s; };
const bookLabel = (b) => { if (!b) return ""; const r = (b.from || b.to) ? `${b.from ? _bd(b.from) : "…"}–${b.to ? _bd(b.to) : "…"}` : "mọi ngày"; return (b.label ? b.label + " · " : "") + r; };
// Nhãn cột giá đã áp: "40HC" (cột riêng) · "40HC → 40FT" (rơi về cột chung) · snapshot cũ chỉ có is20 → 20FT/40FT.
const contColLabel = (d) => {
  if (!d) return "";
  if (d.contKey) return d.contType && d.contType.toUpperCase() !== d.contKey ? `${d.contType} → ${d.contKey}` : d.contKey;
  if (d.is20 != null) return d.is20 ? "20FT" : "40FT";
  return d.contType || "—";
};

/* ===== GIÁ TÙY CHỈNH + GỢI Ý "TÍNH LẠI" (dùng chung trang xem + shell bang-ke-xem) =====
   - Dòng tùy chỉnh: detail.manualBase = nền người dùng đặt; cuoc/dau/sà lan trong detail vẫn là số hệ thống tính.
   - "Tính lại" chỉ trả GỢI Ý (suggestById[lineId] = {found, ...pr}); số đã lưu KHÔNG đổi cho tới khi bấm Áp dụng. */
const isManualLine = (l) => !!(l && l.detail && typeof l.detail === "object" && l.detail.manualBase != null && l.detail.manualBase !== "");
/* Nền hệ thống tính trong 1 snapshot (cuoc+dau+sà lan); null nếu snapshot không có giá. */
const sysBaseOf = (d) => (d && ("cuoc" in d || "dau" in d || "bargeCuoc" in d || "bargeDau" in d))
  ? Math.round((+d.cuoc || 0) + (+d.dau || 0) + (+d.bargeCuoc || 0) + (+d.bargeDau || 0)) : null;
/* Gợi ý cho 1 dòng từ kết quả Tính lại: null = không khác gì số đang dùng. */
const suggestionOf = (l, s, vatRate) => {
  if (!l || !s || !s.found) return null;
  const a = lineAmounts(l, vatRate);
  const base = sysBaseOf(s) ?? 0, choho = Math.round(+s.chiHo || 0);
  const od = (l.detail && typeof l.detail === "object") ? l.detail : {};
  // Dòng giá tùy chỉnh thu theo số gõ tay → so giá hệ thống MỚI với giá hệ thống trong snapshot (chỉ để báo),
  // không so với số gõ tay — nếu không, dòng gõ tay lúc nào cũng "có giá mới".
  const manual = isManualLine(l);
  const curBase = manual ? (sysBaseOf(od) ?? 0) : a.base;
  const info = ["route", "conn", "kind", "contKey"].some((k) => (s[k] ?? "") !== (od[k] ?? ""));
  if (base === curBase && choho === a.choho && !info) return null;
  return { pr: s, base, choho, manual, baseDiff: base !== curBase, chohoDiff: choho !== a.choho, infoOnly: base === curBase && choho === a.choho };
};
/* Áp dụng gợi ý vào dòng: snapshot mới thay snapshot cũ (giữ VAT riêng dòng, phí riêng). Dòng GIÁ TÙY CHỈNH giữ nguyên
   giá gõ tay — chỉ cập nhật chi hộ + thông tin hệ thống; muốn về giá hệ thống thì bấm ↺ ở dòng đó. */
const applySuggestion = (l, s) => {
  if (!l || !s || !s.found) return l;
  const d = { ...s }; delete d.found; delete d.manualBase;
  const od = (l.detail && typeof l.detail === "object") ? l.detail : {};
  if (od.vat != null && od.vat !== "") d.vat = od.vat; else delete d.vat;
  if (od.extras && typeof od.extras === "object") d.extras = od.extras; else delete d.extras;   // phí riêng từng lô (cột chi phí riêng) giữ nguyên
  if (isManualLine(l)) { const mb = Math.round(+od.manualBase || 0); d.manualBase = mb; return { ...l, phaiThu: mb, cuoc: mb, detail: d }; }
  const base = sysBaseOf(d) ?? 0;
  return { ...l, phaiThu: base, cuoc: base, detail: d };
};
/* Lô ứng viên (API candidates / lô mới khi Tính lại) → dòng bảng kê (cùng hình dạng trang tạo lưu). */
const candidateToLine = (x) => {
  const base = sysBaseOf(x.pr) ?? 0;
  return {
    id: x.id, booking: x.booking, sheet: x.sheet, io: x.io,
    declNo: x.declNo || "", contType: x.contType || "", inv: x.inv || "", contNo: x.contNo || "", bks: x.bks || "",
    from: x.from, to: x.to, date: x.date, contLabel: x.contLabel,
    phaiThu: base, cuoc: base, thanhLy: x.thanhLy || 0, note: x.note, infoNote: x.infoNote || "",
    detail: { ...x.pr },
  };
};

/* HẠN THANH TOÁN của bảng kê: lưu ở info.dueDate (Y-m-d) cùng bảng kê; chưa đặt thì gợi ý = ngày lập + số ngày
   "Hạn TT" của khách (snapshot info.termDays lúc lập, không có thì Hạn TT hiện tại customerTermDays).
   Khớp backend statementDueDate (danh sách + Excel). */
const addDaysIso = (iso, n) => { const d = new Date(String(iso || "").slice(0, 10) + "T00:00:00Z"); if (isNaN(d) || !n) return ""; d.setUTCDate(d.getUTCDate() + n); return d.toISOString().slice(0, 10); };
const daysBetweenIso = (a, b) => Math.round((Date.parse(String(b).slice(0, 10) + "T00:00:00Z") - Date.parse(String(a).slice(0, 10) + "T00:00:00Z")) / 86400000);
const defaultDue = (date, info) => addDaysIso(date, parseInt((info || {}).termDays, 10) || 0);
const dueOf = (st) => { const info = st.info || {}; return info.dueDate || defaultDue(st.date, { termDays: info.termDays || st.customerTermDays }); };
const DUE_DAY_OPTIONS = [7, 15, 30, 45, 60, 90];
/* Ô hạn thanh toán: chọn ngày, hoặc chọn nhanh "ngày lập + N ngày". */
function DueDateField({ base, value, onChange, width = 140 }) {
  const n = value && base ? daysBetweenIso(base, value) : null;
  return (
    <span style={{ display: "inline-flex", alignItems: "center", gap: 6, whiteSpace: "nowrap" }}>
      <div style={{ width }}><DateField value={value || ""} onChange={(v) => onChange(v || "")} /></div>
      <select value={DUE_DAY_OPTIONS.includes(n) ? String(n) : ""} onChange={(e) => e.target.value && onChange(addDaysIso(base, +e.target.value))}
        title="Chọn nhanh: hạn = ngày lập + số ngày"
        style={{ padding: "6px 8px", fontSize: 12.5, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: n != null ? "var(--ink-2)" : "var(--ink-4)", cursor: "pointer", outline: "none" }}>
        <option value="">{n != null && n > 0 && !DUE_DAY_OPTIONS.includes(n) ? `${n} ngày` : "Số ngày…"}</option>
        {DUE_DAY_OPTIONS.map((d) => <option key={d} value={d}>{d} ngày</option>)}
      </select>
    </span>
  );
}
/* Ô HẠN THANH TOÁN dưới "CÒN PHẢI THU" (trang xem/sửa): chọn ngày / "ngày lập + N ngày" + nhãn Quá hạn / Còn N ngày /
   Đã thu đủ (cùng quy tắc dueStatus của danh sách); ghi info.dueDate, chốt khi bấm Lưu. In ra ngày. */
function DueSummaryRow({ st, tongThu, payments, onChange }) {
  const due = dueOf(st);
  const s = dueStatus({ tongThu, payments, dueDate: due }, localTodayIso());
  const term = (st.info || {}).termDays || st.customerTermDays;
  return (
    <div style={{ marginTop: 10, paddingTop: 10, borderTop: "1px dashed var(--line)" }}>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: 8, marginBottom: 6, fontSize: 13 }}>
        <span style={{ color: "var(--ink-3)" }} title={term ? `Chưa đặt thì = ngày lập + ${term} ngày (Hạn TT của khách)` : "Khách chưa cài Hạn TT mặc định (Cài đặt › Khách hàng) — chọn ngày hoặc số ngày"}>Hạn thanh toán</span>
        <span className="ke-noprint">{(due || s.key === "paid") && <DueChip s={s} />}</span>
        <b className="ke-printonly tnum" style={{ display: "none" }}>{due ? fmtDate(due) : "—"}</b>
      </div>
      <div className="ke-noprint"><DueDateField base={st.date} value={due} onChange={onChange} /></div>
    </div>
  );
}

/* Dòng thông tin khách trên bảng kê: MST · địa chỉ · liên hệ · hạn thanh toán. */
function CustomerInfoLine({ info, due, base }) {
  const days = due && base ? daysBetweenIso(base, due) : 0;
  return (
    <div style={{ color: "var(--ink-2)", marginTop: 3, display: "flex", gap: 16, flexWrap: "wrap" }}>
      {info.taxCode && <span>MST: <b className="tnum">{info.taxCode}</b></span>}
      {info.address && <span>Địa chỉ: {info.address}</span>}
      {info.contact && <span>Liên hệ: {info.contact}</span>}
      {due ? <span>Hạn thanh toán: <b className="tnum">{fmtDate(due)}</b>{days > 0 ? <span style={{ color: "var(--ink-4)" }}> ({days} ngày)</span> : null}</span>
        : (info.termDays ? <span>Hạn TT: {info.termDays} ngày</span> : null)}
    </div>
  );
}

/* "Ghi chú tự do cho lô hàng" (popup Thông tin lô) — dùng chung trang tạo + trang xem bảng kê; có in. */
function LotNote({ text }) {
  const t = String(text || "").trim();
  if (!t) return null;
  return (
    <div style={{ display: "flex", gap: 6, alignItems: "flex-start", fontSize: 12, color: "var(--ink-2)", background: "#fffaf0", border: "1px solid #f3e2b8", borderRadius: 7, padding: "5px 9px", margin: "2px 0 6px", whiteSpace: "pre-wrap", lineHeight: 1.5 }}>
      <i className="bi bi-sticky" style={{ color: "#b7791f", marginTop: 1 }} />
      <span><b style={{ color: "#9a6700" }}>Ghi chú:</b> {t}</span>
    </div>
  );
}

/* Thông tin công ty cho header bảng kê (màn hình + bản in). */
const CO = (window.__TRK && window.__TRK.boot && window.__TRK.boot.company) || {};
const ROUTES_TRK = (window.__TRK && window.__TRK.routes) || {};
const CO_NAME = CO.name || "MBF JOINT STOCK COMPANY";
const CO_SUB = [CO.website, CO.phone].filter(Boolean).join(" · ") || "http://mbf.com.vn · 84-24-39449616";

/* Lọc lô theo NHẬP / XUẤT (io của lô: "Nhập" | "Xuất"). Dùng ở trang tạo (lọc ứng viên) và trang xem (lọc hiển thị). */
const ioKey = (io) => { const v = String(io || "").toLowerCase(); return /xu/.test(v) ? "xuat" : /nh/.test(v) ? "nhap" : ""; };
function IoSeg({ value, onChange, counts }) {
  return (
    <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 9, padding: 3, gap: 1 }}>
      {[["all", "Tất cả"], ["nhap", "Nhập"], ["xuat", "Xuất"]].map(([k, lb]) => {
        const on = value === k; const n = counts ? counts[k] : null;
        return (
          <button key={k} type="button" onClick={() => onChange(k)} title={k === "all" ? "Mọi lô" : `Chỉ lô hàng ${lb.toLowerCase()}`}
            style={{ border: "none", cursor: "pointer", fontSize: 12, fontWeight: 600, padding: "5px 11px", borderRadius: 7, whiteSpace: "nowrap",
              background: on ? "#fff" : "transparent", color: on ? "var(--accent)" : "var(--ink-3)", boxShadow: on ? "0 1px 2px rgba(16,19,23,.14)" : "none" }}>
            {lb}{n != null ? <span className="tnum" style={{ marginLeft: 5, fontSize: 10.5, fontWeight: 700, color: "var(--ink-4)" }}>{n}</span> : null}
          </button>
        );
      })}
    </div>
  );
}
const ioCounts = (lines) => { const c = { all: 0, nhap: 0, xuat: 0 }; (lines || []).forEach((l) => { c.all++; const k = ioKey(l.io); if (k) c[k]++; }); return c; };

/* CỘT CHI PHÍ RIÊNG của bảng kê (extraCols [{key,label,vat}]) — dùng chung trang tạo + trang xem.
   Lưu cùng bảng kê, KHÔNG khai trong Cài đặt; số từng lô ở detail.extras[key]; vat = khoản chịu VAT% của bảng kê. */
const colLabel = (c) => String((c && c.label) || "").trim() || "Khoản mới";
const newExtraCol = () => ({ key: "x" + Date.now().toString(36), label: "", vat: true });
function ExtraColsBar({ cols, onAdd, onChange, onDelete }) {
  return (
    <div className="ke-noprint" style={{ display: "flex", alignItems: "center", gap: 7, fontSize: 12.5, color: "var(--ink-3)", flexWrap: "wrap" }}>
      <span style={{ fontWeight: 600 }} title="Khoản thu thêm theo từng lô, riêng cho bảng kê này (không cần khai trong Cài đặt). Thêm cột rồi điền số ở từng lô; lưu cùng bảng kê.">Cột chi phí riêng</span>
      {cols.map((c) => (
        <span key={c.key} style={{ display: "inline-flex", alignItems: "center", gap: 5, padding: "3px 5px 3px 8px", border: "1px solid " + (String(c.label || "").trim() ? "var(--line)" : "var(--warn)"), borderRadius: 8, background: "#fff" }}>
          <input value={c.label || ""} onChange={(e) => onChange(c.key, { label: e.target.value })} placeholder="Tên khoản (VD: Lưu ca)"
            title={String(c.label || "").trim() ? "Tên cột — hiện ở bảng, bản in và Excel" : "Chưa đặt tên — cột sẽ không được lưu"}
            style={{ width: 150, border: "none", outline: "none", fontSize: 12.5, fontWeight: 600, color: "var(--ink)", background: "transparent" }} />
          <label style={{ display: "inline-flex", alignItems: "center", gap: 3, fontSize: 11, cursor: "pointer", whiteSpace: "nowrap", color: c.vat ? "var(--accent)" : "var(--ink-4)" }} title="Tích = khoản này chịu VAT% của bảng kê (như cước); bỏ tích = không VAT (như chi hộ)">
            <input type="checkbox" checked={!!c.vat} onChange={(e) => onChange(c.key, { vat: e.target.checked })} /> VAT
          </label>
          <button type="button" onClick={() => onDelete(c.key)} title="Xóa cột (bỏ cả số đã điền ở các lô)"
            style={{ border: "none", background: "transparent", color: "var(--ink-4)", cursor: "pointer", fontSize: 13, padding: "0 3px", lineHeight: 1 }}>✕</button>
        </span>
      ))}
      <button type="button" onClick={onAdd}
        style={{ display: "inline-flex", alignItems: "center", gap: 5, padding: "4px 9px", fontSize: 12, fontWeight: 600, border: "none", borderRadius: 7, background: "var(--accent-weak)", color: "var(--accent)", cursor: "pointer" }}>
        <I.plus /> Thêm cột
      </button>
    </div>
  );
}
/* Ô số nhập tay cho 1 cột chi phí riêng của 1 lô (màn hình) + số để in. */
function ExtraCell({ col, value, onChange }) {
  return (<>
    <span className="ke-noprint"><span style={{ position: "relative", display: "inline-block", width: 110 }}>
      <input inputMode="numeric" value={value ? value.toLocaleString("vi-VN") : ""} placeholder="0" className="tnum"
        onChange={(e) => onChange(parseInt(e.target.value.replace(/[^\d]/g, ""), 10) || 0)}
        title={colLabel(col) + " của lô này" + (col.vat ? " (chịu VAT)" : " (không VAT)")}
        style={{ width: "100%", padding: "6px 20px 6px 8px", fontSize: 12.5, textAlign: "right", fontWeight: 600, border: "1px solid var(--line)", background: "#fff", borderRadius: 7, outline: "none" }}
        onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
      <span style={{ position: "absolute", right: 7, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 12, pointerEvents: "none" }}>₫</span>
    </span></span>
    <span style={{ display: "none" }} className="ke-printonly">{fmtNum(value || 0)}</span>
  </>);
}

/* Ô GHI CHÚ của 1 lô trong bảng kê (người dùng gõ) — lưu line.userNote; in ra chữ; Excel cột Note
   (trống thì Excel dùng ghi chú tự sinh như cũ). */
function LineNoteCell({ value, onChange }) {
  return (<>
    <textarea className="ke-noprint" rows={1} value={value || ""} onChange={(e) => onChange(e.target.value)} placeholder="Ghi chú…"
      style={{ display: "block", width: 170, minHeight: 32, padding: "6px 8px", fontSize: 12.5, lineHeight: 1.4, fontFamily: "inherit", border: "1px solid var(--line)", borderRadius: 7, background: "#fff", outline: "none", resize: "vertical" }}
      onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
    <span style={{ display: "none", whiteSpace: "pre-wrap" }} className="ke-printonly">{value || ""}</span>
  </>);
}

/* CHI PHÍ CÔNG TY của 1 lô (khoản không tích "chi hộ khách" — vd cước thuê xe ngoài kèm tên nhà xe) để người lập
   bảng kê đầu ra thấy chi phí đầu vào của cont. Không in. block = hiện thành 1 dòng riêng (trang tạo). */
function LotCostLine({ items, block }) {
  const list = (items || []).filter((c) => !c.billable && +c.amount);
  if (!list.length) return null;
  const total = list.reduce((s, c) => s + (+c.amount || 0), 0);
  const body = <>Chi phí công ty (không thu khách): {list.map((c, j) => <React.Fragment key={j}>{j ? " · " : ""}{c.item}{c.vendor ? <b> ({c.vendor})</b> : null} <b className="tnum">{fmtNum(c.amount)}</b></React.Fragment>)}{list.length > 1 ? <> = <b className="tnum">{fmtNum(total)}</b></> : null}</>;
  return block
    ? <div style={{ color: "#7c3aed" }}><i className="bi bi-receipt" /> {body}</div>
    : <span style={{ color: "#7c3aed" }}>· {body}</span>;
}

/* Ô CHI HỘ của 1 lô: tổng + TỪNG HẠNG MỤC (snapshot choHoItems = khoản chi phí lô đã tích "chi hộ khách"),
   gộp khoản trùng tên; có in. Bảng kê cũ không có danh sách hạng mục → chỉ hiện tổng. */
function ChoHoCell({ amount, items }) {
  const byName = new Map();
  (Array.isArray(items) ? items : []).forEach((c) => {
    const amt = Math.round(+c.amount || 0); if (!amt) return;
    const k = String(c.item || "").trim() || "(khoản)";
    byName.set(k, (byName.get(k) || 0) + amt);
  });
  return (<>
    <div>{fmtNum(amount)}</div>
    {byName.size > 0 && (
      <div style={{ marginTop: 3, display: "flex", flexDirection: "column", alignItems: "flex-end", gap: 1, fontSize: 10.5, lineHeight: 1.35, fontWeight: 400, color: "var(--ink-3)" }}>
        {[...byName].map(([k, v]) => <span key={k} title={`${k}: ${fmtNum(v)}`} style={{ whiteSpace: "nowrap" }}>{k} <b className="tnum" style={{ fontWeight: 600, color: "var(--ink-2)" }}>{fmtNum(v)}</b></span>)}
      </div>
    )}
  </>);
}

/* Select % VAT cho bảng kê (cấp statement). VAT chỉ áp nền cước+dầu+sà lan. */
function VatSelect({ value, onChange }) {
  return (
    <select value={String(value || 0)} onChange={(e) => onChange(parseFloat(e.target.value) || 0)}
      style={{ padding: "6px 10px", fontSize: 13, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink)", cursor: "pointer", outline: "none" }}>
      {STATEMENT_VAT_RATES.map((r) => <option key={r} value={r}>{r}%</option>)}
    </select>
  );
}

/* Khối tổng tiền 4 dòng (dùng chung tạo + xem): nền · VAT · chi hộ · tổng (+ công nợ nếu có). */
/* extras: [{key,label,vat,sum}] — cột chi phí riêng của bảng kê (đã gồm trong amt.total). */
function AmountSummary({ amt, vatRate, paid, payCount, showDebt, extras = [], children }) {
  const con = (amt.total || 0) - (paid || 0);
  const row = (label, val, opt = {}) => (
    <div style={{ display: "flex", justifyContent: "space-between", fontSize: 13, marginBottom: 6, ...(opt.wrap || {}) }}>
      <span style={{ color: "var(--ink-3)", ...(opt.labelStyle || {}) }}>{label}</span>
      <b className="tnum" style={opt.valStyle || {}}>{fmtVND(val)}</b>
    </div>
  );
  return (
    <div style={{ width: 320, background: "#fafbfc", border: "1px solid var(--line)", borderRadius: 10, padding: "12px 16px" }}>
      {row("Phải thu (cước + dầu)", amt.base)}
      {row(`VAT (${vatRate || 0}%)`, amt.vat, { valStyle: { color: (vatRate ? "var(--accent)" : "var(--ink-4)") } })}
      {row("Chi hộ (không VAT)", amt.choho)}
      {extras.map((c) => <React.Fragment key={c.key}>{row((String(c.label || "").trim() || "Khoản mới") + (c.vat ? " (chịu VAT)" : " (không VAT)"), c.sum)}</React.Fragment>)}
      <div style={{ display: "flex", justifyContent: "space-between", fontSize: 15, paddingTop: 8, marginBottom: showDebt ? 8 : 0, borderTop: "1px solid var(--line)" }}>
        <span style={{ fontWeight: 700 }}>TỔNG TIỀN</span><b className="tnum">{fmtVND(amt.total)}</b>
      </div>
      {showDebt && <>
        {row(`Đã thanh toán (${payCount || 0} đợt)`, paid, { valStyle: { color: "var(--good)" } })}
        <div style={{ display: "flex", justifyContent: "space-between", fontSize: 15, paddingTop: 8, borderTop: "1px solid var(--line)" }}>
          <span style={{ fontWeight: 600 }}>CÒN PHẢI THU</span><b className="tnum" style={{ color: con > 0 ? "var(--warn)" : "var(--good)" }}>{fmtVND(Math.max(0, con))}</b>
        </div>
      </>}
      {children}
    </div>
  );
}

/* onExtraColsCount(n): báo số cột chi phí riêng đang có để trang cha nới khung (bảng rộng thêm theo cột). */
/* So danh sách ứng viên trước / sau "Làm mới" → câu tóm tắt cho người dùng. */
function refreshSummary(prev, next) {
  const old = new Map((prev || []).map((x) => [x.id, x]));
  const ids = new Set((next || []).map((x) => x.id));
  let added = 0, choho = 0, price = 0;
  (next || []).forEach((x) => {
    const o = old.get(x.id);
    if (!o) { added++; return; }
    if (Math.round(+(o.pr || {}).chiHo || 0) !== Math.round(+(x.pr || {}).chiHo || 0)) choho++;
    if ((sysBaseOf(o.pr) ?? 0) !== (sysBaseOf(x.pr) ?? 0)) price++;
  });
  const gone = (prev || []).filter((x) => !ids.has(x.id)).length;
  const parts = [added && `+${added} lô mới`, choho && `${choho} lô đổi chi hộ`, price && `${price} lô đổi giá hệ thống`, gone && `${gone} lô không còn trong kỳ`].filter(Boolean);
  return parts.length ? `Đã làm mới: ${parts.join(" · ")}` : "Đã làm mới — không có gì thay đổi";
}

function StatementForm({ cfg, onCancel, onSaved, onExtraColsCount }) {
  const { useState, useEffect, useRef } = React;
  const T = window.__TRK || {}; const ROUTES = T.routes || {};
  const customers = cfg.customers || [];
  // Mở từ danh sách bảng kê (khung "Khách có lô trong kỳ" → Tạo bảng kê): ?customer=&from=&to= điền sẵn.
  const qs0 = useRef(new URLSearchParams(window.location.search)).current;
  const [cust, setCust] = useState(() => { const c = qs0.get("customer"); return c && customers.includes(c) ? c : (customers[0] || ""); });
  const [from, setFrom] = useState(() => qs0.get("from") || "");
  const [to, setTo] = useState(() => qs0.get("to") || "");
  const [picked, setPicked] = useState({}); // id -> bool
  const [vatRate, setVatRate] = useState(0); // % VAT cho bảng kê (mặc định 0)
  const info = (cfg.customerInfo || {})[cust] || {};
  const today = new Date().toISOString().slice(0, 10);
  // Hạn thanh toán: gợi ý theo Hạn TT của khách khi đổi khách (đến khi người dùng tự chọn ngày).
  const [dueDate, setDueDate] = useState(() => defaultDue(today, info));
  const dueTouched = useRef(false);
  useEffect(() => { if (!dueTouched.current) setDueDate(defaultDue(today, (cfg.customerInfo || {})[cust] || {})); }, [cust]);

  // ----- Lô + ĐỊNH GIÁ lấy TỪ BACKEND (nguồn chân lý duy nhất) theo khách + kỳ -----
  const [all, setAll] = useState([]);   // [{id,booking,io,sheet,from,to,contType,contNo,qty,cru,date,contLabel,note,thanhLy,pr}]
  const [loading, setLoading] = useState(false);
  // Chưa chọn KỲ (ngày ra) → KHÔNG tải lô (tránh kéo toàn bộ + ép người dùng chọn kỳ trước).
  const needDate = !!cust && !from && !to;
  // "Làm mới" = tải lại CÙNG khách + kỳ (lấy lô mới / chi hộ vừa điền) mà GIỮ tích chọn, giá gõ tay, VAT riêng,
  // phí cột riêng (đều lưu theo id lô). Đổi khách / kỳ thì mới bỏ tích chọn như cũ.
  const [reloadTick, setReloadTick] = useState(0);
  const [refreshMsg, setRefreshMsg] = useState("");
  const lastKey = useRef("");
  const prevAll = useRef([]);
  useEffect(() => {
    if (!cust || (!from && !to)) { setAll([]); setLoading(false); lastKey.current = ""; prevAll.current = []; setRefreshMsg(""); return; }
    const key = `${cust}|${from}|${to}`;
    const isRefresh = key === lastKey.current;
    lastKey.current = key;
    let alive = true; setLoading(true);
    if (!isRefresh) { setPicked({}); setRefreshMsg(""); }
    const p = new URLSearchParams({ customer: cust }); if (from) p.set("from", from); if (to) p.set("to", to);
    window.trkApi("GET", ROUTES.candidates + "?" + p.toString())
      .then((r) => {
        if (!alive) return;
        const next = r && r.ok ? (r.candidates || []) : [];
        if (isRefresh) setRefreshMsg(refreshSummary(prevAll.current, next));
        prevAll.current = next; setAll(next); setLoading(false);
      })
      .catch(() => { if (alive) { setLoading(false); if (!isRefresh) setAll([]); } });
    return () => { alive = false; };
  }, [cust, from, to, reloadTick]);

  const [amtOv, setAmtOv] = useState({}); // override NỀN (cước+dầu+sà lan) theo lô
  const [vatOv, setVatOv] = useState({}); // override % VAT riêng theo lô ("" = theo mặc định bảng kê)
  const [noteOv, setNoteOv] = useState({}); // cột Ghi chú theo lô (giữ khi Làm mới)
  // Lọc NHẬP / XUẤT ngay trên danh sách ứng viên (client): bảng kê lưu CHỈ các lô đang hiện (+ đang tích).
  const [io, setIo] = useState("all");
  const shown = io === "all" ? all : all.filter((x) => ioKey(x.io) === io);
  const sel = shown.filter((x) => picked[x.id] !== false); // mặc định chọn hết
  // CỘT CHI PHÍ RIÊNG của bảng kê đang tạo + số từng lô (extraOv[shipmentId][key]) — lưu cùng bảng kê (extraCols / detail.extras).
  const [extraCols, setExtraCols] = useState([]);
  const [extraOv, setExtraOv] = useState({});
  const addExtraCol = () => setExtraCols((c) => [...c, newExtraCol()]);
  const setExtraCol = (key, patch) => setExtraCols((c) => c.map((x) => (x.key === key ? { ...x, ...patch } : x)));
  const delExtraCol = (key) => {
    setExtraCols((c) => c.filter((x) => x.key !== key));
    setExtraOv((o) => { const n = {}; Object.keys(o).forEach((id) => { const e = { ...o[id] }; delete e[key]; n[id] = e; }); return n; });
  };
  const setLineExtra = (id, key, v) => setExtraOv((o) => ({ ...o, [id]: { ...(o[id] || {}), [key]: v } }));
  useEffect(() => { onExtraColsCount && onExtraColsCount(extraCols.length); }, [extraCols.length]);
  const nCols = 10 + extraCols.length;   // số cột bảng (gồm cột tích chọn + cột Ghi chú) cho colSpan
  // 3 con số/dòng từ NGUỒN CHÂN LÝ chung (nền cước+dầu+sà lan; VAT áp nền + cột riêng có vat; chi hộ không VAT).
  const ovOf = (x) => (amtOv[x.id] != null ? amtOv[x.id] : null);   // null = chưa sửa tay
  const vatOf = (x) => vatOv[x.id];                                 // undefined/"" = theo mặc định
  const prOf = (x) => ({ ...x.pr, vat: vatOf(x), extras: extraOv[x.id] || {} });   // gắn override VAT + phí riêng vào detail để lineAmounts đọc
  const lineAmt = (x) => lineAmounts({ detail: prOf(x) }, vatRate, ovOf(x), extraCols);   // {base,vat,choho,extra,extras,total,rate}
  const amt = statementAmounts(sel.map((x) => ({ detail: prOf(x) })), vatRate, (l, i) => ovOf(sel[i]), extraCols);
  const tongThu = amt.total;
  const keNo = "BK-" + today.replace(/-/g, "").slice(2) + "-" + (cust ? cust.slice(0, 3).toUpperCase() : "XXX");

  const [saving, setSaving] = useState(false);
  const save = async () => {
    if (!sel.length || saving) return;
    setSaving(true);
    const lines = sel.map((x) => {
      const a = lineAmt(x);   // {base,vat,choho,total}
      const ov = ovOf(x);
      // Sửa tay NỀN → ghi detail.manualBase (GIÁ TÙY CHỈNH); giữ nguyên cuoc/dau/sà lan hệ thống tính để đối chiếu.
      const detail = { ...x.pr };
      if (ov != null) detail.manualBase = a.base; else delete detail.manualBase;
      const vov = vatOf(x);   // VAT riêng dòng → ghi detail.vat (backend per-line)
      if (vov != null && vov !== "") detail.vat = +vov; else delete detail.vat;
      // Phí riêng từng cột của lô → detail.extras (backend chỉ giữ key của cột còn tồn tại, bỏ số 0)
      const ex = {}; extraCols.forEach((c) => { const v = Math.round(+(extraOv[x.id] || {})[c.key] || 0); if (v) ex[c.key] = v; });
      if (Object.keys(ex).length) detail.extras = ex; else delete detail.extras;
      return {
        id: x.id, booking: x.booking, sheet: x.sheet, io: x.io,
        declNo: x.declNo || "", contType: x.contType || "", inv: x.inv || "", contNo: x.contNo || "", bks: x.bks || "",
        from: x.from, to: x.to, date: x.date, contLabel: x.contLabel,
        // phaiThu/cuoc = NỀN dòng (cước+dầu) — giữ cho exporter; tổng có VAT tính ở cấp bảng kê.
        phaiThu: a.base, cuoc: a.base, thanhLy: x.thanhLy || 0, note: x.note, userNote: (noteOv[x.id] || "").trim(),
        // snapshot định giá (backend) để hiển thị đối soát TĨNH (không query lại khi xem)
        detail,
      };
    });
    // ioScope = phạm vi Nhập/Xuất đã chọn → lưu cùng bảng kê (backend chỉ tính tiền lô trong phạm vi).
    const payload = { id: Date.now(), no: keNo, customer: cust, info: dueDate ? { ...info, dueDate } : info, date: today, from, to, ioScope: io, extraCols, lines, vatRate, tongThu, payments: [], createdAt: new Date().toISOString() };
    // onSaved có thể async + trả về false để huỷ (vd bấm Huỷ ở confirm) → giữ nguyên trang
    let result;
    try { result = await Promise.resolve(onSaved && onSaved(payload)); }
    catch (e) { setSaving(false); return; }
    if (result === false) { setSaving(false); return; }   // huỷ → cho bấm lại; thành công thì trang điều hướng đi
  };

  const footer = (
    <div className="ke-noprint" style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 16, marginTop: 8, paddingTop: 14, borderTop: "1px solid var(--line)" }}>
      <div style={{ fontSize: 12.5, color: "var(--ink-3)" }}>{sel.length} lô · nền <b className="tnum">{fmtNum(amt.base)}</b> + VAT <b className="tnum">{fmtNum(amt.vat)}</b> + chi hộ <b className="tnum">{fmtNum(amt.choho)}</b>{extraCols.length ? <> + phí riêng <b className="tnum">{fmtNum(amt.extra)}</b></> : null} = tổng <b className="tnum" style={{ color: "var(--ink)" }}>{fmtVND(tongThu)}</b></div>
      <div style={{ display: "flex", gap: 10 }}>
        <Btn onClick={onCancel}>Hủy</Btn>
        <Btn onClick={() => window.print()}>In thử</Btn>
        <Btn variant="primary" onClick={save} disabled={saving}>{saving ? "Đang lưu…" : "Lưu bảng kê"}</Btn>
      </div>
    </div>
  );

  return (
    <div>
      {/* controls */}
      <div className="ke-noprint" style={{ display: "flex", gap: 12, alignItems: "flex-end", padding: "12px 0 14px", borderBottom: "1px solid var(--line-2)", flexWrap: "wrap" }}>
        <label style={{ display: "block" }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>Khách hàng</div>
          <div style={{ width: 240 }}>
            <Combo value={cust} onChange={(v) => { setCust(v); setPicked({}); }} options={customers} placeholder="Chọn khách hàng…" strict />
          </div>
        </label>
        <div style={{ display: "block" }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>Cont ra từ ngày</div>
          <div style={{ width: 150 }}><DateField value={from} onChange={setFrom} /></div>
        </div>
        <div style={{ display: "block" }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>đến ngày</div>
          <div style={{ width: 150 }}><DateField value={to} onChange={setTo} /></div>
        </div>
        <div style={{ display: "block" }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>VAT</div>
          <VatSelect value={vatRate} onChange={setVatRate} />
        </div>
        <div style={{ display: "block" }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }} title={info.termDays ? `Mặc định = ngày lập + ${info.termDays} ngày (Hạn TT của khách)` : "Khách chưa cài Hạn TT mặc định (Cài đặt › Khách hàng) — chọn ngày hoặc số ngày"}>Hạn thanh toán</div>
          <DueDateField base={today} value={dueDate} width={150} onChange={(v) => { dueTouched.current = true; setDueDate(v); }} />
        </div>
        <div style={{ display: "block" }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>Lô hàng</div>
          <IoSeg value={io} onChange={setIo} counts={ioCounts(all)} />
        </div>
        <button type="button" onClick={() => setReloadTick((t) => t + 1)} disabled={!cust || (!from && !to) || loading}
          title="Tải lại lô + chi hộ mới nhất theo khách và kỳ đang chọn — giữ giá gõ tay, VAT riêng, phí cột riêng và lô đã bỏ tích"
          style={{ display: "inline-flex", alignItems: "center", gap: 6, height: 36, padding: "0 13px", fontSize: 13, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: "var(--ink-2)", cursor: (!cust || (!from && !to) || loading) ? "default" : "pointer", opacity: (!cust || (!from && !to)) ? 0.5 : 1 }}>
          <i className="bi bi-arrow-clockwise" style={loading ? { animation: "trk-spin .8s linear infinite" } : undefined} /> Làm mới
        </button>
      </div>

      {/* CỘT CHI PHÍ RIÊNG cho bảng kê đang tạo — điền số từng lô ở cột tương ứng trong bảng dưới */}
      <div style={{ padding: "10px 0 0" }}><ExtraColsBar cols={extraCols} onAdd={addExtraCol} onChange={setExtraCol} onDelete={delExtraCol} /></div>

      {/* Ghi chú cho kế toán: bộ lọc kỳ dựa theo Giờ xe ra của lô */}
      <div className="ke-noprint" style={{ fontSize: 11.5, color: "var(--ink-4)", padding: "8px 0 0", display: "flex", alignItems: "flex-start", gap: 6, lineHeight: 1.5, flexWrap: "wrap" }}>
        <i className="bi bi-info-circle" style={{ marginTop: 1 }} />
        <span style={{ flex: "1 1 320px" }}>Lọc theo <b style={{ color: "var(--ink-3)" }}>Giờ xe ra</b> của lô hàng (mốc cont rời đi, nhập ở popup Lô hàng). Lô <b style={{ color: "var(--ink-3)" }}>chưa có giờ ra</b> sẽ không hiện ở đây.</span>
        <span className="tnum" style={{ whiteSpace: "nowrap", fontWeight: 600, color: loading ? "var(--accent)" : "var(--ink-3)" }}>
          {loading ? "Đang tải lô…" : (io === "all" ? `${all.length} lô có phải thu` : `${shown.length} / ${all.length} lô (${io === "nhap" ? "Nhập" : "Xuất"})`)}
          {!loading && refreshMsg ? <span style={{ color: "var(--good)", fontWeight: 600 }}> · {refreshMsg}</span> : null}
        </span>
      </div>

      {/* printable statement */}
      <div className="ke-print" style={{ padding: "16px 4px 4px", background: "#fff" }}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", gap: 20, marginBottom: 14 }}>
          <div>
            <div style={{ fontSize: 18, fontWeight: 700, letterSpacing: "-0.01em" }}>BẢNG KÊ CẦN THU</div>
            <div style={{ fontSize: 12.5, color: "var(--ink-3)", marginTop: 3 }} className="tnum">Số: {keNo} · Ngày lập: {fmtDate(today)}{(from || to) ? ` · Cont ra: ${fmtDate(from) || "…"} – ${fmtDate(to) || "…"}` : ""}{dueDate ? ` · Hạn TT: ${fmtDate(dueDate)}` : ""}</div>
          </div>
          <div style={{ textAlign: "right", fontSize: 12 }}>
            <div style={{ fontWeight: 700, color: "var(--accent)" }}>{CO_NAME}</div>
            <div style={{ color: "var(--ink-3)", marginTop: 2 }}>{CO_SUB}</div>
          </div>
        </div>
        <div style={{ background: "var(--accent-weak-2)", border: "1px solid var(--accent-weak)", borderRadius: 10, padding: "11px 14px", marginBottom: 14, fontSize: 12.5 }}>
          <div style={{ fontWeight: 700, fontSize: 14 }}>{cust || "—"}</div>
          <CustomerInfoLine info={info} due={dueDate} base={today} />
        </div>

        {/* Nhiều cột chi phí riêng → bảng giữ bề rộng tối thiểu và cuộn ngang trên màn hình (in: .ke-scroll bỏ cuộn + minWidth) */}
        <div className="ke-scroll" style={{ overflowX: "auto" }}>
        <table style={{ width: "100%", minWidth: 1080 + 120 * extraCols.length, borderCollapse: "collapse", fontSize: 12.5 }}>
          <thead>
            <tr style={{ background: "#fafbfc" }}>
              <th className="ke-noprint" style={{ width: 34, padding: "9px 8px", borderBottom: "1.5px solid var(--line)" }}></th>
              <th style={{ width: 34, textAlign: "center", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)" }}>#</th>
              <th style={{ textAlign: "left", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Lô / Booking</th>
              <th style={{ textAlign: "left", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Tuyến · Cont</th>
              <th style={{ textAlign: "left", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Cont ra</th>
              <th style={{ textAlign: "right", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Phải thu<br/>(cước+dầu)</th>
              <th style={{ textAlign: "right", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>VAT</th>
              {extraCols.map((c) => <th key={c.key} style={{ textAlign: "right", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>{colLabel(c)}{c.vat ? <span style={{ color: "var(--accent)", fontSize: 9.5 }}><br/>+VAT</span> : null}</th>)}
              <th style={{ textAlign: "right", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Chi hộ</th>
              <th style={{ textAlign: "right", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Tổng tiền</th>
              <th style={{ textAlign: "left", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Ghi chú</th>
            </tr>
          </thead>
          <tbody style={{ opacity: loading && all.length ? 0.5 : 1, transition: "opacity .15s" }}>
            {!loading && all.length === 0 && <tr><td colSpan={nCols} style={{ padding: "28px 24px", textAlign: "center", color: "var(--ink-4)" }}>
              {needDate
                ? <span style={{ display: "inline-flex", flexDirection: "column", alignItems: "center", gap: 6 }}>
                    <i className="bi bi-calendar-range" style={{ fontSize: 26, color: "var(--accent)", opacity: .8 }} />
                    <b style={{ color: "var(--ink-2)", fontSize: 13.5 }}>Vui lòng chọn ngày ra của lô hàng</b>
                    <span style={{ fontSize: 12.5 }}>Chọn <b>Cont ra từ ngày</b> (và đến ngày) ở trên để lọc lô đưa vào bảng kê.</span>
                  </span>
                : (cust ? "Không có lô nào phù hợp trong kỳ đã chọn." : "Chọn khách hàng để bắt đầu.")}
            </td></tr>}
            {loading && all.length === 0 && <tr><td colSpan={nCols} style={{ padding: "24px", textAlign: "center", color: "var(--ink-4)" }}>Đang tải lô + định giá…</td></tr>}
            {!loading && all.length > 0 && shown.length === 0 && <tr><td colSpan={nCols} style={{ padding: "24px", textAlign: "center", color: "var(--ink-4)" }}>Không có lô <b>{io === "nhap" ? "Nhập" : "Xuất"}</b> trong kỳ — đổi bộ lọc Lô hàng ở trên.</td></tr>}
            {shown.map((x, i) => {
              const on = picked[x.id] !== false;
              return (
                <React.Fragment key={x.id}>
                <tr className={i % 2 ? "ke-zebra" : ""} style={{ opacity: on ? 1 : 0.4 }}>
                  <td className="ke-noprint" style={{ textAlign: "center", padding: "8px", borderBottom: "none", verticalAlign: "top" }}>
                    <input type="checkbox" checked={on} onChange={(e) => setPicked((p) => ({ ...p, [x.id]: e.target.checked }))} style={{ width: 16, height: 16, accentColor: "var(--accent)", cursor: "pointer" }} />
                  </td>
                  <td className="tnum" style={{ textAlign: "center", padding: "8px", borderBottom: "none", verticalAlign: "top", color: "var(--ink-4)" }}>{i + 1}</td>
                  <td style={{ padding: "8px", borderBottom: "none", verticalAlign: "top" }}>
                    <div style={{ fontWeight: 600 }} className="tnum">{x.booking || "—"}</div>
                    <div style={{ fontSize: 11, color: "var(--ink-4)" }}>{x.sheet} · {x.io}</div>
                  </td>
                  <td style={{ padding: "8px", borderBottom: "none", verticalAlign: "top", color: "var(--ink-2)" }}>
                    {x.from} → {x.to}<div style={{ fontSize: 11, color: "var(--ink-4)" }} className="tnum">{x.contLabel}</div>
                  </td>
                  <td className="tnum" style={{ padding: "8px", borderBottom: "none", verticalAlign: "top", color: "var(--ink-2)" }}>{fmtDate(x.date) || "—"}</td>
                  {(() => { const a = lineAmt(x); return (<>
                  <td className="tnum" style={{ textAlign: "right", padding: "6px 8px", borderBottom: "none", verticalAlign: "top", fontWeight: 600 }}>
                    <div style={{ position: "relative", width: 130, marginLeft: "auto" }}>
                      <input inputMode="numeric" value={(a.base || 0).toLocaleString("vi-VN")} onChange={(e) => setAmtOv((o) => ({ ...o, [x.id]: parseInt(e.target.value.replace(/[^\d]/g, ""), 10) || 0 }))} className="tnum"
                        style={{ width: "100%", padding: "6px 22px 6px 8px", fontSize: 12.5, textAlign: "right", fontWeight: 600, border: "1px solid var(--line)", borderRadius: 7, outline: "none" }}
                        onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
                      <span style={{ position: "absolute", right: 8, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 12, pointerEvents: "none" }}>₫</span>
                    </div>
                  </td>
                  <td className="tnum" style={{ textAlign: "right", padding: "6px 8px", borderBottom: "none", verticalAlign: "top" }}>
                    <span className="ke-noprint">
                      <select value={vatOf(x) == null ? "" : String(vatOf(x))} onChange={(e) => setVatOv((o) => ({ ...o, [x.id]: e.target.value }))} style={{ padding: "5px 6px", fontSize: 12, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 7, background: "#fff", cursor: "pointer", textAlign: "right" }} title="VAT riêng dòng này (mặc định theo bảng kê)">
                        <option value="">Mặc định ({vatRate}%)</option>
                        {STATEMENT_VAT_RATES.map((r) => <option key={r} value={r}>{r}%</option>)}
                      </select>
                      <div className="tnum" style={{ fontSize: 11, marginTop: 2, color: a.rate ? "var(--accent)" : "var(--ink-4)" }}>{fmtNum(a.vat)}</div>
                    </span>
                    <span style={{ display: "none" }} className="ke-printonly">{fmtNum(a.vat)}{a.rate ? ` (${a.rate}%)` : ""}</span>
                  </td>
                  {extraCols.map((c) => (
                    <td key={c.key} className="tnum" style={{ textAlign: "right", padding: "6px 8px", borderBottom: "none", verticalAlign: "top" }}>
                      <ExtraCell col={c} value={a.extras[c.key] || 0} onChange={(v) => setLineExtra(x.id, c.key, v)} />
                    </td>
                  ))}
                  <td className="tnum" style={{ textAlign: "right", padding: "6px 8px", borderBottom: "none", verticalAlign: "top", color: "var(--ink-2)", minWidth: 90 }}><ChoHoCell amount={a.choho} items={x.pr.choHoItems} /></td>
                  <td className="tnum" style={{ textAlign: "right", padding: "6px 8px", borderBottom: "none", verticalAlign: "top", fontWeight: 700 }}>{fmtNum(a.total)}</td>
                  <td style={{ padding: "6px 8px", borderBottom: "none", verticalAlign: "top" }}><LineNoteCell value={noteOv[x.id]} onChange={(v) => setNoteOv((o) => ({ ...o, [x.id]: v }))} /></td>
                  </>); })()}
                </tr>
                <tr className={(i % 2 ? "ke-zebra " : "") + "ke-lo-end"} style={{ opacity: on ? 1 : 0.4 }}>
                  <td className="ke-noprint" style={{ borderBottom: "1px solid var(--line-2)" }}></td>
                  <td style={{ borderBottom: "1px solid var(--line-2)" }}></td>
                  <td colSpan={nCols - 2} style={{ padding: "0 8px", borderBottom: "1px solid var(--line-2)" }}>
                    <LotNote text={x.infoNote} />
                    <div className="ke-noprint" style={{ fontSize: 11, lineHeight: 1.55, padding: "0 0 8px" }}>
                      <LotCostLine items={x.pr.costItems} block />
                      {x.pr.matched
                        ? <span style={{ color: "var(--good)" }}>✓ Bảng giá · {x.cru ? (/xu/i.test(x.io || "") ? "CRU ngoại" : "CRU nội") : "1 chiều"} · {x.pr.conn || "—"} · {contColLabel(x.pr)}{x.pr.priceBook ? <span style={{ color: "var(--accent)" }}> · 📅 {bookLabel(x.pr.priceBook)}</span> : null} · <span className="tnum">{x.pr.route}</span>{x.pr.noDrop ? <span style={{ color: "var(--warn)" }}> (lô chưa có Nơi hạ — khớp theo FROM)</span> : null} — <span className="tnum">{x.pr.dau > 0 ? `Cước ${fmtNum(x.pr.cuoc)} + Dầu ${fmtNum(x.pr.dau)}` : `Cước+dầu ${fmtNum(x.pr.cuoc)}`}{(x.pr.bargeCuoc || x.pr.bargeDau) ? " + Sà lan " + fmtNum((x.pr.bargeCuoc || 0) + (x.pr.bargeDau || 0)) : ""}{x.pr.chiHo ? " + Chi hộ " + fmtNum(x.pr.chiHo) : ""} = {fmtNum(x.pr.phaiThu)} ₫</span>{x.pr.isBarge && !x.pr.bargeMatched ? <span style={{ color: "var(--warn)" }}> · ⚠ sà lan chưa khớp giá</span> : null}</span>
                        : <span style={{ color: "var(--warn)" }}>⚠ Chưa khớp bảng giá{!x.pr.priceBook ? " (ngày cont ra ngoài mọi bảng giá)" : (x.pr.routeMatched ? ` (tuyến có giá nhưng thiếu cột loại cont ${x.pr.contType || "?"})` : "")}{x.pr.chiHo ? " · mới có Chi hộ " + fmtShort(x.pr.chiHo) : " · phải thu 0"}</span>}
                    </div>
                  </td>
                </tr>
                </React.Fragment>
              );
            })}
          </tbody>
          <tfoot>
            {/* Hàng tổng theo TỪNG CỘT (nền · VAT · chi hộ) — khớp 3 cột mỗi dòng */}
            <tr style={{ fontWeight: 700 }}>
              <td className="ke-noprint" style={{ borderTop: "1.5px solid var(--line)" }}></td>
              <td colSpan={4} style={{ padding: "9px 8px", borderTop: "1.5px solid var(--line)", textAlign: "right", color: "var(--ink-3)" }}>CỘNG</td>
              <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{fmtNum(amt.base)}</td>
              <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)", color: amt.vat ? "var(--accent)" : "var(--ink-4)" }}>{fmtNum(amt.vat)}</td>
              {extraCols.map((c) => <td key={c.key} className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{fmtNum(amt.extras[c.key] || 0)}</td>)}
              <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{fmtNum(amt.choho)}</td>
              <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{fmtNum(tongThu)}</td>
              <td style={{ borderTop: "1.5px solid var(--line)" }}></td>
            </tr>
            {/* Khối tổng (nền/VAT/chi hộ/từng cột riêng/tổng tiền) */}
            <tr>
              <td className="ke-noprint"></td>
              <td colSpan={nCols - 4} style={{ padding: "9px 8px 3px", textAlign: "right", color: "var(--ink-3)" }}>Phải thu (cước + dầu)</td>
              <td className="tnum" colSpan={2} style={{ textAlign: "right", padding: "9px 8px 3px" }}>{fmtVND(amt.base)}</td><td></td>
            </tr>
            <tr>
              <td className="ke-noprint"></td>
              <td colSpan={nCols - 4} style={{ padding: "3px 8px", textAlign: "right", color: "var(--ink-3)" }}>VAT{vatRate ? ` (mặc định ${vatRate}%)` : ""}</td>
              <td className="tnum" colSpan={2} style={{ textAlign: "right", padding: "3px 8px", color: amt.vat ? "var(--accent)" : "var(--ink-4)" }}>{fmtVND(amt.vat)}</td><td></td>
            </tr>
            {extraCols.map((c) => (
              <tr key={c.key}>
                <td className="ke-noprint"></td>
                <td colSpan={nCols - 4} style={{ padding: "3px 8px", textAlign: "right", color: "var(--ink-3)" }}>{colLabel(c)}{c.vat ? " (chịu VAT)" : " (không VAT)"}</td>
                <td className="tnum" colSpan={2} style={{ textAlign: "right", padding: "3px 8px" }}>{fmtVND(amt.extras[c.key] || 0)}</td><td></td>
              </tr>
            ))}
            <tr>
              <td className="ke-noprint"></td>
              <td colSpan={nCols - 4} style={{ padding: "3px 8px", textAlign: "right", color: "var(--ink-3)" }}>Chi hộ (không VAT)</td>
              <td className="tnum" colSpan={2} style={{ textAlign: "right", padding: "3px 8px" }}>{fmtVND(amt.choho)}</td><td></td>
            </tr>
            <tr style={{ fontWeight: 700 }}>
              <td className="ke-noprint"></td>
              <td colSpan={nCols - 4} style={{ padding: "8px 8px 11px", textAlign: "right" }}>TỔNG TIỀN</td>
              <td className="tnum" colSpan={2} style={{ textAlign: "right", padding: "8px 8px 11px" }}>{fmtVND(tongThu)}</td><td></td>
            </tr>
          </tfoot>
        </table>
        </div>

        <div className="ke-noprint" style={{ marginTop: 14, fontSize: 12.5, color: "var(--ink-4)" }}>Sau khi lưu, mở bảng kê để nhập các đợt khách thanh toán và theo dõi công nợ.</div>
      </div>
      {footer}
    </div>
  );
}

/* Thân chi tiết bảng kê (in được) — dùng CHUNG cho modal & trang xem riêng.
   detailById:  { [shipmentId]: { found, matched, cuoc, dau, choHoItems[], costItems[], phaiThu } } = SNAPSHOT đã lưu để đối soát.
   suggestById: kết quả "Tính lại" (gợi ý, không đụng số đã lưu) → hiện chip "Hệ thống tính X · Áp dụng" ở dòng lệch.
   onApplyLine(lineId): áp gợi ý vào 1 dòng. */
function StatementDetailBody({ st, onUpdate, detailById = {}, suggestById = null, onApplyLine }) {
  const { useState } = React;
  const [payments, setPayments] = useState(st.payments || []);
  const sync = (arr) => { setPayments(arr); onUpdate && onUpdate({ ...st, payments: arr }); };
  const setP = (id, np) => sync(payments.map((p) => (p.id === id ? { ...p, ...np } : p)));
  const addP = () => sync([...payments, { id: Date.now() + Math.random(), date: new Date().toISOString().slice(0, 10), amount: "", note: "" }]);
  const delP = (id) => sync(payments.filter((p) => p.id !== id));
  const vatRate = +st.vatRate || 0;
  const setVat = (v) => onUpdate && onUpdate({ ...st, vatRate: v });
  // CỘT CHI PHÍ RIÊNG của bảng kê này (extraCols [{key,label,vat}] lưu cùng bảng kê, KHÔNG khai trong Cài đặt);
  // số từng lô ở detail.extras[key]. Cột có cờ vat → chịu VAT% của bảng kê. Nhãn rỗng → backend bỏ cột khi lưu.
  const extraCols = Array.isArray(st.extraCols) ? st.extraCols : [];
  const setExtraCols = (cols) => onUpdate && onUpdate({ ...st, extraCols: cols });
  const addExtraCol = () => setExtraCols([...extraCols, newExtraCol()]);
  const setExtraCol = (key, patch) => setExtraCols(extraCols.map((c) => (c.key === key ? { ...c, ...patch } : c)));
  const delExtraCol = async (key) => {
    const filled = (st.lines || []).some((l) => l.detail && l.detail.extras && +l.detail.extras[key]);
    if (filled && window.confirmAction && !(await window.confirmAction({ title: "Xóa cột chi phí riêng?", text: "Các số đã điền ở cột này sẽ bị bỏ (chỉ chốt khi bấm <b>Lưu</b>).", confirmText: "Xóa cột", cancelText: "Huỷ" }))) return;
    onUpdate && onUpdate({ ...st, extraCols: extraCols.filter((c) => c.key !== key), lines: (st.lines || []).map((l) => {
      const d = (l.detail && typeof l.detail === "object") ? l.detail : null;
      if (!d || !d.extras || !(key in d.extras)) return l;
      const ex = { ...d.extras }; delete ex[key];
      return { ...l, detail: { ...d, extras: ex } };
    }) });
  };
  const setLineExtra = (id, key, v) => onUpdate && onUpdate({ ...st, lines: (st.lines || []).map((l) => {
    if (l.id !== id) return l;
    const d = (l.detail && typeof l.detail === "object") ? { ...l.detail } : {};
    d.extras = { ...(d.extras || {}), [key]: v };
    return { ...l, detail: d };
  }) });
  const nCols = 9 + extraCols.length;   // số cột của bảng gồm cột Ghi chú (cho colSpan phần chi tiết + tổng kết)
  const setLineNote = (id, v) => onUpdate && onUpdate({ ...st, lines: (st.lines || []).map((l) => (l.id === id ? { ...l, userNote: v } : l)) });
  // NỀN dòng = cước+dầu+sà lan (từ detail) — NGUỒN CHÂN LÝ khớp backend. VAT áp nền (+ phí riêng có vat); chi hộ không VAT.
  const lineAmt = (l) => lineAmounts(l, vatRate, null, extraCols);   // {base,vat,choho,extra,extras,total} từ detail
  // Sửa tay NỀN dòng → GIÁ TÙY CHỈNH: detail.manualBase + phaiThu = nền; snapshot cuoc/dau/sà lan giữ nguyên để đối chiếu.
  const setLineBase = (id, base) => onUpdate && onUpdate({ ...st, lines: (st.lines || []).map((l) => {
    if (l.id !== id) return l;
    const d = (l.detail && typeof l.detail === "object") ? l.detail : {};
    return { ...l, phaiThu: base, cuoc: base, detail: { ...d, manualBase: base } };
  }) });
  // Bỏ giá tùy chỉnh → về nền hệ thống đã tính trong snapshot.
  const clearManual = (id) => onUpdate && onUpdate({ ...st, lines: (st.lines || []).map((l) => {
    if (l.id !== id) return l;
    const d = { ...((l.detail && typeof l.detail === "object") ? l.detail : {}) };
    delete d.manualBase;
    const base = sysBaseOf(d) ?? (+l.phaiThu || 0);
    return { ...l, phaiThu: base, cuoc: base, detail: d };
  }) });
  const chipBtn = { border: "none", borderRadius: 6, padding: "2px 8px", fontSize: 10.5, fontWeight: 700, cursor: "pointer" };
  // Sửa VAT RIÊNG 1 dòng (ghi detail.vat). "" = theo mặc định bảng kê (xóa override).
  const setLineVat = (id, v) => onUpdate && onUpdate({ ...st, lines: (st.lines || []).map((l) => {
    if (l.id !== id) return l;
    const d = (l.detail && typeof l.detail === "object") ? { ...l.detail } : {};
    if (v === "" || v == null) delete d.vat; else d.vat = +v;
    return { ...l, detail: d };
  }) });
  const lineVatStyle = { padding: "5px 6px", fontSize: 12, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 7, background: "#fff", cursor: "pointer", textAlign: "right" };
  // PHẠM VI bảng kê (ioScope: all | nhap | xuat) — LƯU cùng bảng kê, khớp backend ioInScope: chỉ lô đúng loại được
  // TÍNH TIỀN (4 con số), in và xuất Excel. Lô khác loại vẫn lưu trong bảng kê nhưng ẨN HẲN khỏi danh sách
  // (user: hiện mờ nhìn rối) — đổi phạm vi về "Tất cả" là hiện lại; thanh cảnh báo nêu số lô đang ẩn.
  const scope = st.ioScope || "all";
  const setScope = (k) => onUpdate && onUpdate({ ...st, ioScope: k });
  const inScope = (l) => scope === "all" || ioKey(l.io) === scope;
  const scopedLines = (st.lines || []).filter(inScope);
  const outCount = (st.lines || []).length - scopedLines.length;
  const scopeLabel = scope === "nhap" ? "Nhập" : "Xuất";
  // Các con số = Σ per-line TRONG PHẠM VI (khớp từng cột mỗi dòng), gồm cột chi phí riêng.
  const amt = statementAmounts(scopedLines, vatRate, null, extraCols);
  const tongThu = amt.total;
  const grp = (d) => { d = (d || "").toString().replace(/[^\d]/g, ""); return d ? d.replace(/\B(?=(\d{3})+(?!\d))/g, ".") : ""; };
  const daTT = payments.reduce((a, p) => a + toNum(p.amount), 0);
  const conNo = tongThu - daTT;
  const info = st.info || {};
  const setPeriod = (k, v) => onUpdate && onUpdate({ ...st, [k]: v });   // sửa kỳ cont ra (from/to)
  const th = (txt, align) => <th style={{ textAlign: align || "left", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>{txt}</th>;
  return (
      <div className="ke-print" style={{ padding: "16px 4px 4px", background: "#fff" }}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", gap: 20, marginBottom: 14 }}>
          <div>
            <div style={{ fontSize: 18, fontWeight: 700, letterSpacing: "-0.01em" }}>BẢNG KÊ CẦN THU</div>
            <div style={{ fontSize: 12.5, color: "var(--ink-3)", marginTop: 3 }} className="tnum">Số: {st.no} · Ngày lập: {fmtDate(st.date)}
              <span className="ke-printonly" style={{ display: "none" }}>{(st.from || st.to) ? ` · Cont ra: ${fmtDate(st.from) || "…"} – ${fmtDate(st.to) || "…"}` : ""}{dueOf(st) ? ` · Hạn TT: ${fmtDate(dueOf(st))}` : ""}</span>
            </div>
            {/* Kỳ cont ra — sửa được để tính lại theo khoảng ngày (ẩn khi in, in dùng dòng text trên) */}
            <div className="ke-noprint" style={{ display: "flex", alignItems: "center", gap: 7, marginTop: 7, fontSize: 12.5, color: "var(--ink-3)", flexWrap: "wrap" }}>
              <span style={{ fontWeight: 600 }}>Cont ra từ</span>
              <div style={{ width: 140 }}><DateField value={st.from || ""} onChange={(v) => setPeriod("from", v)} /></div>
              <span style={{ fontWeight: 600 }}>đến</span>
              <div style={{ width: 140 }}><DateField value={st.to || ""} onChange={(v) => setPeriod("to", v)} /></div>
              {(st.from || st.to) && <button type="button" onClick={() => onUpdate && onUpdate({ ...st, from: "", to: "" })} title="Xóa khoảng ngày"
                style={{ border: "none", background: "transparent", color: "var(--ink-4)", cursor: "pointer", fontSize: 12, padding: "2px 4px" }}>✕</button>}
              <span style={{ fontWeight: 600, marginLeft: 6 }}>VAT</span>
              <VatSelect value={vatRate} onChange={setVat} />
              <span style={{ fontWeight: 600, marginLeft: 6 }} title="Phạm vi lô được tính tiền / in / xuất Excel — lưu cùng bảng kê">Phạm vi</span>
              <IoSeg value={scope} onChange={setScope} counts={ioCounts(st.lines)} />
            </div>
            {scope !== "all" && (
              <div className="ke-noprint tnum" style={{ marginTop: 6, fontSize: 12, color: "#7a5200", background: "#fff7e6", border: "1px solid #ffe1a8", borderRadius: 8, padding: "6px 10px", lineHeight: 1.5 }}>
                Bảng kê <b>hàng {scopeLabel.toLowerCase()}</b>: tính tiền <b>{scopedLines.length}</b> lô {scopeLabel}{outCount > 0 ? <> — <b>{outCount}</b> lô khác loại đã ẩn, <b>không tính</b>, không in / xuất Excel (chọn <b>Tất cả</b> để xem lại)</> : null}. Bấm <b>Lưu</b> để chốt phạm vi.
              </div>
            )}
            {/* CỘT CHI PHÍ RIÊNG — repeater, chỉ áp cho bảng kê này; mỗi cột = 1 ô số theo lô trong bảng dưới */}
            <div style={{ marginTop: 8 }}><ExtraColsBar cols={extraCols} onAdd={addExtraCol} onChange={setExtraCol} onDelete={delExtraCol} /></div>
            {/* VAT + phạm vi cho bản in (read-only) */}
            <div className="ke-printonly" style={{ display: "none", fontSize: 12.5, color: "var(--ink-3)", marginTop: 4 }}>VAT: {vatRate}%{scope !== "all" ? ` · Phạm vi: lô hàng ${scopeLabel.toLowerCase()}` : ""}</div>
          </div>
          <div style={{ textAlign: "right", fontSize: 12 }}>
            <div style={{ fontWeight: 700, color: "var(--accent)" }}>{CO_NAME}</div>
            <div style={{ color: "var(--ink-3)", marginTop: 2 }}>{CO_SUB}</div>
          </div>
        </div>
        <div style={{ background: "var(--accent-weak-2)", border: "1px solid var(--accent-weak)", borderRadius: 10, padding: "11px 14px", marginBottom: 14, fontSize: 12.5 }}>
          <div style={{ fontWeight: 700, fontSize: 14 }}>{st.customer}</div>
          <CustomerInfoLine info={info} due={dueOf(st)} base={st.date} />
        </div>
        {/* Nhiều cột chi phí riêng → bảng giữ bề rộng tối thiểu và cuộn ngang trên màn hình (in: .ke-scroll bỏ cuộn + minWidth) */}
        <div className="ke-scroll" style={{ overflowX: "auto" }}>
        <table style={{ width: "100%", minWidth: 1060 + 110 * extraCols.length, borderCollapse: "collapse", fontSize: 12.5 }}>
          <thead><tr style={{ background: "#fafbfc" }}>{th("#", "center")}{th("Lô / Booking")}{th("Tuyến · Cont")}{th("Cont ra")}{th(<>Phải thu<br/>(cước+dầu)</>, "right")}{th("VAT", "right")}
            {extraCols.map((c) => <React.Fragment key={c.key}>{th(<>{colLabel(c)}{c.vat ? <span style={{ color: "var(--accent)", fontSize: 9.5 }}><br/>+VAT</span> : null}</>, "right")}</React.Fragment>)}
            {th("Chi hộ", "right")}{th("Tổng tiền", "right")}{th("Ghi chú")}</tr></thead>
          <tbody>
            {scopedLines.map((l, i) => {
              const d = detailById[l.id];
              const sg = suggestById ? suggestById[l.id] : null;   // gợi ý từ Tính lại (nếu có)
              const gone = !!(sg && !sg.found);                      // lô không còn trong hệ thống
              const sug = suggestionOf(l, sg, vatRate);
              const manual = isManualLine(l);
              const sysB = sysBaseOf(l.detail);
              const note = String(l.infoNote || "").trim();   // "Ghi chú tự do cho lô hàng" (popup Thông tin lô, lấy trực tiếp từ lô)
              const noteOnly = !!note && !(d && d.found) && !gone;   // có ghi chú nhưng không có dòng chi tiết → dòng riêng
              const noteEl = <LotNote text={note} />;
              return (
              <React.Fragment key={l.id}>
              <tr className={(i % 2 ? "ke-zebra " : "") +(d || noteOnly ? "" : "ke-lo-end")}>
                <td className="tnum" style={{ textAlign: "center", padding: "8px", borderBottom: d || noteOnly ? "none" : "1px solid var(--line-2)", color: "var(--ink-4)", verticalAlign: "top" }}>{i + 1}</td>
                <td style={{ padding: "8px", borderBottom: d || noteOnly ? "none" : "1px solid var(--line-2)", verticalAlign: "top" }}><div style={{ fontWeight: 600 }} className="tnum">{l.booking || "—"}</div><div style={{ fontSize: 11, color: "var(--ink-4)" }}>{l.sheet} · {l.io}</div></td>
                <td style={{ padding: "8px", borderBottom: d || noteOnly ? "none" : "1px solid var(--line-2)", color: "var(--ink-2)", verticalAlign: "top" }}>{l.from} → {l.to}<div style={{ fontSize: 11, color: "var(--ink-4)" }} className="tnum">{(ROUTES_TRK.loHang && l.contNo)
                  ? <a className="ke-noprint" href={ROUTES_TRK.loHang + "?q=" + encodeURIComponent(l.contNo) + "&open=1"} target="_blank" rel="noopener" title="Mở lô hàng (tab mới, lọc đúng cont này)" style={{ color: "var(--accent)", textDecoration: "none" }}>{l.contLabel}</a>
                  : l.contLabel}<span style={{ display: "none" }} className="ke-printonly">{l.contLabel}</span></div></td>
                <td className="tnum" style={{ padding: "8px", borderBottom: d || noteOnly ? "none" : "1px solid var(--line-2)", color: "var(--ink-2)", verticalAlign: "top" }}>{fmtDate(l.date) || "—"}</td>
                {(() => { const a = lineAmt(l); return (<>
                <td className="tnum" style={{ textAlign: "right", padding: "6px 8px", borderBottom: d || noteOnly ? "none" : "1px solid var(--line-2)", fontWeight: 600, verticalAlign: "top" }}>
                  <span className="ke-noprint"><span style={{ position: "relative", display: "inline-block", width: 130 }}>
                    <input inputMode="numeric" value={(a.base || 0).toLocaleString("vi-VN")} onChange={(e) => setLineBase(l.id, parseInt(e.target.value.replace(/[^\d]/g, ""), 10) || 0)} className="tnum"
                      title={manual ? "Giá tùy chỉnh (bạn đặt tay) — Tính lại không ghi đè" : "Nền cước+dầu(+sà lan). Gõ số khác = giá tùy chỉnh"}
                      style={{ width: "100%", padding: "6px 22px 6px 8px", fontSize: 12.5, textAlign: "right", fontWeight: 600, border: "1px solid " + (manual ? "#e0b45a" : "var(--line)"), background: manual ? "#fffaf0" : "#fff", borderRadius: 7, outline: "none" }}
                      onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = manual ? "#e0b45a" : "var(--line)")} />
                    <span style={{ position: "absolute", right: 8, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 12, pointerEvents: "none" }}>₫</span>
                  </span></span>
                  {/* Giá tùy chỉnh: nhãn + nút về giá hệ thống (số trong snapshot) */}
                  {manual && (
                    <div className="ke-noprint" style={{ display: "flex", justifyContent: "flex-end", alignItems: "center", gap: 6, marginTop: 3, fontSize: 10.5, fontWeight: 700, color: "#9a6700" }}>
                      <span><i className="bi bi-pencil-fill" style={{ fontSize: 9 }} /> Giá tùy chỉnh</span>
                      {sysB != null && sysB !== a.base && (
                        <button type="button" onClick={() => clearManual(l.id)} title={`Bỏ giá tùy chỉnh, về giá hệ thống tính ${fmtNum(sysB)}`}
                          style={{ ...chipBtn, background: "#fff", border: "1px solid #ffe1a8", color: "#9a6700" }}>↺ {fmtNum(sysB)}</button>
                      )}
                    </div>
                  )}
                  {/* Gợi ý từ Tính lại: KHÔNG tự điền, bấm Áp dụng mới đổi (giá tùy chỉnh cũng vậy) */}
                  {sug && (
                    <div className="ke-noprint" style={{ display: "flex", justifyContent: "flex-end", alignItems: "center", gap: 6, marginTop: 4, fontSize: 10.5, fontWeight: 600, color: "var(--warn)", whiteSpace: "nowrap" }}
                      title={manual ? "Dòng giá tùy chỉnh: Áp dụng chỉ cập nhật chi hộ + thông tin hệ thống, GIỮ giá bạn gõ (về giá hệ thống: bấm ↺)" : "Số hệ thống vừa tính lại theo lô hàng + bảng giá hiện tại"}>
                      <span>{sug.infoOnly ? "Thông tin tuyến/kết nối đổi" : <>Hệ thống tính {sug.baseDiff ? <b className="tnum">{fmtNum(sug.base)}</b> : null}{sug.baseDiff && manual ? " (giữ giá gõ tay)" : null}{sug.chohoDiff ? <> · chi hộ <b className="tnum">{fmtNum(sug.choho)}</b></> : null}</>}</span>
                      {onApplyLine && <button type="button" onClick={() => onApplyLine(l.id)} style={{ ...chipBtn, background: "var(--warn)", color: "#fff" }}>Áp dụng</button>}
                    </div>
                  )}
                  <span style={{ display: "none" }} className="ke-printonly">{fmtVND(a.base)}</span>
                </td>
                <td className="tnum" style={{ textAlign: "right", padding: "6px 8px", borderBottom: d || noteOnly ? "none" : "1px solid var(--line-2)", verticalAlign: "top" }}>
                  <span className="ke-noprint">
                    <select value={(l.detail && l.detail.vat != null && l.detail.vat !== "") ? String(l.detail.vat) : ""} onChange={(e) => setLineVat(l.id, e.target.value)} style={lineVatStyle} title="VAT riêng dòng này (mặc định theo bảng kê)">
                      <option value="">Mặc định ({vatRate}%)</option>
                      {STATEMENT_VAT_RATES.map((r) => <option key={r} value={r}>{r}%</option>)}
                    </select>
                    <div className="tnum" style={{ fontSize: 11, marginTop: 2, color: a.rate ? "var(--accent)" : "var(--ink-4)" }}>{fmtNum(a.vat)}</div>
                  </span>
                  <span style={{ display: "none" }} className="ke-printonly">{fmtNum(a.vat)}{a.rate ? ` (${a.rate}%)` : ""}</span>
                </td>
                {/* Cột chi phí riêng: số nhập tay theo lô (0 = trống), in ra số */}
                {extraCols.map((c) => (
                  <td key={c.key} className="tnum" style={{ textAlign: "right", padding: "6px 8px", borderBottom: d || noteOnly ? "none" : "1px solid var(--line-2)", verticalAlign: "top" }}>
                    <ExtraCell col={c} value={a.extras[c.key] || 0} onChange={(v) => setLineExtra(l.id, c.key, v)} />
                  </td>
                ))}
                <td className="tnum" style={{ textAlign: "right", padding: "6px 8px", borderBottom: d || noteOnly ? "none" : "1px solid var(--line-2)", color: "var(--ink-2)", verticalAlign: "top", minWidth: 90 }}><ChoHoCell amount={a.choho} items={l.detail && l.detail.choHoItems} /></td>
                <td className="tnum" style={{ textAlign: "right", padding: "6px 8px", borderBottom: d || noteOnly ? "none" : "1px solid var(--line-2)", fontWeight: 700, verticalAlign: "top" }}>{fmtNum(a.total)}</td>
                <td style={{ padding: "6px 8px", borderBottom: d || noteOnly ? "none" : "1px solid var(--line-2)", verticalAlign: "top" }}><LineNoteCell value={l.userNote} onChange={(v) => setLineNote(l.id, v)} /></td>
                </>); })()}
              </tr>
              {d && d.found && (
                <tr className={(i % 2 ? "ke-zebra " : "") +"ke-lo-end"}>
                  <td style={{ borderBottom: "1px solid var(--line-2)" }}></td>
                  <td colSpan={nCols - 1} style={{ padding: "0 8px 9px", borderBottom: "1px solid var(--line-2)" }}>
                    {noteEl}
                    {/* LỘ TRÌNH lô (ĐI → NHÀ MÁY → HẠ, theo ký hiệu) — để kế toán dò bảng giá */}
                    {(d.loTrinh || l.from || l.to) && (
                      <div style={{ fontSize: 12, fontWeight: 700, margin: "2px 0 4px", color: "var(--ink-2)" }}>
                        <i className="bi bi-signpost-2-fill" style={{ color: "var(--accent)" }} /> Lộ trình: <span className="tnum">{d.loTrinh || [l.from, l.to].filter(Boolean).join(" → ")}</span>
                        <span style={{ fontSize: 10.5, fontWeight: 400, color: "var(--ink-4)" }}> (đi → nhà máy → hạ)</span>
                      </div>
                    )}
                    {/* Hàng 1: KẾT NỐI (Connect/Disconnect) + KIND + loại cont + tuyến — ghi rõ nhất */}
                    <div style={{ display: "flex", flexWrap: "wrap", alignItems: "center", gap: "5px 8px", marginBottom: 3 }}>
                      {d.conn === "Connect"
                        ? <span style={{ display: "inline-flex", alignItems: "center", gap: 4, fontSize: 11, fontWeight: 700, color: "var(--good)", background: "var(--good-weak)", padding: "2px 9px", borderRadius: 999 }}><span style={{ width: 6, height: 6, borderRadius: 999, background: "currentColor" }} />CONNECT</span>
                        : d.conn === "Disconnect"
                        ? <span style={{ display: "inline-flex", alignItems: "center", gap: 4, fontSize: 11, fontWeight: 700, color: "var(--danger)", background: "#fce8e8", padding: "2px 9px", borderRadius: 999 }}><span style={{ width: 6, height: 6, borderRadius: 999, background: "currentColor" }} />DISCONNECT</span>
                        : <span style={{ fontSize: 11, fontWeight: 700, color: "var(--ink-4)", background: "var(--line-2)", padding: "2px 9px", borderRadius: 999 }}>KẾT NỐI: chưa xác định (thiếu giờ xe ra)</span>}
                      {d.ftHours != null && <span style={{ fontSize: 11, color: "var(--ink-4)" }} className="tnum">Free time {fmtHours(d.ftHours)} · ngưỡng {d.ftThreshold}h{d.ftBasis ? " · từ " + d.ftBasis : ""}</span>}
                      <span style={{ fontSize: 11, fontWeight: 600, color: "var(--ink-3)", background: "var(--line-2)", padding: "2px 9px", borderRadius: 999 }}>{/external cru/i.test(d.kind || "") ? "CRU ngoại" : /internal cru/i.test(d.kind || "") ? "CRU nội" : "1 chiều"}</span>
                      <span style={{ fontSize: 11, fontWeight: 600, color: "var(--ink-3)", background: "var(--line-2)", padding: "2px 9px", borderRadius: 999 }} title="Loại cont của lô → cột giá đã áp trong bảng giá">{contColLabel(d)}</span>
                      {d.route && <span className="tnum" style={{ fontSize: 11, color: "var(--ink-4)" }}>{d.route}</span>}
                      {d.priceBook && <span style={{ fontSize: 11, fontWeight: 600, color: "var(--accent)", background: "var(--accent-weak-2)", border: "1px solid var(--accent-weak)", padding: "2px 9px", borderRadius: 999 }} title="Bảng giá áp theo ngày cont ra"><i className="bi bi-calendar3" /> Giá: {bookLabel(d.priceBook)}</span>}
                      {!d.matched && <span style={{ fontSize: 11, color: "var(--warn)", fontWeight: 700 }}>⚠ chưa khớp bảng giá{!d.priceBook ? " (ngày cont ra ngoài mọi bảng giá)" : ""}</span>}
                    </div>
                    {/* DÒ: vì sao chưa khớp — tiêu chí đã tìm trong bảng giá (cảng + nhà máy + loại) */}
                    {!d.matched && d.diag && (
                      <div style={{ fontSize: 11, color: "var(--ink-4)", marginBottom: 3, lineHeight: 1.6 }}>
                        <i className="bi bi-search" /> Đã dò bảng giá: đi <b className="tnum" style={{ color: "var(--ink-3)" }}>{d.diag.di}</b> → nhà máy <b className="tnum" style={{ color: "var(--ink-3)" }}>{d.diag.nhaMay}</b> → hạ <b className="tnum" style={{ color: "var(--ink-3)" }}>{d.diag.ha}</b> · loại <b style={{ color: "var(--ink-3)" }}>{/external cru/i.test(d.diag.kind || "") ? "CRU ngoại" : /internal cru/i.test(d.diag.kind || "") ? "CRU nội" : "1 chiều"}</b>{d.diag.conn ? <> · <b style={{ color: "var(--ink-3)" }}>{d.diag.conn}</b></> : null}{d.diag.contType ? <> · cont <b style={{ color: "var(--ink-3)" }}>{d.diag.contType}</b></> : null}{!d.diag.hasPrice ? <span style={{ color: "var(--warn)" }}> — khách CHƯA có bảng giá</span> : d.routeMatched ? <span style={{ color: "var(--warn)" }}> — tuyến CÓ giá nhưng thiếu cột loại cont {d.diag.contType || "(trống)"} (cột đang có: {(d.diag.contKeys || []).join(", ") || "—"})</span> : <span> — không có dòng giá khớp (kiểm tra ký hiệu đi·nhà máy·hạ trong Bảng giá)</span>}
                      </div>
                    )}
                    {/* Hàng 2: tách khoản tiền */}
                    <div style={{ fontSize: 11.5, color: "var(--ink-3)", display: "flex", flexWrap: "wrap", alignItems: "center", gap: "3px 12px", lineHeight: 1.7 }}>
                      {/* Bảng giá mới: 1 số tổng (dau = 0). Snapshot cũ còn tách cước/dầu → vẫn hiện đủ. */}
                      {d.dau > 0
                        ? <><span>Cước <b className="tnum" style={{ color: "var(--ink-2)" }}>{fmtNum(d.cuoc)}</b></span><span>+ Dầu <b className="tnum" style={{ color: "var(--ink-2)" }}>{fmtNum(d.dau)}</b></span></>
                        : <span>Cước+dầu <b className="tnum" style={{ color: "var(--ink-2)" }}>{fmtNum(d.cuoc)}</b></span>}
                      {d.isBarge && (
                        <span style={{ color: "var(--accent)" }} title={d.bargeRoute ? "Sà lan: " + d.bargeRoute : "Sà lan"}>
                          <i className="bi bi-water" /> + Cước sà lan <b className="tnum">{fmtNum((d.bargeCuoc || 0) + (d.bargeDau || 0))}</b>
                          {!d.bargeMatched && <span style={{ color: "var(--warn)", fontWeight: 700 }}> ⚠ chưa khớp{d.bargeDrop ? "" : " (thiếu nơi hạ sà lan)"}</span>}
                        </span>
                      )}
                      {d.choHoItems.map((c, j) => <span key={"h" + j} style={{ color: "var(--good)" }}>+ Chi hộ · {c.item} <b className="tnum">{fmtNum(c.amount)}</b></span>)}
                      <span style={{ fontWeight: 700 }}>= <b className="tnum" style={{ color: "var(--accent)" }}>{fmtNum(d.phaiThu)} ₫</b></span>
                      {manual && <span style={{ color: "#9a6700", fontWeight: 600 }}>· đang thu theo giá tùy chỉnh <b className="tnum">{fmtNum(lineAmt(l).base)}</b></span>}
                      <LotCostLine items={d.costItems} />
                    </div>
                  </td>
                </tr>
              )}
              {noteOnly && (
                <tr className={(i % 2 ? "ke-zebra " : "") +"ke-lo-end"}><td style={{ borderBottom: "1px solid var(--line-2)" }}></td><td colSpan={nCols - 1} style={{ padding: "0 8px 7px", borderBottom: "1px solid var(--line-2)" }}>{noteEl}</td></tr>
              )}
              {gone && (
                <tr className={(i % 2 ? "ke-zebra " : "") +"ke-lo-end"}><td style={{ borderBottom: "1px solid var(--line-2)" }}></td><td colSpan={nCols - 1} style={{ padding: "0 8px 9px", borderBottom: "1px solid var(--line-2)", fontSize: 11.5, color: "var(--ink-4)" }}>Lô không còn trong hệ thống — giữ số đã lưu, không tính lại được.</td></tr>
              )}
              </React.Fragment>
            );})}
          </tbody>
          <tfoot>
            {/* Hàng cộng theo TỪNG CỘT (nền · VAT · chi hộ) — khớp 3 cột mỗi dòng */}
            <tr style={{ fontWeight: 700 }}>
              <td colSpan={4} style={{ padding: "9px 8px", borderTop: "1.5px solid var(--line)", textAlign: "right", color: "var(--ink-3)" }}>CỘNG</td>
              <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{fmtNum(amt.base)}</td>
              <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)", color: amt.vat ? "var(--accent)" : "var(--ink-4)" }}>{fmtNum(amt.vat)}</td>
              {extraCols.map((c) => <td key={c.key} className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{fmtNum(amt.extras[c.key] || 0)}</td>)}
              <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{fmtNum(amt.choho)}</td>
              <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{fmtNum(tongThu)}</td>
              <td style={{ borderTop: "1.5px solid var(--line)" }}></td>
            </tr>
            <tr>
              <td colSpan={nCols - 3} style={{ padding: "9px 8px 3px", textAlign: "right", color: "var(--ink-3)" }}>Phải thu (cước + dầu)</td>
              <td className="tnum" colSpan={2} style={{ textAlign: "right", padding: "9px 8px 3px" }}>{fmtVND(amt.base)}</td><td></td>
            </tr>
            <tr>
              <td colSpan={nCols - 3} style={{ padding: "3px 8px", textAlign: "right", color: "var(--ink-3)" }}>VAT{vatRate ? ` (mặc định ${vatRate}%)` : ""}</td>
              <td className="tnum" colSpan={2} style={{ textAlign: "right", padding: "3px 8px", color: amt.vat ? "var(--accent)" : "var(--ink-4)" }}>{fmtVND(amt.vat)}</td><td></td>
            </tr>
            {extraCols.map((c) => (
              <tr key={c.key}>
                <td colSpan={nCols - 3} style={{ padding: "3px 8px", textAlign: "right", color: "var(--ink-3)" }}>{colLabel(c)}{c.vat ? " (chịu VAT)" : " (không VAT)"}</td>
                <td className="tnum" colSpan={2} style={{ textAlign: "right", padding: "3px 8px" }}>{fmtVND(amt.extras[c.key] || 0)}</td><td></td>
              </tr>
            ))}
            <tr>
              <td colSpan={nCols - 3} style={{ padding: "3px 8px", textAlign: "right", color: "var(--ink-3)" }}>Chi hộ (không VAT)</td>
              <td className="tnum" colSpan={2} style={{ textAlign: "right", padding: "3px 8px" }}>{fmtVND(amt.choho)}</td><td></td>
            </tr>
            <tr style={{ fontWeight: 700 }}>
              <td colSpan={nCols - 3} style={{ padding: "8px 8px 11px", textAlign: "right" }}>TỔNG TIỀN</td>
              <td className="tnum" colSpan={2} style={{ textAlign: "right", padding: "8px 8px 11px" }}>{fmtVND(tongThu)}</td><td></td>
            </tr>
          </tfoot>
        </table>
        </div>

        {/* payments / công nợ */}
        <div style={{ marginTop: 18 }}>
          <div style={{ fontSize: 12.5, fontWeight: 700, color: "var(--ink-2)", textTransform: "uppercase", letterSpacing: "0.04em", marginBottom: 6 }}>Khách thanh toán (nhiều đợt)</div>
          <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12.5 }}>
            <thead><tr style={{ background: "#fafbfc" }}>{th("Đợt", "center")}{th("Ngày thu")}{th("Ghi chú")}{th("Số tiền", "right")}<th className="ke-noprint" style={{ width: 34, borderBottom: "1.5px solid var(--line)" }}></th></tr></thead>
            <tbody>
              {payments.length === 0 && <tr><td colSpan={5} style={{ padding: "14px", textAlign: "center", color: "var(--ink-4)" }}>Chưa có đợt thanh toán nào.</td></tr>}
              {payments.map((p, i) => (
                <tr key={p.id}>
                  <td className="tnum" style={{ textAlign: "center", padding: "6px 8px", borderBottom: "1px solid var(--line-2)", color: "var(--ink-4)" }}>{i + 1}</td>
                  <td style={{ padding: "6px 8px", borderBottom: "1px solid var(--line-2)" }}>
                    <DateField value={p.date || ""} onChange={(v) => setP(p.id, { date: v })} />
                  </td>
                  <td style={{ padding: "6px 8px", borderBottom: "1px solid var(--line-2)" }}>
                    <input value={p.note || ""} onChange={(e) => setP(p.id, { note: e.target.value })} placeholder="VD: chuyển khoản…"
                      style={{ width: "100%", padding: "6px 8px", fontSize: 12.5, border: "1px solid var(--line)", borderRadius: 7, outline: "none" }} />
                  </td>
                  <td style={{ padding: "6px 8px", borderBottom: "1px solid var(--line-2)" }}>
                    <div style={{ position: "relative" }}>
                      <input inputMode="numeric" value={grp(p.amount)} onChange={(e) => setP(p.id, { amount: e.target.value.replace(/[^\d]/g, "") })} placeholder="0" className="tnum"
                        style={{ width: "100%", padding: "6px 24px 6px 8px", fontSize: 12.5, textAlign: "right", fontWeight: 600, border: "1px solid var(--line)", borderRadius: 7, outline: "none" }} />
                      <span style={{ position: "absolute", right: 8, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 12, pointerEvents: "none" }}>₫</span>
                    </div>
                  </td>
                  <td className="ke-noprint" style={{ textAlign: "center", padding: "6px 4px", borderBottom: "1px solid var(--line-2)" }}>
                    <button type="button" onClick={() => delP(p.id)} title="Xóa đợt"
                      style={{ width: 26, height: 26, display: "grid", placeItems: "center", border: "none", borderRadius: 6, background: "transparent", color: "var(--ink-4)", cursor: "pointer" }}
                      onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
                      onMouseLeave={(e) => { e.currentTarget.style.background = "transparent"; e.currentTarget.style.color = "var(--ink-4)"; }}><I.trash /></button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          <button type="button" onClick={addP} className="ke-noprint"
            style={{ display: "inline-flex", alignItems: "center", gap: 7, margin: "8px 0 2px", padding: "6px 11px", fontSize: 12.5, fontWeight: 600, border: "none", borderRadius: 7, background: "var(--accent-weak)", color: "var(--accent)", cursor: "pointer" }}>
            <I.plus /> Thêm đợt thanh toán
          </button>
        </div>

        <div style={{ marginTop: 16, display: "flex", justifyContent: "flex-end" }}>
          <AmountSummary amt={amt} vatRate={vatRate} paid={daTT} payCount={payments.length} showDebt extras={extraCols.map((c) => ({ ...c, sum: amt.extras[c.key] || 0 }))}>
            <DueSummaryRow st={st} tongThu={tongThu} payments={payments} onChange={(v) => onUpdate && onUpdate({ ...st, info: { ...info, dueDate: v } })} />
          </AmountSummary>
        </div>
      </div>
  );
}

/* Hàng nút thao tác bảng kê (Xóa · trạng thái dirty · Lưu · In) dùng chung. */
function StatementActions({ st, onDelete, onSave, isDirty, onClose, closeLabel = "Đóng" }) {
  const { useState } = React;
  const dirty = !!(isDirty && isDirty(st.id));
  const [saving, setSaving] = useState(false);
  const doSave = () => { if (saving || !dirty) return; setSaving(true); Promise.resolve(onSave && onSave()).then(() => setSaving(false)).catch(() => setSaving(false)); };
  const esc = (s) => String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const askDelete = async () => {
    if (!onDelete) return;
    const ok = await window.confirmAction({
      title: "Xóa bảng kê?",
      text: `Bảng kê <b>${esc(st.no || "(chưa có số)")}</b>${st.customer ? " · " + esc(st.customer) : ""} sẽ bị xóa vĩnh viễn cùng toàn bộ đợt thanh toán. Không thể hoàn tác.`,
      confirmText: '<i class="bi bi-trash me-1"></i> Xóa bảng kê',
      danger: true,
    });
    if (ok) onDelete(st.id);
  };
  return (
    <div className="ke-noprint" style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 16 }}>
      <button type="button" onClick={askDelete}
        style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "8px 13px", fontSize: 13, fontWeight: 500, border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: "var(--ink-3)", cursor: "pointer" }}
        onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
        onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-3)"; }}>
        <I.trash /> Xóa bảng kê
      </button>
      <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
        {dirty && <span style={{ fontSize: 12, color: "var(--warn)", fontWeight: 600, display: "inline-flex", alignItems: "center", gap: 5 }}><span style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} />Có thay đổi chưa lưu</span>}
        {onClose && <Btn onClick={onClose}>{closeLabel}</Btn>}
        <Btn variant="primary" onClick={doSave} disabled={!dirty || saving}>{saving ? "Đang lưu…" : "Lưu"}</Btn>
        <Btn onClick={() => window.print()}>In / Xuất PDF</Btn>
      </div>
    </div>
  );
}

function SavedStatementModal({ st, onClose, onDelete, onUpdate, onSave, isDirty }) {
  const footer = <StatementActions st={st} isDirty={isDirty} onSave={onSave} onClose={onClose}
    onDelete={(id) => { onDelete(id); onClose(); }} />;
  return (
    <Modal title="Bảng kê cần thu" subtitle={st.no + " · " + st.customer} onClose={onClose} footer={footer} width={940} icon={<I.fx />}>
      <StatementDetailBody st={st} onUpdate={onUpdate} />
    </Modal>
  );
}

/* Trang xem bảng kê đã lưu (route riêng) — chi tiết đầy đủ như lúc tạo.
   suggestById = kết quả "Tính lại" (gợi ý); onApplyLine/onApplyAll áp gợi ý vào dòng; onClearSuggest ẩn gợi ý. */
function SavedStatementPage({ st, onUpdate, onSave, onDelete, isDirty, backUrl, onExcel, onRecalc, detailById, suggestById = null, onApplyLine, onApplyAll, onClearSuggest, newLots = null, inOtherStatements = 0, onAddNewLots }) {
  // Tổng kết gợi ý: dòng có giá/chi hộ mới (dòng giá tùy chỉnh: Áp dụng giữ giá gõ tay), lô đã mất, lô mới trong kỳ.
  const vr = +st.vatRate || 0;
  let nNew = 0, nManualKept = 0, nApplicable = 0, nGone = 0;
  if (suggestById) (st.lines || []).forEach((l) => {
    const s = suggestById[l.id];
    if (s && !s.found) { nGone++; return; }
    if (!suggestionOf(l, s, vr)) return;
    nNew++; nApplicable++; if (isManualLine(l)) nManualKept++;
  });
  const nAdd = (newLots || []).length;
  const recalcDiff = nNew > 0 || nAdd > 0;
  // Khung rộng thêm theo số cột chi phí riêng (tối đa 4 cột; nhiều hơn thì bảng cuộn ngang).
  const wide = 1120 + 110 * Math.min(4, (st.extraCols || []).length);
  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <div className="ke-noprint trk-head" style={{ display: "flex", alignItems: "center", gap: 10, padding: "14px 22px", background: "#fff", borderBottom: "1px solid var(--line)" }}>
        <div className="trk-head-lead" style={{ display: "flex", alignItems: "center", gap: 10, flex: 1, minWidth: 0 }}>
          <a href={backUrl} title="Về danh sách bảng kê"
            style={{ display: "inline-flex", alignItems: "center", gap: 6, flexShrink: 0, padding: "7px 12px", fontSize: 13, fontWeight: 600, color: "var(--ink-2)", textDecoration: "none", border: "1px solid var(--line)", borderRadius: 9 }}>
            <span style={{ transform: "rotate(180deg)", display: "inline-flex" }}><I.arrow /></span> Bảng kê
          </a>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 16, fontWeight: 700, letterSpacing: "-0.01em" }}>Bảng kê cần thu</div>
            <div className="tnum" style={{ fontSize: 12.5, color: "var(--ink-3)" }}>{st.no} · {st.customer}</div>
          </div>
        </div>
        {onRecalc && <button type="button" onClick={onRecalc} title="Tính lại theo lô hàng + bảng giá hiện tại — chỉ GỢI Ý, không đổi số đã lưu; giá tùy chỉnh giữ nguyên"
          style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "9px 15px", fontSize: 13.5, fontWeight: 600, cursor: "pointer", color: recalcDiff ? "#fff" : "var(--ink-2)", background: recalcDiff ? "var(--warn)" : "#fff", border: recalcDiff ? "none" : "1px solid var(--line)", borderRadius: 9 }}>
          <I.fx /> Tính lại{recalcDiff ? ` (${[nNew && `${nNew} dòng có giá mới`, nAdd && `${nAdd} lô mới`].filter(Boolean).join(", ")})` : ""}
        </button>}
        {onExcel && <button type="button" onClick={onExcel}
          style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "9px 15px", fontSize: 13.5, fontWeight: 600, cursor: "pointer", color: "#fff", background: "var(--good)", border: "none", borderRadius: 9 }}>
          <I.check /> Xuất Excel
        </button>}
      </div>
      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: "22px" }}>
        <div className="ke-noprint" style={{ maxWidth: wide, margin: "0 auto 14px", display: "flex", gap: 10, alignItems: "flex-start", background: "#eff5ff", border: "1px solid #cfe0fb", borderRadius: 12, padding: "12px 14px", fontSize: 13, color: "#1f4f9e", lineHeight: 1.6 }}>
          <i className="bi bi-info-circle-fill" style={{ fontSize: 16, marginTop: 1, flexShrink: 0 }} />
          <div>
            Bảng kê này đã được <b>chốt số khi tạo</b> để lưu hồ sơ. Nếu sau này <b>lô hàng gốc bị sửa hoặc xóa</b>, bảng kê này <b>vẫn giữ nguyên</b> — không bị ảnh hưởng.
            <br />Bấm <b>Tính lại</b> để hệ thống tính theo lô hàng + bảng giá hiện tại (chi hộ mới, giá mới) và tìm <b>lô mới trong kỳ</b>; số đã lưu chỉ đổi khi bạn bấm <b>Áp dụng</b> / <b>Thêm lô</b> rồi <b>Lưu</b>. Ô nào bạn gõ tay là <b>giá tùy chỉnh</b> (viền vàng) — Áp dụng chỉ cập nhật chi hộ, <b>giữ nguyên giá gõ tay</b>.
          </div>
        </div>
        {/* Thanh tổng kết gợi ý sau Tính lại */}
        {suggestById && (
          <div className="ke-noprint" style={{ maxWidth: wide, margin: "0 auto 14px", display: "flex", gap: 12, alignItems: "center", flexWrap: "wrap", background: nNew ? "#fff7e6" : "var(--good-weak)", border: "1px solid " + (nNew ? "#ffe1a8" : "var(--good)"), borderRadius: 12, padding: "10px 14px", fontSize: 13, color: nNew ? "#7a5200" : "var(--good)", lineHeight: 1.5 }}>
            <i className="bi bi-calculator-fill" style={{ fontSize: 16, flexShrink: 0 }} />
            <div style={{ flex: 1, minWidth: 240 }}>
              {nNew
                ? <>Hệ thống tính ra <b>{nNew}</b> dòng có giá / chi hộ khác số đang dùng{nManualKept ? <> — trong đó <b>{nManualKept}</b> dòng <b>giá tùy chỉnh</b>: Áp dụng chỉ cập nhật chi hộ, <b>giữ giá gõ tay</b></> : null}. Số đã lưu chưa thay đổi.</>
                : <>Giá và chi hộ khớp dữ liệu hiện tại.</>}
              {nGone ? <> · {nGone} lô không còn trong hệ thống, giữ số đã lưu.</> : null}
              {nAdd ? <> · <b>{nAdd}</b> lô mới trong kỳ chưa có trong bảng kê.</> : null}
              {inOtherStatements ? <> · {inOtherStatements} lô trong kỳ đã nằm ở bảng kê khác (không thêm).</> : null}
            </div>
            {nAdd > 0 && onAddNewLots && <Btn variant="primary" onClick={onAddNewLots} title={(newLots || []).map((x) => `${x.contNo || x.booking} · ${x.date || ""}`).join("\n")}>Thêm {nAdd} lô mới</Btn>}
            {nApplicable > 0 && onApplyAll && <Btn variant="primary" onClick={onApplyAll} title="Áp cho mọi dòng có gợi ý — dòng giá tùy chỉnh giữ giá gõ tay, chỉ cập nhật chi hộ">Áp dụng {nApplicable} dòng</Btn>}
            {onClearSuggest && <Btn onClick={onClearSuggest}>Ẩn gợi ý</Btn>}
          </div>
        )}
        <div style={{ maxWidth: wide, margin: "0 auto", background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "8px 22px 18px" }}>
          <StatementDetailBody st={st} onUpdate={onUpdate} detailById={detailById || {}} suggestById={suggestById} onApplyLine={onApplyLine} />
          <div style={{ marginTop: 16, paddingTop: 14, borderTop: "1px solid var(--line)" }}>
            <StatementActions st={st} isDirty={isDirty} onSave={onSave} onDelete={(id) => { Promise.resolve(onDelete && onDelete(id)).then(() => { window.location.href = backUrl; }); }} />
          </div>
        </div>
      </div>
    </div>
  );
}


/* Chip: bảng kê có lô mà hệ thống tính ra giá/chi hộ khác số đã lưu (giá tùy chỉnh không tính) → mở, bấm Tính lại xem gợi ý. */
function DriftChip({ n }) {
  return (
    <span title={`${n} lô có giá / chi hộ hệ thống tính khác số đã lưu — mở bảng kê, bấm “Tính lại” để xem gợi ý và Áp dụng nếu đúng. Giá tùy chỉnh không bị ghi đè.`}
      style={{ display: "inline-flex", alignItems: "center", gap: 4, fontSize: 11, fontWeight: 700, color: "#7a5200", background: "#fff1cc", border: "1px solid #ffe1a8", padding: "1px 8px", borderRadius: 999, whiteSpace: "nowrap", verticalAlign: "middle" }}>
      <i className="bi bi-calculator" style={{ fontSize: 10 }} /> Giá mới: {n} lô
    </span>
  );
}

/* TRẠNG THÁI HẠN THANH TOÁN ở danh sách — st.dueDate do backend tính (statementDueDate), còn nợ = Tổng tiền − đã thu. */
const paidOf = (st) => (st.payments || []).reduce((a, p) => a + (parseInt((p.amount || "0").toString().replace(/[^\d]/g, ""), 10) || 0), 0);
const localTodayIso = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`; };
const DUE_SOON_DAYS = 7;
function dueStatus(st, today) {
  const con = (st.tongThu || 0) - paidOf(st);
  if ((st.tongThu || 0) > 0 && con <= 0) return { key: "paid", con: 0, label: "Đã thu đủ" };
  if (!st.dueDate) return { key: "none", con, label: "Chưa đặt hạn" };
  const n = daysBetweenIso(today, st.dueDate);
  if (n < 0) return { key: "overdue", con, label: `Quá hạn ${-n} ngày` };
  if (n <= DUE_SOON_DAYS) return { key: "soon", con, label: n === 0 ? "Đến hạn hôm nay" : `Còn ${n} ngày` };
  return { key: "later", con, label: `Còn ${n} ngày` };
}
const DUE_FILTERS = [
  { key: "all", label: "Tất cả" },
  { key: "overdue", label: "Quá hạn" },
  { key: "soon", label: `Đến hạn trong ${DUE_SOON_DAYS} ngày` },
  { key: "later", label: "Chưa đến hạn" },
  { key: "none", label: "Chưa đặt hạn" },
  { key: "paid", label: "Đã thu đủ" },
];
const DUE_TONE = {
  overdue: { c: "#b42318", bg: "#fdecea", bd: "#f6c4bd" },
  soon:    { c: "#7a5200", bg: "#fff1cc", bd: "#ffe1a8" },
  later:   { c: "var(--ink-3)", bg: "var(--line-2)", bd: "var(--line)" },
  none:    { c: "var(--ink-4)", bg: "#fff", bd: "var(--line)" },
  paid:    { c: "var(--good)", bg: "var(--good-weak)", bd: "#bfe3cf" },
};
const DUE_FILTER_KEY = "trk:bangke:dueFilter";
function DueChip({ s }) {
  const t = DUE_TONE[s.key] || DUE_TONE.none;
  return <span style={{ display: "inline-block", fontSize: 11, fontWeight: 700, color: t.c, background: t.bg, border: "1px solid " + t.bd, padding: "1px 8px", borderRadius: 999, whiteSpace: "nowrap" }}>{s.label}</span>;
}

/* GẮN HẠN THANH TOÁN ngay trên danh sách: bấm ngày → popup chọn ngày / "ngày lập + N ngày" → lưu luôn
   (onSet = PATCH .../due, chỉ ghi info.dueDate). Popup position:fixed để không bị khung bảng cắt. */
function DueEditor({ st, onSet }) {
  const btn = React.useRef(null); const pop = React.useRef(null);
  const [pos, setPos] = useState(null);   // null = đóng
  const [busy, setBusy] = useState(false);
  const open = () => {
    const r = btn.current.getBoundingClientRect(); const h = 120;
    setPos({ left: Math.max(8, Math.min(r.left, window.innerWidth - 300)), top: r.bottom + h + 8 > window.innerHeight ? r.top - h - 6 : r.bottom + 6 });
  };
  useEffect(() => {
    if (!pos) return;
    // Lịch Flatpickr gắn vào body → coi là "bên trong" để chọn ngày không làm đóng popup.
    const inside = (t) => (pop.current && pop.current.contains(t)) || (btn.current && btn.current.contains(t)) || !!(t && t.closest && t.closest(".flatpickr-calendar"));
    const onDown = (e) => { if (!inside(e.target)) setPos(null); };
    const onScroll = (e) => { if (!inside(e.target)) setPos(null); };
    document.addEventListener("mousedown", onDown); window.addEventListener("scroll", onScroll, true);
    return () => { document.removeEventListener("mousedown", onDown); window.removeEventListener("scroll", onScroll, true); };
  }, [pos]);
  const save = async (v) => {
    setBusy(true);
    try { const r = await onSet(st, v); if (r && r.ok) setPos(null); } catch (e) { /* trkApi đã báo lỗi */ } finally { setBusy(false); }
  };
  return (
    <span onClick={(e) => e.stopPropagation()} onKeyDown={(e) => e.stopPropagation()}>
      <button ref={btn} type="button" onClick={() => (pos ? setPos(null) : open())} title="Bấm để đặt / sửa hạn thanh toán"
        style={{ display: "inline-flex", alignItems: "center", gap: 5, padding: "2px 6px", margin: "-2px -6px", border: "1px dashed transparent", borderRadius: 6, background: "transparent", cursor: "pointer", font: "inherit", color: st.dueDate ? "var(--ink-2)" : "var(--accent)", fontWeight: st.dueDate ? 400 : 600 }}
        onMouseEnter={(e) => (e.currentTarget.style.borderColor = "var(--line)")} onMouseLeave={(e) => (e.currentTarget.style.borderColor = "transparent")}>
        {st.dueDate ? fmtDate(st.dueDate) : "+ Đặt hạn"} <i className="bi bi-pencil" style={{ fontSize: 10, color: "var(--ink-4)" }} />
      </button>
      {pos && (
        <div ref={pop} style={{ position: "fixed", zIndex: 1000, top: pos.top, left: pos.left, width: 290, background: "#fff", border: "1px solid var(--line)", borderRadius: 10, boxShadow: "0 10px 28px rgba(16,19,23,.16)", padding: "10px 12px", cursor: "default", textAlign: "left", whiteSpace: "normal" }}>
          <div style={{ fontSize: 12, fontWeight: 700, color: "var(--ink-2)", marginBottom: 7 }}>Hạn thanh toán · <span className="tnum">{st.no}</span></div>
          <DueDateField base={st.date} value={st.dueDate} onChange={save} width={150} />
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 8, marginTop: 8, fontSize: 11.5, color: "var(--ink-4)" }}>
            <span>{busy ? "Đang lưu…" : `Ngày lập ${fmtDate(st.date)} · lưu ngay khi chọn`}</span>
            {st.dueDate && <button type="button" onClick={() => save("")} disabled={busy} style={{ border: "none", background: "transparent", color: "var(--danger)", cursor: "pointer", fontSize: 11.5, fontWeight: 600, padding: 0 }}>Bỏ hạn</button>}
          </div>
        </div>
      )}
    </span>
  );
}

/* onSetDue(st, dueDate) → Promise<{ok, dueDate}>; null = không có quyền sửa (chỉ xem hạn). */
function KePage({ ke, drift = {}, onNew, onOpen, onSetDue = null, children }) {
  const isMobile = useIsMobile();
  // Cột khách minmax(0,1fr) để tên dài/chip KHÔNG kéo lưới tràn khung (trước đây cột Tổng tiền bị cắt).
  const cols = "130px minmax(0, 1fr) 96px 128px 150px 128px 100px 110px 140px";
  const money = { textAlign: "right", whiteSpace: "nowrap" };
  const driftOf = (st) => drift[String(st.id)];
  const num = (v, fb) => (v == null ? fb : v);   // số dẫn xuất từ backend; fallback nếu boot cũ
  // LỌC THEO HẠN THANH TOÁN (nhớ lựa chọn trên máy): Quá hạn / Đến hạn trong 7 ngày / Chưa đến hạn / Chưa đặt hạn / Đã thu đủ.
  const [fk, setFk] = useState(() => { try { return localStorage.getItem(DUE_FILTER_KEY) || "all"; } catch (e) { return "all"; } });
  useEffect(() => { try { localStorage.setItem(DUE_FILTER_KEY, fk); } catch (e) {} }, [fk]);
  const today = localTodayIso();
  const rows = ke.slice().reverse().map((st) => ({ st, s: dueStatus(st, today) }));
  const counts = {}; rows.forEach((r) => { counts[r.s.key] = (counts[r.s.key] || 0) + 1; });
  let shown = fk === "all" ? rows : rows.filter((r) => r.s.key === fk);
  // Lọc theo hạn → xếp hạn gần nhất (quá hạn lâu nhất) lên đầu; Tất cả giữ mới tạo trước.
  if (fk === "overdue" || fk === "soon" || fk === "later") shown = shown.slice().sort((a, b) => (a.st.dueDate < b.st.dueDate ? -1 : a.st.dueDate > b.st.dueDate ? 1 : 0));
  const conSum = shown.reduce((a, r) => a + Math.max(0, r.s.con), 0);
  const emptyText = ke.length ? "Không có bảng kê nào ở mục này." : "Chưa có bảng kê nào. Bấm “Tạo bảng kê mới” để bắt đầu.";
  const chip = (on, key, n) => {
    const t = key !== "all" ? DUE_TONE[key] : null;
    return { display: "inline-flex", alignItems: "center", gap: 6, padding: "6px 11px", fontSize: 12.5, fontWeight: 600, borderRadius: 8, cursor: "pointer", whiteSpace: "nowrap",
      border: "1px solid " + (on ? (t ? t.c : "var(--accent)") : "var(--line)"), background: on ? (t ? t.bg : "var(--accent-weak-2)") : "#fff",
      color: on ? (t ? t.c : "var(--accent)") : (n ? "var(--ink-2)" : "var(--ink-4)") };
  };
  const tongCell = (st, s) => (
    <span className="tnum" style={{ ...money }}>
      <div style={{ fontWeight: 700 }}>{fmtVND(st.tongThu)}</div>
      {s.key !== "paid" && s.con > 0 && s.con < (st.tongThu || 0) && <div style={{ fontSize: 11.5, color: "var(--warn)", fontWeight: 600 }} title="Đã thu một phần">còn {fmtNum(s.con)}</div>}
    </span>
  );
  return (
    <div style={{ flex: 1, minHeight: 0, display: "flex", flexDirection: "column", padding: isMobile ? "16px 14px 24px" : "20px 22px 24px", overflow: "auto" }}>
      <div style={{ maxWidth: 1280, width: "100%", margin: "0 auto" }}>
        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 16, marginBottom: 18, flexWrap: "wrap" }}>
          <div>
            <h1 style={{ margin: 0, fontSize: 22, fontWeight: 700, letterSpacing: "-0.02em" }}>Bảng kê cần thu</h1>
            <div style={{ fontSize: 13, color: "var(--ink-3)", marginTop: 3 }}>{ke.length} bảng kê đã tạo</div>
          </div>
          <button type="button" onClick={onNew}
            style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "10px 16px", fontSize: 13.5, fontWeight: 600, cursor: "pointer", color: "#fff", background: "var(--accent)", border: "none", borderRadius: 10, boxShadow: "0 1px 2px rgba(42,111,219,.4)" }}>
            <I.plus /> Tạo bảng kê mới
          </button>
        </div>
        {children /* vd khung "Khách có lô trong kỳ" — trang danh sách truyền vào */}
        {/* ===== Lọc theo hạn thanh toán ===== */}
        {ke.length > 0 && (
          <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap", marginBottom: 10 }}>
            <span style={{ fontSize: 12.5, fontWeight: 700, color: "var(--ink-3)", display: "inline-flex", alignItems: "center", gap: 6 }}><i className="bi bi-alarm" /> Hạn thanh toán</span>
            {DUE_FILTERS.map((f) => {
              const n = f.key === "all" ? rows.length : (counts[f.key] || 0);
              return <button key={f.key} type="button" onClick={() => setFk(f.key)} style={chip(fk === f.key, f.key, n)}>{f.label} <b className="tnum">{n}</b></button>;
            })}
            <div style={{ flex: 1 }} />
            <span className="tnum" style={{ fontSize: 12.5, color: "var(--ink-3)", whiteSpace: "nowrap" }}>
              Còn phải thu{fk !== "all" ? ` (${shown.length} bảng kê)` : ""}: <b style={{ color: conSum ? "var(--warn)" : "var(--good)" }}>{fmtVND(conSum)}</b>
            </span>
          </div>
        )}
        {/* ===== Mobile: card list ===== */}
        {isMobile && (
          <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
            {shown.length === 0 && <div style={{ padding: "44px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5, background: "#fff", border: "1px solid var(--line)", borderRadius: 12 }}>{emptyText}</div>}
            {shown.map(({ st, s }) => (
                <div key={st.id} role="button" tabIndex={0} onClick={() => onOpen(st)} onKeyDown={(e) => { if (e.key === "Enter") onOpen(st); }}
                  style={{ width: "100%", textAlign: "left", background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "12px 14px", cursor: "pointer", boxShadow: "0 1px 2px rgba(16,19,23,.04)" }}>
                  <div style={{ display: "flex", justifyContent: "space-between", gap: 10, alignItems: "baseline" }}>
                    <span className="tnum" style={{ fontWeight: 700, color: "var(--accent)", fontSize: 14 }}>{st.no}</span>
                    <span className="tnum" style={{ color: "var(--ink-3)", fontSize: 12.5 }}>{fmtDate(st.date)}</span>
                  </div>
                  <div style={{ fontWeight: 600, fontSize: 14.5, marginTop: 4, display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap" }}>{st.customer}{driftOf(st) ? <DriftChip n={driftOf(st).changed} /> : null}</div>
                  <div className="tnum" style={{ color: "var(--ink-4)", fontSize: 12, marginTop: 2 }}>{(st.from || st.to) ? `Cont ra: ${fmtDate(st.from) || "…"} – ${fmtDate(st.to) || "…"}` : "—"}</div>
                  <div className="tnum" style={{ display: "flex", alignItems: "center", gap: 8, marginTop: 6, fontSize: 12.5, color: "var(--ink-3)" }}>Hạn TT: {onSetDue ? <DueEditor st={st} onSet={onSetDue} /> : <b style={{ color: "var(--ink-2)" }}>{st.dueDate ? fmtDate(st.dueDate) : "—"}</b>} <DueChip s={s} /></div>
                  <div style={{ marginTop: 9, paddingTop: 9, borderTop: "1px solid var(--line-2)", fontSize: 12.5, color: "var(--ink-3)", display: "flex", flexWrap: "wrap", gap: "2px 12px" }}>
                    <span>Cước+dầu: <b className="tnum" style={{ color: "var(--ink-2)" }}>{fmtNum(num(st.baseAmount, st.tongThu))}</b></span>
                    <span>VAT {num(st.vatRate, 0)}%: <b className="tnum" style={{ color: (st.vatRate ? "var(--accent)" : "var(--ink-2)") }}>{fmtNum(num(st.vatAmount, 0))}</b></span>
                    <span>Chi hộ: <b className="tnum" style={{ color: "var(--ink-2)" }}>{fmtNum(num(st.chohoAmount, 0))}</b></span>
                    {st.extraAmount > 0 && <span>Phí riêng: <b className="tnum" style={{ color: "var(--ink-2)" }}>{fmtNum(st.extraAmount)}</b></span>}
                  </div>
                  <div style={{ display: "flex", justifyContent: "space-between", gap: 12, marginTop: 6 }}>
                    <span style={{ fontSize: 13, color: "var(--ink-3)" }}>Tổng tiền: <b className="tnum" style={{ color: "var(--ink)" }}>{fmtVND(st.tongThu)}</b></span>
                    <span style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Còn: <b className="tnum" style={{ color: s.con > 0 ? "var(--warn)" : "var(--good)" }}>{fmtVND(Math.max(0, s.con))}</b></span>
                  </div>
                </div>
            ))}
          </div>
        )}
        {/* ===== Desktop: grid table ===== */}
        {!isMobile && (
        <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, overflow: "hidden" }}>
          <div style={{ display: "grid", gridTemplateColumns: cols, gap: 12, padding: "11px 16px", background: "#fafbfc", borderBottom: "1px solid var(--line)", fontSize: 11, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.04em" }}>
            <div>Số bảng kê</div><div>Khách hàng</div><div>Ngày lập</div><div>Hạn TT</div><div>Kỳ cont ra</div><div style={{ textAlign: "right" }}>Phải thu<br/>(cước+dầu)</div><div style={{ textAlign: "right" }}>+VAT</div><div style={{ textAlign: "right" }}>Chi hộ</div><div style={{ textAlign: "right" }}>Tổng tiền</div>
          </div>
          {shown.length === 0 && <div style={{ padding: "44px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5 }}>{emptyText}</div>}
          {shown.map(({ st, s }, i) => (
            <div key={st.id} role="button" tabIndex={0} onClick={() => onOpen(st)} onKeyDown={(e) => { if (e.key === "Enter") onOpen(st); }}
              style={{ width: "100%", textAlign: "left", display: "grid", gridTemplateColumns: cols, gap: 12, alignItems: "center", padding: "12px 16px", background: "transparent", border: "none", borderBottom: i < shown.length - 1 ? "1px solid var(--line-2)" : "none", cursor: "pointer", fontSize: 13.5 }}
              onMouseEnter={(e) => (e.currentTarget.style.background = "var(--accent-weak-2)")}
              onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}>
              <span className="tnum" style={{ fontWeight: 600, color: "var(--accent)", whiteSpace: "nowrap" }}>{st.no}</span>
              <span style={{ fontWeight: 500, minWidth: 0 }}>
                <div style={{ overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }} title={st.customer}>{st.customer}</div>
                {(driftOf(st) || (st.ioScope && st.ioScope !== "all") || st.extraAmount > 0) ? (
                  <div style={{ marginTop: 3, display: "flex", gap: 6, flexWrap: "wrap" }}>
                    {st.ioScope && st.ioScope !== "all" ? <span style={{ fontSize: 11, fontWeight: 700, color: "var(--accent)", background: "var(--accent-weak-2)", border: "1px solid var(--accent-weak)", padding: "1px 8px", borderRadius: 999, whiteSpace: "nowrap" }} title="Phạm vi bảng kê — chỉ tính tiền lô loại này">{st.ioScope === "nhap" ? "Hàng nhập" : "Hàng xuất"}</span> : null}
                    {st.extraAmount > 0 ? <span className="tnum" style={{ fontSize: 11, fontWeight: 700, color: "var(--ink-3)", background: "var(--line-2)", padding: "1px 8px", borderRadius: 999, whiteSpace: "nowrap" }} title="Σ cột chi phí riêng của bảng kê — đã gồm trong Tổng tiền">+ Phí riêng {fmtNum(st.extraAmount)}</span> : null}
                    {driftOf(st) ? <DriftChip n={driftOf(st).changed} /> : null}
                  </div>
                ) : null}
              </span>
              <span className="tnum" style={{ color: "var(--ink-2)", whiteSpace: "nowrap" }}>{fmtDate(st.date)}</span>
              <span className="tnum" style={{ whiteSpace: "nowrap" }}>
                <div>{onSetDue ? <DueEditor st={st} onSet={onSetDue} /> : <span style={{ color: st.dueDate ? "var(--ink-2)" : "var(--ink-4)" }}>{st.dueDate ? fmtDate(st.dueDate) : "—"}</span>}</div>
                {(st.dueDate || s.key === "paid") && <div style={{ marginTop: 3 }}><DueChip s={s} /></div>}
              </span>
              <span className="tnum" style={{ color: "var(--ink-3)", fontSize: 12.5, whiteSpace: "nowrap" }}>{(st.from || st.to) ? `${fmtDate(st.from) || "…"} – ${fmtDate(st.to) || "…"}` : "—"}</span>
              <span className="tnum" style={{ ...money, color: "var(--ink-2)" }}>{fmtNum(num(st.baseAmount, st.tongThu))}</span>
              <span className="tnum" style={{ ...money, color: (st.vatRate ? "var(--accent)" : "var(--ink-4)") }} title={`VAT ${num(st.vatRate, 0)}%`}>{fmtNum(num(st.vatAmount, 0))}</span>
              <span className="tnum" style={{ ...money, color: "var(--ink-3)" }}>{fmtNum(num(st.chohoAmount, 0))}</span>
              {tongCell(st, s)}
            </div>
          ))}
        </div>
        )}
      </div>
    </div>
  );
}


export { StatementForm, StatementDetailBody, StatementActions, SavedStatementModal, SavedStatementPage, DriftChip, KePage, isManualLine, suggestionOf, applySuggestion, candidateToLine };
