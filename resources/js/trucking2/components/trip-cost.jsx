import React from "react";
import { I, Money, Num, Txt, Combo, fmtVND, fmtNum, fmtDate, useIsMobile } from "@trk/lib.jsx";

// tiền tệ thô → số (chấp nhận "1.200.000", 1200000, "")
export const n = (v) => parseFloat((v ?? "").toString().replace(/[^\d.-]/g, "")) || 0;
const sumExtras = (arr) => (arr || []).reduce((a, e) => a + n(e.amount), 0);
// tổng phí 1 dòng lô = chi phí vận hành + lương nhân sự (gồm cả khoản lương khác)
export const rowTotal = (c) => n(c.veTram) + n(c.tienDuong) + n(c.troCap) + n(c.phiKhac)
  + n(c.luong) + Math.round(n(c.fuelLiters) * n(c.fuelPrice))
  + sumExtras(c.extras) + sumExtras(c.salaryExtras);
// định danh dòng (view dùng lineId, create dùng shipmentId)
export const rk = (x) => (x.lineId != null ? "L" + x.lineId : "S" + x.shipmentId);

// nhãn các khoản phí cố định của tuyến
const FEE_KEYS = ["veTram", "tienDuong", "troCap", "phiKhac", "luong"];
const FEE_LABEL = { veTram: "Vé trạm", tienDuong: "Tiền đường", troCap: "Trợ cấp", phiKhac: "Phí khác", luong: "Lương" };
// các khoản phí cố định (đặt vào cột lương/khác theo salaryParts).
//  - luong: lương đã áp theo CRU (CRU→Lương CRU, không CRU→Lương không CRU) — LUÔN tính.
//  - phiKhac: ĐÃ BỎ khỏi cấu hình; legacy → chỉ hiện khi dòng cũ còn giá trị (giữ tổng kỳ cũ đúng).
const FEE_FIELDS = [
  { k: "veTram", label: "Vé trạm" },
  { k: "tienDuong", label: "Tiền đường" },
  { k: "troCap", label: "Trợ cấp" },
  { k: "phiKhac", label: "Phí khác (tuyến · đã bỏ)", legacy: true },
  { k: "luong", label: "Lương" },
];

// Tách 1 dòng thành 2 nhóm: chi phí vận hành (công ty) vs lương nhân sự (lái xe).
export function splitLine(x) {
  const c = x.cur || {}; const sp = Array.isArray(x.salaryParts) ? x.salaryParts : [];
  const cost = [], salary = [];
  FEE_KEYS.forEach((k) => {
    const v = n(c[k]);
    const isSal = sp.includes(k);
    if (isSal) { if (v > 0 || k === "troCap") salary.push({ name: FEE_LABEL[k], amount: v }); }
    else if (v > 0) cost.push({ name: FEE_LABEL[k], amount: v });
  });
  const fuel = Math.round(n(c.fuelLiters) * n(c.fuelPrice));
  if (fuel > 0) cost.push({ name: "Dầu" + (x.axle ? " (" + x.axle + " cầu)" : ""), amount: fuel });
  (c.extras || []).forEach((e) => { if (n(e.amount) || (e.name || "").trim()) cost.push({ name: e.name || "Phí khác", amount: n(e.amount) }); });
  (c.salaryExtras || []).forEach((e) => { if (n(e.amount) || (e.name || "").trim()) salary.push({ name: e.name || "Khoản lương", amount: n(e.amount) }); });
  const costTotal = cost.reduce((a, i) => a + i.amount, 0);
  const salaryTotal = salary.reduce((a, i) => a + i.amount, 0);
  return { cost, salary, costTotal, salaryTotal };
}

// lương nhân sự (lái xe) của 1 dòng
export const lineSalary = (x) => splitLine(x).salaryTotal;

/**
 * Chẩn đoán từng mắt xích của 1 dòng → cảnh báo đúng nguyên nhân cho kế toán rà soát.
 * Kết hợp diag (vì sao auto-fill hỏng, từ server) + giá trị đang sửa (cập nhật trực tiếp).
 * sev: "err" (chặn/đỏ) | "warn" (vàng). Trả về [{code, sev, label, fix}].
 */
