import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useEffect, useRef } = React;
import { I, Money, Num, Txt, Combo, DateField, Btn, Modal, fmtVND, fmtNum, fmtDate, toNum } from "@trk/lib.jsx";
import { ChkBox } from "@trk/pop.jsx";

const num = (v) => parseFloat((v ?? "").toString().replace(/[^\d.-]/g, "")) || 0;
const daysUsed = (iso) => { if (!iso) return 0; const s = new Date(iso + "T00:00:00"); const now = new Date(); const d = Math.floor((now - s) / 86400000); return d > 0 ? d : 0; };
const COST_KINDS = [["fixed", "Cố định"], ["recurring", "Định kỳ"]];
const normKind = (k) => (k === "fixed" ? "fixed" : "recurring");   // gộp monthly/yearly cũ → recurring
const TAB_KEYS = ["info", "allowance", "deprec", "usage", "cost"];
const SECTION_OF = { deprec: "depreciations", usage: "usages", cost: "costs" };   // tab → nhóm lazy-load (allowance + info nằm trong base)

// Trạng thái hạn (đăng kiểm / bảo hiểm): chưa có < còn hạn < sắp hết (≤30 ngày) < hết hạn
const DUE_NONE = { key: "none", label: "Chưa có", color: "var(--ink-4)", bg: "var(--line-2)", rank: 0 };
const dueStatus = (iso) => {
  if (!iso) return DUE_NONE;
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const d = new Date(iso + "T00:00:00");
  if (isNaN(d.getTime())) return DUE_NONE;
  const days = Math.floor((d - today) / 86400000);
  if (days < 0) return { key: "expired", label: "Hết hạn", color: "var(--danger)", bg: "#fce8e8", rank: 3, days };
  if (days <= 30) return { key: "soon", label: "Sắp hết hạn", color: "var(--warn)", bg: "#fcf3e2", rank: 2, days };
  return { key: "ok", label: "Còn hạn", color: "var(--good)", bg: "var(--good-weak)", rank: 1, days };
};
const vehRank = (v) => Math.max(dueStatus(v.registrationDue).rank, dueStatus(v.insuranceDue).rank);
// Ô hạn trong bảng: ngày + chip trạng thái
const DueCell = ({ iso }) => {
  const s = dueStatus(iso);
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 3 }}>
      <span className="tnum" style={{ fontSize: 12.5, color: iso ? "var(--ink-2)" : "var(--ink-4)" }}>{iso ? fmtDate(iso) : "—"}</span>
      <span style={{ alignSelf: "flex-start", fontSize: 11, fontWeight: 700, color: s.color, background: s.bg, padding: "1px 8px", borderRadius: 999, display: "inline-flex", alignItems: "center", gap: 4 }}>
        {s.key === "expired" && <i className="bi bi-exclamation-triangle-fill" />}
        {s.key === "soon" && <i className="bi bi-clock-fill" />}
        {s.label}{s.key === "soon" && s.days != null ? ` · ${s.days}n` : ""}
      </span>
    </div>
  );
};

const lbl = (t) => <div style={{ fontSize: 11.5, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>{t}</div>;
const card = { border: "1px solid var(--line)", borderRadius: 12, padding: "14px 16px", background: "#fafbfc" };
const delBtn = (onClick) => (
  <button type="button" onClick={onClick} title="Xóa"
    style={{ flexShrink: 0, width: 34, height: 34, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: "var(--ink-4)", cursor: "pointer" }}
    onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
    onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-4)"; }}><I.trash /></button>
);
const addBtn = (onClick, text) => (
  <button type="button" onClick={onClick}
    style={{ alignSelf: "flex-start", display: "inline-flex", alignItems: "center", gap: 7, padding: "9px 14px", fontSize: 13.5, fontWeight: 600, border: "1px dashed var(--accent)", borderRadius: 10, background: "var(--accent-weak-2)", color: "var(--accent)", cursor: "pointer" }}>
    <I.plus /> {text}
  </button>
);

/* ---- Tab: Thông tin xe / Khấu hao ---- */
function DeprecTab({ rows, onChange }) {
  const set = (i, np) => onChange(rows.map((r, j) => (j === i ? { ...r, ...np } : r)));
  const add = () => onChange([...(rows || []), { id: Date.now() + Math.random(), name: "", origPrice: "", startDate: "", months: "" }]);
  const del = (i) => onChange(rows.filter((_, j) => j !== i));
  let totalAccrued = 0;
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
      <div style={{ fontSize: 11.5, color: "var(--ink-4)", lineHeight: 1.5 }}>
        Khấu hao/ngày = <b>Nguyên giá ÷ (30 × số tháng)</b>. Đã khấu hao = khấu hao/ngày × số ngày sử dụng (từ ngày bắt đầu đến hôm nay, tối đa hết kỳ).
      </div>
      {(rows || []).length === 0 && <div style={{ padding: "12px 2px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có hạng mục khấu hao — bấm <b>+ Thêm hạng mục</b>.</div>}
      {(rows || []).map((r, i) => {
        const orig = num(r.origPrice), months = num(r.months);
        const perDay = months > 0 && orig > 0 ? orig / (30 * months) : 0;
        const totalDays = 30 * months;
        const used = Math.min(daysUsed(r.startDate), totalDays || Infinity);
        const accrued = Math.round(perDay * used);
        const remain = Math.max(0, Math.round(orig - accrued));
        totalAccrued += accrued;
        return (
          <div key={r.id || i} style={card}>
            <div style={{ display: "flex", alignItems: "flex-end", gap: 12, marginBottom: 10 }}>
              <div style={{ flex: 1, minWidth: 0 }}>{lbl("Tên hạng mục khấu hao")}<Txt value={r.name} onChange={(x) => set(i, { name: x })} placeholder="VD: Khấu hao đầu kéo" /></div>
              {delBtn(() => del(i))}
            </div>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(150px, 1fr))", gap: 10 }}>
              <div>{lbl("Nguyên giá")}<Money value={r.origPrice} onChange={(x) => set(i, { origPrice: x })} dim /></div>
              <div>{lbl("Ngày bắt đầu sử dụng")}<DateField value={r.startDate} onChange={(x) => set(i, { startDate: x })} /></div>
              <div>{lbl("Số tháng khấu hao")}<Num value={r.months} onChange={(x) => set(i, { months: x })} suffix="tháng" /></div>
            </div>
            <div style={{ marginTop: 10, display: "flex", flexWrap: "wrap", gap: "4px 18px", fontSize: 12, color: "var(--ink-3)" }} className="tnum">
              <span>Khấu hao/ngày: <b style={{ color: "var(--ink-2)" }}>{fmtNum(Math.round(perDay))} đ</b></span>
              <span>Số ngày đã dùng: <b style={{ color: "var(--ink-2)" }}>{used || 0}</b>{totalDays ? " / " + totalDays : ""}</span>
              <span>Đã khấu hao: <b style={{ color: "var(--accent)" }}>{fmtNum(accrued)} đ</b></span>
              <span>Còn lại: <b style={{ color: "var(--ink-2)" }}>{fmtNum(remain)} đ</b></span>
            </div>
          </div>
        );
      })}
      {(rows || []).length > 0 && <div style={{ textAlign: "right", fontSize: 13, fontWeight: 700 }} className="tnum">Tổng đã khấu hao: <span style={{ color: "var(--accent)" }}>{fmtVND(totalAccrued)}</span></div>}
      {addBtn(add, "Thêm hạng mục")}
    </div>
  );
}

/* ---- Tab: Thời gian sử dụng xe ---- */
function UsageTab({ rows, onChange, drivers }) {
  const set = (i, np) => onChange(rows.map((r, j) => (j === i ? { ...r, ...np } : r)));
  const add = () => onChange([...(rows || []), { id: Date.now() + Math.random(), driver: "", from: "", to: "", note: "" }]);
  const del = (i) => onChange(rows.filter((_, j) => j !== i));
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
      <div style={{ fontSize: 11.5, color: "var(--ink-4)", lineHeight: 1.5 }}>Gán lái xe (danh mục Lái xe) theo khoảng thời gian dùng xe — để sau tính lương theo phí tuyến đường.</div>
      {(rows || []).length === 0 && <div style={{ padding: "12px 2px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có lịch sử — bấm <b>+ Thêm lượt sử dụng</b>.</div>}
      {(rows || []).map((r, i) => (
        <div key={r.id || i} style={{ display: "grid", gridTemplateColumns: "1fr 150px 150px 1fr 34px", gap: 10, alignItems: "end", ...card }}>
          <div>{lbl("Lái xe")}<Combo value={r.driver} onChange={(x) => set(i, { driver: x })} options={drivers || []} placeholder="Chọn lái xe…" /></div>
          <div>{lbl("Từ ngày")}<DateField value={r.from} onChange={(x) => set(i, { from: x })} /></div>
          <div>{lbl("Đến ngày")}<DateField value={r.to} onChange={(x) => set(i, { to: x })} /></div>
          <div>{lbl("Ghi chú")}<Txt value={r.note} onChange={(x) => set(i, { note: x })} placeholder="…" /></div>
          {delBtn(() => del(i))}
        </div>
      ))}
      {addBtn(add, "Thêm lượt sử dụng")}
    </div>
  );
}

/* ---- Tab: Chi phí — BẢNG hiển thị cột chính, bấm dòng → modal sửa, Thêm phiếu chi → modal ---- */
const today10 = () => new Date().toISOString().slice(0, 10);
const esc = (s) => String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
const blankCost = () => ({ id: Date.now() + Math.random(), name: "", invoiceNo: "", kind: "fixed", spendDate: today10(), dueDate: "", amount: "", currentKm: "", supplier: "", note: "", paid: false, paidDate: "", paidMethod: "", paidRef: "", paidNote: "", approved: false });
const PAY_METHODS = ["Chuyển khoản", "Tiền mặt", "Khác"];

/* Modal DUYỆT THANH TOÁN — kế toán điền thông tin rồi mới duyệt */
function PayModal({ row, onConfirm, onClose }) {
  const { useState } = React;
  const [d, setD] = useState({ paidDate: row.paidDate || today10(), paidMethod: row.paidMethod || "Chuyển khoản", paidRef: row.paidRef || "", paidNote: row.paidNote || "" });
  const set = (np) => setD((x) => ({ ...x, ...np }));
  return (
    <Modal title="Duyệt thanh toán" subtitle={`${row.name || "(phiếu chi)"}${row.invoiceNo ? " · # " + row.invoiceNo : ""} · ${fmtVND(toNum(row.amount))}`} width={560} icon={<I.check />} onClose={onClose}
      footer={<div style={{ display: "flex", justifyContent: "flex-end", gap: 10, width: "100%" }}><Btn onClick={onClose}>Hủy</Btn><Btn variant="primary" onClick={() => onConfirm(d)}>Xác nhận đã thanh toán</Btn></div>}>
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, padding: "6px 0 2px" }}>
        <div>{lbl("Ngày thanh toán")}<DateField value={d.paidDate} onChange={(x) => set({ paidDate: x })} /></div>
        <div>{lbl("Hình thức")}
          <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 8, padding: 2, flexWrap: "wrap" }}>
            {PAY_METHODS.map((m) => { const on = (d.paidMethod || "") === m; return <button key={m} type="button" onClick={() => set({ paidMethod: m })} style={{ border: "none", cursor: "pointer", fontSize: 12.5, fontWeight: 600, padding: "6px 12px", borderRadius: 6, background: on ? "#fff" : "transparent", color: on ? "var(--accent)" : "var(--ink-4)", boxShadow: on ? "0 1px 2px rgba(16,19,23,.14)" : "none" }}>{m}</button>; })}
          </div>
        </div>
        <div>{lbl("Số chứng từ / UNC")}<Txt value={d.paidRef} onChange={(x) => set({ paidRef: x })} placeholder="VD: UNC-2026-0123" /></div>
        <div style={{ gridColumn: "1 / -1" }}>{lbl("Ghi chú kế toán")}<Txt value={d.paidNote} onChange={(x) => set({ paidNote: x })} placeholder="Diễn giải, tài khoản chi, người duyệt…" /></div>
      </div>
    </Modal>
  );
}

