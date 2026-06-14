import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState } = React;
import { I, Txt, DateField, Btn, Modal, fmtDate, useIsMobile } from "@trk/lib.jsx";

function PlanApp() {
  const isMobile = useIsMobile();
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const api = (m, u, b) => window.trkApi(m, u, b);
  const canEdit = !!T.canEdit;

  const [links, setLinks] = useState(B.links || []);
  const today = () => new Date().toISOString().slice(0, 10);
  const [from, setFrom] = useState(today());
  const [to, setTo] = useState(today());
  const [title, setTitle] = useState("");
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState("");

  const create = async () => {
    if (!from || !to) return setErr("Vui lòng chọn khoảng ngày.");
    setErr(""); setBusy(true);
    try {
      const r = await api("POST", ROUTES.create, { from, to, title });
      if (r && r.ok && r.link) { setLinks((l) => [r.link, ...l]); setTitle(""); window.trkToast && window.trkToast("Đã tạo link kế hoạch"); }
      else setErr((r && r.message) || "Tạo thất bại");
    } catch (e) { setErr("Lỗi kết nối."); }
    setBusy(false);
  };
  const toggle = async (lk) => {
    try { const r = await api("PUT", ROUTES.base + lk.id + "/toggle", { active: !lk.active }); if (r && r.ok) setLinks((ls) => ls.map((x) => x.id === lk.id ? { ...x, active: !x.active } : x)); } catch (e) {}
  };
  const del = async (lk) => {
    const ok = await window.confirmAction({ title: "Xóa link kế hoạch?", text: "Link này sẽ ngừng hoạt động vĩnh viễn. Dữ liệu lô hàng <b>không bị xóa</b>.", confirmText: '<i class="bi bi-trash me-1"></i> Xóa link', danger: true });
    if (!ok) return;
    try { const r = await api("DELETE", ROUTES.base + lk.id); if (r && r.ok) setLinks((ls) => ls.filter((x) => x.id !== lk.id)); } catch (e) {}
  };
  const copy = (url) => { try { navigator.clipboard && navigator.clipboard.writeText(url); window.trkToast && window.trkToast("Đã sao chép link"); } catch (e) {} };

  // Sửa link (đổi tên + khoảng ngày)
  const [edit, setEdit] = useState(null);   // {id, title, from, to}
  const [eBusy, setEBusy] = useState(false);
  const [eErr, setEErr] = useState("");
  const openEdit = (lk) => { setEErr(""); setEdit({ id: lk.id, title: lk.title || "", from: lk.from, to: lk.to }); };
  const saveEdit = async () => {
    if (!edit.from || !edit.to) return setEErr("Vui lòng chọn khoảng ngày.");
    setEErr(""); setEBusy(true);
    try {
      const r = await api("PUT", ROUTES.base + edit.id, { from: edit.from, to: edit.to, title: edit.title });
      if (r && r.ok && r.link) { setLinks((ls) => ls.map((x) => x.id === edit.id ? r.link : x)); setEdit(null); window.trkToast && window.trkToast("Đã cập nhật link"); }
      else setEErr((r && r.message) || "Lưu thất bại");
    } catch (e) { setEErr("Lỗi kết nối."); }
    setEBusy(false);
  };

  const card = { border: "1px solid var(--line)", borderRadius: 12, padding: "14px 16px", background: "#fff" };
  const lbl = (t) => <div style={{ fontSize: 11.5, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>{t}</div>;

  return (
    <div style={{ height: "100%", overflow: "auto", background: "var(--bg)" }}>
      <div style={{ maxWidth: 1000, margin: "0 auto", padding: isMobile ? "16px 14px" : "22px" }}>
        <div style={{ marginBottom: 14 }}>
          <h1 style={{ margin: 0, fontSize: 22, fontWeight: 700, letterSpacing: "-0.02em" }}>Link kế hoạch cho lái xe</h1>
          <div style={{ fontSize: 13, color: "var(--ink-3)", marginTop: 3 }}>Chọn khoảng ngày (theo <b>Giờ đến dự kiến</b>) → tạo link công khai gửi lái xe. Lái xe mở link trên điện thoại để cập nhật <b>giờ xe đến/ra</b>, ghi chú, ảnh — không cần đăng nhập.</div>
        </div>

        {/* Tạo link */}
        {canEdit && (
          <div style={{ ...card, marginBottom: 16 }}>
            <div style={{ fontSize: 13.5, fontWeight: 700, marginBottom: 12 }}>Tạo link mới</div>
            {err && <div style={{ marginBottom: 10, fontSize: 12.5, color: "#b42318", background: "#fdecec", border: "1px solid #f3c9c9", borderRadius: 9, padding: "8px 12px" }}><i className="bi bi-exclamation-triangle-fill" /> {err}</div>}
            <div style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr 1fr" : "180px 180px 1fr auto", gap: 12, alignItems: "end" }}>
              <div>{lbl("Từ ngày")}<DateField value={from} onChange={setFrom} /></div>
              <div>{lbl("Đến ngày")}<DateField value={to} onChange={setTo} /></div>
              <div style={{ gridColumn: isMobile ? "1 / -1" : "auto" }}>{lbl("Tiêu đề (tùy chọn)")}<Txt value={title} onChange={setTitle} placeholder="VD: Kế hoạch tuần này" /></div>
              <Btn variant="primary" onClick={create} disabled={busy}>{busy ? "Đang tạo…" : "Tạo link"}</Btn>
            </div>
          </div>
        )}

        {/* Danh sách link */}
        {links.length === 0
          ? <div style={{ padding: "40px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5, background: "#fff", border: "1px solid var(--line)", borderRadius: 12 }}>Chưa có link kế hoạch nào.</div>
          : (
            <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
              {links.map((lk) => (
                <div key={lk.id} style={{ ...card, opacity: lk.active ? 1 : 0.62 }}>
                  <div style={{ display: "flex", alignItems: "flex-start", gap: 12, flexWrap: "wrap" }}>
                    <div style={{ flex: 1, minWidth: 200 }}>
                      <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
                        <span style={{ fontSize: 15, fontWeight: 700 }}>{lk.title || "Kế hoạch"}</span>
                        <span style={{ fontSize: 11, fontWeight: 700, color: lk.active ? "var(--good)" : "#94a3b8", background: lk.active ? "var(--good-weak)" : "#eef1f6", padding: "2px 9px", borderRadius: 999 }}>{lk.active ? "Đang hoạt động" : "Đã tắt"}</span>
                        <span className="tnum" style={{ fontSize: 11, fontWeight: 700, color: "var(--accent)", background: "var(--accent-weak)", padding: "2px 9px", borderRadius: 999 }}>{lk.count} lô</span>
                      </div>
                      <div className="tnum" style={{ fontSize: 12.5, color: "var(--ink-3)", marginTop: 4 }}>{fmtDate(lk.from)} → {fmtDate(lk.to)}</div>
                      <a href={lk.url} target="_blank" rel="noreferrer" style={{ display: "inline-block", fontSize: 12, color: "var(--accent)", marginTop: 6, wordBreak: "break-all", fontWeight: 600, textDecoration: "none" }} className="tnum">{lk.url}</a>
                    </div>
                    <div style={{ display: "flex", gap: 8, flexShrink: 0, flexWrap: "wrap" }}>
                      <button type="button" onClick={() => copy(lk.url)} style={{ fontSize: 12.5, fontWeight: 600, padding: "7px 12px", border: "1px solid var(--accent)", borderRadius: 8, background: "#fff", color: "var(--accent)", cursor: "pointer" }}><i className="bi bi-clipboard" /> Sao chép</button>
                      <a href={lk.url} target="_blank" rel="noreferrer" style={{ fontSize: 12.5, fontWeight: 600, padding: "7px 12px", border: "none", borderRadius: 8, background: "var(--accent)", color: "#fff", textDecoration: "none", display: "inline-flex", alignItems: "center", gap: 5 }}><i className="bi bi-box-arrow-up-right" /> Mở</a>
                      {canEdit && <button type="button" onClick={() => openEdit(lk)} title="Sửa tên / khoảng ngày" style={{ fontSize: 12.5, fontWeight: 600, padding: "7px 12px", border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-2)", cursor: "pointer" }}><i className="bi bi-pencil" /> Sửa</button>}
                      {canEdit && <button type="button" onClick={() => toggle(lk)} title={lk.active ? "Tắt link" : "Bật link"} style={{ fontSize: 12.5, fontWeight: 600, padding: "7px 12px", border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-2)", cursor: "pointer" }}><i className={"bi " + (lk.active ? "bi-pause-circle" : "bi-play-circle")} /> {lk.active ? "Tắt" : "Bật"}</button>}
                      {canEdit && <button type="button" onClick={() => del(lk)} title="Xóa link" style={{ fontSize: 12.5, fontWeight: 600, padding: "7px 10px", border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--danger)", cursor: "pointer" }}><I.trash /></button>}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
      </div>

      {edit && (
        <Modal title="Sửa link kế hoạch" subtitle="Đổi tên & khoảng ngày (theo Giờ đến dự kiến)" width={460} icon={<I.edit />} onClose={() => setEdit(null)}
          footer={<div style={{ display: "flex", justifyContent: "flex-end", gap: 10, width: "100%" }}><Btn onClick={() => setEdit(null)}>Hủy</Btn><Btn variant="primary" onClick={saveEdit} disabled={eBusy}>{eBusy ? "Đang lưu…" : "Lưu"}</Btn></div>}>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, padding: "4px 0" }}>
            {eErr && <div style={{ gridColumn: "1 / -1", fontSize: 12.5, color: "#b42318", background: "#fdecec", border: "1px solid #f3c9c9", borderRadius: 9, padding: "8px 12px" }}><i className="bi bi-exclamation-triangle-fill" /> {eErr}</div>}
            <div style={{ gridColumn: "1 / -1" }}>{lbl("Tiêu đề")}<Txt value={edit.title} onChange={(x) => setEdit((e) => ({ ...e, title: x }))} placeholder="VD: Kế hoạch tuần này" /></div>
            <div>{lbl("Từ ngày")}<DateField value={edit.from} onChange={(x) => setEdit((e) => ({ ...e, from: x }))} /></div>
            <div>{lbl("Đến ngày")}<DateField value={edit.to} onChange={(x) => setEdit((e) => ({ ...e, to: x }))} /></div>
          </div>
        </Modal>
      )}
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<PlanApp />);
