import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useRef, useMemo } = React;
import { SavedStatementPage, isManualLine, suggestionOf, applySuggestion } from "@trk/ui.jsx";

function ViewStatementApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const api = (method, url, body) => window.trkApi(method, url, body);

  const [st, setSt] = useState(B.st || null);
  // Kết quả "Tính lại" = GỢI Ý theo dòng ({found, ...pr}); KHÔNG đụng st.lines cho tới khi bấm Áp dụng.
  const [suggestById, setSuggestById] = useState(null);
  const dirtyIds = useRef(new Set());
  const isDirty = (id) => dirtyIds.current.has(id);

  // Chi tiết đối soát TĨNH lấy từ SNAPSHOT đã lưu (không query realtime khi xem)
  const snapDetail = useMemo(() => {
    const d = {};
    (st ? st.lines || [] : []).forEach((l) => { if (l.detail) d[l.id] = { found: true, ...l.detail }; });
    return d;
  }, [st]);

  if (!st) return <div style={{ display: "grid", placeItems: "center", height: "100%", color: "var(--ink-4)" }}>Không tìm thấy bảng kê.</div>;

  const toast = (msg, kind) => window.trkToast && window.trkToast(msg, kind);
  const onUpdate = (ns) => { dirtyIds.current.add(ns.id); setSt(ns); };
  const onSave = () => api("PUT", ROUTES.statement + (st.hashid || st.id), { statement: st })
    .then((r) => { if (r && r.ok) { dirtyIds.current.delete(st.id); toast("Đã lưu"); return true; } toast("Lưu thất bại", "error"); return false; })
    .catch(() => { toast("Lỗi kết nối khi lưu", "error"); return false; });
  const onDelete = () => api("DELETE", ROUTES.statement + (st.hashid || st.id));
  // Xuất Excel: tải từ server (PhpSpreadsheet dựng theo mẫu chính thức, giữ định dạng)
  const onExcel = () => { window.location.href = ROUTES.base + (st.hashid || st.id) + "/export-excel"; };

  // Tính lại: HỎI xác nhận → query realtime (bảng giá + lô hiện tại) → chỉ GỢI Ý bên cạnh từng dòng.
  // Số đã lưu (kể cả giá tùy chỉnh) giữ nguyên; đổi khi bấm Áp dụng (từng dòng / tất cả dòng không tùy chỉnh) rồi Lưu.
  const onRecalc = async () => {
    const ok = await window.confirmAction({
      title: "Tính lại bảng kê?",
      text: "Hệ thống sẽ <b>truy vấn lô hàng &amp; bảng giá HIỆN TẠI</b> và <b>gợi ý</b> giá mới bên cạnh từng dòng.<br/>Số đã lưu <b>không đổi</b>, <b>giá tùy chỉnh</b> bạn đã gõ <b>giữ nguyên</b> — chỉ đổi khi bạn bấm <b>Áp dụng</b> rồi <b>Lưu</b>.",
      confirmText: "Tính lại và xem gợi ý",
      cancelText: "Huỷ",
    });
    if (!ok) return;

    const r = await api("GET", ROUTES.base + (st.hashid || st.id) + "/reprice").catch(() => null);
    if (!r || !r.ok) { toast("Không tải được dữ liệu để tính lại", "error"); return; }
    const rep = r.repriced || {};   // { shipmentId => { pr, ... } } — đã định giá ở BACKEND

    const sug = {}; let nNew = 0, nManual = 0;
    const vr = +st.vatRate || 0;
    (st.lines || []).forEach((l) => {
      const c = rep[String(l.id)];
      sug[l.id] = c ? { found: true, ...c.pr } : { found: false };
      if (suggestionOf(l, sug[l.id], vr)) { nNew++; if (isManualLine(l)) nManual++; }
    });
    setSuggestById(sug);
    toast(nNew
      ? `${nNew} dòng có giá mới${nManual ? ` (${nManual} dòng giá tùy chỉnh được giữ)` : ""} — xem gợi ý, bấm Áp dụng rồi Lưu`
      : "Khớp dữ liệu hiện tại — không có chênh lệch");
  };

  // Áp gợi ý vào 1 dòng (kể cả dòng giá tùy chỉnh — người dùng chủ động bấm ở đúng dòng đó).
  const onApplyLine = (id) => {
    const s = suggestById && suggestById[id];
    if (!s || !s.found) return;
    onUpdate({ ...st, lines: (st.lines || []).map((l) => (l.id === id ? applySuggestion(l, s) : l)) });
  };
  // Áp mọi gợi ý cho dòng KHÔNG phải giá tùy chỉnh.
  const onApplyAll = () => {
    if (!suggestById) return;
    const vr = +st.vatRate || 0; let n = 0;
    const lines = (st.lines || []).map((l) => {
      const s = suggestById[l.id];
      if (!s || !s.found || isManualLine(l) || !suggestionOf(l, s, vr)) return l;
      n++; return applySuggestion(l, s);
    });
    if (!n) return;
    onUpdate({ ...st, lines });
    toast(`Đã áp dụng ${n} dòng — bấm Lưu để chốt`);
  };

  return <SavedStatementPage st={st} onUpdate={onUpdate} onSave={onSave} onDelete={onDelete} isDirty={isDirty} backUrl={ROUTES.list} onExcel={onExcel} onRecalc={onRecalc}
    detailById={snapDetail} suggestById={suggestById} onApplyLine={onApplyLine} onApplyAll={onApplyAll} onClearSuggest={() => setSuggestById(null)} />;
}

createRoot(document.getElementById("trk-root")).render(<ViewStatementApp />);
