import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useEffect, useRef, useMemo } = React;
import { I, useIsMobile } from "@trk/lib.jsx";

const POLL_MS = 15000;            // chu kỳ poll
const STALE_MS = 10 * 60 * 1000;  // > 10 phút không cập nhật = mất tín hiệu

/* ---- trạng thái xe → màu / nhãn ---- */
const STATUS = {
  run:  { color: "#1f8a5b", label: "Đang chạy" },
  idle: { color: "#e08600", label: "Dừng (nổ máy)" },
  off:  { color: "#8a94a6", label: "Tắt máy" },
  lost: { color: "#d64545", label: "Mất tín hiệu" },
};
const effStatus = (p) => (p.ts && Date.now() - p.ts > STALE_MS ? "lost" : (p.status || "off"));
const statusColor = (p) => (STATUS[effStatus(p)] || STATUS.off).color;

const esc = (s) => String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
const timeAgo = (ts) => {
  if (!ts) return "—";
  const s = Math.max(0, Math.floor((Date.now() - ts) / 1000));
  if (s < 60) return s + " giây trước";
  if (s < 3600) return Math.floor(s / 60) + " phút trước";
  if (s < 86400) return Math.floor(s / 3600) + " giờ trước";
  return Math.floor(s / 86400) + " ngày trước";
};
const fmtClock = (ts) => { if (!ts) return ""; const d = new Date(ts); const z = (n) => String(n).padStart(2, "0"); return `${z(d.getHours())}:${z(d.getMinutes())} ${z(d.getDate())}/${z(d.getMonth() + 1)}`; };

function popupHtml(p) {
  const st = STATUS[effStatus(p)] || STATUS.off;
  return `<div style="font-size:12.5px;line-height:1.55;min-width:180px;padding:2px">
    <div style="font-weight:700;font-size:14px">${esc(p.plate)} ${p.matched ? '<span style="color:#1f8a5b;font-size:11px">✓ xe hệ thống</span>' : ""}</div>
    <div style="display:inline-flex;align-items:center;gap:5px;margin:3px 0"><span style="width:8px;height:8px;border-radius:50%;background:${st.color};display:inline-block"></span><b style="color:${st.color}">${st.label}</b> · ${Math.round(p.speed || 0)} km/h</div>
    ${p.driver ? `<div>Tài xế: <b>${esc(p.driver)}</b></div>` : ""}
    ${p.address ? `<div style="color:#5b6470">${esc(p.address)}</div>` : ""}
    <div style="color:#8a94a6;margin-top:3px">${esc(p.providerLabel)} · ${esc(fmtClock(p.ts))} (${esc(timeAgo(p.ts))})</div>
  </div>`;
}

/* Hình Ô TÔ nhìn từ trên (đầu xe hướng LÊN) — SVG data-URI, xoay theo `angle`, thân tô màu theo status.
   Dùng url icon (không phải Symbol path) để hình rõ ràng giống xe; xoay bằng transform trong SVG. */
function vehicleSvg(color, angle) {
  return `<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48">
    <g transform="rotate(${angle} 24 24)">
      <rect x="9" y="13" width="5" height="8.5" rx="2.2" fill="#23272e"/>
      <rect x="34" y="13" width="5" height="8.5" rx="2.2" fill="#23272e"/>
      <rect x="9" y="26.5" width="5" height="8.5" rx="2.2" fill="#23272e"/>
      <rect x="34" y="26.5" width="5" height="8.5" rx="2.2" fill="#23272e"/>
      <rect x="13" y="6" width="22" height="36" rx="7" fill="${color}" stroke="#ffffff" stroke-width="2"/>
      <path d="M16.5 14 L31.5 14 L29 9.6 Q24 7.6 19 9.6 Z" fill="#e8f1ff"/>
      <rect x="17" y="32.5" width="14" height="5" rx="2" fill="#ffffff" opacity="0.55"/>
      <rect x="17.5" y="18" width="13" height="11" rx="3" fill="#ffffff" opacity="0.22"/>
    </g>
  </svg>`;
}
function iconFor(maps, p, selected) {
  const color = statusColor(p);
  const sz = selected ? 52 : 42;
  const svg = vehicleSvg(color, Math.round(p.angle || 0));
  return {
    url: "data:image/svg+xml;charset=UTF-8," + encodeURIComponent(svg),
    scaledSize: new maps.Size(sz, sz),
    anchor: new maps.Point(sz / 2, sz / 2),
  };
}

/* Trượt marker từ vị trí hiện tại tới vị trí mới trong ~1.2s (cảm giác xe chạy realtime,
   không nhảy giật mỗi 15s). Nhảy thẳng nếu lệch quá lớn (>~8km) để tránh trôi vô lý. */
