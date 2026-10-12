import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useRef, useMemo } = React;
import { SavedStatementPage, isManualLine, suggestionOf, applySuggestion, candidateToLine } from "@trk/ui.jsx";

function ViewStatementApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const api = (method, url, body) => window.trkApi(method, url, body);

  const [st, setSt] = useState(B.st || null);
  // Kết quả "Tính lại" = GỢI Ý theo dòng ({found, ...pr}); KHÔNG đụng st.lines cho tới khi bấm Áp dụng.
  const [suggestById, setSuggestById] = useState(null);
  const [newLots, setNewLots] = useState(null);          // lô mới trong kỳ chưa có trong bảng kê (sau Tính lại)
  const [inOther, setInOther] = useState(0);             // lô trong kỳ đã ở bảng kê khác (không đề xuất)
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
      text: "Hệ thống sẽ <b>truy vấn lô hàng &amp; bảng giá HIỆN TẠI</b>: <b>gợi ý</b> giá / chi hộ mới bên cạnh từng dòng và tìm <b>lô mới trong kỳ</b>.<br/>Số đã lưu <b>không đổi</b> cho tới khi bạn bấm <b>Áp dụng</b> / <b>Thêm lô</b> rồi <b>Lưu</b>. <b>Giá tùy chỉnh</b> bạn đã gõ <b>luôn giữ nguyên</b> (Áp dụng chỉ cập nhật chi hộ).",
      confirmText: "Tính lại và xem gợi ý",
      cancelText: "Huỷ",
    });
    if (!ok) return;

    // Kỳ + phạm vi ĐANG HIỆN (có thể vừa sửa chưa lưu) → backend tìm lô mới trong đúng kỳ đó.
    const q = new URLSearchParams(); if (st.from) q.set("from", st.from); if (st.to) q.set("to", st.to); if (st.ioScope) q.set("io", st.ioScope);
    const r = await api("GET", ROUTES.base + (st.hashid || st.id) + "/reprice?" + q.toString()).catch(() => null);
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
    const nAdd = (r.newCandidates || []).length;
    setNewLots(r.newCandidates || []); setInOther(r.inOtherStatements || 0);
    const parts = [nNew && `${nNew} dòng có giá / chi hộ mới${nManual ? ` (${nManual} dòng giá gõ tay giữ nguyên giá)` : ""}`, nAdd && `${nAdd} lô mới trong kỳ`].filter(Boolean);
    toast(parts.length ? `${parts.join(" · ")} — xem gợi ý, bấm Áp dụng / Thêm lô rồi Lưu` : "Khớp dữ liệu hiện tại — không có chênh lệch, không có lô mới");
  };

  // Áp gợi ý vào 1 dòng (kể cả dòng giá tùy chỉnh — người dùng chủ động bấm ở đúng dòng đó).
  const onApplyLine = (id) => {
    const s = suggestById && suggestById[id];
    if (!s || !s.found) return;
    onUpdate({ ...st, lines: (st.lines || []).map((l) => (l.id === id ? applySuggestion(l, s) : l)) });
  };
  // Áp mọi gợi ý — dòng giá tùy chỉnh giữ giá gõ tay, chỉ cập nhật chi hộ + thông tin (xem applySuggestion).
  const onApplyAll = () => {
    if (!suggestById) return;
    const vr = +st.vatRate || 0; let n = 0, m = 0;
    const lines = (st.lines || []).map((l) => {
      const s = suggestById[l.id];
      if (!s || !s.found || !suggestionOf(l, s, vr)) return l;
      n++; if (isManualLine(l)) m++; return applySuggestion(l, s);
    });
    if (!n) return;
    onUpdate({ ...st, lines });
    toast(`Đã áp dụng ${n} dòng${m ? ` (${m} dòng giữ giá gõ tay)` : ""} — bấm Lưu để chốt`);
  };
  // Thêm lô mới trong kỳ vào cuối bảng kê (giá hệ thống; gõ tay lại được) → bấm Lưu để chốt.
  const onAddNewLots = () => {
    if (!newLots || !newLots.length) return;
    const have = new Set((st.lines || []).map((l) => String(l.id)));
    const add = newLots.filter((x) => !have.has(String(x.id))).map(candidateToLine);
    if (!add.length) { setNewLots([]); return; }
    onUpdate({ ...st, lines: [...(st.lines || []), ...add] });
    setNewLots([]);
    toast(`Đã thêm ${add.length} lô — kiểm tra giá rồi bấm Lưu`);
  };

  return <SavedStatementPage st={st} onUpdate={onUpdate} onSave={onSave} onDelete={onDelete} isDirty={isDirty} backUrl={ROUTES.list} onExcel={onExcel} onRecalc={onRecalc}
    detailById={snapDetail} suggestById={suggestById} onApplyLine={onApplyLine} onApplyAll={onApplyAll} onClearSuggest={() => { setSuggestById(null); setNewLots(null); setInOther(0); }}
    newLots={newLots} inOtherStatements={inOther} onAddNewLots={onAddNewLots} />;
}

createRoot(document.getElementById("trk-root")).render(<ViewStatementApp />);
