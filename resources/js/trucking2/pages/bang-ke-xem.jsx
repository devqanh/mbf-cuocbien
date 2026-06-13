import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useRef, useMemo, useEffect } = React;
import { SavedStatementPage, makePricer } from "@trk/ui.jsx";

function ViewStatementApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const api = (method, url, body) => window.trkApi(method, url, body);

  const [st, setSt] = useState(B.st || null);
  const [liveDetail, setLiveDetail] = useState(null);   // chỉ set sau khi bấm "Tính lại" (query realtime)
  const dirtyIds = useRef(new Set());
  const isDirty = (id) => dirtyIds.current.has(id);

  // Chi tiết đối soát TĨNH lấy từ SNAPSHOT đã lưu (không query realtime khi xem)
  const snapDetail = useMemo(() => {
    const d = {};
    (st ? st.lines || [] : []).forEach((l) => { if (l.detail) d[l.id] = { found: true, ...l.detail }; });
    return d;
  }, [st]);
  const detailById = liveDetail || snapDetail;   // sau "Tính lại" thì hiện số realtime

  if (!st) return <div style={{ display: "grid", placeItems: "center", height: "100%", color: "var(--ink-4)" }}>Không tìm thấy bảng kê.</div>;

  const onUpdate = (ns) => { dirtyIds.current.add(ns.id); setSt(ns); };
  const onSave = () => api("PUT", ROUTES.statement + st.id, { statement: st })
    .then((r) => { if (r && r.ok) { dirtyIds.current.delete(st.id); setLiveDetail(null); window.trkToast && window.trkToast("Đã lưu"); return true; } window.trkToast && window.trkToast("Lưu thất bại", "error"); return false; })
    .catch(() => { window.trkToast && window.trkToast("Lỗi kết nối khi lưu", "error"); return false; });
  const onDelete = (id) => api("DELETE", ROUTES.statement + id);
  // Xuất Excel: tải từ server (PhpSpreadsheet dựng theo mẫu chính thức, giữ định dạng)
  const onExcel = () => { window.location.href = ROUTES.base + st.id + "/excel"; };

  // Tính lại: HỎI xác nhận → mới query realtime (bảng giá + lô hiện tại) để tính.
  // Bảng kê đã lưu là dữ liệu TĨNH — chỉ thay đổi khi bấm Lưu sau khi kiểm tra.
  const onRecalc = async () => {
    const ok = await window.confirmAction({
      title: "Tính lại bảng kê?",
      text: "Bảng kê đã lưu là <b>dữ liệu tĩnh</b> (giữ nguyên kể cả khi lô hàng bị sửa/xóa bên ngoài).<br/>Tính lại sẽ <b>truy vấn dữ liệu lô hàng &amp; bảng giá HIỆN TẠI</b> để đối chiếu. Thay đổi chỉ áp dụng khi bạn bấm <b>Lưu</b>.",
      confirmText: "Tính lại theo dữ liệu hiện tại",
      cancelText: "Huỷ",
    });
    if (!ok) return;

    const r = await api("GET", ROUTES.base + st.id + "/context").catch(() => null);
    if (!r || !r.ok) { window.trkToast && window.trkToast("Không tải được dữ liệu để tính lại", "error"); return; }
    const { priceFor } = makePricer(r.cfg || {});
    const shipById = {}; (r.ships || []).forEach((s) => { shipById[s.id] = s; });

    const live = {}; let changed = 0;
    // So sánh cả các trường HIỂN THỊ (không chỉ số tiền) để nếu tuyến/kết nối/chi tiết đổi
    // thì vẫn cho Lưu — vd tuyến giờ hiển thị theo TÊN thay vì ký hiệu.
    const cmpFields = ["phaiThu", "cuoc", "dau", "chiHo", "route", "conn", "kind", "is20"];
    const lines = (st.lines || []).map((l) => {
      const s = shipById[l.id];
      if (!s) { live[l.id] = { found: false }; return l; }
      const pr = priceFor(s);
      live[l.id] = { found: true, ...pr };
      const od = l.detail || {};
      const diff = (pr.phaiThu || 0) !== (l.phaiThu || 0) || cmpFields.some((k) => {
        const a = pr[k], b = od[k];
        return (typeof a === "number" || typeof b === "number") ? ((a || 0) !== (b || 0)) : ((a ?? "") !== (b ?? ""));
      });
      if (diff) changed++;
      return { ...l, phaiThu: pr.phaiThu, cuoc: pr.phaiThu,
        detail: { ...pr } };   // snapshot đầy đủ (conn/kind/loại/tuyến/free time)
    });
    setLiveDetail(live);
    if (changed) { dirtyIds.current.add(st.id); setSt({ ...st, lines }); }
    window.trkToast && window.trkToast(changed ? ("Đã tính lại " + changed + " dòng — kiểm tra rồi bấm Lưu để chốt") : "Khớp dữ liệu hiện tại — không có chênh lệch");
  };

  return <SavedStatementPage st={st} onUpdate={onUpdate} onSave={onSave} onDelete={onDelete} isDirty={isDirty} backUrl={ROUTES.list} onExcel={onExcel} onRecalc={onRecalc} detailById={detailById} />;
}

createRoot(document.getElementById("trk-root")).render(<ViewStatementApp />);

