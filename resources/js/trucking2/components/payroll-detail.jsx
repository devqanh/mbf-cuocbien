import React from "react";
import { I, Money, DateField, Txt, fmtVND, fmtNum, toNum } from "@trk/lib.jsx";

/* Gộp khoản phí tuyến + chi khác thủ công của 1 chuyến thành 2 rổ hiển thị cho kế toán. */
function splitGroup(g) {
  const manual = g.manual || [];
  const daily = [
    ...(g.items || []),
    ...manual.filter((m) => m.perDay !== false).map((m) => ({ label: m.name || "Chi khác", amount: m.amount })),
  ];
  const payroll = [
    ...(g.payrollItems || []),
    ...manual.filter((m) => m.perDay === false).map((m) => ({ label: m.name || "Chi khác", amount: m.amount })),
  ];
  return { daily, payroll };
}

const sum = (arr, k) => (arr || []).reduce((s, x) => s + (x[k] || 0), 0);

/* CHI TIẾT 1 XE (read-only) — 2 cột: ĐÃ CHI THEO NGÀY · LƯƠNG CHƯA CHI, gom theo ngày + chuyến. */
export function PayrollDetail({ row }) {
  const lines = row.detail || row.lines || [];
  if (!lines.length) return <div style={{ padding: "10px 14px", fontSize: 12.5, color: "var(--ink-4)" }}>Không có chi tiết chuyến.</div>;

  const Col = ({ title, pick, accent }) => (
    <div style={{ flex: 1, minWidth: 0 }}>
      <div style={{ fontSize: 10.5, fontWeight: 700, textTransform: "uppercase", letterSpacing: ".03em", color: accent, marginBottom: 6 }}>{title}</div>
      {lines.map((ln, li) => {
        const groups = (ln.groups || []).map((g) => ({ g, items: pick(g) })).filter((x) => x.items.length);
        if (!groups.length) return null;
        return (
          <div key={li} style={{ marginBottom: 8 }}>
            <div style={{ fontSize: 10.5, color: "var(--ink-4)", fontWeight: 600 }} className="tnum">{ln.date}</div>
            {groups.map((x, gi) => (
              <div key={gi} style={{ margin: "3px 0 5px", paddingLeft: 8, borderLeft: "2px solid var(--line-2)" }}>
                <div className="tnum" style={{ fontSize: 11, color: "var(--ink-3)", fontWeight: 600 }}>{x.g.route}{x.g.cont ? " · " + x.g.cont : ""}</div>
                {x.items.map((it, i) => (
                  <div key={i} style={{ display: "flex", gap: 8, fontSize: 12, padding: "2px 0" }}>
                    <span style={{ flex: 1 }}>{it.label}{it.liters != null && it.unitPrice != null ? <span style={{ color: "var(--ink-4)", fontSize: 11 }} className="tnum"> ({fmtNum(it.liters)}L×{fmtVND(it.unitPrice)})</span> : null}</span>
                    <span className="tnum" style={{ fontWeight: 600 }}>{fmtVND(it.amount)}</span>
                  </div>
                ))}
              </div>
            ))}
          </div>
        );
      })}
    </div>
  );

  const totDaily = lines.reduce((s, ln) => s + (ln.groups || []).reduce((a, g) => a + sum(splitGroup(g).daily, "amount"), 0), 0);
  const totPayroll = lines.reduce((s, ln) => s + (ln.groups || []).reduce((a, g) => a + sum(splitGroup(g).payroll, "amount"), 0), 0);

  return (
    <div style={{ padding: "12px 14px", background: "#fafbfc" }}>
      <div style={{ display: "flex", gap: 20, flexWrap: "wrap" }}>
        <Col title="Đã chi theo ngày (đã thanh toán)" pick={(g) => splitGroup(g).daily} accent="var(--ink-3)" />
        <div style={{ width: 1, background: "var(--line)", alignSelf: "stretch" }} />
        <Col title="Lương chưa chi (gom trả đợt)" pick={(g) => splitGroup(g).payroll} accent="var(--accent)" />
      </div>
      <div style={{ display: "flex", gap: 20, marginTop: 8, paddingTop: 8, borderTop: "1px dashed var(--line)", fontSize: 12 }}>
        <div style={{ flex: 1 }}>Σ đã chi theo ngày: <b className="tnum" style={{ color: "var(--ink-2)" }}>{fmtVND(totDaily)}</b></div>
        <div style={{ flex: 1 }}>Σ lương phải trả: <b className="tnum" style={{ color: "var(--accent)" }}>{fmtVND(totPayroll)}</b></div>
      </div>
    </div>
  );
}

/* CÁC ĐỢT THANH TOÁN (trả chậm / chia đợt) — sửa được; trả về qua onChange. */
export function PaymentsEditor({ payments = [], onChange, payroll = 0 }) {
  const list = Array.isArray(payments) ? payments : [];
  const upd = (k, np) => onChange(list.map((p, j) => (j === k ? { ...p, ...np } : p)));
  const paidSum = list.reduce((s, p) => s + toNum(p.amount), 0);
  const remain = payroll - paidSum;
  return (
    <div style={{ padding: "10px 14px", borderTop: "1px dashed var(--line)" }}>
      <div style={{ fontSize: 11, fontWeight: 700, color: "var(--ink-2)", marginBottom: 7 }}><i className="bi bi-wallet2" style={{ color: "var(--accent)" }} /> Các đợt thanh toán</div>
      <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
        {list.map((p, k) => (
          <div key={k} style={{ display: "flex", alignItems: "center", gap: 8 }}>
            <div style={{ width: 140 }}><DateField value={p.date || ""} onChange={(v) => upd(k, { date: v })} /></div>
            <div style={{ width: 130 }}><Money value={p.amount} onChange={(v) => upd(k, { amount: v })} dim /></div>
            <input value={p.note || ""} onChange={(e) => upd(k, { note: e.target.value })} placeholder="Ghi chú đợt trả" style={{ flex: 1, minWidth: 0, padding: "6px 9px", fontSize: 12.5, border: "1px solid var(--line)", borderRadius: 7, outline: "none" }} />
            <button type="button" onClick={() => onChange(list.filter((_, j) => j !== k))} title="Xóa đợt" style={{ flexShrink: 0, width: 28, height: 28, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 7, background: "#fff", color: "var(--ink-4)", cursor: "pointer" }}><I.x /></button>
          </div>
        ))}
        {!list.length && <div style={{ fontSize: 12, color: "var(--ink-4)" }}>Chưa có đợt thanh toán nào.</div>}
      </div>
      <button type="button" onClick={() => onChange([...list, { date: "", amount: "", note: "" }])} style={{ marginTop: 7, display: "inline-flex", alignItems: "center", gap: 6, padding: "6px 11px", fontSize: 12, fontWeight: 600, border: "1px dashed var(--line)", borderRadius: 8, background: "#fff", color: "var(--accent)", cursor: "pointer" }}><I.plus /> Thêm đợt thanh toán</button>
      <div style={{ display: "flex", gap: 18, marginTop: 9, fontSize: 12.5, flexWrap: "wrap" }}>
        <span>Lương phải trả: <b className="tnum">{fmtVND(payroll)}</b></span>
        <span>Đã trả: <b className="tnum" style={{ color: "var(--good)" }}>{fmtVND(paidSum)}</b></span>
        <span>Còn lại: <b className="tnum" style={{ color: remain > 0 ? "var(--danger)" : "var(--good)" }}>{fmtVND(remain)}</b></span>
      </div>
    </div>
  );
}
