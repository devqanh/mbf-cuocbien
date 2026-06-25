import React from "react";
const { useState, useEffect, useRef } = React;
import { I, Money, Num, Txt, Combo, DateField, Btn, Modal, fmtVND, fmtNum, fmtDate, toNum, useIsMobile } from "@trk/lib.jsx";
import { ChkBox } from "@trk/pop.jsx";
import { num, daysUsed, COST_KINDS, normKind, TAB_KEYS, SECTION_OF, WARN_DAYS, DUE_NONE, dueStatus, vehRank, DueCell, StatChip, lbl, delBtn, addBtn, card, Pager, DeprecTab, DeprecMonthlyTab, UsageTab, today10, esc, blankCost, PAY_METHODS, PayModal, CostModal, CostTab, VEH_DOC_TYPES, DocsBlock, InfoTab, AllowanceTab, PendingCostsModal } from "./parts.jsx";

const ASSET_TAB_KEYS = ["info", "deprec", "deprecMonthly", "cost", "docs"];
const ASSET_SECTION_OF = { deprec: "depreciations", deprecMonthly: "depreciations", cost: "costs" };
const ASSET_STATUS = ["Đang dùng", "Đang bảo trì", "Hỏng", "Ngừng dùng", "Đã thanh lý"];
const ASSET_DOC_TYPES = ["Hóa đơn mua", "Hợp đồng", "Bảo hành", "Kiểm định", "Ảnh tài sản", "Khác"];
const assetRank = (a) => Math.max(dueStatus(a.warrantyDue).rank, dueStatus(a.inspectionDue).rank);

/* ---- Tab: Thông tin tài sản ---- */
function AssetInfoTab({ info, onChange, categories, addCategory }) {
  const i = info || {};
  const set = (k, v) => onChange({ ...i, [k]: v });
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 16 }}>
      <div style={card}>
        <div style={{ fontSize: 13.5, fontWeight: 700, marginBottom: 12 }}>Thông tin cơ bản</div>
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: 12 }}>
          <div style={{ gridColumn: "1 / -1" }}>{lbl("Tên tài sản")}<Txt value={i.name} onChange={(x) => set("name", x)} placeholder="VD: Máy nén khí, Xe nâng, Máy tính…" /></div>
          <div>{lbl("Loại tài sản")}<Combo value={i.category} onChange={(x) => set("category", x)} options={categories || []} onCreate={addCategory} placeholder="Chọn / thêm loại…" /></div>
          <div>{lbl("Số seri / Mã nội bộ")}<Txt value={i.serial} onChange={(x) => set("serial", x)} placeholder="VD: SN-12345" /></div>
          <div>{lbl("Ngày mua")}<DateField value={i.purchaseDate} onChange={(x) => set("purchaseDate", x)} /></div>
          <div>{lbl("Nguyên giá")}<Money value={i.origValue} onChange={(x) => set("origValue", x)} dim /></div>
          <div>{lbl("Nhà cung cấp")}<Txt value={i.supplier} onChange={(x) => set("supplier", x)} placeholder="VD: Công ty ABC" /></div>
        </div>
      </div>
      <div style={card}>
        <div style={{ fontSize: 13.5, fontWeight: 700, marginBottom: 12 }}>Vị trí & tình trạng</div>
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: 12 }}>
          <div>{lbl("Vị trí đặt / sử dụng")}<Txt value={i.location} onChange={(x) => set("location", x)} placeholder="VD: Kho A, Xưởng 2…" /></div>
          <div>{lbl("Người quản lý")}<Txt value={i.manager} onChange={(x) => set("manager", x)} placeholder="VD: Nguyễn Văn A" /></div>
          <div>{lbl("Tình trạng")}
            <select value={i.status || ""} onChange={(e) => set("status", e.target.value)} style={{ width: "100%", padding: "8px 11px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: i.status ? "var(--ink-2)" : "var(--ink-4)" }}>
              <option value="">— chọn —</option>{ASSET_STATUS.map((s) => <option key={s} value={s}>{s}</option>)}
            </select>
          </div>
        </div>
      </div>
      <div style={card}>
        <div style={{ fontSize: 13.5, fontWeight: 700, marginBottom: 12 }}>Bảo hành & kiểm định</div>
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: 12 }}>
          <div>{lbl("Hạn bảo hành")}<DateField value={i.warrantyDue} onChange={(x) => set("warrantyDue", x)} /></div>
          <div>{lbl("Hạn kiểm định")}<DateField value={i.inspectionDue} onChange={(x) => set("inspectionDue", x)} /></div>
        </div>
        <div style={{ marginTop: 12 }}>{lbl("Ghi chú")}<Txt value={i.note} onChange={(x) => set("note", x)} placeholder="Ghi chú thêm về tài sản…" /></div>
      </div>
    </div>
  );
}

