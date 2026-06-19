import React from "react";
const { useState } = React;
import { I, useIsMobile } from "@trk/lib.jsx";
import { Field } from "../shared.jsx";

/* ===================== CUSTOMER MANAGER (master-detail) ===================== */

const CUST_FIELDS = [
  { k: "shortName", label: "Tên viết tắt", ph: "VD: Canon" },
  { k: "taxCode", label: "Mã số thuế", ph: "VD: 0101234567" },
  { k: "phone", label: "Điện thoại", ph: "VD: 024 1234 5678" },
  { k: "contact", label: "Người liên hệ", ph: "VD: Chị Hồng — KT" },
  { k: "email", label: "Email", ph: "VD: ketoan@canon.vn" },
];

export function CustomerManager({ cfg, setCfg }) {
  const isMobile = useIsMobile();
  const customers = cfg.customers || [];
  const info = cfg.customerInfo || {};
  const [sel, setSel] = useState(customers[0] || null);
  const [draft, setDraft] = useState("");
  const cur = sel != null && customers.includes(sel) ? sel : (customers[0] || null);
  const data = (cur && info[cur]) || {};
  const setField = (k, v) => setCfg("customerInfo", { ...info, [cur]: { ...data, [k]: v } });
  const T = window.__TRK || {}; const ROUTES = T.routes || {};
  const [nameDraft, setNameDraft] = useState(cur || "");
  React.useEffect(() => { setNameDraft(cur || ""); }, [cur]);
  // Chuẩn hóa tên: nếu gõ HOA hết hoặc THƯỜNG hết → Title Case (hoa chữ đầu mỗi từ).
  // Nếu đã canh hoa/thường LẪN (vd "Wolong Electric VN") → giữ nguyên, không phá viết tắt.
  const smartName = (s) => {
    const str = (s || "").trim().replace(/\s+/g, " ");
    if (!str) return "";
    const up = str.toLocaleUpperCase("vi"), lo = str.toLocaleLowerCase("vi");
    if (str !== up && str !== lo) return str;
    return str.split(" ").map((w) => (w ? w.charAt(0).toLocaleUpperCase("vi") + w.slice(1).toLocaleLowerCase("vi") : w)).join(" ");
  };
  const dupName = (n, exclude) => customers.some((c) => c !== exclude && c.toLowerCase() === n.toLowerCase());
  // Đổi tên khách (server update theo id — giữ liên kết lô & bảng giá), rồi rekey cfg cục bộ
  const renameCustomer = async () => {
    if (!cur) return;
    const nn = smartName(nameDraft);
    if (!nn) { window.trkToast && window.trkToast("Tên khách hàng không được để trống", "error"); return; }
    if (nn === cur) { setNameDraft(cur); return; }
    if (dupName(nn, cur)) { window.trkToast && window.trkToast("Tên khách hàng đã tồn tại", "error"); return; }
    if (!ROUTES.customerRename) return;
    try {
      const res = await fetch(ROUTES.customerRename, { method: "PUT", headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": T.csrf }, body: JSON.stringify({ old: cur, new: nn }) }).then((r) => r.json());
      if (res && res.ok) {
        setCfg("customers", customers.map((c) => (c === cur ? nn : c)));
        const ni = { ...info }; ni[nn] = ni[cur] || {}; if (nn !== cur) delete ni[cur]; setCfg("customerInfo", ni);
        setSel(nn); setNameDraft(nn);
        window.trkToast && window.trkToast("Đã đổi tên khách hàng");
      } else { window.trkToast && window.trkToast((res && res.message) || "Đổi tên lỗi", "error"); }
    } catch (e) { window.trkToast && window.trkToast("Lỗi kết nối khi đổi tên", "error"); }
  };
  const add = () => {
    const n = smartName(draft);
    if (!n) { window.trkToast && window.trkToast("Vui lòng nhập tên khách hàng", "error"); return; }
    if (dupName(n)) { window.trkToast && window.trkToast(`Khách hàng "${n}" đã tồn tại`, "error"); return; }
    setCfg("customers", [...customers, n]); setSel(n); setDraft("");
  };
  const remove = (name) => {
    setCfg("customers", customers.filter((c) => c !== name));
    const ni = { ...info }; delete ni[name]; setCfg("customerInfo", ni);
    if (cur === name) setSel(customers.filter((c) => c !== name)[0] || null);
  };
  const inp = (val, onCh, ph) => (
    <input value={val || ""} onChange={(e) => onCh(e.target.value)} placeholder={ph}
      style={{ width: "100%", padding: "8px 11px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
      onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
  );
  return (
    <div style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr" : "176px 1fr", gap: 16, minHeight: isMobile ? 0 : 360 }}>
      {/* customer list */}
      <div style={{ borderRight: isMobile ? "none" : "1px solid var(--line-2)", borderBottom: isMobile ? "1px solid var(--line-2)" : "none", paddingRight: isMobile ? 0 : 12, paddingBottom: isMobile ? 12 : 0, display: "flex", flexDirection: "column", minHeight: 0 }}>
        <div style={{ display: "flex", gap: 6, marginBottom: 8 }}>
          <input value={draft} onChange={(e) => setDraft(e.target.value)} placeholder="Tên khách hàng *…"
            onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); add(); } }}
            style={{ flex: 1, minWidth: 0, padding: "7px 9px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, outline: "none" }}
            onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => { e.target.style.borderColor = "var(--line)"; if (draft.trim()) setDraft(smartName(draft)); }} />
          <button type="button" onClick={add} title="Thêm khách hàng"
            style={{ width: 32, flexShrink: 0, display: "grid", placeItems: "center", border: "none", borderRadius: 8, background: "var(--accent)", color: "#fff", cursor: "pointer" }}><I.plus /></button>
        </div>
        <div style={{ overflowY: "auto", display: "flex", flexDirection: "column", gap: 1 }}>
          {customers.map((name) => {
            const active = cur === name;
            return (
              <button key={name} type="button" onClick={() => setSel(name)}
                style={{ textAlign: "left", border: "none", cursor: "pointer", borderRadius: 8, padding: "8px 10px", fontSize: 13.5, fontWeight: active ? 600 : 400,
                  background: active ? "var(--accent-weak)" : "transparent", color: active ? "var(--accent)" : "var(--ink)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}
                onMouseEnter={(e) => { if (!active) e.currentTarget.style.background = "var(--line-2)"; }}
                onMouseLeave={(e) => { if (!active) e.currentTarget.style.background = "transparent"; }}>
                {name}
              </button>
            );
          })}
          {!customers.length && <div style={{ padding: "16px 4px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có khách hàng.</div>}
        </div>
      </div>

      {/* detail */}
      {cur ? (
        <div>
          <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 6 }}>
            <div style={{ fontSize: 11, fontWeight: 700, color: "var(--ink-4)", textTransform: "uppercase", letterSpacing: "0.04em" }}>Tên khách hàng <span style={{ color: "var(--danger)" }}>*</span></div>
            <div style={{ flex: 1 }} />
            <button type="button" onClick={() => remove(cur)} title="Xóa khách hàng"
              style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "6px 11px", fontSize: 12.5, fontWeight: 500, border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-3)", cursor: "pointer" }}
              onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; e.currentTarget.style.borderColor = "#f3c9c9"; }}
              onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-3)"; e.currentTarget.style.borderColor = "var(--line)"; }}>
              <I.trash /> Xóa
            </button>
          </div>
          <div style={{ display: "flex", gap: 8, alignItems: "center", marginBottom: 14 }}>
            <input value={nameDraft} onChange={(e) => setNameDraft(e.target.value)} placeholder="Tên khách hàng…"
              onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); renameCustomer(); } }}
              style={{ flex: 1, padding: "9px 12px", fontSize: 15, fontWeight: 700, border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
              onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => { e.target.style.borderColor = "var(--line)"; if (nameDraft.trim()) setNameDraft(smartName(nameDraft)); }} />
            {(() => { const can = !!(nameDraft && nameDraft.trim() && nameDraft.trim() !== cur); return (
              <button type="button" onClick={renameCustomer} disabled={!can}
                style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "9px 16px", fontSize: 13.5, fontWeight: 600, border: "none", borderRadius: 10, whiteSpace: "nowrap", cursor: can ? "pointer" : "default", color: can ? "#fff" : "var(--ink-4)", background: can ? "var(--accent)" : "var(--line-2)" }}>
                <I.check /> Cập nhật tên
              </button>
            ); })()}
          </div>
          <div style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr" : "1fr 1fr", gap: 12 }}>
            {CUST_FIELDS.map((f) => (
              <Field key={f.k} label={f.label}>{inp(data[f.k], (v) => setField(f.k, v), f.ph)}</Field>
            ))}
            <Field label="Hạn thanh toán mặc định">
              <div style={{ position: "relative", width: 130 }}>
                <input inputMode="numeric" value={data.termDays || ""} onChange={(e) => setField("termDays", e.target.value.replace(/[^\d]/g, ""))} placeholder="VD: 30" className="tnum"
                  style={{ width: "100%", padding: "8px 38px 8px 11px", fontSize: 13.5, textAlign: "right", border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
                  onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
                <span style={{ position: "absolute", right: 10, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 12.5, pointerEvents: "none" }}>ngày</span>
              </div>
            </Field>
          </div>
          <div style={{ marginTop: 12 }}>
            <Field label="Địa chỉ">{inp(data.address, (v) => setField("address", v), "Địa chỉ xuất hóa đơn…")}</Field>
          </div>
          <div style={{ marginTop: 12 }}>
            <Field label="Ghi chú">
              <textarea value={data.note || ""} onChange={(e) => setField("note", e.target.value)} placeholder="Ghi chú về khách hàng, điều khoản riêng…" rows={3}
                style={{ width: "100%", padding: "8px 11px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 9, outline: "none", resize: "vertical", fontFamily: "inherit" }}
                onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
            </Field>
          </div>
          <div style={{ marginTop: 14, fontSize: 11.5, color: "var(--ink-4)" }}>Tên khách hàng là khóa liên kết với lô hàng. Bảng giá đã gửi quản lý ở trang <b style={{ color: "var(--ink-3)" }}>Bảng giá</b>.</div>
        </div>
      ) : (
        <div style={{ display: "grid", placeItems: "center", color: "var(--ink-4)", fontSize: 13.5 }}>Chọn hoặc thêm một khách hàng để xem chi tiết.</div>
      )}
    </div>
  );
}
