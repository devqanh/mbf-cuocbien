import React from "react";
import ReactDOM from "react-dom";
const { useState, useRef, useMemo, useEffect } = React;
import { I, Money, Payer, Txt, Combo, MultiCombo, DateField, Num, Line, Section, Modal, Btn, fmtVND, fmtNum, fmtShort, calcCost, calcVeh, calcRev, calcVehICD, calcRevICD, calcFreeTime, fmtHours, toNum, useIsMobile } from "@trk/lib.jsx";

function DTField({ value, onChange }) {
  return (
    <input type="datetime-local" value={value || ""} onChange={(e) => onChange(e.target.value)}
      style={{ width: "100%", padding: "7px 10px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: value ? "var(--ink-2)" : "var(--ink-4)", outline: "none", colorScheme: "light" }}
      onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
  );
}

/* ===================== COST POPUP (centerpiece) ===================== */

function Field({ label, hint, req, children }) {
  // Dùng <div> (KHÔNG dùng <label>): <label> bọc dropdown tùy biến sẽ forward click sang
  // phần tử labelable đầu tiên (vd nút ✕ của chip) → bấm chọn lại bị xoá mục đã chọn.
  return (
    <div style={{ display: "block" }}>
      <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>{label}{req && <span style={{ color: "var(--danger)", marginLeft: 3, fontWeight: 700 }}>*</span>}{hint && <span style={{ color: "var(--ink-4)", fontWeight: 400, marginLeft: 5 }}>· {hint}</span>}</div>
      {children}
    </div>
  );
}

/* Editable VAT rate (%) — kế toán tự nhập, mặc định gợi ý */

function DriverSpendRows({ rows = [], onChange, drivers = [], onCreateDriver }) {
  const isMobile = useIsMobile();
  const set = (id, np) => onChange(rows.map((e) => (e.id === id ? { ...e, ...np } : e)));
  const add = () => onChange([...rows, { id: Date.now() + Math.random(), who: "", date: "", amount: "", note: "" }]);
  const del = (id) => onChange(rows.filter((e) => e.id !== id));
  const dsCols = "150px 150px 1fr 150px 30px";
  return (
    <div style={{ padding: "6px 0 0" }}>
      <div style={{ fontSize: 11.5, color: "var(--ink-4)", padding: "0 0 4px" }}>Theo dõi từng lần lái xe ứng/chi tiền mặt — ai chi, ngày nào, bao nhiêu.</div>
      <div style={{ overflowX: isMobile ? "auto" : "visible", WebkitOverflowScrolling: "touch" }}>
      <div style={{ minWidth: isMobile ? 640 : undefined }}>
      {rows.length > 0 && (
        <div style={{ display: "grid", gridTemplateColumns: dsCols, gap: 10, padding: "0 0 4px" }}>
          {["Người chi", "Ngày chi", "Nội dung", "Số tiền", ""].map((h, i) => (
            <div key={i} style={{ fontSize: 11, fontWeight: 600, color: "var(--ink-4)", textTransform: "uppercase", letterSpacing: "0.04em", textAlign: i === 3 ? "right" : "left" }}>{h}</div>
          ))}
        </div>
      )}
      {rows.map((e) => (
        <div key={e.id} style={{ display: "grid", gridTemplateColumns: dsCols, gap: 10, alignItems: "center", padding: "5px 0" }}>
          <Combo value={e.who} onChange={(x) => set(e.id, { who: x })} options={drivers} onCreate={onCreateDriver} placeholder="Lái xe…" small />
          <DateField value={e.date} onChange={(x) => set(e.id, { date: x })} />
          <Txt value={e.note} onChange={(x) => set(e.id, { note: x })} placeholder="Nội dung chi…" />
          <Money value={e.amount} onChange={(x) => set(e.id, { amount: x })} dim />
          <button type="button" onClick={() => del(e.id)} title="Xóa"
            style={{ width: 28, height: 28, display: "grid", placeItems: "center", border: "none", borderRadius: 7, background: "transparent", color: "var(--ink-4)", cursor: "pointer" }}
            onMouseEnter={(ev) => { ev.currentTarget.style.background = "#fce8e8"; ev.currentTarget.style.color = "var(--danger)"; }}
            onMouseLeave={(ev) => { ev.currentTarget.style.background = "transparent"; ev.currentTarget.style.color = "var(--ink-4)"; }}>
            <I.trash />
          </button>
        </div>
      ))}
      </div>
      </div>
      <button type="button" onClick={add}
        style={{ display: "inline-flex", alignItems: "center", gap: 7, margin: "6px 0 2px", padding: "6px 10px 6px 7px", background: "transparent", border: "none", cursor: "pointer", color: "var(--accent)", fontSize: 13, fontWeight: 600, borderRadius: 8 }}
        onMouseEnter={(e) => (e.currentTarget.style.background = "var(--accent-weak-2)")}
        onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}>
        <span style={{ width: 18, height: 18, borderRadius: 6, background: "var(--accent-weak)", display: "grid", placeItems: "center" }}><I.plus /></span>
        Thêm lần lái xe chi
      </button>
    </div>
  );
}

/* Editable VAT rate (%) — kế toán tự nhập, mặc định gợi ý */

function VatLine({ rate, vat, onRate }) {
  return (
    <div style={{ display: "flex", justifyContent: "flex-end", alignItems: "center", gap: 8, padding: "8px 0 6px", borderTop: "1px solid var(--line-2)", marginTop: 4 }}>
      <span style={{ fontSize: 12.5, color: "var(--ink-3)" }}>VAT</span>
      <div style={{ position: "relative", width: 72 }}>
        <input inputMode="decimal" value={rate} onChange={(e) => onRate(e.target.value.replace(/[^\d.]/g, ""))}
          className="tnum"
          style={{ width: "100%", padding: "6px 22px 6px 9px", fontSize: 13, textAlign: "right", border: "1px solid var(--line)", borderRadius: 8, outline: "none" }}
          onFocus={(e) => { e.target.style.borderColor = "var(--accent)"; e.target.style.boxShadow = "0 0 0 3px var(--accent-weak)"; }}
          onBlur={(e) => { e.target.style.borderColor = "var(--line)"; e.target.style.boxShadow = "none"; }} />
        <span style={{ position: "absolute", right: 9, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 12.5, pointerEvents: "none" }}>%</span>
      </div>
      <span style={{ fontSize: 12.5, color: "var(--ink-3)" }}>=</span>
      <span className="tnum" style={{ fontSize: 13.5, color: "var(--ink)", fontWeight: 700, minWidth: 90, textAlign: "right" }}>{fmtVND(vat)}</span>
    </div>
  );
}

/* Repeater of {item from config + amount} — dùng cho Doanh thu & Thu chi hộ */

function ItemRows({ rows = [], onChange, options = [], onCreate, note, addText = "Thêm khoản", placeholder = "Chọn khoản…", prices = {} }) {
  const set = (id, np) => onChange(rows.map((e) => (e.id === id ? { ...e, ...np } : e)));
  const pick = (e, item) => set(e.id, { item, amount: toNum(e.amount) ? e.amount : (prices[item] || "") });
  const add = () => onChange([...rows, { id: Date.now() + Math.random(), item: "", amount: "" }]);
  const del = (id) => onChange(rows.filter((e) => e.id !== id));
  return (
    <div style={{ padding: "6px 0 0" }}>
      {note && <div style={{ fontSize: 11.5, color: "var(--ink-4)", padding: "0 0 4px" }}>{note}</div>}
      {rows.map((e) => (
        <div key={e.id} style={{ display: "grid", gridTemplateColumns: "1fr 170px 30px", gap: 10, alignItems: "center", padding: "5px 0" }}>
          <Combo value={e.item} onChange={(x) => pick(e, x)} options={options} onCreate={onCreate} placeholder={placeholder} small />
          <Money value={e.amount} onChange={(x) => set(e.id, { amount: x })} dim />
          <button type="button" onClick={() => del(e.id)} title="Xóa"
            style={{ width: 28, height: 28, display: "grid", placeItems: "center", border: "none", borderRadius: 7, background: "transparent", color: "var(--ink-4)", cursor: "pointer" }}
            onMouseEnter={(ev) => { ev.currentTarget.style.background = "#fce8e8"; ev.currentTarget.style.color = "var(--danger)"; }}
            onMouseLeave={(ev) => { ev.currentTarget.style.background = "transparent"; ev.currentTarget.style.color = "var(--ink-4)"; }}>
            <I.trash />
          </button>
        </div>
      ))}
      <button type="button" onClick={add}
        style={{ display: "inline-flex", alignItems: "center", gap: 7, margin: "6px 0 2px", padding: "6px 10px 6px 7px", background: "transparent", border: "none", cursor: "pointer", color: "var(--accent)", fontSize: 13, fontWeight: 600, borderRadius: 8 }}
        onMouseEnter={(e) => (e.currentTarget.style.background = "var(--accent-weak-2)")}
        onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}>
        <span style={{ width: 18, height: 18, borderRadius: 6, background: "var(--accent-weak)", display: "grid", placeItems: "center" }}><I.plus /></span>
        {addText}
      </button>
    </div>
  );
}