/* ---- Modal tạo tài sản mới ---- */
function CreateAssetModal({ categories, addCategory, onCreate, onClose }) {
  const { useState } = React;
  const [d, setD] = useState({ name: "", code: "", category: "" });
  const [err, setErr] = useState("");
  const [busy, setBusy] = useState(false);
  const submit = async () => {
    if (!d.name.trim()) return setErr("Vui lòng nhập tên tài sản.");
    setErr(""); setBusy(true);
    const ok = await onCreate(d);
    setBusy(false);
    if (ok && ok.message) setErr(ok.message);
  };
  return (
    <Modal title="Thêm tài sản" subtitle="Nhập thông tin cơ bản — chi tiết, khấu hao, chi phí điền sau khi mở" width={520} icon={<I.plus />} onClose={onClose}
      footer={<div style={{ display: "flex", justifyContent: "flex-end", gap: 10, width: "100%" }}><Btn onClick={onClose}>Hủy</Btn><Btn variant="primary" onClick={submit} disabled={busy}>{busy ? "Đang tạo…" : "Tạo & mở"}</Btn></div>}>
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, padding: "6px 0 2px" }}>
        {err && <div style={{ gridColumn: "1 / -1", display: "flex", gap: 7, alignItems: "center", fontSize: 12.5, color: "#b42318", background: "#fdecec", border: "1px solid #f3c9c9", borderRadius: 9, padding: "8px 12px" }}><i className="bi bi-exclamation-triangle-fill" /> {err}</div>}
        <div style={{ gridColumn: "1 / -1" }}>{lbl("Tên tài sản *")}<Txt value={d.name} onChange={(x) => setD((s) => ({ ...s, name: x }))} placeholder="VD: Máy nén khí Hitachi" /></div>
        <div>{lbl("Loại tài sản")}<Combo value={d.category} onChange={(x) => setD((s) => ({ ...s, category: x }))} options={categories || []} onCreate={addCategory} placeholder="Chọn / thêm…" /></div>
        <div>{lbl("Mã tài sản (để trống = tự sinh)")}<Txt value={d.code} onChange={(x) => setD((s) => ({ ...s, code: x }))} placeholder="VD: TS-0001" /></div>
      </div>
    </Modal>
  );
}

/* ---- Ô hạn rút gọn cho danh sách tài sản (BH + KĐ) ---- */
const AssetDueCell = ({ warranty, inspection }) => {
  const rows = [["BH", warranty], ["KĐ", inspection]].filter(([, iso]) => iso);
  if (!rows.length) return <span style={{ fontSize: 12, color: "var(--ink-4)" }}>—</span>;
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 4 }}>
      {rows.map(([tag, iso]) => { const s = dueStatus(iso); return (
        <span key={tag} style={{ display: "inline-flex", alignItems: "center", gap: 5, fontSize: 11.5 }} className="tnum">
          <span style={{ color: "var(--ink-4)", fontWeight: 700, fontSize: 10 }}>{tag}</span>
          <span style={{ color: "var(--ink-2)" }}>{fmtDate(iso)}</span>
          <span style={{ fontSize: 10, fontWeight: 700, color: s.color, background: s.bg, padding: "0 6px", borderRadius: 999 }}>{s.label}{s.key === "soon" && s.days != null ? ` ${s.days}n` : ""}</span>
        </span>
      ); })}
    </div>
  );
};

