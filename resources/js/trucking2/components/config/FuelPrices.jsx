import React from "react";
import { I, Money, Txt, DateField, useIsMobile } from "@trk/lib.jsx";

/* ===================== BẢNG GIÁ DẦU (repeater theo ngày) ===================== */

export function FuelPrices({ rows = [], onChange }) {
  const isMobile = useIsMobile();
  const set = (i, np) => onChange(rows.map((r, j) => (j === i ? { ...r, ...np } : r)));
  const add = () => onChange([...(rows || []), { id: Date.now() + Math.random(), from: "", to: "", price: "", note: "" }]);
  const del = (i) => onChange(rows.filter((_, j) => j !== i));
  const lbl = (t) => <div style={{ fontSize: 11.5, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>{t}</div>;
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
      <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginBottom: 2, lineHeight: 1.5 }}>
        Đơn giá <b style={{ color: "var(--ink-3)" }}>đồng/lít</b> hiệu lực theo khoảng ngày. <b>Đến ngày</b> để trống = áp dụng <b>từ "Từ ngày" trở đi</b> (giá hiện hành); chọn 1 ngày thì đặt Từ = Đến.
      </div>
      {(rows || []).length === 0 && <div style={{ padding: "12px 2px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có mốc giá dầu nào — bấm <b>+ Thêm mốc giá</b>.</div>}
      {(rows || []).map((r, i) => (
        <div key={r.id || i} style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr 1fr" : "150px 150px 160px 1fr 34px", gap: 10, alignItems: "end", border: "1px solid var(--line)", borderRadius: 10, padding: "10px 12px", background: "#fafbfc" }}>
          <div>{lbl("Từ ngày")}<DateField value={r.from} onChange={(x) => set(i, { from: x })} /></div>
          <div>{lbl(<>Đến ngày <span style={{ color: "var(--ink-4)", fontWeight: 400 }}>(tùy chọn)</span></>)}<DateField value={r.to} onChange={(x) => set(i, { to: x })} /></div>
          <div>{lbl("Đơn giá (đ/lít)")}<Money value={r.price} onChange={(x) => set(i, { price: x })} /></div>
          <div>{lbl("Ghi chú")}<Txt value={r.note} onChange={(x) => set(i, { note: x })} placeholder="VD: giá tháng 5/2026" /></div>
          <button type="button" onClick={() => del(i)} title="Xóa mốc giá"
            style={{ width: 34, height: 38, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: "var(--ink-4)", cursor: "pointer" }}
            onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
            onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-4)"; }}><I.trash /></button>
        </div>
      ))}
      <button type="button" onClick={add}
        style={{ alignSelf: "flex-start", display: "inline-flex", alignItems: "center", gap: 7, padding: "9px 14px", fontSize: 13.5, fontWeight: 600, border: "1px dashed var(--accent)", borderRadius: 10, background: "var(--accent-weak-2)", color: "var(--accent)", cursor: "pointer" }}>
        <I.plus /> Thêm mốc giá
      </button>
    </div>
  );
}