function ChiHoRows({ rows, onChange, options, onCreate, prices, fromCost }) {
  return <ItemRows rows={rows} onChange={onChange} options={options} onCreate={onCreate} prices={prices}
    note={<>Chọn khoản từ <b style={{ color: "var(--ink-3)" }}>danh mục Khoản thu/chi hộ</b> — cộng vào tổng phải thu của khách.{fromCost}</>}
    addText="Thêm khoản chi hộ" placeholder="Chọn khoản chi hộ…" />;
}

function DoanhThuRows({ rows, onChange, options, onCreate, prices }) {
  return <ItemRows rows={rows} onChange={onChange} options={options} onCreate={onCreate} prices={prices}
    note={<>Chọn khoản từ <b style={{ color: "var(--ink-3)" }}>danh mục Khoản doanh thu</b> — gõ để tìm hoặc thêm mới.</>}
    addText="Thêm khoản doanh thu" placeholder="Chọn khoản doanh thu…" />;
}

/* mini checkbox */

function ChkBox({ checked, onChange, label }) {
  return (
    <button type="button" role="checkbox" aria-checked={checked} onClick={() => onChange(!checked)}
      style={{ display: "flex", alignItems: "center", gap: 7, background: "none", border: "none", cursor: "pointer", padding: "2px 0", width: "100%" }}>
      <span style={{ width: 18, height: 18, borderRadius: 5, flexShrink: 0, display: "grid", placeItems: "center",
        background: checked ? "var(--good)" : "#fff", border: checked ? "1px solid var(--good)" : "1.5px solid var(--line)",
        color: "#fff", transition: "all .12s" }}>
        {checked && <I.check />}
      </span>
      {label && <span style={{ fontSize: 12.5, color: checked ? "var(--good)" : "var(--ink-3)", fontWeight: checked ? 600 : 400 }}>{label}</span>}
    </button>
  );
}

