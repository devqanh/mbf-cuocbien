import React from "react";
import { I, Money, DateField, Txt, fmtVND, fmtNum, fmtDate, toNum } from "@trk/lib.jsx";

// Dầu = CHI PHÍ CÔNG TY, KHÔNG tính vào lương lái → loại khỏi cả 2 rổ (kể cả snapshot CŨ còn dau1/dau2 trong items).
const isFuel = (it) => it && (it.key === "dau1" || it.key === "dau2" || (it.liters != null && it.unitPrice != null));

/* Gộp khoản quy chế + chi khác thủ công của 1 chuyến thành 4 rổ cho kế toán:
   daily = chi theo ngày TÍNH LƯƠNG · choho = chi theo ngày chọn "Chi hộ" (không tính lương) ·
   khac = chi khác phát sinh chọn "Chi phí khác" (không tính lương, thống kê riêng) · payroll = gom trả kỳ lương. */
function splitGroup(g) {
  const manual = g.manual || [];
  const dayAll = [
    ...(g.items || []).filter((it) => !isFuel(it)),
    ...manual.filter((m) => m.perDay !== false).map((m) => ({ label: (m.name || "Chi khác") + (m.khac && m.note ? " (" + m.note + ")" : ""), amount: m.amount, choHo: !!m.choHo && !m.khac, khac: !!m.khac })),
  ];
  const payroll = [
    ...(g.payrollItems || []).filter((it) => !isFuel(it)),
    ...manual.filter((m) => m.perDay === false).map((m) => ({ label: m.name || "Chi khác", amount: m.amount })),
  ];
  return { daily: dayAll.filter((it) => !it.choHo && !it.khac), choho: dayAll.filter((it) => it.choHo), khac: dayAll.filter((it) => it.khac), payroll };
}

const sum = (arr, k) => (arr || []).reduce((s, x) => s + (x[k] || 0), 0);

/* Tổng kỳ lương suy TỪ detail groups (đã loại dầu) — dùng cho snapshot cũ chưa "Tính lại" để khớp với phần chi tiết.
   Trả null nếu kỳ không có detail (để gọi nơi dùng fallback về số snapshot). */
export function payrollSumsFromDetail(row) {
  const lines = row.detail || row.lines || [];
  if (!lines.length) return null;
  let daily = 0, payroll = 0;
  for (const ln of lines) for (const g of (ln.groups || [])) {
    const sp = splitGroup(g);
    daily += sum(sp.daily, "amount"); payroll += sum(sp.payroll, "amount");
  }
  return { daily, payroll };
}

/* Tổng hợp 1 xe từ detail: tiền theo rổ + số chuyến/cảnh báo/lít dầu — dùng cho bảng chính, KPI, lọc, Excel. */
export function rowStats(row) {
  const lines = row.detail || row.lines || [];
  const st = { daily: 0, choho: 0, khac: 0, payroll: 0, trips: 0, days: lines.length, warn: 0, liters: 0 };
  for (const ln of lines) for (const g of (ln.groups || [])) {
    const sp = splitGroup(g);
    st.daily += sum(sp.daily, "amount"); st.choho += sum(sp.choho, "amount"); st.khac += sum(sp.khac, "amount"); st.payroll += sum(sp.payroll, "amount");
    if (!g.orphan) st.trips++;   // nhóm "chi khác chưa gắn chuyến" không tính là chuyến
    if (g.note) st.warn++;
    if (g.fuel) st.liters += Number(g.fuel.liters) || 0;
  }
  return st;
}

const norm = (v) => String(v == null ? "" : v).toLowerCase();
/* Chuyến khớp ô tìm kiếm (cont / tuyến / tên khoản). q rỗng = khớp hết. */
export const groupMatches = (g, q) => !q || norm(g.cont).includes(q) || norm(g.route).includes(q)
  || [...(g.items || []), ...(g.payrollItems || []), ...(g.manual || [])].some((it) => norm(it.label || it.name).includes(q));