function tweenMarker(marker, to, dur = 1200) {
  const cur = marker.getPosition && marker.getPosition();
  if (!cur) { marker.setPosition(to); return; }
  const from = { lat: cur.lat(), lng: cur.lng() };
  const d = Math.abs(from.lat - to.lat) + Math.abs(from.lng - to.lng);
  if (d < 1e-7) return;                                        // không đổi → khỏi animate
  if (d > 0.08) { marker.setPosition(to); return; }            // lệch quá lớn → nhảy thẳng
  if (marker.__anim) cancelAnimationFrame(marker.__anim);
  const t0 = performance.now();
  const step = (now) => {
    const t = Math.min(1, (now - t0) / dur);
    const e = t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;   // easeInOutQuad
    marker.setPosition({ lat: from.lat + (to.lat - from.lat) * e, lng: from.lng + (to.lng - from.lng) * e });
    if (t < 1) marker.__anim = requestAnimationFrame(step);
  };
  marker.__anim = requestAnimationFrame(step);
}

/* CSS pill biển số gắn dưới marker (1 lần). margin-top đẩy xuống dưới icon, không lệch ngang. */
function ensurePlateStyle() {
  if (document.getElementById("trk-plate-style")) return;
  const s = document.createElement("style");
  s.id = "trk-plate-style";
  s.textContent = ".trk-plate{margin-top:30px;background:#fff;border:1px solid rgba(0,0,0,.2);border-radius:6px;padding:1px 6px;box-shadow:0 1px 3px rgba(0,0,0,.3);white-space:nowrap}";
  document.head.appendChild(s);
}

