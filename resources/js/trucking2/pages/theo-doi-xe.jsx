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

/* Khoảng cách Haversine (km) giữa 2 tọa độ. */
function haversineKm(a, b) {
  const R = 6371, rad = (d) => (d * Math.PI) / 180;
  const dLat = rad(b.lat - a.lat), dLng = rad(b.lng - a.lng);
  const s = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(s));
}
const fmtDist = (km) => (km < 1 ? Math.round(km * 1000) + " m" : km.toFixed(km < 10 ? 1 : 0) + " km");
const AT_WH_KM = 0.3;   // ≤300m coi như "đã ở kho"

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

/* Style bản đồ TỐI GIẢN cho logistics: ẩn POI (quán ăn/khu vui chơi…), transit, icon biển báo
   → chỉ còn ĐƯỜNG + tên đường + cao tốc, để xe & kho nổi bật, dễ nhìn tuyến. */
const MAP_STYLE = [
  { featureType: "poi", stylers: [{ visibility: "off" }] },          // ẩn quán ăn/khu vui chơi…
  { featureType: "poi.business", stylers: [{ visibility: "off" }] },
  { featureType: "transit", stylers: [{ visibility: "off" }] },      // ẩn xe buýt/ga
  { featureType: "administrative.neighborhood", stylers: [{ visibility: "off" }] },
  // GIỮ biển số đường (QL/CT) + tên đường để biết tuyến — KHÔNG ẩn road labels.icon.
  { featureType: "road.highway", elementType: "labels", stylers: [{ visibility: "on" }] },
  { featureType: "road.highway", elementType: "labels.icon", stylers: [{ visibility: "on" }] },
];

/* Marker KHO — ghim teardrop màu chàm + biểu tượng nhà kho. Anchor ở mũi (đáy). */
const WAREHOUSE_PIN = "data:image/svg+xml;charset=UTF-8," + encodeURIComponent(
  `<svg xmlns="http://www.w3.org/2000/svg" width="34" height="42" viewBox="0 0 34 42">
    <path d="M17 41C7 28 2 21 2 14a15 15 0 0 1 30 0c0 7-5 14-15 27z" fill="#4f46e5" stroke="#ffffff" stroke-width="2"/>
    <path d="M8.5 16.5 L17 9 L25.5 16.5 L25.5 24 L8.5 24 Z" fill="#ffffff"/>
    <rect x="14" y="18" width="6" height="6" fill="#4f46e5"/>
  </svg>`);