/* Unified cost line repeater: khoản · số tiền · người chi · tích chi hộ khách */

const TRACK_COLORS = [
  { id: "", dot: "", label: "Không theo dõi" },
  { id: "amber", dot: "#e0a92e", label: "Vàng" },
  { id: "blue", dot: "#2a6fdb", label: "Xanh dương" },
  { id: "green", dot: "#1f8a5b", label: "Xanh lá" },
  { id: "red", dot: "#d64545", label: "Đỏ" },
];

const SWATCHES = ["#e0a92e", "#2a6fdb", "#1f8a5b", "#d64545", "#8b5cf6", "#ec4899", "#0891b2", "#f97316", "#16a34a", "#64748b"];
// normalize legacy ids -> hex

const colorHex = (c) => { if (!c) return ""; const f = TRACK_COLORS.find((x) => x.id === c); return f ? f.dot : c; };

function FlagPicker({ value, missing, onChange }) {
  const { useState, useRef, useLayoutEffect, useEffect } = React;
  const [open, setOpen] = useState(false);
  const [pos, setPos] = useState({ top: 0, left: 0 });
  const btnRef = useRef(null);
  const hex = colorHex(value);
  const POP_W = 184, POP_H = 150;

  // Định vị popup ngay sau khi mở — anchor vào nút theo viewport (fixed),
  // tránh bị cha có overflow:auto/hidden cắt mất.
  useLayoutEffect(() => {
    if (!open || !btnRef.current) return;
    const r = btnRef.current.getBoundingClientRect();
    const vw = window.innerWidth, vh = window.innerHeight;
    let left = r.right - POP_W; if (left < 8) left = 8; if (left + POP_W > vw - 8) left = vw - POP_W - 8;
    let top = r.bottom + 6; if (top + POP_H > vh - 8) top = Math.max(8, r.top - POP_H - 6);
    setPos({ top, left });
  }, [open]);

  // Cuộn ngoài hoặc resize → đóng popup để tránh lệch vị trí.
  useEffect(() => {
    if (!open) return;
    const close = () => setOpen(false);
    window.addEventListener("scroll", close, true);
    window.addEventListener("resize", close);
    return () => { window.removeEventListener("scroll", close, true); window.removeEventListener("resize", close); };
  }, [open]);

  return (
    <div style={{ display: "flex", justifyContent: "center" }}>
      <button ref={btnRef} type="button" onClick={() => setOpen((o) => !o)} title={hex ? "Đang theo dõi" + (missing ? " · chưa điền → hiện ngoài bảng" : " · đã điền") : "Bấm để chọn màu theo dõi"}
        style={{ display: "inline-flex", alignItems: "center", gap: 5, border: "1px solid var(--line)", borderRadius: 999, padding: "3px 6px", background: "#fff", cursor: "pointer" }}>
        <span style={{ width: 13, height: 13, borderRadius: 999, background: hex || "transparent", border: hex ? "none" : "1.5px dashed var(--ink-4)" }} />
        {missing && <span style={{ fontSize: 9.5, fontWeight: 700, color: "var(--warn)" }}>!</span>}
      </button>
      {open && ReactDOM.createPortal(
        <>
          <div onClick={() => setOpen(false)} style={{ position: "fixed", inset: 0, zIndex: 9998 }} />
          <div style={{ position: "fixed", top: pos.top, left: pos.left, zIndex: 9999, background: "#fff", border: "1px solid var(--line)", borderRadius: 12, boxShadow: "0 8px 28px -8px rgba(16,19,23,.28)", padding: 12, width: POP_W }}>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(5, 1fr)", gap: 7, marginBottom: 10 }}>
              {SWATCHES.map((c) => (
                <button key={c} type="button" onClick={() => { onChange(c); setOpen(false); }}
                  style={{ width: 24, height: 24, borderRadius: 999, background: c, cursor: "pointer", border: hex === c ? "2px solid var(--ink)" : "2px solid #fff", boxShadow: "0 0 0 1px var(--line)" }} />
              ))}
            </div>
            <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 8 }}>
              <label style={{ display: "inline-flex", alignItems: "center", gap: 7, fontSize: 12, color: "var(--ink-2)", cursor: "pointer" }}>
                <input type="color" value={hex || "#2a6fdb"} onChange={(e) => onChange(e.target.value)}
                  style={{ width: 26, height: 26, padding: 0, border: "1px solid var(--line)", borderRadius: 7, background: "#fff", cursor: "pointer" }} />
                Màu tùy chọn
              </label>
            </div>
            {hex && (
              <button type="button" onClick={() => { onChange(""); setOpen(false); }}
                style={{ width: "100%", fontSize: 12, fontWeight: 600, color: "var(--ink-3)", background: "var(--line-2)", border: "none", borderRadius: 8, padding: "6px 0", cursor: "pointer" }}>
                Bỏ theo dõi
              </button>
            )}
          </div>
        </>,
        document.body
      )}
    </div>
  );
}