export function rowIssues(x) {
  const c = x.cur || {}; const d = x.diag || null;
  const bks = (x.bks || "").trim(); const date = fmtDate(x.date) || "ngày xe ra"; const out = [];

  // mắt xích XE → quyết định cầu & lít dầu
  if (!bks) out.push({ code: "bks", sev: "err", label: "Lô chưa nhập BKS vào", fix: "→ không xác định được xe / lái xe / dầu" });
  else if (d && !d.vehFound) out.push({ code: "vehicle", sev: "warn", label: `BKS ${bks} không thuộc xe MBF nội bộ`, fix: "→ không rõ số cầu để tính lít dầu (thêm xe ở Quản lý xe)" });
  else if (d && !d.hasAxle) out.push({ code: "axle", sev: "warn", label: `Xe ${bks} chưa khai số cầu (1/2 cầu)`, fix: "→ lít dầu có thể sai (sửa ở Quản lý xe)" });

  // mắt xích TUYẾN
  if (!x.matched) out.push({ code: "route", sev: "warn", label: `Kho "${x.kho || "—"}" chưa khớp tuyến nào`, fix: "→ phí tuyến chưa tự điền (thêm tuyến ở Cấu hình phí tuyến, hoặc chọn tuyến tay)" });

  // mắt xích LÁI XE
  if (!(c.driver || "").trim()) {
    let why = "";
    if (d && d.vehFound && !d.hasUsage) why = ` (xe ${bks} chưa khai lịch lái xe nào trong Quản lý xe)`;
    else if (d && d.vehFound && !d.driverFound) why = ` (chưa có lịch lái xe cho ngày ${date})`;
    out.push({ code: "driver", sev: "warn", label: "Chưa gán lái xe" + why, fix: "→ chưa tính được lương; khai lịch ở Quản lý xe hoặc chọn lái xe tay" });
  }

  // mắt xích GIÁ DẦU
  if (n(c.fuelPrice) === 0) out.push({ code: "fuel", sev: "warn", label: "Đơn giá dầu = 0", fix: (d && !d.fuelFound) ? `→ chưa có Bảng giá dầu cho ngày ${date}` : "→ nhập đơn giá dầu" });

  return out;
}

