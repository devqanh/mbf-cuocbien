// Helpers thuần (không React state): trạng thái xe, format, icon/marker, map style, load Google Maps.
import { I } from "@trk/lib.jsx";

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


export { CLUSTER_RENDERER, POLL_MS, STALE_MS, STATUS, effStatus, statusColor, STATUS_RANK, esc, timeAgo, fmtClock, haversineKm, fmtDist, AT_WH_KM, ETA_MIN_KMH, SPEED_WINDOW_MS, vehKey, avgSpeedKmh, fmtEta, popupHtml, MAP_STYLE, WAREHOUSE_PIN, whPopup, vehicleSvg, iconFor, tweenMarker, ensurePlateStyle, loadGoogleMaps };
