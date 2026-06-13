import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useEffect } = React;
import { I, Btn, Txt, DateField, fmtVND } from "@trk/lib.jsx";
import { TripEditor, rowTotal } from "@trk/components/trip-cost.jsx";

const lbl = (t) => <div style={{ fontSize: 11, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>{t}</div>;

function ViewTripApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const api = (method, url, body) => fetch(url, { method, headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": T.csrf }, body: body ? JSON.stringify(body) : undefined }).then((r) => r.json());
  const back = () => { window.location.href = ROUTES.list; };
  const batch = B.batch || {};

  const [no, setNo] = useState(batch.no || "");
  const [name, setName] = useState(batch.name || "");
  const [from, setFrom] = useState(batch.from || "");
  const [to, setTo] = useState(batch.to || "");
  const [rows, setRows] = useState((batch.rows || []).map((x) => ({ ...x, cur: { ...x.cur, extras: (x.cur.extras || []).map((e) => ({ ...e })), salaryExtras: (x.cur.salaryExtras || []).map((e) => ({ ...e })) } })));
  const [routeFees, setRouteFees] = useState([]);
  const [drivers, setDrivers] = useState([]);
  const [costItems, setCostItems] = useState([]);
  const [salaryItems, setSalaryItems] = useState([]);
  const [dirty, setDirty] = useState(false);

  // gộp ngữ cảnh mới vào rows; apply=true thì GHI ĐÈ phí (Tính lại), apply=false chỉ gắn gợi ý/chẩn đoán
  const applyContext = (r, apply) => {
    setRouteFees(r.routeFees || []); setDrivers(r.drivers || []);
    setCostItems(r.costItems || []); setSalaryItems(r.salaryItems || []);
    const sug = r.sug || {};
    setRows((rs) => rs.map((x) => {
      const s = x.shipmentId != null ? sug[x.shipmentId] : null;
      if (!s) return x;
      const merged = { ...x, sug: s, matched: s.matched ?? x.matched, axle: s.axle ?? x.axle, diag: s.diag ?? x.diag, salaryParts: s.salaryParts ?? x.salaryParts };
      if (apply) merged.cur = { ...s, extras: x.cur.extras || [], salaryExtras: x.cur.salaryExtras || [] };
      return merged;
    }));
  };

  // tải lazy 1 lần khi mở: cấu hình + gợi ý/chẩn đoán mới — KHÔNG ghi đè snapshot
  useEffect(() => {
    api("GET", ROUTES.context + batch.id + "/context").then((r) => { if (r && r.ok) applyContext(r, false); }).catch(() => {});
  }, []);

  const onRows = (rs) => { setRows(rs); setDirty(true); };
  const grand = rows.reduce((a, x) => a + rowTotal(x.cur), 0);

  const recalcAll = async () => {
    const ok = await window.confirmAction({
      title: "Tính lại theo cấu hình hiện tại?",
      text: "Sẽ truy vấn lại bảng giá tuyến / giá dầu / lịch lái xe <b>hiện tại</b> rồi cập nhật phí cho các lô còn trong hệ thống (giữ lại phí khác phát sinh). Lô đã xóa giữ nguyên số đã lưu. Nhớ bấm <b>Lưu</b> sau khi tính lại.",
      confirmText: '<i class="bi bi-arrow-repeat me-1"></i> Tính lại',
    });
    if (!ok) return;
    try {
      const r = await api("GET", ROUTES.context + batch.id + "/context");   // truy vấn realtime ngay lúc bấm
      if (!r || !r.ok) { window.trkToast && window.trkToast("Tính lại thất bại", "error"); return false; }
      applyContext(r, true);
      setDirty(true);
      const updated = Object.keys(r.sug || {}).length;
      const missing = (r.missing || []).length;
      window.trkToast && window.trkToast(`Đã tính lại ${updated} lô theo cấu hình hiện tại${missing ? ` · ${missing} lô đã xóa giữ nguyên` : ""}. Nhớ bấm Lưu.`);
    } catch (e) {
      window.trkToast && window.trkToast("Lỗi kết nối khi tính lại", "error"); return false;
    }
  };

  const save = async () => {
    const res = await api("PUT", ROUTES.update + batch.id, { batch: { no, name, from, to, rows } });
    if (res && res.ok) { setDirty(false); window.trkToast && window.trkToast("Đã lưu kỳ phí xe"); return; }
    window.trkToast && window.trkToast("Lưu thất bại", "error"); return false;
  };

  const del = async () => {
    const ok = await window.confirmAction({
      title: "Xóa kỳ phí xe?", text: `Kỳ <b>${no}</b> · <b>${rows.length}</b> lô sẽ bị xóa vĩnh viễn.`,
      confirmText: '<i class="bi bi-trash me-1"></i> Xóa kỳ', danger: true,
    });
    if (!ok) return;
    const res = await api("DELETE", ROUTES.destroy + batch.id);
    if (res && res.ok) { window.location.href = ROUTES.list; }
    else window.trkToast && window.trkToast("Xóa thất bại", "error");
  };

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 12, height: 58 }}>
          <button type="button" onClick={back} title="Quay lại danh sách"
            style={{ width: 34, height: 34, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: "var(--ink-2)", cursor: "pointer" }}>
            <span style={{ transform: "rotate(180deg)", display: "grid" }}><I.arrow /></span>
          </button>
          <div style={{ flex: 1 }}>
            <div style={{ fontSize: 15.5, fontWeight: 700, lineHeight: 1.1 }} className="tnum">Kỳ {no || ""}</div>
            <div style={{ fontSize: 12.5, color: "var(--ink-3)" }} className="tnum">{from || "?"} → {to || "?"} · {rows.length} lô</div>
          </div>
          <div style={{ textAlign: "right", marginRight: 8 }}>
            <div style={{ fontSize: 11.5, color: "var(--ink-4)" }}>Tổng phí</div>
            <div className="tnum" style={{ fontSize: 17, fontWeight: 700, color: "var(--accent)" }}>{fmtVND(grand)}</div>
          </div>
          {T.canEdit && <Btn onClick={recalcAll}><I.fx /> Tính lại</Btn>}
          {T.canEdit && <Btn variant="primary" onClick={save} disabled={!dirty}>Lưu</Btn>}
          {T.canDelete && <Btn onClick={del}><I.trash /></Btn>}
        </div>
      </header>

      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: "18px 22px 40px" }}>
        <div style={{ maxWidth: 1100, margin: "0 auto", display: "flex", flexDirection: "column", gap: 14 }}>
          <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "14px 16px", display: "flex", gap: 12, flexWrap: "wrap", alignItems: "flex-end" }}>
            <div style={{ width: 150 }}>{lbl("Số kỳ")}<Txt value={no} onChange={(v) => { setNo(v); setDirty(true); }} /></div>
            <div style={{ flex: 1, minWidth: 180 }}>{lbl("Tên / ghi chú kỳ")}<Txt value={name} onChange={(v) => { setName(v); setDirty(true); }} /></div>
            <div>{lbl("Ngày xe ra — từ")}<DateField value={from} onChange={(v) => { setFrom(v); setDirty(true); }} /></div>
            <div>{lbl("đến")}<DateField value={to} onChange={(v) => { setTo(v); setDirty(true); }} /></div>
          </div>
          <TripEditor rows={rows} onRows={onRows} routeFees={routeFees} drivers={drivers} costItems={costItems} salaryItems={salaryItems} />
        </div>
      </div>
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<ViewTripApp />);