const lbl = (t) => <div style={{ fontSize: 11, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>{t}</div>;

function BreakdownBox({ title, items, total, color, highlight }) {
  return (
    <div style={{ background: highlight ? "#fffaf0" : "#fafbfc", border: `1px solid ${highlight ? "#f3e1ad" : "var(--line)"}`, borderRadius: 10, padding: "8px 12px" }}>
      <div style={{ fontSize: 11, fontWeight: 700, color, marginBottom: 5, textTransform: "uppercase", letterSpacing: "0.02em" }}>{title}</div>
      {(!items || !items.length)
        ? <div style={{ fontSize: 12, color: "var(--ink-4)" }}>—</div>
        : items.map((it, i) => (
          <div key={i} style={{ display: "flex", justifyContent: "space-between", gap: 8, fontSize: 12.5, padding: "1px 0" }} className="tnum">
            <span style={{ color: "var(--ink-3)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{it.name}</span>
            <span style={{ color: "var(--ink)", flexShrink: 0 }}>{fmtNum(it.amount)}</span>
          </div>
        ))}
      <div style={{ display: "flex", justifyContent: "space-between", gap: 8, fontSize: 12.5, fontWeight: 700, borderTop: "1px solid var(--line-2)", marginTop: 5, paddingTop: 5 }} className="tnum">
        <span style={{ color }}>Cộng</span><span style={{ color }}>{fmtNum(total)} đ</span>
      </div>
    </div>
  );
}

// 1 dòng phí cố định có thể nhập (label trái — ô tiền phải)
function FeeRow({ label, value, onChange, readOnly }) {
  return (
    <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 8, padding: "2px 0" }}>
      <span style={{ fontSize: 12.5, color: "var(--ink-3)" }}>{label}</span>
      <div style={{ width: 150, flexShrink: 0 }}>
        {readOnly ? <span className="tnum" style={{ fontSize: 12.5 }}>{fmtNum(n(value))}</span> : <Money value={value} onChange={onChange} dim />}
      </div>
    </div>
  );
}

// 1 cột (Chi phí lương / Chi phí khác) — chứa các dòng nhập + repeater + dòng Cộng
function EditCol({ title, color, highlight, total, children }) {
  return (
    <div style={{ background: highlight ? "#fffaf0" : "#fafbfc", border: `1px solid ${highlight ? "#f3e1ad" : "var(--line)"}`, borderRadius: 10, padding: "10px 12px" }}>
      <div style={{ fontSize: 11, fontWeight: 700, color, marginBottom: 8, textTransform: "uppercase", letterSpacing: "0.02em" }}>{title}</div>
      <div style={{ display: "flex", flexDirection: "column", gap: 2 }}>{children}</div>
      <div style={{ display: "flex", justifyContent: "space-between", gap: 8, fontSize: 13, fontWeight: 700, borderTop: "1px solid var(--line-2)", marginTop: 8, paddingTop: 6 }} className="tnum">
        <span style={{ color }}>Cộng</span><span style={{ color }}>{fmtNum(total)} đ</span>
      </div>
    </div>
  );
}

function ExtrasRepeater({ extras = [], onChange, readOnly, accent, addLabel = "Thêm phí khác", compact, nameOptions }) {
  const isMobile = useIsMobile();
  const set = (i, np) => onChange(extras.map((e, j) => (j === i ? { ...e, ...np } : e)));
  const add = () => onChange([...(extras || []), { name: "", amount: "", note: "" }]);
  const del = (i) => onChange(extras.filter((_, j) => j !== i));
  const aBg = accent ? "#fef3c7" : "var(--accent-weak)"; const aFg = accent || "var(--accent)";
  // Mobile: bỏ cột ghi chú khỏi hàng (tên + tiền + xóa) cho dễ thao tác
  const grid = compact ? (readOnly ? "1fr 110px" : "1fr 110px 28px")
    : isMobile ? (readOnly ? "1fr 120px" : "1fr 120px 30px")
    : (readOnly ? "1fr 130px 1fr" : "1fr 130px 1fr 30px");
  if (readOnly && !(extras || []).length) return null;
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 6, marginTop: 4 }}>
      {(extras || []).map((e, i) => (
        <div key={i} style={{ display: "flex", flexDirection: "column", gap: 6 }}>
          <div style={{ display: "grid", gridTemplateColumns: grid, gap: 6, alignItems: "center" }}>
            {nameOptions
              ? <Combo value={e.name} onChange={(x) => set(i, { name: x })} options={nameOptions} strict small placeholder="Chọn khoản…" />
              : <Txt value={e.name} onChange={(x) => set(i, { name: x })} placeholder="Tên khoản" />}
            <Money value={e.amount} onChange={(x) => set(i, { amount: x })} dim />
            {!compact && !isMobile && <Txt value={e.note} onChange={(x) => set(i, { note: x })} placeholder="Ghi chú" />}
            {!readOnly && <button type="button" onClick={() => del(i)} title="Xóa"
              style={{ width: 28, height: 30, display: "grid", placeItems: "center", border: "none", borderRadius: 7, background: "transparent", color: "var(--ink-4)", cursor: "pointer" }}
              onMouseEnter={(ev) => { ev.currentTarget.style.background = "#fce8e8"; ev.currentTarget.style.color = "var(--danger)"; }}
              onMouseLeave={(ev) => { ev.currentTarget.style.background = "transparent"; ev.currentTarget.style.color = "var(--ink-4)"; }}><I.trash /></button>}
          </div>
          {!compact && isMobile && <Txt value={e.note} onChange={(x) => set(i, { note: x })} placeholder="Ghi chú" />}
        </div>
      ))}
      {!readOnly && <button type="button" onClick={add}
        style={{ alignSelf: "flex-start", display: "inline-flex", alignItems: "center", gap: 6, padding: "5px 10px", fontSize: 12.5, fontWeight: 600, border: "none", borderRadius: 7, background: aBg, color: aFg, cursor: "pointer" }}>
        <I.plus /> {addLabel}
      </button>}
    </div>
  );
}

