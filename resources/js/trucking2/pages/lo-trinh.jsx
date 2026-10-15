import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useEffect, useRef } = React;
import { I, useIsMobile, DateField, Combo, Modal, Btn, Money, fmtVND, fmtNum, toNum, axleLabel } from "@trk/lib.jsx";
import { vietqrImg } from "@trk/banks.js";

// Khối thông tin NH của lái nhận tiền: copy STK + QR VietQR quét chuyển khoản. Gọn — QR ẩn, bấm mới hiện.
function PayBankBox({ banks, amount, addInfo }) {
  const list = (banks || []).filter((b) => (b.number || "").trim());
  const [bi, setBi] = useState(0);
  const [showQr, setShowQr] = useState(false);
  if (!list.length) return null;
  const b = list[Math.min(bi, list.length - 1)];
  const copy = () => { try { navigator.clipboard.writeText((b.number || "").replace(/\s/g, "")); window.trkToast && window.trkToast("Đã copy số TK"); } catch (e) {} };
  const qr = showQr ? vietqrImg({ bin: b.bin, account: b.number, name: b.holder, amount, info: addInfo }) : "";
  return (
    <div style={{ marginTop: 8, border: "1px solid var(--line)", borderRadius: 10, background: "#fafcff", overflow: "hidden" }}>
      <div style={{ display: "flex", alignItems: "center", gap: 8, padding: "8px 10px" }}>
        <i className="bi bi-bank2" style={{ color: "var(--accent)", fontSize: 14 }} />
        {list.length > 1 ? (
          <select value={bi} onChange={(e) => setBi(+e.target.value)} style={{ fontSize: 12, fontWeight: 700, border: "1px solid var(--line)", borderRadius: 6, padding: "2px 4px", background: "#fff" }}>
            {list.map((x, k) => <option key={k} value={k}>{x.bank || "NH"}</option>)}
          </select>
        ) : (
          <span style={{ fontWeight: 700, fontSize: 12.5 }}>{b.bank || "Ngân hàng"}</span>
        )}
        <span className="tnum" style={{ fontWeight: 700, fontSize: 13 }}>{b.number || "—"}</span>
        <button type="button" onClick={copy} title="Copy số TK" style={{ width: 24, height: 24, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 6, background: "#fff", color: "var(--ink-4)", cursor: "pointer" }}><i className="bi bi-clipboard" style={{ fontSize: 11 }} /></button>
        {b.holder ? <span style={{ fontSize: 11.5, color: "var(--ink-4)" }}>· {b.holder}</span> : null}
        <span style={{ flex: 1 }} />
        {b.bin ? (
          <button type="button" onClick={() => setShowQr((v) => !v)} style={{ display: "inline-flex", alignItems: "center", gap: 5, fontSize: 12, fontWeight: 600, padding: "4px 9px", border: "1px solid var(--accent)", borderRadius: 7, background: showQr ? "var(--accent)" : "#fff", color: showQr ? "#fff" : "var(--accent)", cursor: "pointer" }}><i className="bi bi-qr-code" /> {showQr ? "Ẩn QR" : "Mã QR"}</button>
        ) : <span style={{ fontSize: 11, color: "var(--ink-4)" }}>Chọn NH ở Cài đặt để có QR</span>}
      </div>
      {qr ? (
        <div style={{ display: "flex", justifyContent: "center", padding: "4px 10px 12px" }}>
          <img src={qr} alt="VietQR" style={{ width: 220, maxWidth: "100%", borderRadius: 8, border: "1px solid var(--line-2)" }} />
        </div>
      ) : null}
    </div>
  );
}

