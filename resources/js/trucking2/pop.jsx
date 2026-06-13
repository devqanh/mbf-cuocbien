import React from "react";
import ReactDOM from "react-dom";   // createPortal (FlagPicker popover) — không còn global sau khi build
const { useState, useMemo } = React;
import { I, Money, Payer, Txt, Combo, MultiCombo, DateField, Num, Line, Section, Modal, Btn, fmtVND, fmtNum, fmtShort, calcCost, calcVeh, calcRev, calcVehICD, calcRevICD, calcFreeTime, fmtHours, toNum } from "@trk/lib.jsx";

/* datetime-local field */
function DTField({ value, onChange }) {
  return (
    <input type="datetime-local" value={value || ""} onChange={(e) => onChange(e.target.value)}
      style={{ width: "100%", padding: "7px 10px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: value ? "var(--ink-2)" : "var(--ink-4)", outline: "none", colorScheme: "light" }}
      onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
  );
}

/* ===================== COST POPUP (centerpiece) ===================== */
function CostPopup({ ship, patch, onSave, isDirty, onClose, cfg = {}, addCfg }) {
  const payerOpts = cfg.payers || [];
  const costOpts = cfg.costItems || [];
  const prices = cfg.prices || {};
  const addPayer = (v) => addCfg && addCfg("payers", v);
  const addCostItem = (v) => addCfg && addCfg("costItems", v);
  const [showFx, setShowFx] = useState(false);
  const c = ship.cost || {};
  const setC = (np) => patch({ cost: { ...c, ...np } });
  const cc = calcCost(c);
  const items = c.items || [];
  const setItems = (arr) => setC({ items: arr });
  const dirty = !!(isDirty && isDirty(ship.id));
  const [saving, setSaving] = useState(false);
  const handleSave = () => { if (saving) return; setSaving(true); Promise.resolve(onSave && onSave()).then(() => onClose()).catch(() => setSaving(false)); };

  const footer = (
    <div style={{ display: "flex", alignItems: "flex-end", justifyContent: "space-between", gap: 20 }}>
      <div style={{ display: "flex", gap: 24 }}>
        <div>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 2 }}>Chi phí công ty</div>
          <div className="tnum" style={{ fontSize: 16, fontWeight: 700, color: "var(--ink-2)" }}>{fmtVND(cc.congTy)}</div>
        </div>
        <div style={{ borderLeft: "1px solid var(--line)", paddingLeft: 24 }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 2 }}>Chi hộ (thu lại khách)</div>
          <div className="tnum" style={{ fontSize: 16, fontWeight: 700, color: "var(--good)" }}>{fmtVND(cc.thuChiHo)}</div>
        </div>
        <div style={{ borderLeft: "1px solid var(--line)", paddingLeft: 24 }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 2, display: "flex", alignItems: "center", gap: 6 }}>
            Tổng chi phí
            <button type="button" onClick={() => setShowFx((s) => !s)} title="Xem công thức"
              style={{ display: "inline-grid", placeItems: "center", width: 18, height: 18, border: "none", borderRadius: 5, background: showFx ? "var(--accent-weak)" : "transparent", color: showFx ? "var(--accent)" : "var(--ink-4)", cursor: "pointer" }}><I.fx /></button>
          </div>
          <div className="tnum" style={{ fontSize: 22, fontWeight: 700, letterSpacing: "-0.02em" }}>{fmtVND(cc.tongChiPhi)}</div>
        </div>
      </div>
      <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
        {dirty && <span style={{ fontSize: 12, color: "var(--warn)", fontWeight: 600, display: "inline-flex", alignItems: "center", gap: 5 }}><span style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} />Có thay đổi chưa lưu</span>}
        <Btn onClick={onClose}>Đóng</Btn>
        <Btn variant="primary" onClick={handleSave} disabled={!dirty || saving}>{saving ? "Đang lưu…" : "Lưu chi phí"}</Btn>
      </div>
    </div>
  );

  return (
    <Modal title="Chi phí lô hàng" subtitle={<>Lô <b style={{ color: "var(--ink-2)" }}>{ship.booking}</b> · {ship.customer} · gom mọi khoản chi phí phân bổ vào một nơi</>}
      onClose={onClose} footer={footer} width={960}>

      {showFx && (
        <div style={{ margin: "12px 0 2px", padding: "10px 13px", background: "var(--accent-weak-2)", border: "1px solid var(--accent-weak)", borderRadius: 10, fontSize: 12.5, color: "var(--ink-2)", lineHeight: 1.6 }}>
          <b style={{ color: "var(--accent)" }}>Tổng chi phí</b> = cộng tất cả các khoản. Khoản tích <b style={{ color: "var(--good)" }}>“Chi hộ khách”</b> là phần sẽ thu lại của khách (chi hộ); khoản không tích là <b>chi phí công ty</b> tự chịu.
          <br /><span style={{ color: "var(--ink-3)" }}>Cột “Người chi” chỉ ghi ai ứng/chi khoản đó, không cộng vào tổng.</span>
        </div>
      )}

      <CostLineRows rows={items} onChange={setItems} options={costOpts} onCreate={addCostItem}
        payers={payerOpts} onCreatePayer={addPayer} prices={prices} costColors={cfg.costColors || {}} />
    </Modal>
  );
}

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
  const set = (id, np) => onChange(rows.map((e) => (e.id === id ? { ...e, ...np } : e)));
  const add = () => onChange([...rows, { id: Date.now() + Math.random(), who: "", date: "", amount: "", note: "" }]);
  const del = (id) => onChange(rows.filter((e) => e.id !== id));
  return (
    <div style={{ padding: "6px 0 0" }}>
      <div style={{ fontSize: 11.5, color: "var(--ink-4)", padding: "0 0 4px" }}>Theo dõi từng lần lái xe ứng/chi tiền mặt — ai chi, ngày nào, bao nhiêu.</div>
      {rows.length > 0 && (
        <div style={{ display: "grid", gridTemplateColumns: "150px 150px 1fr 150px 30px", gap: 10, padding: "0 0 4px" }}>
          {["Người chi", "Ngày chi", "Nội dung", "Số tiền", ""].map((h, i) => (
            <div key={i} style={{ fontSize: 11, fontWeight: 600, color: "var(--ink-4)", textTransform: "uppercase", letterSpacing: "0.04em", textAlign: i === 3 ? "right" : "left" }}>{h}</div>
          ))}
        </div>
      )}
      {rows.map((e) => (
        <div key={e.id} style={{ display: "grid", gridTemplateColumns: "150px 150px 1fr 150px 30px", gap: 10, alignItems: "center", padding: "5px 0" }}>
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
  const set = (id, np) => onChange(rows.map((e) => (e.id === id ? { ...e, ...np } : e)));
  const pickItem = (e, item) => set(e.id, { item, amount: toNum(e.amount) ? e.amount : (prices[item] || "") });
  const add = () => onChange([...rows, { id: Date.now() + Math.random(), item: "", amount: "", payer: "", date: "", billable: false }]);
  const del = (id) => onChange(rows.filter((e) => e.id !== id));
  const cols = "1fr 124px 118px 124px 92px 44px 28px";
  return (
    <div style={{ padding: "4px 0 0" }}>
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
function RevenuePopup({ ship, patch, onSave, isDirty, onClose, cfg = {}, addCfg }) {
  const r = ship.rev || {};
  const setR = (np) => patch({ rev: { ...r, ...np } });
  const rc = calcRev(r);
  const paid = rc.conNo <= 0 && rc.phaiThu > 0;
  const choHo = r.choHo || [];
  const choHoOpts = cfg.choHoItems || [];
  const setChoHo = (arr) => setR({ choHo: arr });
  const dirty = !!(isDirty && isDirty(ship.id));
  const [saving, setSaving] = useState(false);
  const handleSave = () => { if (saving) return; setSaving(true); Promise.resolve(onSave && onSave()).then(() => onClose()).catch(() => setSaving(false)); };

  const footer = (
    <div style={{ display: "flex", alignItems: "flex-end", justifyContent: "space-between", gap: 20 }}>
      <div style={{ display: "flex", gap: 26 }}>
        <div>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 2 }}>Tổng phải thu</div>
          <div className="tnum" style={{ fontSize: 22, fontWeight: 700, letterSpacing: "-0.02em" }}>{fmtVND(rc.phaiThu)}</div>
        </div>
        <div style={{ borderLeft: "1px solid var(--line)", paddingLeft: 26 }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 2 }}>Còn nợ</div>
          <div className="tnum" style={{ fontSize: 16, fontWeight: 700, color: rc.conNo > 0 ? "var(--warn)" : "var(--good)" }}>{fmtVND(Math.max(0, rc.conNo))}</div>
        </div>
      </div>
      <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
        {dirty && <span style={{ fontSize: 12, color: "var(--warn)", fontWeight: 600, display: "inline-flex", alignItems: "center", gap: 5 }}><span style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} />Có thay đổi chưa lưu</span>}
        <Btn onClick={onClose}>Đóng</Btn>
        <Btn variant="primary" onClick={handleSave} disabled={!dirty || saving}>{saving ? "Đang lưu…" : "Lưu doanh thu"}</Btn>
      </div>
    </div>
  );

  return (
    <Modal title="Doanh thu & công nợ" subtitle={<>Lô <b style={{ color: "var(--ink-2)" }}>{ship.booking}</b> · {ship.customer}</>} onClose={onClose} footer={footer} width={820}>
      <Section title="Doanh thu" total={rc.tongDT} totalLabel="Tổng doanh thu">
        <DoanhThuRows rows={r.doanhThu || []} onChange={(arr) => setR({ doanhThu: arr })} options={cfg.revItems || []} onCreate={(v) => addCfg && addCfg("revItems", v)} prices={cfg.prices || {}} />
        <VatLine rate={r.vatRate == null ? "8" : r.vatRate} vat={rc.vat} onRate={(x) => setR({ vatRate: x })} />
      </Section>

      <Section title="Thu chi hộ (thu lại của khách)" total={(choHo).reduce((s,e)=>s+toNum(e.amount),0)} totalLabel="Tổng chi hộ">
        {(ship.cost?.items || []).filter((e) => e.billable).length > 0 && (
          <button type="button" onClick={() => setChoHo((ship.cost.items || []).filter((e) => e.billable).map((e) => ({ id: Date.now() + Math.random(), item: e.item, amount: e.amount })))}
            style={{ display: "inline-flex", alignItems: "center", gap: 6, margin: "6px 0 0", padding: "5px 10px", background: "var(--accent-weak)", border: "none", cursor: "pointer", color: "var(--accent)", fontSize: 12.5, fontWeight: 600, borderRadius: 8 }}
            title="Lấy các khoản đã tích 'chi hộ khách' ở popup Chi phí">
            <I.fx /> Lấy từ chi phí ({(ship.cost.items || []).filter((e) => e.billable).length} khoản)
          </button>
        )}
        <ChiHoRows rows={choHo} onChange={setChoHo} options={choHoOpts} onCreate={(v) => addCfg && addCfg("choHoItems", v)} prices={cfg.prices || {}} />
      </Section>

      <Section title="Thanh toán" total={rc.daTT} totalLabel="Đã thu">
        <div style={{ padding: "10px 0 4px", maxWidth: 320 }}>
          <Field label="Hạn thanh toán"><DateField value={r.hanTT} onChange={(x) => setR({ hanTT: x })} /></Field>
        </div>
        <div style={{ fontSize: 11.5, color: "var(--ink-4)", padding: "2px 0 0" }}>Khách trả nhiều đợt — thêm từng lần với số tiền và ngày.</div>
        <PaymentRows payments={r.payments || []} onChange={(arr) => setR({ payments: arr })} />
        <div style={{ display: "flex", alignItems: "center", gap: 10, padding: "10px 0 8px" }}>
          <span style={{ fontSize: 12.5, fontWeight: 600, color: paid ? "var(--good)" : "var(--warn)", background: paid ? "var(--good-weak)" : "var(--warn-weak)", padding: "4px 11px", borderRadius: 999 }}>
            {rc.phaiThu === 0 ? "Chưa có doanh thu" : paid ? "Đã thu đủ" : `Còn nợ ${fmtVND(rc.conNo)}`}
          </span>
          {(r.payments || []).length > 0 && <span style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Đã thu {(r.payments || []).length} đợt: <b className="tnum" style={{ color: "var(--good)" }}>{fmtVND(rc.daTT)}</b></span>}
        </div>
        <Field label="Ghi chú kế toán"><Txt value={r.ghiChu} onChange={(x) => setR({ ghiChu: x })} placeholder="Ghi chú…" /></Field>
      </Section>
    </Modal>
  );
}

/* ===================== ICD — CHI PHÍ CHUYẾN XE ===================== */
function CostPopupICD({ ship, patch, onSave, isDirty, onClose, cfg = {}, addCfg }) {
  const v = ship.veh || {};
  const setV = (np) => patch({ veh: { ...v, ...np } });
  const tong = calcVehICD(v);
  const dirty = !!(isDirty && isDirty(ship.id));
  const [saving, setSaving] = useState(false);
  const handleSave = () => { if (saving) return; setSaving(true); Promise.resolve(onSave && onSave()).then(() => onClose()).catch(() => setSaving(false)); };
  const footer = (
    <div style={{ display: "flex", alignItems: "flex-end", justifyContent: "space-between", gap: 20 }}>
      <div>
        <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 2 }}>Tổng chi phí chuyến xe</div>
        <div className="tnum" style={{ fontSize: 22, fontWeight: 700, letterSpacing: "-0.02em" }}>{fmtVND(tong)}</div>
      </div>
      <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
        {dirty && <span style={{ fontSize: 12, color: "var(--warn)", fontWeight: 600, display: "inline-flex", alignItems: "center", gap: 5 }}><span style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} />Có thay đổi chưa lưu</span>}
        <Btn onClick={onClose}>Đóng</Btn>
        <Btn variant="primary" onClick={handleSave} disabled={!dirty || saving}>{saving ? "Đang lưu…" : "Lưu chi phí"}</Btn>
      </div>
    </div>
  );
  return (
    <Modal title="Chi phí chuyến xe" subtitle={<>Lô <b style={{ color: "var(--ink-2)" }}>{ship.booking}</b> · {ship.customer}</>} onClose={onClose} footer={footer} width={760}>
      <Section title="Xe chạy">
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, padding: "8px 0" }}>
          <Field label="Biển số xe" hint="danh mục"><Combo value={v.bienSo} onChange={(x) => setV({ bienSo: x })} options={cfg.vehicles || []} onCreate={(x) => addCfg && addCfg("vehicles", x)} placeholder="15C-123.45…" /></Field>
          <Field label="Lái xe" hint="danh mục"><Combo value={v.laiXe} onChange={(x) => setV({ laiXe: x })} options={cfg.drivers || []} onCreate={(x) => addCfg && addCfg("drivers", x)} placeholder="Chọn lái xe…" /></Field>
        </div>
      </Section>
      <Section title="Chi phí chuyến xe" total={tong} totalLabel="Tổng chi phí">
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, padding: "10px 0" }}>
          <Field label="Phụ cấp tiền đường"><Money value={v.phuCapTienDuong} onChange={(x) => setV({ phuCapTienDuong: x })} dim /></Field>
          <Field label="Trợ cấp"><Money value={v.troCap} onChange={(x) => setV({ troCap: x })} dim /></Field>
          <Field label="Lương"><Money value={v.luong} onChange={(x) => setV({ luong: x })} dim /></Field>
          <Field label="Chi phí khác"><Money value={v.chiPhiKhac} onChange={(x) => setV({ chiPhiKhac: x })} dim /></Field>
        </div>
      </Section>
      <Section title="Nhiên liệu & quãng đường">
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 12, padding: "10px 0" }}>
          <Field label="Quãng đường"><Num value={v.km} onChange={(x) => setV({ km: x })} suffix="km" /></Field>
          <Field label="Số lít"><Num value={v.lit} onChange={(x) => setV({ lit: x })} suffix="L" /></Field>
          <Field label="Đơn giá dầu"><Money value={v.donGia} onChange={(x) => setV({ donGia: x })} dim /></Field>
        </div>
        <div style={{ fontSize: 12, color: "var(--ink-3)", padding: "2px 0 8px" }}>Tiền dầu = Lít × Đơn giá = <b className="tnum" style={{ color: "var(--ink-2)" }}>{fmtVND(toNum(v.lit) * toNum(v.donGia))}</b></div>
      </Section>
    </Modal>
  );
}

