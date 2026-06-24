import React from "react";
const { useState, useMemo, useEffect, useRef } = React;
import { I, fmtVND, fmtShort, fmtDate, calcCost, calcVeh, calcRev, calcVehICD, calcRevICD, calcFreeTime, fmtHours, toNum, Modal, Btn, Combo, MultiCombo, useIsMobile, DateField } from "@trk/lib.jsx";
import { CostPopup, InfoPopup, colorHex } from "@trk/pop.jsx";
import { SortBtn, CellBtn, Badge, EditCell, TH, TD } from "@trk/ui.jsx";
import { loCountOf, parseImportRows, buildTemplateWb } from "./excel.js";

// Chip số INV — nổi bật để kế toán dễ dò
const invChip = { display: "inline-flex", alignItems: "center", gap: 4, fontSize: 12, fontWeight: 700, color: "var(--accent)", background: "var(--accent-weak-2)", border: "1px solid var(--accent-weak)", padding: "1px 8px", borderRadius: 7 };
const invChipLbl = { fontSize: 9.5, fontWeight: 800, letterSpacing: ".04em", opacity: .75 };
const tagChip = { display: "inline-flex", alignItems: "center", fontSize: 10.5, fontWeight: 600, color: "var(--accent)", background: "var(--accent-weak-2)", border: "1px solid var(--accent-weak)", padding: "1px 7px", borderRadius: 999, whiteSpace: "nowrap" };
// Màu badge Nhập/Xuất/Khác cho dễ phân biệt: Nhập=xanh dương · Xuất=xanh lá · Khác=hổ phách
const ioTone = (io) => { const v = (io || "").toLowerCase(); return v.includes("nh") ? "blue" : v.includes("xu") ? "good" : "amber"; };