/* Popup chi cho lái xe (theo ngày + xe): tổng các khoản chi trong ngày theo Quy chế lương chuyến + chọn lái nhận. */
function PayPopup({ truck, date, drivers, routeFeesUrl, onClose, onSaved }) {
  const [driver, setDriver] = useState(truck.payDriver || "");
  const [paid, setPaid] = useState(!!truck.paid);
  const [saving, setSaving] = useState(false);
  // Mỗi CHUYẾN = 1 nhóm (backend đã gom, kể cả chuyến chưa tính được theo quy chế → có note cảnh báo).
  const groups = truck.payGroups || [];
  // Chi khác phát sinh THỦ CÔNG theo từng chuyến — state song song groups, init từ g.manual (đã lưu).
  const [extras, setExtras] = useState(() => groups.map((g) => (g.manual || []).map((m) => ({ ...m }))));
  const setGE = (gi, arr) => setExtras((prev) => prev.map((x, j) => (j === gi ? arr : x)));
  const updEx = (gi, k, np) => setGE(gi, (extras[gi] || []).map((m, j) => (j === k ? { ...m, ...np } : m)));
  const addEx = (gi) => setGE(gi, [...(extras[gi] || []), { name: "", amount: "", perDay: true }]);
  const delEx = (gi, k) => setGE(gi, (extras[gi] || []).filter((_, j) => j !== k));

  // LOẠI KHOẢN chi trong ngày: "Tính lương" (cộng lương cuối tháng) | "Chi hộ" (đưa lái để chi hộ — KHÔNG tính lương).
  // chSel[gi] = Set khóa (ck) các khoản phí tuyến của chuyến gi đang chọn Chi hộ; chi khác thủ công mang m.choHo.
  const frozen = !!truck.frozen;
  const ckOf = (it) => it.ck || it.key;
  const [chSel, setChSel] = useState(() => groups.map((g) => new Set((g.items || []).filter((it) => it.choHo).map(ckOf))));
  const setCh = (gi, ck, on) => setChSel((prev) => prev.map((st, j) => { if (j !== gi) return st; const n = new Set(st); on ? n.add(ck) : n.delete(ck); return n; }));
  // SỐ TIỀN NHẬP TAY cho khoản editable (tiền đường chuyến HP → ICD/Tri Phương: quy chế không có định mức).
  // amt[gi] = {key: chuỗi tiền}; "" = chưa nhập (chuyến còn cảnh báo).
  // Khoản editable có thể ở cả chi trong ngày (items) lẫn kỳ lương (payrollItems — lương chuyến xe ra không kéo cont).
  const editables = (g) => [...(g.items || []), ...(g.payrollItems || [])].filter((it) => it.editable);
  const [amt, setAmt] = useState(() => groups.map((g) => Object.fromEntries(editables(g).map((it) => [it.key, it.edited ? String(it.amount) : ""]))));
  const setAmtOf = (gi, key, v) => setAmt((prev) => prev.map((o, j) => (j === gi ? { ...o, [key]: v } : o)));
  const rawOf = (gi, it) => String((amt[gi] || {})[it.key] ?? "").trim();
  // Ô trống = theo quy chế (base); khoản BẮT BUỘC nhập (required — quy chế không có định mức) trống = 0.
  const amtOf = (gi, it) => (!it.editable ? (it.amount || 0) : rawOf(gi, it) === "" ? (it.required ? 0 : (it.base || 0)) : toNum(rawOf(gi, it)));
  const pendingInput = (g, gi) => editables(g).some((it) => it.required && rawOf(gi, it) === "");
  // Ô nhập tiền của khoản sửa được: placeholder = mức quy chế; đã sửa khác quy chế → ghi chú + nút về quy chế.
  const amtInput = (gi, it) => {
    const raw = rawOf(gi, it);
    const changed = !it.required && raw !== "" && toNum(raw) !== (it.base || 0);
    return (
      <div style={{ width: 130, flexShrink: 0, textAlign: "right" }}>
        <Money value={(amt[gi] || {})[it.key] ?? ""} onChange={(x) => setAmtOf(gi, it.key, x)} placeholder={it.required ? "Nhập tiền" : fmtNum(it.base || 0)} />
        {changed && (
          <button type="button" onClick={() => setAmtOf(gi, it.key, "")} title="Bỏ sửa tay — tính lại theo quy chế"
            style={{ border: "none", background: "none", padding: "2px 0 0", fontSize: 10.5, fontWeight: 600, color: "#b45309", cursor: "pointer" }}>
            sửa tay · quy chế {fmtVND(it.base || 0)} ↺
          </button>
        )}
      </div>
    );
  };
  const computedSub = (g, gi) => (g.items || []).reduce((s, it) => s + amtOf(gi, it), 0);
  const manualSub = (gi) => (extras[gi] || []).reduce((s, m) => s + (m.perDay !== false ? toNum(m.amount) : 0), 0);
  const chohoSub = (g, gi) => (g.items || []).reduce((s, it) => s + ((chSel[gi] || new Set()).has(ckOf(it)) ? amtOf(gi, it) : 0), 0)
    + (extras[gi] || []).reduce((s, m) => s + (m.perDay !== false && m.choHo ? toNum(m.amount) : 0), 0);
  const groupTotal = (g, gi) => computedSub(g, gi) + manualSub(gi);
  // Chuyến cần cảnh báo: còn khoản chờ nhập tay; hoặc chưa ra tiền theo quy chế mà chưa thêm chi khác.
  const isWarn = (g, gi) => (g.needInput ? pendingInput(g, gi) : (!!g.note && manualSub(gi) <= 0));
  const total = groups.reduce((s, g, gi) => s + groupTotal(g, gi), 0);
  const chohoTotal = groups.reduce((s, g, gi) => s + chohoSub(g, gi), 0);
  // CHI PHÍ KHÁC (chi khác phát sinh chọn "Chi phí khác"): đưa lái hôm nay, KHÔNG tính lương, thống kê riêng với chi hộ
  const khacTotal = extras.reduce((s, arr) => s + (arr || []).reduce((a, m) => a + (m.perDay !== false && m.khac ? toNum(m.amount) : 0), 0), 0);
  // Ô loại cho CHI KHÁC PHÁT SINH: 3 loại (khoản theo quy chế đã khóa loại nên chỉ dòng thủ công mới chọn)
  const exTypeSel = (m, onChange) => {
    const v = m.khac ? "khac" : m.choHo ? "choho" : "luong";
    const tone = v === "choho" ? ["#f0c36d", "#fff7e6", "#9a6700"] : v === "khac" ? ["#c4b5fd", "#f5f3ff", "#6d28d9"] : ["var(--line)", "#fff", "var(--ink-3)"];
    return (
      <select value={v} disabled={frozen} onChange={(e) => onChange({ choHo: e.target.value === "choho", khac: e.target.value === "khac" })}
        title={frozen ? "Ngày đã chốt — bỏ chốt để đổi" : "Tính lương: cộng vào lương cuối tháng · Chi hộ: tiền đưa lái chi hộ (vé, đường…) · Chi phí khác: chi phí công ty lái chi giúp (sửa xe, rửa xe, gửi xe…) — 2 loại sau KHÔNG tính lương"}
        style={{ flexShrink: 0, fontSize: 11.5, fontWeight: 600, padding: "3px 4px", borderRadius: 6, cursor: frozen ? "default" : "pointer", border: "1px solid " + tone[0], background: tone[1], color: tone[2] }}>
        <option value="luong">Tính lương</option>
        <option value="choho">Chi hộ</option>
        <option value="khac">Chi phí khác</option>
      </select>
    );
  };
  // Ô chọn loại khoản (dùng cho khoản phí tuyến + chi khác)
  const typeSel = (isChoho, onChange) => (
    <select value={isChoho ? "choho" : "luong"} disabled={frozen} onChange={(e) => onChange(e.target.value === "choho")}
      title={frozen ? "Ngày đã chốt — bỏ chốt để đổi" : "Tính lương: cộng vào lương cuối tháng · Chi hộ: tiền đưa lái để chi hộ, KHÔNG tính lương"}
      style={{ flexShrink: 0, fontSize: 11.5, fontWeight: 600, padding: "3px 4px", borderRadius: 6, cursor: frozen ? "default" : "pointer",
        border: "1px solid " + (isChoho ? "#f0c36d" : "var(--line)"), background: isChoho ? "#fff7e6" : "#fff", color: isChoho ? "#9a6700" : "var(--ink-3)" }}>
      <option value="luong">Tính lương</option>
      <option value="choho">Chi hộ</option>
    </select>
  );
  // (Dầu = chi phí công ty đã hiển thị ở timeline Lộ trình — KHÔNG đưa vào popup này cho đỡ rối.)
  // Cảnh báo: chuyến KHÔNG khớp phí tuyến VÀ chưa thêm chi khác thủ công nào.
  const warnCount = groups.filter((g, gi) => isWarn(g, gi)).length;

  const save = () => {
    if (saving) return; setSaving(true);
    const extraItems = [];
    groups.forEach((g, gi) => (extras[gi] || []).forEach((m) => {
      if (!(m.name || "").trim() && !toNum(m.amount)) return;   // bỏ dòng rỗng
      extraItems.push({ cont: g.cont || "", name: m.name || "", amount: m.amount, perDay: m.perDay !== false, choHo: !m.khac && !!m.choHo, khac: !!m.khac });
    }));
    // Khoản phí tuyến chọn Chi hộ theo từng chuyến (gửi đủ mọi chuyến để bỏ chọn cũng được lưu)
    const chohoItems = groups.map((g, gi) => ({ cont: g.cont || "", keys: [...(chSel[gi] || [])] }));
    // Số tiền nhập tay (gửi đủ mọi khoản editable — xóa trắng = bỏ nhập tay)
    const amountItems = groups.flatMap((g, gi) => editables(g).map((it) => ({ cont: g.cont || "", key: it.key, amount: (amt[gi] || {})[it.key] ?? "" })));
    window.trkApi("POST", (window.__TRK.routes || {}).savePay, { date, bks: truck.bks, driver, paid, extraItems, chohoItems, amountItems })
      .then((r) => { if (r && r.ok) { window.trkToast && window.trkToast("Đã lưu chi cho lái"); onSaved({ payDriver: driver, paid }); onClose(); } else { window.trkToast && window.trkToast("Lưu thất bại", "error"); setSaving(false); } })
      .catch(() => { window.trkToast && window.trkToast("Lỗi kết nối", "error"); setSaving(false); });
  };
  const footer = (
    <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 10 }}>
      <a href={routeFeesUrl} target="_blank" rel="noreferrer" style={{ fontSize: 12.5, color: "var(--accent)", textDecoration: "none" }}><i className="bi bi-gear" /> Sửa quy chế lương</a>
      <div style={{ display: "flex", gap: 8 }}><Btn onClick={onClose}>Đóng</Btn><Btn variant="primary" onClick={save} disabled={saving}>{saving ? "Đang lưu…" : "Lưu"}</Btn></div>
    </div>
  );
  // Hàng nhập 1 khoản chi khác thủ công (tên + tiền + xóa)
  const exRow = (gi, m, k) => (
    <div key={"m" + k} style={{ display: "flex", alignItems: "center", gap: 8, padding: "6px 12px", borderTop: "1px solid var(--line-2)", background: "#fcfdff" }}>
      <i className="bi bi-plus-circle" style={{ color: "var(--accent)", fontSize: 12 }} />
      <input value={m.name || ""} onChange={(e) => updEx(gi, k, { name: e.target.value })} placeholder="Tên khoản phát sinh"
        style={{ flex: 1, minWidth: 0, padding: "5px 8px", fontSize: 12.5, border: "1px solid var(--line)", borderRadius: 7, outline: "none" }} />
      {exTypeSel(m, (np) => updEx(gi, k, np))}
      <div style={{ width: 130 }}><Money value={m.amount} onChange={(x) => updEx(gi, k, { amount: x })} dim /></div>
      <button type="button" onClick={() => delEx(gi, k)} title="Xóa khoản" style={{ flexShrink: 0, width: 26, height: 26, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 7, background: "#fff", color: "var(--ink-4)", cursor: "pointer" }}><I.x /></button>
    </div>
  );
  return (
    <Modal title={"Chi cho lái xe · " + truck.bks} subtitle={"Ngày " + date + " · theo quy chế lương chuyến + chi khác phát sinh từng chuyến"} onClose={onClose} footer={footer} width={580} icon={<i className="bi bi-cash-coin" />}>
      <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
        {groups.length === 0 ? (
          <div style={{ padding: "14px", fontSize: 13, color: "var(--ink-4)", background: "#fafbfc", border: "1px dashed var(--line)", borderRadius: 10 }}>Xe không có chuyến nào trong ngày.</div>
        ) : (
          <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
            <div style={{ display: "flex", alignItems: "flex-start", gap: 8, padding: "9px 12px", fontSize: 12.5, lineHeight: 1.5, color: "#1f4f9e", background: "#eff5ff", border: "1px solid #cfe0fb", borderRadius: 10 }}>
              <i className="bi bi-info-circle-fill" style={{ marginTop: 2 }} />
              <span>Mỗi khoản thuộc 1 loại. <b>Tính lương</b> = cộng vào <b>lương cuối tháng</b> của lái (kỳ lương). <b style={{ color: "#9a6700" }}>Chi hộ</b> = tiền đưa lái để chi hộ (vé, đường…). <b style={{ color: "#6d28d9" }}>Chi phí khác</b> = chi phí công ty lái chi giúp (sửa xe, rửa xe, gửi xe…). Chi hộ và chi phí khác vẫn nằm trong <b>tổng chi hôm nay</b> nhưng <b>KHÔNG tính vào lương cuối tháng</b>. Dòng vàng “kỳ lương” là khoản không chi trong ngày, trả vào kỳ lương.{frozen ? <> <b>Ngày đã chốt</b> — bỏ chốt để đổi loại khoản.</> : null}</span>
            </div>
            {warnCount > 0 && (
              <div style={{ display: "flex", alignItems: "flex-start", gap: 8, padding: "9px 12px", fontSize: 12.5, color: "#a05a00", background: "#fff7e9", border: "1px solid #f1d59a", borderRadius: 10 }}>
                <i className="bi bi-exclamation-triangle-fill" style={{ marginTop: 1 }} />
                <span><b>{warnCount}/{groups.length} chuyến chưa ra tiền</b> — xem lý do ở từng chuyến (quy chế, tỉnh cũ của kho…), hoặc thêm <b>chi khác</b> phát sinh cho chuyến đó bên dưới.</span>
              </div>
            )}
            {groups.map((g, gi) => {
              const warn = isWarn(g, gi);
              const gt = groupTotal(g, gi);
              return (
              <div key={gi} style={{ border: "1px solid " + (warn ? "#f1d59a" : "var(--line)"), borderRadius: 10, overflow: "hidden" }}>
                {/* Header tuyến: lộ trình + cont + tổng phụ của chuyến */}
                <div style={{ display: "flex", alignItems: "center", gap: 8, padding: "8px 12px", background: warn ? "#fff7e9" : "var(--accent-weak)", borderBottom: "1px solid var(--line-2)" }}>
                  <i className={"bi " + (warn ? "bi-exclamation-triangle-fill" : "bi-signpost-split")} style={{ color: warn ? "#c9820f" : "var(--accent)", fontSize: 13 }} />
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ display: "flex", alignItems: "baseline", gap: 6, flexWrap: "wrap" }}>
                      <span className="tnum" style={{ fontWeight: 700, fontSize: 12.5, color: warn ? "#a05a00" : "var(--accent)" }}>{g.route}</span>
                      {g.cont ? <span className="tnum" style={{ fontSize: 11, color: "var(--ink-4)", fontWeight: 600, whiteSpace: "nowrap" }}>· cont {g.cont}</span> : null}
                    </div>
                    {/* Mức đã áp theo QUY CHẾ (bảng KCN / bảng vùng theo tỉnh cũ của kho) — dòng riêng cho gọn */}
                    {g.matched && g.feeSource === "regulation" && (
                      <span className="tnum" style={{ display: "inline-block", marginTop: 3, fontSize: 10.5, fontWeight: 600, color: "#6d28d9", background: "#f1ebff", padding: "1px 7px", borderRadius: 999 }}
                        title={"Tính theo quy chế lương chuyến" + (g.feeBook ? " — " + g.feeBook : "") + (g.feeFrom ? " (áp dụng từ " + g.feeFrom.split("-").reverse().join("/") + ")" : "")}>
                        <i className="bi bi-journal-text" /> quy chế · {g.feeRule}
                      </span>
                    )}
                  </div>
                  <span className="tnum" style={{ fontWeight: 700, fontSize: 12.5, color: warn ? "#a05a00" : "var(--ink-1)" }}>{warn ? "—" : fmtVND(gt)}</span>
                </div>
                {/* Khoản theo quy chế (tự tính, chỉ đọc) */}
                {(g.items || []).map((it, i) => (
                  <div key={i} style={{ display: "flex", alignItems: "center", gap: 8, padding: "7px 12px", borderTop: i ? "1px solid var(--line-2)" : "none", fontSize: 13, background: it.required && rawOf(gi, it) === "" ? "#fffbf0" : undefined }}>
                    <span style={{ fontWeight: 600 }}>
                      {it.label}
                      {/* Quy chế không có định mức (khác nhóm cảng / xe ra không kéo cont) → bắt buộc nhập tay, gợi ý nếu có */}
                      {it.required && <span style={{ display: "block", fontSize: 11, fontWeight: 500, color: "#a05a00" }}>{g.inputHint || "Nhập tay"}{it.suggest ? <> · gợi ý <b className="tnum">{fmtVND(it.suggest)}</b></> : null}</span>}
                    </span>
                    {it.liters != null && it.unitPrice != null
                      ? <span style={{ fontSize: 11.5, color: "var(--ink-4)" }} className="tnum">{fmtNum(it.liters)} lít × {fmtVND(it.unitPrice)}/lít</span>
                      : null}
                    <span style={{ flex: 1 }} />
                    {it.fixed
                      ? <span title={it.fixed === "choho" ? "Theo quy chế: khoản chi hộ — không tính lương" : "Theo quy chế: tính vào lương"}
                          style={{ flexShrink: 0, display: "inline-flex", alignItems: "center", gap: 4, fontSize: 11.5, fontWeight: 600, padding: "3px 8px", borderRadius: 6,
                            border: "1px solid " + (it.fixed === "choho" ? "#f0c36d" : "var(--line)"), background: it.fixed === "choho" ? "#fff7e6" : "var(--line-2)", color: it.fixed === "choho" ? "#9a6700" : "var(--ink-3)" }}>
                          <i className="bi bi-lock-fill" style={{ fontSize: 9 }} /> {it.fixed === "choho" ? "Chi hộ" : "Tính lương"}
                        </span>
                      : typeSel((chSel[gi] || new Set()).has(ckOf(it)), (v) => setCh(gi, ckOf(it), v))}
                    {it.editable && !frozen
                      ? amtInput(gi, it)
                      : <span className="tnum" style={{ fontWeight: 600, minWidth: 92, textAlign: "right" }}>{fmtVND(amtOf(gi, it))}</span>}
                  </div>
                ))}
                {/* Khoản gom trả vào kỳ lương (lương chuyến — chỉ để biết, không cộng tổng hôm nay) */}
                {(g.payrollItems || []).map((it, i) => (
                  <div key={"p" + i} title="Gom trả vào kỳ lương — không cộng vào chi hôm nay"
                    style={{ display: "flex", alignItems: "center", gap: 8, padding: "6px 12px", borderTop: "1px solid var(--line-2)", fontSize: 12.5, color: "var(--ink-4)" }}>
                    <span>{it.label}</span>
                    <span style={{ fontSize: 10.5, fontWeight: 700, color: "#7c5b16", background: "#fbf0d3", padding: "0 7px", borderRadius: 999 }}>kỳ lương</span>
                    <span style={{ flex: 1 }} />
                    {it.editable && !frozen
                      ? amtInput(gi, it)
                      : <span className="tnum">{fmtVND(amtOf(gi, it))}</span>}
                  </div>
                ))}
                {g.note && !(g.items || []).length ? (
                  <div style={{ padding: "8px 12px", fontSize: 12, color: "#a05a00" }}>{g.note} <span style={{ color: "var(--ink-4)" }}>· có thể thêm chi khác phát sinh bên dưới</span></div>
                ) : null}
                {/* Chi khác phát sinh THỦ CÔNG của chuyến này */}
                {(extras[gi] || []).map((m, k) => exRow(gi, m, k))}
                <button type="button" onClick={() => addEx(gi)} style={{ width: "100%", display: "inline-flex", alignItems: "center", justifyContent: "center", gap: 6, padding: "7px 12px", fontSize: 12, fontWeight: 600, borderTop: "1px dashed var(--line)", background: "#fff", color: "var(--accent)", cursor: "pointer", border: "none" }}>
                  <I.plus /> Thêm chi khác phát sinh
                </button>
              </div>
              );
            })}
            <div style={{ display: "flex", alignItems: "center", padding: "10px 12px", border: "1.5px solid var(--accent)", borderRadius: 10, background: "var(--accent-weak)" }}>
              <span style={{ fontWeight: 700 }}>Tổng chi cho lái <span style={{ fontWeight: 400, fontSize: 12, color: "var(--ink-4)" }}>({groups.length} chuyến{warnCount > 0 ? ", " + warnCount + " chưa ra tiền" : ""})</span></span><span style={{ flex: 1 }} />
              <span className="tnum" style={{ fontWeight: 800, fontSize: 16, color: "var(--accent)" }}>{fmtVND(total)}</span>
            </div>
            {(chohoTotal > 0 || khacTotal > 0) && (
              <div className="tnum" style={{ display: "flex", gap: 14, flexWrap: "wrap", justifyContent: "flex-end", marginTop: -4, fontSize: 12.5, color: "var(--ink-3)" }}>
                <span>Tính lương: <b style={{ color: "var(--ink)" }}>{fmtVND(total - chohoTotal - khacTotal)}</b></span>
                {chohoTotal > 0 && <span>Chi hộ (không tính lương): <b style={{ color: "#9a6700" }}>{fmtVND(chohoTotal)}</b></span>}
                {khacTotal > 0 && <span>Chi phí khác (không tính lương): <b style={{ color: "#6d28d9" }}>{fmtVND(khacTotal)}</b></span>}
              </div>
            )}
          </div>
        )}
        <div style={{ display: "flex", gap: 12, flexWrap: "wrap", alignItems: "flex-end" }}>
          <div style={{ flex: 1, minWidth: 200 }}>
            <div style={{ fontSize: 11.5, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>Lái xe nhận tiền</div>
            <Combo value={driver} onChange={setDriver} options={(drivers || []).map((d) => d.name)} placeholder="Chọn lái xe…" small />
          </div>
          <label style={{ display: "inline-flex", alignItems: "center", gap: 7, fontSize: 13, fontWeight: 600, color: paid ? "var(--good)" : "var(--ink-3)", cursor: "pointer", padding: "9px 0" }}>
            <input type="checkbox" checked={paid} onChange={() => setPaid((v) => !v)} style={{ accentColor: "var(--good)", cursor: "pointer", margin: 0 }} /> Đã chi cho lái
          </label>
        </div>
        {/* Lái đã chọn → hiện NH + QR VietQR (copy STK / quét chuyển khoản đúng số tiền) */}
        {(() => { const d = (drivers || []).find((x) => x.name === driver); return d && (d.banks || []).length
          ? <PayBankBox banks={d.banks} amount={total} addInfo={"Chi lai xe " + truck.bks + " " + date} /> : null; })()}
      </div>
    </Modal>
  );
}

