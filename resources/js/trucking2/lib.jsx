import React from "react";
import { createPortal } from "react-dom";
const { useState, useRef, useMemo, useEffect, useCallback } = React;

/* ============================ responsive ============================ */
/* Hook dùng chung: true khi viewport ≤ bp (mặc định 640px = điện thoại).
   Dùng để stack grid/sidebar, đổi bảng→card, bớt padding trên mobile. */
function useIsMobile(bp = 640) {
  const get = () => (typeof window !== "undefined" ? window.innerWidth <= bp : false);
  const [m, setM] = useState(get);
  useEffect(() => {
    const on = () => setM(get());
    window.addEventListener("resize", on);
    window.addEventListener("orientationchange", on);
    return () => { window.removeEventListener("resize", on); window.removeEventListener("orientationchange", on); };
  }, [bp]);
  return m;
}

/* ============================ helpers ============================ */
const onlyDigits = (s) => (s || "").toString().replace(/[^\d]/g, "");
const groupVND = (d) => { d = onlyDigits(d); return d ? d.replace(/\B(?=(\d{3})+(?!\d))/g, ".") : ""; };
const toNum = (d) => { d = onlyDigits(d); return d ? parseInt(d, 10) : 0; };
const fmtVND = (n) => (n || 0).toLocaleString("vi-VN") + " ₫";
const fmtNum = (n) => (n || 0).toLocaleString("vi-VN");   // số đầy đủ, có dấu phân cách, không làm tròn
const fmtShort = (n) => {
  n = n || 0;
  if (n >= 1e9) return (n / 1e9).toFixed(n % 1e9 ? 1 : 0).replace(".", ",") + " tỷ";
  if (n >= 1e6) return (n / 1e6).toFixed(n % 1e6 ? 1 : 0).replace(".", ",") + "tr";
  if (n >= 1e3) return Math.round(n / 1e3) + "k";
  return String(n);
};
const fmtDate = (iso) => { if (!iso) return ""; const [y, m, d] = iso.split("-"); return d ? `${d}/${m}/${y}` : iso; };

const PAYERS = ["Tài xế", "A.Hoàn", "Xe ngoài", "TK công ty", "Khách"];
const VAT_RATE = 0.08;

/* Lựa chọn % VAT cho bảng kê (cấp statement). */
const STATEMENT_VAT_RATES = [0, 8, 10];

/**
 * NGUỒN CHÂN LÝ DUY NHẤT cho 1 DÒNG bảng kê (per-line).
 * - base  = nền dòng = cước+dầu+sà lan (chưa VAT). Ưu tiên override baseOv (nếu sửa tay).
 * - vat   = round(base × vatRate/100). Chi hộ KHÔNG chịu VAT.
 * - choho = chi hộ dòng.
 *
 * @param line     dòng có .detail (cuoc/dau/chiHo/bargeCuoc/bargeDau) hoặc .phaiThu (fallback)
 * @param vatRate  % VAT (0/8/10)
 * @param baseOv   (tùy chọn) override nền dòng do người dùng sửa tay
 * @returns {base, vat, choho, total}
 */
function lineAmounts(line, vatRate, baseOv) {
  const d = (line && typeof line.detail === "object" && line.detail) ? line.detail : null;
  let base, choho = 0;
  if (baseOv != null) {
    base = +baseOv || 0;
    if (d) choho = +d.chiHo || 0;
  } else if (d && ("cuoc" in d || "dau" in d || "bargeCuoc" in d || "bargeDau" in d)) {
    base = (+d.cuoc || 0) + (+d.dau || 0) + (+d.bargeCuoc || 0) + (+d.bargeDau || 0);
    choho = +d.chiHo || 0;
  } else {
    base = +(line && line.phaiThu) || 0;   // không có detail → coi phaiThu là nền
  }
  base = Math.round(base); choho = Math.round(choho);
  // VAT theo DÒNG: ưu tiên override detail.vat (sửa riêng từng dòng), else % mặc định bảng kê.
  const rate = (d && d.vat != null && d.vat !== "") ? (+d.vat || 0) : (+vatRate || 0);
  const vat = Math.round(base * rate / 100);
  return { base, vat, choho, total: base + vat + choho, rate };
}

/**
 * NGUỒN CHÂN LÝ DUY NHẤT (frontend) cho 4 con số bảng kê — khớp backend statementAmounts().
 * VAT chỉ áp lên NỀN vận chuyển (cước+dầu+sà lan); chi hộ KHÔNG chịu VAT.
 * = Σ per-line (lineAmounts) để luôn khớp 3 cột từng dòng.
 *
 * @param lines    mảng dòng, mỗi dòng có .detail (cuoc/dau/chiHo/bargeCuoc/bargeDau) hoặc .phaiThu
 * @param vatRate  % VAT (0/8/10)
 * @param baseOvOf (tùy chọn) fn(line, idx) → override nền dòng (null = không override)
 * @returns {base, choho, vat, total}
 */
function statementAmounts(lines, vatRate, baseOvOf) {
  let base = 0, choho = 0, vat = 0;
  (lines || []).forEach((l, i) => {
    const ov = baseOvOf ? baseOvOf(l, i) : null;
    const a = lineAmounts(l, vatRate, ov);
    base += a.base; choho += a.choho; vat += a.vat;
  });
  return { base, choho, vat, total: base + vat + choho };
}

