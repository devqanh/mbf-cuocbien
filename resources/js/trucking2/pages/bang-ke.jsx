import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useEffect } = React;
import { KePage } from "@trk/ui.jsx";

function StatementsApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const [ke] = useState(B.ke || []);
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
      <KePage ke={ke} drift={drift} onNew={() => { window.location.href = ROUTES.create; }} onOpen={(st) => { window.location.href = ROUTES.view + (st.hashid || st.id); }} />
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<StatementsApp />);

