import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useEffect } = React;
import { KePage } from "@trk/ui.jsx";
import { CustomersInPeriod } from "@trk/components/bang-ke/CustomersInPeriod.jsx";

function StatementsApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const [ke, setKe] = useState(B.ke || []);
  // Gắn hạn thanh toán ngay trên danh sách (chỉ ghi info.dueDate) → cập nhật dòng để bộ lọc Quá hạn / Sắp đến hạn đổi theo.
  const onSetDue = T.canEdit ? async (st, dueDate) => {
    const r = await window.trkApi("PATCH", ROUTES.statement + (st.hashid || st.id) + "/due", { dueDate: dueDate || null });
    if (r && r.ok) setKe((arr) => arr.map((x) => (x.id === st.id ? { ...x, dueDate: r.dueDate || "" } : x)));
    return r;
  } : null;
  // Đối soát lệch (LAZY): danh sách render ngay từ boot, sau đó hỏi server bảng kê nào
  // có lô lệch phải thu so với snapshot → gắn cảnh báo "cần tính lại".
  const [drift, setDrift] = useState({});
  useEffect(() => {
    if (!ROUTES.drift || !(B.ke || []).length) return;
    window.trkApi("GET", ROUTES.drift)
      .then((r) => { if (r && r.ok) setDrift(r.drift || {}); })
      .catch(() => {});
  }, []);

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <KePage ke={ke} drift={drift} onNew={() => { window.location.href = ROUTES.create; }} onOpen={(st) => { window.location.href = ROUTES.view + (st.hashid || st.id); }} onSetDue={onSetDue}>
        <CustomersInPeriod routes={ROUTES} />
      </KePage>
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<StatementsApp />);