/* ===================== ICD — DOANH THU ===================== */
function RevenuePopupICD({ ship, patch, onSave, isDirty, onClose, cfg = {}, addCfg }) {
  const r = ship.rev || {};
  const setR = (np) => patch({ rev: { ...r, ...np } });
  const rc = calcRevICD(r);
  const paid = rc.conNo <= 0 && rc.phaiThu > 0;
  const choHo = r.choHo || [];
  const choHoOpts = cfg.choHoItems || [];
  const setChoHo = (arr) => setR({ choHo: arr });
  const dirty = !!(isDirty && isDirty(ship.id));
  const [saving, setSaving] = useState(false);
  const handleSave = () => { if (saving) return; setSaving(true); Promise.resolve(onSave && onSave()).then(() => onClose()).catch(() => setSaving(false)); };
  const footer = (
    <div style={{ display: "flex", alignItems: "flex-end", justifyContent: "space-between", gap: 20 }}>
      <div style={{ display: "flex", gap: 26 }}>
        <div>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 2 }}>Tổng phải thu</div>
          <div className="tnum" style={{ fontSize: 22, fontWeight: 700, letterSpacing: "-0.02em" }}>{fmtVND(rc.phaiThu)}</div>
        </div>
        <div style={{ borderLeft: "1px solid var(--line)", paddingLeft: 26 }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 2 }}>Còn nợ</div>
          <div className="tnum" style={{ fontSize: 16, fontWeight: 700, color: rc.conNo > 0 ? "var(--warn)" : "var(--good)" }}>{fmtVND(Math.max(0, rc.conNo))}</div>
        </div>
      </div>
      <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
        {dirty && <span style={{ fontSize: 12, color: "var(--warn)", fontWeight: 600, display: "inline-flex", alignItems: "center", gap: 5 }}><span style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} />Có thay đổi chưa lưu</span>}
        <Btn onClick={onClose}>Đóng</Btn>
        <Btn variant="primary" onClick={handleSave} disabled={!dirty || saving}>{saving ? "Đang lưu…" : "Lưu doanh thu"}</Btn>
      </div>
    </div>
  );
  return (
    <Modal title="Doanh thu & công nợ" subtitle={<>Lô <b style={{ color: "var(--ink-2)" }}>{ship.booking}</b> · {ship.customer}</>} onClose={onClose} footer={footer} width={780}>
      <Section title="Doanh thu" total={rc.tongDT} totalLabel="Tổng doanh thu">
        <DoanhThuRows rows={r.doanhThu || []} onChange={(arr) => setR({ doanhThu: arr })} options={cfg.revItems || []} onCreate={(v) => addCfg && addCfg("revItems", v)} prices={cfg.prices || {}} />
        <VatLine rate={r.vatRate == null ? "0" : r.vatRate} vat={rc.vat} onRate={(x) => setR({ vatRate: x })} />
      </Section>
      <Section title="Chi hộ" total={(choHo).reduce((s,e)=>s+toNum(e.amount),0)} totalLabel="Tổng chi hộ">
        <ChiHoRows rows={choHo} onChange={setChoHo} options={choHoOpts} onCreate={(v) => addCfg && addCfg("choHoItems", v)} prices={cfg.prices || {}} />
      </Section>
      <Section title="Thanh toán" total={rc.daTT} totalLabel="Đã thu">
        <div style={{ padding: "10px 0 4px", maxWidth: 320 }}>
          <Field label="Hạn thanh toán"><DateField value={r.hanTT} onChange={(x) => setR({ hanTT: x })} /></Field>
        </div>
        <div style={{ fontSize: 11.5, color: "var(--ink-4)", padding: "2px 0 0" }}>Khách trả nhiều đợt — thêm từng lần với số tiền và ngày.</div>
        <PaymentRows payments={r.payments || []} onChange={(arr) => setR({ payments: arr })} />
        <div style={{ display: "flex", alignItems: "center", gap: 10, padding: "10px 0 8px" }}>
          <span style={{ fontSize: 12.5, fontWeight: 600, color: paid ? "var(--good)" : "var(--warn)", background: paid ? "var(--good-weak)" : "var(--warn-weak)", padding: "4px 11px", borderRadius: 999 }}>
            {rc.phaiThu === 0 ? "Chưa có doanh thu" : paid ? "Đã thu đủ" : `Còn nợ ${fmtVND(rc.conNo)}`}
          </span>
          {(r.payments || []).length > 0 && <span style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Đã thu {(r.payments || []).length} đợt: <b className="tnum" style={{ color: "var(--good)" }}>{fmtVND(rc.daTT)}</b></span>}
        </div>
        <Field label="Ghi chú kế toán"><Txt value={r.ghiChu} onChange={(x) => setR({ ghiChu: x })} placeholder="Ghi chú…" /></Field>
      </Section>
    </Modal>
  );
}

/* ===================== INFO EDIT POPUP (khách / cont / tuyến / lịch) ===================== */
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

