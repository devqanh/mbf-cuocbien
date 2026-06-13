import React from "react";
const { useState, useRef, useMemo, useEffect, useCallback } = React;

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
function Txt({ value, onChange, placeholder }) {
  return (
    <input value={value || ""} onChange={(e) => onChange(e.target.value)} placeholder={placeholder}
      style={{ width: "100%", padding: "8px 11px", fontSize: 13.5, background: "#fff", border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
      onFocus={(e) => { e.target.style.borderColor = "var(--accent)"; e.target.style.boxShadow = "0 0 0 3px var(--accent-weak)"; }}
      onBlur={(e) => { e.target.style.borderColor = "var(--line)"; e.target.style.boxShadow = "none"; }} />
  );
}

/* Select2-style searchable combo bound to a config list. onCreate(v) adds to config. */
function Combo({ value, onChange, options = [], onCreate, placeholder = "Chọn…", small, clearable, strict }) {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState("");
  const wrapRef = useRef(null);
  useEffect(() => {
    if (!open) return;
    const onDoc = (e) => { if (wrapRef.current && !wrapRef.current.contains(e.target)) { setOpen(false); setQ(""); } };
    document.addEventListener("mousedown", onDoc);
    return () => document.removeEventListener("mousedown", onDoc);
  }, [open]);

  const ql = q.trim().toLowerCase();
  // Cho phép option dạng chuỗi HOẶC {value,label} (nhãn ≠ giá trị — vd lái xe "Tên · SĐT")
  const opts = options.map((o) => (o && typeof o === "object") ? { value: String(o.value), label: o.label == null ? String(o.value) : String(o.label) } : { value: String(o), label: String(o) });
  const filtered = opts.filter((o) => !ql || o.label.toLowerCase().includes(ql));
  const exact = opts.some((o) => o.label.toLowerCase() === ql);
  const curLabel = (opts.find((o) => o.value === value) || {}).label || value;
  const pick = (v) => { onChange(v); setOpen(false); setQ(""); };
  const create = () => { const v = q.trim(); if (!v) return; if (onCreate && !opts.some((o) => o.value === v)) onCreate(v); onChange(v); setOpen(false); setQ(""); };
  const showClear = clearable && !!value;
  const padRight = showClear ? 50 : 28;
  const pad = small ? `7px ${padRight}px 7px 10px` : `8px ${padRight}px 8px 11px`;

  return (
    <div ref={wrapRef} style={{ position: "relative", width: "100%" }}>
      <button type="button" onClick={() => setOpen((o) => !o)}
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
      {open && (
        <div style={{ position: "absolute", zIndex: 80, top: "calc(100% + 4px)", left: 0, right: 0, background: "#fff",
          border: "1px solid var(--line)", borderRadius: 11, boxShadow: "0 12px 32px -8px rgba(16,19,23,.24), 0 2px 8px rgba(16,19,23,.08)", overflow: "hidden" }}>
          <div style={{ padding: 7, borderBottom: "1px solid var(--line-2)", position: "relative" }}>
            <span style={{ position: "absolute", left: 16, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)" }}><I.search /></span>
            <input autoFocus value={q} onChange={(e) => setQ(e.target.value)} placeholder="Tìm hoặc thêm mới…"
              onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); if (filtered.length && !ql) return; if (filtered.length === 1) pick(filtered[0].value); else if (!strict && !exact && q.trim()) create(); } }}
              style={{ width: "100%", padding: "7px 10px 7px 30px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, outline: "none" }} />
          </div>
          <div style={{ maxHeight: 196, overflowY: "auto", padding: 4 }}>
            {filtered.map((o) => {
              const sel = o.value === value;
              return (
                <button key={o.value} type="button" onClick={() => pick(o.value)}
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
        </div>
      )}
    </div>
  );
}
/* Multi-select (chips) — chọn nhiều giá trị từ danh mục, giới hạn max. */
function MultiCombo({ values = [], onChange, options = [], onCreate, max = 3, placeholder = "Chọn…" }) {
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
  const avail = options.filter((o) => !sel.includes(o) && (!ql || o.toLowerCase().includes(ql)));
  const exact = options.some((o) => o.toLowerCase() === ql) || sel.some((o) => o.toLowerCase() === ql);
  // Chọn xong GIỮ mở + focus lại ô tìm để chọn tiếp nhiều mục (không bị mất các mục đã chọn)
  const refocus = () => { setTimeout(() => { try { searchRef.current && searchRef.current.focus(); } catch (e) {} }, 0); };
  const addVal = (v) => { const cur = selRef.current; if (!v || cur.includes(v) || cur.length >= max) return; onChange([...cur, v]); setQ(""); refocus(); };
  const removeVal = (v) => onChange(selRef.current.filter((x) => x !== v));
  const create = () => { const v = q.trim(); if (!v || sel.length >= max) return; if (onCreate && !options.includes(v)) onCreate(v); addVal(v); };
  return (
    <div ref={wrapRef} style={{ position: "relative", width: "100%" }}>
      <div onClick={() => { if (!full) setOpen(true); }}
        style={{ display: "flex", flexWrap: "wrap", gap: 5, alignItems: "center", minHeight: 38, padding: "5px 8px",
          border: `1px solid ${open ? "var(--accent)" : "var(--line)"}`, borderRadius: 9, background: "#fff", cursor: full ? "default" : "pointer",
          boxShadow: open ? "0 0 0 3px var(--accent-weak)" : "none" }}>
        {sel.map((v) => (
          <span key={v} style={{ display: "inline-flex", alignItems: "center", gap: 4, background: "var(--accent-weak)", color: "var(--accent)", fontSize: 12.5, fontWeight: 600, padding: "3px 4px 3px 9px", borderRadius: 7 }}>
            {v}
            <button type="button" onClick={(e) => { e.stopPropagation(); removeVal(v); }} title="Bỏ"
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
            <input ref={searchRef} autoFocus value={q} onChange={(e) => setQ(e.target.value)} placeholder="Tìm hoặc thêm mới…"
              onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); if (avail.length === 1) addVal(avail[0]); else if (!exact && q.trim()) create(); } }}
              style={{ width: "100%", padding: "7px 10px 7px 30px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, outline: "none" }} />
          </div>
          <div style={{ maxHeight: 196, overflowY: "auto", padding: 4 }}>
            {avail.map((o) => (
              <button key={o} type="button" onClick={() => addVal(o)}
                style={{ width: "100%", textAlign: "left", padding: "8px 10px", fontSize: 13.5, border: "none", borderRadius: 7, cursor: "pointer", background: "transparent", color: "var(--ink-2)" }}
                onMouseEnter={(e) => (e.currentTarget.style.background = "var(--line-2)")} onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}>{o}</button>
            ))}
            {ql && !exact && (
              <button type="button" onClick={create}
                style={{ width: "100%", textAlign: "left", display: "flex", alignItems: "center", gap: 8, padding: "8px 10px", fontSize: 13.5, border: "none", borderRadius: 7, cursor: "pointer", background: "transparent", color: "var(--accent)", fontWeight: 600 }}
                onMouseEnter={(e) => (e.currentTarget.style.background = "var(--accent-weak-2)")} onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}>
                <span style={{ width: 17, height: 17, borderRadius: 5, background: "var(--accent-weak)", display: "grid", placeItems: "center" }}><I.plus /></span>Thêm “{q.trim()}”
              </button>
            )}
            {!avail.length && !ql && <div style={{ padding: "12px 10px", fontSize: 12.5, color: "var(--ink-4)" }}>Hết mục để chọn — gõ để thêm mới.</div>}
          </div>
        </div>
      )}
    </div>
  );
}
function DateField({ value, onChange }) {
  return (
    <input type="date" value={value || ""} onChange={(e) => onChange(e.target.value)}
      style={{ width: "100%", padding: "7px 10px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: value ? "var(--ink-2)" : "var(--ink-4)", outline: "none", colorScheme: "light" }}
      onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
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
  useEffect(() => {
    const onKey = (e) => { if (e.key === "Escape") onClose(); };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);
  return (
    <div onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}
      style={{ position: "fixed", inset: 0, zIndex: 1100, background: "rgba(16,19,23,.34)", backdropFilter: "blur(2px)", display: "grid", placeItems: "center", padding: 24 }}>
      <div role="dialog" aria-modal="true"
        style={{ width: `min(${width}px,100%)`, maxHeight: "90vh", display: "flex", flexDirection: "column", background: "var(--panel)", borderRadius: "var(--radius)", boxShadow: "var(--shadow-modal)", overflow: "hidden" }}>
        <div style={{ display: "flex", alignItems: "flex-start", gap: 14, padding: "18px 22px 0" }}>
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
          <div style={{ display: "flex", gap: 4, padding: "14px 22px 0", marginTop: 4 }}>
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

        <div style={{ overflowY: "auto", flex: 1, padding: "4px 22px 18px" }}>{children}</div>

        {footer && <div style={{ borderTop: "1px solid var(--line)", padding: "14px 22px 16px", background: "#fff" }}>{footer}</div>}
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
  const items = v.items || [];
  const tongChiPhi = items.reduce((s, e) => s + g(e.amount), 0);
  const thuChiHo = items.reduce((s, e) => s + (e.billable ? g(e.amount) : 0), 0);
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

/* Free time & kết nối — quy tắc kế toán ICD (ngưỡng giờ cấu hình được) */
function calcFreeTime(s, thresholdH) {
  const den = s && s.gioXeDen, duKien = s && s.gioDenDuKien, ra = s && s.gioXeRa;
  if (!ra || (!den && !duKien)) return null;
  const dRa = new Date(ra);
  let start, basis;
  if (den && duKien) {
    const dDen = new Date(den), dDk = new Date(duKien);
    if (dDen > dDk) { start = dDen; basis = "Giờ xe đến"; } else { start = dDk; basis = "Giờ đến kế hoạch"; }
  } else { start = new Date(den || duKien); basis = den ? "Giờ xe đến" : "Giờ đến kế hoạch"; }
  if (isNaN(dRa.getTime()) || isNaN(start.getTime())) return null;
  const hours = (dRa - start) / 3600000;
  const th = thresholdH == null || thresholdH === "" ? 4 : (parseFloat(thresholdH) || 0);
  return { hours, connect: hours > th, threshold: th, basis };
}
const fmtHours = (h) => {
  if (h == null) return "—";
  const neg = h < 0; h = Math.abs(h);
  const hh = Math.floor(h), mm = Math.round((h - hh) * 60);
  return (neg ? "-" : "") + (mm ? `${hh}h${String(mm).padStart(2, "0")}` : `${hh}h`);
};

export { useState, useRef, useMemo, useEffect, useCallback, onlyDigits, groupVND, toNum, fmtVND, fmtNum, fmtShort, fmtDate, PAYERS, VAT_RATE, I, Money, Payer, Txt, Combo, MultiCombo, DateField, Num, Line, Section, Modal, Btn, calcCost, calcVeh, calcRev, calcVehICD, calcRevICD, calcFreeTime, fmtHours };
