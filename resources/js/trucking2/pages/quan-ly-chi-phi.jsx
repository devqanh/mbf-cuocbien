import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";
import { CostManagementApp } from "@trk/components/cost-management/CostManagementApp.jsx";

createRoot(document.getElementById("trk-root")).render(<CostManagementApp />);