const z = (n) => String(n).padStart(2, "0");
const toYmd = (d) => `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}`;

// self = lấy chính cont này ra · none = ra xe không kéo cont (chạy xe không) · other = kéo cont khác ra
const MODE = {
  self:  { label: "Kéo cont ra",     color: "#1f8a5b", bg: "var(--good-weak)", icon: "bi-box-arrow-right" },
  none:  { label: "Ra xe không cont", color: "#8a94a6", bg: "#eef0f3",          icon: "bi-truck" },
  other: { label: "Kéo cont khác",   color: "#e08600", bg: "#fcf3e2",          icon: "bi-arrow-left-right" },
};
// điểm hành trình: nơi lấy → kho → nơi hạ
const PT = {
  pickup: { color: "#2a6fdb", icon: "bi-box-arrow-up-right" },
  kho:    { color: "#4f46e5", icon: "bi-buildings-fill" },
  drop:   { color: "#1f8a5b", icon: "bi-geo-alt-fill" },
};

// Câu mô tả hành động của 1 hoạt động trong ngày
function actionNode(l) {
  if (l.mode === "none") return l.from
    ? <>Vào <span className="tnum">{l.from}</span> <span style={{ color: "var(--ink-4)", fontWeight: 400 }}>— ra xe không kéo cont</span></>
    : <>Ra xe <span style={{ color: "var(--ink-3)", fontWeight: 500 }}>— chạy xe không (chưa kéo cont)</span></>;
  if (l.mode === "other") return <>Kéo cont khác <span className="tnum">{l.refCont || "—"}</span> ra <span style={{ color: "var(--ink-4)", fontWeight: 400 }}>· cont {l.cont || "—"} còn chờ</span></>;
  return <>Lấy cont <span className="tnum">{l.cont || "—"}</span> ra</>;
}

// Chuỗi điểm hành trình (Nơi lấy → Kho → Nơi hạ) với mũi tên. dimDrop: chuyến chưa ra → làm mờ điểm hạ (xe chưa tới).
function PointChain({ points, dimDrop = false }) {
  if (!points || !points.length) return <span style={{ color: "var(--ink-4)" }}>—</span>;
  return (
    <span style={{ display: "inline-flex", alignItems: "center", gap: 4, flexWrap: "wrap" }}>
      {points.map((p, i) => { const c = PT[p.kind] || PT.kho; const dim = dimDrop && p.kind === "drop"; return (
        <React.Fragment key={i}>
          {i > 0 && <i className="bi bi-arrow-right" style={{ color: "var(--ink-4)", fontSize: 11, opacity: dim ? 0.4 : 1 }} />}
          <span title={dim ? "Điểm hạ dự kiến — xe chưa ra" : undefined} style={{ display: "inline-flex", alignItems: "center", gap: 4, fontSize: 12, fontWeight: 600, color: c.color, background: "var(--bg)", border: "1px solid var(--line-2)", borderRadius: 999, padding: "2px 9px", opacity: dim ? 0.4 : 1 }}>
            <i className={"bi " + c.icon} style={{ fontSize: 11 }} />{p.label}
          </span>
        </React.Fragment>
      ); })}
    </span>
  );
}

// Chặn click trong ô sửa (nằm trong thẻ <a> của chuyến) để không mở lô hàng.
const stopLink = (e) => { e.preventDefault(); e.stopPropagation(); };
const parseLiters = (v) => { const t = String(v == null ? "" : v).trim().replace(",", "."); return t === "" ? null : (isFinite(+t) ? +t : NaN); };

/* KHOẢN TIỀN 1 chuyến trên Lộ trình (phụ cấp đi đường / lương chuyến / vé / trợ cấp). Khoản sửa được (editable):
   bấm để sửa TẠI CHỖ — ô tiền + Lưu / Về quy chế / Hủy; trống = theo quy chế. Ngày đã chốt thì khóa. */
