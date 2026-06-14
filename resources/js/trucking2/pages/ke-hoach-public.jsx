import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useMemo } = React;
const T = window.__TRK || {};
const ROUTES = T.routes || {};
const B = T.boot || {};

const pad = (n) => String(n).padStart(2, "0");
const nowLocal = () => { const d = new Date(); return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`; };
const fmtDT = (s) => { if (!s) return ""; const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/); return m ? `${m[3]}/${m[2]} ${m[4]}:${m[5]}` : s; };
const fmtD = (s) => { if (!s) return ""; const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? `${m[3]}/${m[2]}/${m[1]}` : s; };
// Trạng thái suy ra từ giờ: chưa đến < đã đến < đã ra
const statusOf = (s) => s.gioXeRa ? { t: "Đã ra", c: "#16a34a", bg: "#e8f6ee" } : s.gioXeDen ? { t: "Đã đến", c: "#2a6fdb", bg: "#eaf1fd" } : { t: "Chưa đến", c: "#e0a92e", bg: "#fdf3df" };

/* ============ Sheet sửa 1 lô (toàn màn hình, mobile) ============ */
function EditSheet({ ship, onClose, onSaved }) {
  const [gioXeDen, setDen] = useState(ship.gioXeDen || "");
  const [gioXeRa, setRa] = useState(ship.gioXeRa || "");
  const [note, setNote] = useState(ship.driverNote || "");
  const [photos, setPhotos] = useState(ship.photos || []);   // [{id,url,...}] cũ + {file,url} mới
  const [editDen, setEditDen] = useState(false);
  const [editRa, setEditRa] = useState(false);
  const [busy, setBusy] = useState(false);
  const [photoBusy, setPhotoBusy] = useState(false);
  const fileRef = React.useRef(null);

  const addPhotos = (e) => {
    const files = Array.from(e.target.files || []); e.target.value = "";
    const next = files.filter((f) => f.type.startsWith("image/")).map((f) => ({ file: f, url: URL.createObjectURL(f) }));
    setPhotos((p) => [...p, ...next].slice(0, 12));
  };
  const removePhoto = async (i) => {
    const p = photos[i];
    if (p.id) {   // ảnh đã lưu → gọi xóa server
      try { const r = await window.trkApi("DELETE", ROUTES.base + ship.hashid + "/photo/" + p.id); if (r && r.ok) setPhotos(r.photos || []); } catch (e) {}
    } else {
      if (p.url) { try { URL.revokeObjectURL(p.url); } catch (e) {} }
      setPhotos((arr) => arr.filter((_, j) => j !== i));
    }
  };

  const save = async () => {
    if (busy) return;
    setBusy(true);
    try {
      const fd = new FormData();
      fd.append("gioXeDen", gioXeDen || "");
      fd.append("gioXeRa", gioXeRa || "");
      fd.append("driverNote", note || "");
      photos.filter((p) => p.file).forEach((p) => fd.append("photos[]", p.file));
      const r = await window.trkUpload("POST", ROUTES.base + ship.hashid + "/update", fd);
      if (r && r.ok) { window.trkToast && window.trkToast("Đã cập nhật"); onSaved(r.ship); }
      else window.trkToast && window.trkToast((r && r.message) || "Lưu thất bại", "error");
    } catch (e) { window.trkToast && window.trkToast("Lỗi kết nối — thử lại", "error"); }
    setBusy(false);
  };

  const card = { background: "#fff", borderRadius: 16, padding: 16, marginBottom: 14, boxShadow: "0 1px 3px rgba(16,24,40,.05)" };
  const lbl = { fontSize: 13.5, fontWeight: 700, color: "#3a4759", marginBottom: 8, display: "block" };

  // 1 nút đóng dấu giờ lớn
  const stampBtn = (val, setVal, edit, setEdit, label, icon, color) => (
    <div style={card}>
      <label style={lbl}>{label}</label>
      {!val ? (
        <button type="button" onClick={() => setVal(nowLocal())}
          style={{ width: "100%", padding: "16px", fontSize: 17, fontWeight: 800, border: "none", borderRadius: 14, background: color, color: "#fff", cursor: "pointer", display: "inline-flex", alignItems: "center", justifyContent: "center", gap: 10 }}>
          <i className={"bi " + icon} style={{ fontSize: 22 }} /> {label}
        </button>
      ) : (
        <div>
          <div style={{ display: "flex", alignItems: "center", gap: 10, background: "#f3f9f4", border: "1px solid #cde9d6", borderRadius: 12, padding: "12px 14px" }}>
            <i className="bi bi-check-circle-fill" style={{ fontSize: 22, color: "#16a34a" }} />
            <div style={{ flex: 1 }}>
              <div className="tnum" style={{ fontSize: 18, fontWeight: 800 }}>{fmtDT(val)}</div>
              <div style={{ fontSize: 12, color: "#6b7585" }}>Đã ghi nhận</div>
            </div>
            <button type="button" onClick={() => setVal("")} title="Xóa" style={{ border: "none", background: "transparent", color: "#dc2626", fontSize: 13.5, fontWeight: 700, cursor: "pointer" }}>Xóa</button>
          </div>
          <button type="button" onClick={() => setEdit((x) => !x)} style={{ marginTop: 8, border: "none", background: "transparent", color: "#2a6fdb", fontSize: 13.5, fontWeight: 700, cursor: "pointer", padding: 0 }}>
            <i className="bi bi-pencil" /> Sửa giờ thủ công
          </button>
          {edit && <input type="datetime-local" value={val} onChange={(e) => setVal(e.target.value)} style={{ width: "100%", marginTop: 8, padding: "13px 14px", fontSize: 16, border: "1px solid #d9dee7", borderRadius: 12, colorScheme: "light" }} />}
        </div>
      )}
    </div>
  );

  return (
    <div style={{ position: "fixed", inset: 0, background: "#eef1f6", zIndex: 50, display: "flex", flexDirection: "column" }}>
      {/* Header sheet */}
      <div style={{ background: "#fff", borderBottom: "1px solid #e3e7ee", padding: "14px 16px", display: "flex", alignItems: "center", gap: 12, position: "sticky", top: 0 }}>
        <button type="button" onClick={onClose} style={{ border: "none", background: "#f1f3f6", width: 40, height: 40, borderRadius: 12, fontSize: 20, color: "#3a4759", cursor: "pointer", flexShrink: 0 }}><i className="bi bi-chevron-left" /></button>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontSize: 16.5, fontWeight: 800, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{ship.customer || ship.booking || "Lô hàng"}</div>
          <div className="tnum" style={{ fontSize: 12.5, color: "#6b7585" }}>{[ship.contNo, ship.contType].filter(Boolean).join(" · ")}</div>
        </div>
      </div>

      {/* Nội dung cuộn */}
      <div style={{ flex: 1, overflowY: "auto", padding: 16, paddingBottom: 100 }}>
        {/* Thông tin lô */}
        <div style={{ ...card, background: "#fff" }}>
          {[["Khách", ship.customer], ["Booking", ship.booking], ["Số cont", ship.contNo], ["Kho", ship.kho], ["Tuyến", [ship.from, ship.to].filter(Boolean).join(" → ")], ["Giờ đến dự kiến", fmtDT(ship.gioDenDuKien)]].filter(([, v]) => v).map(([k, v]) => (
            <div key={k} style={{ display: "flex", justifyContent: "space-between", gap: 12, padding: "5px 0", fontSize: 14.5 }}>
              <span style={{ color: "#6b7585", flexShrink: 0 }}>{k}</span>
              <span style={{ fontWeight: 600, textAlign: "right" }} className="tnum">{v}</span>
            </div>
          ))}
        </div>

        {stampBtn(gioXeDen, setDen, editDen, setEditDen, "Xe đã đến", "bi-truck", "#2a6fdb")}
        {stampBtn(gioXeRa, setRa, editRa, setEditRa, "Xe đã ra (xong)", "bi-check2-circle", "#16a34a")}

        {/* Ghi chú */}
        <div style={card}>
          <label style={lbl}>Ghi chú <span style={{ color: "#9aa3b2", fontWeight: 400 }}>(nếu có)</span></label>
          <textarea value={note} onChange={(e) => setNote(e.target.value)} rows={2} placeholder="VD: kẹt cảng, chờ hạ lâu…"
            style={{ width: "100%", padding: "13px 14px", fontSize: 16, border: "1px solid #d9dee7", borderRadius: 12, resize: "vertical", minHeight: 56, fontFamily: "inherit", lineHeight: 1.5 }} />
        </div>

        {/* Ảnh */}
        <div style={card}>
          <label style={lbl}>Ảnh <span style={{ color: "#9aa3b2", fontWeight: 400 }}>(cont, chứng từ…)</span></label>
          <div style={{ display: "flex", flexWrap: "wrap", gap: 10 }}>
            {photos.map((p, i) => (
              <div key={i} style={{ position: "relative", width: 92, height: 92, borderRadius: 12, overflow: "hidden", border: "1px solid #e3e7ee" }}>
                <a href={p.url} target="_blank" rel="noreferrer"><img src={p.url} alt="" style={{ width: "100%", height: "100%", objectFit: "cover", display: "block" }} /></a>
                <button type="button" onClick={() => removePhoto(i)} style={{ position: "absolute", top: 4, right: 4, width: 26, height: 26, borderRadius: "50%", border: "none", background: "rgba(20,24,30,.66)", color: "#fff", cursor: "pointer", fontSize: 13 }}><i className="bi bi-x-lg" /></button>
              </div>
            ))}
            <button type="button" onClick={() => fileRef.current && fileRef.current.click()} disabled={photoBusy}
              style={{ width: 92, height: 92, borderRadius: 12, border: "1.5px dashed #b9c2d0", background: "#fff", cursor: "pointer", display: "grid", placeItems: "center", color: "#6b7585" }}>
              <span style={{ display: "grid", placeItems: "center" }}><i className="bi bi-camera" style={{ fontSize: 24 }} /><span style={{ fontSize: 11.5, fontWeight: 600, marginTop: 3 }}>Thêm ảnh</span></span>
            </button>
          </div>
          <input ref={fileRef} type="file" accept="image/*" capture="environment" multiple onChange={addPhotos} style={{ display: "none" }} />
        </div>
      </div>

      {/* Nút lưu cố định đáy */}
      <div style={{ position: "sticky", bottom: 0, background: "#fff", borderTop: "1px solid #e3e7ee", padding: "12px 16px", paddingBottom: "max(12px, env(safe-area-inset-bottom))" }}>
        <button type="button" onClick={save} disabled={busy}
          style={{ width: "100%", padding: "16px", fontSize: 17, fontWeight: 800, border: "none", borderRadius: 14, background: busy ? "#9bb6e6" : "#2a6fdb", color: "#fff", cursor: busy ? "default" : "pointer", display: "inline-flex", alignItems: "center", justifyContent: "center", gap: 8 }}>
          {busy ? <><span style={{ width: 18, height: 18, border: "2.5px solid rgba(255,255,255,.45)", borderTopColor: "#fff", borderRadius: "50%", display: "inline-block", animation: "trk-spin .7s linear infinite" }} /> Đang lưu…</> : <><i className="bi bi-check-lg" /> Lưu cập nhật</>}
        </button>
      </div>
    </div>
  );
}

function App() {
  const data = B.data || {};
  const [ships, setShips] = useState(data.ships || []);
  const [q, setQ] = useState("");
  const [filter, setFilter] = useState("all");   // all | todo | done
  const [selHash, setSelHash] = useState(null);

  const ql = q.trim().toLowerCase();
  const list = useMemo(() => ships.filter((s) => {
    if (ql && !(`${s.customer} ${s.booking} ${s.contNo} ${s.bksVao} ${s.bksRa} ${s.kho}`.toLowerCase().includes(ql))) return false;
    if (filter === "todo") return !s.gioXeRa;
    if (filter === "done") return !!s.gioXeRa;
    return true;
  }), [ships, ql, filter]);

  const doneCount = ships.filter((s) => s.gioXeRa).length;
  const onSaved = (ship) => { setShips((arr) => arr.map((s) => s.hashid === ship.hashid ? ship : s)); setSelHash(null); };
  const sel = ships.find((s) => s.hashid === selHash);

  if (!B.active) {
    return (
      <div style={{ minHeight: "100svh", display: "grid", placeItems: "center", padding: 24, textAlign: "center", color: "#6b7585" }}>
        <div>
          <div style={{ fontSize: 48, color: "#c3ccda", marginBottom: 8 }}><i className="bi bi-link-45deg" /></div>
          <div style={{ fontSize: 17, fontWeight: 700, color: "#3a4759" }}>Link không còn hiệu lực</div>
          <div style={{ fontSize: 14, marginTop: 6 }}>Vui lòng liên hệ văn phòng để nhận link mới.</div>
        </div>
      </div>
    );
  }

  const FILTERS = [["all", "Tất cả", ships.length], ["todo", "Chưa xong", ships.length - doneCount], ["done", "Đã xong", doneCount]];
  return (
    <div style={{ maxWidth: 560, margin: "0 auto", padding: "16px 14px 40px" }}>
      <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 14 }}>
        <div style={{ width: 46, height: 46, borderRadius: 14, background: "linear-gradient(135deg,#2a6fdb,#4f8cf0)", color: "#fff", display: "grid", placeItems: "center", fontSize: 22, flexShrink: 0 }}><i className="bi bi-truck" /></div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontSize: 19, fontWeight: 800, letterSpacing: "-0.02em" }}>{data.title || "Kế hoạch xe"}</div>
          <div className="tnum" style={{ fontSize: 12.5, color: "#6b7585" }}>{fmtD(data.from)} – {fmtD(data.to)} · {ships.length} lô</div>
        </div>
      </div>

      {/* Lọc + tìm */}
      <div style={{ display: "flex", background: "#e6e9ef", borderRadius: 12, padding: 4, gap: 3, marginBottom: 10 }}>
        {FILTERS.map(([k, t, n]) => { const on = filter === k; return (
          <button key={k} type="button" onClick={() => setFilter(k)}
            style={{ flex: 1, border: "none", cursor: "pointer", fontSize: 14, fontWeight: 700, padding: "10px 6px", borderRadius: 9, background: on ? "#fff" : "transparent", color: on ? "#2a6fdb" : "#6b7585", boxShadow: on ? "0 1px 3px rgba(16,24,40,.12)" : "none" }}>{t} <span className="tnum" style={{ fontSize: 12, opacity: .8 }}>{n}</span></button>
        ); })}
      </div>
      <div style={{ position: "relative", marginBottom: 14 }}>
        <i className="bi bi-search" style={{ position: "absolute", left: 14, top: "50%", transform: "translateY(-50%)", color: "#9aa3b2" }} />
        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Tìm booking / cont / biển số…"
          style={{ width: "100%", padding: "13px 14px 13px 40px", fontSize: 16, border: "1px solid #d9dee7", borderRadius: 12, outline: "none", background: "#fff" }} />
      </div>

      {/* Danh sách lô */}
      {list.length === 0 ? (
        <div style={{ background: "#fff", border: "1px dashed #d0d7e2", borderRadius: 14, padding: "30px 16px", textAlign: "center", color: "#9aa3b2", fontSize: 14 }}>Không có lô nào.</div>
      ) : (
        <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
          {list.map((s) => { const st = statusOf(s); return (
            <button key={s.hashid} type="button" onClick={() => setSelHash(s.hashid)}
              style={{ textAlign: "left", border: "1px solid #e3e7ee", borderLeft: `4px solid ${st.c}`, background: "#fff", borderRadius: 14, padding: "13px 14px", cursor: "pointer", display: "flex", alignItems: "center", gap: 12 }}>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 15.5, fontWeight: 700, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{s.customer || s.booking || "Lô hàng"}</div>
                <div className="tnum" style={{ fontSize: 12.5, color: "#6b7585", marginTop: 2 }}>{[s.contNo, s.kho, [s.from, s.to].filter(Boolean).join("→")].filter(Boolean).join(" · ")}</div>
                <div className="tnum" style={{ fontSize: 12, color: "#9aa3b2", marginTop: 3 }}><i className="bi bi-clock" /> KH {fmtDT(s.gioDenDuKien) || "—"}{s.gioXeDen ? ` · Đến ${fmtDT(s.gioXeDen)}` : ""}{s.gioXeRa ? ` · Ra ${fmtDT(s.gioXeRa)}` : ""}</div>
              </div>
              <span style={{ fontSize: 11.5, fontWeight: 700, color: st.c, background: st.bg, padding: "3px 10px", borderRadius: 999, whiteSpace: "nowrap", flexShrink: 0 }}>{st.t}</span>
              <i className="bi bi-chevron-right" style={{ color: "#c3ccda", flexShrink: 0 }} />
            </button>
          ); })}
        </div>
      )}

      {sel && <EditSheet ship={sel} onClose={() => setSelHash(null)} onSaved={onSaved} />}
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<App />);