/* ============================ icons ============================ */
const I = {
  truck: (p) => (<svg viewBox="0 0 24 24" width="20" height="20" fill="none" {...p}><path d="M2.5 6.5A1.5 1.5 0 014 5h9.5v9.5H2.5v-8zM13.5 8.5H18l3 3v3h-7.5v-6z" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round"/><circle cx="6.5" cy="17" r="1.8" stroke="currentColor" strokeWidth="1.6"/><circle cx="17.5" cy="17" r="1.8" stroke="currentColor" strokeWidth="1.6"/></svg>),
  x: (p) => (<svg viewBox="0 0 20 20" width="18" height="18" fill="none" {...p}><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"/></svg>),
  plus: (p) => (<svg viewBox="0 0 20 20" width="16" height="16" fill="none" {...p}><path d="M10 4v12M4 10h12" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"/></svg>),
  trash: (p) => (<svg viewBox="0 0 20 20" width="15" height="15" fill="none" {...p}><path d="M4 6h12M8 6V4.6c0-.6.4-1 1-1h2c.6 0 1 .4 1 1V6m1.5 0l-.5 9c0 .6-.5 1-1 1H7c-.6 0-1-.4-1-1l-.5-9" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  chev: (p) => (<svg viewBox="0 0 20 20" width="13" height="13" fill="none" {...p}><path d="M6 8l4 4 4-4" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  check: (p) => (<svg viewBox="0 0 20 20" width="14" height="14" fill="none" {...p}><path d="M4.5 10.5l3.2 3.2L15.5 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  arrow: (p) => (<svg viewBox="0 0 24 24" width="15" height="15" fill="none" {...p}><path d="M4 12h15m0 0l-5-5m5 5l-5 5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  fx: (p) => (<svg viewBox="0 0 20 20" width="14" height="14" fill="none" {...p}><path d="M4 16c2.2 0 2.5-2 3-5s.8-5 3-5m-5 5h6" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  search: (p) => (<svg viewBox="0 0 20 20" width="16" height="16" fill="none" {...p}><circle cx="9" cy="9" r="5.2" stroke="currentColor" strokeWidth="1.6"/><path d="M13 13l3.5 3.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"/></svg>),
  dot: (p) => (<svg viewBox="0 0 20 20" width="18" height="18" fill="none" {...p}><circle cx="5" cy="10" r="1.4" fill="currentColor"/><circle cx="10" cy="10" r="1.4" fill="currentColor"/><circle cx="15" cy="10" r="1.4" fill="currentColor"/></svg>),
  edit: (p) => (<svg viewBox="0 0 20 20" width="14" height="14" fill="none" {...p}><path d="M12.6 4.4l3 3M4 16l.7-3.2 8.1-8.1 2.5 2.5-8.1 8.1L4 16z" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  open: (p) => (<svg viewBox="0 0 20 20" width="13" height="13" fill="none" {...p}><path d="M7.5 4.5H15.5V12.5M15 5L5 15" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  cog: (p) => (<svg viewBox="0 0 22 22" width="18" height="18" fill="none" {...p}><circle cx="11" cy="11" r="2.8" stroke="currentColor" strokeWidth="1.6"/><path d="M11 2.5v2M11 17.5v2M2.5 11h2M17.5 11h2M5 5l1.4 1.4M15.6 15.6L17 17M17 5l-1.4 1.4M6.4 15.6L5 17" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"/></svg>),
  link: (p) => (<svg viewBox="0 0 20 20" width="15" height="15" fill="none" {...p}><path d="M8 12l4-4M7.5 6.5l1-1a3 3 0 014.2 4.2l-1 1M12.5 13.5l-1 1a3 3 0 01-4.2-4.2l1-1" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/></svg>),
};

/* ============================ small fields ============================ */
function Money({ value, onChange, placeholder = "0", bold, dim }) {
  return (
    <div style={{ position: "relative", width: "100%" }}>
      <input inputMode="numeric" value={groupVND(value)} onChange={(e) => onChange(onlyDigits(e.target.value))} placeholder={placeholder}
        className="tnum"
        style={{ width: "100%", padding: "8px 26px 8px 11px", fontSize: 13.5, textAlign: "right", fontWeight: bold ? 600 : 500,
          color: dim && !toNum(value) ? "var(--ink-4)" : "var(--ink)",
          background: "#fff", border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
        onFocus={(e) => { e.target.style.borderColor = "var(--accent)"; e.target.style.boxShadow = "0 0 0 3px var(--accent-weak)"; }}
        onBlur={(e) => { e.target.style.borderColor = "var(--line)"; e.target.style.boxShadow = "none"; }} />
      <span style={{ position: "absolute", right: 10, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 12.5, pointerEvents: "none" }}>₫</span>
    </div>
  );
}
function Payer({ value, onChange, placeholder = "Bên TT…" }) {
  return (
    <div style={{ position: "relative", width: "100%" }}>
      <select value={value || ""} onChange={(e) => onChange(e.target.value)}
        style={{ width: "100%", appearance: "none", WebkitAppearance: "none", padding: "8px 26px 8px 11px", fontSize: 13,
          color: value ? "var(--ink-2)" : "var(--ink-4)", background: "#fff", border: "1px solid var(--line)", borderRadius: 9, cursor: "pointer" }}>
        <option value="">{placeholder}</option>
        {PAYERS.map((p) => <option key={p} value={p}>{p}</option>)}
      </select>
      <span style={{ position: "absolute", right: 9, top: "50%", transform: "translateY(-50%)", color: "var(--ink-3)", pointerEvents: "none" }}><I.chev /></span>
    </div>
  );
}
function Txt({ value, onChange, placeholder, disabled = false }) {
  return (
    <input value={value || ""} disabled={disabled} onChange={(e) => onChange(e.target.value)} placeholder={placeholder}
      style={{ width: "100%", padding: "8px 11px", fontSize: 13.5, background: disabled ? "var(--line-2)" : "#fff", border: "1px solid var(--line)", borderRadius: 9, outline: "none", color: disabled ? "var(--ink-3)" : "inherit" }}
      onFocus={(e) => { if (disabled) return; e.target.style.borderColor = "var(--accent)"; e.target.style.boxShadow = "0 0 0 3px var(--accent-weak)"; }}
      onBlur={(e) => { e.target.style.borderColor = "var(--line)"; e.target.style.boxShadow = "none"; }} />
  );
}

/* Select2-style searchable combo bound to a config list. onCreate(v) adds to config. */
// Chuẩn hóa để tìm KHÔNG phụ thuộc dấu VÀ không lệch NFC/NFD: NFD tách dấu → bỏ dấu kết hợp (U+0300–U+036F)
// → đ→d → thường. Nhờ vậy "hà hưng hải" (gõ Unikey, có thể NFD) tìm ra "HÀ HƯNG HẢI" (DB lưu NFC) và
// "hai minh" (không dấu) cũng ra "HẢI MINH". KHÔNG strip thì indexOf hỏng do khác chuẩn hóa Unicode.
function stripDia(s) {
  return (s == null ? "" : String(s)).normalize("NFD").replace(/[̀-ͯ]/g, "").replace(/đ/g, "d").replace(/Đ/g, "D").toLowerCase();
}
// Xếp hạng độ khớp khi tìm: khớp tuyệt đối(0) < bắt đầu bằng(1) < đầu 1 từ(2) < chứa(3); -1 = không khớp.
function matchRank(label, ql) {
  const l = stripDia(label);
  const needle = stripDia(ql);
  if (!needle) return 4;
  const i = l.indexOf(needle);
  if (i < 0) return -1;
  if (l === needle) return 0;
  if (i === 0) return 1;
  const p = l[i - 1];
  if (p === " " || p === "-" || p === "(" || p === "/" || p === "·") return 2;
  return 3;
}

function Combo({ value, onChange, options = [], onCreate, placeholder = "Chọn…", small, clearable, strict }) {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState("");
  const [pos, setPos] = useState(null);   // vị trí dropdown (fixed) — thoát khỏi overflow của modal
  const wrapRef = useRef(null);
  const btnRef = useRef(null);
  const popRef = useRef(null);
  // Đặt dropdown theo viewport: tự lật LÊN khi dưới thiếu chỗ (trong modal cuộn)
  const place = () => {
    const el = btnRef.current; if (!el) return;
    const r = el.getBoundingClientRect();
    const below = window.innerHeight - r.bottom, above = r.top, want = 264;
    const up = below < Math.min(want, 220) && above > below;
    setPos({ left: r.left, width: r.width, top: r.top, bottom: r.bottom, up, maxH: Math.max(150, Math.min(want, (up ? above : below) - 14)) });
  };
  useEffect(() => {
    if (!open) { setPos(null); return; }
    place();
    const onDoc = (e) => { if (wrapRef.current && !wrapRef.current.contains(e.target) && popRef.current && !popRef.current.contains(e.target)) { setOpen(false); setQ(""); } };
    const onMove = () => place();
    document.addEventListener("mousedown", onDoc);
    window.addEventListener("scroll", onMove, true);
    window.addEventListener("resize", onMove);
    return () => { document.removeEventListener("mousedown", onDoc); window.removeEventListener("scroll", onMove, true); window.removeEventListener("resize", onMove); };
  }, [open]);

  const ql = q.trim().toLowerCase();
  // Cho phép option dạng chuỗi HOẶC {value,label} (nhãn ≠ giá trị — vd lái xe "Tên · SĐT")
  const opts = options.map((o) => (o && typeof o === "object") ? { value: String(o.value), label: o.label == null ? String(o.value) : String(o.label) } : { value: String(o), label: String(o) });
  // Lọc + XẾP HẠNG: khớp đúng/đầu chuỗi/đầu từ lên trước (tránh chìm trong danh sách dài).
  const filtered = !ql ? opts : opts
    .map((o, idx) => ({ o, r: matchRank(o.label, ql), idx }))
    .filter((x) => x.r >= 0)
    .sort((a, b) => a.r - b.r || a.o.label.length - b.o.label.length || a.idx - b.idx)
    .map((x) => x.o);
  const exact = opts.some((o) => o.label.toLowerCase() === ql);
  const curLabel = (opts.find((o) => o.value === value) || {}).label || value;
  const pick = (v) => { onChange(v); setOpen(false); setQ(""); };
  const create = () => { const v = q.trim(); if (!v) return; if (onCreate && !opts.some((o) => o.value === v)) onCreate(v); onChange(v); setOpen(false); setQ(""); };
  const showClear = clearable && !!value;
  const padRight = showClear ? 50 : 28;
  const pad = small ? `7px ${padRight}px 7px 10px` : `8px ${padRight}px 8px 11px`;

  return (
    <div ref={wrapRef} style={{ position: "relative", width: "100%" }}>
      <button type="button" ref={btnRef} onClick={() => setOpen((o) => !o)}
        style={{ width: "100%", textAlign: "left", padding: pad, fontSize: small ? 13 : 13.5, cursor: "pointer",
          background: "#fff", border: `1px solid ${open ? "var(--accent)" : "var(--line)"}`, borderRadius: 9, outline: "none",
          color: value ? "var(--ink)" : "var(--ink-4)", boxShadow: open ? "0 0 0 3px var(--accent-weak)" : "none",
          whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis", position: "relative" }}>
        {curLabel || placeholder}
        {showClear && (
          <span role="button" title="Xóa lựa chọn"
            onMouseDown={(e) => { e.preventDefault(); e.stopPropagation(); }}
            onClick={(e) => { e.preventDefault(); e.stopPropagation(); onChange(""); setOpen(false); setQ(""); }}
            style={{ position: "absolute", right: 30, top: "50%", transform: "translateY(-50%)", display: "inline-flex", color: "var(--ink-4)", cursor: "pointer", borderRadius: 6, padding: 1 }}
            onMouseEnter={(e) => { e.currentTarget.style.background = "var(--line-2)"; e.currentTarget.style.color = "var(--ink-2)"; }}
            onMouseLeave={(e) => { e.currentTarget.style.background = "transparent"; e.currentTarget.style.color = "var(--ink-4)"; }}><I.x /></span>
        )}
        <span style={{ position: "absolute", right: 9, top: "50%", transform: `translateY(-50%) rotate(${open ? 180 : 0}deg)`, color: "var(--ink-3)", transition: "transform .12s", pointerEvents: "none" }}><I.chev /></span>
      </button>
      {open && pos && createPortal(
        <div ref={popRef} style={{ position: "fixed", zIndex: 9999, left: pos.left, width: pos.width,
          ...(pos.up ? { bottom: Math.round(window.innerHeight - pos.top + 4) } : { top: Math.round(pos.bottom + 4) }),
          display: "flex", flexDirection: "column", background: "#fff",
          border: "1px solid var(--line)", borderRadius: 11, boxShadow: "0 12px 32px -8px rgba(16,19,23,.24), 0 2px 8px rgba(16,19,23,.08)", overflow: "hidden" }}>
          <div style={{ padding: 7, borderBottom: "1px solid var(--line-2)", position: "relative", flexShrink: 0 }}>
            <span style={{ position: "absolute", left: 16, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)" }}><I.search /></span>
            <input autoFocus value={q} onChange={(e) => setQ(e.target.value)} onCompositionEnd={(e) => setQ(e.currentTarget.value)} placeholder="Tìm hoặc thêm mới…"
              onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); if (filtered.length && !ql) return; if (filtered.length === 1) pick(filtered[0].value); else if (!strict && !exact && q.trim()) create(); } }}
              style={{ width: "100%", padding: "7px 10px 7px 30px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, outline: "none" }} />
          </div>
          <div style={{ maxHeight: pos.maxH, overflowY: "auto", padding: 4 }}>
            {filtered.map((o, oi) => {
              const sel = o.value === value;
              return (
                <button key={o.value + "::" + oi} type="button" onClick={() => pick(o.value)}
                  style={{ width: "100%", textAlign: "left", display: "flex", alignItems: "center", justifyContent: "space-between", gap: 8,
                    padding: "8px 10px", fontSize: 13.5, border: "none", borderRadius: 7, cursor: "pointer",
                    background: sel ? "var(--accent-weak)" : "transparent", color: sel ? "var(--accent)" : "var(--ink-2)", fontWeight: sel ? 600 : 400 }}
                  onMouseEnter={(e) => { if (!sel) e.currentTarget.style.background = "var(--line-2)"; }}
                  onMouseLeave={(e) => { if (!sel) e.currentTarget.style.background = "transparent"; }}>
                  {o.label}{sel && <span style={{ color: "var(--accent)" }}><I.check /></span>}
                </button>
              );
            })}
            {ql && !exact && !strict && (
              <button type="button" onClick={create}
                style={{ width: "100%", textAlign: "left", display: "flex", alignItems: "center", gap: 8, padding: "8px 10px", fontSize: 13.5, border: "none", borderRadius: 7, cursor: "pointer", background: "transparent", color: "var(--accent)", fontWeight: 600 }}
                onMouseEnter={(e) => (e.currentTarget.style.background = "var(--accent-weak-2)")}
                onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}>
                <span style={{ width: 17, height: 17, borderRadius: 5, background: "var(--accent-weak)", display: "grid", placeItems: "center" }}><I.plus /></span>
                Thêm “{q.trim()}”
              </button>
            )}
            {ql && !exact && strict && !filtered.length && (
              <div style={{ padding: "8px 10px", fontSize: 12.5, color: "var(--ink-4)" }}>Không có khoản phù hợp — thêm ở Cài đặt.</div>
            )}
            {!filtered.length && !q && <div style={{ padding: "12px 10px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có dữ liệu — gõ để thêm mới.</div>}
          </div>
        </div>,
        document.body
      )}
    </div>
  );
}
/* Multi-select (chips) — chọn nhiều giá trị từ danh mục, giới hạn max. */
// groups (tùy chọn): [{label, items:[...]}] → gợi ý gom nhóm + gắn nhãn loại (vd Cảng/Kho).
// Giá trị lưu vẫn là chuỗi thuần (không kèm loại) để khớp tuyến theo TẬP không phụ thuộc loại.
// allowDup: cho chọn LẶP 1 mục (vd tuyến quay lại cùng cảng ICDQV→QV→ICDQV) — khớp vẫn theo tập.
function MultiCombo({ values = [], onChange, options = [], groups = null, onCreate, max = 3, placeholder = "Chọn…", strict, allowDup = false }) {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState("");
  const wrapRef = useRef(null);
  const searchRef = useRef(null);
  useEffect(() => {
    if (!open) return;
    const onDoc = (e) => { if (wrapRef.current && !wrapRef.current.contains(e.target)) { setOpen(false); setQ(""); } };
    document.addEventListener("mousedown", onDoc);
    return () => document.removeEventListener("mousedown", onDoc);
  }, [open]);
  const sel = Array.isArray(values) ? values : (values ? [values] : []);
  const selRef = useRef(sel);
  selRef.current = sel;   // luôn giữ danh sách MỚI NHẤT để chống stale-closure khi re-render trễ
  const full = sel.length >= max;
  const ql = q.trim().toLowerCase();
  // Chống trùng KHÔNG phân biệt hoa/thường + dấu cách: "ICD QV" == "ICDQV" == "icdqv".
  const norm = (v) => (v || "").toString().replace(/\s+/g, "").toLowerCase();
  const has = (arr, v) => arr.some((x) => norm(x) === norm(v));
  // Gom nhóm → danh sách phẳng (cho dedup/tạo mới) + map giá trị→nhãn loại (hiện trên chip & gợi ý).
  const flatOpts = groups ? groups.flatMap((g) => g.items || []) : options;
  const groupOf = (v) => { if (!groups) return ""; const g = groups.find((g) => (g.items || []).some((x) => norm(x) === norm(v))); return g ? g.label : ""; };
  // Lọc + XẾP HẠNG kết quả tìm (khớp đúng/đầu chuỗi/đầu từ lên trước).
  const rankSort = (arr) => !ql ? arr : arr
    .map((o, idx) => ({ o, r: matchRank(o, ql), idx }))
    .filter((x) => x.r >= 0)
    .sort((a, b) => a.r - b.r || String(a.o).length - String(b.o).length || a.idx - b.idx)
    .map((x) => x.o);
  // allowDup → KHÔNG loại mục đã chọn khỏi gợi ý (cho chọn lại).
  const avail = rankSort(flatOpts.filter((o) => allowDup || !has(sel, o)));
  const exact = has(flatOpts, q) || (!allowDup && has(sel, q));
  // Chọn xong GIỮ mở + focus lại ô tìm để chọn tiếp nhiều mục (không bị mất các mục đã chọn)
  const refocus = () => { setTimeout(() => { try { searchRef.current && searchRef.current.focus(); } catch (e) {} }, 0); };
  const addVal = (v) => { const cur = selRef.current; if (!v || (!allowDup && has(cur, v)) || cur.length >= max) return; onChange([...cur, v]); setQ(""); refocus(); };
  const removeAt = (idx) => onChange(selRef.current.filter((_, j) => j !== idx));
  const create = () => { const v = q.trim(); if (!v || sel.length >= max) return; if (onCreate && !flatOpts.includes(v)) onCreate(v); addVal(v); };
  return (
    <div ref={wrapRef} style={{ position: "relative", width: "100%" }}>
      <div onClick={() => { if (!full) setOpen(true); }}
        style={{ display: "flex", flexWrap: "wrap", gap: 5, alignItems: "center", minHeight: 38, padding: "5px 8px",
          border: `1px solid ${open ? "var(--accent)" : "var(--line)"}`, borderRadius: 9, background: "#fff", cursor: full ? "default" : "pointer",
          boxShadow: open ? "0 0 0 3px var(--accent-weak)" : "none" }}>
        {sel.map((v, idx) => (
          <span key={idx} style={{ display: "inline-flex", alignItems: "center", gap: 4, background: "var(--accent-weak)", color: "var(--accent)", fontSize: 12.5, fontWeight: 600, padding: "3px 4px 3px 9px", borderRadius: 7 }}>
            {groups && groupOf(v) ? <span style={{ fontSize: 9.5, fontWeight: 700, textTransform: "uppercase", letterSpacing: ".03em", opacity: .7 }}>{groupOf(v)}</span> : null}
            {v}
            <button type="button" onClick={(e) => { e.stopPropagation(); removeAt(idx); }} title="Bỏ"
              style={{ border: "none", background: "transparent", color: "var(--accent)", cursor: "pointer", display: "grid", placeItems: "center", padding: 0, width: 16, height: 16 }}><I.x /></button>
          </span>
        ))}
        {!full
          ? <span style={{ fontSize: 13, color: "var(--ink-4)" }}>{sel.length ? "Thêm…" : placeholder}</span>
          : <span style={{ fontSize: 11.5, color: "var(--ink-4)" }}>Đã đủ tối đa {max}</span>}
      </div>
      {open && !full && (
        <div style={{ position: "absolute", zIndex: 80, top: "calc(100% + 4px)", left: 0, right: 0, background: "#fff", border: "1px solid var(--line)", borderRadius: 11, boxShadow: "0 12px 32px -8px rgba(16,19,23,.24), 0 2px 8px rgba(16,19,23,.08)", overflow: "hidden" }}>
          <div style={{ padding: 7, borderBottom: "1px solid var(--line-2)", position: "relative" }}>
            <span style={{ position: "absolute", left: 16, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)" }}><I.search /></span>
            <input ref={searchRef} autoFocus value={q} onChange={(e) => setQ(e.target.value)} onCompositionEnd={(e) => setQ(e.currentTarget.value)} placeholder={strict ? "Tìm trong danh mục…" : "Tìm hoặc thêm mới…"}
              onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); if (avail.length === 1) addVal(avail[0]); else if (!strict && !exact && q.trim()) create(); } }}
              style={{ width: "100%", padding: "7px 10px 7px 30px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, outline: "none" }} />
          </div>
          <div style={{ maxHeight: 196, overflowY: "auto", padding: 4 }}>
            {!groups && avail.map((o) => (
              <button key={o} type="button" onClick={() => addVal(o)}
                style={{ width: "100%", textAlign: "left", padding: "8px 10px", fontSize: 13.5, border: "none", borderRadius: 7, cursor: "pointer", background: "transparent", color: "var(--ink-2)" }}
                onMouseEnter={(e) => (e.currentTarget.style.background = "var(--line-2)")} onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}>{o}</button>
            ))}
            {groups && groups.map((g) => {
              const gi = rankSort((g.items || []).filter((o) => allowDup || !has(sel, o)));
              if (!gi.length) return null;
              return (
                <div key={g.label}>
                  <div style={{ padding: "6px 10px 3px", fontSize: 10.5, fontWeight: 700, textTransform: "uppercase", letterSpacing: ".04em", color: "var(--ink-4)" }}>{g.label}</div>
                  {gi.map((o) => (
                    <button key={o} type="button" onClick={() => addVal(o)}
                      style={{ width: "100%", textAlign: "left", padding: "8px 10px", fontSize: 13.5, border: "none", borderRadius: 7, cursor: "pointer", background: "transparent", color: "var(--ink-2)" }}
                      onMouseEnter={(e) => (e.currentTarget.style.background = "var(--line-2)")} onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}>{o}</button>
                  ))}
                </div>
              );
            })}
            {ql && !exact && !strict && (
              <button type="button" onClick={create}
                style={{ width: "100%", textAlign: "left", display: "flex", alignItems: "center", gap: 8, padding: "8px 10px", fontSize: 13.5, border: "none", borderRadius: 7, cursor: "pointer", background: "transparent", color: "var(--accent)", fontWeight: 600 }}
                onMouseEnter={(e) => (e.currentTarget.style.background = "var(--accent-weak-2)")} onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}>
                <span style={{ width: 17, height: 17, borderRadius: 5, background: "var(--accent-weak)", display: "grid", placeItems: "center" }}><I.plus /></span>Thêm “{q.trim()}”
              </button>
            )}
            {strict && ql && !avail.length && <div style={{ padding: "12px 10px", fontSize: 12.5, color: "var(--ink-4)" }}>Không có mục khớp. Thêm kho mới ở <b>Cài đặt → Kho</b>.</div>}
            {!avail.length && !ql && <div style={{ padding: "12px 10px", fontSize: 12.5, color: "var(--ink-4)" }}>{strict ? "Hết mục để chọn." : "Hết mục để chọn — gõ để thêm mới."}</div>}
          </div>
        </div>
      )}
    </div>
  );
}
/* Ngày — dùng Flatpickr (đã nạp sẵn ở layout, locale VN) để dễ thao tác hơn input date mặc định.
   Lưu giá trị ISO Y-m-d (không đổi format cũ), hiển thị d/m/Y. disableMobile để dùng cùng UI trên điện thoại. */
function DateField({ value, onChange }) {
  const hasFp = typeof window !== "undefined" && !!window.flatpickr;
  const ref = useRef(null);
  const fp = useRef(null);
  const cb = useRef(onChange); cb.current = onChange;
  useEffect(() => {
    if (!hasFp || !ref.current) return;
    const inst = window.flatpickr(ref.current, {
      dateFormat: "Y-m-d", altInput: true, altFormat: "d/m/Y", altInputClass: "trk-fp",
      allowInput: false, disableMobile: true,
      onChange: (_, str) => cb.current(str),
    });
    fp.current = inst;
    if (value) inst.setDate(value, false);
    return () => { try { inst.destroy(); } catch (e) {} };
  }, []);
  useEffect(() => {
    if (fp.current && (value || "") !== (fp.current.input.value || "")) fp.current.setDate(value || "", false);
  }, [value]);
  // Fallback: nơi không nạp Flatpickr → input date gốc (vẫn hoạt động).
  if (!hasFp) return <input type="date" className="trk-fp" value={value || ""} onChange={(e) => onChange(e.target.value)} style={{ colorScheme: "light" }} />;
  return (
    <div style={{ position: "relative", width: "100%" }}>
      <input ref={ref} type="text" className="trk-fp" placeholder="dd/mm/yyyy" />
      {value ? (
        <span role="button" title="Xoá ngày"
          onMouseDown={(e) => { e.preventDefault(); e.stopPropagation(); }}
          onClick={(e) => { e.stopPropagation(); if (fp.current) fp.current.clear(); else onChange(""); }}
          style={{ position: "absolute", right: 8, top: "50%", transform: "translateY(-50%)", display: "inline-flex", color: "var(--ink-4)", cursor: "pointer", zIndex: 2 }}><I.x /></span>
      ) : null}
    </div>
  );
}
function Num({ value, onChange, suffix, placeholder = "0" }) {
  // Chừa lề phải theo ĐỘ DÀI hậu tố để số không bị đè lên (vd "tháng", "ngày" dài hơn "km"/"₫")
  const padRight = suffix ? Math.max(30, String(suffix).length * 7.5 + 18) : 11;
  return (
    <div style={{ position: "relative", width: "100%" }}>
      <input inputMode="numeric" value={value || ""} onChange={(e) => onChange(onlyDigits(e.target.value))} placeholder={placeholder} className="tnum"
        style={{ width: "100%", padding: `8px ${padRight}px 8px 11px`, fontSize: 13.5, textAlign: "right", background: "#fff", border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
        onFocus={(e) => { e.target.style.borderColor = "var(--accent)"; e.target.style.boxShadow = "0 0 0 3px var(--accent-weak)"; }}
        onBlur={(e) => { e.target.style.borderColor = "var(--line)"; e.target.style.boxShadow = "none"; }} />
      {suffix && <span style={{ position: "absolute", right: 10, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 12, pointerEvents: "none" }}>{suffix}</span>}
    </div>
  );
}

/* ============================ cost line row ============================ */
/* label | amount | payer(optional)  — the repeated "phân bổ" unit */
function Line({ label, hint, amt, onAmt, payer, onPayer, hasPayer = true, removable, onRemove, editableLabel, onLabel, labelOptions, onCreateLabel, payerOptions, onCreatePayer }) {
  const [hover, setHover] = useState(false);
  return (
    <div onMouseEnter={() => setHover(true)} onMouseLeave={() => setHover(false)}
      style={{ display: "grid", gridTemplateColumns: hasPayer ? "1fr 168px 150px 30px" : "1fr 168px 150px 30px", gap: 10, alignItems: "center", padding: "7px 0" }}>
      <div style={{ minWidth: 0 }}>
        {editableLabel
          ? <Combo value={label} onChange={onLabel} options={labelOptions || []} onCreate={onCreateLabel} placeholder="Chọn khoản chi…" small />
          : <div style={{ fontSize: 13.5, color: "var(--ink-2)", fontWeight: 500 }}>{label}{hint && <span style={{ color: "var(--ink-4)", fontWeight: 400, marginLeft: 6, fontSize: 12 }}>{hint}</span>}</div>}
      </div>
      <Money value={amt} onChange={onAmt} dim />
      {hasPayer ? <Combo value={payer} onChange={onPayer} options={payerOptions || []} onCreate={onCreatePayer} placeholder="Bên TT…" small /> : <div />}
      <div style={{ display: "flex", justifyContent: "center" }}>
        {removable && (
          <button type="button" onClick={onRemove} title="Xóa khoản"
            style={{ width: 28, height: 28, display: "grid", placeItems: "center", border: "none", borderRadius: 7, background: "transparent", color: "var(--ink-4)", cursor: "pointer", opacity: hover ? 1 : 0.35 }}
            onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
            onMouseLeave={(e) => { e.currentTarget.style.background = "transparent"; e.currentTarget.style.color = "var(--ink-4)"; }}>
            <I.trash />
          </button>
        )}
      </div>
    </div>
  );
}

function Section({ title, total, totalLabel, children, headPayer }) {
  return (
    <div style={{ marginBottom: 6 }}>
      <div style={{ display: "flex", alignItems: "baseline", justifyContent: "space-between", gap: 16, padding: "14px 0 2px" }}>
        <div style={{ fontSize: 11.5, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.05em", whiteSpace: "nowrap" }}>{title}</div>
        {total != null && (
          <div style={{ fontSize: 12.5, color: "var(--ink-3)", whiteSpace: "nowrap" }}>{totalLabel || "Cộng"}: <span className="tnum" style={{ color: "var(--ink)", fontWeight: 700 }}>{fmtVND(total)}</span></div>
        )}
      </div>
      <div style={{ borderTop: "1px solid var(--line-2)" }}>{children}</div>
    </div>
  );
}

/* ============================ Modal shell ============================ */
function Modal({ title, subtitle, onClose, children, footer, width = 860, tabs, tab, onTab, icon }) {
  const isMobile = useIsMobile();
  useEffect(() => {
    const onKey = (e) => { if (e.key === "Escape") onClose(); };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);
  const padX = isMobile ? 14 : 22;
  // iOS: dùng svh (small viewport height) để modal luôn nằm trọn trong vùng nhìn thấy
  // dù thanh công cụ Safari hiện/ẩn — tránh header bị đẩy lên trên mép màn hình.
  return (
    <div onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}
      style={{ position: "fixed", inset: 0, zIndex: 1100, background: "rgba(16,19,23,.34)", backdropFilter: "blur(2px)", display: "grid", placeItems: "center", padding: isMobile ? 10 : 24 }}>
      <div role="dialog" aria-modal="true"
        style={{ width: `min(${width}px,100%)`, maxHeight: isMobile ? "92svh" : "90vh", display: "flex", flexDirection: "column", background: "var(--panel)", borderRadius: "var(--radius)", boxShadow: "var(--shadow-modal)", overflow: "hidden" }}>
        <div style={{ display: "flex", alignItems: "flex-start", gap: 14, padding: `18px ${padX}px 0` }}>
          <div style={{ width: 38, height: 38, borderRadius: 10, flexShrink: 0, background: "var(--accent-weak)", color: "var(--accent)", display: "grid", placeItems: "center" }}>{icon || <I.truck />}</div>
          <div style={{ flex: 1, minWidth: 0 }}>
            <h2 style={{ margin: 0, fontSize: 17, fontWeight: 600, letterSpacing: "-0.01em" }}>{title}</h2>
            {subtitle && <p style={{ margin: "3px 0 0", fontSize: 13, color: "var(--ink-3)" }}>{subtitle}</p>}
          </div>
          <button type="button" onClick={onClose} title="Đóng (Esc)"
            style={{ width: 32, height: 32, display: "grid", placeItems: "center", flexShrink: 0, border: "none", borderRadius: 8, background: "transparent", color: "var(--ink-3)", cursor: "pointer" }}
            onMouseEnter={(e) => { e.currentTarget.style.background = "var(--line-2)"; e.currentTarget.style.color = "var(--ink)"; }}
            onMouseLeave={(e) => { e.currentTarget.style.background = "transparent"; e.currentTarget.style.color = "var(--ink-3)"; }}>
            <I.x />
          </button>
        </div>

        {tabs && (
          <div style={{ display: "flex", gap: 4, padding: `14px ${padX}px 0`, marginTop: 4, overflowX: "auto", whiteSpace: "nowrap" }}>
            {tabs.map((t) => (
              <button key={t.id} type="button" onClick={() => onTab(t.id)}
                style={{ border: "none", background: "transparent", cursor: "pointer", padding: "8px 14px 12px", fontSize: 13.5, fontWeight: 600,
                  color: tab === t.id ? "var(--accent)" : "var(--ink-3)", borderBottom: `2px solid ${tab === t.id ? "var(--accent)" : "transparent"}`, marginBottom: -1 }}>
                {t.label}{t.badge != null && <span className="tnum" style={{ marginLeft: 7, fontSize: 12, fontWeight: 600, color: tab === t.id ? "var(--accent)" : "var(--ink-4)" }}>{t.badge}</span>}
              </button>
            ))}
          </div>
        )}
        <div style={{ borderBottom: "1px solid var(--line)" }} />

        <div style={{ overflowY: "auto", flex: 1, padding: `4px ${padX}px 18px` }}>{children}</div>

        {footer && <div style={{ borderTop: "1px solid var(--line)", padding: `14px ${padX}px 16px`, background: "#fff" }}>{footer}</div>}
      </div>
    </div>
  );
}

function Btn({ children, onClick, variant = "ghost", disabled = false, busy = false }) {
  const [auto, setAuto] = useState(false);   // tự bận khi onClick trả Promise → chống bấm double
  const loading = busy || auto;
  const off = disabled || loading;
  const handle = (e) => {
    if (off || !onClick) return;
    const r = onClick(e);
    if (r && typeof r.then === "function") { setAuto(true); Promise.resolve(r).finally(() => setAuto(false)); }
  };
  const base = { padding: "10px 20px", fontSize: 14, fontWeight: variant === "primary" ? 600 : 500, cursor: off ? "not-allowed" : "pointer", borderRadius: 10, transition: "background .12s", opacity: off ? 0.55 : 1, display: "inline-flex", alignItems: "center", gap: 7 };
  const styles = variant === "primary"
    ? { ...base, background: "var(--accent)", color: "#fff", border: "1px solid var(--accent)", boxShadow: off ? "none" : "0 1px 2px rgba(42,111,219,.4)" }
    : { ...base, background: "#fff", color: "var(--ink-2)", border: "1px solid var(--line)" };
  return (
    <button type="button" onClick={handle} style={styles} disabled={off}
      onMouseEnter={(e) => { if (!off) e.currentTarget.style.background = variant === "primary" ? "#235dc0" : "var(--line-2)"; }}
      onMouseLeave={(e) => { if (!off) e.currentTarget.style.background = variant === "primary" ? "var(--accent)" : "#fff"; }}>
      {loading && <span style={{ width: 13, height: 13, border: "2px solid currentColor", borderTopColor: "transparent", borderRadius: "50%", display: "inline-block", animation: "trk-spin .7s linear infinite", opacity: .8 }} />}
      {children}
    </button>
  );
}

/* ============================ compute ============================ */
function calcCost(c) {
  const v = c || {};
  const g = (x) => toNum(x);
  // Chi phí NET = số tiền (gồm VAT) ÷ (1 + vat/100). VAT% theo từng dòng (mặc định 0).
  const net = (e) => { const a = g(e.amount); const vr = g(e.vat); return vr > 0 ? Math.round(a / (1 + vr / 100)) : a; };
  const items = v.items || [];
  const tongChiPhi = items.reduce((s, e) => s + net(e), 0);
  const thuChiHo = items.reduce((s, e) => s + (e.billable ? net(e) : 0), 0);
  return { thuChiHo, tongChiPhi, congTy: tongChiPhi - thuChiHo };
}
function calcVeh(v) {
  v = v || {};
  const g = (x) => toNum(x);
  const fuel = g(v.lit) * g(v.donGiaDau);
  const items = (v.items || []).reduce((s, e) => s + g(e.amount), 0);
  return items + fuel;
}
function calcRev(r) {
  r = r || {};
  const g = (x) => toNum(x);
  const tongDT = (r.doanhThu || []).reduce((s, e) => s + g(e.amount), 0);
  const rate = r.vatRate == null ? 8 : toNum(r.vatRate);
  const vat = Math.round(tongDT * rate / 100);
  const chiHo = (r.choHo || []).reduce((s, e) => s + g(e.amount), 0);
  const phaiThu = tongDT + vat + chiHo;
  const daTT = (r.payments || []).reduce((s, p) => s + g(p.amount), 0);
  const conNo = phaiThu - daTT;
  return { tongDT, vat, rate, phaiThu, conNo, daTT };
}

/* ICD (Quế Võ) — cấu trúc rút gọn */
function calcVehICD(v) {
  v = v || {};
  const g = (x) => toNum(x);
  return g(v.phuCapTienDuong) + g(v.troCap) + g(v.luong) + g(v.chiPhiKhac) + g(v.lit) * g(v.donGia);
}
function calcRevICD(r) {
  r = r || {};
  const g = (x) => toNum(x);
  const tongDT = (r.doanhThu || []).reduce((s, e) => s + g(e.amount), 0);
  const rate = r.vatRate == null ? 0 : toNum(r.vatRate);
  const vat = Math.round(tongDT * rate / 100);
  const chiHo = (r.choHo || []).reduce((s, e) => s + g(e.amount), 0);
  const phaiThu = tongDT + vat + chiHo;
  const daTT = (r.payments || []).reduce((s, p) => s + g(p.amount), 0);
  const conNo = phaiThu - daTT;
  return { tongDT, vat, rate, phaiThu, conNo, daTT };
}

/* Free time & kết nối — quy tắc kế toán ICD.
 * Ngưỡng (giờ): mặc định = thresholdH; nếu có `rules` (quy tắc theo khoảng ngày) và NGÀY CONT RA
 * (gio_xe_ra) rơi vào 1 khoảng → dùng ngưỡng của khoảng đó. rules = [{from:"YYYY-MM-DD", to?, hours}]. */
function freeTimeThresholdFor(dRa, thresholdH, rules) {
  const def = thresholdH == null || thresholdH === "" ? 4 : (parseFloat(thresholdH) || 0);
  if (!Array.isArray(rules) || !rules.length || !dRa || isNaN(dRa.getTime())) return def;
  const p = (n) => String(n).padStart(2, "0");
  const ymd = `${dRa.getFullYear()}-${p(dRa.getMonth() + 1)}-${p(dRa.getDate())}`;
  for (const r of rules) {
    if (r && r.from && ymd >= r.from && (!r.to || ymd <= r.to)) return (r.hours == null || r.hours === "") ? def : (parseFloat(r.hours) || def);
  }
  return def;
}
// Giờ XE RA hiệu lực cho Free time theo ra_mode (follow theo XE đó ra):
//  self  → Giờ xe ra (của cont)           gioXeRa
//  none  → Giờ xe ra (của XE — đầu kéo)    gioXeRaXe
//  other → giờ ra của cont KHÁC thực sự ra (mục "Giờ ra & biển số"): raOtherGioXeRa (popup, live) | gioXeRaEff (list, từ backend)
function freeTimeRaOf(s) {
  if (!s) return "";
  const mode = s.raMode || "self";
  if (mode === "none")  return s.gioXeRaXe || "";
  if (mode === "other") return s.raOtherGioXeRa || s.gioXeRaEff || "";
  return s.gioXeRa || "";
}
function calcFreeTime(s, thresholdH, rules) {
  const den = s && s.gioXeDen;
  const ra = freeTimeRaOf(s);
  if (!ra || !den) return null;
  const dRa = new Date(ra), dDen = new Date(den);
  if (isNaN(dRa.getTime()) || isNaN(dDen.getTime())) return null;
  const hours = (dRa - dDen) / 3600000;            // Free time = Giờ xe ra − Giờ xe đến
  const th = freeTimeThresholdFor(dRa, thresholdH, rules);   // ngưỡng theo NGÀY xe ra
  return { hours, connect: hours > th, threshold: th, basis: "Giờ xe đến" };
}
const fmtHours = (h) => {
  if (h == null) return "—";
  const neg = h < 0; h = Math.abs(h);
  const hh = Math.floor(h), mm = Math.round((h - hh) * 60);
  return (neg ? "-" : "") + (mm ? `${hh}h${String(mm).padStart(2, "0")}` : `${hh}h`);
};

export { useState, useRef, useMemo, useEffect, useCallback, useIsMobile, onlyDigits, groupVND, toNum, fmtVND, fmtNum, fmtShort, fmtDate, PAYERS, VAT_RATE, STATEMENT_VAT_RATES, statementAmounts, lineAmounts, I, Money, Payer, Txt, Combo, MultiCombo, DateField, Num, Line, Section, Modal, Btn, calcCost, calcVeh, calcRev, calcVehICD, calcRevICD, calcFreeTime, fmtHours };