function AmountChip({ it, payroll, hint, canEdit, frozen, onSave }) {
  const [edit, setEdit] = useState(false);
  const [val, setVal] = useState("");
  const [busy, setBusy] = useState(false);
  const can = it.editable && canEdit && !frozen;
  const open = (e) => { if (!can) return; stopLink(e); setVal(it.edited || it.required ? String(it.amount || "") : String(it.amount || it.base || "")); setEdit(true); };
  const save = async (e, v) => { stopLink(e); setBusy(true); const ok = await onSave(v); setBusy(false); if (ok) setEdit(false); };
  const mini = { border: "none", background: "transparent", cursor: "pointer", padding: "0 2px", fontSize: 11, fontWeight: 700 };
  if (edit) return (
    <span onClick={stopLink} style={{ display: "inline-flex", alignItems: "center", gap: 5, padding: "3px 8px", fontSize: 11, fontWeight: 600, borderRadius: 9, border: "1px solid var(--accent)", background: "#fff" }}>
      {it.label}:
      <input autoFocus value={val ? Number(val).toLocaleString("vi-VN") : ""} inputMode="numeric" onChange={(e) => setVal(e.target.value.replace(/\D/g, ""))}
        onKeyDown={(e) => { if (e.key === "Enter") save(e, val === "" ? null : +val); if (e.key === "Escape") { stopLink(e); setEdit(false); } }}
        className="tnum" style={{ width: 90, padding: "2px 6px", fontSize: 12, textAlign: "right", border: "1px solid var(--line)", borderRadius: 6, outline: "none" }} />
      <button type="button" disabled={busy} onClick={(e) => save(e, val === "" ? null : +val)} style={{ ...mini, color: "var(--accent)" }}>{busy ? "…" : "Lưu"}</button>
      {(it.edited || it.required) && <button type="button" disabled={busy} onClick={(e) => save(e, null)} title={it.base != null ? "Về mức quy chế " + fmtVND(it.base) : "Bỏ số nhập tay"} style={{ ...mini, color: "var(--ink-3)" }}>{it.base != null ? "Về quy chế" : "Bỏ nhập"}</button>}
      <button type="button" onClick={(e) => { stopLink(e); setEdit(false); }} style={{ ...mini, color: "var(--ink-4)" }}>Hủy</button>
    </span>
  );
  const pend = it.required && !it.edited;
  const tone = pend ? { background: "#fff1d6", color: "#a05a00", border: "1px dashed #e8b860", fontWeight: 700 }
    : payroll ? { background: "#fbf0d3", color: "#7c5b16" } : { background: "var(--accent-weak)", color: "var(--accent)" };
  const title = (pend ? (hint || "Quy chế không có định mức") + " — bấm để nhập"
    : payroll ? "Gom trả vào kỳ lương" : it.choHo ? "Chi hộ — chi cho lái hôm nay nhưng KHÔNG tính lương" : "Chi trong ngày — cộng vào Chi lái hôm nay (tính lương)")
    + (it.edited && it.base != null ? " · sửa tay (quy chế " + fmtVND(it.base) + ")" : "") + (can && !pend ? " · bấm để sửa" : "");
  return (
    <span onClick={open} title={title} style={{ display: "inline-flex", alignItems: "center", gap: 4, padding: "1px 8px", fontSize: 11, fontWeight: 600, borderRadius: 999, cursor: can ? "pointer" : "default", ...tone }}>
      {pend ? <><i className="bi bi-pencil" style={{ fontSize: 9 }} /> {it.label}: nhập tay{it.suggest ? <span style={{ fontWeight: 500 }}> (gợi ý {fmtVND(it.suggest)})</span> : null}</>
        : <>{it.label} <b className="tnum">{fmtVND(it.amount)}</b>{(it.edited && it.base != null) || can ? <i className="bi bi-pencil" style={{ fontSize: 9, opacity: it.edited ? 1 : .45 }} /> : null}</>}
      {!pend && !payroll && it.choHo ? <span style={{ color: "#9a6700" }}> · chi hộ</span> : null}
      {payroll ? " · kỳ lương" : null}
    </span>
  );
}

/* DẦU 1 chuyến (chi phí công ty): lít × giá dầu theo ngày. Sửa được SỐ LÍT (số nguyên — backend làm tròn, không lẻ phẩy) — lưu theo cont của chuyến,
   ghi đè định mức quy chế; "Về định mức" = bỏ sửa tay. Ngày đã chốt thì khóa. */
function FuelChip({ fuel, canEdit, frozen, onSave }) {
  const [edit, setEdit] = useState(false);
  const [val, setVal] = useState("");
  const [busy, setBusy] = useState(false);
  const start = (e) => { stopLink(e); setVal(fuel ? String(fuel.liters).replace(".", ",") : ""); setEdit(true); };
  const save = async (e, liters) => {
    stopLink(e);
    const n = liters === undefined ? parseLiters(val) : liters;
    if (Number.isNaN(n) || (n != null && n < 0)) { window.trkToast && window.trkToast("Số lít không hợp lệ", "error"); return; }
    setBusy(true);
    const ok = await onSave(n);
    setBusy(false);
    if (ok) setEdit(false);
  };
  const box = { display: "inline-flex", alignItems: "center", gap: 5, marginTop: 6, padding: "2px 9px", fontSize: 11, fontWeight: 600, borderRadius: 999, background: "#eef4ff", color: "#2563eb", border: "1px solid #cfe0ff" };
  const mini = { border: "none", background: "transparent", cursor: "pointer", padding: "0 2px", fontSize: 11, fontWeight: 700 };
  if (edit) return (
    <div onClick={stopLink} style={{ ...box, borderRadius: 9, padding: "4px 9px" }}>
      <i className="bi bi-fuel-pump-fill" /> Dầu{fuel ? ` ${fuel.axle} cầu` : ""}:
      <input autoFocus value={val} inputMode="decimal" onChange={(e) => setVal(e.target.value.replace(/[^\d.,]/g, ""))}
        onKeyDown={(e) => { if (e.key === "Enter") save(e); if (e.key === "Escape") { stopLink(e); setEdit(false); } }}
        style={{ width: 64, padding: "2px 6px", fontSize: 12, textAlign: "right", border: "1px solid #9cc0ff", borderRadius: 6, outline: "none" }} className="tnum" placeholder="lít" />
      lít
      <button type="button" disabled={busy} onClick={(e) => save(e)} style={{ ...mini, color: "#2563eb" }}>{busy ? "…" : "Lưu"}</button>
      {fuel && fuel.edited && <button type="button" disabled={busy} onClick={(e) => save(e, null)} title={fuel.baseLiters != null ? `Về định mức quy chế (${fmtNum(fuel.baseLiters)} lít)` : "Bỏ số lít sửa tay"} style={{ ...mini, color: "var(--ink-3)" }}>Về định mức</button>}
      <button type="button" onClick={(e) => { stopLink(e); setEdit(false); }} style={{ ...mini, color: "var(--ink-4)" }}>Hủy</button>
    </div>
  );
  if (!fuel) return canEdit && !frozen
    ? <button type="button" onClick={start} style={{ ...box, cursor: "pointer", background: "#fff", borderStyle: "dashed" }} title="Chuyến chưa có định mức dầu — nhập số lít"><i className="bi bi-fuel-pump" /> + Nhập lít dầu</button>
    : null;
  return (
    <div style={box}>
      <i className="bi bi-fuel-pump-fill" /> Dầu {fuel.axle} cầu: <span className="tnum">{fmtNum(fuel.liters)} lít</span>
      {/* Chưa có giá dầu phủ ngày chuyến → báo rõ thay vì "× 0 đ = 0 đ" */}
      {fuel.unitPrice > 0
        ? <span className="tnum">× {fmtVND(fuel.unitPrice)}/lít = {fmtVND(fuel.amount)}</span>
        : <span title="Thêm giá dầu ở Cài đặt › Bảng giá dầu" style={{ fontWeight: 700, color: "#b45309" }}>· chưa có giá dầu ngày này</span>}
      {/* Lít theo quy chế = km × định mức (số cầu, Hàng–Hàng / Hàng–Rỗng); nguồn km ở tooltip */}
      {fuel.rate ? <span className="tnum" title={"Km theo " + (fuel.kmSrc || "")} style={{ fontWeight: 500, color: "#4b6fb8" }}>· {fmtNum(fuel.km)} km × {fmtNum(fuel.rate)} lít/100km ({fuel.load})</span> : null}
      {fuel.edited && <span title={fuel.baseLiters != null ? `Đã sửa tay — định mức quy chế ${fmtNum(fuel.baseLiters)} lít` : "Đã sửa tay"} style={{ fontWeight: 700, color: "#b45309" }}>· sửa tay{fuel.baseLiters != null ? ` (định mức ${fmtNum(fuel.baseLiters)})` : ""}</span>}
      <span style={{ fontWeight: 400, color: "var(--ink-4)" }}>· công ty trả</span>
      {canEdit && !frozen && <button type="button" onClick={start} title="Sửa số lít dầu của chuyến này" style={{ ...mini, color: "#2563eb" }}><i className="bi bi-pencil" /></button>}
    </div>
  );
}

// Nhãn Nhập / Xuất của cont (lô Nhập = cont có hàng vào kho; lô Xuất = cont đóng hàng ra cảng)
const ioTag = (io) => {
  const v = String(io || "").toLowerCase();
  const nhap = v.startsWith("nh"), xuat = v.startsWith("xu");
  if (!nhap && !xuat) return null;
  return <span style={{ marginLeft: 4, fontSize: 10, fontWeight: 700, padding: "0 6px", borderRadius: 999, verticalAlign: "1px",
    background: nhap ? "#e0f2fe" : "#fef3c7", color: nhap ? "#0369a1" : "#b45309" }}>{nhap ? "Nhập" : "Xuất"}</span>;
};

