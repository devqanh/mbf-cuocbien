import React from "react";
const { useState, useEffect, useRef, useCallback } = React;
import { fmtVND, fmtDate, toNum, useIsMobile } from "@trk/lib.jsx";
import { PayModal, CostModal } from "@trk/components/quan-ly-xe/parts.jsx";

/* QUẢN LÝ CHI PHÍ — tổng hợp MỌI phiếu chi (xe + tài sản) để duyệt/thanh toán/sửa/hủy tập trung,
   không phải vào từng xe. Hành động lưu qua endpoint 1-phiếu (fleet.updateCost / fleet.cancelCost). */

const T = window.__TRK || {};
const ROUTES = T.routes || {};
const B = T.boot || {};
const api = (m, u, b) => window.trkApi(m, u, b);
const dig = (x) => String(x == null ? "" : x).replace(/\D/g, "");

const TABS = [
  ["action", "Cần xử lý"],
  ["pending", "Chờ duyệt"],
  ["pay", "Chờ thanh toán"],
  ["paid", "Đã chi"],
  ["cancelled", "Đã hủy"],
  ["all", "Tất cả"],
];
const ST = {
  pending:   { label: "Chờ duyệt", color: "#a05a00", bg: "#fff7e9" },
  approved:  { label: "Đã duyệt · chờ chi", color: "#1f4f9e", bg: "#e7efff" },
  paid:      { label: "Đã chi", color: "#1f8a5b", bg: "var(--good-weak)" },
  cancelled: { label: "Đã hủy", color: "var(--ink-4)", bg: "#eef0f3" },
};

