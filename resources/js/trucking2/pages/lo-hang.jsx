import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useMemo, useEffect, useRef } = React;
import { I, fmtVND, fmtShort, fmtDate, calcCost, calcVeh, calcRev, calcVehICD, calcRevICD, calcFreeTime, fmtHours, toNum, Modal, Btn } from "@trk/lib.jsx";
import { CostPopup, RevenuePopup, RevenuePopupICD, InfoPopup, colorHex } from "@trk/pop.jsx";
import { SortBtn, CellBtn, Badge, EditCell, TH, TD } from "@trk/ui.jsx";

function ShipmentsApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const DEFAULT_CFG = { locations: [], locationCode: {}, customers: [], customerInfo: {}, contTypes: [], warehouses: [], payers: [], costItems: [], choHoItems: [], revItems: [], vehicles: [], vehicleType: {}, drivers: [], prices: {}, costColors: {}, vatDefault: { hph: "8", icd: "0" }, freeTimeHours: "4" };
  const api = (method, url, body) => window.trkApi(method, url, body);

  // Dùng chung 1 mẫu (ICD) — không còn tách HPH/ICD
  const sheet = "icd";
  const isHph = false;
  // Phân trang SERVER-SIDE: chỉ giữ 20 lô của trang hiện tại; tổng/đếm/follow lấy toàn cục từ server.
  const P0 = B.page || { data: [], page: 1, perPage: 20, total: 0, lastPage: 1, totalCost: 0, filterCounts: { all: 0, out: 0, notout: 0 }, followStats: { anyShips: 0, missShips: 0, byColor: [] } };
  const [data, setData] = useState(P0.data || []);
  const [pageInfo, setPageInfo] = useState({ page: P0.page, perPage: P0.perPage, total: P0.total, lastPage: P0.lastPage });
  const [totalCost, setTotalCost] = useState(P0.totalCost || 0);
  const [filterCounts, setFilterCounts] = useState(P0.filterCounts || { all: 0, out: 0, notout: 0 });
  const [followStats, setFollowStats] = useState(P0.followStats || { anyShips: 0, missShips: 0, byColor: [] });
  const [loading, setLoading] = useState(false);
  const [sibs, setSibs] = useState(B.sibs || []);   // danh sách rút gọn mọi lô cho picker "ra hộ"
  const [draft, setDraft] = useState(null);   // lô nháp đang tạo (chưa ghi server) — chỉ sống trong popup
  const [cfg, setCfgState] = useState(() => ({ ...DEFAULT_CFG, ...(B.cfg || {}) }));
  const [modal, setModal] = useState(null);
  const [q, setQ] = useState("");
  const [qDeb, setQDeb] = useState("");        // q sau debounce (param thật gửi server)
  const [page, setPage] = useState(1);
  const [filter, setFilter] = useState("all");
  // Bộ lọc theo "follow": 'all' | 'any' | 'missing' | '#hex' (lọc theo màu cụ thể)
  const [followFilter, setFollowFilter] = useState("all");
  const [sort, setSort] = useState({ key: "default", dir: 1 });
  const [showExport, setShowExport] = useState(false);
  const [exporting, setExporting] = useState(false);   // chống bấm Xuất Excel nhiều lần
  const [expFrom, setExpFrom] = useState("");
  const [expTo, setExpTo] = useState("");
  const [showImport, setShowImport] = useState(false);
  const [impWb, setImpWb] = useState(null);   // {names, wb}
  const [impSheet, setImpSheet] = useState("");
  const [impBusy, setImpBusy] = useState(false);
  const [impMsg, setImpMsg] = useState("");
  const [impRows, setImpRows] = useState([]);
  const [impCheck, setImpCheck] = useState(null); // null | { valid, total, errors:[] }
  const impFileRef = useRef(null);

  // Tải 1 trang từ server theo tham số hiện tại. reqId chống race (chỉ áp phản hồi mới nhất).
  const reqId = useRef(0);
  const buildParams = (over) => {
    const pg = over && over.page != null ? over.page : page;
    const p = new URLSearchParams();
    p.set("page", pg);
    if (qDeb.trim()) p.set("q", qDeb.trim());
    if (filter !== "all") p.set("filter", filter);
    if (followFilter !== "all") p.set("follow", followFilter);
    if (sort.key !== "default") { p.set("sort", sort.key); p.set("dir", String(sort.dir)); }
    return p;
  };
  const load = async (over) => {
    const myId = ++reqId.current;
    const pg = over && over.page != null ? over.page : page;
    setLoading(true);
    try {
      const r = await window.trkApi("GET", ROUTES.shipmentsPage + "?" + buildParams(over).toString());
      if (myId !== reqId.current) return;
      if (r && r.ok) {
        setData(r.data || []);
        setPageInfo({ page: r.page, perPage: r.perPage, total: r.total, lastPage: r.lastPage });
        setTotalCost(r.totalCost || 0);
        setFilterCounts(r.filterCounts || { all: 0, out: 0, notout: 0 });
        setFollowStats(r.followStats || { anyShips: 0, missShips: 0, byColor: [] });
        if (r.sibs) setSibs(r.sibs);
        if (r.page !== pg) setPage(r.page);
      }
    } catch (e) { window.trkToast && window.trkToast("Lỗi tải danh sách", "error"); }
    finally { if (myId === reqId.current) setLoading(false); }
  };
  // Debounce ô tìm kiếm → cập nhật qDeb + về trang 1 (cùng 1 batch để chỉ load 1 lần)
  useEffect(() => { const t = setTimeout(() => { setQDeb(q); setPage(1); }, 350); return () => clearTimeout(t); }, [q]);
  // Nạp lại khi tham số đổi (bỏ lần mount đầu — đã có boot)
  const skipFirst = useRef(true);
  useEffect(() => {
    if (skipFirst.current) { skipFirst.current = false; return; }
    load();
  }, [page, qDeb, filter, followFilter, sort]);

  // Lazy-load master data (danh mục dropdown) lần đầu cần — boot chỉ có cfg tối thiểu.
  // cfgRef giữ cfg mới nhất để ensureCfg trả về giá trị đã merge (tránh stale closure ở export).
  const cfgLoaded = useRef(false);
  const cfgRef = useRef(cfg);
  useEffect(() => { cfgRef.current = cfg; }, [cfg]);
  const ensureCfg = async () => {
    if (cfgLoaded.current) return cfgRef.current;
    cfgLoaded.current = true;
    try {
      const r = await window.trkApi("GET", ROUTES.config);
      if (r && r.ok) { const merged = { ...cfgRef.current, ...r.cfg }; cfgRef.current = merged; setCfgState(merged); return merged; }
      cfgLoaded.current = false;
    } catch (e) { cfgLoaded.current = false; }
    return cfgRef.current;
  };
  // Mở popup (xem/sửa) → cần đầy đủ danh mục.
  const openModal = (m) => { ensureCfg(); setModal(m); };

  const ships = data;

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
        url = ROUTES.catalog + key; partial = { [key]: n[key], prices: n.prices };
        // Gửi đúng map/mảng mã theo danh mục (định danh theo mã) — tránh xoá mã khi thêm nhanh
        if (key === "locations")  { partial.locationCode = n.locationCode;  partial.locationCodeArr = n.locationCodeArr; }
        if (key === "warehouses") { partial.warehouseCode = n.warehouseCode; partial.warehouseCodeArr = n.warehouseCodeArr; }
      }
      if (url) api("PUT", url, { cfg: partial });
    }, 700);
  };
  const addCfg = (key, v) => setCfgState((c) => {
    if ((c[key] || []).includes(v)) return c;
    const n = { ...c, [key]: [...(c[key] || []), v] };
    if (key === "locations")  n.locationCodeArr  = [...(c.locationCodeArr  || []), ""];   // giữ mảng mã thẳng hàng
    if (key === "warehouses") n.warehouseCodeArr = [...(c.warehouseCodeArr || []), ""];
    saveCatalogKey(key, n); return n;
  });

  // Manual save: patch chỉ cập nhật local state + đánh dấu dirty; thực sự PUT khi user bấm nút Lưu trong popup.
  const dirtyIds = useRef(new Set());
  const dirtyFields = useRef({});   // id -> Set(field) : chỉ ghi field đã sửa (lưu từng phần, tránh đè người khác)
  const patch = (id, np) => {
    dirtyIds.current.add(id);
    const set = dirtyFields.current[id] || (dirtyFields.current[id] = new Set());
    Object.keys(np || {}).forEach((k) => set.add(k));
    if (draft && draft.id === id) { setDraft((d) => ({ ...d, ...np })); return; }
    setData((s) => s.map((sh) => (sh.id === id ? { ...sh, ...np } : sh)));
  };
  // Lưu các lô đã sửa: lô nháp (_new) → POST tạo mới; lô có sẵn → PUT. Sau đó nạp lại trang
  // để cập nhật tổng/đếm. Trả về Promise<boolean>.
  const commitDirty = async (ids) => {
    const list = ids ? ids.filter((id) => dirtyIds.current.has(id)) : [...dirtyIds.current];
    if (!list.length) return true;
    let ok = true, createdNew = false;
    for (const id of list) {
      const ship = (draft && draft.id === id) ? draft : data.find((s) => s.id === id);
      if (!ship) continue;
      // Bắt buộc Khách hàng + Số booking trước khi tạo/lưu
      if (!((ship.customer || "").toString().trim()) || !((ship.booking || "").toString().trim())) { ok = false; continue; }
      if (ship._new) {
        const res = await api("POST", ROUTES.shipmentStore, { sheet, ship });
        if (res && res.ok) { dirtyIds.current.delete(id); delete dirtyFields.current[id]; if (draft && draft.id === id) setDraft(null); createdNew = true; }
        else ok = false;
      } else {
        // Chỉ gửi field đã sửa + danh sách "fields" → server ghi đúng field đó, giữ nguyên field khác
        const fields = [...(dirtyFields.current[id] || [])];
        const partial = { id: ship.id };
        fields.forEach((k) => { partial[k] = ship[k]; });
        const res = await api("PUT", ROUTES.shipment + id, { sheet, ship: partial, fields });
        if (res && res.ok) { dirtyIds.current.delete(id); delete dirtyFields.current[id]; }
        else ok = false;
      }
    }
    window.trkToast && window.trkToast(ok ? "Đã lưu" : "Chưa lưu được (kiểm tra Khách hàng / Số booking)", ok ? undefined : "error");
    if (createdNew && ok) setModal(null);   // lô mới đã có id thật → đóng popup (tránh tham chiếu id tạm)
    await load();
    return ok;
  };
  const isDirty = (id) => dirtyIds.current.has(id);
  const active = modal ? ((draft && draft.id === modal.id) ? draft : data.find((s) => s.id === modal.id)) : null;

  const today = new Date(); today.setHours(0, 0, 0, 0);
  const metrics = (s) => {
    const cost = calcCost(s.cost).tongChiPhi;
    const r = isHph ? calcRev(s.rev) : calcRevICD(s.rev);
    const profit = (r.phaiThu - r.vat) - cost;
    const overdue = r.conNo > 0 && s.rev?.hanTT && new Date(s.rev.hanTT) < today;
    return { cost, r, profit, overdue };
  };

  // Màu theo dõi lấy từ danh mục (cfg.costColors) — dùng cho chip "chưa điền" trên từng dòng.
  const costColors = cfg.costColors || {};
  // Lọc/tìm/sắp xếp/phân trang đã làm SERVER-SIDE → hàng hiển thị chính là data của trang.
  const rows = data;

  // Thêm lô = tạo bản NHÁP cục bộ (chưa ghi server). Chỉ tạo thật khi bấm "Lưu thông tin" (đủ Khách hàng + Số booking).
  const addRow = () => {
    if (draft || modal) return;   // tránh tạo nhiều lô nháp khi bấm double
    const vat = (cfg.vatDefault || {})[sheet] || (isHph ? "8" : "0");
    const tmpId = "tmp_" + Date.now() + "_" + Math.floor(Math.random() * 1000);
    const base = { id: tmpId, _new: true, customer: "", booking: "", io: "Nhập", contNo: "", contType: "40HC", kho: "", bksVao: "", bksRa: "", from: "ICD Quế Võ", to: "", contDen: "", contRa: "", cutOff: "", gioDenDuKien: "", gioXeRa: "", cost: { items: [] }, rev: { vatRate: vat, doanhThu: [], choHo: [], payments: [] } };
    dirtyIds.current.add(tmpId);
    setDraft(base);   // lô nháp sống trong popup, chưa vào danh sách trang
    ensureCfg();      // cần danh mục dropdown cho popup
    setModal({ id: tmpId, type: "info" });
  };
  // Đóng popup thông tin: nếu là lô nháp chưa lưu → bỏ draft
  const closeInfo = () => {
    if (draft && modal && modal.id === draft.id) { dirtyIds.current.delete(draft.id); delete dirtyFields.current[draft.id]; setDraft(null); }
    setModal(null);
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
    if (res && res.ok) {
      setModal(null);
      window.trkToast && window.trkToast("Đã xoá lô hàng");
      await load();   // nạp lại trang (server tự kẹp page nếu trang cuối rỗng)
    } else {
      window.trkToast && window.trkToast("Xoá thất bại", "error");
    }
  };

  // Xuất Excel (client-side) — các cột yêu cầu + MST/email lấy từ khách hàng; lọc theo NGÀY KẾ HOẠCH (Giờ đến kế hoạch)
  const exportExcel = async () => {
    if (exporting) return;
    if (typeof XLSX === "undefined") { window.alert("Thư viện Excel chưa tải xong, thử lại sau giây lát."); return; }
    setExporting(true);
    try {
    const fullCfg = await ensureCfg();   // export cần MST/email từ customerInfo
    const info = fullCfg.customerInfo || {};
    const plannedDate = (s) => (s.gioDenDuKien || "").slice(0, 10); // YYYY-MM-DD
    // Xuất TẤT CẢ lô (không chỉ trang hiện tại) — lấy qua endpoint với all=1, rồi lọc theo ngày.
    let allShips = [];
    try {
      const r = await window.trkApi("GET", ROUTES.shipmentsPage + "?all=1");
      if (r && r.ok) allShips = r.data || [];
    } catch (e) { window.trkToast && window.trkToast("Lỗi tải dữ liệu xuất Excel", "error"); return; }
    const list = allShips.filter((s) => {
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
    } finally { setExporting(false); }
  };

  // ---- Import lô hàng từ Excel ----
  const IMP_COLS = ["Khách hàng", "SỐ BOOKING/BILL", "NHẬP/XUẤT", "SỐ LƯỢNG", "LOẠI", "SỐ CONTAINER", "CẮT MÁNG", "NƠI LẤY", "NƠI HẠ", "NGÀY", "GIỜ", "KHO", "INVOICE"];
  const downloadTemplate = () => {
    if (typeof XLSX === "undefined") { window.alert("Thư viện Excel chưa tải xong."); return; }
    const c = cfgRef.current || {};
    const locs = c.locations || [];
    const codeOf = c.locationCode || {};
    const custs = c.customers || [];
    // Ví dụ dùng đúng dữ liệu đang có (nếu chưa nạp thì dùng mẫu mặc định)
    const exFrom = locs[0] || "ICD Quế Võ";
    const exTo = locs[1] || locs[0] || "KCN Tiên Sơn";
    const exCust = custs[0] || "Canon Vietnam";
    const ex1 = { "Khách hàng": exCust, "SỐ BOOKING/BILL": "BL-ICD-0001", "NHẬP/XUẤT": "Nhập", "SỐ LƯỢNG": 3, "LOẠI": "40HC", "SỐ CONTAINER": "TGHU1234567\nMSKU9981122\nCSNU4567788", "CẮT MÁNG": "14/05/2026 10:00", "NƠI LẤY": exFrom, "NƠI HẠ": exTo, "NGÀY": "14/05/2026", "GIỜ": "08:00", "KHO": "Kho A2", "INVOICE": "INV-001" };
    const ex2 = { "Khách hàng": exCust, "SỐ BOOKING/BILL": "BL-ICD-0002", "NHẬP/XUẤT": "Xuất", "SỐ LƯỢNG": 2, "LOẠI": "20DC", "SỐ CONTAINER": "", "CẮT MÁNG": "15/05/2026 09:00", "NƠI LẤY": codeOf[exFrom] || exFrom, "NƠI HẠ": exTo, "NGÀY": "15/05/2026", "GIỜ": "07:30", "KHO": "Kho B1", "INVOICE": "INV-002" };
    const ws = XLSX.utils.json_to_sheet([ex1, ex2], { header: IMP_COLS });
    ws["!cols"] = IMP_COLS.map((col) => ({ wch: Math.max(12, col.length + 2) }));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Lô hàng");
    // Sheet tham chiếu — copy đúng giá trị để tránh lỗi "chưa có trong hệ thống"
    const locRows = locs.map((n) => ({ "Tên địa điểm": n, "Ký hiệu": codeOf[n] || "" }));
    if (locRows.length) {
      const wl = XLSX.utils.json_to_sheet(locRows, { header: ["Tên địa điểm", "Ký hiệu"] });
      wl["!cols"] = [{ wch: 32 }, { wch: 14 }];
      XLSX.utils.book_append_sheet(wb, wl, "Địa điểm hợp lệ");
    }
    if (custs.length) {
      const wc = XLSX.utils.json_to_sheet(custs.map((n) => ({ "Khách hàng": n })), { header: ["Khách hàng"] });
      wc["!cols"] = [{ wch: 36 }];
      XLSX.utils.book_append_sheet(wb, wc, "Khách hàng hợp lệ");
    }
    XLSX.writeFile(wb, "mau-import-lo-hang.xlsx");
  };
  const onImpFile = (e) => {
    const f = e.target.files && e.target.files[0]; e.target.value = "";
    if (!f) return;
    if (typeof XLSX === "undefined") { setImpMsg("Thư viện Excel chưa tải xong."); return; }
    setImpMsg(""); setImpCheck(null); setImpRows([]);
    const rd = new FileReader();
    rd.onload = () => { const wb = XLSX.read(rd.result, { type: "array" }); setImpWb({ names: wb.SheetNames, wb }); setImpSheet(wb.SheetNames[0] || ""); };
    rd.readAsArrayBuffer(f);
  };
  const normH = (s) => String(s == null ? "" : s).trim().toLowerCase().replace(/\s+/g, " ");
  const toIsoDate = (s) => { const m = /(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})/.exec(String(s || "")); if (!m) return ""; let [, d, mo, y] = m; if (y.length === 2) y = "20" + y; return `${y}-${mo.padStart(2, "0")}-${d.padStart(2, "0")}`; };
  const toHm = (s) => { const m = /(\d{1,2}):(\d{2})/.exec(String(s || "")); return m ? `${m[1].padStart(2, "0")}:${m[2]}` : ""; };
  const parseRows = () => {
    const aoa = XLSX.utils.sheet_to_json(impWb.wb.Sheets[impSheet], { header: 1, raw: false, defval: "" });
    let hi = aoa.findIndex((r) => (r || []).some((c) => { const h = normH(c); return h.includes("khách") || h.includes("nhà máy"); }));
    if (hi < 0) hi = 0;
    const header = (aoa[hi] || []).map(normH);
    const col = (...kws) => header.findIndex((h) => kws.some((k) => h.includes(k)));
    const C = { customer: col("khách", "nhà máy"), booking: col("booking", "bill"), io: col("nhập", "xuất"), qty: col("lượng"), contType: col("loại"), contNo: col("container", "tên cont", "số cont"), cutOff: col("máng"), from: col("lấy"), to: col("hạ"), ngay: col("ngày"), gio: col("giờ"), kho: col("kho"), inv: col("invoice", "inv") };
    const out = [];
    for (let r = hi + 1; r < aoa.length; r++) {
      const row = aoa[r] || []; const g = (i) => (i >= 0 ? String(row[i] == null ? "" : row[i]).trim() : "");
      if (!g(C.customer) && !g(C.booking) && !g(C.from) && !g(C.to)) continue;
      const ngayIso = toIsoDate(g(C.ngay)); const hm = toHm(g(C.gio));
      const gioDenDuKien = ngayIso ? `${ngayIso}T${hm || "00:00"}` : "";
      const cmRaw = g(C.cutOff); const cmDate = toIsoDate(cmRaw); const cmHm = toHm(cmRaw);
      const cutOff = cmDate ? `${cmDate}T${cmHm || "00:00"}` : cmRaw;
      out.push({ customer: g(C.customer), booking: g(C.booking), io: g(C.io), qty: g(C.qty).replace(/[^\d]/g, ""), contType: g(C.contType), contNo: g(C.contNo), cutOff, from: g(C.from), to: g(C.to), kho: g(C.kho), inv: g(C.inv), gioDenDuKien });
    }
    return out;
  };
  const doCheck = async () => {
    if (!impWb || !impSheet) return;
    setImpBusy(true); setImpMsg(""); setImpCheck(null);
    const out = parseRows(); setImpRows(out);
    if (!out.length) { setImpBusy(false); setImpCheck({ valid: false, total: 0, errors: [{ line: 0, customer: "", booking: "", reasons: ["Sheet không có dòng dữ liệu hợp lệ"] }] }); return; }
    try {
      const res = await api("POST", ROUTES.shipmentCheck, { rows: out });
      setImpBusy(false);
      if (res && res.ok) setImpCheck({ valid: res.valid, total: res.total, errors: res.errors || [] });
      else setImpCheck({ valid: false, total: out.length, errors: [{ line: 0, reasons: [(res && res.message) || "Lỗi kiểm tra"] }] });
    } catch (err) { setImpBusy(false); setImpMsg("Lỗi kết nối khi kiểm tra."); }
  };
  const doImport = async () => {
    if (!impCheck || !impCheck.valid || !impRows.length) return;
    setImpBusy(true); setImpMsg("");
    try {
      const res = await api("POST", ROUTES.shipmentImport, { sheet, rows: impRows });
      setImpBusy(false);
      if (res && res.ok && res.valid) {
        setImpMsg(`Đã nhập ${res.created} lô.`); setImpWb(null); setImpCheck(null); setImpRows([]);
        await load();   // nạp lại danh sách + tổng/đếm sau import
      } else if (res && res.errors) {
        setImpCheck({ valid: false, total: impRows.length, errors: res.errors });
        setImpMsg("Dữ liệu có lỗi — chưa import gì.");
      } else setImpMsg("Import lỗi: " + ((res && res.message) || "không rõ"));
    } catch (err) { setImpBusy(false); setImpMsg("Import lỗi kết nối."); }
  };

  const toggleSort = (key) => { setSort((s) => s.key === key ? { key, dir: -s.dir } : { key, dir: 1 }); setPage(1); };
  const setFilterP = (f) => { setFilter(f); setPage(1); };
  const setFollowP = (f) => { setFollowFilter(f); setPage(1); };
  const minW = 880;
  // Dãy số trang có dấu "…" — kiểu phân trang gọn (luôn hiện trang đầu/cuối + lân cận trang hiện tại)
  const pageList = (cur, last) => {
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
    const s = new Set([1, last, cur, cur - 1, cur + 1, cur - 2, cur + 2]);
    const arr = [...s].filter((n) => n >= 1 && n <= last).sort((a, b) => a - b);
    const out = []; let prev = 0;
    arr.forEach((n) => { if (n - prev > 1) out.push("…"); out.push(n); prev = n; });
    return out;
  };
  const goPage = (p) => { const np = Math.min(Math.max(1, p), pageInfo.lastPage); if (np !== pageInfo.page) setPage(np); };

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
          <button type="button" onClick={() => { ensureCfg(); setShowImport(true); }} title="Import lô hàng từ Excel"
            style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "9px 14px", fontSize: 13.5, fontWeight: 600, cursor: "pointer", color: "var(--ink-2)", background: "#fff", border: "1px solid var(--line)", borderRadius: 10 }}
            onMouseEnter={(e) => (e.currentTarget.style.background = "var(--line-2)")} onMouseLeave={(e) => (e.currentTarget.style.background = "#fff")}>
            <i className="bi bi-upload" style={{ color: "var(--accent)" }} /> Import lô
          </button>
          <input ref={impFileRef} type="file" accept=".xlsx,.xls" onChange={onImpFile} style={{ display: "none" }} />
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
                  <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginBottom: 11, lineHeight: 1.5 }}>Lọc theo <b style={{ color: "var(--ink-3)" }}>ngày kế hoạch</b> (Giờ đến kế hoạch). Để trống = xuất tất cả {pageInfo.total} lô.</div>
                  <div style={{ display: "flex", gap: 8, marginBottom: 11 }}>
                    <label style={{ flex: 1 }}><div style={{ fontSize: 11, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>Từ ngày</div>
                      <input type="date" value={expFrom} onChange={(e) => setExpFrom(e.target.value)} style={{ width: "100%", padding: "7px 8px", fontSize: 12.5, border: "1px solid var(--line)", borderRadius: 8, outline: "none", colorScheme: "light" }} /></label>
                    <label style={{ flex: 1 }}><div style={{ fontSize: 11, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>Đến ngày</div>
                      <input type="date" value={expTo} onChange={(e) => setExpTo(e.target.value)} style={{ width: "100%", padding: "7px 8px", fontSize: 12.5, border: "1px solid var(--line)", borderRadius: 8, outline: "none", colorScheme: "light" }} /></label>
                  </div>
                  <button type="button" onClick={exportExcel} disabled={exporting}
                    style={{ width: "100%", display: "inline-flex", alignItems: "center", justifyContent: "center", gap: 7, padding: "9px 0", fontSize: 13.5, fontWeight: 600, cursor: exporting ? "default" : "pointer", color: "#fff", background: "var(--good)", border: "none", borderRadius: 9, opacity: exporting ? 0.6 : 1 }}>
                    {exporting ? <><span style={{ width: 13, height: 13, border: "2px solid #fff", borderTopColor: "transparent", borderRadius: "50%", display: "inline-block", animation: "trk-spin .7s linear infinite" }} /> Đang xuất…</> : <><i className="bi bi-download" /> Tải file Excel</>}
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
        {[["Lô hàng", pageInfo.total, "ink"], ["Tổng chi phí", fmtVND(totalCost), "ink"]].map(([k, v, tone], i, arr) => (
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
            const cnt = (filterCounts || {})[k] || 0;
            return (
              <button key={k} type="button" onClick={() => setFilterP(k)}
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
                <button type="button" onClick={() => setFollowP("all")}
                  style={{ ...pillBase, background: isOn("all") ? "#fff" : "transparent", color: isOn("all") ? "var(--ink)" : "var(--ink-3)", boxShadow: isOn("all") ? "0 1px 2px rgba(16,19,23,.12)" : "none" }}>
                  Tất cả
                </button>
                <button type="button" onClick={() => setFollowP("missing")} title="Lô có khoản gắn theo dõi nhưng chưa điền số tiền"
                  style={{ ...pillBase, background: isOn("missing") ? "#fff" : "transparent", color: isOn("missing") ? "var(--warn)" : "var(--ink-3)", boxShadow: isOn("missing") ? "0 1px 2px rgba(16,19,23,.12)" : "none" }}>
                  Chưa điền tiền
                  <span className="tnum" style={{ fontSize: 11, fontWeight: 700, color: isOn("missing") ? "#fff" : "var(--ink-4)", background: isOn("missing") ? "var(--warn)" : "var(--line-2)", padding: "0 6px", borderRadius: 999, minWidth: 16, textAlign: "center" }}>{followStats.missShips}</span>
                </button>
                <button type="button" onClick={() => setFollowP("any")} title="Lô có ít nhất 1 khoản gắn theo dõi"
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
                      <button key={b.hex} type="button" onClick={() => setFollowP(on ? "all" : b.hex)}
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
        {loading && <span style={{ fontSize: 12, color: "var(--accent)", display: "inline-flex", alignItems: "center", gap: 5 }}><i className="bi bi-arrow-repeat" style={{ animation: "trk-spin 0.7s linear infinite" }} /> Đang tải…</span>}
        <span style={{ fontSize: 12, color: "var(--ink-4)" }}>{pageInfo.total} lô · bấm tiêu đề cột để sắp xếp</span>
      </div>

      {/* table */}
      <div style={{ flex: 1, minHeight: 0, display: "flex", flexDirection: "column", padding: "16px 22px 14px" }}>
        <div style={{ flex: 1, minHeight: 0, background: "#fff", border: "1px solid var(--line)", borderRadius: 12, overflow: "auto" }}>
          <table style={{ width: "100%", borderCollapse: "collapse", minWidth: minW }}>
            <thead>
              <tr>
                <TH w={48} align="center">ID</TH>
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
                    <TD align="center"><span className="tnum" style={{ color: "var(--ink-4)", fontSize: 12.5 }} title="ID trong CSDL">{String(s.id).startsWith("tmp") ? "mới" : s.id}</span></TD>
                    <TD sticky>
                      <EditCell onClick={() => openModal({ id: s.id, type: "info" })}>
                        <div style={{ fontWeight: 600, fontSize: 13.5 }}>{s.customer || <span style={{ color: "var(--ink-4)", fontWeight: 400 }}>(chưa đặt tên)</span>}</div>
                        <div style={{ display: "flex", alignItems: "center", gap: 7, marginTop: 3 }}>
                          <span style={{ fontSize: 12, color: "var(--ink-3)" }} className="tnum">{s.booking || "—"}</span>
                          <Badge tone={s.io === "Nhập" ? "blue" : "gray"}>{s.io}</Badge>
                          {s.cru ? <Badge tone="amber">CRU</Badge> : null}
                        </div>
                      </EditCell>
                    </TD>
                    <TD>
                      <EditCell onClick={() => openModal({ id: s.id, type: "info" })}>
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
                      <EditCell onClick={() => openModal({ id: s.id, type: "info" })}>
                        <div style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 12.5 }}>
                          <span style={{ color: "var(--ink-2)" }}>{s.from || "—"}</span>
                          <span style={{ color: "var(--accent)", flexShrink: 0 }}><I.arrow /></span>
                          <span style={{ color: "var(--ink-2)" }}>{s.to || "—"}</span>
                        </div>
                      </EditCell>
                    </TD>
                    <TD>
                      <EditCell onClick={() => openModal({ id: s.id, type: "info" })}>
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
                      <CellBtn main={fmtVND(costMain)} sub={costSub} onClick={() => openModal({ id: s.id, type: "cost" })} />
                      {(() => {
                        const miss = ((s.cost && s.cost.items) || []).filter((it) => it.item && costColors[it.item] && !toNum(it.amount));
                        if (!miss.length) return null;
                        return (
                          <div style={{ display: "flex", flexWrap: "wrap", gap: 4, marginTop: 5, paddingLeft: 9 }}>
                            {miss.map((it) => {
                              const dot = colorHex(costColors[it.item]) || "var(--warn)";
                              return (
                                <button key={it.id} type="button" title={"Lọc lô có theo dõi màu này · Chưa điền: " + it.item}
                                  onClick={(e) => { e.stopPropagation(); setFollowP(dot); }}
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
                      <button type="button" onClick={() => openModal({ id: s.id, type: "rev" })} title="Doanh thu & công nợ"
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
          {rows.length === 0 && <div style={{ padding: "40px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5 }}>{loading ? "Đang tải…" : (qDeb || filter !== "all" || followFilter !== "all" ? "Không có lô nào khớp bộ lọc." : "Chưa có lô hàng nào. Bấm “Thêm lô hàng” để bắt đầu.")}</div>}
        </div>

        {/* Phân trang */}
        {pageInfo.lastPage > 1 && (
          <div style={{ marginTop: 12, display: "flex", alignItems: "center", justifyContent: "center", gap: 6, flexShrink: 0 }}>
            {(() => {
              const cur = pageInfo.page, last = pageInfo.lastPage;
              const navBtn = (label, to, disabled, title) => (
                <button type="button" key={title} title={title} disabled={disabled} onClick={() => goPage(to)}
                  style={{ minWidth: 34, height: 34, padding: "0 9px", display: "inline-flex", alignItems: "center", justifyContent: "center", border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: disabled ? "var(--ink-4)" : "var(--ink-2)", cursor: disabled ? "default" : "pointer", fontSize: 13, opacity: disabled ? 0.5 : 1 }}>
                  {label}
                </button>
              );
              return (
                <>
                  {navBtn(<i className="bi bi-chevron-bar-left" />, 1, cur <= 1, "Trang đầu")}
                  {navBtn(<i className="bi bi-chevron-left" />, cur - 1, cur <= 1, "Trang trước")}
                  {pageList(cur, last).map((n, i) => n === "…"
                    ? <span key={"e" + i} style={{ minWidth: 22, textAlign: "center", color: "var(--ink-4)", fontSize: 13 }}>…</span>
                    : (
                      <button type="button" key={n} onClick={() => goPage(n)}
                        style={{ minWidth: 34, height: 34, border: n === cur ? "1px solid var(--accent)" : "1px solid var(--line)", borderRadius: 9, background: n === cur ? "var(--accent)" : "#fff", color: n === cur ? "#fff" : "var(--ink-2)", cursor: "pointer", fontSize: 13, fontWeight: n === cur ? 700 : 500 }} className="tnum">
                        {n}
                      </button>
                    ))}
                  {navBtn(<i className="bi bi-chevron-right" />, cur + 1, cur >= last, "Trang sau")}
                  {navBtn(<i className="bi bi-chevron-bar-right" />, last, cur >= last, "Trang cuối")}
                  <span style={{ marginLeft: 8, fontSize: 12.5, color: "var(--ink-4)" }} className="tnum">Trang {cur}/{last}</span>
                </>
              );
            })()}
          </div>
        )}

        <div style={{ marginTop: 10, fontSize: 12, color: "var(--ink-4)", display: "flex", alignItems: "center", gap: 7, flexShrink: 0 }}>
          <I.fx /> Chi phí thống kê theo từng lô. Doanh thu & công nợ thu theo <b style={{ color: "var(--ink-3)" }}>Bảng kê</b> (gom lô theo ngày cont ra). Bấm ô <b style={{ color: "var(--ink-3)" }}>Chi phí</b> để phân bổ, nút <b style={{ color: "var(--ink-3)" }}>₫</b> để nhập doanh thu.
        </div>
      </div>

      {active && modal.type === "cost" && <CostPopup ship={active} patch={(np) => patch(active.id, np)} onSave={() => commitDirty()} isDirty={isDirty} onClose={() => setModal(null)} cfg={cfg} addCfg={addCfg} />}
      {active && modal.type === "rev" && (isHph
        ? <RevenuePopup ship={active} patch={(np) => patch(active.id, np)} onSave={() => commitDirty()} isDirty={isDirty} onClose={() => setModal(null)} cfg={cfg} addCfg={addCfg} />
        : <RevenuePopupICD ship={active} patch={(np) => patch(active.id, np)} onSave={() => commitDirty()} isDirty={isDirty} onClose={() => setModal(null)} cfg={cfg} addCfg={addCfg} />)}
      {active && modal.type === "info" && <InfoPopup ship={active} isHph={isHph} patch={(np) => patch(active.id, np)} patchOther={(id, np) => patch(id, np)} onSave={() => commitDirty()} isDirty={isDirty} siblings={sibs.filter((x) => x.id !== active.id)} onClose={closeInfo} onDelete={active._new ? null : () => delShip(active.id)} canDelete={T.canDelete} cfg={cfg} addCfg={addCfg} />}

      {showImport && (
        <Modal title="Import lô hàng từ Excel" subtitle="Nơi lấy/hạ nhập theo TÊN hoặc KÝ HIỆU · file mẫu có sẵn danh mục hợp lệ · kiểm tra trước, 1 lỗi là không import gì cả" width={720} icon={<I.truck />}
          onClose={() => setShowImport(false)}
          footer={
            <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 12 }}>
              <div style={{ fontSize: 12.5, color: impCheck ? (impCheck.valid ? "var(--good)" : "var(--danger)") : "var(--ink-3)", fontWeight: impCheck ? 600 : 400 }}>
                {impCheck ? (impCheck.valid ? `✓ ${impCheck.total} dòng hợp lệ` : `${impCheck.errors.length} dòng lỗi — chưa import gì`) : (impWb ? "Đã chọn file — bấm Kiểm tra" : "Chọn file Excel để bắt đầu")}
              </div>
              <div style={{ display: "flex", gap: 10 }}>
                <Btn onClick={() => setShowImport(false)}>Đóng</Btn>
                {impCheck && impCheck.valid && <Btn variant="primary" onClick={doImport}>{impBusy ? "Đang nhập…" : `Bắt đầu import ${impCheck.total} lô`}</Btn>}
              </div>
            </div>
          }>
          <div style={{ padding: "12px 0 4px" }}>
            <div style={{ display: "flex", gap: 8, alignItems: "center", marginBottom: 14, flexWrap: "wrap" }}>
              <button type="button" onClick={downloadTemplate}
                style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "9px 14px", fontSize: 13, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: "var(--ink-2)", cursor: "pointer" }}>
                <i className="bi bi-download" /> Tải file mẫu
              </button>
              <button type="button" onClick={() => impFileRef.current && impFileRef.current.click()}
                style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "9px 14px", fontSize: 13, fontWeight: 600, border: "none", borderRadius: 9, background: "var(--accent)", color: "#fff", cursor: "pointer" }}>
                <i className="bi bi-file-earmark-arrow-up" /> {impWb ? "Chọn file khác" : "Chọn file Excel"}
              </button>
              {impWb && <span style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Đã đọc file · {impWb.names.length} sheet</span>}
            </div>

            {impWb && (
              <div style={{ display: "flex", gap: 10, alignItems: "flex-end", marginBottom: 14 }}>
                <label style={{ flex: 1 }}>
                  <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>Sheet cần import</div>
                  <div style={{ position: "relative" }}>
                    <select value={impSheet} onChange={(e) => { setImpSheet(e.target.value); setImpCheck(null); }}
                      style={{ width: "100%", appearance: "none", WebkitAppearance: "none", padding: "9px 28px 9px 11px", fontSize: 13.5, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 10, background: "#fff", cursor: "pointer" }}>
                      {impWb.names.map((n) => <option key={n} value={n}>{n}</option>)}
                    </select>
                    <span style={{ position: "absolute", right: 10, top: "50%", transform: "translateY(-50%)", color: "var(--ink-3)", pointerEvents: "none" }}><I.chev /></span>
                  </div>
                </label>
                <Btn onClick={doCheck}>{impBusy && !impCheck ? "Đang kiểm tra…" : "Kiểm tra dữ liệu"}</Btn>
              </div>
            )}

            {impCheck && impCheck.valid && (() => {
              const expand = (r) => { const cs = String(r.contNo || "").split(/[\r\n;,]+/).map((s) => s.trim()).filter(Boolean); if (cs.length) return cs.length; const q = parseInt(String(r.qty || "").replace(/[^\d]/g, ""), 10); return q > 0 ? q : 1; };
              const totalLo = impRows.reduce((a, r) => a + expand(r), 0);
              const cellP = { padding: "7px 12px", borderBottom: "1px solid var(--line-2)", color: "var(--ink-2)" };
              return (
                <div style={{ border: "1px solid #bfe4d1", borderRadius: 10, overflow: "hidden" }}>
                  <div style={{ fontSize: 13, fontWeight: 700, color: "var(--good)", padding: "10px 13px", background: "var(--good-weak)", borderBottom: "1px solid #bfe4d1" }}>
                    <i className="bi bi-check-circle-fill" /> {impCheck.total} dòng hợp lệ → sẽ tạo <b>{totalLo} lô</b>. Xem trước bên dưới rồi bấm <b>Bắt đầu import</b>.
                  </div>
                  <div style={{ maxHeight: "40vh", overflowY: "auto", overscrollBehavior: "contain" }}>
                    <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12.5 }}>
                      <thead>
                        <tr style={{ background: "#fafbfc" }}>
                          {["#", "Khách hàng", "Tuyến", "Loại", "Số lô", "Ngày"].map((h, i) => (
                            <th key={i} style={{ textAlign: i >= 4 ? "center" : "left", padding: "7px 12px", fontSize: 11, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.04em", borderBottom: "1px solid var(--line)", position: "sticky", top: 0, background: "#fafbfc", whiteSpace: "nowrap" }}>{h}</th>
                          ))}
                        </tr>
                      </thead>
                      <tbody>
                        {impRows.map((r, i) => (
                          <tr key={i}>
                            <td className="tnum" style={cellP}>{i + 1}</td>
                            <td style={cellP}>{r.customer || "—"}</td>
                            <td style={cellP}>{(r.from || "?")} <span style={{ color: "var(--accent)" }}>→</span> {(r.to || "?")}</td>
                            <td style={cellP}>{r.contType || "—"}</td>
                            <td className="tnum" style={{ ...cellP, textAlign: "center", fontWeight: 600 }}>{expand(r)}</td>
                            <td className="tnum" style={{ ...cellP, whiteSpace: "nowrap" }}>{(r.gioDenDuKien || "").slice(0, 10) || "—"}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              );
            })()}

            {impCheck && !impCheck.valid && (
              <div style={{ border: "1px solid #f3c9c9", borderRadius: 10, overflow: "hidden" }}>
                <div style={{ fontSize: 13, fontWeight: 700, color: "var(--danger)", padding: "10px 13px", background: "#fef6f6", borderBottom: "1px solid #f3c9c9" }}>
                  <i className="bi bi-exclamation-triangle-fill" /> {impCheck.errors.length} dòng lỗi — sửa file rồi Kiểm tra lại. Chưa import gì cả.
                </div>
                <div style={{ maxHeight: "44vh", overflowY: "auto", overscrollBehavior: "contain" }}>
                  <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12.5 }}>
                    <thead>
                      <tr style={{ background: "#fafbfc" }}>
                        {["Dòng", "Booking", "Khách hàng", "Lý do"].map((h, i) => (
                          <th key={i} style={{ textAlign: "left", padding: "7px 12px", fontSize: 11, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.04em", borderBottom: "1px solid var(--line)", position: "sticky", top: 0, background: "#fafbfc", whiteSpace: "nowrap" }}>{h}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {impCheck.errors.map((er, i) => (
                        <tr key={i}>
                          <td className="tnum" style={{ padding: "7px 12px", borderBottom: "1px solid var(--line-2)", fontWeight: 600, color: "var(--ink-2)", whiteSpace: "nowrap" }}>{er.line}</td>
                          <td className="tnum" style={{ padding: "7px 12px", borderBottom: "1px solid var(--line-2)", color: "var(--ink-2)" }}>{er.booking || "—"}</td>
                          <td style={{ padding: "7px 12px", borderBottom: "1px solid var(--line-2)", color: "var(--ink-2)" }}>{er.customer || "—"}</td>
                          <td style={{ padding: "7px 12px", borderBottom: "1px solid var(--line-2)", color: "var(--danger)" }}>{(er.reasons || []).join("; ")}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}

            {impMsg && <div style={{ fontSize: 12.5, fontWeight: 600, marginTop: 10, color: impMsg.startsWith("Đã nhập") ? "var(--good)" : "var(--danger)" }}>{impMsg}</div>}
          </div>
        </Modal>
      )}
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<ShipmentsApp />);

