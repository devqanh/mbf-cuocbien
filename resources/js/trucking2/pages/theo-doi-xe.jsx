import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useEffect, useRef, useMemo } = React;
import { I, useIsMobile } from "@trk/lib.jsx";
import { MarkerClusterer } from "@googlemaps/markerclusterer";

/* Renderer cụm marker — vòng tròn màu accent + số lượng xe (to dần theo số xe). */
const CLUSTER_RENDERER = {
  render: ({ count, position }) => {
    const g = window.google.maps;
    const size = count < 10 ? 40 : count < 50 ? 48 : 56;
    const svg = "data:image/svg+xml;charset=UTF-8," + encodeURIComponent(
      `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}"><circle cx="${size / 2}" cy="${size / 2}" r="${size / 2 - 3}" fill="#2a6fdb" fill-opacity="0.92" stroke="#fff" stroke-width="3"/></svg>`
    );
    return new g.Marker({
      position,
      icon: { url: svg, scaledSize: new g.Size(size, size), anchor: new g.Point(size / 2, size / 2) },
      label: { text: String(count), color: "#fff", fontSize: "13px", fontWeight: "700" },
      zIndex: 1000 + count,
      title: count + " xe — bấm để phóng to",
    });
  },
};

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
// Thứ tự ưu tiên xếp danh sách: nổ máy → dừng → tắt máy → mất tín hiệu (mất tín hiệu luôn xuống cuối).
const STATUS_RANK = { run: 0, idle: 1, off: 2, lost: 3 };

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
const ETA_MIN_KMH = 5;          // dưới tốc độ TB này coi như đang dừng → không ước tính ETA
const SPEED_WINDOW_MS = 5 * 60 * 1000;   // cửa sổ trượt tính vận tốc trung bình: 5 phút
const vehKey = (p) => p.provider + ":" + (p.deviceId || p.plateNorm || "");

/**
 * Vận tốc TRUNG BÌNH theo THỜI GIAN trong cửa sổ 5' (mỗi đơn vị thời gian như nhau):
 * tích phân tốc độ theo thời gian ÷ tổng thời gian → đèn đỏ/đứng ở phút nào cũng kéo TB xuống đủ.
 * hist = [{ts, speed}] tăng dần theo ts. Trả null nếu chưa đủ dữ liệu.
 */
function avgSpeedKmh(hist, now) {
  if (!hist || !hist.length) return null;
  const cutoff = now - SPEED_WINDOW_MS;
  let sum = 0, dur = 0;
  for (let i = 0; i < hist.length; i++) {
    const segStart = Math.max(hist[i].ts, cutoff);
    const segEnd = i + 1 < hist.length ? hist[i + 1].ts : now;
    const dt = segEnd - segStart;
    if (dt > 0) { sum += (hist[i].speed || 0) * dt; dur += dt; }
  }
  if (dur <= 0) return hist[hist.length - 1].speed || 0;
  return sum / dur;
}

// ETA tới kho = khoảng cách (km hiển thị) ÷ vận tốc (km/h) × 60. Null nếu đang dừng.
function fmtEta(km, speedKmh) {
  const v = speedKmh || 0;
  if (v < ETA_MIN_KMH) return null;
  const mins = Math.round((km / v) * 60);
  if (mins <= 1) return "sắp đến";
  if (mins < 60) return "~" + mins + " phút nữa";
  const h = Math.floor(mins / 60), m = mins % 60;
  return "~" + h + "h" + (m ? " " + m + "'" : "") + " nữa";
}

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

/* Ẩn POI dịch vụ (KS/nhà nghỉ/cafe/quán ăn — đều là poi.business; Google không tách lẻ được)
   + khu vui chơi + transit → bản đồ gọn. Kho đích (Canon…) KHÔNG dựa nhãn Google nữa mà dùng
   chính marker 🏭 có gắn TÊN của mình (xem warehouse marker). Giữ tên tỉnh/huyện/xã + đường. */