// 1 hoạt động = 1 nút trên đường rail dọc (nối liền nhau thành lộ trình cả ngày)
// g = nhóm tiền của chuyến theo quy chế (chuyến đã xong): khoản chi trong ngày / kỳ lương + dầu.
function TripNode({ l, isFirst, isLast, bks, href, g, canEdit, frozen, onFuel, onAmount }) {
  const fuel = g && g.fuel;
  const m = MODE[l.mode] || MODE.self;
  // Xe không kéo cont → chỉ hiện NƠI LẤY (chỗ xe vào), không vẽ tới nơi hạ vì chưa giao cont nào.
  const pts = l.mode === "none" ? (l.points || []).filter((p) => p.kind === "pickup") : l.points;
  // Chuyến CHƯA HOÀN THÀNH (đã gán xe, chưa ra): chấm rỗng + rail nét đứt; giờ = giờ vào kho / giờ đến dự kiến.
  const planned = !!l.planned;
  const dot = planned ? "#9aa3b2" : m.color;
  return (
    <a href={href} title="Xem lô hàng (mở tab mới)" target="_blank" rel="noreferrer"
      style={{ display: "flex", alignItems: "stretch", gap: 12, textDecoration: "none", color: "inherit" }}
      onMouseEnter={(e) => (e.currentTarget.querySelector(".trk-trip-body").style.background = "var(--accent-weak-2)")}
      onMouseLeave={(e) => (e.currentTarget.querySelector(".trk-trip-body").style.background = "transparent")}>
      {/* RAIL: đường dọc + chấm mốc */}
      <div style={{ position: "relative", width: 30, flexShrink: 0 }}>
        <div style={{ position: "absolute", left: 14, width: 2, background: planned ? "repeating-linear-gradient(to bottom, var(--line) 0 4px, transparent 4px 8px)" : "var(--line-2)", top: isFirst ? 22 : 0, bottom: isLast ? "calc(100% - 22px)" : 0 }} />
        <div style={{ position: "absolute", left: 7, top: 15, width: 16, height: 16, borderRadius: "50%", background: planned ? "#fff" : m.color, border: planned ? "1.5px dashed " + dot : "3px solid #fff", boxShadow: planned ? "none" : "0 0 0 1.5px " + m.color, display: "grid", placeItems: "center" }}>
          <i className={"bi " + m.icon} style={{ fontSize: 8, color: planned ? dot : "#fff" }} />
        </div>
      </div>
      {/* NỘI DUNG */}
      <div className="trk-trip-body" style={{ flex: 1, minWidth: 0, padding: "10px 12px", borderRadius: 10, marginBottom: 2, transition: "background .12s" }}>
        <div style={{ display: "flex", alignItems: "baseline", gap: 10, flexWrap: "wrap" }}>
          <span className="tnum" style={{ fontWeight: 700, fontSize: 14, color: planned ? "var(--ink-3)" : m.color }}>{planned ? (l.gioDen ? "Vào " : "Dự kiến ") + l.timeLabel : l.timeLabel}</span>
          <span style={{ fontSize: 13.5, fontWeight: 600, color: planned ? "var(--ink-2)" : undefined }}>{actionNode(l)}</span>
          {planned && <span style={{ fontSize: 10.5, fontWeight: 700, color: "#b45309", background: "#fef3c7", padding: "1px 8px", borderRadius: 999 }}>Chưa hoàn thành</span>}
          {l.kepCont && <span title="Kẹp 2 cont 20' (đánh dấu ở lô)" style={{ fontSize: 10.5, fontWeight: 700, color: "#7c3aed", background: "#f3ecff", padding: "1px 8px", borderRadius: 999 }}>Kẹp cont</span>}
          {l.ketHop && <span title="Kết hợp: cont nhập trả nhà máy rồi sang nhà máy khác đóng xuất (đánh dấu ở lô)" style={{ fontSize: 10.5, fontWeight: 700, color: "#7c3aed", background: "#f3ecff", padding: "1px 8px", borderRadius: 999 }}>Kết hợp</span>}
        </div>
        <div style={{ marginTop: 6 }}><PointChain points={pts} dimDrop={planned} /></div>
        {/* CHI TIẾT: xe đưa cont VÀO + xe đưa cont (số nào) RA */}
        <div style={{ marginTop: 7, display: "flex", flexDirection: "column", gap: 3, fontSize: 11.5, lineHeight: 1.5 }}>
          <div style={{ color: "var(--ink-3)" }}>
            <i className="bi bi-box-arrow-in-down" style={{ color: "#2a6fdb" }} /> <span style={{ color: "var(--ink-4)" }}>Xe vào:</span>{" "}
            {planned && !l.gioDen
              ? <><b className="tnum">{l.bks}</b> nhận chuyến cont <b className="tnum">{l.cont || "—"}</b> <span style={{ color: "var(--ink-4)" }}>· chưa vào — dự kiến đến {l.timeLabel}</span></>
              : l.mode === "none"
              ? <><b className="tnum">{l.bks}</b> đã vào nơi lấy <b className="tnum">{l.from || "—"}</b>{l.gioDenLabel ? <> lúc <b className="tnum">{l.gioDenLabel}</b></> : <span style={{ color: "var(--ink-4)" }}> (chưa có giờ xe đến)</span>}</>
              : <><b className="tnum">{l.bks}</b> đưa cont <b className="tnum">{l.cont || "—"}</b>{ioTag(l.io)} vào{l.gioDenLabel && <span style={{ color: "var(--ink-4)" }}> · vào kho {l.gioDenLabel}</span>}</>}
          </div>
          <div style={{ color: "var(--ink-3)" }}>
            <i className="bi bi-box-arrow-up" style={{ color: dot }} /> <span style={{ color: "var(--ink-4)" }}>Xe ra:</span>{" "}
            {planned
              ? (l.mode === "other" && l.refCont
                ? <><b className="tnum">{l.bks}</b> sẽ kéo cont khác <b className="tnum">{l.refCont}</b> ra <span style={{ color: "var(--ink-4)" }}>— chưa ra{l.refBksVao ? ` (cont ${l.refCont} do xe ${l.refBksVao} đưa vào)` : ""}</span></>
                : <span style={{ color: "var(--ink-4)" }}>chưa ra — nhập Giờ xe ra ở lô khi xong chuyến</span>)
              : l.mode === "none"
              ? <><b className="tnum">{l.bks}</b> ra <span style={{ color: "var(--ink-4)" }}>không kéo cont — cont {l.cont || "—"} vẫn chưa ra</span> · <b className="tnum">{l.timeLabel}</b></>
              : l.mode === "other"
                ? <><b className="tnum">{l.bks}</b> kéo cont khác <b className="tnum">{l.refCont || "—"}</b>{ioTag(l.ioOut)} ra ·<b className="tnum">{l.timeLabel}</b>{l.refBksVao && <span style={{ color: "var(--ink-4)" }}> (cont {l.refCont} do xe {l.refBksVao} đưa vào)</span>}</>
                : <><b className="tnum">{(l.bksRa && l.bksRa !== l.bks) ? l.bksRa : l.bks}</b> đưa cont <b className="tnum">{l.cont || "—"}</b>{ioTag(l.ioOut)} ra ·<b className="tnum">{l.timeLabel}</b>{(l.bksRa && l.bksRa !== l.bks) && <span style={{ color: "var(--warn)" }}> (khác xe vào {l.bks})</span>}</>}
          </div>
        </div>
        {(l.customer || l.booking) && <div style={{ fontSize: 11, color: "var(--ink-4)", marginTop: 6 }}>{l.customer}{l.booking ? <span className="tnum"> · {l.booking}</span> : null}</div>}
        {/* Khoản của chuyến: chi trong ngày → cộng Chi lái hôm nay; lương chuyến → gom trả kỳ lương */}
        {g && ((g.items || []).length + (g.payrollItems || []).length + (g.manual || []).filter((m) => +m.amount).length) > 0 && (
          <div style={{ marginTop: 6, display: "flex", flexWrap: "wrap", gap: 5 }}>
            {(g.items || []).map((it, k) => <AmountChip key={"d" + k} it={it} hint={g.inputHint} canEdit={canEdit} frozen={frozen} onSave={(v) => onAmount(l, it.key, v)} />)}
            {(g.payrollItems || []).map((it, k) => <AmountChip key={"p" + k} it={it} payroll hint={g.inputHint} canEdit={canEdit} frozen={frozen} onSave={(v) => onAmount(l, it.key, v)} />)}
            {/* Chi khác phát sinh (thêm ở popup Chi lái): tính lương / chi hộ / chi phí khác / kỳ lương */}
            {(g.manual || []).filter((m) => +m.amount).map((m, k) => {
              const later = m.perDay === false;
              return (
                <span key={"m" + k} title={later ? "Chi khác phát sinh — gom trả vào kỳ lương" : m.khac ? "Chi phí khác — chi hôm nay, KHÔNG tính lương (thống kê riêng)" : m.choHo ? "Chi khác phát sinh · Chi hộ — chi hôm nay, KHÔNG tính lương" : "Chi khác phát sinh · Tính lương — chi hôm nay"}
                  style={{ display: "inline-flex", alignItems: "center", gap: 4, padding: "1px 8px", fontSize: 11, fontWeight: 600, borderRadius: 999,
                    background: later ? "#fbf0d3" : m.khac ? "#f5f3ff" : "var(--accent-weak)", color: later ? "#7c5b16" : m.khac ? "#6d28d9" : "var(--accent)" }}>
                  <i className="bi bi-plus-circle" style={{ fontSize: 9 }} /> {m.name || "Chi khác"} <b className="tnum">{fmtVND(m.amount)}</b>
                  {later ? " · kỳ lương" : m.khac ? " · chi phí khác" : m.choHo ? <span style={{ color: "#9a6700" }}> · chi hộ</span> : null}
                </span>
              );
            })}
          </div>
        )}
        {/* Tính theo QUY CHẾ (không khớp tuyến riêng) + chặng chưa khai km → chưa ra lít dầu */}
        {g && g.feeSource === "regulation" && (
          <div style={{ marginTop: 5, fontSize: 11, color: "#6d28d9" }}><i className="bi bi-journal-text" /> Quy chế · {g.feeRule}</div>
        )}
        {/* Chuyến kéo cont chưa tính được theo quy chế → nêu lý do ngay trên lộ trình (vd kho chưa gán tỉnh cũ) */}
        {g && !l.planned && !g.matched && !g.needInput && g.note && l.mode !== "none" && !(g.manual || []).some((m) => +m.amount) && (
          <div style={{ marginTop: 5, fontSize: 11, color: "#a05a00" }}><i className="bi bi-exclamation-triangle-fill" /> {g.note}</div>
        )}
        {g && !l.planned && (g.kmMissing || []).length > 0 && (
          <div style={{ marginTop: 5, fontSize: 11, color: "#a05a00" }} title="Bổ sung ở Cài đặt › Quy chế lương chuyến › Chặng: km + vé trạm">
            <i className="bi bi-exclamation-triangle-fill" /> Chưa có km chặng <b className="tnum">{g.kmMissing.join(", ")}</b> — chưa tính được lít dầu
          </div>
        )}
        {/* Dầu chuyến này = chi phí công ty (lít × giá dầu theo ngày) — sửa được số lít */}
        {g && !l.planned ? <div><FuelChip fuel={fuel} canEdit={canEdit} frozen={frozen} onSave={(liters) => onFuel(l, liters)} /></div> : null}
      </div>
    </a>
  );
}

/* ===== KIỂM TRA DỮ LIỆU — vì sao chuyến chưa ra tiền / chưa ra lít dầu =====
   Backend gắn lỗi có cấu trúc vào từng chuyến (g.issues = [{type, refs}]); ở đây gom theo LOẠI cho cả ngày, nói rõ cần nhập gì,
   ở đâu (link mở tab mới), và biển số xe bị ảnh hưởng (bấm để cuộn tới xe; chuyến cần nhập tay → mở popup Chi lái). */