const BUCKETS = [
  { k: "daily", label: "Chi ngày · tính lương", tag: "", color: "var(--ink-2)", bg: "#eef1f5" },
  { k: "payroll", label: "Lương kỳ (gom trả)", tag: "kỳ lương", color: "var(--accent)", bg: "#e7efff" },
  { k: "choho", label: "Chi hộ (không tính lương)", tag: "chi hộ", color: "#9a6700", bg: "#fff4d6" },
  { k: "khac", label: "Chi phí khác (không tính lương)", tag: "chi khác", color: "#6d28d9", bg: "#f1ebff" },
];
const MODE_LABEL = { self: "Kéo cont ra", other: "Kéo cont khác ra", none: "Không kéo cont" };

/* Từng dòng "ngày × chuyến × khoản" của 1 xe — cho Excel đối soát. */
export function detailRows(row) {
  const out = [];
  for (const ln of (row.detail || row.lines || [])) for (const g of (ln.groups || [])) {
    const sp = splitGroup(g);
    const base = { date: ln.date, driver: ln.driver || row.driver || "", route: g.route || "", cont: g.cont || "",
      mode: MODE_LABEL[g.mode] || g.mode || "", liters: g.fuel ? Math.round(Number(g.fuel.liters) || 0) : "", note: g.note || "" };
    let any = false;
    for (const b of BUCKETS) for (const it of sp[b.k]) { any = true; out.push({ ...base, bucket: b.label, item: it.label || "", amount: it.amount || 0 }); }
    if (!any) out.push({ ...base, bucket: "", item: "", amount: 0 });
  }
  return out;
}

/* CHI TIẾT 1 XE — sổ theo NGÀY (khớp thẻ xe/ngày ở Lộ trình): mỗi ngày bung ra từng chuyến, các khoản tô màu theo rổ.
   q = lọc chuyến theo cont/tuyến/khoản · day = chỉ 1 ngày · onlyWarn = chỉ chuyến có cảnh báo · loTrinhUrl = mở Lộ trình đúng ngày + xe. */