/* Modal điền 1 phiếu chi */
function CostModal({ data, isNew, onChange, onSave, onClose, costTypes = [], onUploadPhotos }) {
  const { useState, useRef } = React;
  const d = data; const set = (np) => onChange({ ...d, ...np });
  const rec = normKind(d.kind) === "recurring";
  const [err, setErr] = useState("");
  const [photoBusy, setPhotoBusy] = useState(false);
  const photoRef = useRef(null);
  const photos = Array.isArray(d.photos) ? d.photos : [];
  const pickPhotos = async (e) => {
    const files = Array.from(e.target.files || []); e.target.value = "";
    if (!files.length || !onUploadPhotos) return;
    setPhotoBusy(true);
    const added = await onUploadPhotos(files);
    if (added && added.length) set({ photos: [...photos, ...added] });
    setPhotoBusy(false);
  };
  const removePhoto = (i) => set({ photos: photos.filter((_, j) => j !== i) });
  const reqLbl = (t) => <span>{t} <span style={{ color: "var(--bad, #d6453d)" }}>*</span></span>;
  const trySave = () => {
    if (!String(d.name || "").trim()) return setErr("Vui lòng chọn hoặc nhập tên chi phí.");
    if (toNum(d.amount) <= 0) return setErr("Vui lòng nhập số tiền (lớn hơn 0).");
    setErr(""); onSave();
  };
  const f = (label, node, full) => <div style={{ gridColumn: full ? "1 / -1" : "auto" }}>{lbl(label)}{node}</div>;
  return (
    <Modal title={isNew ? "Thêm phiếu chi" : "Sửa phiếu chi"} subtitle={d.invoiceNo ? "# " + d.invoiceNo : "# hóa đơn tự sinh khi lưu"} width={640} icon={<I.fx />} onClose={onClose}
      footer={<div style={{ display: "flex", justifyContent: "flex-end", gap: 10, width: "100%" }}><Btn onClick={onClose}>Hủy</Btn><Btn variant="primary" onClick={trySave}>Lưu phiếu</Btn></div>}>
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, padding: "6px 0 2px" }}>
        {err && <div style={{ gridColumn: "1 / -1", display: "flex", gap: 7, alignItems: "center", fontSize: 12.5, color: "#b42318", background: "#fdecec", border: "1px solid #f3c9c9", borderRadius: 9, padding: "8px 12px" }}><i className="bi bi-exclamation-triangle-fill" /> {err}</div>}
        {f(reqLbl("Loại chi phí (chọn danh mục để nhóm báo cáo — hoặc gõ riêng)"), <Combo value={d.name} onChange={(x) => set({ name: x })} options={costTypes} placeholder="Chọn loại chi phí xe…" />, true)}
        {f("Loại", (
          <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 8, padding: 2 }}>
            {COST_KINDS.map(([k, t]) => { const on = normKind(d.kind) === k; return <button key={k} type="button" onClick={() => set({ kind: k })} style={{ border: "none", cursor: "pointer", fontSize: 12.5, fontWeight: 600, padding: "6px 14px", borderRadius: 6, background: on ? "#fff" : "transparent", color: on ? "var(--accent)" : "var(--ink-4)", boxShadow: on ? "0 1px 2px rgba(16,19,23,.14)" : "none" }}>{t}</button>; })}
          </div>
        ))}
        {f("# Hóa đơn (tự sinh)", <div style={{ padding: "8px 11px", fontSize: 13.5, fontWeight: 600, border: "1px dashed var(--line)", borderRadius: 9, background: "#fafbfc", color: d.invoiceNo ? "var(--ink-2)" : "var(--ink-4)" }} className="tnum">{d.invoiceNo || "Tự sinh khi lưu"}</div>)}
        {f("Ngày chi", <DateField value={d.spendDate} onChange={(x) => set({ spendDate: x })} />)}
        {f(reqLbl("Số tiền"), <Money value={d.amount} onChange={(x) => set({ amount: x })} dim />)}
        {rec && f(<span style={{ color: "var(--accent)" }}>Ngày hết hạn ★</span>, <DateField value={d.dueDate} onChange={(x) => set({ dueDate: x })} />)}
        {f("Nhà cung cấp", <Txt value={d.supplier} onChange={(x) => set({ supplier: x })} placeholder="…" />)}
        {f("KM hiện tại", <Num value={d.currentKm} onChange={(x) => set({ currentKm: x })} suffix="km" />)}
        {f("Ghi chú", <Txt value={d.note} onChange={(x) => set({ note: x })} placeholder="…" />, true)}

        {/* Ảnh thực tế (hóa đơn / đồng hồ km / phụ tùng) */}
        <div style={{ gridColumn: "1 / -1" }}>
          {lbl("Ảnh thực tế")}
          <div style={{ display: "flex", flexWrap: "wrap", gap: 9 }}>
            {photos.map((p, i) => (
              <div key={i} style={{ position: "relative", width: 84, height: 84, borderRadius: 10, overflow: "hidden", border: "1px solid var(--line)" }}>
                <a href={p.url} target="_blank" rel="noreferrer" title={p.name}><img src={p.url} alt={p.name || ""} style={{ width: "100%", height: "100%", objectFit: "cover", display: "block" }} /></a>
                <button type="button" onClick={() => removePhoto(i)} title="Xóa ảnh" style={{ position: "absolute", top: 3, right: 3, width: 22, height: 22, borderRadius: "50%", border: "none", background: "rgba(20,24,30,.62)", color: "#fff", cursor: "pointer", display: "grid", placeItems: "center", fontSize: 11 }}><i className="bi bi-x-lg" /></button>
              </div>
            ))}
            {onUploadPhotos && (
              <button type="button" onClick={() => photoRef.current && photoRef.current.click()} disabled={photoBusy}
                style={{ width: 84, height: 84, borderRadius: 10, border: "1.5px dashed var(--line)", background: "#fafbfc", cursor: photoBusy ? "default" : "pointer", display: "grid", placeItems: "center", color: "var(--ink-4)", gap: 3 }}>
                {photoBusy ? <span style={{ width: 16, height: 16, border: "2px solid var(--line)", borderTopColor: "var(--accent)", borderRadius: "50%", display: "inline-block", animation: "trk-spin .7s linear infinite" }} />
                  : <span style={{ display: "grid", placeItems: "center" }}><i className="bi bi-camera" style={{ fontSize: 19 }} /><span style={{ fontSize: 10.5, fontWeight: 600, marginTop: 3 }}>Thêm ảnh</span></span>}
              </button>
            )}
            {!onUploadPhotos && photos.length === 0 && <span style={{ fontSize: 12, color: "var(--ink-4)" }}>Chưa có ảnh.</span>}
          </div>
          <input ref={photoRef} type="file" accept="image/*" multiple onChange={pickPhotos} style={{ display: "none" }} />
        </div>

        <div style={{ gridColumn: "1 / -1", display: "flex", gap: 20, marginTop: 2, alignItems: "center" }}>
          <ChkBox checked={!!d.approved} onChange={(v) => set(v ? { approved: true } : { approved: false, paid: false })} label="Đã duyệt" />
          {d.approved
            ? <ChkBox checked={!!d.paid} onChange={(v) => set({ paid: v })} label="Đã thanh toán" />
            : <span style={{ fontSize: 12, color: "var(--ink-4)", display: "inline-flex", alignItems: "center", gap: 5 }}><i className="bi bi-lock-fill" /> Duyệt trước khi thanh toán</span>}
        </div>
        {d.paid && (
          <div style={{ gridColumn: "1 / -1", display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, padding: "12px 14px", background: "#f3f9f4", border: "1px solid #cde9d6", borderRadius: 10 }}>
            <div style={{ gridColumn: "1 / -1", fontSize: 12.5, fontWeight: 700, color: "var(--good)" }}><i className="bi bi-cash-coin me-1" /> Thông tin thanh toán (kế toán)</div>
            <div>{lbl("Ngày thanh toán")}<DateField value={d.paidDate} onChange={(x) => set({ paidDate: x })} /></div>
            <div>{lbl("Hình thức")}
              <select value={d.paidMethod || ""} onChange={(e) => set({ paidMethod: e.target.value })} style={{ width: "100%", padding: "8px 11px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 9, background: "#fff" }}>
                <option value="">— chọn —</option>{PAY_METHODS.map((m) => <option key={m} value={m}>{m}</option>)}
              </select>
            </div>
            <div>{lbl("Số chứng từ / UNC")}<Txt value={d.paidRef} onChange={(x) => set({ paidRef: x })} placeholder="VD: UNC-2026-0123" /></div>
            <div>{lbl("Ghi chú kế toán")}<Txt value={d.paidNote} onChange={(x) => set({ paidNote: x })} placeholder="Diễn giải, TK chi, người duyệt…" /></div>
          </div>
        )}
      </div>
    </Modal>
  );
}

