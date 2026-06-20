import React from "react";
const { useState, useEffect } = React;
import { I, Money, Num, Txt, DateField, Btn, fmtVND, fmtNum, fmtDate, toNum } from "@trk/lib.jsx";
import { lbl, card } from "./parts.jsx";

/**
 * Tab "Dầu" — Theo dõi lượng dầu xe:
 *  - Mức dầu ước tính hiện tại.
 *  - Lịch sử phiếu đổ dầu (CRUD).
 *  - Theo dõi tiêu thụ: mỗi khoảng đổ → tiêu thụ (lý thuyết từ Lộ trình) → còn lại.
 */
function FuelTab({ vehicleId, hashid, routes }) {
  const api = (m, u, b) => window.trkApi(m, u, b);
  const upload = (m, u, fd) => window.trkUpload(m, u, fd);
  const base = routes.fleet + hashid;

  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [editing, setEditing] = useState(null);   // null | {id?,date,liters,...} (phiếu đang sửa/tạo)

  const load = () => {
    setLoading(true);
    api("GET", base + "/fuel").then((r) => { if (r && r.ok) setData(r); setLoading(false); })
      .catch(() => { setLoading(false); });
  };
  useEffect(() => { load(); }, [vehicleId]);

  const startAdd = () => setEditing({ id: null, date: new Date().toISOString().slice(0, 10), liters: "", unitPrice: "", totalCost: "", odometerKm: "", station: "", note: "" });
  const startEdit = (rf) => setEditing({ id: rf.id, date: rf.date, liters: String(rf.liters), unitPrice: rf.unitPrice != null ? String(rf.unitPrice) : "", totalCost: rf.totalCost != null ? String(rf.totalCost) : "", odometerKm: rf.odometerKm != null ? String(rf.odometerKm) : "", station: rf.station, note: rf.note });
  const cancelEdit = () => setEditing(null);

  const saveRefill = async () => {
    if (saving || !editing) return;
    setSaving(true);
    try {
      const r = await api("POST", base + "/fuel", editing);
      if (r && r.ok) { setData(r); setEditing(null); window.trkToast && window.trkToast("Đã lưu phiếu đổ dầu"); }
      else window.trkToast && window.trkToast("Lưu thất bại", "error");
    } catch (e) { window.trkToast && window.trkToast("Lỗi kết nối", "error"); }
    setSaving(false);
  };

  const deleteRefill = async (rf) => {
    const ok = await window.confirmAction({ title: "Xóa phiếu đổ dầu?", text: `${fmtDate(rf.date)} · ${fmtNum(rf.liters)} lít`, confirmText: '<i class="bi bi-trash me-1"></i> Xóa', danger: true });
    if (!ok) return;
    const r = await api("DELETE", base + "/fuel/" + rf.id);
    if (r && r.ok) { setData(r); window.trkToast && window.trkToast("Đã xóa phiếu đổ dầu"); }
  };

  if (loading) return <div style={{ padding: 20, textAlign: "center", color: "var(--ink-4)" }}>Đang tải…</div>;
  if (!data) return <div style={{ padding: 20, textAlign: "center", color: "var(--ink-4)" }}>Không tải được dữ liệu.</div>;

  const refills = data.refills || [];
  const periods = data.periods || [];
  const cur = data.currentRemaining;

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
      {/* Mức dầu hiện tại */}
      <div style={{ ...card, display: "flex", alignItems: "center", gap: 16 }}>
        <div style={{ width: 48, height: 48, borderRadius: 12, background: cur != null && cur > 50 ? "var(--good-weak)" : cur != null && cur > 0 ? "#fff7e9" : "#fce8e8", display: "grid", placeItems: "center" }}>
          <i className="bi bi-fuel-pump-fill" style={{ fontSize: 22, color: cur != null && cur > 50 ? "var(--good)" : cur != null && cur > 0 ? "#c9820f" : "var(--danger)" }} />
        </div>
        <div>
          <div style={{ fontSize: 11, color: "var(--ink-4)", fontWeight: 600, textTransform: "uppercase", letterSpacing: ".03em" }}>Ước tính dầu còn lại</div>
          <div className="tnum" style={{ fontSize: 28, fontWeight: 800, color: cur != null && cur > 50 ? "var(--good)" : cur != null && cur > 0 ? "#c9820f" : "var(--danger)" }}>
            {cur != null ? fmtNum(cur) + " lít" : "—"}
          </div>
          {periods.length > 0 && (() => {
            const last = periods[periods.length - 1];
            return <div style={{ fontSize: 12, color: "var(--ink-4)" }}>Lần đổ gần nhất: {fmtDate(last.from)} · {fmtNum(last.refilled)} lít → tiêu thụ {fmtNum(last.consumed)} lít ({last.trips} chuyến)</div>;
          })()}
        </div>
      </div>

      {/* Lịch sử đổ dầu */}
      <div style={card}>
        <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 10 }}>
          <i className="bi bi-fuel-pump" style={{ color: "var(--accent)", fontSize: 15 }} />
          <span style={{ fontWeight: 700, fontSize: 13.5, flex: 1 }}>Phiếu đổ dầu</span>
          <Btn variant="primary" onClick={startAdd}><I.plus /> Thêm phiếu đổ</Btn>
        </div>
        {/* Form tạo/sửa */}
        {editing && (
          <div style={{ border: "1px solid var(--accent)", borderRadius: 10, padding: "12px 14px", marginBottom: 10, background: "var(--accent-weak-2)" }}>
            <div style={{ display: "flex", gap: 10, flexWrap: "wrap", alignItems: "flex-end", marginBottom: 8 }}>
              <div style={{ width: 140 }}>{lbl("Ngày đổ *")}<DateField value={editing.date} onChange={(v) => setEditing({ ...editing, date: v })} /></div>
              <div style={{ width: 120 }}>{lbl("Số lít *")}<Num value={editing.liters} onChange={(v) => setEditing({ ...editing, liters: v })} suffix="lít" /></div>
              <div style={{ width: 130 }}>{lbl("Đơn giá")}<Money value={editing.unitPrice} onChange={(v) => setEditing({ ...editing, unitPrice: v })} dim /></div>
              <div style={{ width: 130 }}>{lbl("Thành tiền")}<Money value={editing.totalCost} onChange={(v) => setEditing({ ...editing, totalCost: v })} dim /></div>
              <div style={{ width: 100 }}>{lbl("Km đồng hồ")}<Num value={editing.odometerKm} onChange={(v) => setEditing({ ...editing, odometerKm: v })} suffix="km" /></div>
              <div style={{ flex: 1, minWidth: 140 }}>{lbl("Trạm / nơi đổ")}<Txt value={editing.station} onChange={(v) => setEditing({ ...editing, station: v })} placeholder="VD: Petrolimex 48 NVC" /></div>
            </div>
            <div style={{ display: "flex", gap: 10, alignItems: "flex-end" }}>
              <div style={{ flex: 1 }}>{lbl("Ghi chú")}<Txt value={editing.note} onChange={(v) => setEditing({ ...editing, note: v })} placeholder="Ghi chú…" /></div>
              <Btn variant="primary" onClick={saveRefill} disabled={saving || !toNum(editing.liters)}>{saving ? "Đang lưu…" : (editing.id ? "Cập nhật" : "Thêm phiếu")}</Btn>
              <Btn onClick={cancelEdit}>Hủy</Btn>
            </div>
          </div>
        )}
        {refills.length === 0 ? (
          <div style={{ padding: "14px 0", fontSize: 13, color: "var(--ink-4)" }}>Chưa có phiếu đổ dầu nào — bấm <b>Thêm phiếu đổ</b> để ghi nhận.</div>
        ) : (
          <div style={{ overflowX: "auto" }}>
            <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13 }}>
              <thead><tr style={{ color: "var(--ink-4)", fontSize: 11, textTransform: "uppercase" }}>
                <th style={th}>Ngày đổ</th><th style={thR}>Số lít</th><th style={thR}>Đơn giá</th><th style={thR}>Thành tiền</th>
                <th style={th}>Trạm</th><th style={thR}>Km</th><th style={th}>Ghi chú</th><th style={{ ...th, width: 60 }} />
              </tr></thead>
              <tbody>
                {refills.map((rf) => (
                  <tr key={rf.id}>
                    <td style={td} className="tnum">{fmtDate(rf.date)}</td>
                    <td style={tdR} className="tnum"><b>{fmtNum(rf.liters)}</b></td>
                    <td style={tdR} className="tnum">{rf.unitPrice ? fmtVND(rf.unitPrice) : "—"}</td>
                    <td style={tdR} className="tnum">{rf.totalCost ? fmtVND(rf.totalCost) : "—"}</td>
                    <td style={td}>{rf.station || "—"}</td>
                    <td style={tdR} className="tnum">{rf.odometerKm ? fmtNum(rf.odometerKm) : "—"}</td>
                    <td style={td}>{rf.note || ""}</td>
                    <td style={{ ...td, whiteSpace: "nowrap" }}>
                      <button type="button" onClick={() => startEdit(rf)} title="Sửa" style={iconBtn}><i className="bi bi-pencil" /></button>
                      <button type="button" onClick={() => deleteRefill(rf)} title="Xóa" style={{ ...iconBtn, color: "var(--danger)" }}><I.trash /></button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Theo dõi tiêu thụ: mỗi khoảng đổ */}
      {periods.length > 0 && (
        <div style={card}>
          <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 10 }}>
            <i className="bi bi-graph-down" style={{ color: "var(--accent)", fontSize: 15 }} />
            <span style={{ fontWeight: 700, fontSize: 13.5 }}>Theo dõi tiêu thụ theo lần đổ</span>
          </div>
          <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
            {periods.map((p, i) => {
              const pct = p.refilled > 0 ? Math.max(0, Math.min(100, p.remaining / p.refilled * 100)) : 0;
              return (
                <div key={i} style={{ border: "1px solid var(--line)", borderRadius: 10, padding: "10px 12px", background: "#fff" }}>
                  <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 6, fontSize: 12.5 }}>
                    <i className="bi bi-fuel-pump-fill" style={{ color: "var(--accent)" }} />
                    <span className="tnum" style={{ fontWeight: 700 }}>{fmtDate(p.from)}</span>
                    <span style={{ color: "var(--ink-4)" }}>→</span>
                    <span className="tnum">{p.isLast ? "hôm nay" : fmtDate(p.to)}</span>
                    <span style={{ flex: 1 }} />
                    <span style={{ fontWeight: 700, color: p.remaining > 0 ? "var(--good)" : "var(--danger)" }} className="tnum">còn {fmtNum(p.remaining)} lít</span>
                  </div>
                  {/* Bar mức dầu */}
                  <div style={{ height: 18, background: "var(--line-2)", borderRadius: 6, overflow: "hidden", marginBottom: 5 }}>
                    <div style={{ width: pct + "%", height: "100%", background: pct > 30 ? "var(--good)" : pct > 10 ? "#f59e0b" : "var(--danger)", borderRadius: 6, transition: "width .3s" }} />
                  </div>
                  <div style={{ display: "flex", gap: 16, fontSize: 11.5, color: "var(--ink-4)" }}>
                    <span>Đổ: <b className="tnum">{fmtNum(p.refilled)} lít</b></span>
                    <span>Tiêu thụ: <b className="tnum">{fmtNum(p.consumed)} lít</b> ({p.trips} chuyến)</span>
                    <span>Còn: <b className="tnum" style={{ color: p.remaining > 0 ? "var(--good)" : "var(--danger)" }}>{fmtNum(p.remaining)} lít</b></span>
                  </div>
                </div>
              );
            })}
          </div>
          <div style={{ marginTop: 8, fontSize: 11, color: "var(--ink-4)" }}>
            <i className="bi bi-info-circle" /> Tiêu thụ = lý thuyết từ Phí tuyến × số chuyến ở Lộ trình. Dầu còn lại là ước tính tương đối.
          </div>
        </div>
      )}
    </div>
  );
}

const th = { textAlign: "left", padding: "6px 8px", fontWeight: 700, borderBottom: "1px solid var(--line)" };
const thR = { ...th, textAlign: "right" };
const td = { padding: "7px 8px", borderBottom: "1px solid var(--line-2)", verticalAlign: "middle" };
const tdR = { ...td, textAlign: "right" };
const iconBtn = { border: "none", background: "transparent", cursor: "pointer", padding: 4, color: "var(--ink-4)", fontSize: 14 };

export { FuelTab };
