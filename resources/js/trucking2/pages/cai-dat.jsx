import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useEffect } = React;
import { I } from "@trk/lib.jsx";
import { ConfigBody } from "@trk/pop.jsx";

// Mỗi tab (danh mục) lưu độc lập — gửi đúng key liên quan của tab đó
const CAT_KEYS = {
  locations: ["locations", "locationCodeArr"],
  customers: ["customers", "customerInfo"],
  contTypes: ["contTypes"],
  warehouses: ["warehouses", "warehouseCodeArr"],
  payers: ["payers"],
  costItems: ["costItems", "prices", "costColors"],
  choHoItems: ["choHoItems", "prices"],
  revItems: ["revItems", "prices"],
  vehicles: ["vehicles", "vehicleType"],
  drivers: ["drivers"],
  vehItems: ["vehItems", "prices"],
  __vat: ["vatDefault"],
  __freetime: ["freeTimeHours"],
};

// Nhãn tab (cho hộp xác nhận)
const TAB_LABELS = {
  locations: "Địa điểm", customers: "Khách hàng", contTypes: "Loại cont", warehouses: "Kho",
  payers: "Bên thanh toán", costItems: "Khoản chi phí", choHoItems: "Khoản chi hộ", revItems: "Khoản doanh thu",
  vehicles: "Đội xe", drivers: "Tài xế", vehItems: "Chi phí xe", __vat: "VAT mặc định", __freetime: "Free time",
};
const esc = (s) => String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

function SettingsApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const DEFAULT_CFG = { locations: [], locationCode: {}, locationCodeArr: [], locationLocked: [], customers: [], customerInfo: {}, contTypes: [], warehouses: [], warehouseCode: {}, warehouseCodeArr: [], payers: [], costItems: [], choHoItems: [], revItems: [], vehicles: [], vehicleType: {}, drivers: [], vehItems: [], prices: {}, costColors: {}, vatDefault: { hph: "8", icd: "0" }, freeTimeHours: "4" };
  const api = (method, url, body) => fetch(url, { method, headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": T.csrf }, body: body ? JSON.stringify(body) : undefined }).then((r) => r.json());
  const [cfg, setCfgState] = useState(DEFAULT_CFG);
  const [counts, setCounts] = useState(B.counts || {});   // badge sidebar (boot, nhẹ)
  const [sel, setSel] = useState("locations");
  const [dirty, setDirty] = useState({});   // { catKey: true }
  const [saving, setSaving] = useState(false);
  const [loadingTab, setLoadingTab] = useState(null);
  const loaded = React.useRef(new Set());

  // Nạp dữ liệu TƯƠI của 1 tab từ server (bỏ qua mọi guard — dùng cho lần đầu & sau khi lưu).
  const fetchTab = (tab) => {
    setLoadingTab(tab);
    api("GET", ROUTES.catalog + tab).then((r) => {
      if (r && r.ok) {
        setCfgState((c) => ({ ...c, ...(r.cfg || {}) }));
        if (Array.isArray((r.cfg || {})[tab])) setCounts((m) => ({ ...m, [tab]: r.cfg[tab].length }));
        loaded.current.add(tab);
      }
      setLoadingTab((t) => (t === tab ? null : t));
    }).catch(() => setLoadingTab((t) => (t === tab ? null : t)));
  };
  // Lazy-load khi cần: ĐANG sửa dở thì KHÔNG nạp đè; đã tải rồi & không ép thì giữ.
  const loadTab = (tab, force) => {
    if (dirty[tab]) return;                          // bảo vệ chỉnh sửa chưa lưu
    if (!force && loaded.current.has(tab)) return;
    fetchTab(tab);
  };
  // Đổi tab = chọn + nạp TƯƠI tab đó (mỗi lần mở lấy dữ liệu mới → an toàn khi nhiều người sửa)
  const selectTab = (tab) => { setSel(tab); loadTab(tab, true); };
  useEffect(() => { fetchTab(sel); }, []);   // tải tab đầu khi mở trang

  // Sửa tay → cập nhật state + đánh dấu TAB hiện tại có thay đổi (KHÔNG tự lưu)
  const setCfgKey = (key, val) => { setCfgState((c) => ({ ...c, [key]: val })); setDirty((d) => ({ ...d, [sel]: true })); };

  // Lưu RIÊNG tab đang chọn — chỉ gửi các key của tab đó. HỎI xác nhận trước khi lưu;
  // cảnh báo mạnh nếu phát hiện đang XÓA bớt mục (xóa khách hàng sẽ kéo theo bảng giá).
  const saveCat = async () => {
    if (!dirty[sel] || saving) return;
    const cur = sel;
    const label = TAB_LABELS[cur] || "mục này";
    const before = counts[cur];
    const now = Array.isArray(cfg[cur]) ? (cfg[cur] || []).length : null;
    const removed = (typeof before === "number" && now != null) ? Math.max(0, before - now) : 0;

    let title = "Lưu thay đổi?";
    let text = `Lưu thay đổi cho danh mục <b>${esc(label)}</b>? Thao tác sẽ ghi đè dữ liệu danh mục này trên hệ thống.`;
    let confirmText = '<i class="bi bi-save me-1"></i> Lưu';
    if (removed > 0) {
      title = "Xác nhận xóa & lưu?";
      text = cur === "customers"
        ? `Bạn sắp xóa <b>${removed}</b> khách hàng — kèm theo <b>toàn bộ bảng giá</b> của những khách đó. Không thể hoàn tác.`
        : `Bạn sắp xóa <b>${removed}</b> mục khỏi danh mục <b>${esc(label)}</b>. Không thể hoàn tác.`;
      confirmText = '<i class="bi bi-trash me-1"></i> Xóa & lưu';
    }
    const ok = await window.confirmAction({ title, text, confirmText, danger: removed > 0 });
    if (!ok) return;

    setSaving(true);
    const keys = CAT_KEYS[cur] || [cur];
    const partial = {};
    keys.forEach((k) => { partial[k] = cfg[k]; });
    // Tab Khách hàng: KHÔNG gửi priceList (bảng giá quản lý ở trang Bảng giá) để không ghi đè
    if (cur === "customers" && partial.customerInfo) {
      const ci = {};
      Object.keys(partial.customerInfo).forEach((n) => { const o = partial.customerInfo[n] || {}; const rest = { ...o }; delete rest.priceList; ci[n] = rest; });
      partial.customerInfo = ci;
    }
    // Mỗi danh mục → endpoint riêng (1 bảng)
    const url = cur === "customers" ? ROUTES.customers
      : cur === "vehicles" ? ROUTES.vehicles
      : (cur === "__vat" || cur === "__freetime") ? ROUTES.settings
      : ROUTES.catalog + cur;
    api("PUT", url, { cfg: partial }).then((r) => {
      setSaving(false);
      if (r && r.ok) {
        setDirty((d) => ({ ...d, [cur]: false }));
        window.trkToast && window.trkToast("Đã lưu");
        fetchTab(cur);   // nạp lại bản TƯƠI từ server (gộp thay đổi người khác, xác nhận đã lưu)
      } else { window.trkToast && window.trkToast("Lưu thất bại", "error"); }
    }).catch(() => { setSaving(false); window.trkToast && window.trkToast("Lỗi kết nối khi lưu", "error"); });
  };

  const anyDirty = Object.values(dirty).some(Boolean);
  useEffect(() => {
    const h = (e) => { if (anyDirty) { e.preventDefault(); e.returnValue = ""; } };
    window.addEventListener("beforeunload", h);
    return () => window.removeEventListener("beforeunload", h);
  }, [anyDirty]);

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 14, height: 58 }}>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center" }}><I.cog /></div>
          <div style={{ fontSize: 15.5, fontWeight: 700, letterSpacing: "-0.01em" }}>Cài đặt dữ liệu danh mục</div>
          <div style={{ flex: 1 }} />
          {anyDirty && <span style={{ display: "inline-flex", alignItems: "center", gap: 6, fontSize: 12.5, fontWeight: 600, color: "var(--warn)" }}><span style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} /> Có tab chưa lưu</span>}
          <span style={{ fontSize: 12, color: "var(--ink-4)" }}>Mỗi mục lưu riêng bằng nút trong mục</span>
        </div>
      </header>
      <div style={{ flex: 1, minHeight: 0, overflow: "auto", padding: "20px 22px 40px" }}>
        <div style={{ maxWidth: 1100, margin: "0 auto", background: "#fff", border: "1px solid var(--line)", borderRadius: 14, padding: "6px 22px 22px" }}>
          <ConfigBody cfg={cfg} setCfg={setCfgKey} sel={sel} setSel={selectTab} dirty={!!dirty[sel]} saving={saving} onSave={saveCat} dirtyMap={dirty} counts={counts} loading={loadingTab === sel} />
        </div>
      </div>
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<SettingsApp />);