function InfoPopup({ ship, patch, patchOther, onSave, isDirty, siblings = [], onClose, onDelete, canDelete, isHph, cfg = {}, addCfg }) {
  const set = (np) => patch(np);
  const add = (k, v) => addCfg && addCfg(k, v);
  const hqFee = ((ship.cost && ship.cost.items) || []).some((it) => it.src === "thanhLyFee" && toNum(it.amount) > 0);
  const hqFilled = [ship.declNo, ship.declNote, ship.thanhLy, ship.cshtNote].filter((v) => (v || "").toString().trim()).length + (hqFee ? 1 : 0);
  const [hqOpen, setHqOpen] = useState(false);
  // Thuê xe ngoài → 1 dòng chi phí "Cước xe ngoài" (src=extTruck) link sang Chi phí lô hàng
  const cost = ship.cost || {};
  const costItems = cost.items || [];
  const extLine = costItems.find((it) => it.src === "extTruck");
  const extHired = !!extLine;
  const setCostItems = (arr) => patch({ cost: { ...cost, items: arr } });
  const toggleExt = (on) => {
    if (on && !extLine) setCostItems([...costItems, { id: Date.now() + Math.random(), src: "extTruck", item: "Cước xe ngoài", amount: "", payer: "Xe ngoài", date: "", billable: false, color: "", note: "" }]);
    else if (!on && extLine) setCostItems(costItems.filter((it) => it.src !== "extTruck"));
  };
  const setExt = (np) => setCostItems(costItems.map((it) => (it.src === "extTruck" ? { ...it, ...np } : it)));
  // Phí thanh lý tờ khai (Hải Quan) → 1 dòng chi phí "Phí thanh lý tờ khai" (src=thanhLyFee) link sang Chi phí lô hàng
  const tlLine = costItems.find((it) => it.src === "thanhLyFee");
  const setTlFee = (val) => {
    if (toNum(val) > 0) {
      if (tlLine) setCostItems(costItems.map((it) => (it.src === "thanhLyFee" ? { ...it, amount: val } : it)));
      else setCostItems([...costItems, { id: Date.now() + Math.random(), src: "thanhLyFee", item: "Phí thanh lý tờ khai", amount: val, payer: "", date: "", billable: false, color: "", note: "" }]);
    } else if (tlLine) {
      setCostItems(costItems.filter((it) => it.src !== "thanhLyFee"));
    }
  };
  const sibOpts = siblings.map((s) => ({ value: s.id, label: (s.contNo || "(chưa có cont)") + " — " + (s.booking || "(chưa có booking)") }));
  const raMode = ship.raMode || "self";
  const other = (raMode === "other" && ship.raOtherId != null) ? siblings.find((s) => s.id === ship.raOtherId) : null;
  // Khi "cont khác ra": input giờ ra/BKS ra chỉ ghi vào cont kia (qua patchOther), KHÔNG động vào cont hiện tại.
  const setRa = (val) => { if (other && patchOther) patchOther(other.id, { gioXeRa: val }); else set({ gioXeRa: val }); };
  const setRaBks = (val) => { if (other && patchOther) patchOther(other.id, { bksRa: val }); else set({ bksRa: val }); };
  const otherGioXeRa = other ? (other.gioXeRa || "") : "";
  const otherBksRa = other ? (other.bksRa || "") : "";

  const dirty = !!(isDirty && (isDirty(ship.id) || (other && isDirty(other.id))));
  const missingReq = !((ship.customer || "").toString().trim()) || !((ship.booking || "").toString().trim());
  const [saving, setSaving] = useState(false);
  const handleSave = () => { if (missingReq || saving) return; setSaving(true); Promise.resolve(onSave && onSave()).then(() => onClose()).catch(() => setSaving(false)); };

  const footer = (
    <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 10 }}>
      <div>
        {canDelete && onDelete && (
          <button type="button" onClick={onDelete}
            style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "9px 14px", fontSize: 13.5, fontWeight: 500, border: "1px solid var(--line)", borderRadius: 10, background: "#fff", color: "var(--ink-3)", cursor: "pointer" }}
            onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; e.currentTarget.style.borderColor = "#f3c9c9"; }}
            onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-3)"; e.currentTarget.style.borderColor = "var(--line)"; }}>
            <I.trash /> Xóa lô hàng
          </button>
        )}
      </div>
      <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
        {missingReq
          ? <span style={{ fontSize: 12, color: "var(--danger)", fontWeight: 600 }}>Cần nhập Khách hàng <b>*</b> và Số booking <b>*</b></span>
          : (dirty && <span style={{ fontSize: 12, color: "var(--warn)", fontWeight: 600, display: "inline-flex", alignItems: "center", gap: 5 }}><span style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} />Có thay đổi chưa lưu</span>)}
        <Btn onClick={onClose}>Đóng</Btn>
        <Btn variant="primary" onClick={handleSave} disabled={!dirty || missingReq || saving}>{saving ? "Đang lưu…" : "Lưu thông tin"}</Btn>
      </div>
    </div>
  );
  return (
    <Modal title="Thông tin lô hàng" subtitle="Sửa khách hàng, container, tuyến và lịch trình" onClose={onClose} footer={footer} width={720}>
      <Section title="Thông tin chung">
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, padding: "10px 0" }}>
          <Field label="Khách hàng" hint="danh mục" req><Combo value={ship.customer} onChange={(x) => set({ customer: x })} options={cfg.customers || []} onCreate={(v) => add("customers", v)} placeholder="Chọn khách hàng…" /></Field>
          <Field label={isHph ? "Số booking" : "Số booking / bill"} req><Txt value={ship.booking} onChange={(x) => set({ booking: x })} placeholder="Mã booking" /></Field>
        </div>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, padding: "0 0 4px" }}>
          <Field label="Số INV" hint="hóa đơn"><Txt value={ship.inv} onChange={(x) => set({ inv: x })} placeholder="VD: INV-2026-0142" /></Field>
          <Field label="Nhập / Xuất"><div style={{ marginTop: 2 }}><Seg value={ship.io} onChange={(x) => set({ io: x })} options={["Nhập", "Xuất", "Khác"]} /></div></Field>
        </div>
        <div style={{ padding: "6px 0 2px" }}>
          <ChkBox checked={!!ship.cru} onChange={(v) => set({ cru: v })} label="Hàng CRU" />
          <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 4, paddingLeft: 25, lineHeight: 1.5 }}>
            Quyết định KIND khi lấy giá: <b style={{ color: "var(--ink-3)" }}>CRU + Xuất</b> → External CRU · <b style={{ color: "var(--ink-3)" }}>CRU + Nhập</b> → Internal CRU · <b style={{ color: "var(--ink-3)" }}>không CRU</b> → Transportation 1 way.
          </div>
        </div>
      </Section>

      <Section title="Container">
        {isHph ? (
          <div style={{ display: "grid", gridTemplateColumns: "120px 1fr 1.4fr", gap: 12, padding: "10px 0" }}>
            <Field label="Số lượng"><Num value={ship.qty} onChange={(x) => set({ qty: x })} /></Field>
            <Field label="Loại cont" hint="danh mục"><Combo value={ship.contType} onChange={(x) => set({ contType: x })} options={cfg.contTypes || []} onCreate={(v) => add("contTypes", v)} placeholder="40HC…" /></Field>
            <Field label="Số container"><Txt value={ship.contNo} onChange={(x) => set({ contNo: x })} placeholder="TGHU…" /></Field>
          </div>
        ) : (
          <>
            <div style={{ display: "grid", gridTemplateColumns: "1.2fr 1fr 1fr", gap: 12, padding: "10px 0 0" }}>
              <Field label="Số container"><Txt value={ship.contNo} onChange={(x) => set({ contNo: x })} placeholder="TGHU 123 4567" /></Field>
              <Field label="Loại cont" hint="danh mục"><Combo value={ship.contType} onChange={(x) => set({ contType: x })} options={cfg.contTypes || []} onCreate={(v) => add("contTypes", v)} placeholder="40HC…" /></Field>
              <Field label="Kho" hint="tối đa 3"><MultiCombo values={(ship.kho || "").split(/\s*,\s*/).filter(Boolean)} onChange={(arr) => set({ kho: arr.join(", ") })} options={cfg.warehouses || []} onCreate={(v) => add("warehouses", v)} max={3} placeholder="Chọn kho (tối đa 3)…" /></Field>
            </div>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, padding: "12px 0 0" }}>
              <Field label="BKS vào"><Combo value={ship.bksVao} onChange={(x) => set({ bksVao: x })} options={cfg.vehicles || []} onCreate={(v) => add("vehicles", v)} placeholder="15C-123.45…" /></Field>
              <Field label="BKS ra"><Combo value={ship.bksRa} onChange={(x) => set({ bksRa: x })} options={cfg.vehicles || []} onCreate={(v) => add("vehicles", v)} placeholder="15C-678.90…" /></Field>
            </div>
          </>
        )}
      </Section>

      <div style={{ borderTop: "1px solid var(--line)" }}>
        <button type="button" onClick={() => setHqOpen((o) => !o)}
          style={{ width: "100%", display: "flex", alignItems: "center", gap: 9, padding: "13px 0", background: "none", border: "none", cursor: "pointer", textAlign: "left" }}>
          <span style={{ color: "var(--ink-4)", display: "inline-flex", transform: hqOpen ? "rotate(0deg)" : "rotate(-90deg)", transition: "transform .15s" }}><I.chev /></span>
          <span style={{ fontSize: 13.5, fontWeight: 600, color: "var(--ink-2)", letterSpacing: ".01em" }}>Hải Quan</span>
          {hqFilled > 0 && <span style={{ fontSize: 11.5, fontWeight: 600, color: "var(--accent)", background: "var(--accent-weak)", padding: "3px 9px", borderRadius: 999 }}>{hqFilled} mục</span>}
          {!hqOpen && <span style={{ fontSize: 11.5, color: "var(--ink-4)" }}>Số tờ khai, ngày thanh lý, cơ sở hạ tầng…</span>}
        </button>
        {hqOpen && (
          <div style={{ padding: "0 0 14px" }}>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
              <Field label="Số tờ khai"><Txt value={ship.declNo} onChange={(x) => set({ declNo: x })} placeholder="VD: 103456789012" /></Field>
              <Field label="Ngày thanh lý"><DateField value={ship.thanhLy} onChange={(x) => set({ thanhLy: x })} /></Field>
            </div>
            <div style={{ marginTop: 12, maxWidth: 240 }}>
              <Field label="Phí thanh lý tờ khai" hint="link sang Chi phí"><Money value={tlLine ? tlLine.amount : ""} onChange={(x) => setTlFee(x)} dim /></Field>
            </div>
            <div style={{ marginTop: 12 }}>
              <Field label="Ghi chú tờ khai">
                <textarea value={ship.declNote || ""} onChange={(e) => set({ declNote: e.target.value })} placeholder="Ghi chú liên quan tờ khai hải quan…" rows={2}
                  style={{ width: "100%", padding: "8px 11px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 9, outline: "none", resize: "vertical", fontFamily: "inherit" }}
                  onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
              </Field>
            </div>
            <div style={{ marginTop: 12 }}>
              <Field label="Cơ sở hạ tầng (ghi chú)">
                <textarea value={ship.cshtNote || ""} onChange={(e) => set({ cshtNote: e.target.value })} placeholder="Ghi chú phí/biên lai cơ sở hạ tầng cảng…" rows={2}
                  style={{ width: "100%", padding: "8px 11px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 9, outline: "none", resize: "vertical", fontFamily: "inherit" }}
                  onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
              </Field>
            </div>
          </div>
        )}
      </div>

      <Section title="Thuê xe ngoài">
        <div style={{ padding: "8px 0 2px" }}>
          <ChkBox checked={extHired} onChange={toggleExt} label="Có thuê xe ngoài cho lô này" />
        </div>
        {extHired && (
          <>
            <div style={{ display: "grid", gridTemplateColumns: "220px 1fr", gap: 12, padding: "10px 0 4px", alignItems: "end" }}>
              <Field label="Số tiền (cước xe ngoài)"><Money value={extLine.amount} onChange={(x) => setExt({ amount: x })} dim /></Field>
              <Field label="Ghi chú thông tin nhà xe"><Txt value={extLine.note} onChange={(x) => setExt({ note: x })} placeholder="Tên nhà xe, SĐT, biển số…" /></Field>
            </div>
            <div style={{ fontSize: 11.5, color: "var(--ink-4)", padding: "2px 0 6px", display: "flex", alignItems: "center", gap: 6, lineHeight: 1.5 }}>
              <I.link /> Số tiền này là khoản <b style={{ color: "var(--ink-3)" }}>“Cước xe ngoài”</b> trong <b style={{ color: "var(--ink-3)" }}>Chi phí lô hàng</b> — kế toán sửa được ở đó nhưng không xóa được. Bỏ tích ở đây để gỡ khoản này.
            </div>
          </>
        )}
      </Section>

      <Section title="Tuyến" >
        <div style={{ fontSize: 11.5, color: "var(--ink-4)", padding: "6px 0 0" }}>Địa điểm lấy từ <b style={{ color: "var(--ink-3)" }}>danh mục Địa điểm</b> — gõ để tìm hoặc thêm mới.</div>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 36px 1fr", gap: 10, alignItems: "end", padding: "8px 0 10px" }}>
          <Field label="Nơi lấy"><Combo value={ship.from} onChange={(x) => set({ from: x })} options={cfg.locations || []} onCreate={(v) => add("locations", v)} placeholder="Điểm lấy cont…" clearable /></Field>
          <div style={{ display: "grid", placeItems: "center", color: "var(--accent)", paddingBottom: 9 }}><I.arrow /></div>
          <Field label="Nơi hạ"><Combo value={ship.to} onChange={(x) => set({ to: x })} options={cfg.locations || []} onCreate={(v) => add("locations", v)} placeholder="Điểm hạ cont…" clearable /></Field>
        </div>
      </Section>

      <Section title="Lịch trình">
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, padding: "10px 0" }}>
          {isHph ? (
            <>
              <Field label="Ngày tàu chạy"><DateField value={ship.sailDate} onChange={(x) => set({ sailDate: x })} /></Field>
              <Field label="Cắt máng"><Txt value={ship.cutOff} onChange={(x) => set({ cutOff: x })} placeholder="18/06 14:00" /></Field>
            </>
          ) : (
            <>
              <Field label="Cắt máng" hint="ngày giờ"><DTField value={ship.cutOff} onChange={(x) => set({ cutOff: x })} /></Field>
              <Field label="Ngày cont đến"><DateField value={ship.contDen} onChange={(x) => set({ contDen: x })} /></Field>
              <Field label="Ngày cont ra"><DateField value={ship.contRa} onChange={(x) => set({ contRa: x })} /></Field>
            </>
          )}
        </div>
      </Section>

      {!isHph && (() => {
        // Khi "cont khác ra": dùng giờ xe ra của cont kia để tính free time của chuyến.
        const effective = other ? { ...ship, gioXeRa: otherGioXeRa } : ship;
        const ft = calcFreeTime(effective, (cfg.freeTimeHours == null ? "4" : cfg.freeTimeHours));
        return (
          <Section title="Free time & kết nối">
            <div style={{ fontSize: 11.5, color: "var(--ink-4)", padding: "2px 0 6px" }}>Free time = Giờ xe ra − (Giờ đến kế hoạch hoặc Giờ xe đến, lấy giờ muộn hơn). Ngưỡng <b style={{ color: "var(--ink-3)" }}>{ft ? ft.threshold : (cfg.freeTimeHours || 4)}h</b> chỉnh trong Cấu hình. Có thể để trống nếu chưa có giờ.</div>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 12, padding: "4px 0 6px" }}>
              <Field label="Giờ đến kế hoạch"><DTField value={ship.gioDenDuKien} onChange={(x) => set({ gioDenDuKien: x })} /></Field>
              <Field label="Giờ xe đến"><DTField value={ship.gioXeDen} onChange={(x) => set({ gioXeDen: x })} /></Field>
              <Field label="Giờ xe ra">{raMode === "other"
                ? <div style={{ padding: "9px 11px", fontSize: 13, border: "1px dashed var(--line)", borderRadius: 9, background: "#fafbfc", color: "var(--ink-4)" }} title="Cont này không tự ra — giờ xe ra ghi vào cont đã chọn ở dưới">{otherGioXeRa ? new Date(otherGioXeRa).toLocaleString("vi-VN", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" }) : "Cont này không tự ra"}</div>
                : <DTField value={ship.gioXeRa} onChange={(x) => setRa(x)} />}</Field>
            </div>
            <div style={{ background: "var(--accent-weak-2)", border: "1px solid var(--accent-weak)", borderRadius: 10, padding: "10px 12px", margin: "2px 0 12px" }}>
              <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 7, fontWeight: 500 }}>Giờ xe ra này là của:</div>
              <div style={{ display: "flex", alignItems: "center", gap: 12, flexWrap: "wrap" }}>
                <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 8, padding: 2 }}>
                  {[["self", "Chính cont này"], ["other", "Cont khác ra"]].map(([k, lbl]) => {
                    const on = raMode === k;
                    return (
                      <button key={k} type="button" onClick={() => set({ raMode: k })}
                        style={{ border: "none", cursor: "pointer", fontSize: 12.5, fontWeight: 600, padding: "6px 13px", borderRadius: 6, whiteSpace: "nowrap",
                          background: on ? "#fff" : "transparent", color: on ? "var(--accent)" : "var(--ink-3)", boxShadow: on ? "0 1px 2px rgba(16,19,23,.12)" : "none", transition: "all .12s" }}>
                        {lbl}
                      </button>
                    );
                  })}
                </div>
                {raMode === "other" && (
                  <div style={{ flex: 1, minWidth: 240 }}>
                    <Combo value={ship.raOtherId != null ? (sibOpts.find((o) => o.value === ship.raOtherId) || {}).label : ""}
                      options={sibOpts.map((o) => o.label)}
                      onChange={(label) => { const opt = sibOpts.find((o) => o.label === label); set({ raOtherId: opt ? opt.value : null }); }}
                      placeholder="Chọn cont ra cùng chuyến…" small />
                  </div>
                )}
              </div>
              {raMode === "other" && (
                ship.raOtherId != null ? (
                  <div style={{ marginTop: 10 }}>
                    <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>Giờ ra & biển số của <b style={{ color: "var(--ink-2)" }}>{(sibOpts.find((o) => o.value === ship.raOtherId) || {}).label}</b></div>
                    <div style={{ display: "flex", alignItems: "center", gap: 10, flexWrap: "wrap" }}>
                      <div style={{ width: 220 }}><DTField value={otherGioXeRa} onChange={(x) => setRa(x)} /></div>
                      <div style={{ width: 190 }}>
                        <Combo value={otherBksRa} onChange={(x) => setRaBks(x)} options={cfg.vehicles || []} onCreate={(x) => add("vehicles", x)} placeholder="BKS ra…" small />
                      </div>
                    </div>
                    <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 7, lineHeight: 1.5 }}>
                      Nhập <b style={{ color: "var(--ink-3)" }}>giờ ra</b> và <b style={{ color: "var(--ink-3)" }}>biển số</b> ở đây chỉ cập nhật cho cont đã chọn (cont thực sự rời đi). Cont hiện tại giữ <b style={{ color: "var(--ink-3)" }}>trống</b> giờ xe ra. Thay đổi sẽ lưu khi bấm <b style={{ color: "var(--ink-3)" }}>Lưu thông tin</b>.
                    </div>
                  </div>
                ) : (
                  <div style={{ fontSize: 11.5, color: "var(--warn)", marginTop: 8, fontWeight: 500 }}>Chọn cont ra cùng chuyến để nhập giờ ra cập nhật cho cont đó.</div>
                )
              )}
            </div>
            <div style={{ display: "flex", alignItems: "center", gap: 14, padding: "4px 0 4px" }}>
              <div style={{ display: "flex", alignItems: "baseline", gap: 8 }}>
                <span style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Free time</span>
                <span className="tnum" style={{ fontSize: 20, fontWeight: 700 }}>{ft ? fmtHours(ft.hours) : "—"}</span>
                {ft && <span style={{ fontSize: 12, color: "var(--ink-4)" }}>(tính từ {ft.basis})</span>}
              </div>
              <div style={{ flex: 1 }} />
              {ft && (
                <span style={{ display: "inline-flex", alignItems: "center", gap: 7, fontSize: 13.5, fontWeight: 700, padding: "6px 14px", borderRadius: 999,
                  color: ft.connect ? "var(--good)" : "var(--danger)", background: ft.connect ? "var(--good-weak)" : "#fce8e8" }}>
                  <span style={{ width: 8, height: 8, borderRadius: 999, background: "currentColor" }} />
                  {ft.connect ? "CONNECT" : "DISCONNECT"}
                </span>
              )}
            </div>
          </Section>
        );
      })()}
    </Modal>
  );
}

