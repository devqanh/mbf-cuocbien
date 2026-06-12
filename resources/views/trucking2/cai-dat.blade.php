@extends('layouts.app')
@section('title', 'Cài đặt — Trucking')

@push('styles')
@include('trucking2.partials._styles')
@endpush

@section('content')
<div id="trk-root"></div>
<script>
window.__TRK = {
  csrf: '{{ csrf_token() }}',
  canEdit: {{ $canEdit ? 'true' : 'false' }},
  canDelete: {{ $canDelete ? 'true' : 'false' }},
  routes: {
    catalog: '{{ url("trucking-v2/catalog") }}/',
    customers: '{{ route("trucking2.customers.save") }}',
    customerRename: '{{ route("trucking2.customerRename") }}',
    vehicles: '{{ route("trucking2.vehicles.save") }}',
    settings: '{{ route("trucking2.settings.save") }}',
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
@include('trucking2.partials._runtime')
@verbatim
<script type="text/babel" data-presets="react">
(() => {
const { useState, useEffect } = React;
const { I } = window.__lib;
const { ConfigBody } = window.__pop;

// Mỗi tab (danh mục) lưu độc lập — gửi đúng key liên quan của tab đó
const CAT_KEYS = {
  locations: ["locations", "locationCode"],
  customers: ["customers", "customerInfo"],
  contTypes: ["contTypes"],
  warehouses: ["warehouses"],
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

function SettingsApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const DEFAULT_CFG = { locations: [], locationCode: {}, locationLocked: [], customers: [], customerInfo: {}, contTypes: [], warehouses: [], payers: [], costItems: [], choHoItems: [], revItems: [], vehicles: [], vehicleType: {}, drivers: [], vehItems: [], prices: {}, costColors: {}, vatDefault: { hph: "8", icd: "0" }, freeTimeHours: "4" };
  const api = (method, url, body) => fetch(url, { method, headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": T.csrf }, body: body ? JSON.stringify(body) : undefined }).then((r) => r.json());
  const [cfg, setCfgState] = useState(() => ({ ...DEFAULT_CFG, ...(B.cfg || {}) }));
  const [sel, setSel] = useState("locations");
  const [dirty, setDirty] = useState({});   // { catKey: true }
  const [saving, setSaving] = useState(false);

  // Sửa tay → cập nhật state + đánh dấu TAB hiện tại có thay đổi (KHÔNG tự lưu)
  const setCfgKey = (key, val) => { setCfgState((c) => ({ ...c, [key]: val })); setDirty((d) => ({ ...d, [sel]: true })); };

  // Lưu RIÊNG tab đang chọn — chỉ gửi các key của tab đó
  const saveCat = () => {
    if (!dirty[sel] || saving) return;
    setSaving(true);
    const keys = CAT_KEYS[sel] || [sel];
    const partial = {};
    keys.forEach((k) => { partial[k] = cfg[k]; });
    // Tab Khách hàng: KHÔNG gửi priceList (bảng giá quản lý ở trang Bảng giá) để không ghi đè
    if (sel === "customers" && partial.customerInfo) {
      const ci = {};
      Object.keys(partial.customerInfo).forEach((n) => { const o = partial.customerInfo[n] || {}; const rest = { ...o }; delete rest.priceList; ci[n] = rest; });
      partial.customerInfo = ci;
    }
    // Mỗi danh mục → endpoint riêng (1 bảng)
    const url = sel === "customers" ? ROUTES.customers
      : sel === "vehicles" ? ROUTES.vehicles
      : (sel === "__vat" || sel === "__freetime") ? ROUTES.settings
      : ROUTES.catalog + sel;
    api("PUT", url, { cfg: partial }).then((r) => { setSaving(false); if (r && r.ok) setDirty((d) => ({ ...d, [sel]: false })); }).catch(() => setSaving(false));
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
          <ConfigBody cfg={cfg} setCfg={setCfgKey} sel={sel} setSel={setSel} dirty={!!dirty[sel]} saving={saving} onSave={saveCat} dirtyMap={dirty} />
        </div>
      </div>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("trk-root")).render(<SettingsApp />);
})();
</script>
@endverbatim
@endpush