function CostLineRows({ rows = [], onChange, options = [], onCreate, payers = [], onCreatePayer, prices = {}, costColors = {} }) {
  const isMobile = useIsMobile();
  const set = (id, np) => onChange(rows.map((e) => (e.id === id ? { ...e, ...np } : e)));
  const pickItem = (e, item) => set(e.id, { item, amount: toNum(e.amount) ? e.amount : (prices[item] || "") });
  const add = () => onChange([...rows, { id: Date.now() + Math.random(), item: "", amount: "", payer: "", date: "", billable: false }]);
  const del = (id) => onChange(rows.filter((e) => e.id !== id));
  const cols = "1fr 124px 118px 124px 92px 44px 28px";
  return (
    <div style={{ padding: "4px 0 0" }}>
      <div style={{ overflowX: isMobile ? "auto" : "visible", WebkitOverflowScrolling: "touch" }}>
      <div style={{ minWidth: isMobile ? 660 : undefined }}>
      <div style={{ display: "grid", gridTemplateColumns: cols, gap: 9, padding: "8px 0 5px" }}>
        {["Khoản chi phí", "Số tiền", "Người chi", "Ngày hóa đơn", "Chi hộ", "Theo dõi", ""].map((h, i) => (
          <div key={i} style={{ fontSize: 11, fontWeight: 600, color: "var(--ink-4)", textTransform: "uppercase", letterSpacing: "0.04em", textAlign: i === 1 ? "right" : (i === 4 || i === 5) ? "center" : "left" }}>{h}</div>
        ))}
      </div>
      {rows.map((e) => {
        const locked = !!e.src;
        const hex = colorHex(costColors[e.item] || "");
        const missing = !!hex && !toNum(e.amount);
        return (
        <div key={e.id} style={{ display: "grid", gridTemplateColumns: cols, gap: 9, alignItems: "center", padding: "5px 0", background: locked ? "var(--accent-weak-2)" : (e.billable ? "var(--good-weak)" : "transparent"), borderRadius: 8 }}>
          {locked
            ? <div title="Khoản liên kết từ Thông tin lô — sửa số tiền được, không xóa được ở đây (gỡ ở Thông tin lô)" style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 13, fontWeight: 600, color: "var(--ink-2)", padding: "0 4px", minWidth: 0 }}><span style={{ color: "var(--accent)", flexShrink: 0 }}><I.link /></span><span style={{ whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{e.item || "Cước xe ngoài"}</span></div>
            : <Combo value={e.item} onChange={(x) => pickItem(e, x)} options={options} onCreate={onCreate} placeholder="Chọn khoản chi phí…" small />}
          <Money value={e.amount} onChange={(x) => set(e.id, { amount: x })} dim />
          <Combo value={e.payer} onChange={(x) => set(e.id, { payer: x })} options={payers} onCreate={onCreatePayer} placeholder="Người chi…" small />
          <DateField value={e.date} onChange={(x) => set(e.id, { date: x })} />
          <div style={{ display: "flex", justifyContent: "center" }}><ChkBox checked={!!e.billable} onChange={(v) => set(e.id, { billable: v })} /></div>
          <div style={{ display: "flex", justifyContent: "center" }}
            title={hex ? ("Màu theo dõi gắn cho khoản này ở Cài đặt" + (missing ? " · chưa điền tiền → hiện nhắc ngoài bảng" : " · đã điền")) : "Khoản này chưa gắn màu theo dõi (chỉnh ở Cài đặt → Khoản chi phí)"}>
            <span style={{ position: "relative", display: "inline-flex", alignItems: "center", gap: 4, padding: "3px 7px", border: "1px solid var(--line)", borderRadius: 999, background: hex ? "#fff" : "var(--line-2)" }}>
              <span style={{ width: 11, height: 11, borderRadius: 999, background: hex || "transparent", border: hex ? "none" : "1.5px dashed var(--ink-4)" }} />
              {missing && <span style={{ fontSize: 9.5, fontWeight: 700, color: "var(--warn)" }}>!</span>}
            </span>
          </div>
          {locked
            ? <span title="Khóa — gỡ bằng cách bỏ tích “Thuê xe ngoài” ở Thông tin lô" style={{ width: 28, height: 28, display: "grid", placeItems: "center", color: "var(--ink-4)", fontSize: 12 }}>🔒</span>
            : <button type="button" onClick={() => del(e.id)} title="Xóa"
                style={{ width: 28, height: 28, display: "grid", placeItems: "center", border: "none", borderRadius: 7, background: "transparent", color: "var(--ink-4)", cursor: "pointer" }}
                onMouseEnter={(ev) => { ev.currentTarget.style.background = "#fce8e8"; ev.currentTarget.style.color = "var(--danger)"; }}
                onMouseLeave={(ev) => { ev.currentTarget.style.background = "transparent"; ev.currentTarget.style.color = "var(--ink-4)"; }}>
                <I.trash />
              </button>}
        </div>
      ); })}
      {!rows.length && <div style={{ fontSize: 13, color: "var(--ink-4)", padding: "10px 0" }}>Chưa có khoản chi phí nào.</div>}
      </div>
      </div>
      <div style={{ fontSize: 11.5, color: "var(--ink-4)", padding: "2px 0 0" }}>Màu <b style={{ color: "var(--ink-3)" }}>Theo dõi</b> được gắn sẵn cho từng khoản tại <b style={{ color: "var(--ink-3)" }}>Cài đặt → Khoản chi phí</b>. Khoản có gắn màu mà <b style={{ color: "var(--ink-3)" }}>chưa điền số tiền</b> sẽ hiện nhắc ngoài bảng lô; điền rồi thì ẩn.</div>
      <button type="button" onClick={add}
        style={{ display: "inline-flex", alignItems: "center", gap: 7, margin: "8px 0 2px", padding: "6px 10px 6px 7px", background: "transparent", border: "none", cursor: "pointer", color: "var(--accent)", fontSize: 13, fontWeight: 600, borderRadius: 8 }}
        onMouseEnter={(e) => (e.currentTarget.style.background = "var(--accent-weak-2)")}
        onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}>
        <span style={{ width: 18, height: 18, borderRadius: 6, background: "var(--accent-weak)", display: "grid", placeItems: "center" }}><I.plus /></span>
        Thêm khoản chi phí
      </button>
    </div>
  );
}

