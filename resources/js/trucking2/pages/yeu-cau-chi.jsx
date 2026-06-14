import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";   // cung cấp window.trkUpload / trkToast / trkError (trang public không có layout nạp sẵn)

const T = window.__TRK || {};
const B = T.boot || {};
const group = (s) => { const d = String(s || "").replace(/[^\d]/g, ""); return d ? d.replace(/\B(?=(\d{3})+(?!\d))/g, ".") : ""; };
const today10 = () => new Date().toISOString().slice(0, 10);
const dmy = (s) => { if (!s) return ""; const p = String(s).split("-"); return p.length === 3 ? p[2] + "/" + p[1] + "/" + p[0] : s; };

/* ---------- Searchable select (select2-style, không cần thư viện) ---------- */
function Picker({ value, onChange, options, placeholder, icon }) {
  const { useState, useRef, useEffect } = React;
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState("");
  const box = useRef(null);
  useEffect(() => {
    if (!open) return;
    const h = (e) => { if (box.current && !box.current.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, [open]);
  const opts = options.map((o) => (typeof o === "string" ? { v: o, t: o } : o));
  const ql = q.trim().toLowerCase();
  const list = ql ? opts.filter((o) => o.t.toLowerCase().includes(ql)) : opts;
  const sel = opts.find((o) => String(o.v) === String(value));

  const field = { width: "100%", padding: "13px 14px", fontSize: 16, border: "1px solid #d9dee7", borderRadius: 12, background: "#fff", color: sel ? "#1b2330" : "#9aa3b2", display: "flex", alignItems: "center", gap: 10, cursor: "pointer", minHeight: 50 };
  return (
    <div ref={box} style={{ position: "relative" }}>
      <div style={{ ...field, borderColor: open ? "#2a6fdb" : "#d9dee7", boxShadow: open ? "0 0 0 3px rgba(42,111,219,.12)" : "none" }} onClick={() => { setOpen((o) => !o); setQ(""); }}>
        {icon && <i className={"bi " + icon} style={{ fontSize: 17, color: "#6b7585" }} />}
        <span style={{ flex: 1, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{sel ? sel.t : (placeholder || "— Chọn —")}</span>
        <i className="bi bi-chevron-down" style={{ fontSize: 13, color: "#9aa3b2", transform: open ? "rotate(180deg)" : "none", transition: "transform .15s" }} />
      </div>
      {open && (
        <div style={{ position: "absolute", zIndex: 30, top: "calc(100% + 6px)", left: 0, right: 0, background: "#fff", border: "1px solid #e3e7ee", borderRadius: 12, boxShadow: "0 10px 30px rgba(16,24,40,.16)", overflow: "hidden" }}>
          <div style={{ padding: 8, borderBottom: "1px solid #eef1f6" }}>
            <input autoFocus value={q} onChange={(e) => setQ(e.target.value)} placeholder="Gõ để tìm…"
              style={{ width: "100%", padding: "9px 12px", fontSize: 15, border: "1px solid #e3e7ee", borderRadius: 9, outline: "none" }} />
          </div>
          <div style={{ maxHeight: 240, overflowY: "auto" }}>
            {list.length === 0 && <div style={{ padding: "14px", fontSize: 14, color: "#9aa3b2", textAlign: "center" }}>Không tìm thấy</div>}
            {list.map((o) => {
              const on = String(o.v) === String(value);
              return (
                <div key={o.v} onClick={() => { onChange(o.v); setOpen(false); }}
                  style={{ padding: "12px 14px", fontSize: 15.5, cursor: "pointer", display: "flex", alignItems: "center", gap: 8, background: on ? "#eef4ff" : "#fff", color: on ? "#2a6fdb" : "#1b2330", fontWeight: on ? 700 : 500 }}
                  onMouseDown={(e) => e.preventDefault()}>
                  {on && <i className="bi bi-check-lg" />} <span style={{ flex: 1 }}>{o.t}</span>
                </div>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}

function App() {
  const { useState, useRef } = React;
  const vehicles = B.vehicles || [];
  const costItems = B.costItems || [];
  const auth = B.auth || {};
  const [history, setHistory] = useState(B.history || []);
  const refreshHistory = async () => { try { const r = await window.trkApi("GET", T.routes.history); if (r && r.ok) setHistory(r.history || []); } catch (e) {} };
  const logout = async () => { try { await window.trkApi("POST", T.routes.logout); } catch (e) {} window.location.reload(); };
  const cancelItem = async (h) => {
    const ok = await window.confirmAction({ title: "Hủy phiếu này?", text: `Phiếu <b>${h.invoiceNo || h.name}</b> · ${h.amount} đ sẽ bị hủy.`, confirmText: "Hủy phiếu", danger: true });
    if (!ok) return;
    try { const r = await window.trkApi("POST", T.routes.cancel + h.id + "/cancel"); if (r && r.ok) { window.trkToast("Đã hủy phiếu"); refreshHistory(); } else window.trkToast((r && r.message) || "Không hủy được", "error"); } catch (e) {}
  };
  const [editId, setEditId] = useState(null);   // đang sửa phiếu nào (null = tạo mới)
  const [vehicleId, setVehicleId] = useState(vehicles.length === 1 ? String(vehicles[0].id) : "");
  const [costItem, setCostItem] = useState("");
  const [date, setDate] = useState(today10());
  const [amount, setAmount] = useState("");
  const [km, setKm] = useState("");
  const [photos, setPhotos] = useState([]);   // [{file?:File mới, ref?:basename ảnh cũ, url, name}]
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState(null);
  const fileRef = useRef(null);
  const formRef = useRef(null);

  const label = { fontSize: 13.5, fontWeight: 700, color: "#3a4759", marginBottom: 7, display: "block" };
  const input = { width: "100%", padding: "13px 14px", fontSize: 16, border: "1px solid #d9dee7", borderRadius: 12, outline: "none", background: "#fff", color: "#1b2330", appearance: "none", WebkitAppearance: "none" };
  const field = { marginBottom: 17 };

  const resetForm = () => {
    photos.forEach((p) => { if (p.file && p.url) { try { URL.revokeObjectURL(p.url); } catch (e) {} } });
    setEditId(null); setCostItem(""); setDate(today10()); setAmount(""); setKm(""); setPhotos([]);
    setVehicleId(vehicles.length === 1 ? String(vehicles[0].id) : ""); setResult(null);
  };
  const startEdit = (h) => {
    setEditId(h.id); setVehicleId(String(h.vehicleId || "")); setCostItem(h.name || "");
    setDate(h.date || today10()); setAmount(h.amount || ""); setKm(h.km || "");
    setPhotos((h.photos || []).map((p) => ({ ref: p.file, url: p.url, name: p.name })));
    setResult(null);
    try { formRef.current && formRef.current.scrollIntoView({ behavior: "smooth", block: "start" }); } catch (e) {}
  };

  const addPhotos = (e) => {
    const files = Array.from(e.target.files || []); e.target.value = "";
    const next = files.filter((f) => f.type.startsWith("image/")).map((f) => ({ file: f, url: URL.createObjectURL(f) }));
    setPhotos((p) => [...p, ...next].slice(0, 12));
  };
  const removePhoto = (i) => setPhotos((p) => { const x = p[i]; if (x && x.file && x.url) { try { URL.revokeObjectURL(x.url); } catch (e) {} } return p.filter((_, j) => j !== i); });

  const submit = async () => {
    if (busy) return;
    setResult(null);
    if (!vehicleId) return setResult({ ok: false, message: "Vui lòng chọn xe." });
    if (!costItem) return setResult({ ok: false, message: "Vui lòng chọn loại chi phí." });
    const amt = amount.replace(/[^\d]/g, "");
    if (!amt) return setResult({ ok: false, message: "Vui lòng nhập số tiền." });
    setBusy(true);
    try {
      const fd = new FormData();
      if (!editId) fd.append("vehicleId", vehicleId);
      fd.append("costItem", costItem);
      fd.append("date", date);
      fd.append("amount", amt);
      fd.append("km", km.replace(/[^\d]/g, ""));
      photos.filter((p) => p.file).forEach((p) => fd.append("photos[]", p.file));
      if (editId) photos.filter((p) => p.ref).forEach((p) => fd.append("keep[]", p.ref));
      const r = await window.trkUpload("POST", editId ? (T.routes.cancel + editId + "/update") : T.routes.submit, fd);
      if (r && r.ok) { window.trkToast(editId ? "Đã cập nhật phiếu" : "Đã gửi yêu cầu chi"); resetForm(); refreshHistory(); }
      else setResult(r);
    } catch (e) { setResult({ ok: false, message: "Lỗi kết nối — vui lòng thử lại." }); }
    setBusy(false);
  };

  return (
    <div style={{ maxWidth: 480, margin: "0 auto", padding: "20px 16px 48px" }}>
      <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 18 }}>
        <div style={{ width: 46, height: 46, borderRadius: 14, background: "linear-gradient(135deg,#2a6fdb,#4f8cf0)", color: "#fff", display: "grid", placeItems: "center", fontSize: 23, boxShadow: "0 4px 12px rgba(42,111,219,.3)" }}><i className="bi bi-receipt" /></div>
        <div style={{ flex: 1 }}>
          <div style={{ fontSize: 20, fontWeight: 800, letterSpacing: "-0.02em" }}>Gửi yêu cầu chi</div>
          <div style={{ fontSize: 12.5, color: "#6b7585" }}>{auth.name ? auth.name + " · kế toán duyệt sau" : "Tài xế đề nghị · kế toán duyệt sau"}</div>
        </div>
        <button type="button" onClick={logout} title="Đăng xuất" style={{ flexShrink: 0, border: "1px solid #d9dee7", background: "#fff", color: "#6b7585", borderRadius: 10, padding: "8px 11px", fontSize: 13, fontWeight: 600, cursor: "pointer" }}><i className="bi bi-box-arrow-right" /></button>
      </div>

      <div ref={formRef} style={{ background: "#fff", border: "1px solid #e3e7ee", borderRadius: 18, padding: "20px 16px", boxShadow: "0 1px 3px rgba(16,24,40,.05)" }}>
        {editId && (
          <div style={{ display: "flex", alignItems: "center", gap: 8, background: "#eff5ff", border: "1px solid #cfe0fb", borderRadius: 12, padding: "10px 12px", marginBottom: 16, fontSize: 13.5, color: "#1f4f9e" }}>
            <i className="bi bi-pencil-square" /> <span style={{ flex: 1 }}>Đang sửa phiếu <b>#{editId}</b></span>
            <button type="button" onClick={resetForm} style={{ border: "none", background: "transparent", color: "#1f4f9e", fontWeight: 700, fontSize: 13, cursor: "pointer", padding: 0 }}>Hủy sửa</button>
          </div>
        )}
        <div style={field}>
          <label style={label}>Người yêu cầu</label>
          <div style={{ ...input, background: "#f4f6fa", color: "#6b7585", display: "flex", alignItems: "center", gap: 9, cursor: "default" }}><i className="bi bi-person-circle" style={{ fontSize: 18, color: "#9aa3b2" }} />{auth.name || "—"}</div>
        </div>
        <div style={field}>
          <label style={label}>Xe <span style={{ color: "#e0533d" }}>*</span></label>
          <Picker value={vehicleId} onChange={setVehicleId} options={vehicles.map((v) => ({ v: String(v.id), t: v.plate }))} placeholder="Chọn xe…" icon="bi-truck" />
        </div>
        <div style={field}>
          <label style={label}>Loại chi phí <span style={{ color: "#e0533d" }}>*</span></label>
          <Picker value={costItem} onChange={setCostItem} options={costItems} placeholder="Chọn loại chi phí…" icon="bi-tag" />
        </div>

        <div style={{ display: "flex", gap: 12 }}>
          <div style={{ ...field, flex: 1 }}>
            <label style={label}>Ngày chi</label>
            <input type="date" style={{ ...input, colorScheme: "light" }} value={date} onChange={(e) => setDate(e.target.value)} />
          </div>
          <div style={{ ...field, flex: 1 }}>
            <label style={label}>KM hiện tại</label>
            <input inputMode="numeric" style={{ ...input, textAlign: "right", fontVariantNumeric: "tabular-nums" }} value={group(km)} onChange={(e) => setKm(e.target.value)} placeholder="Số đồng hồ" />
          </div>
        </div>

        <div style={field}>
          <label style={label}>Số tiền (đ) <span style={{ color: "#e0533d" }}>*</span></label>
          <div style={{ position: "relative" }}>
            <input inputMode="numeric" style={{ ...input, textAlign: "right", fontWeight: 800, fontSize: 19, paddingRight: 42, fontVariantNumeric: "tabular-nums" }} value={group(amount)} onChange={(e) => setAmount(e.target.value)} placeholder="0" />
            <span style={{ position: "absolute", right: 14, top: "50%", transform: "translateY(-50%)", fontSize: 14, fontWeight: 700, color: "#9aa3b2" }}>đ</span>
          </div>
        </div>

        <div style={field}>
          <label style={label}>Ảnh thực tế <span style={{ color: "#9aa3b2", fontWeight: 400 }}>(hóa đơn, đồng hồ km, phụ tùng…)</span></label>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 9 }}>
            {photos.map((p, i) => (
              <div key={i} style={{ position: "relative", paddingTop: "100%", borderRadius: 12, overflow: "hidden", border: "1px solid #e3e7ee" }}>
                <img src={p.url} alt="" style={{ position: "absolute", inset: 0, width: "100%", height: "100%", objectFit: "cover" }} />
                <button type="button" onClick={() => removePhoto(i)} style={{ position: "absolute", top: 4, right: 4, width: 26, height: 26, borderRadius: "50%", border: "none", background: "rgba(20,24,30,.65)", color: "#fff", cursor: "pointer", display: "grid", placeItems: "center", fontSize: 13 }}><i className="bi bi-x-lg" /></button>
              </div>
            ))}
            {photos.length < 12 && (
              <button type="button" onClick={() => fileRef.current && fileRef.current.click()}
                style={{ paddingTop: "100%", position: "relative", borderRadius: 12, border: "1.5px dashed #c3ccda", background: "#f7f9fc", cursor: "pointer" }}>
                <span style={{ position: "absolute", inset: 0, display: "grid", placeItems: "center", color: "#6b7585", gap: 3 }}>
                  <span style={{ display: "grid", placeItems: "center", lineHeight: 1 }}><i className="bi bi-camera" style={{ fontSize: 22 }} /><span style={{ fontSize: 11.5, fontWeight: 600, marginTop: 4 }}>Thêm ảnh</span></span>
                </span>
              </button>
            )}
          </div>
          <input ref={fileRef} type="file" accept="image/*" multiple onChange={addPhotos} style={{ display: "none" }} />
        </div>

        {result && (
          <div style={{ display: "flex", gap: 9, padding: "13px 14px", borderRadius: 12, marginBottom: 15, fontSize: 14, lineHeight: 1.5,
            background: result.ok ? "#e8f6ee" : "#fdecec", color: result.ok ? "#176b44" : "#b42318", border: `1px solid ${result.ok ? "#bfe4cf" : "#f3c9c9"}` }}>
            <i className={"bi " + (result.ok ? "bi-check-circle-fill" : "bi-exclamation-triangle-fill")} style={{ marginTop: 1, fontSize: 16 }} />
            <span>{result.message}</span>
          </div>
        )}

        <button type="button" onClick={submit} disabled={busy}
          style={{ width: "100%", padding: "15px", fontSize: 16.5, fontWeight: 800, border: "none", borderRadius: 13, background: busy ? "#9bb6e6" : "#2a6fdb", color: "#fff", cursor: busy ? "default" : "pointer", display: "inline-flex", alignItems: "center", justifyContent: "center", gap: 8, boxShadow: busy ? "none" : "0 4px 14px rgba(42,111,219,.3)" }}>
          {busy ? <><span style={{ width: 18, height: 18, border: "2.5px solid rgba(255,255,255,.45)", borderTopColor: "#fff", borderRadius: "50%", display: "inline-block", animation: "trk-spin .7s linear infinite" }} /> {editId ? "Đang cập nhật…" : "Đang gửi…"}</> : (editId ? <><i className="bi bi-check-lg" /> Cập nhật phiếu</> : <><i className="bi bi-send-fill" /> Gửi yêu cầu</>)}
        </button>
      </div>

      <div style={{ marginTop: 22 }}>
        <div style={{ fontSize: 14, fontWeight: 800, color: "#3a4759", margin: "0 4px 10px" }}>Lịch sử yêu cầu{history.length ? ` (${history.length})` : ""}</div>
        {history.length === 0 ? (
          <div style={{ background: "#fff", border: "1px dashed #d0d7e2", borderRadius: 14, padding: "28px 16px", textAlign: "center", color: "#9aa3b2" }}>
            <div style={{ fontSize: 32, marginBottom: 6, color: "#c3ccda" }}><i className="bi bi-inbox" /></div>
            <div style={{ fontSize: 13.5 }}>Chưa có phiếu nào.<br />Điền form trên rồi bấm <b>Gửi yêu cầu</b>.</div>
          </div>
        ) : (
          <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
            {history.map((h) => {
              const st = ({ pending: ["#e0a92e", "#fdf3df"], approved: ["#2a6fdb", "#eaf1fd"], paid: ["#16a34a", "#e8f6ee"], cancelled: ["#94a3b8", "#f1f3f6"] })[h.status] || ["#6b7585", "#f1f3f6"];
              return (
                <div key={h.id} style={{ background: "#fff", border: editId === h.id ? "1.5px solid #2a6fdb" : "1px solid #e3e7ee", borderRadius: 14, padding: "12px 14px", opacity: h.status === "cancelled" ? 0.7 : 1 }}>
                  <div style={{ display: "flex", alignItems: "flex-start", gap: 8 }}>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ fontSize: 14.5, fontWeight: 700, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis", textDecoration: h.status === "cancelled" ? "line-through" : "none" }}>{h.name}</div>
                      <div style={{ fontSize: 12, color: "#9aa3b2", marginTop: 2 }}>{h.plate} · {dmy(h.date)}{h.invoiceNo ? " · " + h.invoiceNo : ""}{(h.photos || []).length ? " · 📷 " + h.photos.length : ""}</div>
                    </div>
                    <div style={{ textAlign: "right", flexShrink: 0 }}>
                      <div style={{ fontSize: 15.5, fontWeight: 800, fontVariantNumeric: "tabular-nums" }}>{group(h.amount)} đ</div>
                      <span style={{ display: "inline-block", marginTop: 3, fontSize: 11, fontWeight: 700, color: st[0], background: st[1], padding: "2px 9px", borderRadius: 999 }}>{h.statusLabel}</span>
                    </div>
                  </div>
                  {(h.canEdit || h.canCancel) && (
                    <div style={{ display: "flex", gap: 8, marginTop: 10 }}>
                      {h.canEdit && <button type="button" onClick={() => startEdit(h)} style={{ flex: 1, padding: "9px", fontSize: 13.5, fontWeight: 700, border: "1px solid #cfe0fb", background: "#fff", color: "#2a6fdb", borderRadius: 10, cursor: "pointer" }}><i className="bi bi-pencil" /> Sửa</button>}
                      {h.canCancel && <button type="button" onClick={() => cancelItem(h)} style={{ flex: 1, padding: "9px", fontSize: 13.5, fontWeight: 700, border: "1px solid #f3c9c9", background: "#fff", color: "#dc2626", borderRadius: 10, cursor: "pointer" }}><i className="bi bi-x-circle" /> Hủy</button>}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </div>

      <div style={{ textAlign: "center", fontSize: 11.5, color: "#9aa3b2", marginTop: 18 }}>MBF Joint Stock Company</div>
    </div>
  );
}

/* ---------- Đăng nhập mobile (đơn giản, không 2FA) ---------- */
function LoginCard() {
  const { useState } = React;
  const [email, setEmail] = useState("");
  const [pw, setPw] = useState("");
  const [showPw, setShowPw] = useState(false);
  const [remember, setRemember] = useState(true);   // mặc định LUÔN đăng nhập
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState("");
  const inp = { width: "100%", padding: "14px", fontSize: 16, border: "1px solid #d9dee7", borderRadius: 12, outline: "none", marginBottom: 12 };
  const doLogin = async () => {
    if (busy) return; setErr("");
    if (!email || !pw) return setErr("Nhập email và mật khẩu.");
    setBusy(true);
    try {
      const r = await window.trkApi("POST", T.routes.login, { email, password: pw, remember: remember ? 1 : 0 });
      if (r && r.ok) { window.location.reload(); return; }
      setErr((r && r.message) || "Đăng nhập thất bại."); setBusy(false);
    } catch (e) { setErr("Lỗi kết nối — thử lại."); setBusy(false); }
  };
  return (
    <div style={{ maxWidth: 400, margin: "0 auto", padding: "56px 18px" }}>
      <div style={{ textAlign: "center", marginBottom: 24 }}>
        <div style={{ width: 60, height: 60, borderRadius: 18, background: "linear-gradient(135deg,#2a6fdb,#4f8cf0)", color: "#fff", display: "grid", placeItems: "center", fontSize: 30, margin: "0 auto 14px", boxShadow: "0 6px 16px rgba(42,111,219,.35)" }}><i className="bi bi-receipt" /></div>
        <div style={{ fontSize: 21, fontWeight: 800 }}>Yêu cầu chi</div>
        <div style={{ fontSize: 13, color: "#6b7585", marginTop: 4 }}>Đăng nhập để gửi & theo dõi phiếu chi</div>
      </div>
      <div style={{ background: "#fff", border: "1px solid #e3e7ee", borderRadius: 18, padding: "22px 18px", boxShadow: "0 1px 3px rgba(16,24,40,.05)" }}>
        <input type="email" autoComplete="username" inputMode="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="Email" style={inp} />
        <div style={{ position: "relative" }}>
          <input type={showPw ? "text" : "password"} autoComplete="current-password" value={pw} onChange={(e) => setPw(e.target.value)} onKeyDown={(e) => { if (e.key === "Enter") doLogin(); }} placeholder="Mật khẩu" style={{ ...inp, paddingRight: 46 }} />
          <button type="button" onClick={() => setShowPw((s) => !s)} title={showPw ? "Ẩn mật khẩu" : "Xem mật khẩu"} style={{ position: "absolute", right: 6, top: 7, width: 38, height: 38, border: "none", background: "transparent", color: "#6b7585", fontSize: 18, cursor: "pointer", display: "grid", placeItems: "center" }}><i className={"bi " + (showPw ? "bi-eye-slash" : "bi-eye")} /></button>
        </div>
        <label style={{ display: "flex", alignItems: "center", gap: 9, fontSize: 14, color: "#3a4759", margin: "2px 2px 14px", cursor: "pointer", userSelect: "none" }}>
          <input type="checkbox" checked={remember} onChange={(e) => setRemember(e.target.checked)} style={{ width: 18, height: 18, accentColor: "#2a6fdb" }} />
          Luôn đăng nhập trên thiết bị này
        </label>
        {err && <div style={{ fontSize: 13.5, color: "#b42318", background: "#fdecec", border: "1px solid #f3c9c9", borderRadius: 10, padding: "10px 12px", marginBottom: 12 }}>{err}</div>}
        <button type="button" onClick={doLogin} disabled={busy} style={{ width: "100%", padding: "15px", fontSize: 16.5, fontWeight: 800, border: "none", borderRadius: 13, background: busy ? "#9bb6e6" : "#2a6fdb", color: "#fff", cursor: busy ? "default" : "pointer", display: "inline-flex", alignItems: "center", justifyContent: "center", gap: 9, boxShadow: busy ? "none" : "0 4px 14px rgba(42,111,219,.3)", transition: "background .15s" }}>
          {busy ? <><span style={{ width: 18, height: 18, border: "2.5px solid rgba(255,255,255,.45)", borderTopColor: "#fff", borderRadius: "50%", display: "inline-block", animation: "trk-spin .7s linear infinite" }} /> Đang đăng nhập…</> : <><i className="bi bi-box-arrow-in-right" /> Đăng nhập</>}
        </button>
      </div>
      <div style={{ textAlign: "center", fontSize: 11.5, color: "#9aa3b2", marginTop: 18 }}>MBF Joint Stock Company</div>
    </div>
  );
}

function NoPermission() {
  const logout = async () => { try { await window.trkApi("POST", T.routes.logout); } catch (e) {} window.location.reload(); };
  return (
    <div style={{ maxWidth: 400, margin: "0 auto", padding: "64px 18px", textAlign: "center" }}>
      <div style={{ fontSize: 42, color: "#e0a92e" }}><i className="bi bi-shield-lock" /></div>
      <div style={{ fontSize: 17, fontWeight: 800, marginTop: 10 }}>Chưa có quyền gửi yêu cầu chi</div>
      <div style={{ fontSize: 13.5, color: "#6b7585", marginTop: 8, lineHeight: 1.5 }}>Tài khoản chưa được cấp quyền. Liên hệ quản trị để được thêm vai trò “Gửi yêu cầu chi”.</div>
      <button type="button" onClick={logout} style={{ marginTop: 18, padding: "11px 18px", fontSize: 14, fontWeight: 700, border: "1px solid #d9dee7", background: "#fff", borderRadius: 11, cursor: "pointer" }}>Đăng xuất</button>
    </div>
  );
}

function Root() {
  const auth = B.auth || {};
  if (!auth.logged) return <LoginCard />;
  if (!auth.canRequest) return <NoPermission />;
  return <App />;
}

createRoot(document.getElementById("trk-root")).render(<Root />);
