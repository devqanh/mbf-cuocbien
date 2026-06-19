import React from "react";
const { useState, useRef, useEffect } = React;
import { I, Txt, Combo, DateField, useIsMobile } from "@trk/lib.jsx";
import { loadBanks, banksSync, findBank } from "@trk/banks.js";

// Ô chọn ngân hàng từ danh sách VietQR (lưu tên viết tắt + bin để dựng QR sau này). Chỉ chọn, không gõ tự do.
function BankPicker({ value, bin, banks, onPick }) {
  const opts = banks.map((b) => ({ value: String(b.bin), label: `${b.shortName || b.code} — ${b.name}` }));
  // Khớp value hiện tại: ưu tiên bin đã lưu; dữ liệu cũ chỉ có tên thì dò theo code/shortName.
  const cur = findBank({ bin, bank: value });
  return (
    <Combo
      value={cur ? String(cur.bin) : ""}
      onChange={(v) => { const b = banks.find((x) => String(x.bin) === String(v)); if (b) onPick({ bank: b.shortName || b.code, bin: String(b.bin), code: b.code }); }}
      options={opts}
      placeholder={value ? value + " (chọn lại)" : "Chọn ngân hàng…"}
      strict
      small
    />
  );
}

/* ===================== HỒ SƠ LÁI XE (master-detail) ===================== */

// Thâm niên (số năm/tháng) tính từ ngày vào công ty → hiện tại. Không nhập tay.
function tenureLabel(joined) {
  if (!joined) return "";
  const p = joined.split("-").map(Number); const y = p[0], m = p[1], d = p[2];
  if (!y || !m) return "";
  const now = new Date();
  let months = (now.getFullYear() - y) * 12 + (now.getMonth() + 1 - m);
  if (now.getDate() < (d || 1)) months -= 1;
  if (months < 0) return "chưa tới ngày vào làm";
  const yy = Math.floor(months / 12), mm = months % 12;
  return (yy ? `${yy} năm ` : "") + `${mm} tháng` + ` (${months} tháng)`;
}

const DOC_TYPES = ["CCCD mặt trước", "CCCD mặt sau", "Bằng lái xe", "Khác"];