const MAP_STYLE = [
  { featureType: "poi.business", stylers: [{ visibility: "off" }] },
  { featureType: "poi.attraction", stylers: [{ visibility: "off" }] },
  { featureType: "transit", stylers: [{ visibility: "off" }] },
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
  s.textContent = ".trk-plate{margin-top:30px;background:#fff;border:1px solid rgba(0,0,0,.2);border-radius:6px;padding:1px 6px;box-shadow:0 1px 3px rgba(0,0,0,.3);white-space:nowrap}"
    + ".trk-wh-label{margin-top:8px;background:#eef2ff;border:1px solid #c7d2fe;color:#3730a3;border-radius:6px;padding:1px 7px;box-shadow:0 1px 3px rgba(0,0,0,.25);white-space:nowrap}";
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
    s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&callback=${cb}&loading=async&libraries=places&language=vi&region=VN`;
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
  const [stale, setStale] = useState(false);   // poll lỗi liên tiếp → dữ liệu không còn "live"
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
  const [booted, setBooted] = useState(false);   // đã dựng map + có dữ liệu + zoom ra xong → tắt overlay loading
  // ---- Kho (ghim vị trí trên bản đồ) ----
  const [warehouses, setWarehouses] = useState([]);
  const [whPanel, setWhPanel] = useState(false);     // mở panel quản lý vị trí kho (admin)
  const [placingId, setPlacingId] = useState(null);  // kho đang chờ bấm/đặt điểm
  const [hintHidden, setHintHidden] = useState(false); // ẩn dòng hướng dẫn ghim kho
  // Lớp bản đồ — NHỚ lựa chọn qua localStorage (lần sau vào giữ nguyên).
  const lsBool = (k, d) => { try { const v = localStorage.getItem(k); return v == null ? d : v === "1"; } catch (e) { return d; } };
  const [trafficOn, setTrafficOn] = useState(() => lsBool("trk_traffic", true));   // lớp giao thông — mặc định BẬT
  const [satellite, setSatellite] = useState(() => lsBool("trk_sat", false));      // ảnh vệ tinh
  const [showPoi, setShowPoi] = useState(() => lsBool("trk_poi", false));          // hiện địa điểm (POI)
  const [follow, setFollow] = useState(false);   // bám xe đang chọn (map tự đi theo khi xe chạy)
  const trafficRef = useRef(null);
  useEffect(() => { try { localStorage.setItem("trk_traffic", trafficOn ? "1" : "0"); localStorage.setItem("trk_sat", satellite ? "1" : "0"); localStorage.setItem("trk_poi", showPoi ? "1" : "0"); } catch (e) {} }, [trafficOn, satellite, showPoi]);

  const idOf = (p) => p.provider + ":" + p.plateNorm;

  // ---- poll positions ----
  // Đệ quy setTimeout (KHÔNG setInterval): không chồng request khi mạng chậm — lịch poll
  // kế tiếp chỉ đặt SAU khi request hiện tại xong. Tab ẩn → ngừng hẳn, hiện lại → poll ngay.
  // Bỏ qua setState khi dữ liệu không đổi (chữ ký) → tránh vẽ lại marker/re-render vô ích.
  const reqId = useRef(0);
  const verRef = useRef(null);     // version cuối → gửi ?v= để backend trả "unchanged" (đỡ băng thông + re-render)
  const runRef = useRef(true);     // có xe đang chạy? → nhịp poll nhanh/chậm
  const histRef = useRef({});      // vehKey → [{ts,speed}] lịch sử tốc độ (cửa sổ trượt 5' tính ETA)
  useEffect(() => {
    if (window.AppLoading && window.AppLoading.addSilentPattern) window.AppLoading.addSilentPattern(/tracking\/positions/i);
    let timer = null, stopped = false, fails = 0, gotData = false, emptyTries = 0;
    // Poll LIÊN TỤC kể cả tab ẩn. Nhịp thích ứng: có xe chạy → 15s; không xe nào chạy → 30s (đỡ tải).
    // Lỗi liên tiếp → backoff ×2 (cap 60s).
    const nextDelay = () => Math.min((runRef.current ? POLL_MS : POLL_MS * 2) * 2 ** fails, 60000);
    const schedule = () => { if (!stopped) { clearTimeout(timer); timer = setTimeout(tick, nextDelay()); } };
    async function tick() {
      if (stopped) return;
      const my = ++reqId.current;
      let ok = false;
      try {
        const r = await api("GET", ROUTES.positions + (verRef.current ? "?v=" + encodeURIComponent(verRef.current) : ""));
        if (my !== reqId.current || stopped) return;   // response cũ / đã hủy → để tick mới lo
        if (r && r.ok) {
          ok = true;
          if (r.version) verRef.current = r.version;
          if (r.ts) setLastTs(r.ts);
          if (!r.unchanged) {   // backend báo có đổi → mới cập nhật + vẽ lại
            setProviders(r.providers || []);
            const next = r.positions || [];
            runRef.current = next.some((p) => p.status === "run");
            // Lưu lịch sử tốc độ (theo ts GPS, bỏ trùng) + cắt cửa sổ 5' → tính vận tốc TB trượt cho ETA.
            const nowMs = r.ts || Date.now();
            const H = histRef.current;
            next.forEach((p) => {
              const k = vehKey(p); const ts = p.ts || nowMs;
              const arr = H[k] || (H[k] = []);
              if (!arr.length || ts > arr[arr.length - 1].ts) arr.push({ ts, speed: p.speed || 0 });
              const cutoff = nowMs - SPEED_WINDOW_MS;   // giữ 1 mẫu trước cutoff làm "giá trị đầu cửa sổ"
              while (arr.length > 1 && arr[1].ts <= cutoff) arr.shift();
            });
            setPositions(next);
            if (next.length) gotData = true;
          } else if (verRef.current) {
            gotData = true;   // unchanged = đã có dữ liệu từ lượt trước
          }
        }
      } catch (e) { /* giữ dữ liệu cũ */ }
      finally {
        // CHỈ tick mới nhất mới đặt lịch kế tiếp → tránh nhân đôi timer
        if (my === reqId.current && !stopped) {
          setLoading(false); fails = ok ? 0 : Math.min(fails + 1, 2); setStale(fails >= 2);
          // Chưa có xe nào (lần đầu rỗng / provider vừa relogin) → thử lại NHANH 3s (tối đa ~5 lần)
          // để trang TỰ ĐẦY mà không cần reload tay; sau đó về nhịp bình thường.
          const quick = ok && ! gotData && emptyTries < 5;
          if (quick) emptyTries++;
          clearTimeout(timer); timer = setTimeout(tick, quick ? 3000 : nextDelay());
        }
      }
    }
    // Quay lại tab → refresh NGAY (không chờ hết chu kỳ); poll nền vẫn chạy nên data đã sẵn sàng.
    const onVis = () => { if (!document.hidden && !stopped) { clearTimeout(timer); tick(); } };
    tick();
    document.addEventListener("visibilitychange", onVis);
    return () => { stopped = true; clearTimeout(timer); document.removeEventListener("visibilitychange", onVis); };
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
  const filteredRef = useRef(filtered); filteredRef.current = filtered;   // handler đọc danh sách lọc mới nhất

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
  const clustererRef = useRef(null);   // gom cụm marker xe (MarkerClusterer)
  const didFit = useRef(false);
  // Đồng hồ "x giây trước" tự đếm giữa 2 lần poll (re-render mỗi giây; effect/memo nặng KHÔNG chạy lại vì deps không đổi).
  const [, setNowTick] = useState(0);
  useEffect(() => { const t = setInterval(() => setNowTick((n) => n + 1), 1000); return () => clearInterval(t); }, []);

  // ---- Tìm địa điểm (Google Places autocomplete) ----
  const acSvcRef = useRef(null);      // AutocompleteService (lấy gợi ý)
  const placesSvcRef = useRef(null);  // PlacesService (lấy chi tiết tọa độ)
  const acTokenRef = useRef(null);    // session token (gộp gợi ý + chi tiết → 1 phiên, rẻ hơn)
  const acTimerRef = useRef(null);    // debounce gõ phím
  const searchMkRef = useRef(null);   // marker điểm tìm thấy
  const [placeQ, setPlaceQ] = useState("");
  const [placePreds, setPlacePreds] = useState([]);
  const [placeOpen, setPlaceOpen] = useState(false);
  const [placeActive, setPlaceActive] = useState(-1);  // index đang chọn bằng phím

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
  // GỠ ghim 1 kho (xóa tọa độ) — hỏi xác nhận.
  const removePin = async (id) => {
    const w = warehouses.find((x) => x.id === id);
    const ok = await window.confirmAction({ title: "Gỡ ghim kho?", text: `Xóa tọa độ đã ghim của kho <b>${(w && w.name) || ""}</b>? Kho sẽ không còn vị trí trên bản đồ.`, confirmText: "Gỡ ghim", cancelText: "Huỷ" });
    if (!ok) return;
    setWarehouses((ws) => ws.map((x) => (x.id === id ? { ...x, lat: null, lng: null } : x)));
    if (placingId === id) setPlacingId(null);
    if (!ROUTES.warehouseGeo) return;
    api("POST", ROUTES.warehouseGeo, { id, lat: null, lng: null })
      .then((r) => window.trkToast && window.trkToast(r && r.ok ? `Đã gỡ ghim kho ${w ? w.name : ""}` : "Gỡ vị trí thất bại", r && r.ok ? undefined : "error"))
      .catch(() => window.trkToast && window.trkToast("Lỗi kết nối khi gỡ", "error"));
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
  // Danh sách đã sắp xếp (memo) — tính kho gần nhất + ETA 1 LẦN/xe rồi mới sort, tránh gọi nearestWh
  // lặp lại trong comparator mỗi lần render (O(N·log N·W) → O(N·W)); refresh mỗi poll khi positions đổi.
  const sortedRows = useMemo(() => {
    const now = Date.now();
    return filtered.map((p) => {
      const near = nearestWh(p);
      const at = !!(near && near.km <= AT_WH_KM);
      const avgV = avgSpeedKmh(histRef.current[vehKey(p)], now);
      const effV = avgV != null ? avgV : (p.speed || 0);
      const eta = (near && !at && effV >= ETA_MIN_KMH) ? fmtEta(near.km, effV) : null;
      return { p, near, at, eta };
    }).sort((A, B) => {
      const sa = STATUS_RANK[effStatus(A.p)] ?? 9, sb = STATUS_RANK[effStatus(B.p)] ?? 9;
      if (sa !== sb) return sa - sb;
      const da = A.near?.km ?? Infinity, db = B.near?.km ?? Infinity;
      if (da !== db) return da - db;
      return (B.p.speed || 0) - (A.p.speed || 0);
    });
  }, [filtered, whGeo]);

  useEffect(() => {
    const key = B.mapsKey;
    if (!key) { setMapErr("Chưa cấu hình Google Maps API key."); return; }
    let alive = true;
    loadGoogleMaps(key).then((maps) => {
      if (!alive || mapRef.current || !mapEl.current) return;
      ensurePlateStyle();
      mapRef.current = new maps.Map(mapEl.current, {
        center: { lat: 16.5, lng: 106.5 }, zoom: 6,
        mapTypeControl: false, streetViewControl: false, fullscreenControl: true, clickableIcons: false, zoomControl: false,
        gestureHandling: "greedy", styles: MAP_STYLE,   // ẩn POI/transit → bản đồ logistics gọn
      });
      infoRef.current = new maps.InfoWindow();
      infoRef.current.addListener("closeclick", () => { infoOpenRef.current = null; });
      whInfoRef.current = new maps.InfoWindow();
      clustererRef.current = new MarkerClusterer({ map: mapRef.current, markers: [], renderer: CLUSTER_RENDERER });
      // Bấm lên bản đồ: đang "đặt kho" → ghim kho; ngược lại → bỏ chọn xe + đóng popup (thoát nhanh khỏi lỡ chọn).
      mapRef.current.addListener("click", (e) => {
        if (placingRef.current != null) { placeFnRef.current(placingRef.current, e.latLng.lat(), e.latLng.lng()); return; }
        setSelected(null); setFollow(false);
        if (infoRef.current) infoRef.current.close();
        infoOpenRef.current = null;
      });
      // Kéo bản đồ bằng tay → tự tắt "bám xe" (trả quyền điều khiển cho người dùng).
      mapRef.current.addListener("dragstart", () => setFollow(false));
      // Tìm địa điểm: AutocompleteService (gợi ý) + PlacesService (chi tiết).
      try {
        if (maps.places) {
          acSvcRef.current = new maps.places.AutocompleteService();
          placesSvcRef.current = new maps.places.PlacesService(mapRef.current);
        }
      } catch (e) {}
      setMapReady(true);
    }).catch((e) => { if (alive) setMapErr(e.message); });
    return () => { alive = false; };
  }, []);

  // ---- Tìm địa điểm: gõ → gợi ý (debounce) ; chọn → bay tới + ghim ----
  const onPlaceInput = (val) => {
    setPlaceQ(val); setPlaceActive(-1);
    const kw = val.trim();
    if (acTimerRef.current) clearTimeout(acTimerRef.current);
    if (!kw || !acSvcRef.current || !window.google) { setPlacePreds([]); setPlaceOpen(false); return; }
    acTimerRef.current = setTimeout(() => {
      const maps = window.google.maps;
      if (!acTokenRef.current) acTokenRef.current = new maps.places.AutocompleteSessionToken();
      const req = { input: kw, sessionToken: acTokenRef.current, componentRestrictions: { country: "vn" }, language: "vi" };
      // Ưu tiên gợi ý quanh vùng đang xem.
      const b = mapRef.current && mapRef.current.getBounds();
      if (b) { req.locationBias = b; } else { req.location = new maps.LatLng(16.5, 106.5); req.radius = 800000; }
      acSvcRef.current.getPlacePredictions(req, (preds, status) => {
        if (status !== maps.places.PlacesServiceStatus.OK || !preds) { setPlacePreds([]); setPlaceOpen(true); return; }
        setPlacePreds(preds.slice(0, 6)); setPlaceOpen(true);
      });
    }, 220);
  };

  const flyToPlace = (pred) => {
    const maps = window.google && window.google.maps;
    if (!maps || !placesSvcRef.current || !pred) return;
    setPlaceOpen(false); setPlaceQ(pred.description); setPlacePreds([]);
    placesSvcRef.current.getDetails(
      { placeId: pred.place_id, fields: ["geometry", "name", "formatted_address"], sessionToken: acTokenRef.current },
      (place, status) => {
        acTokenRef.current = null;  // hết phiên → token mới cho lần tìm sau
        if (status !== maps.places.PlacesServiceStatus.OK || !place || !place.geometry) {
          window.trkToast && window.trkToast("Không lấy được vị trí địa điểm", "error"); return;
        }
        const loc = place.geometry.location;
        if (place.geometry.viewport) mapRef.current.fitBounds(place.geometry.viewport);
        else { mapRef.current.panTo(loc); mapRef.current.setZoom(16); }
        if (!searchMkRef.current) {
          searchMkRef.current = new maps.Marker({ map: mapRef.current, zIndex: 9999,
            icon: { path: maps.SymbolPath.CIRCLE, scale: 9, fillColor: "#4f46e5", fillOpacity: 1, strokeColor: "#fff", strokeWeight: 2.5 } });
        }
        searchMkRef.current.setPosition(loc);
        searchMkRef.current.setMap(mapRef.current);
        whInfoRef.current.setContent(`<div style="font:600 13px/1.4 system-ui;max-width:240px"><div style="color:#4f46e5">${place.name || ""}</div><div style="font-weight:400;color:#555;margin-top:2px">${place.formatted_address || ""}</div></div>`);
        whInfoRef.current.open(mapRef.current, searchMkRef.current);
      }
    );
  };

  const clearPlace = () => {
    setPlaceQ(""); setPlacePreds([]); setPlaceOpen(false); setPlaceActive(-1);
    if (searchMkRef.current) searchMkRef.current.setMap(null);
  };

  const onPlaceKey = (e) => {
    if (!placeOpen || !placePreds.length) return;
    if (e.key === "ArrowDown") { e.preventDefault(); setPlaceActive((i) => Math.min(i + 1, placePreds.length - 1)); }
    else if (e.key === "ArrowUp") { e.preventDefault(); setPlaceActive((i) => Math.max(i - 1, 0)); }
    else if (e.key === "Enter") { e.preventDefault(); flyToPlace(placePreds[placeActive >= 0 ? placeActive : 0]); }
    else if (e.key === "Escape") { setPlaceOpen(false); }
  };

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
        // KHÔNG set map trực tiếp — để MarkerClusterer quản lý hiển thị (gom cụm khi nhiều xe chồng).
        m = new maps.Marker({
          position: pos, icon: iconFor(maps, p, sel), title: p.plate,
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
        if (clustererRef.current) clustererRef.current.addMarker(m, true); else m.setMap(mapRef.current);
      } else {
        if (m.__sig !== sig) { m.setIcon(iconFor(maps, p, sel)); m.__sig = sig; }
        tweenMarker(m, pos);   // trượt mượt tới vị trí mới (cảm giác xe chạy realtime)
      }
      if (follow && sel && mapRef.current) { mapRef.current.panTo(pos); if (mapRef.current.getZoom() < 14) mapRef.current.setZoom(15); }   // bám xe: map đi theo xe đang chọn
    });
    Object.keys(markersRef.current).forEach((id) => { if (!seen.has(id)) { const m = markersRef.current[id]; if (m.__anim) cancelAnimationFrame(m.__anim); if (clustererRef.current) clustererRef.current.removeMarker(m, true); m.setMap(null); delete markersRef.current[id]; } });
    if (clustererRef.current) clustererRef.current.render();   // gom cụm lại theo vị trí mới (1 lần/poll)

    // Fit lần đầu — CHỈ khi container đã có kích thước thật (tránh fitBounds sai lúc layout chưa xong
    // → "đôi khi không thấy xe, reload mới hiện"). Chưa có size thì để ResizeObserver fit lại sau.
    if (!didFit.current && filtered.length && mapEl.current && mapEl.current.offsetHeight > 0) {
      const b = new maps.LatLngBounds();
      filtered.forEach((p) => { if (p.lat != null && p.lng != null) b.extend({ lat: p.lat, lng: p.lng }); });
      if (!b.isEmpty()) { didFit.current = true; mapRef.current.fitBounds(b); if (filtered.length === 1) mapRef.current.setZoom(15); }
    }
    // Chỉ REFRESH nội dung popup ĐANG mở (không tự mở lại mỗi poll → đỡ nháy).
    const openId = infoOpenRef.current;
    if (openId && markerData.current[openId] && infoRef.current) {
      infoRef.current.setContent(popupHtml(markerData.current[openId]));
    }
  }, [filtered, selected, mapReady, follow]);

  // Theo dõi kích thước container map: lúc mới vào layout có thể chưa xong (container 0px) → markers
  // vẽ nhưng map ở vùng sai/xám → "đôi khi không thấy xe". Khi container có/đổi size: ép map resize +
  // fit lại về đàn xe nếu chưa fit lần nào. (Sau khi đã fit thì chỉ resize, không tự đổi khung của user.)
  useEffect(() => {
    if (!mapReady || !mapRef.current || !mapEl.current || !window.google || typeof ResizeObserver === "undefined") return;
    const el = mapEl.current;
    const ro = new ResizeObserver(() => {
      if (!mapRef.current) return;
      window.google.maps.event.trigger(mapRef.current, "resize");
      if (!didFit.current && el.offsetHeight > 0 && filteredRef.current.length) {
        const b = new window.google.maps.LatLngBounds();
        filteredRef.current.forEach((p) => { if (p.lat != null && p.lng != null) b.extend({ lat: p.lat, lng: p.lng }); });
        if (!b.isEmpty()) { didFit.current = true; mapRef.current.fitBounds(b); if (filteredRef.current.length === 1) mapRef.current.setZoom(15); }
      }
    });
    ro.observe(el);
    return () => ro.disconnect();
  }, [mapReady]);

  // FIT LẦN ĐẦU bền vững (chống đua map↔ajax): khi map sẵn sàng & ĐÃ có xe nhưng CHƯA fit,
  // thử fit lặp lại (chờ container có size + layout ổn) → map luôn center về đàn xe ở lần load đầu,
  // không bị kẹt ở center mặc định (vùng Lào) khiến "xe không hiện trên map" dù danh sách có.
  useEffect(() => {
    if (!mapReady || didFit.current || !filtered.length || !mapRef.current || !window.google) return;
    let tries = 0, t = null;
    const tryFit = () => {
      if (didFit.current || !mapRef.current || !mapEl.current) return;
      const list = filteredRef.current;
      if (mapEl.current.offsetHeight > 0 && list.length) {
        const b = new window.google.maps.LatLngBounds();
        list.forEach((p) => { if (p.lat != null && p.lng != null) b.extend({ lat: p.lat, lng: p.lng }); });
        if (!b.isEmpty()) {
          didFit.current = true;
          window.google.maps.event.trigger(mapRef.current, "resize");
          mapRef.current.fitBounds(b);
          if (list.length === 1) mapRef.current.setZoom(15);
          return;
        }
      }
      if (tries++ < 25) t = setTimeout(tryFit, 150);   // chờ layout settle, tối đa ~3.7s
    };
    tryFit();
    return () => clearTimeout(t);
  }, [mapReady, filtered.length]);

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
          icon: { url: WAREHOUSE_PIN, scaledSize: new maps.Size(34, 42), anchor: new maps.Point(17, 41) },
          label: { text: w.name, className: "trk-wh-label", color: "#3730a3", fontSize: "11px", fontWeight: "700" } });
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

  // Bật/tắt hiển thị địa điểm (POI). Tắt = dùng MAP_STYLE (ẩn dịch vụ); Bật = bản đồ đầy đủ (thấy Canon… để ghim kho).
  useEffect(() => {
    if (mapRef.current) mapRef.current.setOptions({ styles: showPoi ? [] : MAP_STYLE });
  }, [showPoi, mapReady]);

  // Tắt overlay loading khi map đã sẵn sàng + có dữ liệu lần đầu (chờ fitBounds zoom ra xong).
  useEffect(() => {
    if (booted || mapErr) return;
    if (mapReady && lastTs) { const t = setTimeout(() => setBooted(true), 450); return () => clearTimeout(t); }
  }, [mapReady, lastTs, booted, mapErr]);

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

  // Khít bản đồ về toàn bộ xe đang hiển thị (mặc định: danh sách đang lọc).
  const fitAll = (pts) => {
    if (!mapRef.current || !window.google) return;
    const arr = (pts || filteredRef.current || []).filter((p) => p.lat != null && p.lng != null);
    if (!arr.length) return;
    const b = new window.google.maps.LatLngBounds();
    arr.forEach((p) => b.extend({ lat: p.lat, lng: p.lng }));
    mapRef.current.fitBounds(b);
    if (arr.length === 1) mapRef.current.setZoom(15);
  };
  const closeInfo = () => { if (infoRef.current) infoRef.current.close(); infoOpenRef.current = null; };
  const zoomBy = (d) => { if (mapRef.current) mapRef.current.setZoom((mapRef.current.getZoom() || 6) + d); };
  // "Toàn cảnh": bỏ chọn xe + đóng popup + khít lại về toàn bộ xe đang lọc.
  const overview = () => { setSelected(null); setFollow(false); closeInfo(); fitAll(); };
  // "Xóa lọc": gỡ MỌI bộ lọc + ô tìm + chọn → tự khít về toàn bộ xe.
  const clearFilters = () => {
    setQ(""); setFStatus("all"); setFProvider("all"); setMatchedOnly(false); setSelected(null); setFollow(false);
    closeInfo();
    if (searchMkRef.current) searchMkRef.current.setMap(null);
    setPlaceQ(""); setPlacePreds([]); setPlaceOpen(false);
    setTimeout(() => fitAll(positions), 40);
  };
  const hasFilter = fStatus !== "all" || fProvider !== "all" || matchedOnly || !!q.trim() || !!selected;

  // Đổi bộ lọc (trạng thái/nguồn/xe hệ thống) → tự khít lại bản đồ theo nhóm xe khớp.
  const filterSig = fStatus + "|" + fProvider + "|" + (matchedOnly ? 1 : 0);
  const prevFilterSig = useRef(null);
  useEffect(() => {
    if (!mapReady) return;
    if (prevFilterSig.current === null) { prevFilterSig.current = filterSig; return; }   // bỏ qua lần đầu (đã có fitBounds khởi tạo)
    if (prevFilterSig.current === filterSig) return;
    prevFilterSig.current = filterSig;
    setTimeout(() => fitAll(filteredRef.current), 40);
  }, [filterSig, mapReady]);

  // Phím Esc: đang đặt kho → hủy; đang mở gợi ý địa điểm → đóng; đang chọn xe → bỏ chọn.
  useEffect(() => {
    const onKey = (e) => {
      if (e.key !== "Escape") return;
      if (placingId != null) { setPlacingId(null); return; }
      if (placeOpen) { setPlaceOpen(false); return; }
      if (selected) { setSelected(null); setFollow(false); closeInfo(); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [placingId, placeOpen, selected]);

  const enabledProviders = providers.filter((p) => p.enabled);
  const noData = !loading && positions.length === 0;
  const selVeh = selected ? positions.find((p) => idOf(p) === selected) : null;   // xe đang chọn (cho chip điều khiển + bám xe)

  // ---- Product tour (driver.js, lazy-load qua @trk/tour.js) ----
  const TOUR_KEY = "trk_tour_track_v2";
  const tourSteps = [
    { element: '[data-tour="title"]', title: "Theo dõi xe realtime 👋", description: "Tại đây bạn xem vị trí mọi xe theo thời gian thực, tự cập nhật khoảng 15 giây/lần. Cùng lướt qua vài tính năng chính nhé!", side: "bottom", align: "start" },
    { element: '[data-tour="status"]', title: "Lọc theo trạng thái", description: "Bấm để lọc nhanh: Đang chạy / Dừng / Tắt máy / Mất tín hiệu. Bản đồ tự khít lại theo nhóm xe; bấm “Tất cả” để xem hết.", side: "bottom", align: "start" },
    { element: '[data-tour="matched"]', title: "Chỉ xe hệ thống", description: "Bật để chỉ hiện những xe đã khớp với đội xe của bạn (ẩn xe lạ cùng nhà cung cấp GPS).", side: "bottom", align: "start" },
    { element: '[data-tour="search"]', title: "Tìm xe", description: "Gõ biển số, tên tài xế hoặc địa chỉ để lọc nhanh danh sách xe.", side: "right", align: "start" },
    { element: '[data-tour="list"]', title: "Danh sách xe", description: "Bấm 1 xe để xem trên bản đồ (bấm lại để bỏ chọn). Mỗi xe hiện tốc độ, khoảng cách & thời gian dự kiến tới kho gần nhất.", side: "right", align: "start" },
    { element: '[data-tour="placesearch"]', title: "Tìm địa điểm trên bản đồ", description: "Gõ địa chỉ/địa điểm bất kỳ rồi chọn gợi ý — bản đồ sẽ bay tới đó ngay.", side: "bottom", align: "start" },
    { element: '[data-tour="overview"]', title: "Toàn cảnh & lớp bản đồ", description: "Cụm nút này: <b>Toàn cảnh</b> đưa bản đồ về xem tất cả xe (bỏ chọn xe); <b>Giao thông</b> hiện tình trạng kẹt đường; <b>Địa điểm</b> hiện POI (quán, cây xăng…); <b>Vệ tinh</b> đổi sang ảnh vệ tinh. Lựa chọn được ghi nhớ cho lần sau.", side: "top", align: "start" },
    { title: "Theo dõi xe", description: "Khi chọn 1 xe sẽ có nút “Theo dõi xe” — bật để bản đồ tự đi theo khi xe di chuyển. Kéo bản đồ bằng tay sẽ tự dừng. Nhấn phím Esc để bỏ chọn nhanh." },
    ...(canEdit && ROUTES.warehouseGeo ? [{ element: '[data-tour="warehouse"]', title: "Vị trí kho", description: "Bạn có quyền ghim vị trí kho lên bản đồ — hệ thống dùng để tính khoảng cách và lịch sử xe ra/vào kho.", side: "bottom", align: "end" }] : []),
    { element: '[data-tour="history"]', title: "Lịch sử đến kho", description: "Xem lại lịch sử xe đến/rời từng kho ở đây.", side: "bottom", align: "end" },
    { element: '[data-tour="help"]', title: "Xong! 🎉", description: "Bạn có thể mở lại hướng dẫn này bất cứ lúc nào bằng nút “Hướng dẫn” ở góc trên. Chúc bạn theo dõi xe hiệu quả!", side: "bottom", align: "end" },
  ];
  const startTour = () => { import("@trk/tour.js").then(({ runTour }) => runTour(tourSteps, { key: TOUR_KEY })); };
  // Tự mở lần đầu (sau khi bản đồ + dữ liệu sẵn sàng) nếu chưa từng xem.
  useEffect(() => {
    if (!booted) return;
    import("@trk/tour.js").then(({ runTour, tourSeen }) => { if (!tourSeen(TOUR_KEY)) setTimeout(() => runTour(tourSteps, { key: TOUR_KEY }), 400); });
  }, [booted]);

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column" }}>
      {/* header */}
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: isMobile ? "10px 14px" : "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 12, height: isMobile ? "auto" : 58, flexWrap: "wrap" }}>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center", flexShrink: 0 }}><i className="bi bi-geo-alt-fill" /></div>
          <div data-tour="title">
            <div style={{ fontSize: 15.5, fontWeight: 700, letterSpacing: "-0.01em" }}>Theo dõi xe realtime</div>
            <div style={{ fontSize: 11.5, color: "var(--ink-3)" }}>{enabledProviders.map((p) => `${p.label}: ${p.count}`).join(" · ") || "Chưa bật nguồn GPS nào"}</div>
          </div>
          <div style={{ flex: 1 }} />
          <span style={{ fontSize: 12, color: stale ? "var(--warn)" : "var(--ink-3)", display: "inline-flex", alignItems: "center", gap: 6, fontWeight: stale ? 600 : 400 }}>
            {(loading && !lastTs)
              ? <><span style={{ width: 8, height: 8, borderRadius: 999, background: "var(--ink-4)" }} /> Đang kết nối…</>
              : stale
              ? <><span style={{ width: 8, height: 8, borderRadius: 999, background: "var(--warn)" }} /> Mất kết nối · đang thử lại{lastTs ? ` · cập nhật ${timeAgo(lastTs)}` : ""}</>
              : <><span style={{ width: 8, height: 8, borderRadius: 999, background: "var(--good)", boxShadow: "0 0 0 3px rgba(31,138,91,.18)" }} /> Trực tuyến · {timeAgo(lastTs)}</>}
          </span>
          <button type="button" data-tour="help" onClick={startTour} title="Xem hướng dẫn các tính năng"
            style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "8px 12px", fontSize: 13, fontWeight: 600, cursor: "pointer", color: "var(--ink-2)", background: "#fff", border: "1px solid var(--line)", borderRadius: 9 }}>
            <i className="bi bi-question-circle" /> Hướng dẫn
          </button>
          <a href={ROUTES.visitsPage} data-tour="history" title="Lịch sử xe đến/rời kho"
            style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "8px 12px", fontSize: 13, fontWeight: 600, cursor: "pointer", color: "var(--ink-2)", background: "#fff", border: "1px solid var(--line)", borderRadius: 9, textDecoration: "none" }}>
            <i className="bi bi-clock-history" /> Lịch sử đến kho
          </a>
          {canEdit && ROUTES.warehouseGeo && <button type="button" data-tour="warehouse" onClick={() => { setWhPanel((v) => !v); if (whPanel) setPlacingId(null); }} title="Ghim vị trí kho trên bản đồ"
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
      <div data-tour="status" style={{ display: "flex", alignItems: "center", gap: 8, background: "#fff", borderBottom: "1px solid var(--line)", padding: isMobile ? "8px 14px" : "8px 22px", flexShrink: 0, flexWrap: isMobile ? "nowrap" : "wrap", overflowX: isMobile ? "auto" : "visible", WebkitOverflowScrolling: "touch" }}>
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
        <button type="button" data-tour="matched" onClick={() => setMatchedOnly((v) => !v)}
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
        {hasFilter && (
          <button type="button" onClick={clearFilters} title="Bỏ mọi bộ lọc & về toàn cảnh bản đồ"
            style={{ flexShrink: 0, display: "inline-flex", alignItems: "center", gap: 6, border: "1px solid var(--danger)", background: "var(--danger-weak, #fdecec)", color: "var(--danger)", borderRadius: 999, padding: "5px 11px", cursor: "pointer", fontSize: 12.5, fontWeight: 600, whiteSpace: "nowrap" }}>
            <i className="bi bi-x-circle" /> Xóa lọc
          </button>
        )}
      </div>

      {/* body */}
      <div style={{ flex: 1, minHeight: 0, display: "flex", flexDirection: isMobile ? "column" : "row" }}>
        {/* list */}
        <div data-tour="list" style={{ width: isMobile ? "100%" : 340, flex: isMobile ? "1 1 auto" : "0 0 auto", minHeight: 0, borderRight: isMobile ? "none" : "1px solid var(--line)", background: "#fff", overflowY: "auto", display: isMobile && mobileView !== "list" ? "none" : "block", order: isMobile ? 2 : 0 }}>
          <div style={{ padding: "10px 12px", borderBottom: "1px solid var(--line-2)", position: "sticky", top: 0, background: "#fff", zIndex: 2 }}>
            <div data-tour="search" style={{ position: "relative" }}>
              <span style={{ position: "absolute", left: 10, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)" }}><I.search /></span>
              <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Tìm biển số, tài xế, địa chỉ…"
                style={{ width: "100%", padding: "8px 10px 8px 32px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 9, outline: "none", background: "#fafbfc", boxSizing: "border-box" }} />
            </div>
          </div>
          {filtered.length === 0 && <div style={{ padding: "30px 16px", textAlign: "center", color: "var(--ink-4)", fontSize: 13 }}>{noData ? "Chưa có dữ liệu xe." : "Không có xe khớp bộ lọc."}</div>}
          {/* Bậc trạng thái: nổ máy → dừng → tắt máy → mất tín hiệu; cùng bậc → gần kho nhất → tốc độ.
             (đã tính sẵn trong sortedRows để khỏi tính lại mỗi render) */}
          {sortedRows.map(({ p, near: n, at, eta }) => {
            const id = idOf(p); const st = STATUS[effStatus(p)] || STATUS.off; const on = id === selected;
            return (
              <button key={id} type="button" onClick={() => { if (on) { setSelected(null); setFollow(false); closeInfo(); } else focusVehicle(p); }}
                style={{ width: "100%", textAlign: "left", display: "block", border: "none", borderLeft: `3px solid ${on ? st.color : "transparent"}`, borderBottom: "1px solid var(--line-2)", background: on ? "var(--accent-weak-2)" : "#fff", cursor: "pointer", padding: "9px 13px" }}>
                <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                  <span style={{ width: 9, height: 9, borderRadius: 999, background: st.color, flexShrink: 0 }} />
                  <span className="tnum" style={{ fontWeight: 700, fontSize: 13.5 }}>{p.plate || "—"}</span>
                  {p.matched && <span title="Khớp xe trong hệ thống" style={{ fontSize: 10, fontWeight: 700, color: "var(--good)", background: "var(--good-weak)", padding: "1px 6px", borderRadius: 999 }}>✓</span>}
                  <span style={{ flex: 1 }} />
                  <span className="tnum" style={{ fontSize: 12.5, fontWeight: 600, color: st.color }}>{Math.round(p.speed || 0)} km/h</span>
                </div>
                <div style={{ fontSize: 11.5, color: "var(--ink-3)", marginTop: 3, display: "flex", alignItems: "center", gap: 6 }}>
                  {p.driver && <span style={{ whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{p.driver}</span>}
                  <span style={{ color: "var(--ink-4)", whiteSpace: "nowrap" }}>· {timeAgo(p.ts)}</span>
                  {eta && <span style={{ marginLeft: "auto", whiteSpace: "nowrap", fontWeight: 600, color: "var(--good)" }}><i className="bi bi-clock-history" /> {eta}</span>}
                </div>
                {p.address && <div style={{ fontSize: 11, color: "var(--ink-4)", marginTop: 2, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{p.address}</div>}
                {n && (
                  <div style={{ fontSize: 11, marginTop: 3, display: "flex", alignItems: "center", gap: 5, fontWeight: 600, color: at ? "var(--good)" : "#4f46e5", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                    <i className={"bi " + (at ? "bi-house-check-fill" : "bi-geo-alt-fill")} />
                    {at ? `Đã ở kho ${n.w.name}` : <>Cách kho <b>{n.w.name}</b>: <span className="tnum">{fmtDist(n.km)}</span></>}
                  </div>
                )}
              </button>
            );
          })}
        </div>

        {/* map */}
        <div style={{ flex: 1, minHeight: 0, position: "relative", display: isMobile && mobileView !== "map" ? "none" : "block", order: isMobile ? 1 : 0 }}>
          <div ref={mapEl} style={{ position: "absolute", inset: 0, background: "#e9edf1" }} />

          {/* Ô tìm địa điểm (Google Places autocomplete) → bay tới điểm chọn */}
          {mapReady && acSvcRef.current && (
            <div data-tour="placesearch" style={{ position: "absolute", top: 12, left: 10, zIndex: 8, width: isMobile ? "calc(100% - 20px)" : 320 }}>
              <div style={{ position: "relative" }}>
                <span style={{ position: "absolute", left: 11, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", pointerEvents: "none" }}><I.search /></span>
                <input value={placeQ} onChange={(e) => onPlaceInput(e.target.value)} onKeyDown={onPlaceKey}
                  onFocus={() => { if (placePreds.length) setPlaceOpen(true); }}
                  onBlur={() => setTimeout(() => setPlaceOpen(false), 160)}
                  placeholder="Tìm địa điểm, địa chỉ trên bản đồ…"
                  style={{ width: "100%", padding: "10px 34px 10px 34px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 10, outline: "none", background: "#fff", boxShadow: "0 2px 8px rgba(0,0,0,.18)", boxSizing: "border-box" }} />
                {placeQ && (
                  <button type="button" onMouseDown={(e) => e.preventDefault()} onClick={clearPlace} title="Xóa"
                    style={{ position: "absolute", right: 8, top: "50%", transform: "translateY(-50%)", border: "none", background: "transparent", cursor: "pointer", color: "var(--ink-4)", display: "inline-flex" }}><I.x /></button>
                )}
              </div>
              {placeOpen && (
                <div style={{ marginTop: 4, background: "#fff", border: "1px solid var(--line)", borderRadius: 10, boxShadow: "0 6px 20px rgba(0,0,0,.18)", overflow: "hidden" }}>
                  {placePreds.length === 0 ? (
                    <div style={{ padding: "10px 12px", fontSize: 13, color: "var(--ink-4)" }}>Không tìm thấy địa điểm</div>
                  ) : placePreds.map((p, i) => {
                    const m = p.structured_formatting || {};
                    return (
                      <div key={p.place_id} onMouseDown={(e) => e.preventDefault()} onClick={() => flyToPlace(p)} onMouseEnter={() => setPlaceActive(i)}
                        style={{ display: "flex", alignItems: "flex-start", gap: 9, padding: "9px 12px", cursor: "pointer", background: i === placeActive ? "var(--accent-soft, #eef2ff)" : "#fff", borderTop: i ? "1px solid var(--line)" : "none" }}>
                        <i className="bi bi-geo-alt" style={{ color: "#4f46e5", marginTop: 2, fontSize: 14 }} />
                        <div style={{ minWidth: 0 }}>
                          <div style={{ fontSize: 13, fontWeight: 600, color: "var(--ink-1)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{m.main_text || p.description}</div>
                          {m.secondary_text && <div style={{ fontSize: 11.5, color: "var(--ink-4)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{m.secondary_text}</div>}
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          )}

          {/* Chip điều khiển xe đang chọn: bám xe / bỏ chọn (ẩn khi đang đặt kho) */}
          {mapReady && selVeh && placingId == null && (
            <div style={{ position: "absolute", top: 12, left: "50%", transform: "translateX(-50%)", zIndex: 7, display: "flex", alignItems: "center", gap: 8, background: "#fff", border: "1px solid var(--line)", borderRadius: 999, padding: "5px 6px 5px 13px", boxShadow: "0 4px 16px rgba(0,0,0,.2)", maxWidth: "92%" }}>
              <span style={{ width: 9, height: 9, borderRadius: 999, background: (STATUS[effStatus(selVeh)] || STATUS.off).color, flexShrink: 0 }} />
              <span className="tnum" style={{ fontWeight: 700, fontSize: 13, whiteSpace: "nowrap" }}>{selVeh.plate || "—"}</span>
              <span className="tnum" style={{ fontSize: 12, color: "var(--ink-4)", whiteSpace: "nowrap" }}>{Math.round(selVeh.speed || 0)} km/h</span>
              <button type="button" onClick={() => setFollow((v) => !v)} title={follow ? "Đang theo dõi xe — bấm để dừng" : "Theo dõi xe khi di chuyển"}
                style={{ display: "inline-flex", alignItems: "center", gap: 5, padding: "5px 11px", fontSize: 12, fontWeight: 600, cursor: "pointer", borderRadius: 999, whiteSpace: "nowrap",
                  border: follow ? "1px solid var(--accent)" : "1px solid var(--line)", background: follow ? "var(--accent)" : "#fff", color: follow ? "#fff" : "var(--ink-2)" }}>
                <i className={"bi " + (follow ? "bi-broadcast-pin" : "bi-pin-map")} /> {follow ? "Đang theo dõi" : "Theo dõi xe"}
              </button>
              <button type="button" onClick={() => { setSelected(null); setFollow(false); closeInfo(); }} title="Bỏ chọn (Esc)"
                style={{ width: 28, height: 28, flexShrink: 0, display: "grid", placeItems: "center", border: "none", borderRadius: 999, background: "var(--line-2)", color: "var(--ink-3)", cursor: "pointer" }}><I.x /></button>
            </div>
          )}

          {/* Overlay loading khi mới vào — tắt sau khi map dựng xong + zoom ra */}
          {!booted && !mapErr && (
            <div style={{ position: "absolute", inset: 0, zIndex: 7, display: "grid", placeItems: "center", background: "rgba(247,249,251,0.94)", backdropFilter: "blur(2px)" }}>
              <div style={{ textAlign: "center" }}>
                <div style={{ width: 46, height: 46, margin: "0 auto 14px", border: "4px solid var(--line)", borderTopColor: "var(--accent)", borderRadius: "50%", animation: "trk-spin .8s linear infinite" }} />
                <div style={{ fontSize: 14, fontWeight: 700, color: "var(--ink-2)" }}>Đang tải bản đồ theo dõi…</div>
                <div style={{ fontSize: 12, color: "var(--ink-4)", marginTop: 4 }}>Đang lấy vị trí xe &amp; dựng bản đồ</div>
              </div>
            </div>
          )}

          {/* Lớp bản đồ: Toàn cảnh + Giao thông + Vệ tinh (góc dưới-trái) */}
          {mapReady && (
            <div data-tour="overview" style={{ position: "absolute", left: 10, bottom: 22, zIndex: 6, display: "flex", gap: 6, flexWrap: "wrap" }}>
              <button type="button" onClick={overview} title="Thu toàn cảnh — bỏ chọn xe & xem tất cả"
                style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: isMobile ? "10px 14px" : "7px 11px", fontSize: isMobile ? 13.5 : 12.5, fontWeight: 600, cursor: "pointer", borderRadius: 8,
                  border: "1px solid var(--line)", background: "#fff", color: "var(--ink-2)", boxShadow: "0 1px 4px rgba(0,0,0,.2)" }}>
                <i className="bi bi-arrows-fullscreen" /> Toàn cảnh
              </button>
              {[["traffic", "Giao thông", "bi-traffic-light", trafficOn, () => setTrafficOn((v) => !v)],
                ["poi", "Địa điểm", "bi-shop", showPoi, () => setShowPoi((v) => !v)],
                ["sat", "Vệ tinh", "bi-globe-asia-australia", satellite, () => setSatellite((v) => !v)]].map(([k, label, ic, on, toggle]) => (
                <button key={k} type="button" onClick={toggle} title={label}
                  style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: isMobile ? "10px 14px" : "7px 11px", fontSize: isMobile ? 13.5 : 12.5, fontWeight: 600, cursor: "pointer", borderRadius: 8,
                    border: on ? "1px solid var(--accent)" : "1px solid var(--line)", background: on ? "var(--accent)" : "#fff", color: on ? "#fff" : "var(--ink-2)", boxShadow: "0 1px 4px rgba(0,0,0,.2)" }}>
                  <i className={"bi " + ic} /> {label}
                </button>
              ))}
            </div>
          )}

          {/* Nút Zoom + / − (góc dưới-phải) */}
          {mapReady && (
            <div style={{ position: "absolute", right: 10, bottom: 22, zIndex: 6, display: "flex", flexDirection: "column", borderRadius: 9, overflow: "hidden", boxShadow: "0 1px 5px rgba(0,0,0,.28)", border: "1px solid var(--line)" }}>
              {[["bi-plus-lg", 1, "Phóng to"], ["bi-dash-lg", -1, "Thu nhỏ"]].map(([ic, d, label], i) => (
                <button key={ic} type="button" onClick={() => zoomBy(d)} title={label} aria-label={label}
                  style={{ width: isMobile ? 44 : 38, height: isMobile ? 44 : 38, display: "grid", placeItems: "center", border: "none", borderTop: i ? "1px solid var(--line-2)" : "none", background: "#fff", color: "var(--ink-1)", cursor: "pointer", fontSize: isMobile ? 18 : 16 }}
                  onMouseEnter={(e) => (e.currentTarget.style.background = "var(--line-2)")} onMouseLeave={(e) => (e.currentTarget.style.background = "#fff")}>
                  <i className={"bi " + ic} />
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
              {!hintHidden && (
                <div style={{ display: "flex", alignItems: "flex-start", gap: 6, padding: "8px 10px", fontSize: 11.5, color: "var(--ink-3)", borderBottom: "1px solid var(--line-2)", lineHeight: 1.5 }}>
                  <div style={{ flex: 1 }}>Bấm <b>Đặt</b> rồi <b>bấm lên bản đồ</b> tại vị trí kho — hoặc bấm vào <b>xe đang đỗ</b> ở kho để lấy đúng tọa độ. Đã ghim thì <b>kéo</b> ghim 🏭 để chỉnh.</div>
                  <button type="button" onClick={() => setHintHidden(true)} title="Ẩn hướng dẫn"
                    style={{ flexShrink: 0, border: "none", background: "transparent", cursor: "pointer", color: "var(--ink-4)", padding: 0, lineHeight: 1, marginTop: -1 }}><I.x /></button>
                </div>
              )}
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
                      {pinned && !active && (
                        <button type="button" onClick={() => removePin(w.id)} title="Gỡ ghim (xóa tọa độ kho)"
                          style={{ flexShrink: 0, border: "1px solid var(--line)", background: "#fff", color: "var(--ink-4)", borderRadius: 8, padding: "5px 9px", cursor: "pointer", fontSize: 12, fontWeight: 600 }}
                          onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; e.currentTarget.style.borderColor = "#f3c9c9"; }}
                          onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-4)"; e.currentTarget.style.borderColor = "var(--line)"; }}>
                          Gỡ
                        </button>
                      )}
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
