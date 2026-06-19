import React from "react";
import { useIsMobile } from "@trk/lib.jsx";
import { PriceList } from "@trk/pop.jsx";

function BangGiaPage({ cfg, setCfg, onImported, loadPrices }) {
  const { useState, useEffect } = React;
  const isMobile = useIsMobile();
  const customers = cfg.customers || [];
  const info = cfg.customerInfo || {};
  const [sel, setSel] = useState(customers[0] || null);
  const cur = sel != null && customers.includes(sel) ? sel : (customers[0] || null);
  const data = (cur && info[cur]) || {};
  const loaded = Array.isArray(data.priceList);   // có key priceList = đã lazy-load xong
  const [loadingCur, setLoadingCur] = useState(false);
  // Lazy-load bảng giá của khách đang chọn nếu chưa có (priceList chưa phải mảng)
  useEffect(() => {
    if (cur && loadPrices && !Array.isArray((info[cur] || {}).priceList)) {
      setLoadingCur(true);
      Promise.resolve(loadPrices(cur)).finally(() => setLoadingCur(false));
    }
  }, [cur]);
  const setPrice = (arr) => setCfg("customerInfo", { ...info, [cur]: { ...data, priceList: arr } });
  const priceImported = (arr) => (onImported ? onImported(cur, arr) : setPrice(arr));
  return (
    <div style={{ flex: 1, minHeight: 0, display: "flex", flexDirection: isMobile ? "column" : "row", overflow: "hidden" }}>
      {/* customer list — dọc trên desktop, thanh chọn ngang cuộn được trên mobile */}
      <div style={{ width: isMobile ? "100%" : 240, flexShrink: 0, borderRight: isMobile ? "none" : "1px solid var(--line)", borderBottom: isMobile ? "1px solid var(--line)" : "none", background: "#fff", overflowY: isMobile ? "visible" : "auto", overflowX: isMobile ? "auto" : "visible", padding: isMobile ? "10px 12px" : "14px 12px" }}>
        <div style={{ fontSize: 11, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.04em", padding: "2px 8px 8px" }}>Khách hàng</div>
        <div style={{ display: "flex", flexDirection: isMobile ? "row" : "column", gap: isMobile ? 7 : 1, flexWrap: isMobile ? "nowrap" : "wrap" }}>
          {customers.map((name) => {
            const active = cur === name;
            const ci = info[name] || {};
            const n = Array.isArray(ci.priceList) ? ci.priceList.length : (ci.priceCount || 0);
            return (
              <button key={name} type="button" onClick={() => setSel(name)}
                style={{ textAlign: "left", border: isMobile ? "1px solid var(--line)" : "none", cursor: "pointer", borderRadius: isMobile ? 999 : 8, padding: "9px 11px", display: "flex", alignItems: "center", justifyContent: "space-between", gap: 8, flexShrink: 0, whiteSpace: "nowrap",
                  background: active ? "var(--accent-weak)" : (isMobile ? "#fff" : "transparent"), color: active ? "var(--accent)" : "var(--ink)", fontWeight: active ? 600 : 400, fontSize: 13.5 }}
                onMouseEnter={(e) => { if (!active && !isMobile) e.currentTarget.style.background = "var(--line-2)"; }}
                onMouseLeave={(e) => { if (!active && !isMobile) e.currentTarget.style.background = "transparent"; }}>
                <span style={{ whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{name}</span>
                {n > 0 && <span className="tnum" style={{ fontSize: 11, fontWeight: 600, color: active ? "var(--accent)" : "var(--ink-4)", background: active ? "#fff" : "var(--line-2)", padding: "1px 7px", borderRadius: 999 }}>{n}</span>}
              </button>
            );
          })}
          {!customers.length && <div style={{ padding: "16px 8px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có khách hàng. Thêm trong Cấu hình → Khách hàng.</div>}
        </div>
      </div>
      {/* price list */}
      <div style={{ flex: 1, minWidth: 0, overflowY: "auto", padding: isMobile ? "16px 14px 40px" : "24px 28px 40px" }}>
        {cur ? (
          <div style={{ maxWidth: 880, margin: "0 auto" }}>
            <div style={{ marginBottom: 16 }}>
              <h1 style={{ margin: 0, fontSize: 21, fontWeight: 700, letterSpacing: "-0.02em" }}>Bảng giá đã gửi</h1>
              <div style={{ fontSize: 13.5, color: "var(--ink-3)", marginTop: 3 }}>{cur}{data.taxCode ? ` · MST ${data.taxCode}` : ""}</div>
            </div>
            <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "16px 18px" }}>
              {loaded
                ? <PriceList rows={data.priceList || []} onChange={setPrice} onImported={priceImported} cfg={cfg} customer={cur} />
                : <div style={{ display: "flex", alignItems: "center", justifyContent: "center", gap: 8, padding: "40px", color: "var(--ink-4)", fontSize: 13.5 }}><i className="bi bi-arrow-repeat" style={{ animation: "trk-spin 0.7s linear infinite" }} /> Đang tải bảng giá…</div>}
            </div>
          </div>
        ) : (
          <div style={{ display: "grid", placeItems: "center", height: "100%", color: "var(--ink-4)", fontSize: 13.5 }}>Chọn một khách hàng để xem bảng giá.</div>
        )}
      </div>
    </div>
  );
}

export { BangGiaPage };