/* ===================== CONFIG (master data) POPUP ===================== */
const CFG_GROUPS = [
  { key: "locations", label: "Địa điểm", hint: "depot, cảng, ICD, KCN — dùng cho Tuyến · thêm ký hiệu viết tắt; tự thêm khi import bảng giá (cột FROM + TO)", ph: "VD: Cảng Tân Vũ", coded: true, codeKey: "locationCode", codeNameLabel: "Tên địa điểm" },
  { key: "customers", label: "Khách hàng", hint: "quản lý khách hàng — MST, liên hệ, hạn thanh toán, ghi chú…", ph: "VD: Canon Vietnam" },
  { key: "contTypes", label: "Loại container", hint: "dùng cho cột Cont", ph: "VD: 40HC" },
  { key: "warehouses", label: "Kho", hint: "kho hàng — dùng cho lô (chọn tối đa 3) · thêm ký hiệu viết tắt; tự thêm khi import bảng giá (cột TO)", ph: "VD: Kho A2", coded: true, codeKey: "warehouseCode", codeNameLabel: "Tên kho" },
  { key: "payers", label: "Bên thanh toán", hint: "dùng cho mọi dòng chi phí", ph: "VD: Tài xế" },
  { key: "costItems", label: "Khoản chi phí", hint: "gắn màu “theo dõi” cho khoản cần nhắc khi chưa điền số tiền — dùng chung cho mọi lô", ph: "VD: Phí cân xe", colored: true },
  { key: "choHoItems", label: "Khoản thu/chi hộ", hint: "dùng cho mục Thu chi hộ ở cả Chi phí & Doanh thu · có đơn giá mặc định", ph: "VD: Nâng", priced: true },
  { key: "revItems", label: "Khoản doanh thu", hint: "dùng cho mục Doanh thu · có đơn giá mặc định", ph: "VD: Doanh thu cước xe", priced: true },
  { key: "vehicles", label: "Biển số xe", hint: "đội xe — mỗi biển số chọn Xe MBF hay Xe ngoài", ph: "VD: 15C-123.45", fleet: true },
  { key: "drivers", label: "Lái xe", hint: "tài xế — dùng cho tab Xe MBF", ph: "VD: A.Tuấn" },
  { key: "vehItems", label: "Chi phí xe", hint: "khoản chi phí chuyến xe MBF · có đơn giá mặc định", ph: "VD: Vé trạm", priced: true },
  { key: "__vat", label: "VAT mặc định", hint: "tỷ lệ VAT gợi ý cho lô mới — kế toán vẫn sửa được từng lô", vat: true },
  { key: "__freetime", label: "Free time / Kết nối", hint: "ngưỡng giờ để quyết định CONNECT / DISCONNECT (sheet ICD)", freetime: true },
];
/* ===================== CUSTOMER MANAGER (master-detail) ===================== */
const CUST_FIELDS = [
  { k: "shortName", label: "Tên viết tắt", ph: "VD: Canon" },
  { k: "taxCode", label: "Mã số thuế", ph: "VD: 0101234567" },
  { k: "phone", label: "Điện thoại", ph: "VD: 024 1234 5678" },
  { k: "contact", label: "Người liên hệ", ph: "VD: Chị Hồng — KT" },
  { k: "email", label: "Email", ph: "VD: ketoan@canon.vn" },
];
function PriceList({ rows = [], onChange, onImported, cfg = {}, customer }) {
  const T = window.__TRK || {}; const ROUTES = T.routes || {};
  const [imp, setImp] = useState(null);   // {names:[], wb} sau khi đọc file
  const [sheet, setSheet] = useState("");
  const [openKind, setOpenKind] = useState(null); // KIND đang mở (accordion); null = thu hết
  const [query, setQuery] = useState("");          // ô tra cứu tuyến
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState("");
  const fileRef = React.useRef(null);
  // Gộp danh mục địa điểm + mọi "Điểm Hạ" đang có trong bảng giá (kể cả ký hiệu mới import) để select luôn hiển thị
  const locOpts = [...new Set([...(cfg.locations || []), ...rows.map((r) => r.loc).filter(Boolean)])];
  const blank = { distance: "", transFee40: "", transFee20: "", fuelFee40: "", fuelFee20: "" };
  const set = (id, np) => onChange(rows.map((e) => (e.id === id ? { ...e, ...np } : e)));
  const del = (id) => onChange(rows.filter((e) => e.id !== id));

  // ---- Import Excel: đọc file → chọn sheet → parse → gọi API upsert ----
  const onFile = (e) => {
    const f = e.target.files && e.target.files[0]; e.target.value = "";
    if (!f) return;
    if (typeof XLSX === "undefined") { setMsg("Thư viện Excel chưa tải xong, thử lại."); return; }
    setMsg("");
    const rd = new FileReader();
    rd.onload = () => { const wb = XLSX.read(rd.result, { type: "array" }); setImp({ names: wb.SheetNames, wb }); setSheet(wb.SheetNames[0] || ""); };
    rd.readAsArrayBuffer(f);
  };
  const norm = (s) => String(s == null ? "" : s).trim().toLowerCase().replace(/\s+/g, " ");
  const doImport = async () => {
    if (!imp || !sheet) return;
    if (!customer) { setMsg("Chọn một khách hàng trước khi import."); return; }
    setBusy(true); setMsg("");
    const aoa = XLSX.utils.sheet_to_json(imp.wb.Sheets[sheet], { header: 1, raw: true, defval: "" });
    let hi = aoa.findIndex((r) => (r || []).some((c) => norm(c) === "loại" || norm(c) === "điểm hạ"));
    if (hi < 0) hi = 0;
    const header = (aoa[hi] || []).map(norm);
    const idx = (...names) => { for (const n of names) { const i = header.indexOf(norm(n)); if (i >= 0) return i; } return -1; };
    const C = { conn: idx("Loại"), loc: idx("Điểm Hạ"), kind: idx("KIND"), from: idx("FROM"), to1: idx("TO 1", "TO1"), to2: idx("TO 2", "TO2"), to3: idx("TO 3", "TO3"), to4: idx("TO 4", "TO4"), distance: idx("Distance (km)", "Distance", "KM"), transFee40: idx("Transport fee 40FT", "Transport fee 40"), transFee20: idx("Transport fee 20FT", "Transport fee 20"), fuelFee40: idx("Fuel fee 40FT", "Fuel fee 40"), fuelFee20: idx("Fuel fee 20FT", "Fuel fee 20") };
    const num = (v) => String(v == null ? "" : v).replace(/[^\d]/g, "");
    const txt = (v) => String(v == null ? "" : v).trim();
    const out = [];
    for (let r = hi + 1; r < aoa.length; r++) {
      const row = aoa[r] || []; const g = (i) => (i >= 0 ? row[i] : "");
      const connRaw = txt(g(C.conn)).toUpperCase(); const loc = txt(g(C.loc));
      const from = txt(g(C.from));
      if (!connRaw && !loc && !from) continue;
      const conn = connRaw.includes("DISCON") ? "Disconnect" : (connRaw.includes("CON") ? "Connect" : (connRaw || "Connect"));
      out.push({ conn, loc, kind: txt(g(C.kind)), from, to1: txt(g(C.to1)), to2: txt(g(C.to2)), to3: txt(g(C.to3)), to4: txt(g(C.to4)), distance: num(g(C.distance)), transFee40: num(g(C.transFee40)), transFee20: num(g(C.transFee20)), fuelFee40: num(g(C.fuelFee40)), fuelFee20: num(g(C.fuelFee20)) });
    }
    if (!out.length) { setBusy(false); setMsg("Sheet không có dòng dữ liệu hợp lệ."); return; }
    try {
      const res = await fetch(ROUTES.priceImport, { method: "POST", headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": T.csrf }, body: JSON.stringify({ customer, rows: out }) }).then((r) => r.json());
      setBusy(false);
      if (res && res.ok) { (onImported || onChange)(res.priceList || []); setImp(null); setMsg(`Đã import ${res.imported} dòng — ${res.created} mới, ${res.updated} cập nhật.`); }
      else { setMsg("Import lỗi: " + ((res && res.message) || "không rõ")); }
    } catch (err) { setBusy(false); setMsg("Import lỗi kết nối."); }
  };

  // ---- Xóa toàn bộ bảng giá của khách (để import lại) — hỏi xác nhận ----
  const clearAll = async () => {
    if (!customer || !rows.length) return;
    const esc = (s) => String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
    const ok = await window.confirmAction({
      title: "Xóa toàn bộ bảng giá?",
      text: `Toàn bộ <b>${rows.length}</b> dòng bảng giá của khách <b>${esc(customer)}</b> sẽ bị xóa để import lại. Không thể hoàn tác.`,
      confirmText: '<i class="bi bi-trash me-1"></i> Xóa toàn bộ',
      danger: true,
    });
    if (!ok) return;
    setBusy(true); setMsg("");
    try {
      const res = await fetch(ROUTES.priceImport, { method: "POST", headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": T.csrf }, body: JSON.stringify({ customer, rows: [], replace: true }) }).then((r) => r.json());
      setBusy(false);
      if (res && res.ok) { (onImported || onChange)(res.priceList || []); setMsg("Đã xóa toàn bộ bảng giá. Bấm Import Excel để nạp lại."); }
      else setMsg("Xóa lỗi: " + ((res && res.message) || "không rõ"));
    } catch (err) { setBusy(false); setMsg("Xóa lỗi kết nối."); }
  };

  // ---- top group = Địa điểm (location) ; each loc-group has its own conn (Connect/Disconnect) ----
  const locKey = (r) => (r.loc || "") + "¦" + (r.conn || "Connect");
  const locGroups = [];
  rows.forEach((r) => { const k = locKey(r); if (!locGroups.some((g) => g.key === k)) locGroups.push({ key: k, loc: r.loc || "", conn: r.conn || "Connect" }); });
  if (!locGroups.length) locGroups.push({ key: "¦Connect", loc: "", conn: "Connect" });
  const setLocField = (oldKey, np) => onChange(rows.map((r) => (locKey(r) === oldKey ? { ...r, ...np } : r)));
  const addLoc = () => onChange([...rows, { id: Date.now() + Math.random(), loc: "", conn: "Connect", kind: "Chưa phân nhóm", from: "", to1: "", to2: "", to3: "", to4: "", ...blank }]);
  // kinds within a loc-group
  const kindsIn = (g) => { const out = []; rows.filter((r) => locKey(r) === g.key).forEach((r) => { const k = r.kind || "Chưa phân nhóm"; if (!out.includes(k)) out.push(k); }); return out.length ? out : ["Chưa phân nhóm"]; };
  const renameKind = (gKey, oldK, newK) => onChange(rows.map((r) => (locKey(r) === gKey && (r.kind || "Chưa phân nhóm") === oldK ? { ...r, kind: newK || "Chưa phân nhóm" } : r)));
  const addRowTo = (g, k) => onChange([...rows, { id: Date.now() + Math.random(), loc: g.loc, conn: g.conn, kind: k, from: "", to1: "", to2: "", to3: "", to4: "", ...blank }]);
  const addKind = (g) => { const ks = kindsIn(g); const base = "Nhóm mới"; let n = base, i = 1; while (ks.includes(n)) n = base + " " + (++i); onChange([...rows, { id: Date.now() + Math.random(), loc: g.loc, conn: g.conn, kind: n, from: "", to1: "", to2: "", to3: "", to4: "", ...blank }]); };
  // Tra cứu: lọc dòng theo điểm hạ / FROM / TO / KIND
  const ql = (query || "").trim().toLowerCase();
  const matchRow = (r) => !ql || [r.from, r.to1, r.to2, r.to3, r.to4, r.kind, r.loc, r.conn].filter(Boolean).join(" ").toLowerCase().includes(ql);
  const matchCount = ql ? rows.filter(matchRow).length : 0;
  const cols = "46px 46px 46px 46px 46px 52px 1fr 1fr 1fr 1fr 24px";
  const cell = (val, onCh, ph, opt) => (
    <input value={val || ""} onChange={(e) => onCh(opt && opt.num ? e.target.value.replace(/[^\d]/g, "") : e.target.value)} placeholder={ph}
      className={opt && (opt.num || opt.money) ? "tnum" : ""}
      style={{ width: "100%", padding: "6px 7px", fontSize: 12, textAlign: opt && (opt.num || opt.money) ? "right" : "center", border: "1px solid var(--line)", borderRadius: 7, outline: "none", background: "#fff", fontWeight: opt && opt.money ? 600 : 400 }}
      onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
  );
  const moneyCell = (val, onCh, ph) => {
    const grp = (d) => { d = (d || "").toString().replace(/[^\d]/g, ""); return d ? d.replace(/\B(?=(\d{3})+(?!\d))/g, ".") : ""; };
    return (
      <div style={{ position: "relative" }}>
        <input value={grp(val)} onChange={(e) => onCh(e.target.value.replace(/[^\d]/g, ""))} placeholder={ph} inputMode="numeric" className="tnum"
          style={{ width: "100%", padding: "6px 20px 6px 7px", fontSize: 12, textAlign: "right", fontWeight: 600, border: "1px solid var(--line)", borderRadius: 7, outline: "none", background: "#fff" }}
          onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
        <span style={{ position: "absolute", right: 7, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 11, pointerEvents: "none" }}>₫</span>
      </div>
    );
  };
  const colHeader = (
    <>
      <div style={{ display: "grid", gridTemplateColumns: cols, gap: 6, padding: "0 0 4px" }}>
        <div style={{ gridColumn: "1 / 6", textAlign: "center", fontSize: 10.5, fontWeight: 700, color: "var(--accent)", textTransform: "uppercase", letterSpacing: "0.04em", background: "var(--accent-weak)", borderRadius: 6, padding: "3px 0" }}>Routing</div>
        <div /><div /><div /><div />
      </div>
      <div style={{ display: "grid", gridTemplateColumns: cols, gap: 6, padding: "0 0 5px" }}>
        {["FROM", "TO", "TO", "TO", "TO", "KM", "Cước 40FT", "Cước 20FT", "Dầu 40FT", "Dầu 20FT", ""].map((h, i) => (
          <div key={i} style={{ fontSize: 10, fontWeight: 600, color: "var(--ink-4)", textTransform: "uppercase", letterSpacing: "0.02em", textAlign: i >= 6 && i <= 9 ? "right" : "center" }}>{h}</div>
        ))}
      </div>
    </>
  );
  const rowEl = (e) => (
    <div key={e.id} style={{ display: "grid", gridTemplateColumns: cols, gap: 6, alignItems: "center" }}>
      {cell(e.from, (v) => set(e.id, { from: v }), "HPP")}
      {cell(e.to1, (v) => set(e.id, { to1: v }), "TL")}
      {cell(e.to2, (v) => set(e.id, { to2: v }), "–")}
      {cell(e.to3, (v) => set(e.id, { to3: v }), "–")}
      {cell(e.to4, (v) => set(e.id, { to4: v }), "HPP")}
      {cell(e.distance, (v) => set(e.id, { distance: v }), "0", { num: true })}
      {moneyCell(e.transFee40, (v) => set(e.id, { transFee40: v }), "0")}
      {moneyCell(e.transFee20, (v) => set(e.id, { transFee20: v }), "0")}
      {moneyCell(e.fuelFee40, (v) => set(e.id, { fuelFee40: v }), "0")}
      {moneyCell(e.fuelFee20, (v) => set(e.id, { fuelFee20: v }), "0")}
      <button type="button" onClick={() => del(e.id)} title="Xóa"
        style={{ width: 26, height: 26, display: "grid", placeItems: "center", border: "none", borderRadius: 6, background: "transparent", color: "var(--ink-4)", cursor: "pointer" }}
        onMouseEnter={(ev) => { ev.currentTarget.style.background = "#fce8e8"; ev.currentTarget.style.color = "var(--danger)"; }}
        onMouseLeave={(ev) => { ev.currentTarget.style.background = "transparent"; ev.currentTarget.style.color = "var(--ink-4)"; }}><I.trash /></button>
    </div>
  );
  return (
    <div>
      <div style={{ position: "relative", marginBottom: 10 }}>
        <span style={{ position: "absolute", left: 11, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)" }}><I.search /></span>
        <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Tra cứu tuyến: điểm hạ, FROM, TO, KIND…"
          style={{ width: "100%", padding: "9px 32px 9px 34px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 10, outline: "none", background: "#fafbfc" }}
          onFocus={(e) => { e.target.style.borderColor = "var(--accent)"; e.target.style.background = "#fff"; }}
          onBlur={(e) => { e.target.style.borderColor = "var(--line)"; e.target.style.background = "#fafbfc"; }} />
        {query && <button type="button" onClick={() => setQuery("")} title="Xóa tìm" style={{ position: "absolute", right: 8, top: "50%", transform: "translateY(-50%)", width: 22, height: 22, display: "grid", placeItems: "center", border: "none", borderRadius: 6, background: "var(--line-2)", color: "var(--ink-3)", cursor: "pointer" }}><I.x /></button>}
      </div>
      {ql && <div style={{ fontSize: 12, color: matchCount ? "var(--ink-4)" : "var(--danger)", marginBottom: 8 }}>{matchCount} dòng khớp “{query.trim()}”{matchCount === 0 ? " — không tìm thấy tuyến nào." : ""}</div>}
      {colHeader}
      <div style={{ maxHeight: "62vh", overflowY: "auto", display: "flex", flexDirection: "column", gap: 18 }}>
        {locGroups.map((g) => {
          if (ql && !rows.some((r) => locKey(r) === g.key && matchRow(r))) return null;
          return (
          <div key={g.key} style={{ border: "1px solid var(--line)", borderRadius: 12, padding: "12px 12px 10px", background: "#fcfcfd" }}>
            {/* location super-header: location select + connect/disconnect select */}
            <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 10, flexWrap: "wrap" }}>
              <span style={{ fontSize: 10.5, fontWeight: 700, color: "var(--ink-4)", textTransform: "uppercase", letterSpacing: "0.04em" }}>Địa điểm hạ</span>
              <div style={{ position: "relative", minWidth: 200 }}>
                <select value={g.loc} onChange={(e) => setLocField(g.key, { loc: e.target.value })}
                  style={{ width: "100%", appearance: "none", WebkitAppearance: "none", padding: "8px 28px 8px 11px", fontSize: 13.5, fontWeight: 700, color: g.loc ? "var(--ink)" : "var(--ink-4)", background: "#fff", border: "1px solid var(--line)", borderRadius: 9, cursor: "pointer" }}>
                  <option value="">— Chọn địa điểm hạ —</option>
                  {locOpts.map((l) => <option key={l} value={l}>{l}</option>)}
                </select>
                <span style={{ position: "absolute", right: 9, top: "50%", transform: "translateY(-50%)", color: "var(--ink-3)", pointerEvents: "none" }}><I.chev /></span>
              </div>
              <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 9, padding: 3 }}>
                {["Connect", "Disconnect"].map((opt) => {
                  const on = g.conn === opt;
                  return (
                    <button key={opt} type="button" onClick={() => setLocField(g.key, { conn: opt })}
                      style={{ border: "none", cursor: "pointer", fontSize: 12.5, fontWeight: 600, padding: "6px 14px", borderRadius: 7,
                        background: on ? "#fff" : "transparent", color: on ? (opt === "Connect" ? "var(--good)" : "var(--danger)") : "var(--ink-3)", boxShadow: on ? "0 1px 2px rgba(16,19,23,.12)" : "none", transition: "all .12s" }}>
                      {opt}
                    </button>
                  );
                })}
              </div>
              <div style={{ flex: 1 }} />
              {locGroups.length > 1 && (
                <button type="button" onClick={() => onChange(rows.filter((r) => locKey(r) !== g.key))} title="Xóa nhóm địa điểm hạ"
                  style={{ width: 28, height: 28, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 7, background: "#fff", color: "var(--ink-4)", cursor: "pointer" }}
                  onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
                  onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-4)"; }}><I.trash /></button>
              )}
            </div>
            {/* KIND subgroups — accordion: mặc định thu gọn, mở 1 thu cái khác */}
            <div style={{ display: "flex", flexDirection: "column", gap: 8, paddingLeft: 4 }}>
              {kindsIn(g).map((k) => {
                const kkey = g.key + "¦" + k;
                const kRows = rows.filter((r) => locKey(r) === g.key && (r.kind || "Chưa phân nhóm") === k);
                const shown = ql ? kRows.filter(matchRow) : kRows;
                if (ql && !shown.length) return null;
                const open = ql ? true : (openKind === kkey);
                return (
                <div key={k} style={{ border: "1px solid var(--line-2)", borderRadius: 9, overflow: "hidden", background: "#fff" }}>
                  <div onClick={() => { if (!ql) setOpenKind(open ? null : kkey); }}
                    style={{ display: "flex", alignItems: "center", gap: 8, padding: "7px 9px", cursor: ql ? "default" : "pointer", background: open ? "var(--accent-weak-2)" : "#fff" }}>
                    <span style={{ display: "grid", placeItems: "center", color: "var(--ink-3)", transform: `rotate(${open ? 0 : -90}deg)`, transition: "transform .12s" }}><I.chev /></span>
                    <span style={{ width: 4, height: 15, borderRadius: 2, background: "var(--accent)" }} />
                    <input value={k === "Chưa phân nhóm" ? "" : k} onClick={(e) => e.stopPropagation()} onChange={(e) => renameKind(g.key, k, e.target.value)} placeholder="Tên KIND / nhóm…"
                      style={{ flex: 1, padding: "4px 8px", fontSize: 13, fontWeight: 700, color: "var(--ink)", border: "1px solid transparent", borderRadius: 7, outline: "none", background: "transparent" }}
                      onFocus={(e) => { e.target.style.borderColor = "var(--accent)"; e.target.style.background = "#fff"; }}
                      onBlur={(e) => { e.target.style.borderColor = "transparent"; e.target.style.background = "transparent"; }} />
                    <span className="tnum" style={{ fontSize: 11, fontWeight: 600, color: "var(--ink-4)", background: "var(--line-2)", padding: "1px 8px", borderRadius: 999 }}>{ql ? `${shown.length}/${kRows.length}` : kRows.length} tuyến</span>
                  </div>
                  {open && (
                    <div style={{ padding: "6px 9px 9px" }}>
                      <div style={{ display: "flex", flexDirection: "column", gap: 4 }}>{shown.map(rowEl)}</div>
                      {!ql && (
                        <button type="button" onClick={() => addRowTo(g, k)}
                          style={{ display: "inline-flex", alignItems: "center", gap: 6, marginTop: 6, padding: "5px 10px", fontSize: 12, fontWeight: 600, border: "none", borderRadius: 7, background: "var(--accent-weak)", color: "var(--accent)", cursor: "pointer" }}>
                          <I.plus /> Thêm dòng
                        </button>
                      )}
                    </div>
                  )}
                </div>
              ); })}
            </div>
            {!ql && (
              <button type="button" onClick={() => addKind(g)}
                style={{ display: "inline-flex", alignItems: "center", gap: 6, marginTop: 10, padding: "5px 10px", fontSize: 12, fontWeight: 600, border: "1px dashed var(--line)", borderRadius: 7, background: "#fff", color: "var(--ink-2)", cursor: "pointer" }}>
                <I.plus /> Thêm nhóm KIND
              </button>
            )}
          </div>
        ); })}
        {!rows.length && !imp && <div style={{ padding: "10px 2px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có dòng báo giá — bấm <b>Import Excel</b> để nạp từ file, hoặc thêm nhóm địa điểm hạ thủ công.</div>}
      </div>

      <input ref={fileRef} type="file" accept=".xlsx,.xls" onChange={onFile} style={{ display: "none" }} />

      {imp ? (
        <div style={{ marginTop: 12, padding: "12px 14px", border: "1px solid var(--accent-weak)", background: "var(--accent-weak-2)", borderRadius: 10 }}>
          <div style={{ fontSize: 12.5, fontWeight: 600, color: "var(--ink-2)", marginBottom: 8 }}>Chọn sheet để import</div>
          <div style={{ display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap" }}>
            <div style={{ position: "relative", minWidth: 240 }}>
              <select value={sheet} onChange={(e) => setSheet(e.target.value)}
                style={{ width: "100%", appearance: "none", WebkitAppearance: "none", padding: "8px 28px 8px 11px", fontSize: 13, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 9, background: "#fff", cursor: "pointer" }}>
                {imp.names.map((n) => <option key={n} value={n}>{n}</option>)}
              </select>
              <span style={{ position: "absolute", right: 9, top: "50%", transform: "translateY(-50%)", color: "var(--ink-3)", pointerEvents: "none" }}><I.chev /></span>
            </div>
            <Btn variant="primary" onClick={doImport}>{busy ? "Đang import…" : "Import sheet này"}</Btn>
            <Btn onClick={() => { setImp(null); setMsg(""); }}>Hủy</Btn>
          </div>
          <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 8, lineHeight: 1.5 }}>
            Cột nhận dạng theo tiêu đề: <b>Loại · Điểm Hạ · KIND · FROM · TO 1..4 · Distance (km) · Transport fee 40FT/20FT · Fuel fee 40FT/20FT</b>.
            Trùng tuyến (Loại+Điểm Hạ+KIND+FROM+TO) sẽ cập nhật giá; chưa có thì tạo mới. Ký hiệu ở <b>FROM</b> và <b>TO</b> tự thêm vào danh mục <b>Địa điểm</b>; các <b>TO</b> đồng thời thêm vào danh mục <b>Kho</b>.
          </div>
        </div>
      ) : (
        <div style={{ display: "flex", gap: 8, marginTop: 12, paddingTop: 12, borderTop: "1px solid var(--line-2)", alignItems: "center", flexWrap: "wrap" }}>
          <button type="button" onClick={() => fileRef.current && fileRef.current.click()}
            style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 13px", fontSize: 12.5, fontWeight: 600, border: "none", borderRadius: 8, background: "var(--accent)", color: "#fff", cursor: "pointer" }}>
            <I.plus /> Import Excel
          </button>
          <button type="button" onClick={addLoc}
            style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 13px", fontSize: 12.5, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-2)", cursor: "pointer" }}>
            <I.plus /> Thêm nhóm địa điểm hạ
          </button>
          {rows.length > 0 && (
            <button type="button" onClick={clearAll} disabled={busy}
              style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 13px", fontSize: 12.5, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-3)", cursor: "pointer" }}
              onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; e.currentTarget.style.borderColor = "#f3c9c9"; }}
              onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-3)"; e.currentTarget.style.borderColor = "var(--line)"; }}>
              <I.trash /> Xóa toàn bộ bảng giá
            </button>
          )}
          {msg && <span style={{ fontSize: 12, fontWeight: 600, color: (msg.startsWith("Đã import") || msg.startsWith("Đã xóa")) ? "var(--good)" : "var(--danger)" }}>{msg}</span>}
        </div>
      )}
    </div>
  );
}
function CustomerManager({ cfg, setCfg }) {
  const customers = cfg.customers || [];
  const info = cfg.customerInfo || {};
  const [sel, setSel] = useState(customers[0] || null);
  const [draft, setDraft] = useState("");
  const cur = sel != null && customers.includes(sel) ? sel : (customers[0] || null);
  const data = (cur && info[cur]) || {};
  const setField = (k, v) => setCfg("customerInfo", { ...info, [cur]: { ...data, [k]: v } });
  const T = window.__TRK || {}; const ROUTES = T.routes || {};
  const [nameDraft, setNameDraft] = useState(cur || "");
  React.useEffect(() => { setNameDraft(cur || ""); }, [cur]);
  // Đổi tên khách (server update theo id — giữ liên kết lô & bảng giá), rồi rekey cfg cục bộ
  const renameCustomer = async () => {
    const nn = (nameDraft || "").trim();
    if (!cur || !nn || nn === cur) return;
    if (customers.includes(nn)) { window.trkToast && window.trkToast("Tên khách hàng đã tồn tại", "error"); return; }
    if (!ROUTES.customerRename) return;
    try {
      const res = await fetch(ROUTES.customerRename, { method: "PUT", headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": T.csrf }, body: JSON.stringify({ old: cur, new: nn }) }).then((r) => r.json());
      if (res && res.ok) {
        setCfg("customers", customers.map((c) => (c === cur ? nn : c)));
        const ni = { ...info }; ni[nn] = ni[cur] || {}; if (nn !== cur) delete ni[cur]; setCfg("customerInfo", ni);
        setSel(nn); setNameDraft(nn);
        window.trkToast && window.trkToast("Đã đổi tên khách hàng");
      } else { window.trkToast && window.trkToast((res && res.message) || "Đổi tên lỗi", "error"); }
    } catch (e) { window.trkToast && window.trkToast("Lỗi kết nối khi đổi tên", "error"); }
  };
  const add = () => {
    const n = draft.trim();
    if (!n || customers.includes(n)) { setDraft(""); return; }
    setCfg("customers", [...customers, n]); setSel(n); setDraft("");
  };
  const remove = (name) => {
    setCfg("customers", customers.filter((c) => c !== name));
    const ni = { ...info }; delete ni[name]; setCfg("customerInfo", ni);
    if (cur === name) setSel(customers.filter((c) => c !== name)[0] || null);
  };
  const inp = (val, onCh, ph) => (
    <input value={val || ""} onChange={(e) => onCh(e.target.value)} placeholder={ph}
      style={{ width: "100%", padding: "8px 11px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
      onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
  );
  return (
    <div style={{ display: "grid", gridTemplateColumns: "176px 1fr", gap: 16, minHeight: 360 }}>
      {/* customer list */}
      <div style={{ borderRight: "1px solid var(--line-2)", paddingRight: 12, display: "flex", flexDirection: "column", minHeight: 0 }}>
        <div style={{ display: "flex", gap: 6, marginBottom: 8 }}>
          <input value={draft} onChange={(e) => setDraft(e.target.value)} placeholder="Thêm khách…"
            onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); add(); } }}
            style={{ flex: 1, minWidth: 0, padding: "7px 9px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, outline: "none" }}
            onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
          <button type="button" onClick={add} title="Thêm khách hàng"
            style={{ width: 32, flexShrink: 0, display: "grid", placeItems: "center", border: "none", borderRadius: 8, background: "var(--accent)", color: "#fff", cursor: "pointer" }}><I.plus /></button>
        </div>
        <div style={{ overflowY: "auto", display: "flex", flexDirection: "column", gap: 1 }}>
          {customers.map((name) => {
            const active = cur === name;
            return (
              <button key={name} type="button" onClick={() => setSel(name)}
                style={{ textAlign: "left", border: "none", cursor: "pointer", borderRadius: 8, padding: "8px 10px", fontSize: 13.5, fontWeight: active ? 600 : 400,
                  background: active ? "var(--accent-weak)" : "transparent", color: active ? "var(--accent)" : "var(--ink)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}
                onMouseEnter={(e) => { if (!active) e.currentTarget.style.background = "var(--line-2)"; }}
                onMouseLeave={(e) => { if (!active) e.currentTarget.style.background = "transparent"; }}>
                {name}
              </button>
            );
          })}
          {!customers.length && <div style={{ padding: "16px 4px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có khách hàng.</div>}
        </div>
      </div>

      {/* detail */}
      {cur ? (
        <div>
          <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 6 }}>
            <div style={{ fontSize: 11, fontWeight: 700, color: "var(--ink-4)", textTransform: "uppercase", letterSpacing: "0.04em" }}>Tên khách hàng</div>
            <div style={{ flex: 1 }} />
            <button type="button" onClick={() => remove(cur)} title="Xóa khách hàng"
              style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "6px 11px", fontSize: 12.5, fontWeight: 500, border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-3)", cursor: "pointer" }}
              onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; e.currentTarget.style.borderColor = "#f3c9c9"; }}
              onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-3)"; e.currentTarget.style.borderColor = "var(--line)"; }}>
              <I.trash /> Xóa
            </button>
          </div>
          <div style={{ display: "flex", gap: 8, alignItems: "center", marginBottom: 14 }}>
            <input value={nameDraft} onChange={(e) => setNameDraft(e.target.value)} placeholder="Tên khách hàng…"
              onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); renameCustomer(); } }}
              style={{ flex: 1, padding: "9px 12px", fontSize: 15, fontWeight: 700, border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
              onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
            {(() => { const can = !!(nameDraft && nameDraft.trim() && nameDraft.trim() !== cur); return (
              <button type="button" onClick={renameCustomer} disabled={!can}
                style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "9px 16px", fontSize: 13.5, fontWeight: 600, border: "none", borderRadius: 10, whiteSpace: "nowrap", cursor: can ? "pointer" : "default", color: can ? "#fff" : "var(--ink-4)", background: can ? "var(--accent)" : "var(--line-2)" }}>
                <I.check /> Cập nhật tên
              </button>
            ); })()}
          </div>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
            {CUST_FIELDS.map((f) => (
              <Field key={f.k} label={f.label}>{inp(data[f.k], (v) => setField(f.k, v), f.ph)}</Field>
            ))}
            <Field label="Hạn thanh toán mặc định">
              <div style={{ position: "relative", width: 130 }}>
                <input inputMode="numeric" value={data.termDays || ""} onChange={(e) => setField("termDays", e.target.value.replace(/[^\d]/g, ""))} placeholder="VD: 30" className="tnum"
                  style={{ width: "100%", padding: "8px 38px 8px 11px", fontSize: 13.5, textAlign: "right", border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
                  onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
                <span style={{ position: "absolute", right: 10, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 12.5, pointerEvents: "none" }}>ngày</span>
              </div>
            </Field>
          </div>
          <div style={{ marginTop: 12 }}>
            <Field label="Địa chỉ">{inp(data.address, (v) => setField("address", v), "Địa chỉ xuất hóa đơn…")}</Field>
          </div>
          <div style={{ marginTop: 12 }}>
            <Field label="Ghi chú">
              <textarea value={data.note || ""} onChange={(e) => setField("note", e.target.value)} placeholder="Ghi chú về khách hàng, điều khoản riêng…" rows={3}
                style={{ width: "100%", padding: "8px 11px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 9, outline: "none", resize: "vertical", fontFamily: "inherit" }}
                onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
            </Field>
          </div>
          <div style={{ marginTop: 14, fontSize: 11.5, color: "var(--ink-4)" }}>Tên khách hàng là khóa liên kết với lô hàng. Bảng giá đã gửi quản lý ở trang <b style={{ color: "var(--ink-3)" }}>Bảng giá</b>.</div>
        </div>
      ) : (
        <div style={{ display: "grid", placeItems: "center", color: "var(--ink-4)", fontSize: 13.5 }}>Chọn hoặc thêm một khách hàng để xem chi tiết.</div>
      )}
    </div>
  );
}

function ConfigBody({ cfg, setCfg, sel, setSel, dirty, saving, onSave, dirtyMap, counts = {}, loading = false }) {
  const [draft, setDraft] = useState("");
  const list = cfg[sel] || [];
  const locked = new Set(cfg.locationLocked || []);
  const g = CFG_GROUPS.find((x) => x.key === sel);
  const prices = cfg.prices || {};
  const setPrice = (name, val) => setCfg("prices", { ...prices, [name]: val });
  const vehType = cfg.vehicleType || {};
  const setVehType = (name, val) => setCfg("vehicleType", { ...vehType, [name]: val });
  const codeKey = (g && g.codeKey) || "locationCode";
  // Mã (ký hiệu) lưu theo CHỈ SỐ dòng → tên được phép trùng, chỉ mã là định danh duy nhất.
  const codeArrKey = codeKey + "Arr";
  const codeArr = cfg[codeArrKey] || [];
  const setCode = (i, val) => { const a = [...codeArr]; while (a.length < list.length) a.push(""); a[i] = val; setCfg(codeArrKey, a); };
  // Phát hiện trùng ký hiệu (chuẩn hóa hoa + bỏ khoảng trắng)
  const normCode = (c) => (c || "").toString().trim().toUpperCase();
  const codeCounts = {};
  if (g && g.coded) list.forEach((_, i) => { const c = normCode(codeArr[i]); if (c) codeCounts[c] = (codeCounts[c] || 0) + 1; });
  const isDupCode = (i) => { const c = normCode(codeArr[i]); return !!c && codeCounts[c] > 1; };
  const hasDupCode = !!(g && g.coded) && Object.values(codeCounts).some((n) => n > 1);
  const costColors = cfg.costColors || {};
  const setColor = (name, val) => { const nc = { ...costColors }; if (val) nc[name] = val; else delete nc[name]; setCfg("costColors", nc); };
  const vatDefault = cfg.vatDefault || { hph: "8", icd: "0" };
  const setVat = (k, val) => setCfg("vatDefault", { ...vatDefault, [k]: val.replace(/[^\d.]/g, "") });
  const setVatAll = (val) => { const v = val.replace(/[^\d.]/g, ""); setCfg("vatDefault", { hph: v, icd: v }); };
  const addItem = () => {
    const v = draft.trim();
    if (!v) { setDraft(""); return; }
    // Danh mục CÓ MÃ (địa điểm/kho): cho phép trùng TÊN. Danh mục khác: vẫn chặn trùng tên.
    if (!(g && g.coded) && list.includes(v)) { setDraft(""); return; }
    setCfg(sel, [...list, v]);
    if (g && g.coded) { const a = [...codeArr]; while (a.length < list.length) a.push(""); a.push(""); setCfg(codeArrKey, a); }
    setDraft("");
  };
  // Đổi tên: các map gắn THEO TÊN (đơn giá/màu/loại xe) phải chuyển sang tên mới, không mất.
  // Riêng MÃ (coded) lưu theo chỉ số → đổi tên không ảnh hưởng mã.
  const rekey = (mapKey, map, old, v) => { if (map[old] === undefined) return; const m = { ...map }; m[v] = m[old]; delete m[old]; setCfg(mapKey, m); };
  const rename = (i, v) => {
    const old = list[i]; const next = [...list]; next[i] = v; setCfg(sel, next);
    if (v === old) return;
    if (g && g.priced)  rekey("prices", prices, old, v);
    if (g && g.colored) rekey("costColors", costColors, old, v);
    if (g && g.fleet)   rekey("vehicleType", vehType, old, v);
  };
  const remove = (i) => {
    const old = list[i]; setCfg(sel, list.filter((_, j) => j !== i));
    if (g && g.coded) setCfg(codeArrKey, codeArr.filter((_, j) => j !== i));
    const drop = (mapKey, map) => { if (map[old] === undefined) return; const m = { ...map }; delete m[old]; setCfg(mapKey, m); };
    if (g && g.priced)  drop("prices", prices);
    if (g && g.colored) drop("costColors", costColors);
    if (g && g.fleet)   drop("vehicleType", vehType);
  };
  return (
      <div style={{ display: "grid", gridTemplateColumns: "210px 1fr", gap: 18, padding: "14px 0 4px", minHeight: 380 }}>
        {/* group list */}
        <div style={{ display: "flex", flexDirection: "column", gap: 2, borderRight: "1px solid var(--line-2)", paddingRight: 14 }}>
          {CFG_GROUPS.map((grp) => {
            const active = sel === grp.key;
            return (
              <button key={grp.key} type="button" onClick={() => { setSel(grp.key); setDraft(""); }}
                style={{ textAlign: "left", border: "none", cursor: "pointer", borderRadius: 9, padding: "9px 11px",
                  background: active ? "var(--accent-weak)" : "transparent", transition: "background .12s" }}
                onMouseEnter={(e) => { if (!active) e.currentTarget.style.background = "var(--line-2)"; }}
                onMouseLeave={(e) => { if (!active) e.currentTarget.style.background = "transparent"; }}>
                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 8 }}>
                  <span style={{ display: "inline-flex", alignItems: "center", gap: 6, fontSize: 13.5, fontWeight: 600, color: active ? "var(--accent)" : "var(--ink)" }}>
                    {grp.label}
                    {dirtyMap && dirtyMap[grp.key] && <span title="Chưa lưu" style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} />}
                  </span>
                  {!grp.vat && <span className="tnum" style={{ fontSize: 11.5, fontWeight: 600, color: active ? "var(--accent)" : "var(--ink-4)", background: active ? "#fff" : "var(--line-2)", padding: "1px 7px", borderRadius: 999 }}>{counts[grp.key] != null ? counts[grp.key] : (cfg[grp.key] || []).length}</span>}
                </div>
              </button>
            );
          })}
        </div>
        {/* items editor */}
        <div>
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 12, marginBottom: 6 }}>
            <div style={{ fontSize: 15, fontWeight: 700, letterSpacing: "-0.01em" }}>{g.label}</div>
            <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
              {dirty
                ? <span style={{ display: "inline-flex", alignItems: "center", gap: 6, fontSize: 12, fontWeight: 600, color: "var(--warn)" }}><span style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} /> Chưa lưu</span>
                : <span style={{ display: "inline-flex", alignItems: "center", gap: 5, fontSize: 12, fontWeight: 600, color: "var(--good)" }}><I.check /> Đã lưu</span>}
              <button type="button" onClick={onSave} disabled={!dirty || saving || hasDupCode}
                title={hasDupCode ? "Có ký hiệu bị trùng — sửa trước khi lưu" : ""}
                style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 14px", fontSize: 13, fontWeight: 600, borderRadius: 9, border: "none",
                  cursor: dirty && !saving && !hasDupCode ? "pointer" : "default", color: dirty && !saving && !hasDupCode ? "#fff" : "var(--ink-4)", background: dirty && !saving && !hasDupCode ? "var(--accent)" : "var(--line-2)",
                  boxShadow: dirty && !saving && !hasDupCode ? "0 1px 2px rgba(42,111,219,.4)" : "none" }}>
                <I.check /> {saving ? "Đang lưu…" : "Lưu mục này"}
              </button>
            </div>
          </div>
          <div style={{ fontSize: 12.5, color: "var(--ink-3)", marginBottom: 10 }}>{g.hint}</div>
          {g.coded && <div style={{ display: "flex", alignItems: "flex-start", gap: 8, fontSize: 12, color: "var(--ink-2)", background: "#eef4ff", border: "1px solid #d6e3fb", borderRadius: 9, padding: "8px 12px", marginBottom: 10 }}>
            <i className="bi bi-info-circle-fill" style={{ color: "var(--accent)", marginTop: 1 }} />
            <span>Ô <b>Ký hiệu</b> được tạo tự động khi <b>import Excel bảng giá</b> (cột FROM/TO) nên đã khóa, không sửa trực tiếp tại đây. Bạn vẫn đổi được <b>tên</b> hiển thị.</span>
          </div>}
          {hasDupCode && <div style={{ display: "flex", alignItems: "center", gap: 7, fontSize: 12.5, fontWeight: 600, color: "var(--danger)", background: "#fce8e8", border: "1px solid #f3c9c9", borderRadius: 9, padding: "8px 12px", marginBottom: 10 }}>⚠ Có ký hiệu bị trùng — mỗi ký hiệu phải là duy nhất. Sửa các ô viền đỏ trước khi lưu.</div>}
          {loading ? (
            <div style={{ display: "flex", alignItems: "center", gap: 9, padding: "30px 4px", color: "var(--ink-4)", fontSize: 13.5 }}>
              <span style={{ width: 15, height: 15, border: "2px solid var(--line)", borderTopColor: "var(--accent)", borderRadius: "50%", display: "inline-block", animation: "trk-spin .7s linear infinite" }} />
              Đang tải dữ liệu mục này…
            </div>
          ) : sel === "customers" ? (
            <CustomerManager cfg={cfg} setCfg={setCfg} />
          ) : g.freetime ? (
            <div style={{ display: "flex", flexDirection: "column", gap: 14, maxWidth: 360 }}>
              <Field label="Ngưỡng Free time (giờ)">
                <div style={{ position: "relative", width: 140 }}>
                  <input inputMode="decimal" value={cfg.freeTimeHours == null ? "4" : cfg.freeTimeHours} onChange={(e) => setCfg("freeTimeHours", e.target.value.replace(/[^\d.]/g, ""))} className="tnum"
                    style={{ width: "100%", padding: "8px 30px 8px 11px", fontSize: 13.5, textAlign: "right", border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
                    onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
                  <span style={{ position: "absolute", right: 10, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 12.5, pointerEvents: "none" }}>giờ</span>
                </div>
              </Field>
              <div style={{ fontSize: 12, color: "var(--ink-4)", lineHeight: 1.6 }}>
                Free time <b>&gt; {cfg.freeTimeHours || 4}h</b> → <b style={{ color: "var(--good)" }}>CONNECT</b>; nhỏ hơn → <b style={{ color: "var(--danger)" }}>DISCONNECT</b>.
                <br />Free time = Giờ xe ra − (Giờ đến kế hoạch hoặc Giờ xe đến, lấy giờ muộn hơn).
              </div>
            </div>
          ) : g.vat ? (
            <div style={{ display: "flex", flexDirection: "column", gap: 14, maxWidth: 320 }}>
              <Field label="VAT mặc định cho lô hàng mới (%)">
                <div style={{ position: "relative", width: 120 }}>
                  <input inputMode="decimal" value={vatDefault.icd == null ? "" : vatDefault.icd} onChange={(e) => setVatAll(e.target.value)} className="tnum"
                    style={{ width: "100%", padding: "8px 24px 8px 11px", fontSize: 13.5, textAlign: "right", border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
                    onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
                  <span style={{ position: "absolute", right: 10, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", pointerEvents: "none" }}>%</span>
                </div>
              </Field>
              <div style={{ fontSize: 12, color: "var(--ink-4)" }}>Áp dụng cho lô hàng <b>mới thêm</b>. Các lô hiện có giữ VAT đã nhập.</div>
            </div>
          ) : (
            <>
              <div style={{ display: "flex", gap: 8, marginBottom: 12 }}>
                <input value={draft} onChange={(e) => setDraft(e.target.value)} placeholder={g.ph}
                  onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); addItem(); } }}
                  style={{ flex: 1, padding: "9px 12px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
                  onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
                <Btn variant="primary" onClick={addItem}>Thêm</Btn>
              </div>
              {(() => {
                const grid = g.priced && g.colored ? "24px 1fr 150px 56px 28px"
                  : g.priced ? "24px 1fr 150px 28px"
                  : g.colored ? "24px 1fr 56px 28px"
                  : g.coded ? "24px 1fr 130px 28px"
                  : g.fleet ? "24px 1fr 180px 28px"
                  : "24px 1fr 28px";
                const head = g.priced && g.colored ? [<span key="i" />, <span key="n">Tên khoản</span>, <span key="p" style={{ textAlign: "right" }}>Đơn giá mặc định</span>, <span key="c" style={{ textAlign: "center" }}>Theo dõi</span>, <span key="x" />]
                  : g.priced ? [<span key="i" />, <span key="n">Tên khoản</span>, <span key="p" style={{ textAlign: "right" }}>Đơn giá mặc định</span>, <span key="x" />]
                  : g.colored ? [<span key="i" />, <span key="n">Tên khoản</span>, <span key="c" style={{ textAlign: "center" }}>Theo dõi</span>, <span key="x" />]
                  : g.coded ? [<span key="i" />, <span key="n">{g.codeNameLabel || "Tên"}</span>, <span key="p">Ký hiệu</span>, <span key="x" />]
                  : g.fleet ? [<span key="i" />, <span key="n">Biển số</span>, <span key="p" style={{ textAlign: "center" }}>Loại xe</span>, <span key="x" />]
                  : null;
                return head && <div style={{ display: "grid", gridTemplateColumns: grid, gap: 8, padding: "0 0 4px", fontSize: 11, fontWeight: 600, color: "var(--ink-4)", textTransform: "uppercase", letterSpacing: "0.04em" }}>{head}</div>;
              })()}
              <div style={{ display: "flex", flexDirection: "column", gap: 2, maxHeight: 300, overflowY: "auto" }}>
                {list.map((it, i) => {
                  const codeLocked = !!g.coded;            // Ký hiệu Địa điểm/Kho do import Excel tạo → luôn khóa, không sửa tay
                  const linkedToPrice = locked.has(it);    // đang được bảng giá tham chiếu (hiện icon liên kết)
                  const dupCode = isDupCode(i);
                  const rowGrid = g.priced && g.colored ? "24px 1fr 150px 56px 28px"
                    : g.priced ? "24px 1fr 150px 28px"
                    : g.colored ? "24px 1fr 56px 28px"
                    : g.coded ? "24px 1fr 130px 28px"
                    : g.fleet ? "24px 1fr 180px 28px"
                    : "24px 1fr 28px";
                  return (
                  <div key={i} style={{ display: "grid", gridTemplateColumns: rowGrid, gap: 8, alignItems: "center", padding: "3px 0" }}>
                    <span style={{ color: linkedToPrice ? "var(--accent)" : "var(--ink-4)" }} title={linkedToPrice ? "Đang dùng trong bảng giá" : ""}><I.link /></span>
                    <input value={it} onChange={(e) => rename(i, e.target.value)}
                      style={{ width: "100%", padding: "7px 10px", fontSize: 13.5, border: "1px solid transparent", borderRadius: 8, outline: "none", background: "transparent" }}
                      onFocus={(e) => { e.target.style.borderColor = "var(--accent)"; e.target.style.background = "#fff"; }}
                      onBlur={(e) => { e.target.style.borderColor = "transparent"; e.target.style.background = "transparent"; }} />
                    {g.priced && <Money value={prices[it]} onChange={(x) => setPrice(it, x)} dim />}
                    {g.colored && (
                      <div style={{ display: "flex", justifyContent: "center" }}>
                        <FlagPicker value={costColors[it] || ""} onChange={(c) => setColor(it, c)} />
                      </div>
                    )}
                    {g.coded && <input value={codeArr[i] || ""} readOnly={codeLocked} onChange={(e) => { if (!codeLocked) setCode(i, e.target.value); }} placeholder="VD: TV"
                      title={codeLocked ? "Ký hiệu tạo từ import Excel — không sửa trực tiếp" : (dupCode ? "Ký hiệu bị trùng với mục khác" : "")}
                      style={{ width: "100%", padding: "7px 10px", fontSize: 13, fontWeight: 600, border: `1px solid ${dupCode ? "var(--danger)" : "var(--line)"}`, borderRadius: 8, outline: "none", textTransform: "uppercase", background: codeLocked ? "var(--line-2)" : (dupCode ? "#fce8e8" : "#fff"), color: codeLocked ? "var(--ink-3)" : (dupCode ? "var(--danger)" : "var(--ink)"), cursor: codeLocked ? "not-allowed" : "text" }}
                      onFocus={(e) => { if (!codeLocked) e.target.style.borderColor = "var(--accent)"; }} onBlur={(e) => (e.target.style.borderColor = dupCode ? "var(--danger)" : "var(--line)")} />}
                    {g.fleet && (
                      <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 8, padding: 2 }}>
                        {["MBF", "Ngoài"].map((opt) => {
                          const active = (vehType[it] || "MBF") === opt;
                          return (
                            <button key={opt} type="button" onClick={() => setVehType(it, opt)}
                              style={{ border: "none", cursor: "pointer", fontSize: 12, fontWeight: 600, padding: "5px 12px", borderRadius: 6, whiteSpace: "nowrap",
                                background: active ? "#fff" : "transparent", color: active ? (opt === "MBF" ? "var(--accent)" : "var(--ink-2)") : "var(--ink-4)", boxShadow: active ? "0 1px 2px rgba(16,19,23,.14)" : "none", transition: "all .12s" }}>
                              {opt === "MBF" ? "Xe MBF" : "Xe ngoài"}
                            </button>
                          );
                        })}
                      </div>
                    )}
                    <button type="button" onClick={() => remove(i)} title="Xóa"
                      style={{ width: 28, height: 28, display: "grid", placeItems: "center", border: "none", borderRadius: 7, background: "transparent", color: "var(--ink-4)", cursor: "pointer" }}
                      onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
                      onMouseLeave={(e) => { e.currentTarget.style.background = "transparent"; e.currentTarget.style.color = "var(--ink-4)"; }}>
                      <I.trash />
                    </button>
                  </div>
                ); })}
                {!list.length && <div style={{ padding: "20px 4px", fontSize: 13, color: "var(--ink-4)" }}>Chưa có mục nào — thêm ở trên.</div>}
              </div>
            </>
          )}
        </div>
      </div>
  );
}

function ConfigPopup({ cfg, setCfg, onClose }) {
  const footer = (
    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
      <div style={{ fontSize: 12.5, color: "var(--ink-4)" }}>Dữ liệu danh mục dùng chung cho cả hai sheet — chọn bằng Select2 trong các popup.</div>
      <Btn variant="primary" onClick={onClose}>Xong</Btn>
    </div>
  );
  return (
    <Modal title="Cấu hình dữ liệu" subtitle="Quản lý các danh mục link (master data) cho toàn hệ thống" onClose={onClose} footer={footer} width={760} icon={<I.cog />}>
      <ConfigBody cfg={cfg} setCfg={setCfg} />
    </Modal>
  );
}

export { CostPopup, RevenuePopup, CostPopupICD, RevenuePopupICD, InfoPopup, ConfigPopup, ConfigBody, CFG_GROUPS, Field, PriceList, TRACK_COLORS, colorHex };