export function PayrollDetail({ row, q = "", day = "", onlyWarn = false, loTrinhUrl = "" }) {
  const [openDay, setOpenDay] = React.useState({});
  const [showAll, setShowAll] = React.useState(false);
  const all = row.detail || row.lines || [];
  if (!all.length) return <div style={{ padding: "10px 14px", fontSize: 12.5, color: "var(--ink-4)" }}>Không có chi tiết chuyến.</div>;
  const qq = norm(q).trim();
  const lines = all
    .filter((ln) => !day || ln.date === day)
    .map((ln) => ({ ...ln, groups: (ln.groups || []).filter((g) => groupMatches(g, qq) && (!onlyWarn || g.note)) }))
    .filter((ln) => ln.groups.length);
  if (!lines.length) return <div style={{ padding: "10px 14px", fontSize: 12.5, color: "var(--ink-4)" }}>Không có chuyến khớp bộ lọc.</div>;

  const filtered = !!(qq || day || onlyWarn);
  const isOpen = (d) => (openDay[d] != null ? openDay[d] : showAll || filtered);
  const sumsOf = (groups) => {
    const s = { daily: 0, choho: 0, khac: 0, payroll: 0, liters: 0, warn: 0, trips: groups.filter((g) => !g.orphan).length };
    for (const g of groups) {
      const sp = splitGroup(g);
      for (const b of BUCKETS) s[b.k] += sum(sp[b.k], "amount");
      if (g.fuel) s.liters += Number(g.fuel.liters) || 0;
      if (g.note) s.warn++;
    }
    return s;
  };
  const tot = sumsOf(lines.flatMap((ln) => ln.groups));
  // Tổng hợp theo khoản (tên khoản × rổ) — đối chiếu nhanh với quy chế / Lộ trình.
  const byItem = {};
  for (const ln of lines) for (const g of ln.groups) {
    const sp = splitGroup(g);
    for (const b of BUCKETS) for (const it of sp[b.k]) {
      const key = b.k + "|" + (it.label || "");
      byItem[key] = byItem[key] || { bucket: b, label: it.label || "—", n: 0, amount: 0 };
      byItem[key].n++; byItem[key].amount += it.amount || 0;
    }
  }
  const itemList = Object.values(byItem).sort((a, b) => BUCKETS.indexOf(a.bucket) - BUCKETS.indexOf(b.bucket) || b.amount - a.amount);

  const th = { padding: "6px 8px", fontSize: 10.5, fontWeight: 700, color: "var(--ink-4)", textTransform: "uppercase", letterSpacing: ".03em", borderBottom: "1px solid var(--line)", whiteSpace: "nowrap", textAlign: "right" };
  const td = { padding: "6px 8px", fontSize: 12.5, borderBottom: "1px solid var(--line-2)", textAlign: "right", verticalAlign: "middle" };
  const dash = <span style={{ color: "var(--ink-4)" }}>—</span>;
  const money = (v, color) => (v ? <span className="tnum" style={{ fontWeight: 600, color }}>{fmtVND(v)}</span> : dash);
  const lit = (v) => (v ? <span className="tnum">{fmtNum(Math.round(v))}</span> : dash);
  const link = (d) => (loTrinhUrl ? loTrinhUrl + "?date=" + encodeURIComponent(d) + "&bks=" + encodeURIComponent(row.bks) : "");
  const sumCells = (s, bold) => (<>
    <td style={td}>{money(s.daily, "var(--ink-2)")}</td>
    <td style={td}>{money(s.choho, "#9a6700")}</td>
    <td style={td}>{money(s.khac, "#6d28d9")}</td>
    <td style={td}>{money(s.payroll, "var(--accent)")}</td>
    <td style={{ ...td, fontWeight: bold ? 700 : 400 }}>{lit(s.liters)}</td>
  </>);

  return (
    <div style={{ padding: "12px 14px", background: "#fafbfc", display: "flex", flexDirection: "column", gap: 12 }}>
      <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
        <div style={{ fontSize: 11.5, fontWeight: 700, color: "var(--ink-2)", flex: 1 }}>
          <i className="bi bi-calendar3" /> Sổ chi tiết theo ngày · {lines.length} ngày · {tot.trips} chuyến
          {tot.warn ? <span style={{ color: "#b45309" }}> · {tot.warn} chuyến cần kiểm tra</span> : null}
          {filtered ? <span style={{ color: "var(--ink-4)", fontWeight: 500 }}> (đang lọc)</span> : null}
        </div>
        {!filtered && <button type="button" onClick={() => { setShowAll((v) => !v); setOpenDay({}); }} style={{ fontSize: 11.5, fontWeight: 600, padding: "4px 10px", border: "1px solid var(--line)", borderRadius: 7, background: "#fff", color: "var(--ink-2)", cursor: "pointer" }}>{showAll ? "Thu gọn chuyến" : "Bung tất cả chuyến"}</button>}
      </div>
      <div style={{ overflowX: "auto", background: "#fff", border: "1px solid var(--line)", borderRadius: 10 }}>
        <table style={{ width: "100%", borderCollapse: "collapse", minWidth: 760 }}>
          <thead><tr>
            <th style={{ ...th, textAlign: "left" }}>Ngày</th><th style={{ ...th, textAlign: "left" }}>Lái nhận</th><th style={th}>Chuyến</th>
            <th style={th}>Chi ngày (tính lương)</th><th style={th}>Chi hộ</th><th style={th}>Chi khác</th><th style={th}>Lương kỳ</th><th style={th}>Dầu (L)</th><th style={th} />
          </tr></thead>
          <tbody>
            {lines.map((ln) => {
              const s = sumsOf(ln.groups); const op = isOpen(ln.date);
              return (
                <React.Fragment key={ln.date}>
                  <tr style={{ cursor: "pointer", background: op ? "#f6f8fb" : "#fff" }} onClick={() => setOpenDay((o) => ({ ...o, [ln.date]: !op }))}>
                    <td style={{ ...td, textAlign: "left", whiteSpace: "nowrap" }}>
                      <i className={"bi " + (op ? "bi-chevron-down" : "bi-chevron-right")} style={{ fontSize: 10, color: "var(--ink-4)", marginRight: 5 }} />
                      <b className="tnum">{fmtDate(ln.date)}</b>
                    </td>
                    <td style={{ ...td, textAlign: "left" }}>{ln.driver || dash}</td>
                    <td style={td}>
                      <span className="tnum">{s.trips}</span>
                      {s.warn ? <i className="bi bi-exclamation-triangle-fill" title={s.warn + " chuyến chưa ra tiền / cần kiểm tra"} style={{ color: "#d97706", marginLeft: 5 }} /> : null}
                    </td>
                    {sumCells(s)}
                    <td style={{ ...td, whiteSpace: "nowrap" }} onClick={(e) => e.stopPropagation()}>
                      {link(ln.date) && <a href={link(ln.date)} target="_blank" rel="noreferrer" title="Mở Lộ trình ngày này (đúng xe) để đối soát" style={{ fontSize: 11.5, fontWeight: 600, color: "var(--accent)", textDecoration: "none" }}>Lộ trình <i className="bi bi-box-arrow-up-right" style={{ fontSize: 9.5 }} /></a>}
                    </td>
                  </tr>
                  {op && ln.groups.map((g, gi) => {
                    const sp = splitGroup(g);
                    const has = BUCKETS.some((b) => sp[b.k].length);
                    return (
                      <tr key={gi}>
                        <td colSpan={9} style={{ padding: "6px 8px 8px 28px", borderBottom: "1px solid var(--line-2)", background: g.note ? "#fffaf0" : "#fcfcfd" }}>
                          <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap", fontSize: 12 }}>
                            <span className="tnum" style={{ color: "var(--ink-4)", fontWeight: 600 }}>{g.orphan ? <i className="bi bi-link-45deg" /> : "#" + (gi + 1)}</span>
                            <span style={{ fontWeight: 600, color: "var(--ink-2)" }}>{g.route || "—"}</span>
                            {g.cont && <span className="tnum" style={{ fontSize: 11.5, fontWeight: 700, padding: "1px 7px", borderRadius: 6, background: "#eef1f5", color: "var(--ink-2)" }}>{g.cont}</span>}
                            {g.mode && <span style={{ fontSize: 11, color: "var(--ink-4)" }}>{MODE_LABEL[g.mode] || g.mode}</span>}
                            {g.fuel && <span className="tnum" style={{ fontSize: 11, color: "#2563eb" }}><i className="bi bi-fuel-pump" /> {fmtNum(Math.round(Number(g.fuel.liters) || 0))} L{g.fuel.edited ? " (sửa tay)" : ""} · dầu công ty</span>}
                          </div>
                          <div style={{ display: "flex", gap: 6, flexWrap: "wrap", marginTop: 5 }}>
                            {BUCKETS.map((b) => sp[b.k].map((it, i) => (
                              <span key={b.k + i} title={b.label} style={{ fontSize: 11.5, padding: "2px 8px", borderRadius: 999, background: b.bg, color: b.color, whiteSpace: "nowrap" }}>
                                {it.label}: <b className="tnum">{fmtVND(it.amount)}</b>{b.tag ? <span style={{ opacity: .75 }}> · {b.tag}</span> : null}
                              </span>
                            )))}
                            {!has && <span style={{ fontSize: 11.5, color: "var(--ink-4)" }}>Không có khoản chi.</span>}
                          </div>
                          {g.note && <div style={{ marginTop: 5, fontSize: 11.5, color: "#b45309" }}><i className="bi bi-exclamation-triangle-fill" /> {g.note}</div>}
                        </td>
                      </tr>
                    );
                  })}
                </React.Fragment>
              );
            })}
          </tbody>
          <tfoot><tr style={{ background: "#f6f8fb" }}>
            <td style={{ ...td, textAlign: "left" }} colSpan={2}><b>Tổng{filtered ? " (đang lọc)" : ""}</b></td>
            <td style={td}><b className="tnum">{tot.trips}</b></td>
            {sumCells(tot, true)}
            <td style={td} />
          </tr></tfoot>
        </table>
      </div>
      {itemList.length > 0 && (
        <div>
          <div style={{ fontSize: 11.5, fontWeight: 700, color: "var(--ink-2)", marginBottom: 6 }}><i className="bi bi-list-check" /> Tổng hợp theo khoản{filtered ? " (đang lọc)" : ""}</div>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(230px, 1fr))", gap: 6 }}>
            {itemList.map((x) => (
              <div key={x.bucket.k + x.label} style={{ display: "flex", alignItems: "center", gap: 8, padding: "6px 10px", borderRadius: 8, background: "#fff", border: "1px solid var(--line)", borderLeft: "3px solid " + x.bucket.color, fontSize: 12 }}>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontWeight: 600, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{x.label}</div>
                  <div style={{ fontSize: 10.5, color: "var(--ink-4)" }}>{x.bucket.label} · {x.n} lần</div>
                </div>
                <b className="tnum" style={{ color: x.bucket.color }}>{fmtVND(x.amount)}</b>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

/* LƯƠNG PHÁT SINH thêm tay cho kỳ (ngoài phí tuyến) — cộng vào "lương phải trả". */
export function ExtraPayEditor({ items = [], onChange, disabled = false }) {
  const list = Array.isArray(items) ? items : [];
  const upd = (k, np) => onChange(list.map((p, j) => (j === k ? { ...p, ...np } : p)));
  return (
    <div style={{ padding: "10px 14px", borderTop: "1px dashed var(--line)" }}>
      <div style={{ fontSize: 11, fontWeight: 700, color: "var(--accent)", marginBottom: 7 }}><i className="bi bi-plus-slash-minus" /> Lương phát sinh (thêm cho kỳ này)</div>
      <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
        {list.map((p, k) => (
          <div key={k} style={{ display: "flex", alignItems: "center", gap: 8 }}>
            <input value={p.name || ""} disabled={disabled} onChange={(e) => upd(k, { name: e.target.value })} placeholder="Tên khoản lương phát sinh" style={{ flex: 1, minWidth: 0, padding: "6px 9px", fontSize: 12.5, border: "1px solid var(--line)", borderRadius: 7, outline: "none", background: disabled ? "var(--line-2)" : "#fff" }} />
            <div style={{ width: 130 }}><Money value={p.amount} onChange={(v) => upd(k, { amount: v })} dim /></div>
            {!disabled && <button type="button" onClick={() => onChange(list.filter((_, j) => j !== k))} title="Xóa" style={{ flexShrink: 0, width: 28, height: 28, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 7, background: "#fff", color: "var(--ink-4)", cursor: "pointer" }}><I.x /></button>}
          </div>
        ))}
        {!list.length && <div style={{ fontSize: 12, color: "var(--ink-4)" }}>Chưa có khoản lương phát sinh.</div>}
      </div>
      {!disabled && <button type="button" onClick={() => onChange([...list, { name: "", amount: "" }])} style={{ marginTop: 7, display: "inline-flex", alignItems: "center", gap: 6, padding: "6px 11px", fontSize: 12, fontWeight: 600, border: "1px dashed var(--line)", borderRadius: 8, background: "#fff", color: "var(--accent)", cursor: "pointer" }}><I.plus /> Thêm lương phát sinh</button>}
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