/* Repeater of partial payments — khách trả từ từ nhiều đợt */

function PaymentRows({ payments = [], onChange }) {
  const set = (id, np) => onChange(payments.map((p) => (p.id === id ? { ...p, ...np } : p)));
  const add = () => onChange([...payments, { id: Date.now() + Math.random(), amount: "", date: "" }]);
  const del = (id) => onChange(payments.filter((p) => p.id !== id));
  return (
    <div style={{ padding: "8px 0 0" }}>
      {payments.length > 0 && (
        <div style={{ display: "grid", gridTemplateColumns: "26px 1fr 170px 30px", gap: 10, padding: "0 0 4px" }}>
          {["Đợt", "Số tiền", "Ngày thanh toán", ""].map((h, i) => (
            <div key={i} style={{ fontSize: 11, fontWeight: 600, color: "var(--ink-4)", textTransform: "uppercase", letterSpacing: "0.04em", textAlign: i === 1 ? "right" : "left" }}>{h}</div>
          ))}
        </div>
      )}
      {payments.map((p, i) => (
        <div key={p.id} style={{ display: "grid", gridTemplateColumns: "26px 1fr 170px 30px", gap: 10, alignItems: "center", padding: "5px 0" }}>
          <div className="tnum" style={{ fontSize: 12.5, color: "var(--ink-3)", textAlign: "center", fontWeight: 600 }}>{i + 1}</div>
          <Money value={p.amount} onChange={(x) => set(p.id, { amount: x })} dim />
          <DateField value={p.date} onChange={(x) => set(p.id, { date: x })} />
          <button type="button" onClick={() => del(p.id)} title="Xóa đợt"
            style={{ width: 28, height: 28, display: "grid", placeItems: "center", border: "none", borderRadius: 7, background: "transparent", color: "var(--ink-4)", cursor: "pointer" }}
            onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
            onMouseLeave={(e) => { e.currentTarget.style.background = "transparent"; e.currentTarget.style.color = "var(--ink-4)"; }}>
            <I.trash />
          </button>
        </div>
      ))}
      <button type="button" onClick={add}
        style={{ display: "inline-flex", alignItems: "center", gap: 7, margin: "6px 0 2px", padding: "6px 10px 6px 7px", background: "transparent", border: "none", cursor: "pointer", color: "var(--accent)", fontSize: 13, fontWeight: 600, borderRadius: 8 }}
        onMouseEnter={(e) => (e.currentTarget.style.background = "var(--accent-weak-2)")}
        onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}>
        <span style={{ width: 18, height: 18, borderRadius: 6, background: "var(--accent-weak)", display: "grid", placeItems: "center" }}><I.plus /></span>
        Thêm lần thanh toán
      </button>
    </div>
  );
}

/* ===================== REVENUE POPUP ===================== */

function Seg({ value, onChange, options }) {
  return (
    <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 9, padding: 3 }}>
      {options.map((o) => {
        const active = value === o;
        return (
          <button key={o} type="button" onClick={() => onChange(o)}
            style={{ border: "none", cursor: "pointer", fontSize: 13, fontWeight: 600, padding: "7px 16px", borderRadius: 7,
              background: active ? "#fff" : "transparent", color: active ? "var(--ink)" : "var(--ink-3)",
              boxShadow: active ? "0 1px 2px rgba(16,19,23,.14)" : "none", transition: "all .12s" }}>{o}</button>
        );
      })}
    </div>
  );
}



export { DTField, Field, DriverSpendRows, VatLine, ItemRows, ChiHoRows, DoanhThuRows, ChkBox, TRACK_COLORS, SWATCHES, colorHex, FlagPicker, CostLineRows, PaymentRows, Seg };