const ISSUE_META = {
  noRegulation:   { order: 1,  block: true, title: "Chưa có quy chế lương chuyến cho ngày này", how: "Mở Quy chế lương chuyến, chọn bảng áp cho ngày này → Nạp mặc định (hoặc sửa) → Lưu quy chế.", link: "routeFees", linkText: "Mở Quy chế lương chuyến" },
  noGroup:        { order: 2,  block: true, title: "Nơi lấy / Nơi hạ chưa thuộc nhóm cảng nào", how: "Thêm ký hiệu cảng này vào 1 nhóm ở Quy chế › Bước 1, hoặc sửa Nơi lấy / Nơi hạ của lô cho đúng.", link: "routeFees", linkText: "Mở Quy chế (Bước 1)" },
  noKho:          { order: 3,  block: true, title: "Lô chưa có Kho", how: "Mở lô hàng theo số cont bên dưới và nhập Kho.", link: "shipment", linkText: "Mở Lô hàng" },
  khoNoProvince:  { order: 4,  block: true, title: "Kho chưa gán tỉnh cũ", how: "Cài đặt › Kho: chọn “Tỉnh cũ” ở đầu nhóm ký hiệu của kho (có nút gợi ý theo địa chỉ).", link: "/trucking-v2/cai-dat#warehouses", linkText: "Mở Cài đặt › Kho" },
  provinceNoZone: { order: 5,  block: true, title: "Tỉnh cũ của kho chưa thuộc vùng nào", how: "Quy chế › Bước 3: thêm tỉnh này vào 1 vùng.", link: "routeFees", linkText: "Mở Quy chế (Bước 3)" },
  noKcn:          { order: 6,  block: true, title: "Ký hiệu kho chưa có trong bảng KCN", how: "Quy chế › Bước 4: thêm dòng KCN cho ký hiệu này — hoặc sửa ký hiệu kho cho đúng (QV, TS, TL…) ở Cài đặt › Kho.", link: "routeFees", linkText: "Mở Quy chế (Bước 4)" },
  needInput:      { order: 7,  block: true, title: "Chuyến cần kế toán nhập tay (quy chế không có định mức)", how: "Bấm biển số để mở popup Chi lái và nhập phụ cấp / lương của chuyến (không trả thì nhập 0).", action: "pay" },
  kmMissing:      { order: 8,  title: "Chặng chưa có km → chưa tính được lít dầu", how: "Quy chế › Bước 6: thêm chặng (Từ → Đến) và km. Hoặc sửa lít dầu tay ở từng chuyến.", link: "routeFees", linkText: "Mở Quy chế (Bước 6)" },
  noFuelPrice:    { order: 9,  title: "Chưa có giá dầu cho ngày này → tiền dầu = 0", how: "Cài đặt › Bảng giá dầu: thêm giá áp dụng cho khoảng ngày này.", link: "/trucking-v2/cai-dat#fuelPrices", linkText: "Mở Bảng giá dầu", noRefs: true },
  noAxle:         { order: 10, title: "Xe chưa khai số cầu → dầu đang tính như xe 1 cầu", how: "Cài đặt › Đội xe: chọn số cầu 1 / 2 cho xe.", link: "/trucking-v2/cai-dat#vehicles", linkText: "Mở Cài đặt › Đội xe", perTruck: true },
};
// Gom lỗi cả ngày: type → {trips, refs: Map(ref → {count, trucks:Set}), trucks:Set}
function collectIssues(trucks) {
  const agg = {};
  const bucket = (type) => (agg[type] = agg[type] || { trips: 0, refs: new Map(), trucks: new Set() });
  const addRef = (type, ref, bks) => { const a = bucket(type); const r = a.refs.get(ref) || { count: 0, trucks: new Set() }; r.count++; r.trucks.add(bks); a.refs.set(ref, r); a.trucks.add(bks); };
  trucks.forEach((tr) => {
    (tr.payGroups || []).forEach((g) => {
      const types = new Set();
      (g.issues || []).forEach((is) => { if (!ISSUE_META[is.type]) return; types.add(is.type); (is.refs && is.refs.length ? is.refs : [""]).forEach((ref) => addRef(is.type, ref, tr.bks)); });
      types.forEach((t) => { agg[t].trips++; });
    });
    // Xe MBF có chuyến mà chưa khai số cầu (1 / 2) — "tai" cũng chưa rõ cầu
    if ((tr.legs || []).length && tr.matched && tr.type !== "Ngoài" && !(tr.axle === "1" || tr.axle === "2")) { addRef("noAxle", tr.bks + (tr.axle ? " (đang ghi “" + tr.axle + "”)" : ""), tr.bks); agg.noAxle.trips++; }
  });
  return Object.keys(agg).map((type) => ({ type, ...agg[type], meta: ISSUE_META[type] })).sort((a, b) => a.meta.order - b.meta.order);
}
const scrollToTruck = (bks) => { const el = document.getElementById("trk-" + bks); if (el) el.scrollIntoView({ behavior: "smooth", block: "start" }); };

function DataCheck({ list, ROUTES, onOpenPay, trucks }) {
  const linkOf = (m) => (m.link === "routeFees" ? ROUTES.routeFees : m.link === "shipment" ? ROUTES.shipment : m.link);
  if (!list.length) return (
    <div style={{ display: "flex", alignItems: "center", gap: 8, padding: "10px 14px", marginBottom: 12, borderRadius: 12, background: "var(--good-weak)", color: "var(--good)", fontSize: 13, fontWeight: 600 }}>
      <i className="bi bi-check-circle-fill" /> Đủ dữ liệu — mọi chuyến trong ngày đã tính được tiền và lít dầu.
    </div>
  );
  const blocking = list.filter((x) => x.meta.block).reduce((s, x) => s + x.trips, 0);
  return (
    <div style={{ marginBottom: 12, background: "#fff", border: "1px solid #f1d59a", borderRadius: 12, overflow: "hidden" }}>
      <div style={{ padding: "10px 14px", background: "#fff7e9", borderBottom: "1px solid #f6e3b8", fontSize: 13 }}>
        <b><i className="bi bi-clipboard-check" /> Kiểm tra dữ liệu ngày này</b>
        <span style={{ color: "#8a5a00" }}> — {list.length} loại cần bổ sung{blocking ? <> · <b>{blocking}</b> chuyến chưa ra tiền tự động</> : null}. Bổ sung đúng mục bên dưới rồi tải lại trang để tính lại.</span>
      </div>
      {list.map((x) => {
        const m = x.meta;
        const refs = [...x.refs.entries()].sort((a, b) => b[1].count - a[1].count);
        const trucksArr = [...x.trucks];
        return (
          <div key={x.type} style={{ display: "grid", gridTemplateColumns: "26px minmax(0, 1fr) auto", gap: 10, padding: "10px 14px", borderTop: "1px solid var(--line-2)", alignItems: "start" }}>
            <i className={"bi " + (m.block ? "bi-exclamation-octagon-fill" : "bi-fuel-pump")} style={{ fontSize: 16, color: m.block ? "#c2410c" : "#2563eb", marginTop: 1 }} />
            <div style={{ minWidth: 0, fontSize: 12.5, lineHeight: 1.55 }}>
              <div><b style={{ fontSize: 13 }}>{m.title}</b> <span style={{ color: "var(--ink-3)" }}>· {m.perTruck ? trucksArr.length + " xe" : x.trips + " chuyến · " + trucksArr.length + " xe"}</span></div>
              <div style={{ color: "var(--ink-3)" }}><b>Cần làm:</b> {m.how}</div>
              {!m.noRefs && !m.perTruck && refs.length > 0 && (
                <div style={{ display: "flex", flexWrap: "wrap", gap: 5, marginTop: 5 }}>
                  {refs.slice(0, 16).map(([ref, r]) => (
                    <span key={ref} title={"Xe: " + [...r.trucks].join(", ")} style={{ display: "inline-flex", alignItems: "center", gap: 4, padding: "1px 8px", borderRadius: 999, fontSize: 11.5, fontWeight: 600, background: "#fff1d6", color: "#8a5a00", border: "1px solid #f1d59a" }}>
                      {m.link === "shipment" && ref && ref[0] !== "(" ? <a href={ROUTES.shipment + "?q=" + encodeURIComponent(ref)} target="_blank" rel="noreferrer" style={{ color: "inherit" }}>{ref}</a> : ref}
                      {r.count > 1 ? <span style={{ fontWeight: 500, opacity: .8 }}>×{r.count}</span> : null}
                    </span>
                  ))}
                  {refs.length > 16 && <span style={{ fontSize: 11.5, color: "var(--ink-4)" }}>+{refs.length - 16} mục khác</span>}
                </div>
              )}
              <div style={{ display: "flex", flexWrap: "wrap", gap: 5, marginTop: 5, alignItems: "center" }}>
                <span style={{ fontSize: 11, color: "var(--ink-4)" }}>{m.action === "pay" ? "Mở Chi lái:" : "Xe:"}</span>
                {trucksArr.slice(0, 20).map((bks) => (
                  <button key={bks} type="button" className="tnum"
                    onClick={() => { if (m.action === "pay") { const tr = trucks.find((t) => t.bks === bks); if (tr) onOpenPay(tr); } else scrollToTruck(bks); }}
                    title={m.action === "pay" ? "Mở popup Chi lái của xe này để nhập" : "Cuộn tới xe này"}
                    style={{ padding: "1px 8px", borderRadius: 999, fontSize: 11.5, fontWeight: 700, cursor: "pointer", border: "1px solid var(--line)", background: "#fff", color: "var(--accent)" }}>{bks}</button>
                ))}
                {trucksArr.length > 20 && <span style={{ fontSize: 11.5, color: "var(--ink-4)" }}>+{trucksArr.length - 20} xe</span>}
              </div>
            </div>
            {m.link && (
              <a href={linkOf(m)} target="_blank" rel="noreferrer" style={{ alignSelf: "center", display: "inline-flex", alignItems: "center", gap: 5, padding: "5px 10px", borderRadius: 8, fontSize: 12, fontWeight: 600, whiteSpace: "nowrap", textDecoration: "none", border: "1px solid var(--accent)", color: "var(--accent)", background: "var(--accent-weak-2)" }}>
                {m.linkText} <i className="bi bi-box-arrow-up-right" style={{ fontSize: 10 }} />
              </a>
            )}
          </div>
        );
      })}
    </div>
  );
}
const CHECK_KEY = "trk.lotrinhCheck";

