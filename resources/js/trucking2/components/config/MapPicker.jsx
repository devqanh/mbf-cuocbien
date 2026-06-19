import React from "react";
const { useState, useRef, useEffect } = React;
import { Btn, Modal } from "@trk/lib.jsx";

/* ===== MapPicker: ghim tọa độ kho (Google Maps + Places Autocomplete) ===== */
let _cfgGmaps = null;
function loadCfgGmaps(key) {
  if (window.google && window.google.maps && window.google.maps.places) return Promise.resolve(window.google.maps);
  if (_cfgGmaps) return _cfgGmaps;
  _cfgGmaps = new Promise((resolve, reject) => {
    const cb = "__trkCfgGmapsCb";
    window[cb] = () => resolve(window.google.maps);
    const s = document.createElement("script");
    s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&callback=${cb}&libraries=places&language=vi&region=VN`;
    s.async = true; s.defer = true;
    s.onerror = () => reject(new Error("Không tải được Google Maps (kiểm tra API key / mạng)."));
    document.head.appendChild(s);
  });
  return _cfgGmaps;
}
function ensurePacStyle() {
  if (document.getElementById("trk-pac-style")) return;
  const s = document.createElement("style"); s.id = "trk-pac-style";
  s.textContent = ".pac-container{z-index:3000 !important}";   // gợi ý địa chỉ nổi trên modal (z-index modal ~1100)
  document.head.appendChild(s);
}

/* Ô địa chỉ có GỢI Ý Google Places — chọn xong tự điền địa chỉ + trả về tọa độ (onPlace). */
export function AddrInput({ value, onChange, onPlace, mapsKey, placeholder }) {
  const ref = React.useRef(null);
  const acRef = React.useRef(null);
  const cb = React.useRef({ onChange, onPlace });
  cb.current = { onChange, onPlace };
  const attach = () => {
    if (acRef.current || !mapsKey || !ref.current) return;
    ensurePacStyle();
    loadCfgGmaps(mapsKey).then((maps) => {
      if (acRef.current || !ref.current || !maps.places) return;
      const ac = new maps.places.Autocomplete(ref.current, { fields: ["geometry", "formatted_address", "name"], componentRestrictions: { country: "vn" } });
      acRef.current = ac;
      ac.addListener("place_changed", () => {
        const pl = ac.getPlace();
        const addr = (pl && (pl.formatted_address || pl.name)) || "";
        if (addr) cb.current.onChange(addr);
        const loc = pl && pl.geometry && pl.geometry.location;
        if (loc) cb.current.onPlace(loc.lat(), loc.lng());
      });
    }).catch(() => {});
  };
  return (
    <input ref={ref} value={value || ""} placeholder={placeholder}
      onChange={(e) => onChange(e.target.value)}
      onKeyDown={(e) => { if (e.key === "Enter") e.preventDefault(); }}
      onFocus={(e) => { attach(); e.target.style.borderColor = "var(--accent)"; }}
      onBlur={(e) => (e.target.style.borderColor = "var(--line)")}
      style={{ width: "100%", padding: "7px 10px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, outline: "none", background: "#fff" }} />
  );
}

export function MapPicker({ initial, address, mapsKey, onClose, onPick }) {
  const elRef = useRef(null), mapRef = useRef(null), mkRef = useRef(null), geoRef = useRef(null), searchRef = useRef(null);
  const [pos, setPos] = useState(initial || null);
  const [addr, setAddr] = useState(address || "");
  const [err, setErr] = useState("");

  useEffect(() => {
    if (!mapsKey) { setErr("Chưa cấu hình Google Maps API key (Cài đặt hệ thống → Giám sát hành trình)."); return; }
    ensurePacStyle();
    let alive = true;
    loadCfgGmaps(mapsKey).then((maps) => {
      if (!alive || !elRef.current) return;
      const center = initial || { lat: 21.0, lng: 105.84 };
      const map = new maps.Map(elRef.current, { center, zoom: initial ? 16 : 11, mapTypeControl: false, streetViewControl: false, fullscreenControl: false, clickableIcons: false, gestureHandling: "greedy" });
      mapRef.current = map; geoRef.current = new maps.Geocoder();
      const mk = new maps.Marker({ position: center, map, draggable: true });
      mkRef.current = mk; if (!initial) mk.setVisible(false);
      const apply = (latLng, doGeocode) => {
        const p = { lat: latLng.lat(), lng: latLng.lng() };
        mk.setPosition(p); mk.setVisible(true); setPos(p);
        if (doGeocode) geoRef.current.geocode({ location: p }, (res, st) => { if (st === "OK" && res[0]) setAddr(res[0].formatted_address); });
      };
      map.addListener("click", (e) => apply(e.latLng, true));
      mk.addListener("dragend", (e) => apply(e.latLng, true));
      if (searchRef.current && maps.places) {
        const ac = new maps.places.Autocomplete(searchRef.current, { fields: ["geometry", "formatted_address", "name"], componentRestrictions: { country: "vn" } });
        ac.bindTo("bounds", map);
        ac.addListener("place_changed", () => {
          const pl = ac.getPlace(); if (!pl.geometry) return;
          map.panTo(pl.geometry.location); map.setZoom(16);
          apply(pl.geometry.location, false); setAddr(pl.formatted_address || pl.name || "");
        });
      }
    }).catch((e) => { if (alive) setErr(e.message); });
    return () => { alive = false; };
  }, []);

  const footer = (
    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: 12 }}>
      <div style={{ fontSize: 12.5, color: "var(--ink-3)" }}>{pos ? <span className="tnum">📍 {pos.lat.toFixed(6)}, {pos.lng.toFixed(6)}</span> : "Tìm địa chỉ hoặc bấm/ghim trên bản đồ"}</div>
      <div style={{ display: "flex", gap: 8 }}>
        <Btn onClick={onClose}>Hủy</Btn>
        <Btn variant="primary" disabled={!pos} onClick={() => onPick({ lat: pos.lat, lng: pos.lng, address: addr })}>Lưu vị trí</Btn>
      </div>
    </div>
  );

  return (
    <Modal title="Ghim vị trí kho" subtitle="Tìm địa chỉ hoặc bấm trên bản đồ để lấy tọa độ" onClose={onClose} footer={footer} width={760} icon={<i className="bi bi-geo-alt" />}>
      {err ? <div style={{ padding: 20, color: "var(--danger)", fontSize: 13.5 }}>{err}</div> : (
        <div>
          <input ref={searchRef} placeholder="Tìm địa chỉ / tên địa điểm…"
            style={{ width: "100%", padding: "9px 12px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 9, outline: "none", marginBottom: 10, boxSizing: "border-box" }} />
          <div ref={elRef} style={{ width: "100%", height: 420, borderRadius: 10, background: "#e9edf1" }} />
          {addr && <div style={{ fontSize: 12.5, color: "var(--ink-3)", marginTop: 8 }}><i className="bi bi-pin-map" /> {addr}</div>}
        </div>
      )}
    </Modal>
  );
}