function whPopup(w) {
  return `<div style="font-size:12.5px;line-height:1.5;min-width:160px;padding:2px">
    <div style="font-weight:700;font-size:14px">🏭 ${esc(w.name)}${w.code ? ` <span style="color:#8a94a6">(${esc(w.code)})</span>` : ""}</div>
    ${w.address ? `<div style="color:#5b6470;margin-top:2px">${esc(w.address)}</div>` : ""}
    ${(w.lat != null && w.lng != null) ? `<div style="color:#8a94a6;margin-top:3px" class="tnum">${(+w.lat).toFixed(6)}, ${(+w.lng).toFixed(6)}</div>` : ""}
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
  // Tab mobile (Bản đồ/Danh sách) — LƯU sessionStorage để không bị "nhảy" về Bản đồ
  // mỗi khi component dựng lại / poll re-render (giữ đúng tab người dùng đang xem).
  const [mobileView, _setMobileView] = useState(() => { try { return sessionStorage.getItem("trk_track_view") || "map"; } catch (e) { return "map"; } });
  const setMobileView = (v) => { try { sessionStorage.setItem("trk_track_view", v); } catch (e) {} _setMobileView(v); };
  const [mapReady, setMapReady] = useState(false);
  const [mapErr, setMapErr] = useState("");
  // ---- Kho (ghim vị trí trên bản đồ) ----
  const [warehouses, setWarehouses] = useState([]);
  const [whPanel, setWhPanel] = useState(false);     // mở panel quản lý vị trí kho (admin)
  const [placingId, setPlacingId] = useState(null);  // kho đang chờ bấm/đặt điểm
  const [trafficOn, setTrafficOn] = useState(true);   // lớp giao thông — MẶC ĐỊNH BẬT (chọn tuyến tiện)
  const [satellite, setSatellite] = useState(false);  // ảnh vệ tinh
  const trafficRef = useRef(null);

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

  // ---- Kho: refs + tải danh sách + ghim ----
  const whMarkersRef = useRef({});
  const whInfoRef = useRef(null);
  const placingRef = useRef(null);   // = placingId (cho listener đọc giá trị mới nhất)
  useEffect(() => { placingRef.current = placingId; }, [placingId]);
  const whListRef = useRef([]);
  useEffect(() => { whListRef.current = warehouses; }, [warehouses]);

  const loadWarehouses = () => {
    if (!ROUTES.warehouses) return;
    api("GET", ROUTES.warehouses).then((r) => { if (r && r.ok) setWarehouses(r.warehouses || []); }).catch(() => {});
  };
  useEffect(() => { loadWarehouses(); }, []);

  // Ghim/cập nhật tọa độ 1 kho (luôn dùng bản mới nhất qua ref → tránh stale trong map listener).
  const placeFnRef = useRef(null);
  placeFnRef.current = (id, lat, lng) => {
    setWarehouses((ws) => ws.map((w) => (w.id === id ? { ...w, lat, lng } : w)));
    setPlacingId(null);
    const w = whListRef.current.find((x) => x.id === id);
    if (!ROUTES.warehouseGeo) return;
    api("POST", ROUTES.warehouseGeo, { id, lat, lng })
      .then((r) => window.trkToast && window.trkToast(r && r.ok ? `Đã ghim kho ${w ? w.name : ""}` : "Lưu vị trí kho thất bại", r && r.ok ? undefined : "error"))
      .catch(() => window.trkToast && window.trkToast("Lỗi lưu vị trí kho", "error"));
  };
  const whPinned = warehouses.filter((w) => w.lat != null && w.lng != null).length;
  // Kho đã có tọa độ → tính kho GẦN NHẤT + khoảng cách cho mỗi xe.
  const whGeo = useMemo(() => warehouses.filter((w) => w.lat != null && w.lng != null).map((w) => ({ ...w, lat: +w.lat, lng: +w.lng })), [warehouses]);
  const nearestWh = (p) => {
    if (!whGeo.length || p.lat == null || p.lng == null) return null;
    let best = null;
    for (const w of whGeo) { const km = haversineKm({ lat: p.lat, lng: p.lng }, { lat: w.lat, lng: w.lng }); if (!best || km < best.km) best = { w, km }; }
    return best;
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
        gestureHandling: "greedy", styles: MAP_STYLE,   // ẩn POI/transit → bản đồ logistics gọn
      });
      infoRef.current = new maps.InfoWindow();
      infoRef.current.addListener("closeclick", () => { infoOpenRef.current = null; });
      whInfoRef.current = new maps.InfoWindow();
      // Bấm lên bản đồ khi đang "đặt kho" → ghim kho tại điểm đó.
      mapRef.current.addListener("click", (e) => { if (placingRef.current != null) placeFnRef.current(placingRef.current, e.latLng.lat(), e.latLng.lng()); });
      setMapReady(true);
    }).catch((e) => { if (alive) setMapErr(e.message); });
    return () => { alive = false; };
  }, []);

  // Mobile: chuyển sang tab Bản đồ → container vừa hiện lại, ép Google Map vẽ lại + giữ tâm (tránh map xám).
  useEffect(() => {
    if (!mapReady || !mapRef.current || !window.google) return;
    if (isMobile && mobileView !== "map") return;
    const map = mapRef.current; const c = map.getCenter();
    setTimeout(() => { window.google.maps.event.trigger(map, "resize"); if (c) map.setCenter(c); }, 180);
  }, [mobileView, isMobile, mapReady]);

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
        m.addListener("click", () => {
          if (placingRef.current != null) {   // đang đặt kho → dùng vị trí xe đang đỗ làm vị trí kho
            const pp = markerData.current[id];
            if (pp) placeFnRef.current(placingRef.current, pp.lat, pp.lng);
            return;
          }
          setSelected(id); openInfo(id);
        });
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

  // ---- marker KHO (vẽ + kéo để chỉnh; bấm xem thông tin) ----
  useEffect(() => {
    if (!mapReady || !mapRef.current || !window.google) return;
    const maps = window.google.maps;
    const seen = new Set();
    warehouses.forEach((w) => {
      if (w.lat == null || w.lng == null) return;
      seen.add(w.id);
      const pos = { lat: +w.lat, lng: +w.lng };
      let m = whMarkersRef.current[w.id];
      if (!m) {
        m = new maps.Marker({ position: pos, map: mapRef.current, title: "Kho: " + w.name, zIndex: 9999,
          icon: { url: WAREHOUSE_PIN, scaledSize: new maps.Size(34, 42), anchor: new maps.Point(17, 41) } });
        m.addListener("click", () => { const cur = whListRef.current.find((x) => x.id === w.id) || w; whInfoRef.current.setContent(whPopup(cur)); whInfoRef.current.open(mapRef.current, m); });
        m.addListener("dragend", (e) => placeFnRef.current(w.id, e.latLng.lat(), e.latLng.lng()));
        whMarkersRef.current[w.id] = m;
      } else { m.setPosition(pos); }
      m.setDraggable(!!canEdit && whPanel);   // chỉ kéo được khi admin mở panel
    });
    Object.keys(whMarkersRef.current).forEach((id) => { if (!seen.has(Number(id))) { whMarkersRef.current[id].setMap(null); delete whMarkersRef.current[id]; } });
  }, [warehouses, mapReady, whPanel]);

  // Con trỏ chữ thập khi đang đặt kho
  useEffect(() => {
    if (mapRef.current) mapRef.current.setOptions({ draggableCursor: placingId != null ? "crosshair" : null });
  }, [placingId]);

  // Lớp giao thông (chọn tuyến tiện) — bật/tắt
  useEffect(() => {
    if (!mapReady || !mapRef.current || !window.google) return;
    if (trafficOn && !trafficRef.current) trafficRef.current = new window.google.maps.TrafficLayer();
    if (trafficRef.current) trafficRef.current.setMap(trafficOn ? mapRef.current : null);
  }, [trafficOn, mapReady]);

  // Ảnh vệ tinh ↔ bản đồ đường
  useEffect(() => {
    if (mapRef.current) mapRef.current.setMapTypeId(satellite ? "hybrid" : "roadmap");
  }, [satellite, mapReady]);

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
          <a href={ROUTES.visitsPage} title="Lịch sử xe đến/rời kho"
            style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "8px 12px", fontSize: 13, fontWeight: 600, cursor: "pointer", color: "var(--ink-2)", background: "#fff", border: "1px solid var(--line)", borderRadius: 9, textDecoration: "none" }}>
            <i className="bi bi-clock-history" /> Lịch sử đến kho
          </a>
          {canEdit && ROUTES.warehouseGeo && <button type="button" onClick={() => { setWhPanel((v) => !v); if (whPanel) setPlacingId(null); }} title="Ghim vị trí kho trên bản đồ"
            style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "8px 12px", fontSize: 13, fontWeight: 600, cursor: "pointer", color: whPanel ? "#fff" : "var(--ink-2)", background: whPanel ? "#4f46e5" : "#fff", border: `1px solid ${whPanel ? "#4f46e5" : "var(--line)"}`, borderRadius: 9 }}>
            <i className="bi bi-geo-alt-fill" /> Vị trí kho
          </button>}
        </div>
      </header>

      {/* Mobile: chuyển Bản đồ / Danh sách (dải nút rõ ràng, full-width) */}
      {isMobile && (
        <div style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: "8px 14px", flexShrink: 0 }}>
          <div style={{ display: "flex", width: "100%", background: "#f1f2f4", borderRadius: 9, padding: 3 }}>
            {[["map", "Bản đồ", "bi-map"], ["list", `Danh sách (${counts.all})`, "bi-list-ul"]].map(([k, l, ic]) => (
              <button key={k} type="button" onClick={() => setMobileView(k)}
                style={{ flex: 1, display: "inline-flex", alignItems: "center", justifyContent: "center", gap: 6, border: "none", cursor: "pointer", fontSize: 13.5, fontWeight: 700, padding: "9px 0", borderRadius: 7, background: mobileView === k ? "#fff" : "transparent", color: mobileView === k ? "var(--accent)" : "var(--ink-3)", boxShadow: mobileView === k ? "0 1px 2px rgba(16,19,23,.14)" : "none" }}>
                <i className={"bi " + ic} /> {l}
              </button>
            ))}
          </div>
        </div>
      )}

      {/* status chips — mobile: cuộn ngang 1 hàng (không dồn nhiều dòng) */}
      <div style={{ display: "flex", alignItems: "center", gap: 8, background: "#fff", borderBottom: "1px solid var(--line)", padding: isMobile ? "8px 14px" : "8px 22px", flexShrink: 0, flexWrap: isMobile ? "nowrap" : "wrap", overflowX: isMobile ? "auto" : "visible", WebkitOverflowScrolling: "touch" }}>
        {[["all", "Tất cả", "var(--ink)"], ["run", STATUS.run.label, STATUS.run.color], ["idle", STATUS.idle.label, STATUS.idle.color], ["off", STATUS.off.label, STATUS.off.color], ["lost", STATUS.lost.label, STATUS.lost.color]].map(([k, label, col]) => {
          const on = fStatus === k; const n = k === "all" ? counts.all : counts[k];
          return (
            <button key={k} type="button" onClick={() => setFStatus(k)}
              style={{ flexShrink: 0, display: "inline-flex", alignItems: "center", gap: 6, border: on ? `1.5px solid ${col}` : "1px solid var(--line)", background: on ? "#fff" : "transparent", borderRadius: 999, padding: "5px 11px", cursor: "pointer", fontSize: 12.5, fontWeight: 600, color: "var(--ink-2)", whiteSpace: "nowrap" }}>
              {k !== "all" && <span style={{ width: 9, height: 9, borderRadius: 999, background: col }} />}{label}
              <span className="tnum" style={{ color: "var(--ink-4)" }}>{n}</span>
            </button>
          );
        })}
        <span style={{ flexShrink: 0, width: 1, height: 20, background: "var(--line-2)" }} />
        <button type="button" onClick={() => setMatchedOnly((v) => !v)}
          style={{ flexShrink: 0, display: "inline-flex", alignItems: "center", gap: 6, border: matchedOnly ? "1.5px solid var(--accent)" : "1px solid var(--line)", background: matchedOnly ? "var(--accent-weak)" : "transparent", color: matchedOnly ? "var(--accent)" : "var(--ink-2)", borderRadius: 999, padding: "5px 11px", cursor: "pointer", fontSize: 12.5, fontWeight: 600, whiteSpace: "nowrap" }}>
          <i className="bi bi-check2-circle" /> Xe hệ thống <span className="tnum" style={{ color: "var(--ink-4)" }}>{counts.matched}</span>
        </button>
        {enabledProviders.length > 1 && (
          <select value={fProvider} onChange={(e) => setFProvider(e.target.value)}
            style={{ flexShrink: 0, fontSize: 12.5, padding: "5px 9px", border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-2)" }}>
            <option value="all">Mọi nguồn</option>
            {enabledProviders.map((p) => <option key={p.key} value={p.key}>{p.label}</option>)}
          </select>
        )}
      </div>

      {/* body */}
      <div style={{ flex: 1, minHeight: 0, display: "flex", flexDirection: isMobile ? "column" : "row" }}>
        {/* list */}
        <div style={{ width: isMobile ? "100%" : 340, flex: isMobile ? "1 1 auto" : "0 0 auto", minHeight: 0, borderRight: isMobile ? "none" : "1px solid var(--line)", background: "#fff", overflowY: "auto", display: isMobile && mobileView !== "list" ? "none" : "block", order: isMobile ? 2 : 0 }}>
          <div style={{ padding: "10px 12px", borderBottom: "1px solid var(--line-2)", position: "sticky", top: 0, background: "#fff", zIndex: 2 }}>
            <div style={{ position: "relative" }}>
              <span style={{ position: "absolute", left: 10, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)" }}><I.search /></span>
              <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Tìm biển số, tài xế, địa chỉ…"
                style={{ width: "100%", padding: "8px 10px 8px 32px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 9, outline: "none", background: "#fafbfc", boxSizing: "border-box" }} />
            </div>
          </div>
          {filtered.length === 0 && <div style={{ padding: "30px 16px", textAlign: "center", color: "var(--ink-4)", fontSize: 13 }}>{noData ? "Chưa có dữ liệu xe." : "Không có xe khớp bộ lọc."}</div>}
          {filtered.slice().sort((a, b) => {
            // Ưu tiên xe ĐANG CHẠY, trong đó xe GẦN KHO NHẤT lên đầu (dễ thấy xe sắp tới kho).
            const ra = effStatus(a) === "run", rb = effStatus(b) === "run";
            if (ra !== rb) return ra ? -1 : 1;
            if (ra && rb) { const da = nearestWh(a)?.km ?? Infinity, db = nearestWh(b)?.km ?? Infinity; if (da !== db) return da - db; }
            return (b.speed || 0) - (a.speed || 0);
          }).map((p) => {
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
                {(() => { const n = nearestWh(p); if (!n) return null; const at = n.km <= AT_WH_KM;
                  return (
                    <div style={{ fontSize: 11, marginTop: 3, display: "flex", alignItems: "center", gap: 5, fontWeight: 600, color: at ? "var(--good)" : "#4f46e5" }}>
                      <i className={"bi " + (at ? "bi-house-check-fill" : "bi-geo-alt-fill")} />
                      {at ? `Đã ở kho ${n.w.name}` : <>Cách kho <b>{n.w.name}</b>: <span className="tnum">{fmtDist(n.km)}</span></>}
                    </div>
                  ); })()}
              </button>
            );
          })}
        </div>

        {/* map */}
        <div style={{ flex: 1, minHeight: 0, position: "relative", display: isMobile && mobileView !== "map" ? "none" : "block", order: isMobile ? 1 : 0 }}>
          <div ref={mapEl} style={{ position: "absolute", inset: 0, background: "#e9edf1" }} />

          {/* Lớp bản đồ: Giao thông + Vệ tinh (góc dưới-trái) */}
          {mapReady && (
            <div style={{ position: "absolute", left: 10, bottom: 22, zIndex: 6, display: "flex", gap: 6 }}>
              {[["traffic", "Giao thông", "bi-traffic-light", trafficOn, () => setTrafficOn((v) => !v)],
                ["sat", "Vệ tinh", "bi-globe-asia-australia", satellite, () => setSatellite((v) => !v)]].map(([k, label, ic, on, toggle]) => (
                <button key={k} type="button" onClick={toggle} title={label}
                  style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 11px", fontSize: 12.5, fontWeight: 600, cursor: "pointer", borderRadius: 8,
                    border: on ? "1px solid var(--accent)" : "1px solid var(--line)", background: on ? "var(--accent)" : "#fff", color: on ? "#fff" : "var(--ink-2)", boxShadow: "0 1px 4px rgba(0,0,0,.2)" }}>
                  <i className={"bi " + ic} /> {label}
                </button>
              ))}
            </div>
          )}

          {/* Banner đang đặt kho */}
          {placingId != null && (() => { const w = warehouses.find((x) => x.id === placingId); return (
            <div style={{ position: "absolute", top: 12, left: "50%", transform: "translateX(-50%)", zIndex: 6, display: "flex", alignItems: "center", gap: 10, background: "#4f46e5", color: "#fff", borderRadius: 10, padding: "8px 14px", boxShadow: "0 6px 18px rgba(0,0,0,.25)", fontSize: 13, maxWidth: "92%" }}>
              <i className="bi bi-geo-alt-fill" />
              <span>Bấm lên bản đồ <b>(hoặc vào xe đang đỗ ở kho)</b> để ghim kho <b>{w ? w.name : ""}</b></span>
              <button type="button" onClick={() => setPlacingId(null)} style={{ border: "none", background: "rgba(255,255,255,.2)", color: "#fff", borderRadius: 7, padding: "4px 9px", cursor: "pointer", fontWeight: 600, fontSize: 12.5 }}>Hủy</button>
            </div>
          ); })()}

          {/* Panel quản lý vị trí kho (admin) */}
          {whPanel && (
            <div style={{ position: "absolute", top: 12, left: 12, zIndex: 6, width: 280, maxHeight: "calc(100% - 24px)", display: "flex", flexDirection: "column", background: "#fff", border: "1px solid var(--line)", borderRadius: 12, boxShadow: "0 8px 24px rgba(16,19,23,.16)" }}>
              <div style={{ display: "flex", alignItems: "center", gap: 8, padding: "11px 13px", borderBottom: "1px solid var(--line-2)" }}>
                <i className="bi bi-geo-alt-fill" style={{ color: "#4f46e5" }} />
                <div style={{ fontWeight: 700, fontSize: 13.5, flex: 1 }}>Vị trí kho <span style={{ color: "var(--ink-4)", fontWeight: 500 }}>({whPinned}/{warehouses.length})</span></div>
                <button type="button" onClick={() => { setWhPanel(false); setPlacingId(null); }} style={{ border: "none", background: "transparent", cursor: "pointer", color: "var(--ink-4)" }}><I.x /></button>
              </div>
              <div style={{ padding: "8px 10px", fontSize: 11.5, color: "var(--ink-3)", borderBottom: "1px solid var(--line-2)", lineHeight: 1.5 }}>
                Bấm <b>Đặt</b> rồi <b>bấm lên bản đồ</b> tại vị trí kho — hoặc bấm vào <b>xe đang đỗ</b> ở kho để lấy đúng tọa độ. Đã ghim thì <b>kéo</b> ghim 🏭 để chỉnh.
              </div>
              <div style={{ overflowY: "auto", padding: 6 }}>
                {warehouses.length === 0 && <div style={{ padding: 16, textAlign: "center", color: "var(--ink-4)", fontSize: 12.5 }}>Chưa có kho. Thêm ở Cài đặt → Kho.</div>}
                {warehouses.map((w) => {
                  const pinned = w.lat != null && w.lng != null;
                  const active = placingId === w.id;
                  return (
                    <div key={w.id} style={{ display: "flex", alignItems: "center", gap: 8, padding: "7px 8px", borderRadius: 8, background: active ? "#eef" : "transparent" }}>
                      <span style={{ width: 8, height: 8, borderRadius: 999, background: pinned ? "var(--good)" : "var(--ink-4)", flexShrink: 0 }} />
                      <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={{ fontSize: 13, fontWeight: 600, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{w.name}{w.code ? <span style={{ color: "var(--ink-4)", fontWeight: 400 }}> · {w.code}</span> : null}</div>
                        <div style={{ fontSize: 11, color: pinned ? "var(--good)" : "var(--ink-4)" }}>{pinned ? "Đã ghim" : "Chưa ghim"}</div>
                      </div>
                      <button type="button" onClick={() => { setPlacingId(active ? null : w.id); if (pinned && !active && mapRef.current) { mapRef.current.panTo({ lat: +w.lat, lng: +w.lng }); mapRef.current.setZoom(15); } }}
                        style={{ flexShrink: 0, border: `1px solid ${active ? "#4f46e5" : "var(--line)"}`, background: active ? "#4f46e5" : "#fff", color: active ? "#fff" : "var(--ink-2)", borderRadius: 8, padding: "5px 10px", cursor: "pointer", fontSize: 12, fontWeight: 600 }}>
                        {active ? "Đang đặt…" : (pinned ? "Đặt lại" : "Đặt")}
                      </button>
                    </div>
                  );
                })}
              </div>
            </div>
          )}
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