function CostTab({ rows, onChange, costTypes, saving, onUploadPhotos, highlightId, onCancel }) {
  const { useState } = React;
  const [filter, setFilter] = useState("all");   // all | fixed | recurring | due
  const [edit, setEdit] = useState(null);         // { i, d }  (i < 0 = thêm mới)
  const [payIdx, setPayIdx] = useState(null);     // index phiếu đang duyệt thanh toán
  const all = rows || [];
  const isRec = (r) => normKind(r.kind) === "recurring";
  const isDue = (r) => isRec(r) && r.dueDate && dueStatus(r.dueDate).rank >= 2;
  const latestByName = {};
  all.forEach((r) => { if (isRec(r) && r.dueDate) { const n = (r.name || "").trim().toLowerCase(); if (!latestByName[n] || r.dueDate > latestByName[n].due) latestByName[n] = { due: r.dueDate, invoice: (r.invoiceNo || "").trim() }; } });
  const supLatest = (r) => { if (!isRec(r)) return null; const L = latestByName[(r.name || "").trim().toLowerCase()]; return (L && (!r.dueDate || L.due > r.dueDate)) ? L : null; };
  const match = (r) => filter === "fixed" ? !isRec(r) : filter === "recurring" ? isRec(r) : filter === "due" ? isDue(r) : true;
  const FILTERS = [["all", "Tất cả", all.length], ["fixed", "Cố định", all.filter((r) => !isRec(r)).length], ["recurring", "Định kỳ", all.filter(isRec).length], ["due", "Hết / sắp hết hạn", all.filter(isDue).length]];
  const shown = all.filter(match).length;

  // KM chênh lệch theo TÊN: so KM phiếu này với phiếu CŨ HƠN gần nhất cùng tên có KM → "+X km".
  const kmDelta = {};
  const byName = {};
  all.forEach((r, i) => { const n = (r.name || "").trim().toLowerCase(); if (!n) return; (byName[n] = byName[n] || []).push({ i, km: toNum(r.currentKm), date: r.spendDate || "" }); });
  Object.values(byName).forEach((arr) => {
    arr.sort((a, b) => (a.date || "").localeCompare(b.date || ""));   // cũ → mới
    let prev = null;
    arr.forEach((x) => { if (x.km > 0) { if (prev != null && x.km > prev) kmDelta[x.i] = x.km - prev; prev = x.km; } });
  });
  // Thứ tự hiển thị: ưu tiên theo trạng thái rồi MỚI NHẤT → cũ nhất (theo ngày chi). Giữ index gốc để sửa/xóa đúng dòng.
  // Nhóm: 0 = chưa duyệt & chưa TT (ưu tiên cao nhất) → 1 = đã duyệt, chờ TT → 2 = đã thanh toán (xuống cuối).
  const stRank = (r) => (r.paid ? 2 : r.approved ? 1 : 0);
  const order = all.map((_, i) => i).filter((i) => match(all[i])).sort((a, b) => {
    const dr = stRank(all[a]) - stRank(all[b]);
    if (dr !== 0) return dr;
    return (all[b].spendDate || "").localeCompare(all[a].spendDate || "");
  });

  const openAdd = () => setEdit({ i: -1, d: blankCost() });
  const openEdit = (i) => setEdit({ i, d: { ...all[i] } });
  const openDup = (i) => { const r = all[i]; setEdit({ i: -1, d: { ...blankCost(), name: r.name || "", supplier: r.supplier || "", kind: "recurring" } }); };
  const del = (i) => onChange(all.filter((_, j) => j !== i));
  const setRow = (i, np) => onChange(all.map((r, j) => (j === i ? { ...r, ...np } : r)));
  const saveModal = () => { if (!edit) return; const next = [...all]; if (edit.i < 0) next.push(edit.d); else next[edit.i] = edit.d; onChange(next); setEdit(null); };
  // Duyệt (kế toán xác nhận hợp lệ) — hỏi xác nhận
  const approve = async (i) => {
    const ok = await window.confirmAction({ title: "Duyệt phiếu chi?", text: `Xác nhận <b>duyệt</b> phiếu chi <b>${esc(all[i].name || all[i].invoiceNo || "")}</b>? Phiếu sẽ được <b>lưu ngay</b>.`, confirmText: '<i class="bi bi-check2-circle me-1"></i> Duyệt' });
    if (ok) setRow(i, { approved: true });
  };
  // Duyệt thanh toán — mở modal điền thông tin kế toán rồi mới duyệt
  const confirmPay = (info) => { if (payIdx == null) return; setRow(payIdx, { paid: true, ...info }); setPayIdx(null); };

  const th = (t, w, align) => <th style={{ textAlign: align || "left", padding: "10px 12px", fontSize: 10.5, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.03em", borderBottom: "1px solid var(--line)", whiteSpace: "nowrap", width: w }}>{t}</th>;
  const cell = { padding: "11px 12px", verticalAlign: "middle" };
  const iconBtn = (onClick, icon, title, accent) => (
    <button type="button" onClick={(e) => { e.stopPropagation(); onClick(); }} title={title}
      style={{ width: 30, height: 30, display: "inline-grid", placeItems: "center", border: `1px solid ${accent ? "var(--accent)" : "var(--line)"}`, borderRadius: 7, background: accent ? "var(--accent-weak-2)" : "#fff", color: accent ? "var(--accent)" : "var(--ink-4)", cursor: "pointer" }}>{icon}</button>
  );

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
      <div style={{ display: "flex", gap: 8, flexWrap: "wrap", alignItems: "center" }}>
        {all.length > 0 && <>
          <span style={{ fontSize: 12.5, color: "var(--ink-3)", fontWeight: 500 }}>Lọc:</span>
          <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 9, padding: 3, gap: 1 }}>
            {FILTERS.map(([k, t, n]) => {
              const on = filter === k;
              return (
                <button key={k} type="button" onClick={() => setFilter(k)}
                  style={{ display: "inline-flex", alignItems: "center", gap: 6, border: "none", cursor: "pointer", fontSize: 12.5, fontWeight: 600, padding: "6px 11px", borderRadius: 7,
                    background: on ? "#fff" : "transparent", color: on ? (k === "due" ? "var(--warn)" : "var(--accent)") : "var(--ink-3)", boxShadow: on ? "0 1px 2px rgba(16,19,23,.12)" : "none" }}>
                  {t}<span className="tnum" style={{ fontSize: 11, fontWeight: 700, color: on ? "var(--ink-3)" : "var(--ink-4)", background: "var(--line-2)", padding: "0 6px", borderRadius: 999, minWidth: 16, textAlign: "center" }}>{n}</span>
                </button>
              );
            })}
          </div>
        </>}
        <div style={{ flex: 1 }} />
        <button type="button" onClick={openAdd}
          style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "9px 15px", fontSize: 13.5, fontWeight: 600, border: "none", borderRadius: 9, background: "var(--accent)", color: "#fff", cursor: "pointer", boxShadow: "0 1px 2px rgba(42,111,219,.4)" }}>
          <I.plus /> Thêm phiếu chi
        </button>
      </div>

      {all.length === 0 && <div style={{ padding: "30px 4px", textAlign: "center", fontSize: 13, color: "var(--ink-4)", background: "#fff", border: "1px solid var(--line)", borderRadius: 12 }}>Chưa có phiếu chi nào. Bấm <b>Thêm phiếu chi</b>.</div>}
      {all.length > 0 && shown === 0 && <div style={{ padding: "20px 4px", textAlign: "center", fontSize: 13, color: "var(--ink-4)" }}>Không có phiếu chi khớp bộ lọc.</div>}

      {all.length > 0 && shown > 0 && (
        <div style={{ border: "1px solid var(--line)", borderRadius: 12, overflow: "hidden" }}>
          <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13 }}>
            <thead><tr style={{ background: "#fafbfc" }}>
              {th("# Hóa đơn", 104)}{th("Khoản chi · KM")}{th("Ngày chi", 104)}{th("Số tiền", 124, "right")}{th("Hạn & trạng thái", 200)}{th("Duyệt · TT", 132, "center")}{th("", 74, "center")}
            </tr></thead>
            <tbody>
              {order.map((i) => {
                const r = all[i];
                const rec = isRec(r); const sl = supLatest(r); const sup = !!sl; const ds = rec && r.dueDate && !sup ? dueStatus(r.dueDate) : null;
                const isHl = highlightId != null && String(r.id) === String(highlightId);
                return (
                  <tr key={r.id || i} id={"trk-cost-" + (r.id || i)} className={isHl ? "trk-row-hl" : undefined} onClick={() => openEdit(i)} style={{ borderBottom: "1px solid var(--line-2)", cursor: "pointer", background: r.cancelled ? "#f7f8fa" : (sup ? "#fafbfc" : "transparent"), opacity: r.cancelled ? 0.6 : (sup ? 0.66 : 1) }}
                    onMouseEnter={(e) => (e.currentTarget.style.background = "var(--accent-weak-2)")} onMouseLeave={(e) => (e.currentTarget.style.background = r.cancelled ? "#f7f8fa" : (sup ? "#fafbfc" : "transparent"))}>
                    <td style={{ ...cell }} className="tnum"><span style={{ fontWeight: 600, color: r.invoiceNo ? "var(--accent)" : "var(--ink-4)" }}>{r.invoiceNo || "(mới)"}</span></td>
                    <td style={cell}>
                      <div style={{ fontWeight: 600, textDecoration: r.cancelled ? "line-through" : "none" }}>{r.name || <span style={{ color: "var(--ink-4)", fontWeight: 400 }}>(chưa đặt tên)</span>}</div>
                      <span style={{ fontSize: 10.5, fontWeight: 700, color: rec ? "var(--accent)" : "var(--ink-3)", background: rec ? "var(--accent-weak)" : "var(--line-2)", padding: "1px 8px", borderRadius: 999 }}>{rec ? "Định kỳ" : "Cố định"}</span>
                      {r.requester && <span style={{ fontSize: 11, color: "var(--ink-4)", marginLeft: 8 }} title="Người yêu cầu tạo phiếu"><i className="bi bi-person" /> {r.requester}</span>}
                      {r.supplier && <span style={{ fontSize: 11.5, color: "var(--ink-4)", marginLeft: 8 }}>{r.supplier}</span>}
                      {Array.isArray(r.photos) && r.photos.length > 0 && <span title={`${r.photos.length} ảnh thực tế`} style={{ fontSize: 10.5, fontWeight: 700, color: "var(--good)", background: "var(--good-weak)", padding: "1px 7px", borderRadius: 999, marginLeft: 8 }}><i className="bi bi-camera-fill" /> {r.photos.length}</span>}
                      {toNum(r.currentKm) > 0 && <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 3 }} className="tnum"><i className="bi bi-speedometer2" /> {fmtNum(toNum(r.currentKm))} km{kmDelta[i] ? <b style={{ color: "var(--good)", marginLeft: 6 }}>▲ +{fmtNum(kmDelta[i])} km</b> : ""}</div>}
                    </td>
                    <td style={cell} className="tnum">{fmtDate(r.spendDate) || "—"}</td>
                    <td style={{ ...cell, textAlign: "right", fontWeight: 600 }} className="tnum">{fmtVND(toNum(r.amount))}</td>
                    <td style={cell}>
                      {!rec ? <span style={{ color: "var(--ink-4)" }}>—</span>
                        : <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
                            <span className="tnum" style={{ color: "var(--ink-3)" }}>{fmtDate(r.dueDate) || "chưa có"}</span>
                            {sup ? <span style={{ fontSize: 11, fontWeight: 700, color: "var(--good)", background: "var(--good-weak)", padding: "2px 9px", borderRadius: 999, display: "inline-flex", alignItems: "center", gap: 4 }}><i className="bi bi-check-circle-fill" /> Đã gia hạn{sl.invoice ? ` ở HĐ #${sl.invoice}` : ""}</span>
                              : !r.dueDate ? <span style={{ fontSize: 11, fontWeight: 600, color: "var(--warn)" }}>chưa đặt hạn</span>
                              : <span style={{ fontSize: 11, fontWeight: 700, color: ds.color, background: ds.bg, padding: "2px 9px", borderRadius: 999, display: "inline-flex", alignItems: "center", gap: 4 }}>{ds.key === "expired" ? <><i className="bi bi-exclamation-triangle-fill" /> Hết hạn</> : ds.key === "soon" ? <><i className="bi bi-clock-fill" /> Còn {ds.days}n</> : "Còn hạn"}</span>}
                          </div>}
                    </td>
                    <td style={{ ...cell, textAlign: "center" }} onClick={(e) => e.stopPropagation()}>
                      {r.cancelled ? <span style={{ fontSize: 10.5, fontWeight: 700, color: "#64748b", background: "#eef1f6", padding: "3px 10px", borderRadius: 999, display: "inline-flex", alignItems: "center", gap: 4 }}><i className="bi bi-x-circle-fill" /> Đã hủy</span> : (() => {
                        const chip = (t, title) => <span title={title} style={{ fontSize: 10.5, fontWeight: 700, color: "var(--good)", background: "var(--good-weak)", padding: "3px 9px", borderRadius: 999, display: "inline-flex", alignItems: "center", gap: 4, justifyContent: "center" }}><i className="bi bi-check-circle-fill" /> {t}</span>;
                        const apBtn = (label, onClick) => <button type="button" onClick={onClick} style={{ fontSize: 11, fontWeight: 600, padding: "3px 10px", border: "1px solid var(--line)", borderRadius: 999, background: "#fff", color: "var(--ink-2)", cursor: "pointer" }}
                          onMouseEnter={(e) => { e.currentTarget.style.borderColor = "var(--good)"; e.currentTarget.style.color = "var(--good)"; }} onMouseLeave={(e) => { e.currentTarget.style.borderColor = "var(--line)"; e.currentTarget.style.color = "var(--ink-2)"; }}>{label}</button>;
                        const payTitle = r.paid ? [r.paidDate && fmtDate(r.paidDate), r.paidMethod, r.paidRef && ("#" + r.paidRef), r.paidNote].filter(Boolean).join(" · ") : "";
                        return (
                          <div style={{ display: "inline-flex", flexDirection: "column", gap: 5 }}>
                            {r.approved ? chip("Đã duyệt") : apBtn("Duyệt", () => approve(i))}
                            {r.paid ? chip("Đã TT", payTitle)
                              : r.approved ? apBtn("Duyệt TT", () => setPayIdx(i))
                              : <span title="Phải duyệt phiếu trước khi thanh toán" style={{ fontSize: 10, fontWeight: 600, color: "var(--ink-4)", display: "inline-flex", alignItems: "center", gap: 4, justifyContent: "center" }}><i className="bi bi-lock-fill" /> Chờ duyệt</span>}
                          </div>
                        );
                      })()}
                    </td>
                    <td style={{ ...cell, textAlign: "center", whiteSpace: "nowrap" }} onClick={(e) => e.stopPropagation()}>
                      {rec && !r.cancelled && iconBtn(() => openDup(i), <i className="bi bi-arrow-repeat" />, "Tạo phiếu mới (gia hạn)", true)}
                      {onCancel && r.canCancel && r.id && <>{iconBtn(() => onCancel(r.id), <i className="bi bi-x-circle" />, "Hủy phiếu")}<span style={{ display: "inline-block", width: 4 }} /></>}
                      {iconBtn(() => del(i), <I.trash />, "Xóa phiếu")}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      <span style={{ fontSize: 11.5, color: "var(--ink-4)" }}><i className="bi bi-check2-circle" style={{ color: "var(--good)" }} /> Mọi thao tác ở mục Chi phí (thêm/sửa/duyệt/thanh toán/xóa) được <b>lưu ngay</b> — không cần bấm Lưu. Khoản <b>định kỳ</b> (bảo hiểm, đăng kiểm…): đến hạn bấm <i className="bi bi-arrow-repeat" /> để <b>tạo phiếu mới</b> → điền tiền + ngày hết hạn mới; phiếu cũ tự chuyển <b>“đã gia hạn ở HĐ #…”</b>. <b># hóa đơn tự sinh</b>.</span>

      {edit && <CostModal data={edit.d} isNew={edit.i < 0} costTypes={costTypes} onUploadPhotos={onUploadPhotos} onChange={(d) => setEdit((e) => ({ ...e, d }))} onSave={saveModal} onClose={() => setEdit(null)} />}
      {payIdx != null && all[payIdx] && <PayModal row={all[payIdx]} onConfirm={confirmPay} onClose={() => setPayIdx(null)} />}
    </div>
  );
}

