import React from "react";
const { useState } = React;
import { I } from "@trk/lib.jsx";

function SortBtn({ k, sort, onSort, align = "left", children }) {
  const on = sort.key === k;
  return (
    <button type="button" onClick={() => onSort(k)}
      style={{ display: "inline-flex", alignItems: "center", gap: 4, background: "transparent", border: "none", cursor: "pointer", padding: 0, font: "inherit",
        fontSize: 11, fontWeight: 700, color: on ? "var(--accent)" : "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.04em",
        flexDirection: align === "right" ? "row-reverse" : "row" }}>
      {children}
      <span style={{ fontSize: 9, opacity: on ? 1 : 0.4 }}>{on ? (sort.dir > 0 ? "▲" : "▼") : "↕"}</span>
    </button>
  );
}
function CellBtn({ main, sub, tone = "ink", onClick }) {
  const [h, setH] = useState(false);
  const color = tone === "warn" ? "var(--warn)" : tone === "good" ? "var(--good)" : "var(--ink)";
  return (
    <button type="button" onClick={onClick} onMouseEnter={() => setH(true)} onMouseLeave={() => setH(false)}
      title="Bấm để xem & sửa chi tiết"
      style={{ width: "100%", display: "flex", alignItems: "center", justifyContent: "flex-end", gap: 8,
        background: h ? "var(--accent-weak-2)" : "transparent", border: `1px solid ${h ? "var(--accent-weak)" : "transparent"}`,
        borderRadius: 9, padding: "6px 9px", cursor: "pointer", transition: "all .12s" }}>
      <span style={{ textAlign: "right", minWidth: 0 }}>
        <div className="tnum" style={{ fontSize: 13.5, fontWeight: 600, color }}>{main}</div>
        {sub && <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 1 }}>{sub}</div>}
      </span>
      <span style={{ flexShrink: 0, color: h ? "var(--accent)" : "var(--ink-4)", opacity: h ? 1 : 0.45, transition: "all .12s" }}><I.open /></span>
    </button>
  );
}

function Badge({ children, tone }) {
  const map = {
    good: ["var(--good)", "var(--good-weak)"], warn: ["var(--warn)", "var(--warn-weak)"],
    blue: ["var(--accent)", "var(--accent-weak)"], gray: ["var(--ink-3)", "#eef0f3"],
    amber: ["#b45309", "#fef3c7"],
  };
  const [fg, bg] = map[tone] || map.gray;
  return <span style={{ fontSize: 11.5, fontWeight: 600, color: fg, background: bg, padding: "3px 9px", borderRadius: 999, whiteSpace: "nowrap" }}>{children}</span>;
}

/* clickable info cell with pencil affordance */
function EditCell({ children, onClick }) {
  const [h, setH] = useState(false);
  return (
    <div onClick={onClick} onMouseEnter={() => setH(true)} onMouseLeave={() => setH(false)} title="Bấm để sửa thông tin"
      style={{ position: "relative", cursor: "pointer", borderRadius: 8, padding: "4px 24px 4px 7px", margin: "-4px -8px",
        background: h ? "var(--accent-weak-2)" : "transparent", transition: "background .12s" }}>
      {children}
      <span style={{ position: "absolute", right: 6, top: "50%", transform: "translateY(-50%)", color: "var(--accent)", opacity: h ? 1 : 0, transition: "opacity .12s" }}><I.edit /></span>
    </div>
  );
}

/* ===================== table row ===================== */
const TH = ({ children, w, align = "left", sticky }) => (
  <th style={{ width: w, textAlign: align, padding: "11px 14px", fontSize: 11, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.04em",
    background: "#fafbfc", borderBottom: "1px solid var(--line)", position: "sticky", top: 0, left: sticky ? 0 : "auto", zIndex: sticky ? 3 : 2, whiteSpace: "nowrap" }}>{children}</th>
);
const TD = ({ children, align = "left", sticky, pad = "9px 14px" }) => (
  <td style={{ padding: pad, fontSize: 13.5, textAlign: align, borderBottom: "1px solid var(--line-2)", verticalAlign: "middle",
    position: sticky ? "sticky" : "static", left: sticky ? 0 : "auto", background: sticky ? "#fff" : "transparent", zIndex: sticky ? 1 : "auto" }}>{children}</td>
);

export { SortBtn, CellBtn, Badge, EditCell, TH, TD };
