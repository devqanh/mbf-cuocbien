import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState } = React;
import { ExtKePage } from "@trk/ui/ext-statement.jsx";

function ExtStatementsApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const [ke] = useState(B.ke || []);

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <ExtKePage ke={ke} onNew={() => { window.location.href = ROUTES.create; }} onOpen={(st) => { window.location.href = ROUTES.view + (st.hashid || st.id); }} />
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<ExtStatementsApp />);