/* ---- Khối tài liệu xe (ảnh/PDF/Word/Excel) — upload nhiều file ---- */
const VEH_DOC_TYPES = ["Đăng ký xe", "Đăng kiểm", "Bảo hiểm", "Hợp đồng mua", "Phù hiệu", "Khác"];

function DocsBlock({ docs, busy, docType, setDocType, onPick, onDelete, canEdit }) {
  const fileRef = useRef(null);
  return (
    <div>
      {lbl("Tài liệu (đăng ký, đăng kiểm, bảo hiểm, hợp đồng… — ảnh / PDF / Word / Excel)")}
      {canEdit && (
        <div style={{ display: "flex", gap: 8, alignItems: "center", marginBottom: 12, flexWrap: "wrap" }}>
          <select value={docType} onChange={(e) => setDocType(e.target.value)} style={{ padding: "7px 10px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, background: "#fff" }}>
            {VEH_DOC_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
          </select>
          <input ref={fileRef} type="file" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv" onChange={onPick} style={{ display: "none" }} />
          <button type="button" onClick={() => fileRef.current && fileRef.current.click()} disabled={busy}
            style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 12px", fontSize: 13, fontWeight: 600, border: "1px dashed var(--accent)", borderRadius: 9, background: "var(--accent-weak-2)", color: "var(--accent)", cursor: busy ? "default" : "pointer" }}>
            <I.plus /> {busy ? "Đang tải…" : "Chọn nhiều file/ảnh"}
          </button>
        </div>
      )}
      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(140px, 1fr))", gap: 10 }}>
        {(docs || []).map((doc, di) => (
          <div key={di} style={{ border: "1px solid var(--line)", borderRadius: 10, overflow: "hidden", background: "#fff", position: "relative" }}>
            <a href={doc.url} target="_blank" rel="noreferrer" style={{ display: "grid", placeItems: "center", height: 96, overflow: "hidden", background: "#fff" }}>
              {doc.isImage
                ? <img src={doc.url} alt={doc.name} style={{ width: "100%", height: "100%", objectFit: "cover" }} />
                : <span style={{ fontSize: 30, color: "var(--ink-4)" }}><i className="bi bi-file-earmark-text" /></span>}
            </a>
            <div style={{ padding: "6px 8px", borderTop: "1px solid var(--line-2)" }}>
              <div style={{ fontSize: 11, fontWeight: 600, color: "var(--accent)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{doc.type}</div>
              <div style={{ fontSize: 10.5, color: "var(--ink-4)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }} title={doc.name}>{doc.name}</div>
            </div>
            {canEdit && <button type="button" onClick={() => onDelete(di)} title="Xóa tài liệu"
              style={{ position: "absolute", top: 4, right: 4, width: 24, height: 24, display: "grid", placeItems: "center", border: "none", borderRadius: 6, background: "rgba(255,255,255,.92)", color: "var(--danger)", cursor: "pointer", boxShadow: "0 1px 3px rgba(0,0,0,.15)" }}><I.trash /></button>}
          </div>
        ))}
        {!(docs || []).length && <div style={{ gridColumn: "1 / -1", padding: "14px 4px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có tài liệu nào.</div>}
      </div>
    </div>
  );
}

/* ---- Tab: Thông tin xe (mã khung, nơi mua… + tài liệu) ---- */
function InfoTab({ info, onChange, canEdit, docsProps }) {
  const i = info || {};
  const set = (k, v) => onChange({ ...i, [k]: v });
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 16 }}>
      <div style={card}>
        <div style={{ fontSize: 13.5, fontWeight: 700, marginBottom: 12 }}>Thông tin xe</div>
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: 12 }}>
          <div>{lbl("Mã khung xe (số khung / VIN)")}<Txt value={i.chassisNo} onChange={(x) => set("chassisNo", x)} placeholder="VD: RLHE…123456" /></div>
          <div>{lbl("Số máy")}<Txt value={i.engineNo} onChange={(x) => set("engineNo", x)} placeholder="VD: D4DB…" /></div>
          <div>{lbl("Hãng / Đời xe")}<Txt value={i.brand} onChange={(x) => set("brand", x)} placeholder="VD: Howo, Hyundai…" /></div>
          <div>{lbl("Năm sản xuất")}<Txt value={i.year} onChange={(x) => set("year", x)} placeholder="VD: 2020" /></div>
          <div>{lbl("Nơi mua / Nhà cung cấp")}<Txt value={i.purchasePlace} onChange={(x) => set("purchasePlace", x)} placeholder="VD: Đại lý ABC" /></div>
          <div>{lbl("Ngày mua")}<DateField value={i.purchaseDate} onChange={(x) => set("purchaseDate", x)} /></div>
          <div>{lbl("Giá mua")}<Money value={i.purchasePrice} onChange={(x) => set("purchasePrice", x)} dim /></div>
          <div>{lbl("Hạn đăng kiểm")}<DateField value={i.registrationDue} onChange={(x) => set("registrationDue", x)} /></div>
          <div>{lbl("Hạn bảo hiểm")}<DateField value={i.insuranceDue} onChange={(x) => set("insuranceDue", x)} /></div>
        </div>
        <div style={{ marginTop: 12 }}>{lbl("Ghi chú")}<Txt value={i.note} onChange={(x) => set("note", x)} placeholder="Ghi chú thêm về xe…" /></div>
      </div>
      <div style={card}>
        <DocsBlock {...docsProps} canEdit={canEdit} />
      </div>
    </div>
  );
}

/* ---- Tab: Định mức km theo loại chi phí (chặn tạo yêu cầu chi quá sớm) ---- */
function AllowanceTab({ rows, onChange, costItems, addCostItem }) {
  const set = (i, np) => onChange(rows.map((r, j) => (j === i ? { ...r, ...np } : r)));
  const add = () => onChange([...(rows || []), { costItem: "", km: "" }]);
  const del = (i) => onChange(rows.filter((_, j) => j !== i));
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
      <div style={{ fontSize: 12, color: "var(--ink-3)", lineHeight: 1.55, background: "#eef4ff", border: "1px solid #d6e3fb", borderRadius: 10, padding: "10px 13px" }}>
        <i className="bi bi-info-circle-fill" style={{ color: "var(--accent)" }} /> Định mức <b>KM tối thiểu</b> giữa 2 lần cùng loại chi phí. Khi tài xế gửi <b>yêu cầu chi</b> qua link công khai, hệ thống <b>chặn</b> nếu KM chưa tăng đủ định mức so với phiếu <b>đã duyệt</b> gần nhất cùng loại. (Để trống / 0 = không giới hạn.)
      </div>
      {(rows || []).length === 0 && <div style={{ padding: "10px 2px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có định mức — bấm <b>+ Thêm định mức</b>.</div>}
      {(rows || []).map((r, i) => (
        <div key={i} style={{ display: "grid", gridTemplateColumns: "1fr 170px 40px", gap: 10, alignItems: "end", ...card }}>
          <div>{lbl("Loại chi phí")}<Combo value={r.costItem} onChange={(x) => set(i, { costItem: x })} options={costItems || []} onCreate={(v) => { set(i, { costItem: v }); addCostItem && addCostItem(v); }} placeholder="Chọn loại chi phí…" /></div>
          <div>{lbl("Định mức (km)")}<Num value={r.km} onChange={(x) => set(i, { km: x })} suffix="km" /></div>
          {delBtn(() => del(i))}
        </div>
      ))}
      {addBtn(add, "Thêm định mức")}
    </div>
  );
}

function FleetApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const api = (method, url, body) => window.trkApi(method, url, body);
  const canEdit = !!T.canEdit;
  const publicUrl = ROUTES.spendRequest || "/yeu-cau-chi";
  const copyPublic = () => { try { navigator.clipboard && navigator.clipboard.writeText(publicUrl); window.trkToast && window.trkToast("Đã sao chép link"); } catch (e) {} };

  const [vehicles] = useState(B.vehicles || []);
  const [costItems, setCostItems] = useState(B.costItems || []);   // danh mục Khoản chi phí (Combo tên phiếu)
  const addCostItem = async (name) => {
    name = (name || "").trim(); if (!name) return;
    setCostItems((c) => (c.includes(name) ? c : [...c, name]));   // hiện ngay trong dropdown
    try { const r = await fetch(ROUTES.costItem, { method: "POST", headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": T.csrf }, body: JSON.stringify({ name }) }).then((x) => x.json()); if (r && r.ok) setCostItems(r.costItems); } catch (e) {}
  };
  const expiringCosts = B.expiringCosts || [];      // chi phí định kỳ hết hạn / sắp hết (toàn đội xe)
  const pendingCosts = B.pendingCosts || [];        // phiếu chi chưa duyệt / chờ thanh toán (toàn đội xe)
  const [showExp, setShowExp] = useState(false);
  const [showPending, setShowPending] = useState(false);
  const [vFilter, setVFilter] = useState("all");   // all | expired | soon | ok
  const [vQuery, setVQuery] = useState("");
  const [selId, setSelId] = useState(null);
  const [detail, setDetail] = useState(null);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [dirty, setDirty] = useState(false);
  const [tab, setTab] = useState("info");
  const [docType, setDocType] = useState("Khác");   // loại tài liệu mặc định
  const [docBusy, setDocBusy] = useState(false);
  const [secLoading, setSecLoading] = useState(false);
  const loadedSecs = useRef(new Set());   // nhóm đã lazy-load (usages/costs/depreciations)
  const pendingCost = useRef(null);       // costId cần cuộn tới sau khi tab Chi phí load (deep-link từ thông báo)
  const [hlCost, setHlCost] = useState(null);   // id phiếu chi đang được highlight

  const setHash = (id, t) => { try { window.history.replaceState(null, "", "#" + id + "/" + t); } catch (e) {} };
  // Lazy-load nhóm con khi mở tab (truyền id để tránh stale selId lúc vừa mở xe)
  const ensureSection = (tabKey, id) => {
    const sec = SECTION_OF[tabKey]; id = id || selId;
    if (!sec || !id || loadedSecs.current.has(sec)) return;
    loadedSecs.current.add(sec);
    setSecLoading(true);
    api("GET", ROUTES.fleet + id + "/section/" + sec).then((r) => {
      if (r && r.ok) setDetail((d) => ({ ...d, [sec]: r[sec] || [] }));
      else loadedSecs.current.delete(sec);
      setSecLoading(false);
    }).catch(() => { loadedSecs.current.delete(sec); setSecLoading(false); });
  };
  const open = (v, tabKey) => {
    const t = TAB_KEYS.includes(tabKey) ? tabKey : "info";
    loadedSecs.current = new Set();
    setSelId(v.id); setDetail(null); setDirty(false); setTab(t); setLoading(true);
    setHash(v.id, t);
    api("GET", ROUTES.fleet + v.id + "/data").then((r) => {
      if (r && r.ok) setDetail(r.vehicle);
      setLoading(false);
      ensureSection(t, v.id);
    }).catch(() => setLoading(false));
  };
  const goTab = (k) => { setTab(k); if (selId) setHash(selId, k); ensureSection(k); };   // đổi tab → ghi #id/tab + lazy-load nhóm
  const back = async () => {
    if (dirty && !(await window.confirmAction({ title: "Thoát khi chưa lưu?", text: "Bạn có thay đổi <b>chưa lưu</b> (tab Thông tin/Định mức/Khấu hao/Sử dụng). Thoát ra sẽ <b>mất</b> các thay đổi này.", confirmText: '<i class="bi bi-box-arrow-left me-1"></i> Thoát, không lưu', danger: true }))) return;
    setSelId(null); setDetail(null); setDirty(false); loadedSecs.current = new Set(); try { window.history.replaceState(null, "", "#"); } catch (e) {}
  };
  const upd = (np) => { setDetail((d) => ({ ...d, ...np })); setDirty(true); };
  // Admin HỦY phiếu chi (chưa thanh toán) — endpoint riêng, rồi nạp lại danh sách chi phí
  const cancelCost = async (id) => {
    const ok = await window.confirmAction({ title: "Hủy phiếu chi?", text: "Phiếu sẽ chuyển <b>Đã hủy</b> và bị loại khỏi tổng chi phí/báo cáo.", confirmText: '<i class="bi bi-x-circle me-1"></i> Hủy phiếu', danger: true });
    if (!ok) return;
    try {
      const r = await api("PUT", ROUTES.cancelCost + id + "/cancel");
      if (r && r.ok) {
        window.trkToast && window.trkToast("Đã hủy phiếu");
        const s = await api("GET", ROUTES.fleet + selId + "/section/costs");
        if (s && s.ok) setDetail((d) => ({ ...d, costs: s.costs || [] }));
      } else window.trkToast && window.trkToast((r && r.message) || "Không hủy được", "error");
    } catch (e) {}
  };
  // Chi phí: LƯU NGAY mỗi thao tác (thêm/sửa/duyệt/thanh toán/xóa) — không gộp vào nút Lưu chung
  const [costSaving, setCostSaving] = useState(false);
  const saveCosts = (rows) => {
    setDetail((d) => ({ ...d, costs: rows }));   // hiển thị ngay (optimistic)
    if (!selId) return;
    setCostSaving(true);
    api("PUT", ROUTES.fleet + selId, { data: { costs: rows } })
      .then((r) => {
        setCostSaving(false);
        if (r && r.ok) { setDetail((d) => ({ ...d, costs: (r.vehicle && r.vehicle.costs) || rows })); window.trkToast && window.trkToast("Đã lưu phiếu chi"); }
        else window.trkToast && window.trkToast("Lưu thất bại", "error");
      })
      .catch(() => { setCostSaving(false); window.trkToast && window.trkToast("Lỗi kết nối khi lưu", "error"); });
  };
  // Upload ảnh thực tế cho phiếu chi → trả metadata [{file,name,mime,size,url}] để đính vào phiếu
  const uploadCostPhotos = async (files) => {
    if (!selId || !files || !files.length) return [];
    const fd = new FormData(); Array.from(files).forEach((f) => fd.append("files[]", f));
    try {
      const res = await window.trkUpload("POST", ROUTES.fleet + selId + "/cost-photo", fd);
      if (res && res.ok) return res.photos || [];
      window.trkToast && window.trkToast((res && res.message) || "Tải ảnh thất bại", "error");
    } catch (e) { window.trkToast && window.trkToast("Lỗi kết nối khi tải ảnh", "error"); }
    return [];
  };
  // Lưu CHỈ các nhóm đã tải (Array.isArray) + info → nhóm chưa mở không bị xóa; merge echo để lấy id mới
  const save = () => {
    if (!dirty || saving || !detail) return;
    setSaving(true);
    const data = { info: detail.info || {}, allowances: detail.allowances || [] };
    ["usages", "costs", "depreciations"].forEach((s) => { if (Array.isArray(detail[s])) data[s] = detail[s]; });
    api("PUT", ROUTES.fleet + selId, { data })
      .then((r) => { setSaving(false); if (r && r.ok) { setDetail((d) => ({ ...d, ...r.vehicle })); setDirty(false); window.trkToast && window.trkToast("Đã lưu"); } else window.trkToast && window.trkToast("Lưu thất bại", "error"); })
      .catch(() => { setSaving(false); window.trkToast && window.trkToast("Lỗi kết nối khi lưu", "error"); });
  };
  // Tài liệu xe — upload/xóa lưu NGAY (không nằm trong nút Lưu thông tin)
  const uploadDocs = async (e) => {
    const files = Array.from(e.target.files || []); e.target.value = "";
    if (!files.length || !selId) return;
    const fd = new FormData(); files.forEach((f) => fd.append("files[]", f)); fd.append("type", docType);
    setDocBusy(true);
    try {
      const res = await window.trkUpload("POST", ROUTES.fleet + selId + "/docs", fd);
      if (res && res.ok) { setDetail((d) => ({ ...d, docs: res.docs })); window.trkToast && window.trkToast(`Đã tải ${files.length} tài liệu`); }
      else window.trkToast && window.trkToast((res && res.message) || "Tải lên thất bại", "error");
    } catch (err) { window.trkToast && window.trkToast("Lỗi kết nối khi tải lên", "error"); }
    setDocBusy(false);
  };
  const deleteDoc = async (idx) => {
    if (!selId) return;
    const ok = await window.confirmAction({ title: "Xóa tài liệu?", text: "Tài liệu này sẽ bị xóa vĩnh viễn.", confirmText: '<i class="bi bi-trash me-1"></i> Xóa', danger: true });
    if (!ok) return;
    const res = await fetch(ROUTES.fleet + selId + "/docs/" + idx, { method: "DELETE", headers: { "Accept": "application/json", "X-CSRF-TOKEN": T.csrf } }).then((r) => r.json());
    if (res && res.ok) setDetail((d) => ({ ...d, docs: res.docs }));
  };
  // Cảnh báo khi reload/đóng tab lúc còn thay đổi chưa lưu (chỉ áp dụng các tab gộp; Chi phí lưu ngay)
  useEffect(() => {
    if (!dirty) return;
    const h = (e) => { e.preventDefault(); e.returnValue = ""; return ""; };
    window.addEventListener("beforeunload", h);
    return () => window.removeEventListener("beforeunload", h);
  }, [dirty]);
  // mở theo hash: #<id> | #<id>/<tab> | #<id>/cost/<costId> (deep-link từ thông báo).
  // Chạy lúc mount VÀ khi hash đổi mà KHÔNG reload (bấm thông báo lúc đang ở sẵn trang
  // này → location.href chỉ đổi hash). Ghi hash nội bộ dùng replaceState nên không phát
  // hashchange → không lặp.
  useEffect(() => {
    const applyHash = () => {
      const h = (window.location.hash || "").replace(/^#/, "");
      const [idStr, tabStr, costStr] = h.split("/");
      if (!idStr) return;
      const v = vehicles.find((x) => String(x.id) === idStr);
      if (!v) return;
      if (costStr) pendingCost.current = costStr;   // nhớ phiếu cần cuộn tới
      open(v, tabStr);
    };
    applyHash();
    window.addEventListener("hashchange", applyHash);
    return () => window.removeEventListener("hashchange", applyHash);
  }, []);
  // Khi tab Chi phí đã load xong → cuộn tới + highlight đúng phiếu chi (deep-link)
  useEffect(() => {
    const cid = pendingCost.current;
    if (!cid || tab !== "cost" || !detail || !Array.isArray(detail.costs)) return;
    if (!detail.costs.some((c) => String(c.id) === String(cid))) return;   // phiếu không thuộc xe này
    pendingCost.current = null;
    setHlCost(String(cid));
    setTimeout(() => { const el = document.getElementById("trk-cost-" + cid); if (el) el.scrollIntoView({ behavior: "smooth", block: "center" }); }, 140);
    const t = setTimeout(() => setHlCost(null), 2800);
    return () => clearTimeout(t);
  }, [detail, tab]);

  // ---------- DANH SÁCH XE ----------
  if (!selId) {
    const expiredCount = vehicles.filter((v) => vehRank(v) === 3).length;
    const soonCount    = vehicles.filter((v) => vehRank(v) === 2).length;
    const okCount      = vehicles.filter((v) => vehRank(v) === 1).length;
    const costExpired      = expiringCosts.filter((c) => c.status === "expired");
    const costSoon         = expiringCosts.filter((c) => c.status === "soon");
    const costExpiredTotal = costExpired.reduce((a, c) => a + (c.amount || 0), 0);
    const needApprove   = pendingCosts.filter((c) => !c.approved);                 // chưa duyệt
    const needPay       = pendingCosts.filter((c) => c.approved && !c.paid);       // đã duyệt, chờ thanh toán
    const needApproveAmt = needApprove.reduce((a, c) => a + (c.amount || 0), 0);
    const needPayAmt     = needPay.reduce((a, c) => a + (c.amount || 0), 0);
    const q = vQuery.trim().toLowerCase();
    const list = vehicles.filter((v) => {
      if (q && !(v.plate || "").toLowerCase().includes(q)) return false;
      if (vFilter === "expired") return vehRank(v) === 3;
      if (vFilter === "soon") return vehRank(v) === 2;
      if (vFilter === "ok") return vehRank(v) === 1;
      return true;
    });
    const FILTERS = [["all", "Tất cả", vehicles.length, "var(--ink-2)"], ["expired", "Hết hạn", expiredCount, "var(--danger)"], ["soon", "Sắp hết hạn", soonCount, "var(--warn)"], ["ok", "Còn hạn", okCount, "var(--good)"]];
    const th = (t, align) => <th style={{ textAlign: align || "left", padding: "10px 12px", fontSize: 11, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.04em", borderBottom: "1px solid var(--line)", whiteSpace: "nowrap" }}>{t}</th>;

    return (
      <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)", overflow: "auto" }}>
        <div style={{ maxWidth: 1120, width: "100%", margin: "0 auto", padding: "22px" }}>
          <div style={{ marginBottom: 14 }}>
            <h1 style={{ margin: 0, fontSize: 22, fontWeight: 700, letterSpacing: "-0.02em" }}>Quản lý xe</h1>
            <div style={{ fontSize: 13, color: "var(--ink-3)", marginTop: 3 }}>{vehicles.length} xe nội bộ (MBF). Bấm vào xe để xem thông tin, khấu hao, chi phí.</div>
          </div>

          {/* Link public — gửi yêu cầu chi cho tài xế */}
          <div style={{ display: "flex", alignItems: "center", gap: 12, padding: "12px 14px", marginBottom: 14, borderRadius: 10, background: "#eef4ff", border: "1px solid #d6e3fb", flexWrap: "wrap" }}>
            <i className="bi bi-link-45deg" style={{ fontSize: 22, color: "var(--accent)" }} />
            <div style={{ flex: 1, minWidth: 240 }}>
              <div style={{ fontSize: 13, fontWeight: 700, color: "var(--ink-2)" }}>Link gửi yêu cầu chi (cho tài xế)</div>
              <div style={{ fontSize: 12, color: "var(--ink-3)", marginTop: 2, lineHeight: 1.5 }}>Gửi link này cho tài xế để đề nghị phiếu chi từ điện thoại — phiếu vào hàng chờ <b>duyệt</b>. Đặt <b>định mức km</b> ở từng xe (tab <b>Định mức</b>) để chặn yêu cầu khi chưa đi đủ số km.</div>
              <a href={publicUrl} target="_blank" rel="noreferrer" style={{ display: "inline-block", fontSize: 12, color: "var(--accent)", marginTop: 4, wordBreak: "break-all", fontWeight: 600, textDecoration: "none" }} className="tnum">{publicUrl}</a>
            </div>
            <div style={{ display: "flex", gap: 8 }}>
              <button type="button" onClick={copyPublic} style={{ fontSize: 12.5, fontWeight: 600, padding: "7px 13px", border: "1px solid var(--accent)", borderRadius: 8, background: "#fff", color: "var(--accent)", cursor: "pointer", display: "inline-flex", alignItems: "center", gap: 5 }}><i className="bi bi-clipboard" /> Sao chép</button>
              <a href={publicUrl} target="_blank" rel="noreferrer" style={{ fontSize: 12.5, fontWeight: 600, padding: "7px 13px", border: "none", borderRadius: 8, background: "var(--accent)", color: "#fff", textDecoration: "none", display: "inline-flex", alignItems: "center", gap: 5 }}><i className="bi bi-box-arrow-up-right" /> Mở</a>
            </div>
          </div>

          {/* Cảnh báo hạn */}
          {(expiredCount > 0 || soonCount > 0) && (
            <div style={{ display: "flex", alignItems: "center", gap: 10, padding: "11px 14px", marginBottom: 14, borderRadius: 10,
              background: expiredCount > 0 ? "#fce8e8" : "#fcf3e2", border: `1px solid ${expiredCount > 0 ? "#f3c9c9" : "#f0dcae"}` }}>
              <i className="bi bi-exclamation-triangle-fill" style={{ fontSize: 18, color: expiredCount > 0 ? "var(--danger)" : "var(--warn)" }} />
              <div style={{ fontSize: 13, color: "var(--ink-2)" }}>
                {expiredCount > 0 && <span><b style={{ color: "var(--danger)" }}>{expiredCount} xe hết hạn</b> đăng kiểm/bảo hiểm. </span>}
                {soonCount > 0 && <span><b style={{ color: "var(--warn)" }}>{soonCount} xe sắp hết hạn</b> (trong 30 ngày). </span>}
                <span style={{ color: "var(--ink-3)" }}>Cần gia hạn để tránh phạt & gián đoạn vận hành.</span>
              </div>
            </div>
          )}

          {/* Cảnh báo chi phí định kỳ hết hạn → bấm xem popup */}
          {expiringCosts.length > 0 && (
            <button type="button" onClick={() => setShowExp(true)}
              style={{ width: "100%", textAlign: "left", display: "flex", alignItems: "center", gap: 10, padding: "11px 14px", marginBottom: 14, borderRadius: 10, cursor: "pointer",
                background: costExpired.length ? "#fce8e8" : "#fcf3e2", border: `1px solid ${costExpired.length ? "#f3c9c9" : "#f0dcae"}` }}>
              <i className="bi bi-receipt-cutoff" style={{ fontSize: 18, color: costExpired.length ? "var(--danger)" : "var(--warn)" }} />
              <div style={{ flex: 1, fontSize: 13, color: "var(--ink-2)" }}>
                {costExpired.length > 0 && <span><b style={{ color: "var(--danger)" }}>{costExpired.length} chi phí định kỳ hết hạn</b> · tổng <b className="tnum">{fmtVND(costExpiredTotal)}</b>. </span>}
                {costSoon.length > 0 && <span><b style={{ color: "var(--warn)" }}>{costSoon.length} khoản sắp hết hạn</b>. </span>}
              </div>
              <span style={{ fontSize: 12.5, fontWeight: 600, color: "var(--accent)", display: "inline-flex", alignItems: "center", gap: 4, whiteSpace: "nowrap" }}>Xem chi tiết <I.open /></span>
            </button>
          )}

          {/* Cảnh báo phiếu chi chưa duyệt / chờ thanh toán → bấm xem popup */}
          {(needApprove.length > 0 || needPay.length > 0) && (
            <button type="button" onClick={() => setShowPending(true)}
              style={{ width: "100%", textAlign: "left", display: "flex", alignItems: "center", gap: 10, padding: "11px 14px", marginBottom: 14, borderRadius: 10, cursor: "pointer",
                background: "#eef4ff", border: "1px solid #d6e3fb" }}>
              <i className="bi bi-clipboard-check" style={{ fontSize: 18, color: "var(--accent)" }} />
              <div style={{ flex: 1, fontSize: 13, color: "var(--ink-2)", display: "flex", gap: 16, flexWrap: "wrap" }}>
                {needApprove.length > 0 && <span><b style={{ color: "var(--warn)" }}>{needApprove.length} phiếu chưa duyệt</b> · <b className="tnum">{fmtVND(needApproveAmt)}</b></span>}
                {needPay.length > 0 && <span><b style={{ color: "var(--accent)" }}>{needPay.length} phiếu chờ thanh toán</b> · <b className="tnum">{fmtVND(needPayAmt)}</b></span>}
              </div>
              <span style={{ fontSize: 12.5, fontWeight: 600, color: "var(--accent)", display: "inline-flex", alignItems: "center", gap: 4, whiteSpace: "nowrap" }}>Xem chi tiết <I.open /></span>
            </button>
          )}

          {/* Bộ lọc + tìm */}
          <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 12, flexWrap: "wrap" }}>
            <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 9, padding: 3, gap: 1 }}>
              {FILTERS.map(([k, label, n, col]) => {
                const on = vFilter === k;
                return (
                  <button key={k} type="button" onClick={() => setVFilter(k)}
                    style={{ display: "inline-flex", alignItems: "center", gap: 6, border: "none", cursor: "pointer", fontSize: 12.5, fontWeight: 600, padding: "6px 12px", borderRadius: 7,
                      background: on ? "#fff" : "transparent", color: on ? col : "var(--ink-3)", boxShadow: on ? "0 1px 2px rgba(16,19,23,.12)" : "none" }}>
                    {label}
                    <span className="tnum" style={{ fontSize: 11, fontWeight: 700, color: on ? "#fff" : "var(--ink-4)", background: on ? col : "var(--line-2)", padding: "0 6px", borderRadius: 999, minWidth: 16, textAlign: "center" }}>{n}</span>
                  </button>
                );
              })}
            </div>
            <div style={{ flex: 1 }} />
            <input value={vQuery} onChange={(e) => setVQuery(e.target.value)} placeholder="Tìm biển số…"
              style={{ width: 220, padding: "8px 12px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 9, outline: "none", background: "#fff" }} />
          </div>

          {vehicles.length === 0
            ? <div style={{ padding: "44px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5, background: "#fff", border: "1px solid var(--line)", borderRadius: 12 }}>Chưa có xe MBF. Thêm xe & chọn loại <b>Xe MBF</b> ở Cài đặt → Biển số xe.</div>
            : (
              <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, overflow: "hidden" }}>
                <div style={{ overflowX: "auto" }}>
                  <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13.5 }}>
                    <thead><tr style={{ background: "#fafbfc" }}>
                      {th("Biển số")}{th("Hạn đăng kiểm")}{th("Hạn bảo hiểm")}{th("Hồ sơ", "center")}{th("Khấu hao · Chi phí · Lượt", "center")}{th("", "right")}
                    </tr></thead>
                    <tbody>
                      {list.map((v) => {
                        const r = vehRank(v);
                        const stripe = r === 3 ? "var(--danger)" : r === 2 ? "var(--warn)" : "transparent";
                        return (
                          <tr key={v.id} onClick={() => open(v)} style={{ cursor: "pointer", borderBottom: "1px solid var(--line-2)", transition: "background .1s" }}
                            onMouseEnter={(e) => (e.currentTarget.style.background = "var(--accent-weak-2)")}
                            onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}>
                            <td style={{ padding: "11px 12px", borderLeft: `3px solid ${stripe}` }}>
                              <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                                <span style={{ fontSize: 15, fontWeight: 700 }} className="tnum">{v.plate}</span>
                                {v.axle && <span style={{ fontSize: 10.5, fontWeight: 600, color: "var(--accent)", background: "var(--accent-weak)", padding: "1px 7px", borderRadius: 999 }}>{v.axle} cầu</span>}
                              </div>
                            </td>
                            <td style={{ padding: "9px 12px" }}><DueCell iso={v.registrationDue} /></td>
                            <td style={{ padding: "9px 12px" }}><DueCell iso={v.insuranceDue} /></td>
                            <td style={{ padding: "9px 12px", textAlign: "center" }} className="tnum">
                              {v.docCount > 0
                                ? <span style={{ display: "inline-flex", alignItems: "center", gap: 5, fontSize: 12.5, color: "var(--ink-2)" }}><i className="bi bi-paperclip" />{v.docCount}</span>
                                : <span style={{ fontSize: 12, color: "var(--ink-4)" }}>—</span>}
                            </td>
                            <td style={{ padding: "9px 12px", textAlign: "center", fontSize: 12, color: "var(--ink-4)" }} className="tnum">{v.depCount} · {v.costCount} · {v.usageCount}</td>
                            <td style={{ padding: "9px 14px", textAlign: "right", color: "var(--ink-4)" }}><I.open /></td>
                          </tr>
                        );
                      })}
                      {list.length === 0 && <tr><td colSpan={6} style={{ padding: "32px", textAlign: "center", color: "var(--ink-4)", fontSize: 13 }}>Không có xe nào khớp bộ lọc.</td></tr>}
                    </tbody>
                  </table>
                </div>
              </div>
            )}
        </div>

        {showExp && (
          <Modal title="Chi phí định kỳ cần gia hạn" subtitle={`${expiringCosts.length} khoản hết hạn / sắp hết hạn — bấm 1 dòng để mở xe`} width={680} icon={<I.fx />} onClose={() => setShowExp(false)}
            footer={<div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", width: "100%" }}>
              <span style={{ fontSize: 13 }}>Tổng đã hết hạn: <b className="tnum" style={{ color: "var(--danger)" }}>{fmtVND(costExpiredTotal)}</b></span>
              <Btn onClick={() => setShowExp(false)}>Đóng</Btn>
            </div>}>
            <div style={{ display: "flex", flexDirection: "column", gap: 8, padding: "8px 0" }}>
              {expiringCosts.map((c, i) => {
                const exp = c.status === "expired";
                return (
                  <div key={i} onClick={() => { const v = vehicles.find((x) => x.id === c.vehicleId); setShowExp(false); if (v) open(v); }}
                    style={{ display: "flex", alignItems: "center", gap: 12, padding: "10px 12px", border: "1px solid var(--line)", borderRadius: 10, cursor: "pointer", borderLeft: `3px solid ${exp ? "var(--danger)" : "var(--warn)"}` }}
                    onMouseEnter={(e) => (e.currentTarget.style.background = "var(--accent-weak-2)")} onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ fontSize: 13.5, fontWeight: 600 }}>{c.name} <span className="tnum" style={{ color: "var(--ink-3)", fontWeight: 500 }}>· {c.plate}</span></div>
                      <div className="tnum" style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 2 }}>Hạn: {fmtDate(c.dueDate)} · {exp ? `quá ${Math.abs(c.days)} ngày` : `còn ${c.days} ngày`}</div>
                    </div>
                    <span className="tnum" style={{ fontWeight: 700, fontSize: 13.5 }}>{fmtVND(c.amount)}</span>
                    <span style={{ fontSize: 11, fontWeight: 700, color: exp ? "var(--danger)" : "var(--warn)", background: exp ? "#fce8e8" : "#fcf3e2", padding: "2px 9px", borderRadius: 999, whiteSpace: "nowrap" }}>{exp ? "Hết hạn" : "Sắp hết"}</span>
                  </div>
                );
              })}
            </div>
          </Modal>
        )}

        {showPending && (
          <Modal title="Phiếu chi cần xử lý" subtitle={`${needApprove.length} chưa duyệt · ${needPay.length} chờ thanh toán — bấm 1 dòng để mở xe`} width={700} icon={<I.fx />} onClose={() => setShowPending(false)}
            footer={<div style={{ display: "flex", justifyContent: "flex-end", width: "100%" }}><Btn onClick={() => setShowPending(false)}>Đóng</Btn></div>}>
            <div style={{ display: "flex", flexDirection: "column", gap: 8, padding: "8px 0" }}>
              {pendingCosts.map((c, i) => {
                const st = !c.approved ? { t: "Chưa duyệt", col: "var(--warn)", bg: "#fcf3e2" } : { t: "Chờ thanh toán", col: "var(--accent)", bg: "#eef4ff" };
                return (
                  <div key={i} onClick={() => { const v = vehicles.find((x) => x.id === c.vehicleId); setShowPending(false); if (v) open(v, "cost"); }}
                    style={{ display: "flex", alignItems: "center", gap: 12, padding: "10px 12px", border: "1px solid var(--line)", borderRadius: 10, cursor: "pointer", borderLeft: `3px solid ${st.col}` }}
                    onMouseEnter={(e) => (e.currentTarget.style.background = "var(--accent-weak-2)")} onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ fontSize: 13.5, fontWeight: 600 }}>{c.name} <span className="tnum" style={{ color: "var(--ink-3)", fontWeight: 500 }}>· {c.plate}</span></div>
                      <div className="tnum" style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 2 }}>{c.invoiceNo ? "# " + c.invoiceNo + " · " : ""}{fmtDate(c.spendDate) || "chưa có ngày"}</div>
                    </div>
                    <span className="tnum" style={{ fontWeight: 700, fontSize: 13.5 }}>{fmtVND(c.amount)}</span>
                    <span style={{ fontSize: 11, fontWeight: 700, color: st.col, background: st.bg, padding: "2px 9px", borderRadius: 999, whiteSpace: "nowrap" }}>{st.t}</span>
                  </div>
                );
              })}
            </div>
          </Modal>
        )}
      </div>
    );
  }

  // ---------- CHI TIẾT XE ----------
  const TABS = [["info", "Thông tin xe"], ["allowance", "Định mức"], ["deprec", "Khấu hao"], ["usage", "Thời gian sử dụng"], ["cost", "Chi phí"]];
  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <div style={{ display: "flex", alignItems: "center", gap: 14, padding: "14px 22px", background: "#fff", borderBottom: "1px solid var(--line)" }}>
        <button type="button" onClick={back} title="Về danh sách xe"
          style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 12px", fontSize: 13, fontWeight: 600, color: "var(--ink-2)", border: "1px solid var(--line)", borderRadius: 9, background: "#fff", cursor: "pointer" }}>
          <span style={{ transform: "rotate(180deg)", display: "inline-flex" }}><I.arrow /></span> Danh sách xe
        </button>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontSize: 16, fontWeight: 700 }} className="tnum">{detail ? detail.plate : "…"}{detail && detail.axle ? " · " + detail.axle + " cầu" : ""}</div>
          <div style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Xe MBF nội bộ</div>
        </div>
        {costSaving && <span style={{ fontSize: 12, color: "var(--ink-4)", display: "inline-flex", alignItems: "center", gap: 5 }}><span style={{ width: 13, height: 13, border: "2px solid var(--line)", borderTopColor: "var(--accent)", borderRadius: "50%", display: "inline-block", animation: "trk-spin .7s linear infinite" }} />Đang lưu phiếu chi…</span>}
        {dirty && <span style={{ fontSize: 12, color: "var(--warn)", fontWeight: 700, display: "inline-flex", alignItems: "center", gap: 5 }} title="Thay đổi chưa được lưu — bấm Lưu trước khi rời trang">
          <i className="bi bi-exclamation-circle-fill" /> Chưa lưu — bấm Lưu</span>}
        {canEdit && dirty && <Btn variant="primary" onClick={save} disabled={saving}>{saving ? "Đang lưu…" : "Lưu"}</Btn>}
      </div>
      <div style={{ display: "flex", gap: 4, padding: "10px 22px 0", background: "#fff", borderBottom: "1px solid var(--line)" }}>
        {TABS.map(([k, t]) => {
          const on = tab === k;
          return <button key={k} type="button" onClick={() => goTab(k)}
            style={{ border: "none", borderBottom: on ? "2px solid var(--accent)" : "2px solid transparent", background: "transparent", padding: "8px 12px 11px", fontSize: 13.5, fontWeight: 600, color: on ? "var(--accent)" : "var(--ink-3)", cursor: "pointer" }}>{t}</button>;
        })}
      </div>
      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: "22px" }}>
        <div style={{ maxWidth: 980, margin: "0 auto" }}>
          {(loading || !detail || (SECTION_OF[tab] && detail[SECTION_OF[tab]] === undefined))
            ? <div style={{ display: "flex", alignItems: "center", gap: 9, padding: "30px 4px", color: "var(--ink-4)", fontSize: 13.5 }}><span style={{ width: 15, height: 15, border: "2px solid var(--line)", borderTopColor: "var(--accent)", borderRadius: "50%", display: "inline-block", animation: "trk-spin .7s linear infinite" }} /> Đang tải dữ liệu…</div>
            : tab === "info" ? <InfoTab info={detail.info} onChange={(info) => upd({ info })} canEdit={canEdit}
                docsProps={{ docs: detail.docs || [], busy: docBusy, docType, setDocType, onPick: uploadDocs, onDelete: deleteDoc }} />
            : tab === "allowance" ? <AllowanceTab rows={detail.allowances || []} onChange={(rows) => upd({ allowances: rows })} costItems={costItems} addCostItem={addCostItem} />
            : tab === "deprec" ? <DeprecTab rows={detail.depreciations || []} onChange={(rows) => upd({ depreciations: rows })} />
            : tab === "usage" ? <UsageTab rows={detail.usages || []} onChange={(rows) => upd({ usages: rows })} drivers={detail.drivers || []} />
            : <CostTab rows={detail.costs || []} onChange={saveCosts} saving={costSaving} costTypes={detail.costTypes || []} onUploadPhotos={uploadCostPhotos} highlightId={hlCost} onCancel={cancelCost} />}
        </div>
      </div>
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<FleetApp />);
