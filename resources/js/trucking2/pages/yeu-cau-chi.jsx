import React from "react";
import { createRoot } from "react-dom/client";

const T = window.__TRK || {};
const B = T.boot || {};
const group = (s) => { const d = String(s || "").replace(/[^\d]/g, ""); return d ? d.replace(/\B(?=(\d{3})+(?!\d))/g, ".") : ""; };
const today10 = () => new Date().toISOString().slice(0, 10);

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
  const [vehicleId, setVehicleId] = useState(vehicles.length === 1 ? String(vehicles[0].id) : "");
  const [costItem, setCostItem] = useState("");
  const [date, setDate] = useState(today10());
  const [amount, setAmount] = useState("");
  const [km, setKm] = useState("");
  const [photos, setPhotos] = useState([]);   // [{file, url}]
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState(null);
  const fileRef = useRef(null);

  const label = { fontSize: 13.5, fontWeight: 700, color: "#3a4759", marginBottom: 7, display: "block" };
  const input = { width: "100%", padding: "13px 14px", fontSize: 16, border: "1px solid #d9dee7", borderRadius: 12, outline: "none", background: "#fff", color: "#1b2330", appearance: "none", WebkitAppearance: "none" };
  const field = { marginBottom: 17 };

  const addPhotos = (e) => {
    const files = Array.from(e.target.files || []); e.target.value = "";
    const next = files.filter((f) => f.type.startsWith("image/")).map((f) => ({ file: f, url: URL.createObjectURL(f) }));
    setPhotos((p) => [...p, ...next].slice(0, 12));
  };
  const removePhoto = (i) => setPhotos((p) => { try { URL.revokeObjectURL(p[i].url); } catch (e) {} return p.filter((_, j) => j !== i); });

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
      fd.append("vehicleId", vehicleId);
      fd.append("costItem", costItem);
      fd.append("date", date);
      fd.append("amount", amt);
      fd.append("km", km.replace(/[^\d]/g, ""));
      photos.forEach((p) => fd.append("photos[]", p.file));
      const r = await fetch(T.routes.submit, { method: "POST", headers: { "Accept": "application/json", "X-CSRF-TOKEN": T.csrf }, body: fd }).then((x) => x.json());
      setResult(r);
      if (r && r.ok) { setAmount(""); setKm(""); setCostItem(""); photos.forEach((p) => { try { URL.revokeObjectURL(p.url); } catch (e) {} }); setPhotos([]); }
    } catch (e) { setResult({ ok: false, message: "Lỗi kết nối — vui lòng thử lại." }); }
    setBusy(false);
  };

  return (
    <div style={{ maxWidth: 480, margin: "0 auto", padding: "20px 16px 48px" }}>
      <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 18 }}>
        <div style={{ width: 46, height: 46, borderRadius: 14, background: "linear-gradient(135deg,#2a6fdb,#4f8cf0)", color: "#fff", display: "grid", placeItems: "center", fontSize: 23, boxShadow: "0 4px 12px rgba(42,111,219,.3)" }}><i className="bi bi-receipt" /></div>
        <div>
          <div style={{ fontSize: 20, fontWeight: 800, letterSpacing: "-0.02em" }}>Gửi yêu cầu chi</div>
          <div style={{ fontSize: 12.5, color: "#6b7585" }}>Tài xế đề nghị · kế toán duyệt sau</div>
        </div>
      </div>

      <div style={{ background: "#fff", border: "1px solid #e3e7ee", borderRadius: 18, padding: "20px 16px", boxShadow: "0 1px 3px rgba(16,24,40,.05)" }}>
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
          {busy ? <><span style={{ width: 17, height: 17, border: "2px solid rgba(255,255,255,.5)", borderTopColor: "#fff", borderRadius: "50%", display: "inline-block", animation: "trk-spin .7s linear infinite" }} /> Đang gửi…</> : <><i className="bi bi-send-fill" /> Gửi yêu cầu</>}
        </button>
      </div>
      <div style={{ textAlign: "center", fontSize: 11.5, color: "#9aa3b2", marginTop: 18 }}>MBF Joint Stock Company</div>
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<App />);
