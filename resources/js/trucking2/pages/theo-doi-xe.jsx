import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";
import { TrackingApp } from "@trk/components/theo-doi-xe/TrackingApp.jsx";

createRoot(document.getElementById("trk-root")).render(<TrackingApp />);
