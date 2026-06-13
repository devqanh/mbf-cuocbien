import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState } = React;
import { KePage } from "@trk/ui.jsx";

function StatementsApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const [ke] = useState(B.ke || []);

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <KePage ke={ke} onNew={() => { window.location.href = ROUTES.create; }} onOpen={(st) => { window.location.href = ROUTES.view + st.id; }} />
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<StatementsApp />);