function ShipmentsApp() {
  const isMobile = useIsMobile();
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
  // Mở từ trang khác (Lộ trình / Bảng kê): ?q=<cont> để LỌC, ?open=1 để TỰ MỞ popup lô.
  const _initSp = new URLSearchParams(window.location.search);
  const [q, setQ] = useState(_initSp.get("q") || "");
  const [qDeb, setQDeb] = useState(_initSp.get("q") || "");   // q sau debounce (param thật gửi server)
  const pendingOpen = useRef(_initSp.get("open"));            // truthy → tự mở popup dòng đầu sau khi tải lọc
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(P0.perPage || 20);   // số lô / trang (chọn được)
  const [selIds, setSelIds] = useState(() => new Set());      // lô đang tích (thao tác hàng loạt)
  const [showBulk, setShowBulk] = useState(false);            // popup sửa hàng loạt
  const [bulkTo, setBulkTo] = useState("");                   // Nơi hạ (cảng) áp hàng loạt
  const [bulkBargeDrop, setBulkBargeDrop] = useState("");     // Nơi hạ sà lan áp hàng loạt
  const [bulkBusy, setBulkBusy] = useState(false);
  const [filter, setFilter] = useState("all");
  // Bộ lọc theo "follow": 'all' | 'any' | 'missing' | '#hex' (lọc theo màu cụ thể)
  const [followFilter, setFollowFilter] = useState("all");
  const [toLocSel, setToLocSel] = useState([]);   // lọc theo NƠI HẠ theo KÝ HIỆU — CHỌN NHIỀU (OR)
  const [toLocs, setToLocs] = useState(P0.toLocs || []);   // danh sách KÝ HIỆU nơi hạ thực có (options)
  const [toMode, setToMode] = useState("include");         // GỒM | LOẠI TRỪ nơi hạ
  const [fromLocSel, setFromLocSel] = useState([]);        // lọc theo NƠI LẤY (ký hiệu) — chọn nhiều
  const [fromMode, setFromMode] = useState("exclude");     // GỒM (include) | LOẠI TRỪ (exclude) nơi lấy
  const [fromLocs, setFromLocs] = useState(P0.fromLocs || []);
  const [denDate, setDenDate] = useState("");     // lọc theo Giờ đến kế hoạch (gio_den_du_kien) — chọn 1 NGÀY
  const [tagSel, setTagSel] = useState([]);        // lọc theo NHÃN — chọn nhiều (OR)
  const [tagOptions, setTagOptions] = useState(P0.tagOptions || []);
  const [showFilters, setShowFilters] = useState(false);   // mở/thu panel bộ lọc chi tiết
  const [sort, setSort] = useState({ key: "default", dir: 1 });
  const [showExport, setShowExport] = useState(false);
  const [exporting, setExporting] = useState(false);   // chống bấm Xuất Excel nhiều lần
  const [expFrom, setExpFrom] = useState("");
  const [expTo, setExpTo] = useState("");
  const [expNotOut, setExpNotOut] = useState(false);   // chỉ xuất cont CHƯA RA
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
    if (perPage && perPage !== 20) p.set("perPage", perPage);
    if (qDeb.trim()) p.set("q", qDeb.trim());
    if (filter !== "all") p.set("filter", filter);
    if (followFilter !== "all") p.set("follow", followFilter);
    (toLocSel || []).forEach((v) => p.append("toLoc[]", v));   // chọn nhiều ký hiệu nơi hạ → OR
    if (toLocSel && toLocSel.length) p.set("toMode", toMode);
    (fromLocSel || []).forEach((v) => p.append("fromLoc[]", v));
    if (fromLocSel && fromLocSel.length) p.set("fromMode", fromMode);
    if (denDate) p.set("denDate", denDate);
    (tagSel || []).forEach((v) => p.append("tags[]", v));
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
        if (r.toLocs) setToLocs(r.toLocs);
        if (r.fromLocs) setFromLocs(r.fromLocs);
        if (r.tagOptions) setTagOptions(r.tagOptions);
        if (r.sibs) setSibs(r.sibs);
        if (r.page !== pg) setPage(r.page);
        // Tự mở popup lô (mở từ Lộ trình/Bảng kê với ?open) — chỉ 1 lần, dòng khớp cont (hoặc dòng đầu).
        if (pendingOpen.current && (r.data || []).length) {
          const want = String(pendingOpen.current);
          const m = (r.data).find((s) => String(s.id) === want) || (r.data).find((s) => (s.contNo || "").toString() === want) || r.data[0];
          pendingOpen.current = null;
          if (m) setTimeout(() => openModal({ id: m.id, type: "info" }), 0);
        }
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
  }, [page, perPage, qDeb, filter, followFilter, toLocSel, toMode, fromLocSel, fromMode, denDate, tagSel, sort]);
  // Mở từ Lộ trình/Bảng kê (?q/?open): boot là danh sách CHƯA lọc → tải lại theo q ngay + tự mở popup.
  useEffect(() => { if (_initSp.get("q") || _initSp.get("open")) { skipFirst.current = false; load(); } }, []);

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
  // Giờ ra dạng gọn cho badge "Đã ra": "dd/mm HH:MM" (chỉ ngày → "dd/mm")
  const fmtRa = (v) => { v = v || ""; const m = /^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?/.exec(v); return m ? `${m[3]}/${m[2]}${m[4] ? " " + m[4] + ":" + m[5] : ""}` : ""; };

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
        if (key === "locations")  { partial.locationCode = n.locationCode;  partial.locationCodeArr = n.locationCodeArr; partial.locationsIdArr = n.locationsIdArr; }
        if (key === "warehouses") { partial.warehouseCode = n.warehouseCode; partial.warehouseCodeArr = n.warehouseCodeArr; partial.warehousesIdArr = n.warehousesIdArr; }
      }
      if (url) api("PUT", url, { cfg: partial });
    }, 700);
  };
  const addCfg = (key, v, opts) => setCfgState((c) => {
    if ((c[key] || []).includes(v)) return c;
    const n = { ...c, [key]: [...(c[key] || []), v] };
    if (key === "locations")  { n.locationCodeArr  = [...(c.locationCodeArr  || []), ""]; n.locationsIdArr  = [...(c.locationsIdArr  || []), null]; }   // giữ mảng mã + id thẳng hàng
    if (key === "warehouses") { n.warehouseCodeArr = [...(c.warehouseCodeArr || []), ""]; n.warehousesIdArr = [...(c.warehousesIdArr || []), null]; }
    // Biển số gõ nhanh ở ô BKS lô hàng → mặc định "Xe ngoài" (không tự lọt vào đội xe MBF)
    if (key === "vehicles" && opts && opts.external) n.vehicleType = { ...(c.vehicleType || {}), [v]: "Ngoài" };
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
        const res = await api("PUT", ROUTES.shipment + (ship.hashid || id), { sheet, ship: partial, fields });
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
    const base = { id: tmpId, _new: true, customer: "", booking: "", io: "Nhập", contNo: "", contType: "40HC", kho: "", bksVao: "", bksRa: "", from: "ICD Quế Võ", to: "", contDen: "", contRa: "", cutOff: "", gioDenDuKien: "", gioXeRa: "", gioXeRaXe: "", cost: { items: [] }, rev: { vatRate: vat, doanhThu: [], choHo: [], payments: [] } };
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
    const res = await api("DELETE", ROUTES.shipment + ((s && s.hashid) || id));
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
      // filter=notout → server trả CHỈ cont chưa ra (cùng quy tắc tab "Chưa ra")
      const r = await window.trkApi("GET", ROUTES.shipmentsPage + "?all=1" + (expNotOut ? "&filter=notout" : ""));
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

  // ---- Import lô hàng từ Excel ---- (logic dựng/parse Excel thuần ở ./excel.js)
  const downloadTemplate = async () => {
    if (typeof XLSX === "undefined") { window.alert("Thư viện Excel chưa tải xong."); return; }
    const c = (await ensureCfg()) || cfgRef.current || {};   // đảm bảo danh mục (địa điểm/kho/khách) đã nạp trước khi dựng file mẫu
    XLSX.writeFile(buildTemplateWb(c), "mau-import-lo-hang.xlsx");
  };
  const onImpFile = (e) => {
    const f = e.target.files && e.target.files[0]; e.target.value = "";
    if (!f) return;
    if (typeof XLSX === "undefined") { setImpMsg("Thư viện Excel chưa tải xong."); return; }
    setImpMsg(""); setImpCheck(null); setImpRows([]);
    const rd = new FileReader();
    rd.onload = () => { const wb = XLSX.read(rd.result, { type: "array", cellDates: true }); setImpWb({ names: wb.SheetNames, wb }); setImpSheet(wb.SheetNames[0] || ""); };
    rd.readAsArrayBuffer(f);
  };
  const doCheck = async () => {
    if (!impWb || !impSheet) return;
    setImpBusy(true); setImpMsg(""); setImpCheck(null);
    const out = parseImportRows(impWb.wb, impSheet); setImpRows(out);
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
  const setPerPageP = (n) => { setPerPage(n); setPage(1); };   // đổi số/trang → về trang 1
  // ----- Chọn nhiều lô + thao tác hàng loạt -----
  const isSel = (id) => selIds.has(id);
  const toggleSel = (id) => setSelIds((p) => { const n = new Set(p); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const pageIds = () => rows.filter((s) => !String(s.id).startsWith("tmp")).map((s) => s.id);
  const allPageSel = () => { const ids = pageIds(); return ids.length > 0 && ids.every((id) => selIds.has(id)); };
  const toggleSelAllPage = () => setSelIds((p) => { const ids = pageIds(); const n = new Set(p); ids.every((id) => n.has(id)) ? ids.forEach((id) => n.delete(id)) : ids.forEach((id) => n.add(id)); return n; });
  const clearSel = () => setSelIds(new Set());
  const openBulk = () => { ensureCfg(); setBulkTo(""); setBulkBargeDrop(""); setShowBulk(true); };
  const doBulk = async () => {
    const ids = [...selIds];
    const ship = {};
    if (bulkTo) ship.to = bulkTo;
    if (bulkBargeDrop) ship.bargeDrop = bulkBargeDrop;
    if (!ids.length || (!ship.to && !ship.bargeDrop) || bulkBusy) return;
    setBulkBusy(true);
    try {
      const res = await api("POST", ROUTES.shipmentBulk, { ids, ship });
      if (res && res.ok) {
        window.trkToast && window.trkToast(`Đã cập nhật ${res.updated} lô`, "success");
        setShowBulk(false); clearSel(); load();
      } else { window.trkToast && window.trkToast("Lỗi cập nhật hàng loạt", "error"); }
    } catch (e) { window.trkToast && window.trkToast("Lỗi cập nhật hàng loạt", "error"); }
    finally { setBulkBusy(false); }
  };
  const locCodeList = () => [...new Set(Object.values(cfg.locationCode || {}).filter(Boolean))].sort();
  const setFilterP = (f) => { setFilter(f); setPage(1); };
  const setFollowP = (f) => { setFollowFilter(f); setPage(1); };
  const setToLocP = (arr) => { setToLocSel(arr); setPage(1); };   // chọn nhiều ký hiệu nơi hạ (OR)
  const setDenDateP = (v) => { setDenDate(v); setPage(1); };      // lọc theo Giờ đến kế hoạch (1 ngày)
  const setTagP = (arr) => { setTagSel(arr); setPage(1); };       // lọc theo nhãn
  // Số bộ lọc chi tiết đang bật + xóa tất cả (để hiện badge / nút Xóa lọc)
  const activeFilters = (toLocSel.length ? 1 : 0) + (fromLocSel.length ? 1 : 0) + (denDate ? 1 : 0) + (tagSel.length ? 1 : 0) + (followFilter !== "all" ? 1 : 0);
  const clearFilters = () => { setToLocSel([]); setFromLocSel([]); setDenDate(""); setTagSel([]); setFollowFilter("all"); setPage(1); };
  // 1 ô lọc trong panel: nhãn nhỏ phía trên + control phía dưới (gọn, thẳng hàng)
  const FF = ({ label, icon, children }) => (
    <div style={{ display: "flex", flexDirection: "column", gap: 5 }}>
      <span style={{ fontSize: 11, fontWeight: 700, color: "var(--ink-4)", textTransform: "uppercase", letterSpacing: ".03em" }}>{icon ? <i className={"bi " + icon} style={{ marginRight: 4 }} /> : null}{label}</span>
      <div style={{ display: "inline-flex", alignItems: "center", gap: 6 }}>{children}</div>
    </div>
  );
  // chip tóm tắt 1 bộ lọc đang bật (khi thu gọn) — bấm ✕ để bỏ
  const FChip = ({ children, onClear }) => (
    <span style={{ display: "inline-flex", alignItems: "center", gap: 6, fontSize: 12, fontWeight: 600, color: "var(--accent)", background: "var(--accent-weak-2)", border: "1px solid var(--accent-weak)", padding: "3px 6px 3px 10px", borderRadius: 999 }}>
      {children}
      <button type="button" onClick={onClear} title="Bỏ lọc này" style={{ border: "none", background: "transparent", color: "var(--accent)", cursor: "pointer", display: "grid", placeItems: "center", padding: 0, width: 15, height: 15 }}><I.x /></button>
    </span>
  );
  const setToModeP = (m) => { setToMode(m); setPage(1); };
  const setFromLocP = (arr) => { setFromLocSel(arr); setPage(1); };
  const setFromModeP = (m) => { setFromMode(m); setPage(1); };
  // Toggle Gồm/Loại trừ dùng chung cho Nơi hạ + Nơi lấy
  const ModeToggle = ({ mode, onMode }) => (
    <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 8, padding: 2 }}>
      {[["exclude", "Loại trừ"], ["include", "Gồm"]].map(([m, label]) => {
        const on = mode === m;
        return (
          <button key={m} type="button" onClick={() => onMode(m)} title={m === "exclude" ? "Bỏ các lô khớp ký hiệu chọn" : "Chỉ lô khớp ký hiệu chọn"}
            style={{ border: "none", cursor: "pointer", fontSize: 11.5, fontWeight: 700, padding: "4px 9px", borderRadius: 6,
              background: on ? "#fff" : "transparent", color: on ? (m === "exclude" ? "var(--danger)" : "var(--ink)") : "var(--ink-4)", boxShadow: on ? "0 1px 2px rgba(16,19,23,.12)" : "none" }}>{label}</button>
        );
      })}
    </div>
  );
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
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: isMobile ? "10px 14px" : "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: isMobile ? 10 : 14, height: isMobile ? "auto" : 58, flexWrap: "wrap" }}>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center", flexShrink: 0 }}><I.truck /></div>
          <div style={{ fontSize: 15.5, fontWeight: 700, letterSpacing: "-0.01em" }}>Lô hàng</div>
          <div style={{ flex: 1 }} />
          <div style={{ position: "relative", order: isMobile ? 5 : 0, flex: isMobile ? "1 1 100%" : "0 0 auto" }}>
            <span style={{ position: "absolute", left: 11, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)" }}><I.search /></span>
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Tìm khách, booking, container…"
              style={{ width: isMobile ? "100%" : 260, padding: "9px 12px 9px 34px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 10, outline: "none", background: "#fafbfc" }}
              onFocus={(e) => { e.target.style.borderColor = "var(--accent)"; e.target.style.background = "#fff"; }}
              onBlur={(e) => { e.target.style.borderColor = "var(--line)"; e.target.style.background = "#fafbfc"; }} />
          </div>
          {ROUTES.plan && (
            <a href={ROUTES.plan} title="Tạo link kế hoạch cho lái xe cập nhật giờ xe"
              style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "9px 14px", fontSize: 13.5, fontWeight: 600, cursor: "pointer", color: "var(--ink-2)", background: "#fff", border: "1px solid var(--line)", borderRadius: 10, textDecoration: "none" }}
              onMouseEnter={(e) => (e.currentTarget.style.background = "var(--line-2)")} onMouseLeave={(e) => (e.currentTarget.style.background = "#fff")}>
              <i className="bi bi-link-45deg" style={{ color: "var(--accent)" }} /> Link kế hoạch
            </a>
          )}
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
                <div style={{ position: "absolute", top: "calc(100% + 6px)", right: 0, zIndex: 1201, width: "min(308px, calc(100vw - 28px))", background: "#fff", border: "1px solid var(--line)", borderRadius: 12, boxShadow: "0 12px 32px -8px rgba(16,19,23,.24), 0 2px 8px rgba(16,19,23,.08)", padding: 14 }}>
                  <div style={{ fontSize: 13, fontWeight: 700, color: "var(--ink)", marginBottom: 3 }}>Xuất Excel</div>
                  <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginBottom: 11, lineHeight: 1.5 }}>Lọc theo <b style={{ color: "var(--ink-3)" }}>ngày kế hoạch</b> (Giờ đến kế hoạch). Để trống = xuất tất cả {pageInfo.total} lô.</div>
                  <div style={{ display: "flex", gap: 8, marginBottom: 11 }}>
                    <div style={{ flex: 1 }}><div style={{ fontSize: 11, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>Từ ngày</div>
                      <DateField value={expFrom} onChange={setExpFrom} /></div>
                    <div style={{ flex: 1 }}><div style={{ fontSize: 11, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>Đến ngày</div>
                      <DateField value={expTo} onChange={setExpTo} /></div>
                  </div>
                  <label style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 11, cursor: "pointer", fontSize: 12.5, color: "var(--ink-2)", fontWeight: 500 }}>
                    <input type="checkbox" checked={expNotOut} onChange={(e) => setExpNotOut(e.target.checked)} style={{ width: 16, height: 16, accentColor: "var(--accent)", cursor: "pointer" }} />
                    Chỉ xuất cont <b style={{ color: "var(--warn)" }}>chưa ra</b>
                  </label>
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

      {/* filter bar — Hàng 1: trạng thái + nút Bộ lọc; Hàng 2 (thu gọn): bộ lọc chi tiết */}
      <div style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: isMobile ? "10px 14px" : "10px 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 10, flexWrap: "wrap" }}>
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
          {/* Nút mở panel bộ lọc chi tiết — badge = số bộ lọc đang bật */}
          <button type="button" onClick={() => setShowFilters((v) => !v)}
            style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "7px 12px", fontSize: 12.5, fontWeight: 600, borderRadius: 9, cursor: "pointer",
              border: "1px solid " + (activeFilters || showFilters ? "var(--accent)" : "var(--line)"), background: activeFilters || showFilters ? "var(--accent-weak-2)" : "#fff", color: activeFilters || showFilters ? "var(--accent)" : "var(--ink-2)", transition: "all .12s" }}>
            <i className="bi bi-funnel" /> Bộ lọc
            {activeFilters > 0 && <span className="tnum" style={{ fontSize: 11, fontWeight: 700, background: "var(--accent)", color: "#fff", padding: "0 6px", borderRadius: 999, minWidth: 16, textAlign: "center" }}>{activeFilters}</span>}
            <i className={"bi " + (showFilters ? "bi-chevron-up" : "bi-chevron-down")} style={{ fontSize: 10 }} />
          </button>
          {activeFilters > 0 && (
            <button type="button" onClick={clearFilters} style={{ fontSize: 12, color: "var(--danger)", background: "transparent", border: "none", cursor: "pointer", fontWeight: 600 }}>Xóa lọc</button>
          )}
          {/* Chip tóm tắt bộ lọc đang bật (chỉ khi thu gọn) */}
          {!showFilters && activeFilters > 0 && (
            <div style={{ display: "inline-flex", gap: 6, flexWrap: "wrap", alignItems: "center" }}>
              {toLocSel.length > 0 && <FChip onClear={() => setToLocP([])}>{toMode === "exclude" ? "Hạ ⊘ " : "Hạ: "}{toLocSel.join(", ")}</FChip>}
              {fromLocSel.length > 0 && <FChip onClear={() => setFromLocP([])}>{fromMode === "exclude" ? "Lấy ⊘ " : "Lấy: "}{fromLocSel.join(", ")}</FChip>}
              {denDate && <FChip onClear={() => setDenDateP("")}>Đóng hàng {fmtDate(denDate)}</FChip>}
              {tagSel.length > 0 && <FChip onClear={() => setTagP([])}>Nhãn: {tagSel.join(", ")}</FChip>}
              {followFilter !== "all" && <FChip onClear={() => setFollowP("all")}>Theo dõi: {followFilter === "missing" ? "chưa số HĐ" : followFilter === "any" ? "có theo dõi" : "màu"}</FChip>}
            </div>
          )}
          <div style={{ flex: 1 }} />
          {loading && <span style={{ fontSize: 12, color: "var(--accent)", display: "inline-flex", alignItems: "center", gap: 5 }}><i className="bi bi-arrow-repeat" style={{ animation: "trk-spin 0.7s linear infinite" }} /> Đang tải…</span>}
          {sort.key !== "default" && (
            <button type="button" onClick={() => setSort({ key: "default", dir: 1 })}
              style={{ fontSize: 12, color: "var(--accent)", background: "transparent", border: "none", cursor: "pointer", fontWeight: 600 }}>↺ Bỏ sắp xếp</button>
          )}
          {/* Số lô / trang */}
          <label style={{ display: "inline-flex", alignItems: "center", gap: 6, fontSize: 12, color: "var(--ink-4)" }}>
            Hiện
            <select value={perPage} onChange={(e) => setPerPageP(Number(e.target.value))}
              style={{ fontSize: 12.5, fontWeight: 600, color: "var(--ink-2)", border: "1px solid var(--line)", borderRadius: 8, padding: "5px 7px", background: "#fff", cursor: "pointer", outline: "none" }}>
              {[20, 50, 100, 200].map((n) => <option key={n} value={n}>{n}</option>)}
            </select>
            lô/trang
          </label>
          <span style={{ fontSize: 12, color: "var(--ink-4)" }}>{pageInfo.total} lô · bấm tiêu đề cột để sắp xếp</span>
        </div>

        {/* Hàng 2: panel bộ lọc chi tiết (thu gọn được) */}
        {showFilters && (
          <div style={{ marginTop: 11, paddingTop: 12, borderTop: "1px solid var(--line-2)", display: "flex", gap: 20, flexWrap: "wrap", alignItems: "flex-end" }}>
            <FF label="Nơi hạ" icon="bi-geo-alt-fill">
              <ModeToggle mode={toMode} onMode={setToModeP} />
              <div style={{ width: isMobile ? 170 : 200 }}><MultiCombo values={toLocSel} onChange={setToLocP} options={toLocs} placeholder={toMode === "exclude" ? "Không trừ gì" : "Tất cả ký hiệu"} strict max={50} /></div>
            </FF>
            <FF label="Nơi lấy" icon="bi-box-arrow-up-right">
              <ModeToggle mode={fromMode} onMode={setFromModeP} />
              <div style={{ width: isMobile ? 170 : 200 }}><MultiCombo values={fromLocSel} onChange={setFromLocP} options={fromLocs} placeholder={fromMode === "exclude" ? "Không trừ gì" : "Tất cả ký hiệu"} strict max={50} /></div>
            </FF>
            <FF label="Ngày đóng hàng" icon="bi-calendar-event">
              <div style={{ width: 150 }}><DateField value={denDate} onChange={setDenDateP} placeholder="Chọn ngày" /></div>
              {denDate && <button type="button" onClick={() => setDenDateP("")} title="Bỏ lọc ngày" style={{ border: "none", background: "transparent", color: "var(--ink-4)", cursor: "pointer", padding: 2 }}><i className="bi bi-x-circle" /></button>}
            </FF>
            <FF label="Nhãn" icon="bi-tags">
              <div style={{ width: isMobile ? 180 : 220 }}><MultiCombo values={tagSel} onChange={setTagP} options={tagOptions} placeholder="Tất cả nhãn" strict max={50} /></div>
            </FF>
            {followStats.anyShips > 0 && (() => {
              const isOn = (k) => followFilter === k;
              const pillBase = { display: "inline-flex", alignItems: "center", gap: 6, border: "none", cursor: "pointer", fontSize: 12.5, fontWeight: 600, padding: "6px 11px", borderRadius: 7, transition: "all .12s" };
              return (
                <FF label="Theo dõi" icon="bi-flag">
                  <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 9, padding: 3, gap: 1 }}>
                    <button type="button" onClick={() => setFollowP("all")}
                      style={{ ...pillBase, background: isOn("all") ? "#fff" : "transparent", color: isOn("all") ? "var(--ink)" : "var(--ink-3)", boxShadow: isOn("all") ? "0 1px 2px rgba(16,19,23,.12)" : "none" }}>Tất cả</button>
                    <button type="button" onClick={() => setFollowP("missing")} title="Lô có khoản gắn theo dõi nhưng chưa điền số hóa đơn"
                      style={{ ...pillBase, background: isOn("missing") ? "#fff" : "transparent", color: isOn("missing") ? "var(--warn)" : "var(--ink-3)", boxShadow: isOn("missing") ? "0 1px 2px rgba(16,19,23,.12)" : "none" }}>
                      Chưa có số HĐ<span className="tnum" style={{ marginLeft: 6, fontSize: 11, fontWeight: 700, color: isOn("missing") ? "#fff" : "var(--ink-4)", background: isOn("missing") ? "var(--warn)" : "var(--line-2)", padding: "0 6px", borderRadius: 999, minWidth: 16, textAlign: "center" }}>{followStats.missShips}</span>
                    </button>
                    <button type="button" onClick={() => setFollowP("any")} title="Lô có ít nhất 1 khoản gắn theo dõi"
                      style={{ ...pillBase, background: isOn("any") ? "#fff" : "transparent", color: isOn("any") ? "var(--ink)" : "var(--ink-3)", boxShadow: isOn("any") ? "0 1px 2px rgba(16,19,23,.12)" : "none" }}>
                      Có theo dõi<span className="tnum" style={{ marginLeft: 6, fontSize: 11, fontWeight: 700, color: isOn("any") ? "var(--ink-3)" : "var(--ink-4)", background: isOn("any") ? "var(--line-2)" : "transparent", padding: "0 6px", borderRadius: 999, minWidth: 16, textAlign: "center" }}>{followStats.anyShips}</span>
                    </button>
                  </div>
                  {followStats.byColor.length > 0 && (
                    <div style={{ display: "inline-flex", gap: 4, alignItems: "center", marginLeft: 2 }}>
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
                </FF>
              );
            })()}
          </div>
        )}
      </div>

      {/* Thanh thao tác hàng loạt — hiện khi đã tích ít nhất 1 lô */}
      {selIds.size > 0 && (
        <div style={{ display: "flex", alignItems: "center", gap: 12, flexWrap: "wrap", background: "var(--accent-weak-2)", borderBottom: "1px solid var(--accent-weak)", padding: isMobile ? "9px 14px" : "9px 22px", flexShrink: 0 }}>
          <span style={{ fontSize: 13, fontWeight: 700, color: "var(--accent)" }}><i className="bi bi-check2-square" /> Đã chọn {selIds.size} lô</span>
          <button type="button" onClick={openBulk}
            style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "7px 14px", fontSize: 13, fontWeight: 600, border: "none", borderRadius: 9, background: "var(--accent)", color: "#fff", cursor: "pointer" }}>
            <i className="bi bi-pencil-square" /> Thao tác hàng loạt
          </button>
          <button type="button" onClick={clearSel} style={{ fontSize: 12.5, color: "var(--ink-3)", background: "transparent", border: "none", cursor: "pointer", fontWeight: 600 }}>Bỏ chọn</button>
        </div>
      )}

      {/* table */}
      <div style={{ flex: 1, minHeight: 0, display: "flex", flexDirection: "column", padding: isMobile ? "12px 12px 14px" : "16px 22px 14px" }}>
        <div style={{ flex: 1, minHeight: 0, background: isMobile ? "transparent" : "#fff", border: isMobile ? "none" : "1px solid var(--line)", borderRadius: 12, overflow: "auto" }}>
          {/* ===== Mobile: danh sách dạng card ===== */}
          {isMobile && (
            <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
              {rows.map((s) => {
                const cc = calcCost(s.cost); const m = metrics(s);
                const ft = !isHph ? calcFreeTime(s, cfg.freeTimeHours, cfg.freeTimeRules) : null;
                const out = !!(s.gioXeRa && s.gioXeRa.trim());   // "đã ra" = cont có Giờ xe ra của chính nó (không xét BKS / xe kéo cont khác)
                return (
                  <div key={s.id} onClick={() => openModal({ id: s.id, type: "info" })}
                    style={{ background: isSel(s.id) ? "var(--accent-weak-2)" : "#fff", border: "1px solid " + (isSel(s.id) ? "var(--accent-weak)" : "var(--line)"), borderRadius: 12, padding: "12px 14px", boxShadow: "0 1px 2px rgba(16,19,23,.04)" }}>
                    <div style={{ display: "flex", justifyContent: "space-between", gap: 10 }}>
                      {!String(s.id).startsWith("tmp") && (
                        <input type="checkbox" checked={isSel(s.id)} onClick={(e) => e.stopPropagation()} onChange={() => toggleSel(s.id)}
                          style={{ width: 18, height: 18, accentColor: "var(--accent)", cursor: "pointer", flexShrink: 0, marginTop: 2 }} />
                      )}
                      <div style={{ minWidth: 0, flex: 1 }}>
                        <div style={{ fontWeight: 600, fontSize: 15 }}>{s.customer || <span style={{ color: "var(--ink-4)", fontWeight: 400 }}>(chưa đặt tên)</span>}</div>
                        <div style={{ display: "flex", alignItems: "center", gap: 6, marginTop: 4, flexWrap: "wrap" }}>
                          <span className="tnum" style={{ fontSize: 12.5, color: "var(--ink-3)" }}>{s.booking || "—"}</span>
                          {s.io ? <Badge tone={ioTone(s.io)}>{s.io}</Badge> : null}
                          {s.cru ? <Badge tone="amber">CRU</Badge> : null}
                          {s.isBarge ? <Badge tone="blue">Sà lan{s.bargeCont ? " " + s.bargeCont : ""}</Badge> : null}
                        </div>
                      </div>
                      <span className="tnum" style={{ fontSize: 11.5, color: "var(--ink-4)", flexShrink: 0 }}>{String(s.id).startsWith("tmp") ? "mới" : ("#" + s.id)}</span>
                    </div>
                    <div style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 13, marginTop: 8 }}>
                      <span style={{ color: "var(--ink-2)" }}>{s.from || "—"}</span>
                      <span style={{ color: "var(--accent)", flexShrink: 0 }}><I.arrow /></span>
                      <span style={{ color: "var(--ink-2)" }}>{s.to || "—"}</span>
                    </div>
                    <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap", marginTop: 6 }}>
                      <span className="tnum" style={{ fontSize: 14.5, fontWeight: 700, color: "var(--ink)" }}>{s.contNo || "—"}</span>
                      <span className="tnum" style={{ fontSize: 12, color: "var(--ink-4)" }}>{s.contType}{s.kho ? " · " + s.kho : ""}</span>
                      {s.inv && <span className="tnum" style={{ display: "inline-flex", alignItems: "center", gap: 4, fontSize: 12, fontWeight: 700, color: "var(--accent)", background: "var(--accent-weak-2)", border: "1px solid var(--accent-weak)", padding: "1px 8px", borderRadius: 7 }}><span style={{ fontSize: 9.5, fontWeight: 800, letterSpacing: ".04em", opacity: .75 }}>INV</span> {s.inv}</span>}
                    </div>
                    <div style={{ display: "flex", gap: 6, flexWrap: "wrap", marginTop: 7 }}>
                      {s.gioDenDuKien && <span className="tnum" style={{ display: "inline-flex", alignItems: "center", gap: 5, fontSize: 10.5, fontWeight: 700, padding: "2px 8px", borderRadius: 999, color: "var(--accent)", background: "var(--accent-weak-2)" }}>
                        <i className="bi bi-calendar-check" />KH đến · {fmtCM(s.gioDenDuKien)}</span>}
                      <span style={{ display: "inline-flex", alignItems: "center", gap: 5, fontSize: 10.5, fontWeight: 700, padding: "2px 8px", borderRadius: 999, color: out ? "var(--good)" : "var(--warn)", background: out ? "var(--good-weak)" : "#fcf3e2" }}>
                        <span style={{ width: 6, height: 6, borderRadius: 999, background: "currentColor" }} />{out ? ("Đã ra" + (fmtRa(s.gioXeRa) ? " · " + fmtRa(s.gioXeRa) : "") + (s.bksRa && s.bksRa.trim() ? " · " + s.bksRa : "")) : "Chưa ra"}</span>
                      {ft && <span style={{ display: "inline-flex", alignItems: "center", gap: 5, fontSize: 10.5, fontWeight: 700, padding: "2px 8px", borderRadius: 999, color: ft.connect ? "var(--good)" : "var(--danger)", background: ft.connect ? "var(--good-weak)" : "#fce8e8" }}>
                        <span style={{ width: 6, height: 6, borderRadius: 999, background: "currentColor" }} />{ft.connect ? "CONNECT" : "DISCONNECT"}</span>}
                    </div>
                    <div style={{ display: "flex", gap: 8, marginTop: 11, paddingTop: 11, borderTop: "1px solid var(--line-2)" }}>
                      <button type="button" onClick={(e) => { e.stopPropagation(); openModal({ id: s.id, type: "cost" }); }}
                        style={{ flex: 1, textAlign: "left", border: "1px solid var(--line)", borderRadius: 9, background: "#fafbfc", padding: "8px 11px", cursor: "pointer" }}>
                        <div style={{ fontSize: 10.5, color: "var(--ink-4)", fontWeight: 600, textTransform: "uppercase", letterSpacing: "0.04em" }}>Chi phí</div>
                        <div className="tnum" style={{ fontSize: 14, fontWeight: 700, marginTop: 2 }}>{fmtVND(m.cost)}</div>
                        {(s.tags || []).length > 0 && <div style={{ display: "flex", flexWrap: "wrap", gap: 4, marginTop: 6 }}>{s.tags.map((t, i) => <span key={i} style={tagChip}>{t}</span>)}</div>}
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
          {/* ===== Desktop: bảng ===== */}
          {!isMobile && (
          <table style={{ width: "100%", borderCollapse: "collapse", minWidth: minW }}>
            <thead>
              <tr>
                <TH w={36} align="center"><input type="checkbox" checked={allPageSel()} onChange={toggleSelAllPage} title="Chọn tất cả lô trong trang" style={{ width: 15, height: 15, accentColor: "var(--accent)", cursor: "pointer" }} /></TH>
                <TH w={48} align="center">ID</TH>
                <TH sticky><SortBtn k="customer" sort={sort} onSort={toggleSort}>Khách hàng</SortBtn></TH>
                <TH>Cont</TH>
                <TH>Tuyến</TH>
                <TH>Lịch trình</TH>
                <TH align="right"><SortBtn k="cost" sort={sort} onSort={toggleSort} align="right">Chi phí</SortBtn></TH>
                <TH w={172} align="center">Hành động</TH>
              </tr>
            </thead>
            <tbody>
              {rows.map((s, i) => {
                const cc = calcCost(s.cost);
                const m = metrics(s);
                const costMain = m.cost;
                const costSub = cc.thuChiHo > 0 ? `Chi hộ ${fmtShort(cc.thuChiHo)} · còn lại công ty` : (cc.tongChiPhi > 0 ? "Chi phí công ty" : "Xem chi tiết");
                return (
                  <tr key={s.id} style={{ transition: "background .1s", background: isSel(s.id) ? "var(--accent-weak-2)" : "transparent" }}
                    onMouseEnter={(e) => { if (!isSel(s.id)) e.currentTarget.style.background = "var(--accent-weak-2)"; }}
                    onMouseLeave={(e) => { if (!isSel(s.id)) e.currentTarget.style.background = "transparent"; }}>
                    <TD align="center">{String(s.id).startsWith("tmp") ? null : <input type="checkbox" checked={isSel(s.id)} onChange={() => toggleSel(s.id)} style={{ width: 15, height: 15, accentColor: "var(--accent)", cursor: "pointer" }} />}</TD>
                    <TD align="center"><span className="tnum" style={{ color: "var(--ink-4)", fontSize: 12.5 }} title="ID trong CSDL">{String(s.id).startsWith("tmp") ? "mới" : s.id}</span></TD>
                    <TD sticky>
                      <EditCell onClick={() => openModal({ id: s.id, type: "info" })}>
                        <div style={{ fontWeight: 600, fontSize: 13.5 }}>{s.customer || <span style={{ color: "var(--ink-4)", fontWeight: 400 }}>(chưa đặt tên)</span>}</div>
                        <div style={{ display: "flex", alignItems: "center", gap: 7, marginTop: 3, flexWrap: "wrap" }}>
                          <span style={{ fontSize: 12, color: "var(--ink-3)" }} className="tnum">{s.booking || "—"}</span>
                          {s.io ? <Badge tone={ioTone(s.io)}>{s.io}</Badge> : null}
                          {s.cru ? <Badge tone="amber">CRU</Badge> : null}
                          {s.isBarge ? <Badge tone="blue">Sà lan{s.bargeCont ? " " + s.bargeCont : ""}</Badge> : null}
                        </div>
                      </EditCell>
                    </TD>
                    <TD>
                      <EditCell onClick={() => openModal({ id: s.id, type: "info" })}>
                        {isHph ? (
                          <>
                            <div style={{ fontWeight: 700, fontSize: 13.5 }} className="tnum">{s.qty} × {s.contType}</div>
                            <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 2 }} className="tnum">{s.contNo || "—"}</div>
                            {s.inv && <div style={{ marginTop: 4 }}><span className="tnum" style={invChip}><span style={invChipLbl}>INV</span> {s.inv}</span></div>}
                          </>
                        ) : (
                          <>
                            <div style={{ fontWeight: 700, fontSize: 14.5, color: "var(--ink)" }} className="tnum">{s.contNo || "—"}</div>
                            <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 2 }} className="tnum">{s.contType}{s.kho ? " · " + s.kho : ""}</div>
                            {s.inv && <div style={{ marginTop: 4 }}><span className="tnum" style={invChip}><span style={invChipLbl}>INV</span> {s.inv}</span></div>}
                            {(() => { const out = !!(s.gioXeRa && s.gioXeRa.trim()); return (
                            <div style={{ display: "inline-flex", alignItems: "center", gap: 5, marginTop: 4, fontSize: 10.5, fontWeight: 700, padding: "2px 8px", borderRadius: 999,
                              color: out ? "var(--good)" : "var(--warn)", background: out ? "var(--good-weak)" : "#fcf3e2" }}>
                              <span style={{ width: 6, height: 6, borderRadius: 999, background: "currentColor" }} />
                              {out ? ("Đã ra" + (fmtRa(s.gioXeRa) ? " · " + fmtRa(s.gioXeRa) : "") + (s.bksRa && s.bksRa.trim() ? " · " + s.bksRa : "")) : "Chưa ra"}
                            </div>); })()}
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
                        {s.gioDenDuKien ? (
                          <div style={{ fontSize: 12.5, color: "var(--accent)", fontWeight: 700, marginBottom: 2 }} className="tnum">
                            <i className="bi bi-calendar-check" style={{ fontSize: 11, marginRight: 4 }} />KH đến: {fmtCM(s.gioDenDuKien)}
                          </div>
                        ) : null}
                        {isHph ? (
                          <>
                            <div style={{ fontSize: 12.5, color: "var(--ink-2)" }} className="tnum">Tàu: {fmtDate(s.sailDate) || "—"}</div>
                            <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 2 }}>Cắt máng {s.cutOff || "—"}</div>
                          </>
                        ) : (
                          <>
                            <div style={{ fontSize: 12.5, color: "var(--ink-2)" }} className="tnum">Cắt máng: {fmtCM(s.cutOff) || "—"}</div>
                            <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 2 }} className="tnum">Đến: {fmtDate(s.contDen) || "—"}</div>
                            {(() => { const ft = calcFreeTime(s, cfg.freeTimeHours, cfg.freeTimeRules); return ft ? (
                              <div style={{ display: "inline-flex", alignItems: "center", gap: 5, marginTop: 3, fontSize: 10.5, fontWeight: 700, padding: "2px 8px", borderRadius: 999, color: ft.connect ? "var(--good)" : "var(--danger)", background: ft.connect ? "var(--good-weak)" : "#fce8e8" }}>
                                <span style={{ width: 6, height: 6, borderRadius: 999, background: "currentColor" }} />{ft.connect ? "CONNECT" : "DISCONNECT"} <span style={{ fontWeight: 500, opacity: .8 }}>· {fmtHours(ft.hours)}</span>
                              </div>
                            ) : <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 2 }} className="tnum">Ra: {fmtRa(s.gioXeRa) || "—"}</div>; })()}
                          </>
                        )}
                      </EditCell>
                    </TD>
                    <TD pad="6px 10px">
                      <CellBtn main={fmtVND(costMain)} sub={costSub} onClick={() => openModal({ id: s.id, type: "cost" })} />
                      {(() => {
                        const miss = ((s.cost && s.cost.items) || []).filter((it) => it.item && costColors[it.item] && !String(it.invoiceNo || "").trim());
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
                      {(s.tags || []).length > 0 && <div style={{ display: "flex", flexWrap: "wrap", gap: 4, marginTop: 5, paddingLeft: 9 }}>{s.tags.map((t, i) => <span key={i} style={tagChip}>{t}</span>)}</div>}
                    </TD>
                    <TD align="center">
                      <div style={{ display: "inline-flex", flexDirection: "column", gap: 5, alignItems: "stretch" }}>
                        <button type="button" onClick={() => openModal({ id: s.id, type: "cost" })} title="Chi phí lô hàng (chi hộ / công ty)"
                          style={{ display: "inline-flex", alignItems: "center", justifyContent: "center", gap: 5, whiteSpace: "nowrap", padding: "6px 11px", border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-2)", cursor: "pointer", fontWeight: 600, fontSize: 12.5 }}
                          onMouseEnter={(e) => { e.currentTarget.style.background = "var(--line-2)"; }}
                          onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; }}>
                          <i className="bi bi-receipt" /> Chi cho lô hàng
                        </button>
                      </div>
                    </TD>
                  </tr>
                );
              })}
            </tbody>
          </table>
          )}
          {rows.length === 0 && <div style={{ padding: "40px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5, background: isMobile ? "#fff" : "transparent", border: isMobile ? "1px solid var(--line)" : "none", borderRadius: 12 }}>
            {loading ? "Đang tải…" : (qDeb || filter !== "all" || followFilter !== "all" ? (
              <>
                Không có lô nào khớp <b style={{ color: "var(--ink-3)" }}>tất cả</b> bộ lọc đang chọn{(filter !== "all" && followFilter !== "all") ? " (trạng thái ra + theo dõi cộng dồn)" : ""}.
                <div style={{ marginTop: 10 }}>
                  <button type="button" onClick={() => { setQ(""); setFilterP("all"); setFollowP("all"); setSort({ key: "default", dir: 1 }); }}
                    style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 13px", fontSize: 12.5, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: "var(--accent)", cursor: "pointer" }}>
                    ↺ Xóa tất cả bộ lọc
                  </button>
                </div>
              </>
            ) : "Chưa có lô hàng nào. Bấm “Thêm lô hàng” để bắt đầu.")}
          </div>}
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
          <I.fx /> Chi phí thống kê theo từng lô. Doanh thu thu theo <b style={{ color: "var(--ink-3)" }}>Bảng kê</b> (gom lô theo ngày cont ra). Bấm ô <b style={{ color: "var(--ink-3)" }}>Chi phí</b> để phân bổ. Chi cho lái xe xem ở <b style={{ color: "var(--ink-3)" }}>Lộ trình</b>.
        </div>
      </div>

      {active && modal.type === "cost" && <CostPopup ship={active} patch={(np) => patch(active.id, np)} onSave={() => commitDirty()} isDirty={isDirty} onClose={() => setModal(null)} cfg={cfg} addCfg={addCfg} tagOptions={tagOptions} />}
      {active && modal.type === "info" && <InfoPopup ship={active} isHph={isHph} patch={(np) => patch(active.id, np)} patchOther={(id, np) => patch(id, np)} onSave={() => commitDirty()} isDirty={isDirty} siblings={sibs.filter((x) => x.id !== active.id)} onClose={closeInfo} onDelete={active._new ? null : () => delShip(active.id)} canDelete={T.canDelete} cfg={cfg} addCfg={addCfg} tagOptions={tagOptions} />}

      {/* Popup THAO TÁC HÀNG LOẠT — tạm thời chỉ Nơi hạ (cảng) + Nơi hạ sà lan */}
      {showBulk && (
        <Modal title={`Thao tác hàng loạt · ${selIds.size} lô`} subtitle="Để trống ô nào thì giữ nguyên ô đó. Bấm Lưu để áp cho tất cả lô đang chọn." width={460} icon={<I.truck />}
          onClose={() => setShowBulk(false)}
          footer={
            <div style={{ display: "flex", alignItems: "center", justifyContent: "flex-end", gap: 10 }}>
              <Btn onClick={() => setShowBulk(false)}>Hủy</Btn>
              <Btn variant="primary" onClick={doBulk}>{bulkBusy ? "Đang lưu…" : `Lưu ${selIds.size} lô`}</Btn>
            </div>
          }>
          <div style={{ padding: "14px 0 6px", display: "flex", flexDirection: "column", gap: 14 }}>
            <label style={{ display: "block" }}>
              <div style={{ fontSize: 12, fontWeight: 600, color: "var(--ink-3)", marginBottom: 5 }}><i className="bi bi-geo-alt-fill" /> Nơi hạ (cảng)</div>
              <Combo value={bulkTo} onChange={setBulkTo} options={locCodeList().map((c) => ({ value: c, label: c }))} placeholder="— Giữ nguyên — (gõ để tìm)" clearable strict />
            </label>
            <label style={{ display: "block" }}>
              <div style={{ fontSize: 12, fontWeight: 600, color: "var(--ink-3)", marginBottom: 5 }}><i className="bi bi-water" /> Nơi hạ sà lan (điểm đến)</div>
              <Combo value={bulkBargeDrop} onChange={setBulkBargeDrop} options={["HPP", "LHP"].map((c) => ({ value: c, label: c }))} placeholder="— Giữ nguyên —" clearable strict />
              <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 5, lineHeight: 1.5 }}>Chọn nơi hạ sà lan = các lô tự đi sà lan; loại DRY/NOR suy từ Loại cont từng lô.</div>
            </label>
          </div>
        </Modal>
      )}

      {showImport && (
        <Modal title="Import lô hàng từ Excel" subtitle="Cột có (*) là BẮT BUỘC: Khách hàng, Số booking, Số lượng cont · Nơi lấy/hạ + Kho không bắt buộc nhưng nhập sai danh mục sẽ báo lỗi · file mẫu có sheet Hướng dẫn + danh mục hợp lệ · kiểm tra trước, 1 lỗi là không import gì cả" width={720} icon={<I.truck />}
          onClose={() => setShowImport(false)}
          footer={
            <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 12 }}>
              <div style={{ fontSize: 12.5, color: impCheck ? (impCheck.valid ? "var(--good)" : "var(--danger)") : "var(--ink-3)", fontWeight: impCheck ? 600 : 400 }}>
                {impCheck ? (impCheck.valid ? `✓ ${impCheck.total} dòng Excel → ${loCountOf(impRows)} lô` : `${impCheck.errors.length} dòng lỗi — chưa import gì`) : (impWb ? "Đã chọn file — bấm Kiểm tra" : "Chọn file Excel để bắt đầu")}
              </div>
              <div style={{ display: "flex", gap: 10 }}>
                <Btn onClick={() => setShowImport(false)}>Đóng</Btn>
                {impCheck && impCheck.valid && <Btn variant="primary" onClick={doImport}>{impBusy ? "Đang nhập…" : `Bắt đầu import ${loCountOf(impRows)} lô`}</Btn>}
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
              // Bung TỪNG LÔ thực tế sẽ tạo — đúng quy tắc backend:
              //  • Có nhiều SỐ CONTAINER (xuống dòng / ; / ,) → mỗi số 1 lô riêng.
              //  • Không có số cont → nhân bản theo SỐ LƯỢNG CONT (số cont điền sau).
              const fmtDT = (iso) => { if (!iso) return "—"; const m = /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/.exec(String(iso)); if (!m) return String(iso); const [, y, mo, d, h, mi] = m; return `${d}/${mo}/${y}${h ? ` ${h}:${mi}` : ""}`; };
              const ioLabel = (v) => { const s = String(v || "").toLowerCase(); if (/nh[âaạ]p|import/.test(s)) return "Nhập"; if (/xu[âaấ]t|export/.test(s)) return "Xuất"; return v || "—"; };
              const los = [];
              impRows.forEach((r, ri) => {
                const cs = String(r.contNo || "").split(/[\r\n;,]+/).map((s) => s.trim()).filter(Boolean);
                if (cs.length) cs.forEach((cn, k) => los.push({ src: ri + 1, part: cs.length > 1 ? `${k + 1}/${cs.length}` : "", cont: cn, r }));
                else { const q = Math.max(1, parseInt(String(r.qty || "").replace(/[^\d]/g, ""), 10) || 1); for (let k = 0; k < q; k++) los.push({ src: ri + 1, part: q > 1 ? `${k + 1}/${q}` : "", cont: "", r }); }
              });
              const cols = [
                { h: "Lô", get: (l, i) => i + 1, num: true, al: "center" },
                { h: "Dòng Excel", get: (l) => l.part ? `${l.src} · cont ${l.part}` : `${l.src}`, al: "center", muted: true },
                { h: "Khách hàng", get: (l) => l.r.customer || "—" },
                { h: "Booking/Bill", get: (l) => l.r.booking || "—" },
                { h: "Nhập/Xuất", get: (l) => ioLabel(l.r.io), al: "center" },
                { h: "Loại cont", get: (l) => l.r.contType || "—", al: "center" },
                { h: "Số container", get: (l) => l.cont, contCol: true },
                { h: "Nơi lấy", get: (l) => l.r.from || "—" },
                { h: "Nơi hạ", get: (l) => l.r.to || "—" },
                { h: "Kho (tuyến)", get: (l) => l.r.kho || "—" },
                { h: "Đến dự kiến", get: (l) => fmtDT(l.r.gioDenDuKien), num: true },
                { h: "Cắt máng", get: (l) => fmtDT(l.r.cutOff), num: true },
                { h: "Invoice", get: (l) => l.r.inv || "—" },
              ];
              return (
                <div style={{ border: "1px solid #bfe4d1", borderRadius: 10, overflow: "hidden" }}>
                  <div style={{ fontSize: 13, fontWeight: 700, color: "var(--good)", padding: "10px 13px", background: "var(--good-weak)", borderBottom: "1px solid #bfe4d1" }}>
                    <i className="bi bi-check-circle-fill" /> {impCheck.total} dòng Excel hợp lệ → sẽ tạo <b>{los.length} lô</b>. Mỗi dòng dưới đây là <b>1 lô riêng</b> — kiểm tra giá trị rơi đúng cột chưa rồi bấm <b>Bắt đầu import</b>.
                  </div>
                  <div style={{ fontSize: 11.5, color: "var(--ink-4)", padding: "7px 13px", background: "#fafbfc", borderBottom: "1px solid var(--line-2)", lineHeight: 1.5 }}>
                    <i className="bi bi-info-circle" /> 1 dòng Excel có nhiều số container (xuống dòng/dấu <code>;</code>/<code>,</code>) sẽ tách thành nhiều lô. Không có số cont thì nhân theo <b>Số lượng cont</b> — cột <b>Số container</b> hiện <span style={{ color: "var(--ink-4)", fontStyle: "italic" }}>điền sau</span>.
                  </div>
                  <div style={{ maxHeight: "44vh", overflow: "auto", overscrollBehavior: "contain" }}>
                    <table style={{ borderCollapse: "collapse", fontSize: 12.5, minWidth: 1040, width: "100%" }}>
                      <thead>
                        <tr>
                          {cols.map((c, i) => (
                            <th key={i} style={{ textAlign: c.al || "left", padding: "7px 11px", fontSize: 10.5, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.03em", borderBottom: "1px solid var(--line)", position: "sticky", top: 0, background: "#fafbfc", whiteSpace: "nowrap", zIndex: 1 }}>{c.h}</th>
                          ))}
                        </tr>
                      </thead>
                      <tbody>
                        {los.map((l, i) => {
                          const newGroup = i > 0 && los[i - 1].src !== l.src;
                          return (
                            <tr key={i} style={{ borderTop: newGroup ? "2px solid var(--line)" : "none" }}>
                              {cols.map((c, ci) => {
                                const v = c.get(l, i);
                                const base = { padding: "6px 11px", borderBottom: "1px solid var(--line-2)", color: "var(--ink-2)", textAlign: c.al || "left", whiteSpace: c.num ? "nowrap" : "normal" };
                                if (c.contCol) return <td key={ci} className="tnum" style={base}>{l.cont ? l.cont : <span style={{ color: "var(--ink-4)", fontStyle: "italic" }}>điền sau</span>}</td>;
                                return <td key={ci} className={c.num ? "tnum" : undefined} style={base}>{v}</td>;
                              })}
                            </tr>
                          );
                        })}
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


export { ShipmentsApp };