export function CostManagementApp() {
  const isMobile = useIsMobile();
  const canEdit = !!T.canEdit;
  const costTypes = B.costTypes || [];              // loại chi phí xe
  const assetCostTypes = B.assetCostTypes || [];    // loại chi phí tài sản
  const [status, setStatus] = useState("action");
  const [kind, setKind] = useState("all");
  const [q, setQ] = useState("");
  const [page, setPage] = useState(1);
  const [data, setData] = useState(B.initial || { rows: [], total: 0, page: 1, perPage: 20, counts: {} });
  const [loading, setLoading] = useState(false);
  const [edit, setEdit] = useState(null);   // CostModal { d }
  const [pay, setPay] = useState(null);      // PayModal row
  const reqId = useRef(0);
  const qTimer = useRef(null);
  const first = useRef(true);

  const load = useCallback((o = {}) => {
    const st = o.status ?? status, kd = o.kind ?? kind, qq = o.q ?? q, pg = o.page ?? page;
    const my = ++reqId.current; setLoading(true);
    const url = ROUTES.list + "?status=" + encodeURIComponent(st) + "&kind=" + encodeURIComponent(kd) + "&q=" + encodeURIComponent(qq) + "&page=" + pg;
    api("GET", url).then((r) => {
      if (my !== reqId.current) return;
      setData(r && r.ok ? r : { rows: [], total: 0, counts: {} }); setLoading(false);
    }).catch(() => { if (my === reqId.current) { setData({ rows: [], total: 0, counts: {} }); setLoading(false); } });
  }, [status, kind, q, page]);

  useEffect(() => { if (first.current) { first.current = false; return; } load(); }, [status, kind, page]);

  const onSearch = (v) => { setQ(v); setPage(1); clearTimeout(qTimer.current); qTimer.current = setTimeout(() => load({ q: v, page: 1 }), 350); };
  const setTab = (st) => { if (st === status) return; setStatus(st); setPage(1); };
  const refresh = () => load();
  const counts = data.counts || {};
  const rows = data.rows || [];
  const totalPages = Math.max(1, Math.ceil((data.total || 0) / (data.perPage || 20)));

  // ---- Hành động ----
  const doApprove = async (row) => {
    const r = await api("PUT", ROUTES.costUpdate + row.hashid, { approved: true, amount: dig(row.amount) });
    if (r && r.ok) { window.trkToast && window.trkToast("Đã duyệt phiếu"); refresh(); }
    else window.trkToast && window.trkToast((r && r.message) || "Thao tác thất bại", "error");
  };
  const doCancel = async (row) => {
    const ok = await window.confirmAction({ title: "Hủy phiếu chi?", text: `Phiếu <b>${row.invoiceNo || row.name || ""}</b> sẽ bị hủy.`, confirmText: "Hủy phiếu", danger: true });
    if (!ok) return;
    const r = await api("PUT", ROUTES.costCancel + row.hashid + "/cancel");
    if (r && r.ok) { window.trkToast && window.trkToast("Đã hủy phiếu"); refresh(); }
    else window.trkToast && window.trkToast((r && r.message) || "Không thể hủy", "error");
  };
  const confirmPay = async (info) => {
    if (!pay) return;
    const r = await api("PUT", ROUTES.costUpdate + pay.hashid, { approved: true, paid: true, ...info, amount: dig(info.amount) });
    if (r && r.ok) { window.trkToast && window.trkToast("Đã duyệt thanh toán"); setPay(null); refresh(); }
    else window.trkToast && window.trkToast((r && r.message) || "Thao tác thất bại", "error");
  };
  const saveEdit = async () => {
    if (!edit) return; const d = edit.d;
    const r = await api("PUT", ROUTES.costUpdate + d.hashid, {
      name: d.name, kind: d.kind, amount: dig(d.amount), spendDate: d.spendDate, dueDate: d.dueDate,
      currentKm: dig(d.currentKm), supplier: d.supplier, note: d.note,
      approved: !!d.approved, paid: !!d.paid, paidDate: d.paidDate, paidMethod: d.paidMethod, paidRef: d.paidRef, paidNote: d.paidNote,
      photos: d.photos || [],
    });
    if (r && r.ok) { window.trkToast && window.trkToast("Đã lưu phiếu"); setEdit(null); refresh(); }
    else window.trkToast && window.trkToast((r && r.message) || "Lưu thất bại", "error");
  };
  const uploadPhotos = (vehHashid) => async (files) => {
    if (!files || !files.length || !vehHashid) return [];
    const fd = new FormData(); Array.from(files).forEach((f) => fd.append("files[]", f));
    try { const res = await window.trkUpload("POST", ROUTES.costPhoto + vehHashid + "/cost-photo", fd); if (res && res.ok) return res.photos || []; } catch (e) {}
    window.trkToast && window.trkToast("Tải ảnh thất bại", "error"); return [];
  };

  const btn = (label, icon, onClick, tone) => (
    <button type="button" onClick={(e) => { e.stopPropagation(); onClick(); }}
      style={{ display: "inline-flex", alignItems: "center", gap: 5, padding: "6px 11px", fontSize: 12.5, fontWeight: 700, borderRadius: 8, cursor: "pointer", whiteSpace: "nowrap",
        border: "1px solid " + (tone === "primary" ? "var(--accent)" : tone === "good" ? "var(--good)" : tone === "danger" ? "#f3c9c9" : "var(--line)"),
        background: tone === "primary" ? "var(--accent-weak)" : tone === "good" ? "var(--good-weak)" : "#fff",
        color: tone === "primary" ? "var(--accent)" : tone === "good" ? "var(--good)" : tone === "danger" ? "#dc2626" : "var(--ink-2)" }}>
      <i className={"bi " + icon} /> {label}
    </button>
  );

  const card = (row) => {
    const st = ST[row.status] || ST.pending;
    const estDiff = row.estAmount != null && dig(row.estAmount) !== dig(row.amount);
    return (
      <div key={row.id} style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "12px 14px", opacity: row.cancelled ? 0.62 : 1 }}>
        <div style={{ display: "flex", alignItems: "flex-start", gap: 10, flexWrap: "wrap" }}>
          <div style={{ flex: 1, minWidth: 200 }}>
            <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
              {row.invoiceNo && <span className="tnum" style={{ fontSize: 11.5, fontWeight: 700, color: "var(--accent)", background: "var(--accent-weak-2)", padding: "1px 7px", borderRadius: 6 }}>{row.invoiceNo}</span>}
              <span style={{ fontWeight: 700, fontSize: 14.5 }}>{row.name || "(phiếu chi)"}</span>
              <span style={{ fontSize: 11, fontWeight: 700, color: st.color, background: st.bg, padding: "2px 9px", borderRadius: 999 }}>{row.statusLabel || st.label}</span>
            </div>
            <div style={{ fontSize: 12, color: "var(--ink-4)", marginTop: 4, display: "flex", gap: 10, flexWrap: "wrap" }}>
              <span><i className={"bi " + (row.kind === "asset" ? "bi-box-seam" : "bi-truck-front")} /> {row.kind === "asset" ? "Tài sản" : "Xe"} <b className="tnum" style={{ color: "var(--ink-2)" }}>{row.targetName || row.plate}</b></span>
              {row.requester && <span><i className="bi bi-person" /> {row.requester}</span>}
              {row.spendDate && <span className="tnum"><i className="bi bi-calendar3" /> {fmtDate(row.spendDate)}</span>}
              {row.supplier && <span><i className="bi bi-shop" /> {row.supplier}</span>}
              {row.vehicleHashid && <a href={ROUTES.fleet + "#" + row.vehicleHashid + "/cost"} onClick={(e) => e.stopPropagation()} title="Mở hồ sơ xe/tài sản" style={{ color: "var(--accent)", textDecoration: "none" }}><i className="bi bi-box-arrow-up-right" /> hồ sơ</a>}
            </div>
          </div>
          <div style={{ textAlign: "right" }}>
            <div className="tnum" style={{ fontSize: 17, fontWeight: 800 }}>{fmtVND(row.amount)}</div>
            {estDiff && <div className="tnum" style={{ fontSize: 11.5, color: "var(--ink-4)" }}>Dự kiến: {fmtVND(row.estAmount)}</div>}
            {row.paid && row.paidDate && <div className="tnum" style={{ fontSize: 11, color: "var(--good)" }}>Đã chi {fmtDate(row.paidDate)}{row.paidMethod ? " · " + row.paidMethod : ""}</div>}
          </div>
        </div>
        {canEdit && !row.cancelled && (
          <div style={{ display: "flex", gap: 7, marginTop: 11, flexWrap: "wrap", paddingTop: 10, borderTop: "1px solid var(--line-2)" }}>
            {!row.approved && btn("Duyệt", "bi-check2-circle", () => doApprove(row), "primary")}
            {!row.paid && btn(row.approved ? "Thanh toán" : "Duyệt & chi", "bi-cash-coin", () => setPay(row), "good")}
            {btn("Sửa", "bi-pencil", () => setEdit({ d: { ...row, kind: row.kindCost }, isAsset: row.kind === "asset" }))}
            {row.canCancel && btn("Hủy", "bi-x-circle", () => doCancel(row), "danger")}
          </div>
        )}
        {row.cancelled && <div style={{ marginTop: 8, fontSize: 12, color: "var(--ink-4)" }}><i className="bi bi-x-circle" /> Phiếu đã hủy</div>}
      </div>
    );
  };

  const tabBtn = (k, label) => {
    const on = status === k; const n = counts[k];
    return (
      <button key={k} type="button" onClick={() => setTab(k)}
        style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 13px", fontSize: 13, fontWeight: 700, borderRadius: 999, cursor: "pointer", whiteSpace: "nowrap",
          border: "1px solid " + (on ? "var(--accent)" : "var(--line)"), background: on ? "var(--accent)" : "#fff", color: on ? "#fff" : "var(--ink-2)" }}>
        {label}{n != null && <span className="tnum" style={{ fontSize: 11, fontWeight: 700, padding: "0 6px", borderRadius: 999, minWidth: 16, textAlign: "center", background: on ? "rgba(255,255,255,.25)" : "var(--line-2)", color: on ? "#fff" : "var(--ink-3)" }}>{n}</span>}
      </button>
    );
  };

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: isMobile ? "10px 14px" : "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 12, height: isMobile ? "auto" : 58, flexWrap: "wrap", padding: isMobile ? "4px 0" : 0 }}>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center", flexShrink: 0 }}><i className="bi bi-receipt-cutoff" /></div>
          <div>
            <div style={{ fontSize: 15.5, fontWeight: 700 }}>Quản lý chi phí</div>
            <div style={{ fontSize: 11.5, color: "var(--ink-3)" }}>Tổng hợp phiếu chi xe &amp; tài sản · duyệt / thanh toán tập trung</div>
          </div>
          <div style={{ flex: 1 }} />
          <div style={{ position: "relative", width: isMobile ? "100%" : 260 }}>
            <i className="bi bi-search" style={{ position: "absolute", left: 12, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 13 }} />
            <input value={q} onChange={(e) => onSearch(e.target.value)} placeholder="Tìm khoản / # / nhà CC / biển số…"
              style={{ width: "100%", padding: "8px 12px 8px 32px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 9, outline: "none" }} />
          </div>
        </div>
        <div style={{ display: "flex", gap: 7, alignItems: "center", flexWrap: "wrap", padding: "0 0 12px" }}>
          {TABS.map(([k, l]) => tabBtn(k, l))}
          <span style={{ width: 1, height: 20, background: "var(--line)", margin: "0 4px" }} />
          {[["all", "Tất cả"], ["vehicle", "Xe"], ["asset", "Tài sản"]].map(([k, l]) => (
            <button key={k} type="button" onClick={() => { setKind(k); setPage(1); }}
              style={{ padding: "6px 11px", fontSize: 12.5, fontWeight: 600, borderRadius: 8, cursor: "pointer",
                border: "1px solid " + (kind === k ? "var(--accent)" : "var(--line)"), background: kind === k ? "var(--accent-weak-2)" : "#fff", color: kind === k ? "var(--accent)" : "var(--ink-3)" }}>{l}</button>
          ))}
        </div>
      </header>

      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: isMobile ? "12px 12px 24px" : "16px 22px 24px" }}>
        <div style={{ maxWidth: 940, margin: "0 auto", display: "flex", flexDirection: "column", gap: 10 }}>
          {loading && <div style={{ padding: "30px", textAlign: "center", color: "var(--ink-4)" }}><i className="bi bi-arrow-repeat" style={{ animation: "trk-spin .7s linear infinite" }} /> Đang tải…</div>}
          {!loading && rows.length === 0 && <div style={{ padding: "40px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5, background: "#fff", border: "1px solid var(--line)", borderRadius: 12 }}>Không có phiếu chi nào khớp bộ lọc.</div>}
          {!loading && rows.map((row) => card(row))}

          {totalPages > 1 && (
            <div style={{ display: "flex", alignItems: "center", justifyContent: "center", gap: 12, marginTop: 6 }}>
              <button type="button" disabled={page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))} style={{ padding: "7px 14px", fontSize: 13, borderRadius: 8, border: "1px solid var(--line)", background: "#fff", cursor: page <= 1 ? "default" : "pointer", opacity: page <= 1 ? 0.5 : 1 }}>‹ Trước</button>
              <span style={{ fontSize: 13, color: "var(--ink-3)" }}>Trang <b>{data.page || page}</b> / {totalPages} · {data.total} phiếu</span>
              <button type="button" disabled={page >= totalPages} onClick={() => setPage((p) => Math.min(totalPages, p + 1))} style={{ padding: "7px 14px", fontSize: 13, borderRadius: 8, border: "1px solid var(--line)", background: "#fff", cursor: page >= totalPages ? "default" : "pointer", opacity: page >= totalPages ? 0.5 : 1 }}>Sau ›</button>
            </div>
          )}
        </div>
      </div>

      {pay && <PayModal row={pay} onConfirm={confirmPay} onClose={() => setPay(null)} />}
      {edit && <CostModal data={edit.d} isNew={false} costTypes={edit.isAsset ? assetCostTypes : costTypes}
        onUploadPhotos={uploadPhotos(edit.d.vehicleHashid)}
        onChange={(d) => setEdit((e) => ({ ...e, d }))} onSave={saveEdit} onClose={() => setEdit(null)} />}
    </div>
  );
}
