@extends('layouts.app')
@section('title', 'Lô hàng — Trucking')

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
    shipmentStore: '{{ route("trucking2.shipments.store") }}',
    shipment: '{{ url("trucking-v2/shipments") }}/',
    catalog: '{{ url("trucking-v2/catalog") }}/',
    customers: '{{ route("trucking2.customers.save") }}',
    vehicles: '{{ route("trucking2.vehicles.save") }}',
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
@include('trucking2.partials._runtime')
@verbatim
<script type="text/babel" data-presets="react">
(() => {
const { useState, useMemo, useEffect, useRef } = React;
const { I, fmtVND, fmtShort, fmtDate, calcCost, calcVeh, calcRev, calcVehICD, calcRevICD, calcFreeTime, fmtHours, toNum } = window.__lib;
const { CostPopup, RevenuePopup, RevenuePopupICD, InfoPopup, colorHex } = window.__pop;
const { SortBtn, CellBtn, Badge, EditCell, TH, TD } = window.__ui;

function ShipmentsApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const DEFAULT_CFG = { locations: [], locationCode: {}, customers: [], customerInfo: {}, contTypes: [], warehouses: [], payers: [], costItems: [], choHoItems: [], revItems: [], vehicles: [], vehicleType: {}, drivers: [], vehItems: [], prices: {}, costColors: {}, vatDefault: { hph: "8", icd: "0" }, freeTimeHours: "4" };
  const api = (method, url, body) => fetch(url, { method, headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": T.csrf }, body: body ? JSON.stringify(body) : undefined }).then((r) => r.json());

  // Dùng chung 1 mẫu (ICD) — không còn tách HPH/ICD
  const sheet = "icd";
  const isHph = false;
  const [icd, setIcd] = useState(B.icd || []);
  const [cfg, setCfgState] = useState(() => ({ ...DEFAULT_CFG, ...(B.cfg || {}) }));
  const [modal, setModal] = useState(null);
  const [q, setQ] = useState("");
  const [filter, setFilter] = useState("all");
  // Bộ lọc theo "follow": 'all' | 'any' | 'missing' | '#hex' (lọc theo màu cụ thể)
  const [followFilter, setFollowFilter] = useState("all");
  const [sort, setSort] = useState({ key: "default", dir: 1 });
  const [showExport, setShowExport] = useState(false);
  const [expFrom, setExpFrom] = useState("");
  const [expTo, setExpTo] = useState("");
  const ships = icd;
  const setShips = setIcd;

  // Cắt máng: datetime-local "YYYY-MM-DDTHH:MM" → "dd/mm/yyyy HH:MM" (giữ nguyên nếu là text cũ)
  const fmtCM = (v) => { v = v || ""; const m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(v); return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : v; };

  // Lưu danh mục thêm nhanh trong popup → đúng endpoint của từng bảng (debounce theo key)
  const catTimer = useRef({});
  const saveCatalogKey = (key, n) => {
    clearTimeout(catTimer.current[key]);
    catTimer.current[key] = setTimeout(() => {
      let url, partial;
      if (key === "customers") {
        const ci = {}; Object.keys(n.customerInfo || {}).forEach((nm) => { const o = { ...(n.customerInfo[nm] || {}) }; delete o.priceList; ci[nm] = o; });
        url = ROUTES.customers; partial = { customers: n.customers, customerInfo: ci };
      } else if (key === "vehicles") {
        url = ROUTES.vehicles; partial = { vehicles: n.vehicles, vehicleType: n.vehicleType };
      } else {
        url = ROUTES.catalog + key; partial = { [key]: n[key], locationCode: n.locationCode, prices: n.prices };
      }
      if (url) api("PUT", url, { cfg: partial });
    }, 700);
  };
  const addCfg = (key, v) => setCfgState((c) => { if ((c[key] || []).includes(v)) return c; const n = { ...c, [key]: [...(c[key] || []), v] }; saveCatalogKey(key, n); return n; });

  // persist 1 lô (debounce theo id)
  const shipTimers = useRef({});
  const saveShip = (ship, sheetName) => {
    clearTimeout(shipTimers.current[ship.id]);
    shipTimers.current[ship.id] = setTimeout(() => api("PUT", ROUTES.shipment + ship.id, { sheet: sheetName, ship }), 600);
  };
  const patch = (id, np) => {
    const cur = ships.find((s) => s.id === id);
    if (cur) saveShip({ ...cur, ...np }, sheet);
    setShips((s) => s.map((sh) => (sh.id === id ? { ...sh, ...np } : sh)));
  };
  const active = modal ? ships.find((s) => s.id === modal.id) : null;

  const today = new Date(); today.setHours(0, 0, 0, 0);
  const metrics = (s) => {
    const cost = calcCost(s.cost).tongChiPhi;
    const r = isHph ? calcRev(s.rev) : calcRevICD(s.rev);
    const profit = (r.phaiThu - r.vat) - cost;
    const overdue = r.conNo > 0 && s.rev?.hanTT && new Date(s.rev.hanTT) < today;
    return { cost, r, profit, overdue };
  };

  // Helpers cho follow filter — màu lấy từ danh mục (cfg.costColors), không từ dòng phí
  const costColors = cfg.costColors || {};
  const shipFollowItems = (s) => ((s.cost && s.cost.items) || []).filter((it) => it.item && costColors[it.item]);
  const hasFollowAny     = (s) => shipFollowItems(s).length > 0;
  const hasFollowMissing = (s) => shipFollowItems(s).some((it) => !toNum(it.amount));
  const hasFollowColor   = (s, hex) => shipFollowItems(s).some((it) => colorHex(costColors[it.item]) === hex);

  // Thống kê theo màu: { hex, total (ships có màu), miss (ships có màu + chưa điền) }
  const followStats = useMemo(() => {
    const buckets = new Map();
    let missShips = 0, anyShips = 0;
    ships.forEach((s) => {
      const its = shipFollowItems(s);
      if (!its.length) return;
      anyShips++;
      if (its.some((it) => !toNum(it.amount))) missShips++;
      const seen = new Map(); // hex → has-missing-in-this-ship
      its.forEach((it) => {
        const hex = colorHex(costColors[it.item]); if (!hex) return;
        const miss = !toNum(it.amount);
        if (!seen.has(hex)) seen.set(hex, miss);
        else if (miss) seen.set(hex, true);
      });
      seen.forEach((miss, hex) => {
        if (!buckets.has(hex)) buckets.set(hex, { hex, total: 0, miss: 0 });
        const b = buckets.get(hex); b.total++; if (miss) b.miss++;
      });
    });
    const byColor = [...buckets.values()].sort((a, b) => (b.miss - a.miss) || (b.total - a.total));
    return { anyShips, missShips, byColor };
  }, [ships, costColors]);

  const rows = useMemo(() => {
    const t = q.trim().toLowerCase();
    let list = ships.filter((s) => !t || [s.customer, s.booking, s.inv, s.contNo, s.kho].join(" ").toLowerCase().includes(t));
    if (filter !== "all") list = list.filter((s) => {
      const out = !!(s.bksRa && s.bksRa.trim());
      if (filter === "out") return out;
      if (filter === "notout") return !out;
      return true;
    });
    if (followFilter !== "all") {
      if (followFilter === "any") list = list.filter(hasFollowAny);
      else if (followFilter === "missing") list = list.filter(hasFollowMissing);
      else if (typeof followFilter === "string" && followFilter.startsWith("#")) list = list.filter((s) => hasFollowColor(s, followFilter));
    }
    if (sort.key !== "default") {
      list = [...list].sort((a, b) => {
        const ma = metrics(a), mb = metrics(b);
        if (sort.key === "customer") return (a.customer || "").localeCompare(b.customer || "") * sort.dir;
        let va, vb;
        if (sort.key === "cost") { va = ma.cost; vb = mb.cost; }
        else if (sort.key === "thu") { va = ma.r.phaiThu; vb = mb.r.phaiThu; }
        else if (sort.key === "no") { va = ma.r.conNo; vb = mb.r.conNo; }
        else if (sort.key === "profit") { va = ma.profit; vb = mb.profit; }
        return (va - vb) * sort.dir;
      });
    }
    return list;
  }, [ships, q, filter, followFilter, sort, sheet]);

  const totals = useMemo(() => ships.reduce((a, s) => {
    const m = metrics(s);
    a.cost += m.cost; a.rev += m.r.tongDT; a.thu += m.r.phaiThu; a.no += Math.max(0, m.r.conNo); a.profit += m.profit;
    a.overdue += m.overdue ? 1 : 0;
    return a;
  }, { cost: 0, rev: 0, thu: 0, no: 0, profit: 0, overdue: 0 }), [ships, sheet]);

  const addRow = async () => {
    const vat = (cfg.vatDefault || {})[sheet] || (isHph ? "8" : "0");
    const base = isHph
      ? { customer: "", booking: "", io: "Xuất", qty: 1, contType: "40HC", contNo: "", from: "", to: "", sailDate: "", cutOff: "", cost: { items: [] }, rev: { vatRate: vat, doanhThu: [], choHo: [], payments: [] } }
      : { customer: "", booking: "", io: "Nhập", contNo: "", contType: "40HC", kho: "", bksVao: "", bksRa: "", from: "ICD Quế Võ", to: "", contDen: "", contRa: "", cost: { items: [] }, rev: { vatRate: vat, doanhThu: [], choHo: [], payments: [] } };
    const res = await api("POST", ROUTES.shipmentStore, { sheet, ship: base });
    if (res && res.ok) { setShips((x) => [...x, res.ship]); setModal({ id: res.ship.id, type: "info" }); }
  };

  const delShip = async (id) => {
    const s = ships.find((x) => x.id === id);
    const label = (s && (s.contNo || s.booking)) || ("#" + id);
    const esc = (t) => String(t == null ? "" : t).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
    const ok = await window.confirmAction({
      title: "Xóa lô hàng?",
      text: `Lô <b>${esc(label)}</b> sẽ bị xóa cùng toàn bộ chi phí, doanh thu, thanh toán. Không thể hoàn tác.`,
      confirmText: '<i class="bi bi-trash me-1"></i> Xóa lô hàng',
      danger: true,
    });
    if (!ok) return;
    const res = await api("DELETE", ROUTES.shipment + id);
    if (res && res.ok) { setShips((x) => x.filter((y) => y.id !== id)); setModal(null); }
  };

  // Xuất Excel (client-side) — các cột yêu cầu + MST/email lấy từ khách hàng; lọc theo NGÀY KẾ HOẠCH (Giờ đến kế hoạch)
  const exportExcel = () => {
    if (typeof XLSX === "undefined") { window.alert("Thư viện Excel chưa tải xong, thử lại sau giây lát."); return; }
    const info = cfg.customerInfo || {};
    const plannedDate = (s) => (s.gioDenDuKien || "").slice(0, 10); // YYYY-MM-DD
    const list = ships.filter((s) => {
      const d = plannedDate(s);
      if (expFrom && (!d || d < expFrom)) return false;
      if (expTo && (!d || d > expTo)) return false;
      return true;
    });
    const cols = ["NHÀ MÁY", "SỐ BOOKING/BILL", "NHẬP/XUẤT", "SỐ LƯỢNG", "LOẠI", "CẮT MÁNG", "NƠI LẤY", "NƠI HẠ", "NGÀY", "GIỜ", "KHO", "INVOICE", "MÃ SỐ THUẾ", "EMAIL CÔNG TY"];
    const data = list.map((s) => {
      const ci = info[s.customer] || {};
      const dt = s.gioDenDuKien || "";
      const ngay = dt.length >= 10 ? dt.slice(0, 10).split("-").reverse().join("/") : "";
      const gio = dt.length >= 16 ? dt.slice(11, 16) : "";
      return { "NHÀ MÁY": s.customer || "", "SỐ BOOKING/BILL": s.booking || "", "NHẬP/XUẤT": s.io || "", "SỐ LƯỢNG": s.qty == null ? "" : s.qty, "LOẠI": s.contType || "", "CẮT MÁNG": fmtCM(s.cutOff), "NƠI LẤY": s.from || "", "NƠI HẠ": s.to || "", "NGÀY": ngay, "GIỜ": gio, "KHO": s.kho || "", "INVOICE": s.inv || "", "MÃ SỐ THUẾ": ci.taxCode || "", "EMAIL CÔNG TY": ci.email || "" };
    });
    const ws = XLSX.utils.json_to_sheet(data, { header: cols });
    ws["!cols"] = cols.map((c) => ({ wch: Math.max(12, c.length + 2) }));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Lô hàng");
    const stamp = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `lo-hang-${stamp}.xlsx`);
    setShowExport(false);
  };

  const toggleSort = (key) => setSort((s) => s.key === key ? { key, dir: -s.dir } : { key, dir: 1 });
  const minW = 880;

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column" }}>
      {/* top bar */}
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 14, height: 58 }}>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center" }}><I.truck /></div>
          <div style={{ fontSize: 15.5, fontWeight: 700, letterSpacing: "-0.01em" }}>Lô hàng</div>
          <div style={{ flex: 1 }} />
          <div style={{ position: "relative" }}>
            <span style={{ position: "absolute", left: 11, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)" }}><I.search /></span>
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Tìm khách, booking, container…"
              style={{ width: 260, padding: "9px 12px 9px 34px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 10, outline: "none", background: "#fafbfc" }}
              onFocus={(e) => { e.target.style.borderColor = "var(--accent)"; e.target.style.background = "#fff"; }}
              onBlur={(e) => { e.target.style.borderColor = "var(--line)"; e.target.style.background = "#fafbfc"; }} />
          </div>
          <div style={{ position: "relative" }}>
            <button type="button" onClick={() => setShowExport((v) => !v)} title="Xuất danh sách lô hàng ra Excel"
              style={{ display: "inline-flex", alignItems: "center", gap: 8, padding: "10px 18px", fontSize: 14, fontWeight: 700, cursor: "pointer", color: "#fff", background: "var(--good)", border: "none", borderRadius: 10, boxShadow: "0 1px 2px rgba(31,138,91,.45)", transition: "background .12s" }}
              onMouseEnter={(e) => (e.currentTarget.style.background = "#1a7350")} onMouseLeave={(e) => (e.currentTarget.style.background = "var(--good)")}>
              <i className="bi bi-file-earmark-excel-fill" style={{ fontSize: 16 }} /> Xuất Excel
            </button>
            {showExport && (
              <>
                <div onClick={() => setShowExport(false)} style={{ position: "fixed", inset: 0, zIndex: 1200 }} />
                <div style={{ position: "absolute", top: "calc(100% + 6px)", right: 0, zIndex: 1201, width: 308, background: "#fff", border: "1px solid var(--line)", borderRadius: 12, boxShadow: "0 12px 32px -8px rgba(16,19,23,.24), 0 2px 8px rgba(16,19,23,.08)", padding: 14 }}>
                  <div style={{ fontSize: 13, fontWeight: 700, color: "var(--ink)", marginBottom: 3 }}>Xuất Excel</div>
                  <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginBottom: 11, lineHeight: 1.5 }}>Lọc theo <b style={{ color: "var(--ink-3)" }}>ngày kế hoạch</b> (Giờ đến kế hoạch). Để trống = xuất tất cả {ships.length} lô.</div>
                  <div style={{ display: "flex", gap: 8, marginBottom: 11 }}>
                    <label style={{ flex: 1 }}><div style={{ fontSize: 11, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>Từ ngày</div>
                      <input type="date" value={expFrom} onChange={(e) => setExpFrom(e.target.value)} style={{ width: "100%", padding: "7px 8px", fontSize: 12.5, border: "1px solid var(--line)", borderRadius: 8, outline: "none", colorScheme: "light" }} /></label>
                    <label style={{ flex: 1 }}><div style={{ fontSize: 11, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>Đến ngày</div>
                      <input type="date" value={expTo} onChange={(e) => setExpTo(e.target.value)} style={{ width: "100%", padding: "7px 8px", fontSize: 12.5, border: "1px solid var(--line)", borderRadius: 8, outline: "none", colorScheme: "light" }} /></label>
                  </div>
                  <button type="button" onClick={exportExcel}
                    style={{ width: "100%", display: "inline-flex", alignItems: "center", justifyContent: "center", gap: 7, padding: "9px 0", fontSize: 13.5, fontWeight: 600, cursor: "pointer", color: "#fff", background: "var(--good)", border: "none", borderRadius: 9 }}>
                    <i className="bi bi-download" /> Tải file Excel
                  </button>
                </div>
              </>
            )}
          </div>
          <button type="button" onClick={addRow}
            style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "9px 15px", fontSize: 13.5, fontWeight: 600, cursor: "pointer", color: "#fff", background: "var(--accent)", border: "none", borderRadius: 10, boxShadow: "0 1px 2px rgba(42,111,219,.4)" }}>
            <I.plus /> Thêm lô hàng
          </button>
        </div>
      </header>

      {/* summary strip */}
      <div style={{ display: "flex", gap: 0, background: "#fff", borderBottom: "1px solid var(--line)", padding: "0 22px", flexShrink: 0, flexWrap: "wrap" }}>
        {[["Lô hàng", ships.length, "ink"], ["Tổng chi phí", fmtVND(totals.cost), "ink"]].map(([k, v, tone], i, arr) => (
          <div key={k} style={{ padding: "13px 26px 13px 0", marginRight: 26, borderRight: i < arr.length - 1 ? "1px solid var(--line-2)" : "none" }}>
            <div style={{ fontSize: 11.5, color: "var(--ink-3)", marginBottom: 3 }}>{k}</div>
            <div className="tnum" style={{ fontSize: 16, fontWeight: 700, color: tone === "warn" ? "var(--warn)" : tone === "good" ? "var(--good)" : "var(--ink)" }}>{v}</div>
          </div>
        ))}
      </div>

      {/* filter bar */}
      <div style={{ display: "flex", alignItems: "center", gap: 10, background: "#fff", borderBottom: "1px solid var(--line)", padding: "10px 22px", flexShrink: 0 }}>
        <span style={{ fontSize: 12.5, color: "var(--ink-3)", fontWeight: 500 }}>Lọc:</span>
        <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 9, padding: 3 }}>
          {[["all", "Tất cả"], ["notout", "Chưa ra"], ["out", "Đã ra"]].map(([k, label]) => {
            const on = filter === k;
            const cnt = k === "all" ? ships.length : ships.filter((s) => { const o = !!(s.bksRa && s.bksRa.trim()); return k === "out" ? o : !o; }).length;
            return (
              <button key={k} type="button" onClick={() => setFilter(k)}
                style={{ display: "inline-flex", alignItems: "center", gap: 6, border: "none", cursor: "pointer", fontSize: 12.5, fontWeight: 600, padding: "6px 13px", borderRadius: 7,
                  background: on ? "#fff" : "transparent", color: on ? (k === "notout" ? "var(--warn)" : "var(--ink)") : "var(--ink-3)", boxShadow: on ? "0 1px 2px rgba(16,19,23,.12)" : "none", transition: "all .12s" }}>
                {label}
                <span className="tnum" style={{ fontSize: 11, fontWeight: 700, color: on ? "var(--ink-3)" : "var(--ink-4)", background: on ? "var(--line-2)" : "transparent", padding: "0 6px", borderRadius: 999, minWidth: 16, textAlign: "center" }}>{cnt}</span>
              </button>
            );
          })}
        </div>
        {/* Theo dõi (follow color) — chỉ hiện khi có ít nhất 1 lô gắn follow */}
        {followStats.anyShips > 0 && (() => {
          const isOn = (k) => followFilter === k;
          const pillBase = { display: "inline-flex", alignItems: "center", gap: 6, border: "none", cursor: "pointer", fontSize: 12.5, fontWeight: 600, padding: "6px 11px", borderRadius: 7, transition: "all .12s" };
          return (
            <>
              <span style={{ width: 1, height: 22, background: "var(--line-2)", margin: "0 2px" }} />
              <span style={{ fontSize: 12.5, color: "var(--ink-3)", fontWeight: 500, display: "inline-flex", alignItems: "center", gap: 5 }} title="Lọc theo cờ theo dõi gắn trên các khoản chi phí">
                <span style={{ display: "inline-block", width: 9, height: 9, borderRadius: 999, background: "var(--warn)" }} /> Theo dõi:
              </span>
              <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 9, padding: 3, gap: 1 }}>
                <button type="button" onClick={() => setFollowFilter("all")}
                  style={{ ...pillBase, background: isOn("all") ? "#fff" : "transparent", color: isOn("all") ? "var(--ink)" : "var(--ink-3)", boxShadow: isOn("all") ? "0 1px 2px rgba(16,19,23,.12)" : "none" }}>
                  Tất cả
                </button>
                <button type="button" onClick={() => setFollowFilter("missing")} title="Lô có khoản gắn theo dõi nhưng chưa điền số tiền"
                  style={{ ...pillBase, background: isOn("missing") ? "#fff" : "transparent", color: isOn("missing") ? "var(--warn)" : "var(--ink-3)", boxShadow: isOn("missing") ? "0 1px 2px rgba(16,19,23,.12)" : "none" }}>
                  Chưa điền tiền
                  <span className="tnum" style={{ fontSize: 11, fontWeight: 700, color: isOn("missing") ? "#fff" : "var(--ink-4)", background: isOn("missing") ? "var(--warn)" : "var(--line-2)", padding: "0 6px", borderRadius: 999, minWidth: 16, textAlign: "center" }}>{followStats.missShips}</span>
                </button>
                <button type="button" onClick={() => setFollowFilter("any")} title="Lô có ít nhất 1 khoản gắn theo dõi"
                  style={{ ...pillBase, background: isOn("any") ? "#fff" : "transparent", color: isOn("any") ? "var(--ink)" : "var(--ink-3)", boxShadow: isOn("any") ? "0 1px 2px rgba(16,19,23,.12)" : "none" }}>
                  Có theo dõi
                  <span className="tnum" style={{ fontSize: 11, fontWeight: 700, color: isOn("any") ? "var(--ink-3)" : "var(--ink-4)", background: isOn("any") ? "var(--line-2)" : "transparent", padding: "0 6px", borderRadius: 999, minWidth: 16, textAlign: "center" }}>{followStats.anyShips}</span>
                </button>
              </div>
              {followStats.byColor.length > 0 && (
                <div style={{ display: "inline-flex", gap: 4, alignItems: "center" }}>
                  {followStats.byColor.map((b) => {
                    const on = followFilter === b.hex;
                    return (
                      <button key={b.hex} type="button" onClick={() => setFollowFilter(on ? "all" : b.hex)}
                        title={`Màu ${b.hex} · ${b.miss} chưa điền / ${b.total} lô`}
                        style={{ display: "inline-flex", alignItems: "center", gap: 5, padding: "5px 9px", border: on ? `1.5px solid ${b.hex}` : "1px solid var(--line)", background: on ? "#fff" : "transparent", borderRadius: 999, cursor: "pointer", fontSize: 11.5, fontWeight: 600, color: "var(--ink-2)", transition: "all .12s" }}>
                        <span style={{ width: 11, height: 11, borderRadius: 999, background: b.hex, boxShadow: on ? `0 0 0 2px #fff, 0 0 0 3px ${b.hex}` : "none" }} />
                        <span className="tnum" style={{ color: b.miss > 0 ? "var(--warn)" : "var(--ink-3)" }}>{b.miss}</span>
                        <span style={{ color: "var(--ink-4)" }} className="tnum">/ {b.total}</span>
                      </button>
                    );
                  })}
                </div>
              )}
            </>
          );
        })()}
        {sort.key !== "default" && (
          <button type="button" onClick={() => setSort({ key: "default", dir: 1 })}
            style={{ marginLeft: 4, fontSize: 12, color: "var(--accent)", background: "transparent", border: "none", cursor: "pointer", fontWeight: 600 }}>
            ↺ Bỏ sắp xếp
          </button>
        )}
        <div style={{ flex: 1 }} />
        <span style={{ fontSize: 12, color: "var(--ink-4)" }}>{rows.length}/{ships.length} lô · bấm tiêu đề cột để sắp xếp</span>
      </div>

      {/* table */}
      <div style={{ flex: 1, minHeight: 0, display: "flex", flexDirection: "column", padding: "16px 22px 14px" }}>
        <div style={{ flex: 1, minHeight: 0, background: "#fff", border: "1px solid var(--line)", borderRadius: 12, overflow: "auto" }}>
          <table style={{ width: "100%", borderCollapse: "collapse", minWidth: minW }}>
            <thead>
              <tr>
                <TH w={40} align="center">#</TH>
                <TH sticky><SortBtn k="customer" sort={sort} onSort={toggleSort}>Khách hàng</SortBtn></TH>
                <TH>Cont</TH>
                <TH>Tuyến</TH>
                <TH>Lịch trình</TH>
                <TH align="right"><SortBtn k="cost" sort={sort} onSort={toggleSort} align="right">Chi phí</SortBtn></TH>
                <TH w={44}></TH>
              </tr>
            </thead>
            <tbody>
              {rows.map((s, i) => {
                const cc = calcCost(s.cost);
                const m = metrics(s);
                const costMain = m.cost;
                const costSub = cc.thuChiHo > 0 ? `Chi hộ ${fmtShort(cc.thuChiHo)} · còn lại công ty` : (cc.tongChiPhi > 0 ? "Chi phí công ty" : "Xem chi tiết");
                return (
                  <tr key={s.id} style={{ transition: "background .1s", background: "transparent" }}
                    onMouseEnter={(e) => (e.currentTarget.style.background = "var(--accent-weak-2)")}
                    onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}>
                    <TD align="center"><span className="tnum" style={{ color: "var(--ink-4)", fontSize: 12.5 }}>{i + 1}</span></TD>
                    <TD sticky>
                      <EditCell onClick={() => setModal({ id: s.id, type: "info" })}>
                        <div style={{ fontWeight: 600, fontSize: 13.5 }}>{s.customer || <span style={{ color: "var(--ink-4)", fontWeight: 400 }}>(chưa đặt tên)</span>}</div>
                        <div style={{ display: "flex", alignItems: "center", gap: 7, marginTop: 3 }}>
                          <span style={{ fontSize: 12, color: "var(--ink-3)" }} className="tnum">{s.booking || "—"}</span>
                          <Badge tone={s.io === "Nhập" ? "blue" : "gray"}>{s.io}</Badge>
                        </div>
                      </EditCell>
                    </TD>
                    <TD>
                      <EditCell onClick={() => setModal({ id: s.id, type: "info" })}>
                        {isHph ? (
                          <>
                            <div style={{ fontWeight: 600, fontSize: 13 }} className="tnum">{s.qty} × {s.contType}</div>
                            <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 2 }} className="tnum">{s.contNo || "—"}</div>
                          </>
                        ) : (
                          <>
                            <div style={{ fontWeight: 600, fontSize: 13 }} className="tnum">{s.contNo || "—"}</div>
                            <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 2 }} className="tnum">{s.contType}{s.kho ? " · " + s.kho : ""}</div>
                            <div style={{ display: "inline-flex", alignItems: "center", gap: 5, marginTop: 4, fontSize: 10.5, fontWeight: 700, padding: "2px 8px", borderRadius: 999,
                              color: (s.bksRa && s.bksRa.trim()) ? "var(--good)" : "var(--warn)", background: (s.bksRa && s.bksRa.trim()) ? "var(--good-weak)" : "#fcf3e2" }}>
                              <span style={{ width: 6, height: 6, borderRadius: 999, background: "currentColor" }} />
                              {(s.bksRa && s.bksRa.trim()) ? "Đã ra · " + s.bksRa : "Chưa ra"}
                            </div>
                          </>
                        )}
                      </EditCell>
                    </TD>
                    <TD>
                      <EditCell onClick={() => setModal({ id: s.id, type: "info" })}>
                        <div style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 12.5 }}>
                          <span style={{ color: "var(--ink-2)" }}>{s.from || "—"}</span>
                          <span style={{ color: "var(--accent)", flexShrink: 0 }}><I.arrow /></span>
                          <span style={{ color: "var(--ink-2)" }}>{s.to || "—"}</span>
                        </div>
                      </EditCell>
                    </TD>
                    <TD>
                      <EditCell onClick={() => setModal({ id: s.id, type: "info" })}>
                        {isHph ? (
                          <>
                            <div style={{ fontSize: 12.5, color: "var(--ink-2)" }} className="tnum">Tàu: {fmtDate(s.sailDate) || "—"}</div>
                            <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 2 }}>Cắt máng {s.cutOff || "—"}</div>
                          </>
                        ) : (
                          <>
                            <div style={{ fontSize: 12.5, color: "var(--ink-2)" }} className="tnum">Cắt máng: {fmtCM(s.cutOff) || "—"}</div>
                            <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 2 }} className="tnum">Đến: {fmtDate(s.contDen) || "—"}</div>
                            {(() => { const ft = calcFreeTime(s, cfg.freeTimeHours); return ft ? (
                              <div style={{ display: "inline-flex", alignItems: "center", gap: 5, marginTop: 3, fontSize: 10.5, fontWeight: 700, padding: "2px 8px", borderRadius: 999, color: ft.connect ? "var(--good)" : "var(--danger)", background: ft.connect ? "var(--good-weak)" : "#fce8e8" }}>
                                <span style={{ width: 6, height: 6, borderRadius: 999, background: "currentColor" }} />{ft.connect ? "CONNECT" : "DISCONNECT"} <span style={{ fontWeight: 500, opacity: .8 }}>· {fmtHours(ft.hours)}</span>
                              </div>
                            ) : <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 2 }} className="tnum">Ra: {fmtDate(s.contRa) || "—"}</div>; })()}
                          </>
                        )}
                      </EditCell>
                    </TD>
                    <TD pad="6px 10px">
                      <CellBtn main={fmtVND(costMain)} sub={costSub} onClick={() => setModal({ id: s.id, type: "cost" })} />
                      {(() => {
                        const miss = ((s.cost && s.cost.items) || []).filter((it) => it.item && costColors[it.item] && !toNum(it.amount));
                        if (!miss.length) return null;
                        return (
                          <div style={{ display: "flex", flexWrap: "wrap", gap: 4, marginTop: 5, paddingLeft: 9 }}>
                            {miss.map((it) => {
                              const dot = colorHex(costColors[it.item]) || "var(--warn)";
                              return (
                                <button key={it.id} type="button" title={"Lọc lô có theo dõi màu này · Chưa điền: " + it.item}
                                  onClick={(e) => { e.stopPropagation(); setFollowFilter(dot); }}
                                  style={{ display: "inline-flex", alignItems: "center", gap: 4, fontSize: 10.5, fontWeight: 600, padding: "1px 7px", borderRadius: 999, color: dot, background: "var(--line-2)", border: "none", cursor: "pointer" }}>
                                  <span style={{ width: 6, height: 6, borderRadius: 999, background: dot }} />{it.item}
                                </button>
                              );
                            })}
                          </div>
                        );
                      })()}
                    </TD>
                    <TD align="center">
                      <button type="button" onClick={() => setModal({ id: s.id, type: "rev" })} title="Doanh thu & công nợ"
                        style={{ width: 30, height: 30, display: "grid", placeItems: "center", border: "none", borderRadius: 8, background: "transparent", color: "var(--ink-4)", cursor: "pointer", fontWeight: 700 }}
                        onMouseEnter={(e) => { e.currentTarget.style.background = "var(--accent-weak)"; e.currentTarget.style.color = "var(--accent)"; }}
                        onMouseLeave={(e) => { e.currentTarget.style.background = "transparent"; e.currentTarget.style.color = "var(--ink-4)"; }}>
                        ₫
                      </button>
                    </TD>
                  </tr>
                );
              })}
            </tbody>
          </table>
          {rows.length === 0 && <div style={{ padding: "40px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5 }}>Chưa có lô hàng nào. Bấm “Thêm lô hàng” để bắt đầu.</div>}
        </div>
        <div style={{ marginTop: 10, fontSize: 12, color: "var(--ink-4)", display: "flex", alignItems: "center", gap: 7, flexShrink: 0 }}>
          <I.fx /> Chi phí thống kê theo từng lô. Doanh thu & công nợ thu theo <b style={{ color: "var(--ink-3)" }}>Bảng kê</b> (gom lô theo ngày cont ra). Bấm ô <b style={{ color: "var(--ink-3)" }}>Chi phí</b> để phân bổ, nút <b style={{ color: "var(--ink-3)" }}>₫</b> để nhập doanh thu.
        </div>
      </div>

      {active && modal.type === "cost" && <CostPopup ship={active} patch={(np) => patch(active.id, np)} onClose={() => setModal(null)} cfg={cfg} addCfg={addCfg} />}
      {active && modal.type === "rev" && (isHph
        ? <RevenuePopup ship={active} patch={(np) => patch(active.id, np)} onClose={() => setModal(null)} cfg={cfg} addCfg={addCfg} />
        : <RevenuePopupICD ship={active} patch={(np) => patch(active.id, np)} onClose={() => setModal(null)} cfg={cfg} addCfg={addCfg} />)}
      {active && modal.type === "info" && <InfoPopup ship={active} isHph={isHph} patch={(np) => patch(active.id, np)} patchOther={(id, np) => patch(id, np)} siblings={ships.filter((x) => x.id !== active.id)} onClose={() => setModal(null)} onDelete={() => delShip(active.id)} canDelete={T.canDelete} cfg={cfg} addCfg={addCfg} />}
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("trk-root")).render(<ShipmentsApp />);
})();
</script>
@endverbatim
@endpush
