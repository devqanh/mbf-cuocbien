import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useRef } = React;
import { SavedExtStatementPage } from "@trk/ui/ext-statement.jsx";

function ViewExtStatementApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const api = (method, url, body) => window.trkApi(method, url, body);

  const [st, setSt] = useState(B.st || null);
  const dirtyIds = useRef(new Set());
  const isDirty = (id) => dirtyIds.current.has(id);

  if (!st) return <div style={{ display: "grid", placeItems: "center", height: "100%", color: "var(--ink-4)" }}>Không tìm thấy bảng kê.</div>;

  const onUpdate = (ns) => { dirtyIds.current.add(ns.id); setSt(ns); };
  const onSave = () => api("PUT", ROUTES.extStatement + (st.hashid || st.id), { statement: st })
    .then((r) => {
      if (r && r.ok) {
        dirtyIds.current.delete(st.id);
        if (r.statement) setSt(r.statement);
        window.trkToast && window.trkToast("Đã lưu");
        return true;
      }
      window.trkToast && window.trkToast("Lưu thất bại", "error"); return false;
    })
    .catch(() => { window.trkToast && window.trkToast("Lỗi kết nối khi lưu", "error"); return false; });
  const onDelete = () => api("DELETE", ROUTES.extStatement + (st.hashid || st.id));

  return <SavedExtStatementPage st={st} onUpdate={onUpdate} onSave={onSave} onDelete={onDelete} isDirty={isDirty} backUrl={ROUTES.list} />;
}

createRoot(document.getElementById("trk-root")).render(<ViewExtStatementApp />);