/* Loader Google Maps JS API (1 lần, dùng callback). */
let gmapsPromise = null;
function loadGoogleMaps(key) {
  if (window.google && window.google.maps) return Promise.resolve(window.google.maps);
  if (gmapsPromise) return gmapsPromise;
  gmapsPromise = new Promise((resolve, reject) => {
    const cb = "__trkGmapsCb";
    window[cb] = () => resolve(window.google.maps);
    const s = document.createElement("script");
    s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&callback=${cb}&loading=async&language=vi&region=VN`;
    s.async = true; s.defer = true;
    s.onerror = () => reject(new Error("Không tải được Google Maps (kiểm tra API key / mạng)."));
    document.head.appendChild(s);
  });
  return gmapsPromise;
}

function TrackingApp() {
  const isMobile = useIsMobile();
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const canEdit = !!T.canEdit;
  const api = (m, u, b) => window.trkApi(m, u, b);

  const [positions, setPositions] = useState([]);
  const [providers, setProviders] = useState(B.providers || []);
  const [lastTs, setLastTs] = useState(0);
  const [loading, setLoading] = useState(true);
  const [q, setQ] = useState("");
  const [fStatus, setFStatus] = useState("all");
  const [fProvider, setFProvider] = useState("all");
  const [matchedOnly, setMatchedOnly] = useState(false);
  const [selected, setSelected] = useState(null);
  const [mobileView, setMobileView] = useState("map");
  const [mapReady, setMapReady] = useState(false);
  const [mapErr, setMapErr] = useState("");

  const idOf = (p) => p.provider + ":" + p.plateNorm;

  // ---- poll positions ----
  const reqId = useRef(0);
  const fetchPos = async () => {
    if (document.hidden) return;
    const my = ++reqId.current;
    try {
      const r = await api("GET", ROUTES.positions);
      if (my !== reqId.current) return;
      if (r && r.ok) { setPositions(r.positions || []); setProviders(r.providers || []); setLastTs(r.ts || Date.now()); }
    } catch (e) { /* giữ dữ liệu cũ */ }
    finally { if (my === reqId.current) setLoading(false); }
  };
  useEffect(() => {
    // Poll chạy ngầm: bỏ overlay loading toàn cục ("Đang xử lý…") cho request này.
    if (window.AppLoading && window.AppLoading.addSilentPattern) window.AppLoading.addSilentPattern(/tracking\/positions/i);
    fetchPos();
    const t = setInterval(fetchPos, POLL_MS);
    const onVis = () => { if (!document.hidden) fetchPos(); };
    document.addEventListener("visibilitychange", onVis);
    return () => { clearInterval(t); document.removeEventListener("visibilitychange", onVis); };
  }, []);

  // ---- lọc ----
  const filtered = useMemo(() => {
    const kw = q.trim().toLowerCase();
    return positions.filter((p) => {
      if (matchedOnly && !p.matched) return false;
      if (fProvider !== "all" && p.provider !== fProvider) return false;
      if (fStatus !== "all" && effStatus(p) !== fStatus) return false;
      if (kw && !((p.plate || "").toLowerCase().includes(kw) || (p.driver || "").toLowerCase().includes(kw) || (p.address || "").toLowerCase().includes(kw))) return false;
      return true;
    });
  }, [positions, q, fStatus, fProvider, matchedOnly]);

  const counts = useMemo(() => {
    const c = { all: positions.length, run: 0, idle: 0, off: 0, lost: 0, matched: 0 };
    positions.forEach((p) => { c[effStatus(p)]++; if (p.matched) c.matched++; });
    return c;
  }, [positions]);

  // ---- Google Map ----
  const mapEl = useRef(null);
  const mapRef = useRef(null);
  const infoRef = useRef(null);
  const markersRef = useRef({});
  const markerData = useRef({});
  const didFit = useRef(false);

  const infoOpenRef = useRef(null);   // id của popup đang mở (để chỉ refresh nội dung, không tự mở lại)
  const openInfo = (id) => {
    const p = markerData.current[id]; const m = markersRef.current[id];
    if (!p || !m || !infoRef.current) return;
    infoRef.current.setContent(popupHtml(p));
    infoRef.current.open(mapRef.current, m);
    infoOpenRef.current = id;
  };

  useEffect(() => {
    const key = B.mapsKey;
    if (!key) { setMapErr("Chưa cấu hình Google Maps API key."); return; }
    let alive = true;
    loadGoogleMaps(key).then((maps) => {
      if (!alive || mapRef.current || !mapEl.current) return;
      ensurePlateStyle();
      mapRef.current = new maps.Map(mapEl.current, {
        center: { lat: 16.5, lng: 106.5 }, zoom: 6,
        mapTypeControl: false, streetViewControl: false, fullscreenControl: true, clickableIcons: false,
        gestureHandling: "greedy",
      });
      infoRef.current = new maps.InfoWindow();
      infoRef.current.addListener("closeclick", () => { infoOpenRef.current = null; });
      setMapReady(true);
    }).catch((e) => { if (alive) setMapErr(e.message); });
    return () => { alive = false; };
  }, []);

  // upsert markers theo filtered
  useEffect(() => {
    if (!mapReady || !mapRef.current || !window.google) return;
    const maps = window.google.maps;
    const seen = new Set();
    filtered.forEach((p) => {
      const id = idOf(p); seen.add(id);
      markerData.current[id] = p;
      const pos = { lat: p.lat, lng: p.lng };
      const sel = id === selected;
      // Chữ ký icon: chỉ đổi icon khi trạng thái / hướng (làm tròn 6°) / chọn đổi → tránh nạp lại ảnh = nháy mỗi poll.
      const sig = effStatus(p) + "|" + Math.round((p.angle || 0) / 6) + "|" + (sel ? 1 : 0);
      let m = markersRef.current[id];
      if (!m) {
        m = new maps.Marker({
          position: pos, map: mapRef.current, icon: iconFor(maps, p, sel), title: p.plate,
          label: { text: p.plate || "—", className: "trk-plate", color: "#16202e", fontSize: "11px", fontWeight: "700" },
        });
        m.__sig = sig;
        m.addListener("click", () => { setSelected(id); openInfo(id); });
        markersRef.current[id] = m;
      } else {
        if (m.__sig !== sig) { m.setIcon(iconFor(maps, p, sel)); m.__sig = sig; }
        tweenMarker(m, pos);   // trượt mượt tới vị trí mới (cảm giác xe chạy realtime)
      }
    });
    Object.keys(markersRef.current).forEach((id) => { if (!seen.has(id)) { const m = markersRef.current[id]; if (m.__anim) cancelAnimationFrame(m.__anim); m.setMap(null); delete markersRef.current[id]; } });

    if (!didFit.current && filtered.length) {
      didFit.current = true;
      const b = new maps.LatLngBounds();
      filtered.forEach((p) => b.extend({ lat: p.lat, lng: p.lng }));
      mapRef.current.fitBounds(b);
      if (filtered.length === 1) mapRef.current.setZoom(15);
    }
    // Chỉ REFRESH nội dung popup ĐANG mở (không tự mở lại mỗi poll → đỡ nháy).
    const openId = infoOpenRef.current;
    if (openId && markerData.current[openId] && infoRef.current) {
      infoRef.current.setContent(popupHtml(markerData.current[openId]));
    }
  }, [filtered, selected, mapReady]);

  const focusVehicle = (p) => {
    const id = idOf(p); setSelected(id);
    if (isMobile) setMobileView("map");
    setTimeout(() => {
      if (!mapRef.current) return;
      mapRef.current.panTo({ lat: p.lat, lng: p.lng });
      if (mapRef.current.getZoom() < 15) mapRef.current.setZoom(15);
      openInfo(id);
    }, isMobile ? 250 : 0);
  };

  const enabledProviders = providers.filter((p) => p.enabled);
  const noData = !loading && positions.length === 0;

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column" }}>
      {/* header */}
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: isMobile ? "10px 14px" : "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 12, height: isMobile ? "auto" : 58, flexWrap: "wrap" }}>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center", flexShrink: 0 }}><i className="bi bi-geo-alt-fill" /></div>
          <div>
            <div style={{ fontSize: 15.5, fontWeight: 700, letterSpacing: "-0.01em" }}>Theo dõi xe realtime</div>
            <div style={{ fontSize: 11.5, color: "var(--ink-3)" }}>{enabledProviders.map((p) => `${p.label}: ${p.count}`).join(" · ") || "Chưa bật nguồn GPS nào"}</div>
          </div>
          <div style={{ flex: 1 }} />
          <span style={{ fontSize: 12, color: "var(--ink-3)", display: "inline-flex", alignItems: "center", gap: 6 }}>
            {(loading && !lastTs)
              ? <><span style={{ width: 8, height: 8, borderRadius: 999, background: "var(--ink-4)" }} /> Đang kết nối…</>
              : <><span style={{ width: 8, height: 8, borderRadius: 999, background: "var(--good)", boxShadow: "0 0 0 3px rgba(31,138,91,.18)" }} /> Trực tuyến · {timeAgo(lastTs)}</>}
          </span>
        </div>
      </header>

      {/* status chips */}
      <div style={{ display: "flex", alignItems: "center", gap: 8, background: "#fff", borderBottom: "1px solid var(--line)", padding: isMobile ? "8px 14px" : "8px 22px", flexShrink: 0, flexWrap: "wrap" }}>
        {[["all", "Tất cả", "var(--ink)"], ["run", STATUS.run.label, STATUS.run.color], ["idle", STATUS.idle.label, STATUS.idle.color], ["off", STATUS.off.label, STATUS.off.color], ["lost", STATUS.lost.label, STATUS.lost.color]].map(([k, label, col]) => {
          const on = fStatus === k; const n = k === "all" ? counts.all : counts[k];
          return (
            <button key={k} type="button" onClick={() => setFStatus(k)}
              style={{ display: "inline-flex", alignItems: "center", gap: 6, border: on ? `1.5px solid ${col}` : "1px solid var(--line)", background: on ? "#fff" : "transparent", borderRadius: 999, padding: "5px 11px", cursor: "pointer", fontSize: 12.5, fontWeight: 600, color: "var(--ink-2)" }}>
              {k !== "all" && <span style={{ width: 9, height: 9, borderRadius: 999, background: col }} />}{label}
              <span className="tnum" style={{ color: "var(--ink-4)" }}>{n}</span>
            </button>
          );
        })}
        <span style={{ width: 1, height: 20, background: "var(--line-2)" }} />
        <label style={{ display: "inline-flex", alignItems: "center", gap: 6, fontSize: 12.5, color: "var(--ink-2)", cursor: "pointer" }}>
          <input type="checkbox" checked={matchedOnly} onChange={(e) => setMatchedOnly(e.target.checked)} style={{ accentColor: "var(--accent)" }} /> Chỉ xe hệ thống ({counts.matched})
        </label>
        {enabledProviders.length > 1 && (
          <select value={fProvider} onChange={(e) => setFProvider(e.target.value)}
            style={{ fontSize: 12.5, padding: "5px 9px", border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-2)" }}>
            <option value="all">Mọi nguồn</option>
            {enabledProviders.map((p) => <option key={p.key} value={p.key}>{p.label}</option>)}
          </select>
        )}
        {isMobile && (
          <div style={{ marginLeft: "auto", display: "inline-flex", background: "#f1f2f4", borderRadius: 8, padding: 2 }}>
            {[["map", "Bản đồ"], ["list", "Danh sách"]].map(([k, l]) => (
              <button key={k} type="button" onClick={() => setMobileView(k)} style={{ border: "none", cursor: "pointer", fontSize: 12.5, fontWeight: 600, padding: "5px 12px", borderRadius: 6, background: mobileView === k ? "#fff" : "transparent", color: mobileView === k ? "var(--accent)" : "var(--ink-3)" }}>{l}</button>
            ))}
          </div>
        )}
      </div>

      {/* body */}
      <div style={{ flex: 1, minHeight: 0, display: "flex", flexDirection: isMobile ? "column" : "row" }}>
        {/* list */}
        <div style={{ width: isMobile ? "100%" : 340, flexShrink: 0, borderRight: isMobile ? "none" : "1px solid var(--line)", background: "#fff", overflowY: "auto", display: isMobile && mobileView !== "list" ? "none" : "block", order: isMobile ? 2 : 0 }}>
          <div style={{ padding: "10px 12px", borderBottom: "1px solid var(--line-2)", position: "sticky", top: 0, background: "#fff", zIndex: 2 }}>
            <div style={{ position: "relative" }}>
              <span style={{ position: "absolute", left: 10, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)" }}><I.search /></span>
              <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Tìm biển số, tài xế, địa chỉ…"
                style={{ width: "100%", padding: "8px 10px 8px 32px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 9, outline: "none", background: "#fafbfc", boxSizing: "border-box" }} />
            </div>
          </div>
          {filtered.length === 0 && <div style={{ padding: "30px 16px", textAlign: "center", color: "var(--ink-4)", fontSize: 13 }}>{noData ? "Chưa có dữ liệu xe." : "Không có xe khớp bộ lọc."}</div>}
          {filtered.slice().sort((a, b) => (b.speed || 0) - (a.speed || 0)).map((p) => {
            const id = idOf(p); const st = STATUS[effStatus(p)] || STATUS.off; const on = id === selected;
            return (
              <button key={id} type="button" onClick={() => focusVehicle(p)}
                style={{ width: "100%", textAlign: "left", display: "block", border: "none", borderLeft: `3px solid ${on ? st.color : "transparent"}`, borderBottom: "1px solid var(--line-2)", background: on ? "var(--accent-weak-2)" : "#fff", cursor: "pointer", padding: "9px 13px" }}>
                <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                  <span style={{ width: 9, height: 9, borderRadius: 999, background: st.color, flexShrink: 0 }} />
                  <span className="tnum" style={{ fontWeight: 700, fontSize: 13.5 }}>{p.plate || "—"}</span>
                  {p.matched && <span title="Khớp xe trong hệ thống" style={{ fontSize: 10, fontWeight: 700, color: "var(--good)", background: "var(--good-weak)", padding: "1px 6px", borderRadius: 999 }}>✓</span>}
                  <span style={{ flex: 1 }} />
                  <span className="tnum" style={{ fontSize: 12.5, fontWeight: 600, color: st.color }}>{Math.round(p.speed || 0)} km/h</span>
                </div>
                <div style={{ fontSize: 11.5, color: "var(--ink-3)", marginTop: 3, display: "flex", gap: 6, flexWrap: "wrap" }}>
                  {p.driver && <span>{p.driver}</span>}
                  <span style={{ color: "var(--ink-4)" }}>· {timeAgo(p.ts)}</span>
                </div>
                {p.address && <div style={{ fontSize: 11, color: "var(--ink-4)", marginTop: 2, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{p.address}</div>}
              </button>
            );
          })}
        </div>

        {/* map */}
        <div style={{ flex: 1, minHeight: isMobile ? "52vh" : 0, position: "relative", display: isMobile && mobileView !== "map" ? "none" : "block", order: isMobile ? 1 : 0 }}>
          <div ref={mapEl} style={{ position: "absolute", inset: 0, background: "#e9edf1" }} />
          {(mapErr || (noData && enabledProviders.length === 0)) && (
            <div style={{ position: "absolute", inset: 0, display: "grid", placeItems: "center", zIndex: 5, pointerEvents: "none" }}>
              <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "18px 22px", textAlign: "center", maxWidth: 360, pointerEvents: "auto", boxShadow: "0 8px 24px rgba(16,19,23,.12)" }}>
                <div style={{ fontWeight: 700, marginBottom: 6 }}>{mapErr ? "Bản đồ chưa sẵn sàng" : "Chưa bật nguồn GPS"}</div>
                <div style={{ fontSize: 13, color: "var(--ink-3)", marginBottom: 12 }}>{mapErr || "Vào Cài đặt → Giám sát hành trình để cấu hình tài khoản Viettel vTracking / Bình Anh và bật theo dõi."}</div>
                {canEdit && !mapErr && <a href={ROUTES.settings + "#gps"} style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "9px 15px", fontSize: 13.5, fontWeight: 600, color: "#fff", background: "var(--accent)", borderRadius: 9, textDecoration: "none" }}><i className="bi bi-gear" /> Cấu hình kết nối</a>}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<TrackingApp />);
