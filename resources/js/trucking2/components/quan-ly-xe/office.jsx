import React from "react";
const { useState, useEffect, useRef, useMemo } = React;
import { I, fmtVND, toNum } from "@trk/lib.jsx";
import { card, CostTab, DocsBlock } from "./parts.jsx";

/*
 * CHI PHÍ VĂN PHÒNG — chi phí quản lý doanh nghiệp không gắn xe/tài sản nào (thuê văn phòng, điện nước, internet,
 * văn phòng phẩm, lương khối văn phòng, phí ngân hàng…). Là 1 trung tâm chi phí kind='office' dùng chung máy phiếu
 * chi với xe/tài sản: duyệt → thanh toán, ảnh hóa đơn, khoản định kỳ (gia hạn), phân bổ trả trước theo tháng.
 * Không có danh sách trung gian: bấm tab là vào thẳng phiếu chi. Tài liệu (hợp đồng thuê, hóa đơn) ở tab bên cạnh.
 */
const OFFICE_DOC_TYPES = ["Hợp đồng thuê", "Hóa đơn", "Chứng từ ngân hàng", "Khác"];
const TABS = [["cost", "Chi phí"], ["docs", "Tài liệu"]];

function OfficeApp({ modeSwitch }) {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const api = (method, url, body) => window.trkApi(method, url, body);
  const canEdit = !!T.canEdit;

  const [detail, setDetail] = useState(null);
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState("cost");
  const [costSaving, setCostSaving] = useState(false);
  const [hlCost, setHlCost] = useState(null);
  const [docType, setDocType] = useState("Hóa đơn");
  const [docBusy, setDocBusy] = useState(false);
  const hash = useRef(null);   // hashid trung tâm chi phí văn phòng → dùng chung endpoint /quan-ly-xe/{vehicle}/…
  // Id phiếu chi đang hiển thị (chụp từ server) → gửi kèm để server chỉ được xóa trong tập đã thấy.
  const costIds = useRef([]);
  const serverCosts = (rows) => { rows = rows || []; costIds.current = rows.map((c) => c.id).filter(Number.isInteger); return rows; };

  useEffect(() => {
    try { window.history.replaceState(null, "", "#office"); } catch (e) {}
    api("GET", ROUTES.officeData).then((r) => {
      if (r && r.ok && r.vehicle) { hash.current = r.vehicle.hashid; setDetail({ ...r.vehicle, costs: serverCosts(r.vehicle.costs) }); }
      setLoading(false);
    }).catch(() => setLoading(false));
  }, []);

  // Chi phí: LƯU NGAY mỗi thao tác (thêm/sửa/duyệt/thanh toán/xóa) — như tab Chi phí của xe/tài sản
  const saveCosts = (rows) => {
    setDetail((d) => ({ ...d, costs: rows }));
    if (!hash.current) return;
    setCostSaving(true);
    api("PUT", ROUTES.fleet + hash.current, { data: { costs: rows, costsLoadedIds: costIds.current } })
      .then((r) => { setCostSaving(false); if (r && r.ok) { setDetail((d) => ({ ...d, costs: (r.vehicle && r.vehicle.costs) ? serverCosts(r.vehicle.costs) : rows })); window.trkToast && window.trkToast("Đã lưu phiếu chi"); } else window.trkToast && window.trkToast("Lưu thất bại", "error"); })
      .catch(() => { setCostSaving(false); window.trkToast && window.trkToast("Lỗi kết nối khi lưu", "error"); });
  };
  const uploadCostPhotos = async (files) => {
    if (!hash.current || !files || !files.length) return [];
    const fd = new FormData(); Array.from(files).forEach((f) => fd.append("files[]", f));
    try { const res = await window.trkUpload("POST", ROUTES.fleet + hash.current + "/cost-photo", fd); if (res && res.ok) return res.photos || []; window.trkToast && window.trkToast((res && res.message) || "Tải ảnh thất bại", "error"); }
    catch (e) { window.trkToast && window.trkToast("Lỗi kết nối khi tải ảnh", "error"); }
    return [];
  };
  const cancelCost = async (id) => {
    const ok = await window.confirmAction({ title: "Hủy phiếu chi?", text: "Phiếu sẽ chuyển <b>Đã hủy</b> và bị loại khỏi tổng chi phí/báo cáo.", confirmText: '<i class="bi bi-x-circle me-1"></i> Hủy phiếu', danger: true });
    if (!ok) return;
    try { const r = await api("PUT", ROUTES.cancelCost + id + "/cancel"); if (r && r.ok) { window.trkToast && window.trkToast("Đã hủy phiếu"); const s = await api("GET", ROUTES.fleet + hash.current + "/section/costs"); if (s && s.ok) setDetail((d) => ({ ...d, costs: serverCosts(s.costs) })); } else window.trkToast && window.trkToast((r && r.message) || "Không hủy được", "error"); } catch (e) {}
  };
  const uploadDocs = async (e) => {
    const files = Array.from(e.target.files || []); e.target.value = "";
    if (!files.length || !hash.current) return;
    const fd = new FormData(); files.forEach((f) => fd.append("files[]", f)); fd.append("type", docType);
    setDocBusy(true);
    try { const res = await window.trkUpload("POST", ROUTES.fleet + hash.current + "/docs", fd); if (res && res.ok) { setDetail((d) => ({ ...d, docs: res.docs })); window.trkToast && window.trkToast(`Đã tải ${files.length} tài liệu`); } else window.trkToast && window.trkToast((res && res.message) || "Tải lên thất bại", "error"); }
    catch (err) { window.trkToast && window.trkToast("Lỗi kết nối khi tải lên", "error"); }
    setDocBusy(false);
  };
  const deleteDoc = async (attId) => {
    if (!hash.current) return;
    const ok = await window.confirmAction({ title: "Xóa tài liệu?", text: "Tài liệu này sẽ bị xóa vĩnh viễn.", confirmText: '<i class="bi bi-trash me-1"></i> Xóa', danger: true });
    if (!ok) return;
    try { const res = await window.trkApi("DELETE", ROUTES.fleet + hash.current + "/docs/" + attId); if (res && res.ok) setDetail((d) => ({ ...d, docs: res.docs })); } catch (e) {}
  };

  // Chỉ số nhanh cho kế toán: chi phí văn phòng THÁNG NÀY (theo ngày chi, bỏ phiếu hủy) + số phiếu còn chờ duyệt / chờ thanh toán.
  const kpi = useMemo(() => {
    const rows = (detail && detail.costs) || [];
    const ym = new Date().toISOString().slice(0, 7);
    let month = 0, pending = 0, toPay = 0;
    rows.forEach((r) => {
      if (r.cancelled) return;
      if ((r.spendDate || "").slice(0, 7) === ym) month += toNum(r.amount);
      if (!r.approved) pending++; else if (!r.paid) toPay++;
    });
    return { month, pending, toPay };
  }, [detail]);

  const chip = (label, value, tone) => (
    <div style={{ display: "flex", flexDirection: "column", gap: 2, padding: "8px 14px", border: "1px solid var(--line)", borderRadius: 10, background: "#fff", minWidth: 128 }}>
      <span style={{ fontSize: 11, fontWeight: 700, color: "var(--ink-4)", textTransform: "uppercase", letterSpacing: ".03em" }}>{label}</span>
      <span className="tnum" style={{ fontSize: 15, fontWeight: 700, color: tone || "var(--ink)" }}>{value}</span>
    </div>
  );

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <div className="trk-head" style={{ display: "flex", alignItems: "center", gap: 14, padding: "14px 22px", background: "#fff", borderBottom: "1px solid var(--line)", flexWrap: "wrap" }}>
        <div style={{ display: "flex", alignItems: "center", gap: 14, flex: 1, minWidth: 0 }}>
          {modeSwitch}
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 16, fontWeight: 700, display: "flex", alignItems: "center", gap: 8 }}><i className="bi bi-building" style={{ color: "var(--accent)" }} /> Chi phí văn phòng</div>
            <div style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Chi phí quản lý doanh nghiệp — thuê văn phòng, điện nước, internet, văn phòng phẩm, lương khối văn phòng… (không gắn xe/tài sản)</div>
          </div>
        </div>
        <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
          {chip("Tháng này", fmtVND(kpi.month) + " đ")}
          {chip("Chờ duyệt", kpi.pending, kpi.pending ? "var(--warn)" : undefined)}
          {chip("Chờ thanh toán", kpi.toPay, kpi.toPay ? "var(--accent)" : undefined)}
        </div>
        {costSaving && <span style={{ fontSize: 12, color: "var(--ink-4)", display: "inline-flex", alignItems: "center", gap: 5 }}><span style={{ width: 13, height: 13, border: "2px solid var(--line)", borderTopColor: "var(--accent)", borderRadius: "50%", display: "inline-block", animation: "trk-spin .7s linear infinite" }} />Đang lưu phiếu chi…</span>}
      </div>
      <div style={{ display: "flex", gap: 4, padding: "10px 22px 0", background: "#fff", borderBottom: "1px solid var(--line)", overflowX: "auto", whiteSpace: "nowrap" }}>
        {TABS.map(([k, t]) => { const on = tab === k; return <button key={k} type="button" onClick={() => setTab(k)}
          style={{ border: "none", flexShrink: 0, borderBottom: on ? "2px solid var(--accent)" : "2px solid transparent", background: "transparent", padding: "8px 12px 11px", fontSize: 13.5, fontWeight: 600, color: on ? "var(--accent)" : "var(--ink-3)", cursor: "pointer" }}>{t}</button>; })}
      </div>
      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: "22px" }}>
        <div style={{ maxWidth: 980, margin: "0 auto" }}>
          {(loading || !detail)
            ? <div style={{ display: "flex", alignItems: "center", gap: 9, padding: "30px 4px", color: "var(--ink-4)", fontSize: 13.5 }}><span style={{ width: 15, height: 15, border: "2px solid var(--line)", borderTopColor: "var(--accent)", borderRadius: "50%", display: "inline-block", animation: "trk-spin .7s linear infinite" }} /> Đang tải dữ liệu…</div>
            : tab === "cost"
            ? <CostTab target="office" rows={detail.costs || []} onChange={saveCosts} saving={costSaving} costTypes={detail.costTypes || []} payMethods={B.payMethods || []} payers={B.payers || []} onUploadPhotos={uploadCostPhotos} onCancel={cancelCost} highlightId={hlCost} />
            : <div style={card}><DocsBlock docs={detail.docs || []} busy={docBusy} docType={docType} setDocType={setDocType} onPick={uploadDocs} onDelete={deleteDoc} canEdit={canEdit} docTypes={OFFICE_DOC_TYPES} hint="Chứng từ văn phòng (hợp đồng thuê, hóa đơn điện nước/internet, chứng từ ngân hàng… — ảnh / PDF / Word / Excel)" /></div>}
        </div>
      </div>
    </div>
  );
}

export { OfficeApp };