/**
 * Trình sửa phí xe cho 1 danh sách lô — dùng chung cho trang Tạo & Xem/Sửa.
 * rows: [{shipmentId|lineId, booking, route, kho, bks, axle, date, matched, usedIn[], cur{}, sug{}}]
 */
export function TripEditor({ rows, onRows, routeFees = [], drivers = [], costItems = [], salaryItems = [], readOnly = false }) {
  const [open, setOpen] = React.useState({});   // nhóm lái xe nào đang mở (mặc định thu gọn)
  const toggle = (k) => setOpen((o) => ({ ...o, [k]: !o[k] }));
  const upd = (key, np) => onRows(rows.map((x) => (rk(x) === key ? { ...x, cur: { ...x.cur, ...np } } : x)));
  const recalc = (key) => onRows(rows.map((x) => (rk(x) === key && x.sug ? { ...x, cur: { ...x.sug, extras: x.cur.extras || [], salaryExtras: x.cur.salaryExtras || [] } } : x)));
  const applyRoute = (key, label) => {
    const rf = routeFees.find((f) => f.route === label); if (!rf) return;
    onRows(rows.map((x) => {
      if (rk(x) !== key) return x;
      const liters = x.axle === "2" ? rf.dau2 : rf.dau1;
      // Lương áp theo CRU của lô: tích CRU → Lương CRU; không tích → Lương không CRU. Không áp lại Phí khác (đã bỏ).
      const luongAp = x.cur.cru ? rf.luong : (rf.luongNoCru || "0");
      return { ...x, matched: true, salaryParts: rf.salaryParts || x.salaryParts, cur: { ...x.cur, veTram: rf.veTram, tienDuong: rf.tienDuong, troCap: rf.troCap, phiKhac: "0", luong: luongAp, fuelLiters: liters } };
    }));
  };

  // gom các lô theo LÁI XE (toggle từng nhóm) — header = tên + tổng tiền nhận
  const labelOf = (val) => { const o = drivers.find((d) => (d && typeof d === "object" ? d.value : d) === val); return o ? (typeof o === "object" ? o.label : o) : val; };
  const gOrder = []; const gMap = {};
  rows.forEach((x) => { const key = (x.cur.driver || "").trim() || "(chưa gán)"; if (!gMap[key]) { gMap[key] = []; gOrder.push(key); } gMap[key].push(x); });
  const groups = gOrder.map((key) => ({ key, label: key === "(chưa gán)" ? "Chưa gán lái xe" : labelOf(key), rows: gMap[key], total: gMap[key].reduce((a, x) => a + lineSalary(x), 0), cost: gMap[key].reduce((a, x) => a + splitLine(x).costTotal, 0) }));

  if (!rows.length) return <div style={{ padding: "30px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5 }}>Không có lô nào trong kỳ.</div>;

  const renderCard = (x) => {
        const c = x.cur; const key = rk(x); const fuel = Math.round(n(c.fuelLiters) * n(c.fuelPrice));
        const sp = Array.isArray(x.salaryParts) ? x.salaryParts : []; const sd = splitLine(x);
        const salTag = (k) => sp.includes(k) ? <span style={{ fontSize: 9.5, fontWeight: 700, color: "#b45309", background: "#fef3c7", padding: "0 4px", borderRadius: 4, marginLeft: 4, verticalAlign: "middle" }}>lương</span> : null;
        const issues = rowIssues(x); const hasErr = issues.some((i) => i.sev === "err");
        const edge = hasErr ? "var(--danger)" : (issues.length ? "var(--warn)" : "var(--line)");
        return (
          <div key={key} style={{ background: "#fff", border: `1px solid ${edge}`, borderRadius: 12, padding: "14px 16px" }}>
            <div style={{ display: "flex", alignItems: "center", gap: 12, flexWrap: "wrap", marginBottom: issues.length ? 8 : 10, paddingBottom: issues.length ? 8 : 10, borderBottom: "1px solid var(--line-2)" }}>
              <div style={{ fontWeight: 700 }} className="tnum">{x.booking || "—"}</div>
              <span style={{ fontSize: 12.5, fontWeight: 600, color: "var(--ink-2)" }} title="Tuyến đi qua các kho (phí xe khớp theo tuyến này)"><i className="bi bi-geo-alt" style={{ color: "var(--accent)", marginRight: 4 }} />{x.khoRoute || x.kho || "—"}</span>
              <span style={{ fontSize: 12, color: "var(--ink-4)" }} className="tnum">BKS: {x.bks || "—"}{x.axle ? " · " + x.axle + " cầu" : ""}</span>
              <span style={{ fontSize: 12, color: "var(--ink-4)" }} className="tnum">Ra: {x.date || "—"}</span>
              {c.cru && <span style={{ fontSize: 11, fontWeight: 700, color: "#b45309", background: "#fef3c7", padding: "2px 8px", borderRadius: 999 }}>CRU</span>}
              {(x.usedIn || []).length > 0 && <span title={"Đã có trong: " + x.usedIn.join(", ")} style={{ fontSize: 11, fontWeight: 700, color: "var(--danger)", background: "#fce8e8", padding: "2px 8px", borderRadius: 999 }}>⚠ đã có ở kỳ {x.usedIn.join(", ")}</span>}
              <div style={{ flex: 1 }} />
              {!readOnly && x.sug && <button type="button" onClick={() => recalc(key)} title="Tính lại theo cấu hình"
                style={{ display: "inline-flex", alignItems: "center", gap: 5, fontSize: 12, fontWeight: 600, color: "var(--ink-3)", border: "1px solid var(--line)", borderRadius: 8, background: "#fff", padding: "5px 10px", cursor: "pointer" }}><I.fx /> Tính lại</button>}
            </div>
            {issues.length > 0 && (
              <div style={{ display: "flex", flexDirection: "column", gap: 4, marginBottom: 10, padding: "8px 10px", borderRadius: 8, background: hasErr ? "#fce8e8" : "#fff8e6", border: `1px solid ${hasErr ? "#f5c6c6" : "#f3e1ad"}` }}>
                {issues.map((it) => (
                  <div key={it.code} style={{ fontSize: 12, display: "flex", gap: 6, alignItems: "baseline", lineHeight: 1.45 }}>
                    <span style={{ color: it.sev === "err" ? "var(--danger)" : "#b45309", fontWeight: 700 }}>●</span>
                    <span><b style={{ color: it.sev === "err" ? "var(--danger)" : "#92600a" }}>{it.label}</b> <span style={{ color: "var(--ink-3)" }}>{it.fix}</span></span>
                  </div>
                ))}
              </div>
            )}
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, marginBottom: 10 }}>
              <div>{lbl("Lái xe")}<Combo value={c.driver} onChange={(v) => upd(key, { driver: v })} options={drivers} placeholder="Chọn lái xe…" /></div>
              {!readOnly && <div>{lbl(x.matched ? "Áp tuyến khác (tùy chọn)" : "Chọn tuyến (chưa khớp tự động)")}
                <Combo value="" onChange={(label) => applyRoute(key, label)} options={routeFees.map((r) => r.route)} placeholder="Chọn tuyến trong Phí tuyến đường…" /></div>}
            </div>
            {/* NHẬP NGAY TRONG 2 CỘT: Chi phí lương (lái xe) | Chi phí khác — vận hành (công ty) */}
            {(() => {
              const show = (f) => f.legacy ? n(c[f.k]) > 0 : true;   // phí khác (legacy) chỉ hiện khi dòng cũ còn giá trị
              const salFields = FEE_FIELDS.filter((f) => show(f) && sp.includes(f.k));
              const costFields = FEE_FIELDS.filter((f) => show(f) && !sp.includes(f.k));
              return (
                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, borderTop: "1px solid var(--line-2)", paddingTop: 10 }}>
                  <EditCol title="Chi phí lương (lái xe)" color="#b45309" highlight total={sd.salaryTotal}>
                    {salFields.map((f) => <FeeRow key={f.k} label={f.label} value={c[f.k]} onChange={(v) => upd(key, { [f.k]: v })} readOnly={readOnly} />)}
                    {!salFields.length && <div style={{ fontSize: 12, color: "var(--ink-4)" }}>Chưa có khoản lương cố định (tích "lương NS" ở Phí tuyến).</div>}
                    <ExtrasRepeater extras={c.salaryExtras || []} onChange={(ar) => upd(key, { salaryExtras: ar })} readOnly={readOnly} accent="#b45309" addLabel="Thêm khoản lương" compact nameOptions={salaryItems} />
                  </EditCol>
                  <EditCol title="Chi phí khác — vận hành (công ty)" color="var(--ink-2)" total={sd.costTotal}>
                    {costFields.map((f) => <FeeRow key={f.k} label={f.label} value={c[f.k]} onChange={(v) => upd(key, { [f.k]: v })} readOnly={readOnly} />)}
                    <div style={{ padding: "4px 0" }}>
                      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 8, marginBottom: 4 }}>
                        <span style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Dầu{x.axle ? " (" + x.axle + " cầu)" : ""}</span>
                        <b className="tnum" style={{ fontSize: 12.5, color: "var(--ink)" }}>{fmtNum(fuel)}</b>
                      </div>
                      {!readOnly && <div style={{ display: "flex", gap: 6 }}>
                        <div style={{ flex: 1 }}><Num value={c.fuelLiters} onChange={(v) => upd(key, { fuelLiters: v })} suffix="lít" /></div>
                        <div style={{ flex: 1 }}><Money value={c.fuelPrice} onChange={(v) => upd(key, { fuelPrice: v })} dim /></div>
                      </div>}
                    </div>
                    <ExtrasRepeater extras={c.extras || []} onChange={(ar) => upd(key, { extras: ar })} readOnly={readOnly} addLabel="Thêm phí khác" compact nameOptions={costItems} />
                  </EditCol>
                </div>
              );
            })()}
            <div style={{ textAlign: "right", fontSize: 14, fontWeight: 700, marginTop: 8 }} className="tnum">
              Kế hoạch lô: <span style={{ color: "var(--accent)" }}>{fmtVND(rowTotal(c))}</span>
              {x.spent && x.spent.total > 0 && <span style={{ fontSize: 12.5, fontWeight: 600, color: "var(--ink-4)", marginLeft: 12 }}>· Đã chi <span style={{ color: "var(--good)" }}>{fmtVND(x.spent.total)}</span></span>}
            </div>
          </div>
        );
  };

  // Tổng hợp KẾ HOẠCH (route fee) vs ĐÃ CHI (duyệt chi theo lô) → CÒN LẠI, tách lương / công ty.
  const agg = rows.reduce((a, x) => {
    const sd = splitLine(x); const sp = x.spent || {};
    a.planSalary += sd.salaryTotal; a.planCompany += sd.costTotal;
    a.spentSalary += n(sp.salary); a.spentCompany += n(sp.company);
    return a;
  }, { planSalary: 0, planCompany: 0, spentSalary: 0, spentCompany: 0 });
  const planTotal = agg.planSalary + agg.planCompany;
  const spentTotal = agg.spentSalary + agg.spentCompany;
  const remainTotal = planTotal - spentTotal;
  const summaryCols = [
    { label: "Kế hoạch", tot: planTotal, sal: agg.planSalary, comp: agg.planCompany, col: "var(--ink)" },
    { label: "Đã chi", tot: spentTotal, sal: agg.spentSalary, comp: agg.spentCompany, col: "var(--good)" },
    { label: "Còn lại", tot: remainTotal, sal: agg.planSalary - agg.spentSalary, comp: agg.planCompany - agg.spentCompany, col: remainTotal > 0 ? "var(--warn)" : "var(--good)" },
  ];

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
      <div style={{ border: "1px solid var(--line)", borderRadius: 12, background: "#fff", padding: "13px 16px" }}>
        <div style={{ fontSize: 12, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.03em", marginBottom: 10 }}>Tổng hợp kỳ · Kế hoạch / Đã chi / Còn lại</div>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 12 }}>
          {summaryCols.map((s, i) => (
            <div key={i} style={{ borderLeft: i ? "1px solid var(--line-2)" : "none", paddingLeft: i ? 14 : 0 }}>
              <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginBottom: 2 }}>{s.label}</div>
              <div className="tnum" style={{ fontSize: 19, fontWeight: 700, color: s.col, letterSpacing: "-0.01em" }}>{fmtVND(s.tot)}</div>
              <div style={{ fontSize: 11, color: "var(--ink-4)", marginTop: 3 }} className="tnum"><span style={{ color: "#b45309" }}>Lương</span> {fmtNum(s.sal)} · <span style={{ color: "var(--accent)" }}>Công ty</span> {fmtNum(s.comp)}</div>
            </div>
          ))}
        </div>
        <div style={{ fontSize: 11, color: "var(--ink-4)", marginTop: 9, lineHeight: 1.5 }}><b style={{ color: "var(--ink-3)" }}>Kế hoạch</b> = phí tuyến (snapshot kỳ) · <b style={{ color: "var(--ink-3)" }}>Đã chi</b> = duyệt chi theo lô (₫ Duyệt chi ở Lô hàng) · <b style={{ color: "var(--ink-3)" }}>Còn lại</b> = Kế hoạch − Đã chi.</div>
      </div>
      {groups.map((g) => {
        const isOpen = !!open[g.key];
        return (
          <div key={g.key} style={{ border: "1px solid var(--line)", borderRadius: 12, overflow: "hidden", background: "#fff" }}>
            <button type="button" onClick={() => toggle(g.key)}
              style={{ width: "100%", display: "flex", alignItems: "center", gap: 12, padding: "12px 16px", border: "none", borderBottom: isOpen ? "1px solid var(--line-2)" : "none", background: isOpen ? "var(--bg)" : "#fff", cursor: "pointer", textAlign: "left" }}>
              <span style={{ transform: `rotate(${isOpen ? 0 : -90}deg)`, transition: "transform .12s", color: "var(--ink-4)", display: "grid" }}><I.chev /></span>
              <span style={{ width: 30, height: 30, borderRadius: "50%", background: "var(--accent-weak)", color: "var(--accent)", display: "grid", placeItems: "center", fontWeight: 700, fontSize: 13, flexShrink: 0 }}>{(g.label.trim()[0] || "?").toUpperCase()}</span>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontWeight: 700, fontSize: 14, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{g.label}</div>
                <div style={{ fontSize: 12, color: "var(--ink-4)" }} className="tnum">{g.rows.length} lô · chi phí khác (vận hành) {fmtNum(g.cost)} đ</div>
              </div>
              <div style={{ textAlign: "right", flexShrink: 0 }}>
                <div style={{ fontSize: 11, color: "var(--ink-4)" }}>Tổng tiền nhận</div>
                <div className="tnum" style={{ fontSize: 16, fontWeight: 700, color: "#b45309" }}>{fmtNum(g.total)} đ</div>
              </div>
            </button>
            {isOpen && <div style={{ display: "flex", flexDirection: "column", gap: 12, padding: "14px" }}>{g.rows.map(renderCard)}</div>}
          </div>
        );
      })}
    </div>
  );
}