function AssetApp({ modeSwitch, assets, setAssets, categories, setCategories, loaded }) {
  const isMobile = useIsMobile();
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const api = (method, url, body) => window.trkApi(method, url, body);
  const canEdit = !!T.canEdit;

  const addCategory = async (name) => {
    name = (name || "").trim(); if (!name) return;
    setCategories((c) => (c.includes(name) ? c : [...c, name]));
    try { const r = await fetch(ROUTES.assetCategory, { method: "POST", headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": T.csrf }, body: JSON.stringify({ name }) }).then((x) => x.json()); if (r && r.ok) setCategories(r.categories); } catch (e) {}
  };

  const [vFilter, setVFilter] = useState("all");   // all | expired | soon | ok
  const [vQuery, setVQuery] = useState("");
  const [aPage, setAPage] = useState(1);           // phân trang client 30 dòng/trang
  const PER = 30;
  useEffect(() => { setAPage(1); }, [vFilter, vQuery]);   // đổi lọc/tìm → về trang 1
  const [showCreate, setShowCreate] = useState(false);
  const [selId, setSelId] = useState(null);
  const [detail, setDetail] = useState(null);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [dirty, setDirty] = useState(false);
  const [tab, setTab] = useState("info");
  const [docType, setDocType] = useState("Hóa đơn mua");
  const [docBusy, setDocBusy] = useState(false);
  const [secLoading, setSecLoading] = useState(false);
  const loadedSecs = useRef(new Set());
  const [costSaving, setCostSaving] = useState(false);
  const pendingCost = useRef(null);   // costId cần cuộn tới sau khi tab Chi phí load (deep-link thông báo)
  const [hlCost, setHlCost] = useState(null);
  const selHash = useRef(null);   // hashid tài sản đang mở → dựng URL

  const ensureSection = (tabKey, hash) => {
    const sec = ASSET_SECTION_OF[tabKey]; hash = hash || selHash.current;
    if (!sec || !hash || loadedSecs.current.has(sec)) return;
    loadedSecs.current.add(sec);
    setSecLoading(true);
    api("GET", ROUTES.fleet + hash + "/section/" + sec).then((r) => {
      if (r && r.ok) setDetail((d) => ({ ...d, [sec]: r[sec] || [], ...(r.costTypes ? { costTypes: r.costTypes } : {}) }));
      else loadedSecs.current.delete(sec);
      setSecLoading(false);
    }).catch(() => { loadedSecs.current.delete(sec); setSecLoading(false); });
  };
  const open = (a, tabKey) => {
    const t = ASSET_TAB_KEYS.includes(tabKey) ? tabKey : "info";
    loadedSecs.current = new Set();
    selHash.current = a.hashid || a.id;
    setSelId(a.id); setDetail(null); setDirty(false); setTab(t); setLoading(true);
    api("GET", ROUTES.fleet + (a.hashid || a.id) + "/data").then((r) => {
      if (r && r.ok) setDetail(r.vehicle);
      setLoading(false);
      ensureSection(t, a.hashid || a.id);
    }).catch(() => setLoading(false));
  };
  const goTab = (k) => { setTab(k); ensureSection(k); };
  const back = async () => {
    if (dirty && !(await window.confirmAction({ title: "Thoát khi chưa lưu?", text: "Bạn có thay đổi <b>chưa lưu</b> (Thông tin / Khấu hao). Thoát ra sẽ <b>mất</b> các thay đổi này.", confirmText: '<i class="bi bi-box-arrow-left me-1"></i> Thoát, không lưu', danger: true }))) return;
    // cập nhật lại dòng trong danh sách theo info + số đếm vừa sửa
    if (detail) {
      const inf = detail.info || {};
      const patch = { name: inf.name || "", category: inf.category || "", status: inf.status || "", location: inf.location || "", warrantyDue: inf.warrantyDue || "", inspectionDue: inf.inspectionDue || "" };
      if (Array.isArray(detail.docs)) patch.docCount = detail.docs.length;
      if (Array.isArray(detail.costs)) patch.costCount = detail.costs.filter((c) => !c.cancelled).length;
      if (Array.isArray(detail.depreciations)) patch.depCount = detail.depreciations.length;
      setAssets((list) => list.map((x) => x.id === selId ? { ...x, ...patch } : x));
    }
    setSelId(null); selHash.current = null; setDetail(null); setDirty(false); loadedSecs.current = new Set();
  };
  const upd = (np) => { setDetail((d) => ({ ...d, ...np })); setDirty(true); };

  const save = () => {
    if (!dirty || saving || !detail) return;
    setSaving(true);
    const data = { info: detail.info || {} };
    ["costs", "depreciations"].forEach((s) => { if (Array.isArray(detail[s])) data[s] = detail[s]; });
    api("PUT", ROUTES.fleet + selHash.current, { data })
      .then((r) => { setSaving(false); if (r && r.ok) { setDetail((d) => ({ ...d, ...r.vehicle })); setDirty(false); window.trkToast && window.trkToast("Đã lưu"); } else window.trkToast && window.trkToast("Lưu thất bại", "error"); })
      .catch(() => { setSaving(false); window.trkToast && window.trkToast("Lỗi kết nối khi lưu", "error"); });
  };
  const saveCosts = (rows) => {
    setDetail((d) => ({ ...d, costs: rows }));
    if (!selId) return;
    setCostSaving(true);
    api("PUT", ROUTES.fleet + selHash.current, { data: { costs: rows } })
      .then((r) => { setCostSaving(false); if (r && r.ok) { setDetail((d) => ({ ...d, costs: (r.vehicle && r.vehicle.costs) || rows })); window.trkToast && window.trkToast("Đã lưu phiếu chi"); } else window.trkToast && window.trkToast("Lưu thất bại", "error"); })
      .catch(() => { setCostSaving(false); window.trkToast && window.trkToast("Lỗi kết nối khi lưu", "error"); });
  };
  const uploadCostPhotos = async (files) => {
    if (!selId || !files || !files.length) return [];
    const fd = new FormData(); Array.from(files).forEach((f) => fd.append("files[]", f));
    try { const res = await window.trkUpload("POST", ROUTES.fleet + selHash.current + "/cost-photo", fd); if (res && res.ok) return res.photos || []; window.trkToast && window.trkToast((res && res.message) || "Tải ảnh thất bại", "error"); }
    catch (e) { window.trkToast && window.trkToast("Lỗi kết nối khi tải ảnh", "error"); }
    return [];
  };
  const cancelCost = async (id) => {
    const ok = await window.confirmAction({ title: "Hủy phiếu chi?", text: "Phiếu sẽ chuyển <b>Đã hủy</b> và bị loại khỏi tổng chi phí/báo cáo.", confirmText: '<i class="bi bi-x-circle me-1"></i> Hủy phiếu', danger: true });
    if (!ok) return;
    try { const r = await api("PUT", ROUTES.cancelCost + id + "/cancel"); if (r && r.ok) { window.trkToast && window.trkToast("Đã hủy phiếu"); const s = await api("GET", ROUTES.fleet + selHash.current + "/section/costs"); if (s && s.ok) setDetail((d) => ({ ...d, costs: s.costs || [] })); } else window.trkToast && window.trkToast((r && r.message) || "Không hủy được", "error"); } catch (e) {}
  };
  const uploadDocs = async (e) => {
    const files = Array.from(e.target.files || []); e.target.value = "";
    if (!files.length || !selId) return;
    const fd = new FormData(); files.forEach((f) => fd.append("files[]", f)); fd.append("type", docType);
    setDocBusy(true);
    try { const res = await window.trkUpload("POST", ROUTES.fleet + selHash.current + "/docs", fd); if (res && res.ok) { setDetail((d) => ({ ...d, docs: res.docs })); window.trkToast && window.trkToast(`Đã tải ${files.length} tài liệu`); } else window.trkToast && window.trkToast((res && res.message) || "Tải lên thất bại", "error"); }
    catch (err) { window.trkToast && window.trkToast("Lỗi kết nối khi tải lên", "error"); }
    setDocBusy(false);
  };
  const deleteDoc = async (attId) => {
    if (!selId) return;
    const ok = await window.confirmAction({ title: "Xóa tài liệu?", text: "Tài liệu này sẽ bị xóa vĩnh viễn.", confirmText: '<i class="bi bi-trash me-1"></i> Xóa', danger: true });
    if (!ok) return;
    try { const res = await window.trkApi("DELETE", ROUTES.fleet + selHash.current + "/docs/" + attId); if (res && res.ok) setDetail((d) => ({ ...d, docs: res.docs })); } catch (e) {}
  };
  const createAsset = async (d) => {
    try {
      const r = await api("POST", ROUTES.assetCreate, { name: d.name, code: d.code, category: d.category });
      if (r && r.ok && r.asset) { setAssets((list) => [r.asset, ...list]); setShowCreate(false); open(r.asset, "info"); return true; }
      return { message: (r && (r.message || (r.errors && Object.values(r.errors)[0][0]))) || "Tạo thất bại" };
    } catch (e) { return { message: "Lỗi kết nối khi tạo." }; }
  };
  const removeAsset = async () => {
    if (!selId) return;
    const ok = await window.confirmAction({ title: "Xóa tài sản?", text: "Tài sản này cùng <b>toàn bộ chi phí, khấu hao, tài liệu</b> sẽ bị xóa vĩnh viễn.", confirmText: '<i class="bi bi-trash me-1"></i> Xóa tài sản', danger: true });
    if (!ok) return;
    try { const r = await api("DELETE", ROUTES.assetDestroy + selHash.current); if (r && r.ok) { const id = selId; setSelId(null); setDetail(null); setDirty(false); setAssets((list) => list.filter((x) => x.id !== id)); window.trkToast && window.trkToast("Đã xóa tài sản"); } else window.trkToast && window.trkToast("Không xóa được", "error"); } catch (e) {}
  };

  useEffect(() => {
    if (!dirty) return;
    const h = (e) => { e.preventDefault(); e.returnValue = ""; return ""; };
    window.addEventListener("beforeunload", h);
    return () => window.removeEventListener("beforeunload", h);
  }, [dirty]);
  // Deep-link thông báo (#asset/<hashid>/cost/<costHashid>): mở đúng tài sản + tab Chi phí khi đã nạp
  useEffect(() => {
    if (!loaded || selId) return;
    const m = (window.location.hash || "").replace(/^#/, "").match(/^asset\/([0-9A-Za-z]+)(?:\/cost\/([0-9A-Za-z]+))?/);
    if (!m) return;
    const a = assets.find((x) => String(x.hashid) === m[1] || String(x.id) === m[1]);
    if (!a) return;
    if (m[2]) pendingCost.current = m[2];
    open(a, "cost");
  }, [loaded]);
  // Khi tab Chi phí load xong → cuộn tới + highlight phiếu chi (deep-link, theo hashid)
  useEffect(() => {
    const cidHash = pendingCost.current;
    if (!cidHash || tab !== "cost" || !detail || !Array.isArray(detail.costs)) return;
    const c = detail.costs.find((x) => String(x.hashid) === String(cidHash) || String(x.id) === String(cidHash));
    if (!c) return;
    pendingCost.current = null;
    setHlCost(String(c.id));
    setTimeout(() => { const el = document.getElementById("trk-cost-" + c.id); if (el) el.scrollIntoView({ behavior: "smooth", block: "center" }); }, 140);
    const t = setTimeout(() => setHlCost(null), 2800);
    return () => clearTimeout(t);
  }, [detail, tab]);

  // ---------- DANH SÁCH TÀI SẢN ----------
  if (!selId) {
    if (!loaded) {
      return (
        <div style={{ height: "100%", background: "var(--bg)", overflow: "auto" }}>
          <div style={{ maxWidth: 1120, width: "100%", margin: "0 auto", padding: isMobile ? "16px 14px" : "22px" }}>
            {modeSwitch}
            <div style={{ display: "flex", alignItems: "center", gap: 9, padding: "30px 4px", color: "var(--ink-4)", fontSize: 13.5 }}><span style={{ width: 15, height: 15, border: "2px solid var(--line)", borderTopColor: "var(--accent)", borderRadius: "50%", display: "inline-block", animation: "trk-spin .7s linear infinite" }} /> Đang tải danh sách tài sản…</div>
          </div>
        </div>
      );
    }
    const expiredCount = assets.filter((a) => assetRank(a) === 3).length;
    const soonCount = assets.filter((a) => assetRank(a) === 2).length;
    const okCount = assets.filter((a) => assetRank(a) === 1).length;
    const q = vQuery.trim().toLowerCase();
    const list = assets.filter((a) => {
      if (q && !((a.name || "").toLowerCase().includes(q) || (a.code || "").toLowerCase().includes(q) || (a.category || "").toLowerCase().includes(q))) return false;
      if (vFilter === "expired") return assetRank(a) === 3;
      if (vFilter === "soon") return assetRank(a) === 2;
      if (vFilter === "ok") return assetRank(a) === 1;
      return true;
    });
    const lastPage = Math.max(1, Math.ceil(list.length / PER));
    const curPage = Math.min(aPage, lastPage);
    const pageList = list.slice((curPage - 1) * PER, curPage * PER);
    const FILTERS = [["all", "Tất cả", assets.length, "var(--ink-2)"], ["expired", "Hết hạn", expiredCount, "var(--danger)"], ["soon", "Sắp hết hạn", soonCount, "var(--warn)"], ["ok", "Còn hạn", okCount, "var(--good)"]];
    const th = (t, align) => <th style={{ textAlign: align || "left", padding: "10px 12px", fontSize: 11, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.04em", borderBottom: "1px solid var(--line)", whiteSpace: "nowrap" }}>{t}</th>;

    return (
      <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)", overflow: "auto" }}>
        <div style={{ maxWidth: 1120, width: "100%", margin: "0 auto", padding: isMobile ? "16px 14px" : "22px" }}>
          {modeSwitch}
          <div style={{ display: "flex", alignItems: "flex-start", gap: 12, marginBottom: 14, flexWrap: "wrap" }}>
            <div style={{ flex: 1, minWidth: 200 }}>
              <h1 style={{ margin: 0, fontSize: 22, fontWeight: 700, letterSpacing: "-0.02em" }}>Quản lý tài sản</h1>
              <div style={{ fontSize: 13, color: "var(--ink-3)", marginTop: 3 }}>{assets.length} tài sản. Bấm vào tài sản để xem thông tin, khấu hao, chi phí, tài liệu.</div>
            </div>
            {canEdit && <button type="button" onClick={() => setShowCreate(true)}
              style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "9px 15px", fontSize: 13.5, fontWeight: 600, border: "none", borderRadius: 9, background: "var(--accent)", color: "#fff", cursor: "pointer", boxShadow: "0 1px 2px rgba(42,111,219,.4)" }}><I.plus /> Thêm tài sản</button>}
          </div>

          {(expiredCount > 0 || soonCount > 0) && (
            <div style={{ display: "flex", alignItems: "center", gap: 10, padding: "11px 14px", marginBottom: 14, borderRadius: 10, background: expiredCount > 0 ? "#fce8e8" : "#fcf3e2", border: `1px solid ${expiredCount > 0 ? "#f3c9c9" : "#f0dcae"}` }}>
              <i className="bi bi-exclamation-triangle-fill" style={{ fontSize: 18, color: expiredCount > 0 ? "var(--danger)" : "var(--warn)" }} />
              <div style={{ fontSize: 13, color: "var(--ink-2)" }}>
                {expiredCount > 0 && <span><b style={{ color: "var(--danger)" }}>{expiredCount} tài sản hết hạn</b> bảo hành/kiểm định. </span>}
                {soonCount > 0 && <span><b style={{ color: "var(--warn)" }}>{soonCount} sắp hết hạn</b> (trong {WARN_DAYS} ngày). </span>}
              </div>
            </div>
          )}

          <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 12, flexWrap: "wrap" }}>
            <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 9, padding: 3, gap: 1 }}>
              {FILTERS.map(([k, label, n, col]) => { const on = vFilter === k; return (
                <button key={k} type="button" onClick={() => setVFilter(k)}
                  style={{ display: "inline-flex", alignItems: "center", gap: 6, border: "none", cursor: "pointer", fontSize: 12.5, fontWeight: 600, padding: "6px 12px", borderRadius: 7, background: on ? "#fff" : "transparent", color: on ? col : "var(--ink-3)", boxShadow: on ? "0 1px 2px rgba(16,19,23,.12)" : "none" }}>
                  {label}<span className="tnum" style={{ fontSize: 11, fontWeight: 700, color: on ? "#fff" : "var(--ink-4)", background: on ? col : "var(--line-2)", padding: "0 6px", borderRadius: 999, minWidth: 16, textAlign: "center" }}>{n}</span>
                </button>
              ); })}
            </div>
            <div style={{ flex: 1 }} />
            <input value={vQuery} onChange={(e) => setVQuery(e.target.value)} placeholder="Tìm tên / mã / loại…"
              style={{ width: isMobile ? "100%" : 240, flex: isMobile ? "1 1 100%" : "0 0 auto", padding: "8px 12px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 9, outline: "none", background: "#fff" }} />
          </div>

          {assets.length === 0
            ? <div style={{ padding: "44px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5, background: "#fff", border: "1px solid var(--line)", borderRadius: 12 }}>Chưa có tài sản nào. Bấm <b>Thêm tài sản</b> để bắt đầu.</div>
            : (
              <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, overflow: "hidden" }}>
                <div style={{ overflowX: "auto" }}>
                  <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13.5 }}>
                    <thead><tr style={{ background: "#fafbfc" }}>
                      {th("Tài sản")}{th("Loại")}{th("Vị trí · Tình trạng")}{th("Bảo hành · Kiểm định")}{th("Hồ sơ", "center")}{th("Khấu hao/tháng", "right")}{th("Khấu hao · Chi phí", "center")}{th("", "right")}
                    </tr></thead>
                    <tbody>
                      {pageList.map((a) => {
                        const r = assetRank(a);
                        const stripe = r === 3 ? "var(--danger)" : r === 2 ? "var(--warn)" : "transparent";
                        return (
                          <tr key={a.id} onClick={() => open(a)} style={{ cursor: "pointer", borderBottom: "1px solid var(--line-2)", transition: "background .1s" }}
                            onMouseEnter={(e) => (e.currentTarget.style.background = "var(--accent-weak-2)")} onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}>
                            <td style={{ padding: "11px 12px", borderLeft: `3px solid ${stripe}` }}>
                              <div style={{ fontSize: 14, fontWeight: 700 }}>{a.name || <span style={{ color: "var(--ink-4)", fontWeight: 400 }}>(chưa đặt tên)</span>}</div>
                              <div className="tnum" style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 1 }}>{a.code}</div>
                            </td>
                            <td style={{ padding: "9px 12px", color: "var(--ink-2)" }}>{a.category ? <span style={{ fontSize: 11.5, fontWeight: 600, color: "var(--accent)", background: "var(--accent-weak)", padding: "2px 9px", borderRadius: 999 }}>{a.category}</span> : <span style={{ color: "var(--ink-4)" }}>—</span>}</td>
                            <td style={{ padding: "9px 12px", color: "var(--ink-2)", fontSize: 12.5 }}>
                              <div>{a.location || <span style={{ color: "var(--ink-4)" }}>—</span>}</div>
                              {a.status && <div style={{ fontSize: 11, color: "var(--ink-4)", marginTop: 1 }}>{a.status}</div>}
                            </td>
                            <td style={{ padding: "9px 12px" }}><AssetDueCell warranty={a.warrantyDue} inspection={a.inspectionDue} /></td>
                            <td style={{ padding: "9px 12px", textAlign: "center" }} className="tnum">
                              {a.docCount > 0 ? <span style={{ display: "inline-flex", alignItems: "center", gap: 5, fontSize: 12.5, color: "var(--ink-2)" }}><i className="bi bi-paperclip" />{a.docCount}</span> : <span style={{ fontSize: 12, color: "var(--ink-4)" }}>—</span>}
                            </td>
                            <td style={{ padding: "9px 12px", textAlign: "right" }} className="tnum">
                              {(a.depMonthly > 0 || a.depOrig > 0)
                                ? <div><div style={{ fontSize: 13.5, fontWeight: 700, color: "var(--ink-1)" }}>{fmtVND(a.depMonthly)}</div><div style={{ fontSize: 10.5, color: "var(--ink-4)" }}>còn {fmtVND(a.depRemain)}</div></div>
                                : <span style={{ fontSize: 12, color: "var(--ink-4)" }}>—</span>}
                            </td>
                            <td style={{ padding: "9px 12px" }} className="tnum">
                              <div style={{ display: "flex", alignItems: "center", justifyContent: "center", gap: 14 }}>
                                <StatChip icon="bi-cash-stack" n={a.depCount} title="Hạng mục khấu hao" />
                                <StatChip icon="bi-receipt" n={a.costCount} title="Phiếu chi" />
                              </div>
                            </td>
                            <td style={{ padding: "9px 14px", textAlign: "right", color: "var(--ink-4)" }}><I.open /></td>
                          </tr>
                        );
                      })}
                      {list.length === 0 && <tr><td colSpan={8} style={{ padding: "32px", textAlign: "center", color: "var(--ink-4)", fontSize: 13 }}>Không có tài sản nào khớp bộ lọc.</td></tr>}
                    </tbody>
                  </table>
                </div>
                <Pager page={curPage} perPage={PER} total={list.length} onPage={setAPage} />
              </div>
            )}
        </div>
        {showCreate && <CreateAssetModal categories={categories} addCategory={addCategory} onCreate={createAsset} onClose={() => setShowCreate(false)} />}
      </div>
    );
  }

  // ---------- CHI TIẾT TÀI SẢN ----------
  const TABS = [["info", "Thông tin"], ["deprec", "Khấu hao"], ["deprecMonthly", "Theo dõi KH"], ["cost", "Chi phí"], ["docs", "Tài liệu"]];
  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <div className="trk-head" style={{ display: "flex", alignItems: "center", gap: 14, padding: "14px 22px", background: "#fff", borderBottom: "1px solid var(--line)" }}>
        <div className="trk-head-lead" style={{ display: "flex", alignItems: "center", gap: 14, flex: 1, minWidth: 0 }}>
          <button type="button" onClick={back} title="Về danh sách tài sản"
            style={{ display: "inline-flex", alignItems: "center", gap: 6, flexShrink: 0, padding: "7px 12px", fontSize: 13, fontWeight: 600, color: "var(--ink-2)", border: "1px solid var(--line)", borderRadius: 9, background: "#fff", cursor: "pointer" }}>
            <span style={{ transform: "rotate(180deg)", display: "inline-flex" }}><I.arrow /></span> Danh sách tài sản
          </button>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 16, fontWeight: 700 }}>{detail ? (detail.info && detail.info.name) || "(chưa đặt tên)" : "…"}</div>
            <div style={{ fontSize: 12.5, color: "var(--ink-3)" }} className="tnum">{detail ? detail.plate : ""}{detail && detail.info && detail.info.category ? " · " + detail.info.category : ""}</div>
          </div>
        </div>
        {costSaving && <span style={{ fontSize: 12, color: "var(--ink-4)", display: "inline-flex", alignItems: "center", gap: 5 }}><span style={{ width: 13, height: 13, border: "2px solid var(--line)", borderTopColor: "var(--accent)", borderRadius: "50%", display: "inline-block", animation: "trk-spin .7s linear infinite" }} />Đang lưu phiếu chi…</span>}
        {dirty && <span style={{ fontSize: 12, color: "var(--warn)", fontWeight: 700, display: "inline-flex", alignItems: "center", gap: 5 }}><i className="bi bi-exclamation-circle-fill" /> Chưa lưu — bấm Lưu</span>}
        {canEdit && dirty && <Btn variant="primary" onClick={save} disabled={saving}>{saving ? "Đang lưu…" : "Lưu"}</Btn>}
        {canEdit && !dirty && <button type="button" onClick={removeAsset} title="Xóa tài sản"
          style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 12px", fontSize: 13, fontWeight: 600, color: "var(--danger)", border: "1px solid var(--line)", borderRadius: 9, background: "#fff", cursor: "pointer" }}><I.trash /> Xóa</button>}
      </div>
      <div style={{ display: "flex", gap: 4, padding: "10px 22px 0", background: "#fff", borderBottom: "1px solid var(--line)", overflowX: "auto", whiteSpace: "nowrap" }}>
        {TABS.map(([k, t]) => { const on = tab === k; return <button key={k} type="button" onClick={() => goTab(k)}
          style={{ border: "none", flexShrink: 0, borderBottom: on ? "2px solid var(--accent)" : "2px solid transparent", background: "transparent", padding: "8px 12px 11px", fontSize: 13.5, fontWeight: 600, color: on ? "var(--accent)" : "var(--ink-3)", cursor: "pointer" }}>{t}</button>; })}
      </div>
      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: "22px" }}>
        <div style={{ maxWidth: 980, margin: "0 auto" }}>
          {(loading || !detail || (ASSET_SECTION_OF[tab] && detail[ASSET_SECTION_OF[tab]] === undefined))
            ? <div style={{ display: "flex", alignItems: "center", gap: 9, padding: "30px 4px", color: "var(--ink-4)", fontSize: 13.5 }}><span style={{ width: 15, height: 15, border: "2px solid var(--line)", borderTopColor: "var(--accent)", borderRadius: "50%", display: "inline-block", animation: "trk-spin .7s linear infinite" }} /> Đang tải dữ liệu…</div>
            : tab === "info" ? <AssetInfoTab info={detail.info} onChange={(info) => upd({ info })} categories={categories} addCategory={addCategory} />
            : tab === "deprec" ? <DeprecTab rows={detail.depreciations || []} onChange={(rows) => upd({ depreciations: rows })} />
            : tab === "deprecMonthly" ? <DeprecMonthlyTab rows={detail.depreciations || []} />
            : tab === "cost" ? <CostTab rows={detail.costs || []} onChange={saveCosts} saving={costSaving} costTypes={detail.costTypes || []} payMethods={B.payMethods || []} onUploadPhotos={uploadCostPhotos} onCancel={cancelCost} highlightId={hlCost} />
            : <div style={card}><DocsBlock docs={detail.docs || []} busy={docBusy} docType={docType} setDocType={setDocType} onPick={uploadDocs} onDelete={deleteDoc} canEdit={canEdit} docTypes={ASSET_DOC_TYPES} hint="Tài liệu tài sản (hóa đơn mua, hợp đồng, bảo hành, ảnh… — ảnh / PDF / Word / Excel)" /></div>}
        </div>
      </div>
    </div>
  );
}

export { AssetApp };
