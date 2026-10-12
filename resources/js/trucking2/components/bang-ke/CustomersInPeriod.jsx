import React from "react";
import { DateField, fmtNum, useIsMobile } from "@trk/lib.jsx";

const { useState, useEffect } = React;

/* KHÁCH CÓ LÔ TRONG KỲ — khung trên trang danh sách Bảng kê: chọn kỳ (Giờ xe ra) → mỗi khách có bao nhiêu lô ra,
   bao nhiêu lô đã nằm trong bảng kê, bao nhiêu còn chưa lập + nút Tạo bảng kê điền sẵn khách + kỳ.
   Thay cho việc chọn từng khách ở trang tạo để dò xem tháng đó có lô hay không. */

const p2 = (n) => String(n).padStart(2, "0");
const iso = (d) => `${d.getFullYear()}-${p2(d.getMonth() + 1)}-${p2(d.getDate())}`;
const monthRange = (offset) => {
  const t = new Date(); const a = new Date(t.getFullYear(), t.getMonth() + offset, 1);
  return [iso(a), iso(new Date(a.getFullYear(), a.getMonth() + 1, 0))];
};
const KEY = "trk:bangke:custPeriod";

export function CustomersInPeriod({ routes }) {
  const isMobile = useIsMobile();
  const saved = (() => { try { return JSON.parse(localStorage.getItem(KEY) || "{}") || {}; } catch (e) { return {}; } })();
  // Mặc định THÁNG TRƯỚC: đầu tháng kế toán lập bảng kê cho các lô của tháng trước.
  const [from, setFrom] = useState(saved.from || monthRange(-1)[0]);
  const [to, setTo] = useState(saved.to || monthRange(-1)[1]);
  const [open, setOpen] = useState(saved.open !== false);
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  useEffect(() => { try { localStorage.setItem(KEY, JSON.stringify({ from, to, open })); } catch (e) {} }, [from, to, open]);
  useEffect(() => {
    if (!open || !routes.customers || (!from && !to)) return;
    let alive = true; setLoading(true);
    const q = new URLSearchParams(); if (from) q.set("from", from); if (to) q.set("to", to);
    window.trkApi("GET", routes.customers + "?" + q.toString())
      .then((r) => { if (alive) { setData(r && r.ok ? r.customers || [] : []); setLoading(false); } })
      .catch(() => { if (alive) { setData([]); setLoading(false); } });
    return () => { alive = false; };
  }, [from, to, open]);

  const rows = data || [];
  const sum = (k) => rows.reduce((a, r) => a + (r[k] || 0), 0);
  const quick = (off) => { const [a, b] = monthRange(off); setFrom(a); setTo(b); };
  const isQuick = (off) => { const [a, b] = monthRange(off); return from === a && to === b; };
  const createUrl = (c) => routes.create + "?" + new URLSearchParams({ customer: c, from, to }).toString();
  const th = { padding: "8px 10px", fontSize: 10.5, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: ".03em", borderBottom: "1px solid var(--line)", background: "#fafbfc", position: "sticky", top: 0, whiteSpace: "nowrap" };
  const td = { padding: "8px 10px", borderBottom: "1px solid var(--line-2)", whiteSpace: "nowrap" };
  const chip = (on) => ({ padding: "6px 11px", fontSize: 12.5, fontWeight: 600, borderRadius: 8, cursor: "pointer", border: "1px solid " + (on ? "var(--accent)" : "var(--line)"), background: on ? "var(--accent-weak-2)" : "#fff", color: on ? "var(--accent)" : "var(--ink-3)" });

  return (
    <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, marginBottom: 16, overflow: "hidden" }}>
      <div style={{ display: "flex", alignItems: "center", gap: 10, padding: "12px 16px", flexWrap: "wrap", borderBottom: open ? "1px solid var(--line-2)" : "none" }}>
        <button type="button" onClick={() => setOpen((v) => !v)} style={{ display: "inline-flex", alignItems: "center", gap: 7, border: "none", background: "transparent", cursor: "pointer", padding: 0, fontSize: 14, fontWeight: 700, color: "var(--ink)" }}>
          <i className={"bi " + (open ? "bi-chevron-down" : "bi-chevron-right")} style={{ fontSize: 12, color: "var(--ink-4)" }} />
          <i className="bi bi-people" style={{ color: "var(--accent)" }} /> Khách có lô trong kỳ
        </button>
        {open && (
          <>
            <button type="button" onClick={() => quick(-1)} style={chip(isQuick(-1))}>Tháng trước</button>
            <button type="button" onClick={() => quick(0)} style={chip(isQuick(0))}>Tháng này</button>
            <span style={{ fontSize: 12.5, color: "var(--ink-3)", fontWeight: 600 }}>Cont ra từ</span>
            <div style={{ width: 140 }}><DateField value={from} onChange={setFrom} /></div>
            <span style={{ fontSize: 12.5, color: "var(--ink-3)", fontWeight: 600 }}>đến</span>
            <div style={{ width: 140 }}><DateField value={to} onChange={setTo} /></div>
          </>
        )}
        <div style={{ flex: 1 }} />
        {open && (
          <span className="tnum" style={{ fontSize: 12.5, color: loading ? "var(--accent)" : "var(--ink-3)" }}>
            {loading ? "Đang tải…" : data ? <><b style={{ color: "var(--ink)" }}>{rows.length}</b> khách · {fmtNum(sum("total"))} lô · <b style={{ color: sum("unbilled") ? "var(--warn)" : "var(--good)" }}>{fmtNum(sum("unbilled"))}</b> lô chưa lập bảng kê</> : ""}
          </span>
        )}
      </div>
      {open && (
        <div style={{ maxHeight: 340, overflow: "auto", opacity: loading && data ? 0.55 : 1 }}>
          <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13, minWidth: isMobile ? 560 : 0 }}>
            <thead><tr>
              <th style={{ ...th, textAlign: "left" }}>Khách hàng</th>
              <th style={{ ...th, textAlign: "right" }}>Lô ra trong kỳ</th>
              <th style={{ ...th, textAlign: "right" }}>Nhập</th>
              <th style={{ ...th, textAlign: "right" }}>Xuất</th>
              <th style={{ ...th, textAlign: "right" }} title="Lô đã nằm trong một bảng kê bất kỳ">Đã vào bảng kê</th>
              <th style={{ ...th, textAlign: "right" }}>Chưa lập</th>
              <th style={{ ...th, width: 1 }}></th>
            </tr></thead>
            <tbody>
              {data && !rows.length && !loading && <tr><td colSpan={7} style={{ padding: 22, textAlign: "center", color: "var(--ink-4)" }}>Không có lô nào ra trong kỳ này.</td></tr>}
              {!data && loading && <tr><td colSpan={7} style={{ padding: 22, textAlign: "center", color: "var(--ink-4)" }}>Đang tải…</td></tr>}
              {rows.map((r, i) => {
                const done = !r.unbilled;
                return (
                  <tr key={i} style={{ color: done ? "var(--ink-4)" : "var(--ink-2)" }}>
                    <td style={{ ...td, fontWeight: 600, color: done ? "var(--ink-3)" : "var(--ink)", whiteSpace: "normal" }}>{r.customer || <span style={{ color: "var(--warn)" }}>(lô chưa có khách)</span>}</td>
                    <td className="tnum" style={{ ...td, textAlign: "right", fontWeight: 700 }}>{fmtNum(r.total)}</td>
                    <td className="tnum" style={{ ...td, textAlign: "right" }}>{r.nhap || "—"}</td>
                    <td className="tnum" style={{ ...td, textAlign: "right" }}>{r.xuat || "—"}</td>
                    <td className="tnum" style={{ ...td, textAlign: "right", color: r.billed ? "var(--good)" : "var(--ink-4)" }}>{r.billed || "—"}</td>
                    <td className="tnum" style={{ ...td, textAlign: "right", fontWeight: 700, color: done ? "var(--good)" : "var(--warn)" }}>{done ? "✓ đủ" : r.unbilled}</td>
                    <td style={{ ...td, textAlign: "right" }}>
                      {r.customer && !done && (
                        <a href={createUrl(r.customer)} title={`Tạo bảng kê cho ${r.customer}, cont ra ${from} → ${to}`}
                          style={{ display: "inline-flex", alignItems: "center", gap: 5, fontSize: 12.5, fontWeight: 600, color: "var(--accent)", textDecoration: "none", whiteSpace: "nowrap" }}>
                          <i className="bi bi-plus-circle" /> Tạo bảng kê
                        </a>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
