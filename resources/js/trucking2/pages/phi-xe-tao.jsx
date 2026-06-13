import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState } = React;
import { I, Btn, Txt, DateField, fmtVND } from "@trk/lib.jsx";
import { TripEditor, rowTotal } from "@trk/components/trip-cost.jsx";

const lbl = (t) => <div style={{ fontSize: 11, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>{t}</div>;

function CreateTripApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {};
  const api = (method, url, body) => fetch(url, { method, headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": T.csrf }, body: body ? JSON.stringify(body) : undefined }).then((r) => r.json());
  const back = () => { window.location.href = ROUTES.list; };

  const [no, setNo] = useState("");
  const [name, setName] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [rows, setRows] = useState(null);
  const [routeFees, setRouteFees] = useState([]);
  const [drivers, setDrivers] = useState([]);
  const [costItems, setCostItems] = useState([]);
  const [salaryItems, setSalaryItems] = useState([]);
  const [loading, setLoading] = useState(false);

  const compute = () => {
    setLoading(true);
    const p = new URLSearchParams(); if (from) p.set("from", from); if (to) p.set("to", to);
    api("GET", ROUTES.compute + "?" + p.toString()).then((r) => {
      if (r && r.ok) {
        setRows((r.rows || []).map((x) => ({ ...x, cur: { ...x.cur, extras: (x.cur.extras || []).map((e) => ({ ...e })), salaryExtras: (x.cur.salaryExtras || []).map((e) => ({ ...e })) } })));
        setRouteFees(r.routeFees || []); setDrivers(r.drivers || []);
        setCostItems(r.costItems || []); setSalaryItems(r.salaryItems || []);
      }
      setLoading(false);
    }).catch(() => { setLoading(false); window.trkToast && window.trkToast("Lỗi tải dữ liệu", "error"); });
  };

  const grand = (rows || []).reduce((a, x) => a + rowTotal(x.cur), 0);
  const dup = (rows || []).filter((x) => (x.usedIn || []).length).length;

  const save = async () => {
    if (!rows || !rows.length) { window.trkToast && window.trkToast("Chưa có lô nào để lưu", "error"); return; }
    const ok = await window.confirmAction({
      title: "Lưu kỳ phí xe?",
      text: `Kỳ <b>${(no || "(tự sinh)")}</b> · <b>${rows.length}</b> lô · tổng <b>${grand.toLocaleString("vi-VN")} ₫</b> sẽ được tạo.`
        + (dup ? `<br><span style="color:var(--danger)">⚠ ${dup} lô đã có ở kỳ khác — kiểm tra tránh cộng trùng.</span>` : ""),
      confirmText: '<i class="bi bi-save me-1"></i> Lưu kỳ',
    });
    if (!ok) return false;
    const payload = { no, name, from, to, rows };
    const res = await api("POST", ROUTES.store, { batch: payload });
    if (res && res.ok) { window.location.href = ROUTES.view + res.batch.id; return; }
    window.trkToast && window.trkToast("Lưu thất bại", "error"); return false;
  };

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 12, height: 58 }}>
          <button type="button" onClick={back} title="Quay lại danh sách"
            style={{ width: 34, height: 34, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: "var(--ink-2)", cursor: "pointer" }}>
            <span style={{ transform: "rotate(180deg)", display: "grid" }}><I.arrow /></span>
          </button>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center" }}><I.fx /></div>
          <div style={{ flex: 1 }}>
            <div style={{ fontSize: 15.5, fontWeight: 700, lineHeight: 1.1 }}>Tạo kỳ phí xe</div>
            <div style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Chọn khoảng ngày xe ra → Tính → kiểm tra/sửa → Lưu</div>
          </div>
          {rows && rows.length > 0 && <div style={{ textAlign: "right", marginRight: 8 }}>
            <div style={{ fontSize: 11.5, color: "var(--ink-4)" }}>Tổng {rows.length} lô</div>
            <div className="tnum" style={{ fontSize: 17, fontWeight: 700, color: "var(--accent)" }}>{fmtVND(grand)}</div>
          </div>}
          {T.canEdit && rows && <Btn variant="primary" onClick={save}>Lưu kỳ</Btn>}
        </div>
      </header>

      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: "18px 22px 40px" }}>
        <div style={{ maxWidth: 1100, margin: "0 auto", display: "flex", flexDirection: "column", gap: 14 }}>
          {/* thông tin kỳ + chọn khoảng ngày */}
          <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "14px 16px", display: "flex", gap: 12, flexWrap: "wrap", alignItems: "flex-end" }}>
            <div style={{ width: 150 }}>{lbl("Số kỳ")}<Txt value={no} onChange={setNo} placeholder="Tự sinh PX-…" /></div>
            <div style={{ flex: 1, minWidth: 180 }}>{lbl("Tên / ghi chú kỳ")}<Txt value={name} onChange={setName} placeholder="VD: Phí xe tháng 6/2026" /></div>
            <div>{lbl("Ngày xe ra — từ")}<DateField value={from} onChange={setFrom} /></div>
            <div>{lbl("đến")}<DateField value={to} onChange={setTo} /></div>
            <Btn variant="primary" onClick={compute}>{loading ? "Đang tính…" : "Tính"}</Btn>
          </div>

          {rows === null
            ? <div style={{ padding: "30px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5 }}>Chọn khoảng <b>Ngày xe ra</b> rồi bấm <b>Tính</b> để gom các lô trong kỳ.</div>
            : <TripEditor rows={rows} onRows={setRows} routeFees={routeFees} drivers={drivers} costItems={costItems} salaryItems={salaryItems} />}
        </div>
      </div>
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<CreateTripApp />);
