import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";
const { useState, useEffect, useRef } = React;
import { FleetApp } from "@trk/components/quan-ly-xe/FleetApp.jsx";
import { AssetApp } from "@trk/components/quan-ly-xe/asset.jsx";

/* ---- Toggle Xe | Tài sản + Root ---- */
function ModeToggle({ mode, setMode }) {
  const opt = (k, label, icon) => { const on = mode === k; return (
    <button key={k} type="button" onClick={() => setMode(k)}
      style={{ display: "inline-flex", alignItems: "center", gap: 7, border: "none", cursor: "pointer", fontSize: 13.5, fontWeight: 700, padding: "8px 18px", borderRadius: 8, background: on ? "#fff" : "transparent", color: on ? "var(--accent)" : "var(--ink-3)", boxShadow: on ? "0 1px 3px rgba(16,19,23,.14)" : "none" }}>
      <i className={"bi " + icon} /> {label}
    </button>
  ); };
  return (
    <div style={{ display: "inline-flex", background: "#eceef1", borderRadius: 10, padding: 4, gap: 2, marginBottom: 16 }}>
      {opt("vehicle", "Xe", "bi-truck")}
      {opt("asset", "Tài sản", "bi-box-seam")}
    </div>
  );
}

function Root() {
  const ROUTES = (window.__TRK || {}).routes || {};
  const [mode, setMode] = useState(() => {
    try { const h = window.location.hash || ""; if (/^#asset\//.test(h)) return "asset"; if (/^#\d+/.test(h)) return "vehicle"; } catch (e) {}
    try { return localStorage.getItem("trk-fleet-mode") === "asset" ? "asset" : "vehicle"; } catch (e) { return "vehicle"; }
  });
  const [aData, setAData] = useState({ loaded: false, assets: [], categories: [] });
  const fetched = useRef(false);
  useEffect(() => {
    if (mode !== "asset" || fetched.current) return;
    fetched.current = true;
    window.trkApi("GET", ROUTES.assetList)
      .then((r) => setAData(r && r.ok ? { loaded: true, assets: r.assets || [], categories: r.assetCategories || [] } : (s) => ({ ...s, loaded: true })))
      .catch(() => setAData((s) => ({ ...s, loaded: true })));
  }, [mode]);
  const setAssets = (u) => setAData((s) => ({ ...s, assets: typeof u === "function" ? u(s.assets) : u }));
  const setCategories = (u) => setAData((s) => ({ ...s, categories: typeof u === "function" ? u(s.categories) : u }));
  const change = (m) => { setMode(m); try { localStorage.setItem("trk-fleet-mode", m); } catch (e) {} };
  const sw = <ModeToggle mode={mode} setMode={change} />;
  return mode === "asset"
    ? <AssetApp modeSwitch={sw} assets={aData.assets} setAssets={setAssets} categories={aData.categories} setCategories={setCategories} loaded={aData.loaded} />
    : <FleetApp modeSwitch={sw} />;
}

createRoot(document.getElementById("trk-root")).render(<Root />);