function LoTrinhApp() {
  const isMobile = useIsMobile();
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const api = (m, u) => window.trkApi(m, u);
  const drivers = B.drivers || [];

  // Mở từ Kỳ lương (?date=YYYY-MM-DD&bks=…) → vào đúng ngày + cuộn tới xe để đối soát.
  const urlQ = useRef(new URLSearchParams(window.location.search)).current;
  const focusBks = useRef(urlQ.get("bks") || "");
  const [date, setDate] = useState(() => (/^\d{4}-\d{2}-\d{2}$/.test(urlQ.get("date") || "") ? urlQ.get("date") : toYmd(new Date())));
  const [data, setData] = useState(null);   // null = đang tải
  const [showExt, setShowExt] = useState(() => !!urlQ.get("bks"));   // mặc định CHỈ xe MBF; bật để xem xe ngoài chạy
  const [payTruck, setPayTruck] = useState(null);   // xe đang mở popup "chi cho lái"
  const [freezing, setFreezing] = useState(false);
  // Khung "Kiểm tra dữ liệu" bật/tắt — nhớ theo trình duyệt
  const [showCheck, setShowCheck] = useState(() => { try { return localStorage.getItem(CHECK_KEY) === "1"; } catch (e) { return false; } });
  const toggleCheck = () => setShowCheck((v) => { try { localStorage.setItem(CHECK_KEY, v ? "0" : "1"); } catch (e) {} return !v; });
  const reqId = useRef(0);
  // Cập nhật lái nhận/đã chi vào state cục bộ sau khi lưu (khỏi tải lại cả trang).
  const applyPay = (bks, np) => setData((d) => d ? { ...d, trucks: (d.trucks || []).map((t) => (t.bks === bks ? { ...t, ...np } : t)) } : d);

  // silent = tải lại ngầm sau khi lưu (giữ màn hình đang xem, không nhảy về đầu trang)
  const load = (silent = false) => {
    const my = ++reqId.current; if (!silent) setData(null);
    api("GET", ROUTES.data + "?date=" + encodeURIComponent(date)).then((r) => {
      if (my !== reqId.current) return;
      setData(r && r.ok ? r : { trucks: [], totalLegs: 0, start: 0, end: 0 });
    }).catch(() => { if (my === reqId.current) setData({ trucks: [], totalLegs: 0 }); });
  };
  useEffect(() => { load(); }, [date]);
  useEffect(() => {
    if (!data || !focusBks.current) return;
    const b = focusBks.current; focusBks.current = "";
    setTimeout(() => scrollToTruck(b), 150);
  }, [data]);

  const shiftDay = (n) => { const d = new Date(date + "T12:00:00"); d.setDate(d.getDate() + n); setDate(toYmd(d)); };
  // Sửa lít dầu 1 chuyến (theo cont) → lưu rồi tải lại ngày (tiền dầu, tổng xe, báo cáo đều tính lại ở backend).
  // Sửa tay phụ cấp đi đường / lương chuyến 1 chuyến ngay trên Lộ trình (amount null = về mức quy chế) → tải lại ngầm
  const saveAmount = async (tr, cont, key, amount) => {
    try {
      const r = await window.trkApi("POST", ROUTES.saveAmount, { date, bks: tr.bks, cont, key, amount: amount == null ? null : String(amount) });
      if (r && r.ok) { window.trkToast && window.trkToast(amount == null ? "Đã về mức quy chế" : "Đã lưu số tiền"); load(true); return true; }
      window.trkToast && window.trkToast((r && r.message) || "Lưu thất bại", "error");
    } catch (e) { /* trkApi đã báo lỗi */ }
    return false;
  };
  const saveFuel = async (tr, cont, liters) => {
    try {
      const r = await window.trkApi("POST", ROUTES.saveFuel, { date, bks: tr.bks, cont, liters: liters == null ? null : String(liters) });
      if (r && r.ok) { window.trkToast && window.trkToast(liters == null ? "Đã về định mức dầu" : "Đã lưu số lít dầu"); load(true); return true; }
      window.trkToast && window.trkToast((r && r.message) || "Lưu thất bại", "error");
    } catch (e) { /* trkApi đã báo lỗi */ }
    return false;
  };
  const allTrucks = data?.trucks || [];
  const isMbf = (t) => t.matched && t.type !== "Ngoài";   // xe MBF = khớp đội xe & không phải "Ngoài"
  const extCount = allTrucks.length - allTrucks.filter(isMbf).length;
  const trucks = showExt ? allTrucks : allTrucks.filter(isMbf);   // mặc định chỉ MBF
  const visLegs = trucks.reduce((a, t) => a + t.legs.length, 0);
  const visPlanned = trucks.reduce((a, t) => a + (t.planned || []).length, 0);   // chuyến đã gán xe, chưa ra
  // Chốt ngày chỉ xét xe CÓ chuyến hoàn thành — xe chỉ có chuyến chưa xong thì chưa có số tiền để chốt.
  const payTrucks = allTrucks.filter((t) => t.legs.length > 0 || (t.payGroups || []).length > 0);
  const allFrozen = payTrucks.length > 0 && payTrucks.every((t) => t.frozen);
  // Lỗi dữ liệu của các xe đang xem (gom theo loại) + số chuyến/xe vướng để hiện trên nút
  const issueList = collectIssues(trucks);
  const issueCount = issueList.reduce((s, x) => s + x.trips, 0);

  // Chốt / bỏ chốt cả ngày: đóng băng số tiền chi cho lái (không đổi khi sửa quy chế).
  const doFreeze = async (frozen) => {
    if (freezing || !payTrucks.length) return;
    const ok = await window.confirmAction({
      title: frozen ? "Chốt (đóng băng) ngày này?" : "Bỏ chốt ngày này?",
      text: frozen
        ? `Số tiền chi cho lái của <b>${payTrucks.length} xe</b> ngày <b>${date}</b> sẽ được <b>đóng băng</b> — không đổi dù sau này sửa quy chế.`
        : `Bỏ đóng băng ngày <b>${date}</b> — số tiền sẽ tính lại theo quy chế hiện tại.`,
      confirmText: frozen ? '<i class="bi bi-lock me-1"></i> Chốt ngày' : '<i class="bi bi-unlock me-1"></i> Bỏ chốt',
    });
    if (!ok) return;
    setFreezing(true);
    try {
      const r = await window.trkApi("POST", ROUTES.freeze, { date, frozen });
      if (r && r.ok) { window.trkToast && window.trkToast(frozen ? "Đã chốt ngày" : "Đã bỏ chốt"); load(); }
      else window.trkToast && window.trkToast("Thao tác thất bại", "error");
    } catch (e) { window.trkToast && window.trkToast("Lỗi kết nối", "error"); }
    setFreezing(false);
  };

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: isMobile ? "10px 14px" : "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 12, height: isMobile ? "auto" : 58, flexWrap: "wrap" }}>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center", flexShrink: 0 }}><i className="bi bi-signpost-split-fill" /></div>
          <div>
            <div style={{ fontSize: 15.5, fontWeight: 700 }}>Lộ trình lái xe trong ngày</div>
            <div style={{ fontSize: 11.5, color: "var(--ink-3)" }}>
              {data ? <>Ngày vận hành <b>{data.startLabel} 08:00</b> → <b>{data.endLabel} 08:00</b> · {trucks.length} xe{showExt ? "" : " MBF"} · {visLegs} hoạt động{visPlanned > 0 && <> · <b>{visPlanned}</b> chưa hoàn thành</>}</> : "Đang tải…"}
            </div>
          </div>
          <div style={{ flex: 1 }} />
          <div style={{ display: "inline-flex", alignItems: "center", gap: 6, flexWrap: "wrap" }}>
            {/* Bật/tắt khung Kiểm tra dữ liệu: vì sao chuyến chưa tính được + cần nhập gì, ở đâu */}
            {data && trucks.length > 0 && (
              <button type="button" onClick={toggleCheck} title="Kiểm tra dữ liệu: chuyến nào chưa tính được và cần bổ sung gì"
                style={{ display: "inline-flex", alignItems: "center", gap: 6, height: 32, padding: "0 12px", fontSize: 12.5, fontWeight: 700, borderRadius: 9, cursor: "pointer",
                  border: "1px solid " + (issueCount ? "#e8b860" : "var(--good)"), background: showCheck ? (issueCount ? "#fff1d6" : "var(--good-weak)") : "#fff", color: issueCount ? "#a05a00" : "var(--good)" }}>
                <i className={"bi " + (issueCount ? "bi-clipboard-x" : "bi-clipboard-check")} /> Kiểm tra dữ liệu
                <span className="tnum" style={{ padding: "0 6px", borderRadius: 999, fontSize: 11, background: issueCount ? "#e8b860" : "var(--good)", color: "#fff" }}>{issueCount || "✓"}</span>
              </button>
            )}
            {(extCount > 0 || showExt) && (
              <button type="button" onClick={() => setShowExt((v) => !v)} title={showExt ? "Chỉ hiện xe MBF" : "Hiện cả xe ngoài chạy"}
                style={{ display: "inline-flex", alignItems: "center", gap: 6, height: 32, padding: "0 12px", fontSize: 12.5, fontWeight: 600, borderRadius: 9, cursor: "pointer",
                  border: "1px solid " + (showExt ? "var(--accent)" : "var(--line)"), background: showExt ? "var(--accent-weak-2)" : "#fff", color: showExt ? "var(--accent)" : "var(--ink-2)" }}>
                <i className={"bi " + (showExt ? "bi-eye-fill" : "bi-eye")} /> {showExt ? "Đang hiện xe ngoài" : `Xem xe ngoài (${extCount})`}
              </button>
            )}
            {T.canEdit && payTrucks.length > 0 && (
              <button type="button" onClick={() => doFreeze(!allFrozen)} disabled={freezing} title={allFrozen ? "Bỏ đóng băng (tính lại theo quy chế)" : "Đóng băng số tiền chi cho lái ngày này"}
                style={{ display: "inline-flex", alignItems: "center", gap: 6, height: 32, padding: "0 12px", fontSize: 12.5, fontWeight: 700, borderRadius: 9, cursor: "pointer",
                  border: "1px solid " + (allFrozen ? "var(--good)" : "var(--accent)"), background: allFrozen ? "var(--good-weak)" : "var(--accent-weak)", color: allFrozen ? "var(--good)" : "var(--accent)" }}>
                <i className={"bi " + (allFrozen ? "bi-lock-fill" : "bi-snow")} /> {allFrozen ? "Đã chốt — Bỏ chốt" : "Chốt ngày"}
              </button>
            )}
            <button type="button" onClick={() => shiftDay(-1)} title="Ngày trước" style={btnIcon}>‹</button>
            <div style={{ width: 150 }}><DateField value={date} onChange={setDate} /></div>
            <button type="button" onClick={() => shiftDay(1)} title="Ngày sau" style={btnIcon}>›</button>
            <button type="button" onClick={() => setDate(toYmd(new Date()))} style={{ ...btnIcon, width: "auto", padding: "0 12px", fontSize: 13, fontWeight: 600 }}>Hôm nay</button>
          </div>
        </div>
      </header>

      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: isMobile ? "12px 12px 24px" : "16px 22px 24px" }}>
        <div style={{ maxWidth: 1040, margin: "0 auto" }}>
          {!data ? (
            <div style={{ padding: "40px", textAlign: "center", color: "var(--ink-4)" }}><i className="bi bi-arrow-repeat" style={{ animation: "trk-spin .7s linear infinite" }} /> Đang tải…</div>
          ) : trucks.length === 0 ? (
            <div style={{ padding: "44px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5, background: "#fff", border: "1px solid var(--line)", borderRadius: 12 }}>
              {!showExt && extCount > 0
                ? <>Không có <b>xe MBF</b> chạy trong ngày này — nhưng có <b>{extCount} xe ngoài</b>. Bấm <b>“Xem xe ngoài ({extCount})”</b> ở trên để xem.</>
                : <>Không có xe nào hoạt động trong ngày này (chuyến xong tính theo giờ xe ra; chuyến chưa xong theo giờ xe đến / giờ đến dự kiến). Chọn ngày khác hoặc kiểm tra giờ của lô.</>}
            </div>
          ) : (
            <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
              {showCheck && <DataCheck list={issueList} ROUTES={ROUTES} trucks={trucks} onOpenPay={(tr) => setPayTruck(tr)} />}
              {trucks.map((tr) => (
                <div key={tr.bks} id={"trk-" + tr.bks} style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, overflow: "hidden", scrollMarginTop: 12 }}>
                  {/* Header xe: nhóm trái (biển số + nhãn + số hoạt động) KHÔNG xuống dòng; nhóm chip tiền bên phải tự xuống
                      hàng dưới khi hẹp — tránh biển số / nhãn bị bóp gãy chữ. */}
                  <div style={{ display: "flex", alignItems: "center", gap: "8px 10px", flexWrap: "wrap", padding: "11px 14px", borderBottom: "1px solid var(--line-2)", background: "#fafbfc" }}>
                    <div style={{ display: "flex", alignItems: "center", gap: 8, flexShrink: 0, whiteSpace: "nowrap" }}>
                      <i className="bi bi-truck-front-fill" style={{ color: "var(--accent)" }} />
                      <span className="tnum" style={{ fontWeight: 700, fontSize: 15 }}>{tr.bks}</span>
                      {tr.matched
                        ? <span style={{ fontSize: 10.5, fontWeight: 700, color: "var(--good)", background: "var(--good-weak)", padding: "1px 7px", borderRadius: 999 }}>✓ {tr.type === "Ngoài" ? "Xe ngoài" : "Xe MBF"}</span>
                        : <span style={{ fontSize: 10.5, color: "var(--ink-4)" }}>(ngoài hệ thống)</span>}
                      {(tr.axle === "1" || tr.axle === "2" || tr.axle === "tai") && <span title="Số cầu xe" style={{ fontSize: 10.5, fontWeight: 700, color: "var(--accent)", background: "var(--accent-weak)", padding: "1px 7px", borderRadius: 999 }}>{axleLabel(tr.axle)}</span>}
                      {tr.frozen && <span title="Đã chốt (số tiền đóng băng)" style={{ fontSize: 10.5, fontWeight: 700, color: "#2563eb", background: "#e7efff", padding: "1px 7px", borderRadius: 999 }}><i className="bi bi-lock-fill" /> Đã chốt</span>}
                      <span style={{ fontSize: 12, color: "var(--ink-3)", fontWeight: 600, marginLeft: 2 }}>{tr.legs.length} hoạt động{(tr.planned || []).length > 0 && <span style={{ color: "#b45309" }}> · {tr.planned.length} chưa xong</span>}</span>
                    </div>
                    <div style={{ marginLeft: "auto", display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap", justifyContent: "flex-end" }}>
                    {/* Dầu = chi phí công ty (không chi cho lái) — có lít mà chưa có giá dầu vẫn hiện lít */}
                    {tr.fuelLiters > 0 && (
                      <span title={"Dầu công ty: " + fmtNum(tr.fuelLiters) + " lít" + (tr.fuelTotal > 0 ? "" : " — chưa có giá dầu ngày này (Cài đặt › Bảng giá dầu)")} style={{ display: "inline-flex", alignItems: "center", gap: 5, padding: "5px 10px", fontSize: 12, fontWeight: 700, borderRadius: 999, border: "1px solid #cfe0ff", background: "#eef4ff", color: "#2563eb", whiteSpace: "nowrap" }}>
                        <i className="bi bi-fuel-pump-fill" /> Dầu (cty): {tr.fuelTotal > 0 ? <span className="tnum">{fmtVND(tr.fuelTotal)}</span> : null}<span style={{ fontWeight: tr.fuelTotal > 0 ? 500 : 700 }}>{tr.fuelTotal > 0 ? "· " : ""}{fmtNum(tr.fuelLiters)} lít</span>
                      </span>
                    )}
                    {/* Lương chuyến trong ngày (không chi hôm nay — gom vào kỳ lương); tooltip = thu nhập tính lương cả ngày */}
                    {tr.payrollTotal > 0 && (
                      <span title={"Thu nhập tính lương ngày này = chi trong ngày tính lương " + fmtVND((tr.payTotal || 0) - (tr.chohoTotal || 0) - (tr.khacTotal || 0)) + " + lương chuyến " + fmtVND(tr.payrollTotal) + " = " + fmtVND((tr.payTotal || 0) - (tr.chohoTotal || 0) - (tr.khacTotal || 0) + tr.payrollTotal) + (tr.chohoTotal ? " (chi hộ " + fmtVND(tr.chohoTotal) + " không tính lương)" : "") + (tr.khacTotal ? " (chi phí khác " + fmtVND(tr.khacTotal) + " không tính lương)" : "")}
                        style={{ display: "inline-flex", alignItems: "center", gap: 5, padding: "5px 10px", fontSize: 12, fontWeight: 700, borderRadius: 999, border: "1px solid #ecd9a3", background: "#fbf0d3", color: "#7c5b16", whiteSpace: "nowrap" }}>
                        <i className="bi bi-wallet2" /> Lương chuyến: <span className="tnum">{fmtVND(tr.payrollTotal)}</span><span style={{ fontWeight: 500 }}>· kỳ lương</span>
                      </span>
                    )}
                    {/* Chi cho lái: tổng các khoản "chi theo ngày" + lái nhận — chỉ hiện khi có quyền driverPay.manage (T.canEdit) */}
                    {T.canEdit && (tr.legs.length > 0 || (tr.payGroups || []).length > 0) && <button type="button" onClick={() => setPayTruck(tr)} title="Chi cho lái xe (theo quy chế lương chuyến)"
                      style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "5px 11px", fontSize: 12.5, fontWeight: 700, borderRadius: 999, cursor: "pointer", whiteSpace: "nowrap",
                        border: "1px solid " + (tr.paid ? "var(--good)" : (tr.payTotal > 0 ? "var(--accent)" : "var(--line)")),
                        background: tr.paid ? "var(--good-weak)" : (tr.payTotal > 0 ? "var(--accent-weak)" : "#fff"),
                        color: tr.paid ? "var(--good)" : (tr.payTotal > 0 ? "var(--accent)" : "var(--ink-3)") }}>
                      <i className={"bi " + (tr.paid ? "bi-cash-coin" : "bi-cash")} /> Chi lái: <span className="tnum">{fmtVND(tr.payTotal || 0)}</span>
                      {tr.payDriver ? <span style={{ fontWeight: 500 }}>· {tr.payDriver}</span> : null}
                      {tr.paid ? <span title="Đã chi">✓</span> : null}
                      {tr.payWarn > 0 ? <i className="bi bi-exclamation-triangle-fill" title={tr.payWarn + " chuyến chưa ra tiền (xem lý do trong popup Chi lái)"} style={{ color: "#c9820f" }} /> : null}
                    </button>}
                    </div>
                  </div>
                  {/* LỘ TRÌNH 1 NGÀY: timeline dọc nối liền — chuyến xong (dầu theo payGroups cùng chỉ số) + chuyến chưa xong, xếp theo giờ */}
                  <div style={{ padding: "8px 12px 10px" }}>
                    {(() => {
                      const items = [...tr.legs.map((l, i) => ({ l, g: (tr.payGroups || [])[i] || null })), ...(tr.planned || []).map((l) => ({ l }))]
                        .sort((a, b) => a.l.sortTs - b.l.sortTs);
                      // Lọc theo số cont (chuyến mới gán xe chưa có cont → theo booking) + open = hashid để popup mở ĐÚNG lô
                      // (1 số cont có thể được dùng lại ở nhiều lô; 1 booking có nhiều lô).
                      const openOf = (l) => (l.hashid ? "&open=" + encodeURIComponent(l.hashid) : "");
                      const hrefOf = (l) => ROUTES.shipment + (l.cont ? "?q=" + encodeURIComponent(l.cont) + (openOf(l) || "&open=1") : (l.booking ? "?q=" + encodeURIComponent(l.booking) + openOf(l) : ""));
                      return items.map(({ l, g }, i) => (
                        <TripNode key={i} l={l} isFirst={i === 0} isLast={i === items.length - 1} bks={tr.bks} g={g} href={hrefOf(l)}
                          canEdit={!!T.canEdit} frozen={tr.frozen} onFuel={(leg, liters) => saveFuel(tr, (g && g.cont) || leg.cont || "", liters)}
                          onAmount={(leg, key, amount) => saveAmount(tr, (g && g.cont) || leg.cont || "", key, amount)} />
                      ));
                    })()}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
      {payTruck && <PayPopup truck={payTruck} date={date} drivers={drivers} routeFeesUrl={ROUTES.routeFees}
        onClose={() => setPayTruck(null)} onSaved={(np) => { applyPay(payTruck.bks, np); load(true); }} />}
    </div>
  );
}

const btnIcon = { width: 32, height: 32, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 9, background: "#fff", cursor: "pointer", fontSize: 16, color: "var(--ink-2)", flexShrink: 0 };

createRoot(document.getElementById("trk-root")).render(<LoTrinhApp />);