export function DriversManager({ cfg, setCfg }) {
  const isMobile = useIsMobile();
  const drivers = cfg.drivers || [];
  const [sel, setSel] = useState(0);
  const [draft, setDraft] = useState("");
  const [docType, setDocType] = useState("Khác");   // mặc định "Khác" — user chủ động chọn loại đúng
  const [busy, setBusy] = useState(false);
  const T = window.__TRK || {}; const ROUTES = T.routes || {};
  const fileRef = useRef(null);
  const idx = sel < drivers.length ? sel : (drivers.length ? 0 : -1);
  const cur = idx >= 0 ? drivers[idx] : null;
  // Danh sách NH VietQR (tải 1 lần, cache localStorage) — dùng cho ô chọn ngân hàng.
  const [banks, setBanks] = useState(() => banksSync());
  useEffect(() => { if (!banks.length) loadBanks().then((b) => setBanks(b || [])); }, []);

  const setDriver = (np) => setCfg("drivers", drivers.map((d, j) => (j === idx ? { ...d, ...np } : d)));
  const add = () => {
    const n = (draft || "").trim() || "Lái xe mới";
    setCfg("drivers", [...drivers, { name: n, phones: [], birthday: "", joinedDate: "", banks: [], docs: [] }]);
    setSel(drivers.length); setDraft("");
  };
  const remove = (j) => { setCfg("drivers", drivers.filter((_, k) => k !== j)); setSel(0); };

  // phones repeater
  const setPhone = (i, v) => setDriver({ phones: (cur.phones || []).map((p, k) => (k === i ? v : p)) });
  const addPhone = () => setDriver({ phones: [...(cur.phones || []), ""] });
  const delPhone = (i) => setDriver({ phones: (cur.phones || []).filter((_, k) => k !== i) });
  // banks repeater
  const setBank = (i, np) => setDriver({ banks: (cur.banks || []).map((b, k) => (k === i ? { ...b, ...np } : b)) });
  const addBank = () => setDriver({ banks: [...(cur.banks || []), { bank: "", bin: "", code: "", number: "", holder: cur.name || "" }] });
  const delBank = (i) => setDriver({ banks: (cur.banks || []).filter((_, k) => k !== i) });

  // upload tài liệu (cần lái xe đã lưu → có id)
  const onPickFiles = async (e) => {
    const files = Array.from(e.target.files || []); e.target.value = "";
    if (!files.length || !cur) return;
    if (!cur.id) { window.trkToast && window.trkToast("Hãy bấm Lưu để tạo lái xe trước khi tải tài liệu", "error"); return; }
    const fd = new FormData(); files.forEach((f) => fd.append("files[]", f)); fd.append("type", docType);
    setBusy(true);
    try {
      const res = await window.trkUpload("POST", ROUTES.driversBase + (cur.hashid || cur.id) + "/docs", fd);
      if (res && res.ok) { setDriver({ docs: res.docs }); window.trkToast && window.trkToast(`Đã tải ${files.length} tài liệu`); }
      else window.trkToast && window.trkToast((res && res.message) || "Tải lên thất bại", "error");
    } catch (err) { window.trkToast && window.trkToast("Lỗi kết nối khi tải lên", "error"); }
    setBusy(false);
  };
  const delDoc = async (attId) => {
    if (!cur || !cur.id) return;
    const ok = await window.confirmAction({ title: "Xóa tài liệu?", text: "Tài liệu này sẽ bị xóa vĩnh viễn.", confirmText: '<i class="bi bi-trash me-1"></i> Xóa', danger: true });
    if (!ok) return;
    try { const res = await window.trkApi("DELETE", ROUTES.driversBase + (cur.hashid || cur.id) + "/docs/" + attId); if (res && res.ok) setDriver({ docs: res.docs }); } catch (e) {}
  };

  const lbl = (t) => <div style={{ fontSize: 11.5, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>{t}</div>;
  const delBtn = (onClick, title) => (
    <button type="button" onClick={onClick} title={title} style={{ width: 32, height: 32, flexShrink: 0, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-4)", cursor: "pointer" }}
      onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
      onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-4)"; }}><I.trash /></button>
  );

  return (
    <div style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr" : "200px 1fr", gap: 16, minHeight: isMobile ? 0 : 380 }}>
      {/* danh sách lái xe */}
      <div style={{ borderRight: isMobile ? "none" : "1px solid var(--line-2)", borderBottom: isMobile ? "1px solid var(--line-2)" : "none", paddingRight: isMobile ? 0 : 12, paddingBottom: isMobile ? 12 : 0, display: "flex", flexDirection: "column", minHeight: 0 }}>
        <div style={{ display: "flex", gap: 6, marginBottom: 8 }}>
          <input value={draft} onChange={(e) => setDraft(e.target.value)} placeholder="Thêm lái xe…"
            onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); add(); } }}
            style={{ flex: 1, minWidth: 0, padding: "7px 9px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, outline: "none" }} />
          <button type="button" onClick={add} title="Thêm lái xe" style={{ width: 32, flexShrink: 0, display: "grid", placeItems: "center", border: "none", borderRadius: 8, background: "var(--accent)", color: "#fff", cursor: "pointer" }}><I.plus /></button>
        </div>
        <div style={{ overflowY: "auto", display: "flex", flexDirection: "column", gap: 1 }}>
          {drivers.map((d, j) => {
            const active = idx === j;
            return (
              <button key={j} type="button" onClick={() => setSel(j)}
                style={{ textAlign: "left", border: "none", cursor: "pointer", borderRadius: 8, padding: "8px 10px", background: active ? "var(--accent-weak)" : "transparent", color: active ? "var(--accent)" : "var(--ink)" }}
                onMouseEnter={(e) => { if (!active) e.currentTarget.style.background = "var(--line-2)"; }}
                onMouseLeave={(e) => { if (!active) e.currentTarget.style.background = "transparent"; }}>
                <div style={{ fontSize: 13.5, fontWeight: active ? 600 : 400, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{d.name || "(chưa đặt tên)"}</div>
                <div style={{ fontSize: 11.5, color: "var(--ink-4)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }} className="tnum">{(d.phones || [])[0] || "—"}{!d.id && " · chưa lưu"}</div>
              </button>
            );
          })}
          {!drivers.length && <div style={{ padding: "16px 4px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có lái xe.</div>}
        </div>
      </div>

      {/* chi tiết */}
      {cur ? (
        <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
          <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
            <input value={cur.name || ""} onChange={(e) => setDriver({ name: e.target.value })} placeholder="Tên lái xe…"
              style={{ flex: 1, padding: "9px 12px", fontSize: 15, fontWeight: 700, border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
              onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
            {delBtn(() => remove(idx), "Xóa lái xe")}
          </div>

          {/* SĐT (nhiều) */}
          <div>
            {lbl("Số điện thoại")}
            <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
              {(cur.phones || []).map((p, i) => (
                <div key={i} style={{ display: "flex", gap: 8 }}>
                  <Txt value={p} onChange={(v) => setPhone(i, v)} placeholder="VD: 09xx xxx xxx" />
                  {delBtn(() => delPhone(i), "Xóa số")}
                </div>
              ))}
              <button type="button" onClick={addPhone} style={{ alignSelf: "flex-start", display: "inline-flex", alignItems: "center", gap: 6, padding: "5px 10px", fontSize: 12.5, fontWeight: 600, border: "none", borderRadius: 7, background: "var(--accent-weak)", color: "var(--accent)", cursor: "pointer" }}><I.plus /> Thêm số điện thoại</button>
            </div>
          </div>

          {/* ngày sinh + ngày vào + thâm niên */}
          <div style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr 1fr" : "1fr 1fr 1fr", gap: 12, alignItems: "end" }}>
            <div>{lbl("Ngày sinh")}<DateField value={cur.birthday} onChange={(v) => setDriver({ birthday: v })} /></div>
            <div>{lbl("Ngày vào công ty")}<DateField value={cur.joinedDate} onChange={(v) => setDriver({ joinedDate: v })} /></div>
            <div>{lbl("Thâm niên (tự tính)")}<div style={{ padding: "8px 11px", fontSize: 13.5, fontWeight: 600, color: cur.joinedDate ? "var(--accent)" : "var(--ink-4)", background: "var(--bg)", border: "1px solid var(--line)", borderRadius: 9 }} className="tnum">{tenureLabel(cur.joinedDate) || "—"}</div></div>
          </div>

          {/* tài khoản ngân hàng (nhiều) */}
          <div>
            {lbl("Tài khoản ngân hàng")}
            <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
              {(cur.banks || []).map((b, i) => (
                <div key={i} style={{ display: "flex", flexDirection: "column", gap: 7, padding: 9, border: "1px solid var(--line)", borderRadius: 9, background: "#fafbfc" }}>
                  {/* dòng 1: chọn NH (rộng cả hàng) + xóa — tên NH dài nên cho riêng 1 dòng */}
                  <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                    <div style={{ flex: 1, minWidth: 0 }}><BankPicker value={b.bank} bin={b.bin} banks={banks} onPick={(np) => setBank(i, np)} /></div>
                    {delBtn(() => delBank(i), "Xóa tài khoản")}
                  </div>
                  {/* dòng 2: số TK + chủ TK */}
                  <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8 }}>
                    <Txt value={b.number} onChange={(v) => setBank(i, { number: v })} placeholder="Số tài khoản" />
                    <Txt value={b.holder} onChange={(v) => setBank(i, { holder: v })} placeholder="Chủ tài khoản" />
                  </div>
                </div>
              ))}
              <button type="button" onClick={addBank} style={{ alignSelf: "flex-start", display: "inline-flex", alignItems: "center", gap: 6, padding: "5px 10px", fontSize: 12.5, fontWeight: 600, border: "none", borderRadius: 7, background: "var(--accent-weak)", color: "var(--accent)", cursor: "pointer" }}><I.plus /> Thêm tài khoản</button>
            </div>
          </div>

          {/* tài liệu (CCCD / bằng lái) */}
          <div>
            {lbl("Tài liệu (CCCD, bằng lái — ảnh hoặc file)")}
            {!cur.id && <div style={{ fontSize: 12, color: "var(--warn)", marginBottom: 6 }}>Bấm <b>Lưu mục này</b> để tạo lái xe trước, rồi mới tải tài liệu lên.</div>}
            <div style={{ display: "flex", gap: 8, alignItems: "center", marginBottom: 8, flexWrap: "wrap" }}>
              <select value={docType} onChange={(e) => setDocType(e.target.value)} style={{ padding: "7px 10px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, background: "#fff" }}>
                {DOC_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
              </select>
              <input ref={fileRef} type="file" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv" onChange={onPickFiles} style={{ display: "none" }} />
              <button type="button" onClick={() => fileRef.current && fileRef.current.click()} disabled={!cur.id || busy}
                style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 12px", fontSize: 13, fontWeight: 600, border: "1px dashed var(--accent)", borderRadius: 9, background: "var(--accent-weak-2)", color: cur.id ? "var(--accent)" : "var(--ink-4)", cursor: cur.id && !busy ? "pointer" : "default" }}>
                <I.plus /> {busy ? "Đang tải…" : "Chọn nhiều file/ảnh"}
              </button>
            </div>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(140px, 1fr))", gap: 10 }}>
              {(cur.docs || []).map((doc, di) => (
                <div key={di} style={{ border: "1px solid var(--line)", borderRadius: 10, overflow: "hidden", background: "#fafbfc" }}>
                  <a href={doc.url} target="_blank" rel="noreferrer" style={{ height: 96, background: "#fff", display: "grid", placeItems: "center", overflow: "hidden" }}>
                    {doc.isImage
                      ? <img src={doc.url} alt={doc.name} style={{ width: "100%", height: "100%", objectFit: "cover" }} />
                      : <span style={{ fontSize: 30, color: "var(--ink-4)" }}><i className="bi bi-file-earmark-text" /></span>}
                  </a>
                  <div style={{ padding: "6px 8px", display: "flex", alignItems: "center", gap: 6 }}>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ fontSize: 11, fontWeight: 600, color: "var(--accent)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{doc.type}</div>
                      <div style={{ fontSize: 10.5, color: "var(--ink-4)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }} title={doc.name}>{doc.name}</div>
                    </div>
                    <button type="button" onClick={() => delDoc(doc.id)} title="Xóa tài liệu" style={{ width: 24, height: 24, flexShrink: 0, display: "grid", placeItems: "center", border: "none", borderRadius: 6, background: "transparent", color: "var(--ink-4)", cursor: "pointer" }}
                      onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
                      onMouseLeave={(e) => { e.currentTarget.style.background = "transparent"; e.currentTarget.style.color = "var(--ink-4)"; }}><I.trash /></button>
                  </div>
                </div>
              ))}
              {!(cur.docs || []).length && <div style={{ gridColumn: "1 / -1", padding: "14px 4px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có tài liệu nào.</div>}
            </div>
          </div>
        </div>
      ) : (
        <div style={{ display: "grid", placeItems: "center", color: "var(--ink-4)", fontSize: 13.5 }}>Chọn hoặc thêm một lái xe để xem hồ sơ.</div>
      )}
    </div>
  );
}
