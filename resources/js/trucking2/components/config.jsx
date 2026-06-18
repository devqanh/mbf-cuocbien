import React from "react";
const { useState, useRef, useMemo, useEffect } = React;
import { I, Money, Payer, Txt, Combo, MultiCombo, DateField, Num, Line, Section, Modal, Btn, fmtVND, fmtNum, fmtShort, calcCost, calcVeh, calcRev, calcVehICD, calcRevICD, calcFreeTime, fmtHours, toNum, useIsMobile } from "@trk/lib.jsx";
import { DTField, Field, DriverSpendRows, VatLine, ItemRows, ChiHoRows, DoanhThuRows, ChkBox, TRACK_COLORS, SWATCHES, colorHex, FlagPicker, CostLineRows, PaymentRows, Seg } from "./shared.jsx";

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
function AddrInput({ value, onChange, onPlace, mapsKey, placeholder }) {
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

function MapPicker({ initial, address, mapsKey, onClose, onPick }) {
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

const CFG_GROUPS = [
  { key: "locations", label: "Địa điểm", hint: "depot, cảng, ICD, KCN — dùng cho Tuyến · thêm ký hiệu viết tắt; tự thêm khi import bảng giá (cột FROM + TO)", ph: "VD: Cảng Tân Vũ", coded: true, codeKey: "locationCode", codeNameLabel: "Tên địa điểm", allowDupCode: true },
  { key: "customers", label: "Khách hàng", hint: "quản lý khách hàng — MST, liên hệ, hạn thanh toán, ghi chú…", ph: "VD: Canon Vietnam" },
  { key: "contTypes", label: "Loại container", hint: "dùng cho cột Cont", ph: "VD: 40HC" },
  { key: "warehouses", label: "Kho", hint: "kho hàng — dùng cho lô (chọn tối đa 3) · thêm ký hiệu viết tắt + địa chỉ; tự thêm khi import bảng giá (cột TO)", ph: "VD: Kho A2", coded: true, codeKey: "warehouseCode", codeNameLabel: "Tên kho", addressed: true, geo: true },
  { key: "payers", label: "Bên thanh toán", hint: "dùng cho mọi dòng chi phí", ph: "VD: Tài xế" },
  { key: "costItems", label: "Khoản chi phí", hint: "gắn màu “theo dõi” cho khoản cần nhắc khi chưa điền số tiền — dùng chung cho mọi lô", ph: "VD: Phí cân xe", colored: true },
  { key: "choHoItems", label: "Khoản thu/chi hộ", hint: "dùng cho mục Thu chi hộ ở cả Chi phí & Doanh thu · có đơn giá mặc định", ph: "VD: Nâng", priced: true },
  { key: "revItems", label: "Khoản doanh thu", hint: "dùng cho mục Doanh thu · có đơn giá mặc định", ph: "VD: Doanh thu cước xe", priced: true },
  { key: "vehicles", label: "Biển số xe", hint: "đội xe — mỗi biển số chọn Xe MBF hay Xe ngoài", ph: "VD: 15C-123.45", fleet: true },
  { key: "drivers", label: "Lái xe", hint: "hồ sơ tài xế — SĐT (nhiều số), ngày sinh, ngày vào công ty (tự tính thâm niên), tài khoản ngân hàng, tài liệu CCCD/bằng lái", ph: "VD: A.Tuấn", drivers: true },
  { key: "salaryItems", label: "Khoản lương (lái xe)", hint: "các khoản lương thêm (thưởng, phụ cấp…) — chọn ở Phí xe nội bộ thay vì nhập tay để sau tổng hợp lương dễ", ph: "VD: Thưởng chuyên cần" },
  { key: "vehicleCostTypes", label: "Loại chi phí xe", hint: "loại chi phí bảo dưỡng/sửa chữa xe — chọn ở Quản lý xe (tab Chi phí) để nhóm báo cáo theo loại", ph: "VD: Bảo dưỡng định kỳ" },
  { key: "assetCategories", label: "Loại tài sản", hint: "phân loại tài sản (máy móc, thiết bị, nhà xưởng…) — chọn khi thêm/sửa tài sản ở Quản lý tài sản", ph: "VD: Máy móc thiết bị" },
  { key: "routeFees", label: "Phí tuyến đường", hint: "định mức phí & dầu cho từng tuyến (tập kho) — vé trạm, tiền đường, trợ cấp, phí khác, lương CRU, km, dầu 2 cầu/1 cầu", ph: "", routefees: true },
  { key: "fuelPrices", label: "Bảng giá dầu", hint: "đơn giá dầu (đồng/lít) theo khoảng ngày — link tính tiền dầu cho tuyến theo ngày của lô", ph: "", fuelprices: true },
  { key: "__general", label: "Cấu hình chung", hint: "cấu hình dùng chung cho hệ thống — VAT mặc định, ngưỡng Free time… (mở rộng thêm sau)", general: true },
];

/* ===================== PHÍ TUYẾN ĐƯỜNG (repeater) ===================== */

function RouteFees({ rows = [], onChange, warehouses = [], locations = [], isDup = () => false }) {
  // Node tuyến = Cảng (địa điểm) HOẶC Kho — gợi ý gom 2 nhóm để chọn cả chuỗi Cảng→Kho→Kho→Cảng.
  const routeGroups = [{ label: "Cảng", items: locations || [] }, { label: "Kho", items: warehouses || [] }];
  const set = (i, np) => onChange(rows.map((r, j) => (j === i ? { ...r, ...np } : r)));
  // Thêm tuyến: KẾ THỪA giá + tick "chi theo ngày" của tuyến trên (đỡ nhập lại), chỉ để TRỐNG ô Tuyến.
  const add = () => {
    const list = rows || [];
    const prev = list[list.length - 1];
    const base = prev
      ? { veTram: prev.veTram, tienDuong: prev.tienDuong, troCap: prev.troCap, phiKhac: prev.phiKhac, cru: prev.cru,
          luong: prev.luong, luongNoCru: prev.luongNoCru, luongNokeo: prev.luongNokeo, luongNokeoNoCru: prev.luongNokeoNoCru,
          salaryParts: [...(prev.salaryParts || [])], km: prev.km, dau2: prev.dau2, dau1: prev.dau1 }
      : { veTram: "", tienDuong: "", troCap: "", cru: false, luong: "", luongNoCru: "", luongNokeo: "", luongNokeoNoCru: "", salaryParts: ["troCap", "luong"], km: "", dau2: "", dau1: "" };
    onChange([...list, { id: Date.now() + Math.random(), route: "", ...base }]);
  };
  const del = (i) => onChange(rows.filter((_, j) => j !== i));
  const lbl = (t) => <div style={{ fontSize: 11.5, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>{t}</div>;
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
      {(rows || []).length === 0 && <div style={{ padding: "14px 2px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có tuyến nào — bấm <b>+ Thêm tuyến</b> để cấu hình phí.</div>}
      {(rows || []).map((r, i) => {
        const dup = isDup(r.route);
        const sal = Array.isArray(r.salaryParts) ? r.salaryParts : [];
        const salChk = (key) => (
          <label style={{ display: "inline-flex", alignItems: "center", gap: 4, marginTop: 5, fontSize: 11, fontWeight: 600, color: sal.includes(key) ? "var(--accent)" : "var(--ink-4)", cursor: "pointer", userSelect: "none" }} title="Tích = chi khoản này cho lái xe theo NGÀY (tổng hợp ở Lộ trình theo từng chuyến)">
            <input type="checkbox" checked={sal.includes(key)} onChange={() => set(i, { salaryParts: sal.includes(key) ? sal.filter((k) => k !== key) : [...sal, key] })} style={{ accentColor: "var(--accent)", cursor: "pointer", margin: 0 }} />
            chi theo ngày
          </label>
        );
        return (
        <div key={r.id || i} style={{ border: `1px solid ${dup ? "var(--danger)" : "var(--line)"}`, borderRadius: 12, padding: "14px 16px", background: dup ? "#fff5f5" : "#fafbfc" }}>
          {/* Hàng đầu: tuyến (chọn kho) + xóa */}
          <div style={{ display: "flex", alignItems: "flex-end", gap: 12, marginBottom: 12 }}>
            <div style={{ flex: 1, minWidth: 0 }}>
              {lbl(<>Tuyến · chọn Cảng &amp; Kho <span style={{ color: "var(--ink-4)", fontWeight: 400 }}>(cả chuỗi, vd Cảng → Kho → Kho → Cảng)</span>{dup && <span style={{ color: "var(--danger)", fontWeight: 700, marginLeft: 6 }}>· trùng tuyến</span>}</>)}
              <MultiCombo values={(r.route || "").split(/\s*-\s*/).filter(Boolean)} onChange={(arr) => set(i, { route: arr.join(" - ") })} groups={routeGroups} allowDup max={Infinity} placeholder="Chọn cảng/kho cho tuyến…" />
            </div>
            <button type="button" onClick={() => del(i)} title="Xóa tuyến"
              style={{ flexShrink: 0, width: 36, height: 36, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: "var(--ink-4)", cursor: "pointer" }}
              onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
              onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-4)"; }}><I.trash /></button>
          </div>
          {/* Phí cố định của tuyến */}
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(130px, 1fr))", gap: 10, marginBottom: 10 }}>
            <div>{lbl("Vé trạm")}<Money value={r.veTram} onChange={(x) => set(i, { veTram: x })} dim />{salChk("veTram")}</div>
            <div>{lbl("Tiền đường")}<Money value={r.tienDuong} onChange={(x) => set(i, { tienDuong: x })} dim />{salChk("tienDuong")}</div>
            <div>{lbl("Trợ cấp")}<Money value={r.troCap} onChange={(x) => set(i, { troCap: x })} dim />{salChk("troCap")}</div>
          </div>
          {/* Lương lái xe — 2 chiều: (CÓ/KHÔNG kéo cont ra) × (CRU/không CRU) = 4 mức */}
          <div style={{ border: "1px solid var(--line)", borderRadius: 10, padding: "11px 12px", marginBottom: 10, background: "#fff" }}>
            <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 9 }}>
              <i className="bi bi-cash-stack" style={{ color: "var(--accent)", fontSize: 13 }} />
              <span style={{ fontWeight: 700, fontSize: 12.5 }}>Lương lái xe</span>
              <span style={{ fontSize: 11, color: "var(--ink-4)" }}>theo <b>kéo cont ra</b> × <b>CRU</b></span>
              <span style={{ flex: 1 }} />
              {salChk("luong")}
            </div>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(230px, 1fr))", gap: 10 }}>
              {[
                { hd: "Có kéo cont ra", sub: "chuyến lấy/giao cont", cru: "luong", noCru: "luongNoCru" },
                { hd: "Không kéo cont ra", sub: "ra xe không kéo cont", cru: "luongNokeo", noCru: "luongNokeoNoCru" },
              ].map((grp) => (
                <div key={grp.cru} style={{ border: "1px solid var(--line-2)", borderRadius: 9, padding: "9px 10px", background: "#fafbfc" }}>
                  <div style={{ fontSize: 11.5, fontWeight: 700, color: "var(--ink-2)", marginBottom: 2 }}>{grp.hd}</div>
                  <div style={{ fontSize: 10, color: "var(--ink-4)", marginBottom: 7 }}>{grp.sub}</div>
                  <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8 }}>
                    <div>{lbl("CRU")}<Money value={r[grp.cru]} onChange={(x) => set(i, { [grp.cru]: x })} dim /></div>
                    <div>{lbl("Không CRU")}<Money value={r[grp.noCru]} onChange={(x) => set(i, { [grp.noCru]: x })} dim /></div>
                  </div>
                </div>
              ))}
            </div>
          </div>
          <div style={{ fontSize: 11, color: "var(--ink-4)", marginBottom: 10 }}>Lương chọn theo <b>2 điều kiện</b>: chuyến <b>có/không kéo cont ra</b> và lô <b>tích CRU</b> hay không. Tích <b style={{ color: "var(--accent)" }}>chi theo ngày</b> ở khoản nào → khoản đó tổng hợp trả cho lái xe theo từng chuyến ở <b>Lộ trình</b>. Dầu tính tiền = số lít × <b>giá dầu theo ngày</b> của chuyến.</div>
          {/* Định mức km & dầu — dầu có thể tích "chi theo ngày" (tính tiền theo Bảng giá dầu theo ngày) */}
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(130px, 1fr))", gap: 10 }}>
            <div>{lbl("Số km")}<Num value={r.km} onChange={(x) => set(i, { km: x })} suffix="km" /></div>
            <div>{lbl("Dầu 2 cầu")}<Num value={r.dau2} onChange={(x) => set(i, { dau2: x })} suffix="lít" />{salChk("dau2")}</div>
            <div>{lbl("Dầu 1 cầu")}<Num value={r.dau1} onChange={(x) => set(i, { dau1: x })} suffix="lít" />{salChk("dau1")}</div>
          </div>
        </div>
        );
      })}
      <button type="button" onClick={add}
        style={{ alignSelf: "flex-start", display: "inline-flex", alignItems: "center", gap: 7, padding: "9px 14px", fontSize: 13.5, fontWeight: 600, border: "1px dashed var(--accent)", borderRadius: 10, background: "var(--accent-weak-2)", color: "var(--accent)", cursor: "pointer" }}>
        <I.plus /> Thêm tuyến
      </button>
    </div>
  );
}

/* ===================== BẢNG GIÁ DẦU (repeater theo ngày) ===================== */

function FuelPrices({ rows = [], onChange }) {
  const isMobile = useIsMobile();
  const set = (i, np) => onChange(rows.map((r, j) => (j === i ? { ...r, ...np } : r)));
  const add = () => onChange([...(rows || []), { id: Date.now() + Math.random(), from: "", to: "", price: "", note: "" }]);
  const del = (i) => onChange(rows.filter((_, j) => j !== i));
  const lbl = (t) => <div style={{ fontSize: 11.5, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>{t}</div>;
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
      <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginBottom: 2, lineHeight: 1.5 }}>
        Đơn giá <b style={{ color: "var(--ink-3)" }}>đồng/lít</b> hiệu lực theo khoảng ngày. <b>Đến ngày</b> để trống = áp dụng <b>từ "Từ ngày" trở đi</b> (giá hiện hành); chọn 1 ngày thì đặt Từ = Đến.
      </div>
      {(rows || []).length === 0 && <div style={{ padding: "12px 2px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có mốc giá dầu nào — bấm <b>+ Thêm mốc giá</b>.</div>}
      {(rows || []).map((r, i) => (
        <div key={r.id || i} style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr 1fr" : "150px 150px 160px 1fr 34px", gap: 10, alignItems: "end", border: "1px solid var(--line)", borderRadius: 10, padding: "10px 12px", background: "#fafbfc" }}>
          <div>{lbl("Từ ngày")}<DateField value={r.from} onChange={(x) => set(i, { from: x })} /></div>
          <div>{lbl(<>Đến ngày <span style={{ color: "var(--ink-4)", fontWeight: 400 }}>(tùy chọn)</span></>)}<DateField value={r.to} onChange={(x) => set(i, { to: x })} /></div>
          <div>{lbl("Đơn giá (đ/lít)")}<Money value={r.price} onChange={(x) => set(i, { price: x })} /></div>
          <div>{lbl("Ghi chú")}<Txt value={r.note} onChange={(x) => set(i, { note: x })} placeholder="VD: giá tháng 5/2026" /></div>
          <button type="button" onClick={() => del(i)} title="Xóa mốc giá"
            style={{ width: 34, height: 38, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: "var(--ink-4)", cursor: "pointer" }}
            onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
            onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-4)"; }}><I.trash /></button>
        </div>
      ))}
      <button type="button" onClick={add}
        style={{ alignSelf: "flex-start", display: "inline-flex", alignItems: "center", gap: 7, padding: "9px 14px", fontSize: 13.5, fontWeight: 600, border: "1px dashed var(--accent)", borderRadius: 10, background: "var(--accent-weak-2)", color: "var(--accent)", cursor: "pointer" }}>
        <I.plus /> Thêm mốc giá
      </button>
    </div>
  );
}
/* ===================== CUSTOMER MANAGER (master-detail) ===================== */

const CUST_FIELDS = [
  { k: "shortName", label: "Tên viết tắt", ph: "VD: Canon" },
  { k: "taxCode", label: "Mã số thuế", ph: "VD: 0101234567" },
  { k: "phone", label: "Điện thoại", ph: "VD: 024 1234 5678" },
  { k: "contact", label: "Người liên hệ", ph: "VD: Chị Hồng — KT" },
  { k: "email", label: "Email", ph: "VD: ketoan@canon.vn" },
];

function CustomerManager({ cfg, setCfg }) {
  const isMobile = useIsMobile();
  const customers = cfg.customers || [];
  const info = cfg.customerInfo || {};
  const [sel, setSel] = useState(customers[0] || null);
  const [draft, setDraft] = useState("");
  const cur = sel != null && customers.includes(sel) ? sel : (customers[0] || null);
  const data = (cur && info[cur]) || {};
  const setField = (k, v) => setCfg("customerInfo", { ...info, [cur]: { ...data, [k]: v } });
  const T = window.__TRK || {}; const ROUTES = T.routes || {};
  const [nameDraft, setNameDraft] = useState(cur || "");
  React.useEffect(() => { setNameDraft(cur || ""); }, [cur]);
  // Chuẩn hóa tên: nếu gõ HOA hết hoặc THƯỜNG hết → Title Case (hoa chữ đầu mỗi từ).
  // Nếu đã canh hoa/thường LẪN (vd "Wolong Electric VN") → giữ nguyên, không phá viết tắt.
  const smartName = (s) => {
    const str = (s || "").trim().replace(/\s+/g, " ");
    if (!str) return "";
    const up = str.toLocaleUpperCase("vi"), lo = str.toLocaleLowerCase("vi");
    if (str !== up && str !== lo) return str;
    return str.split(" ").map((w) => (w ? w.charAt(0).toLocaleUpperCase("vi") + w.slice(1).toLocaleLowerCase("vi") : w)).join(" ");
  };
  const dupName = (n, exclude) => customers.some((c) => c !== exclude && c.toLowerCase() === n.toLowerCase());
  // Đổi tên khách (server update theo id — giữ liên kết lô & bảng giá), rồi rekey cfg cục bộ
  const renameCustomer = async () => {
    if (!cur) return;
    const nn = smartName(nameDraft);
    if (!nn) { window.trkToast && window.trkToast("Tên khách hàng không được để trống", "error"); return; }
    if (nn === cur) { setNameDraft(cur); return; }
    if (dupName(nn, cur)) { window.trkToast && window.trkToast("Tên khách hàng đã tồn tại", "error"); return; }
    if (!ROUTES.customerRename) return;
    try {
      const res = await fetch(ROUTES.customerRename, { method: "PUT", headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": T.csrf }, body: JSON.stringify({ old: cur, new: nn }) }).then((r) => r.json());
      if (res && res.ok) {
        setCfg("customers", customers.map((c) => (c === cur ? nn : c)));
        const ni = { ...info }; ni[nn] = ni[cur] || {}; if (nn !== cur) delete ni[cur]; setCfg("customerInfo", ni);
        setSel(nn); setNameDraft(nn);
        window.trkToast && window.trkToast("Đã đổi tên khách hàng");
      } else { window.trkToast && window.trkToast((res && res.message) || "Đổi tên lỗi", "error"); }
    } catch (e) { window.trkToast && window.trkToast("Lỗi kết nối khi đổi tên", "error"); }
  };
  const add = () => {
    const n = smartName(draft);
    if (!n) { window.trkToast && window.trkToast("Vui lòng nhập tên khách hàng", "error"); return; }
    if (dupName(n)) { window.trkToast && window.trkToast(`Khách hàng "${n}" đã tồn tại`, "error"); return; }
    setCfg("customers", [...customers, n]); setSel(n); setDraft("");
  };
  const remove = (name) => {
    setCfg("customers", customers.filter((c) => c !== name));
    const ni = { ...info }; delete ni[name]; setCfg("customerInfo", ni);
    if (cur === name) setSel(customers.filter((c) => c !== name)[0] || null);
  };
  const inp = (val, onCh, ph) => (
    <input value={val || ""} onChange={(e) => onCh(e.target.value)} placeholder={ph}
      style={{ width: "100%", padding: "8px 11px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
      onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
  );
  return (
    <div style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr" : "176px 1fr", gap: 16, minHeight: isMobile ? 0 : 360 }}>
      {/* customer list */}
      <div style={{ borderRight: isMobile ? "none" : "1px solid var(--line-2)", borderBottom: isMobile ? "1px solid var(--line-2)" : "none", paddingRight: isMobile ? 0 : 12, paddingBottom: isMobile ? 12 : 0, display: "flex", flexDirection: "column", minHeight: 0 }}>
        <div style={{ display: "flex", gap: 6, marginBottom: 8 }}>
          <input value={draft} onChange={(e) => setDraft(e.target.value)} placeholder="Tên khách hàng *…"
            onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); add(); } }}
            style={{ flex: 1, minWidth: 0, padding: "7px 9px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, outline: "none" }}
            onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => { e.target.style.borderColor = "var(--line)"; if (draft.trim()) setDraft(smartName(draft)); }} />
          <button type="button" onClick={add} title="Thêm khách hàng"
            style={{ width: 32, flexShrink: 0, display: "grid", placeItems: "center", border: "none", borderRadius: 8, background: "var(--accent)", color: "#fff", cursor: "pointer" }}><I.plus /></button>
        </div>
        <div style={{ overflowY: "auto", display: "flex", flexDirection: "column", gap: 1 }}>
          {customers.map((name) => {
            const active = cur === name;
            return (
              <button key={name} type="button" onClick={() => setSel(name)}
                style={{ textAlign: "left", border: "none", cursor: "pointer", borderRadius: 8, padding: "8px 10px", fontSize: 13.5, fontWeight: active ? 600 : 400,
                  background: active ? "var(--accent-weak)" : "transparent", color: active ? "var(--accent)" : "var(--ink)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}
                onMouseEnter={(e) => { if (!active) e.currentTarget.style.background = "var(--line-2)"; }}
                onMouseLeave={(e) => { if (!active) e.currentTarget.style.background = "transparent"; }}>
                {name}
              </button>
            );
          })}
          {!customers.length && <div style={{ padding: "16px 4px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có khách hàng.</div>}
        </div>
      </div>

      {/* detail */}
      {cur ? (
        <div>
          <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 6 }}>
            <div style={{ fontSize: 11, fontWeight: 700, color: "var(--ink-4)", textTransform: "uppercase", letterSpacing: "0.04em" }}>Tên khách hàng <span style={{ color: "var(--danger)" }}>*</span></div>
            <div style={{ flex: 1 }} />
            <button type="button" onClick={() => remove(cur)} title="Xóa khách hàng"
              style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "6px 11px", fontSize: 12.5, fontWeight: 500, border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-3)", cursor: "pointer" }}
              onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; e.currentTarget.style.borderColor = "#f3c9c9"; }}
              onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-3)"; e.currentTarget.style.borderColor = "var(--line)"; }}>
              <I.trash /> Xóa
            </button>
          </div>
          <div style={{ display: "flex", gap: 8, alignItems: "center", marginBottom: 14 }}>
            <input value={nameDraft} onChange={(e) => setNameDraft(e.target.value)} placeholder="Tên khách hàng…"
              onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); renameCustomer(); } }}
              style={{ flex: 1, padding: "9px 12px", fontSize: 15, fontWeight: 700, border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
              onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => { e.target.style.borderColor = "var(--line)"; if (nameDraft.trim()) setNameDraft(smartName(nameDraft)); }} />
            {(() => { const can = !!(nameDraft && nameDraft.trim() && nameDraft.trim() !== cur); return (
              <button type="button" onClick={renameCustomer} disabled={!can}
                style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "9px 16px", fontSize: 13.5, fontWeight: 600, border: "none", borderRadius: 10, whiteSpace: "nowrap", cursor: can ? "pointer" : "default", color: can ? "#fff" : "var(--ink-4)", background: can ? "var(--accent)" : "var(--line-2)" }}>
                <I.check /> Cập nhật tên
              </button>
            ); })()}
          </div>
          <div style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr" : "1fr 1fr", gap: 12 }}>
            {CUST_FIELDS.map((f) => (
              <Field key={f.k} label={f.label}>{inp(data[f.k], (v) => setField(f.k, v), f.ph)}</Field>
            ))}
            <Field label="Hạn thanh toán mặc định">
              <div style={{ position: "relative", width: 130 }}>
                <input inputMode="numeric" value={data.termDays || ""} onChange={(e) => setField("termDays", e.target.value.replace(/[^\d]/g, ""))} placeholder="VD: 30" className="tnum"
                  style={{ width: "100%", padding: "8px 38px 8px 11px", fontSize: 13.5, textAlign: "right", border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
                  onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
                <span style={{ position: "absolute", right: 10, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 12.5, pointerEvents: "none" }}>ngày</span>
              </div>
            </Field>
          </div>
          <div style={{ marginTop: 12 }}>
            <Field label="Địa chỉ">{inp(data.address, (v) => setField("address", v), "Địa chỉ xuất hóa đơn…")}</Field>
          </div>
          <div style={{ marginTop: 12 }}>
            <Field label="Ghi chú">
              <textarea value={data.note || ""} onChange={(e) => setField("note", e.target.value)} placeholder="Ghi chú về khách hàng, điều khoản riêng…" rows={3}
                style={{ width: "100%", padding: "8px 11px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 9, outline: "none", resize: "vertical", fontFamily: "inherit" }}
                onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
            </Field>
          </div>
          <div style={{ marginTop: 14, fontSize: 11.5, color: "var(--ink-4)" }}>Tên khách hàng là khóa liên kết với lô hàng. Bảng giá đã gửi quản lý ở trang <b style={{ color: "var(--ink-3)" }}>Bảng giá</b>.</div>
        </div>
      ) : (
        <div style={{ display: "grid", placeItems: "center", color: "var(--ink-4)", fontSize: 13.5 }}>Chọn hoặc thêm một khách hàng để xem chi tiết.</div>
      )}
    </div>
  );
}


/* ===================== HỒ SƠ LÁI XE (master-detail) ===================== */

// Thâm niên (số năm/tháng) tính từ ngày vào công ty → hiện tại. Không nhập tay.
function tenureLabel(joined) {
  if (!joined) return "";
  const p = joined.split("-").map(Number); const y = p[0], m = p[1], d = p[2];
  if (!y || !m) return "";
  const now = new Date();
  let months = (now.getFullYear() - y) * 12 + (now.getMonth() + 1 - m);
  if (now.getDate() < (d || 1)) months -= 1;
  if (months < 0) return "chưa tới ngày vào làm";
  const yy = Math.floor(months / 12), mm = months % 12;
  return (yy ? `${yy} năm ` : "") + `${mm} tháng` + ` (${months} tháng)`;
}

const DOC_TYPES = ["CCCD mặt trước", "CCCD mặt sau", "Bằng lái xe", "Khác"];

function DriversManager({ cfg, setCfg }) {
  const isMobile = useIsMobile();
  const drivers = cfg.drivers || [];
  const [sel, setSel] = useState(0);
  const [draft, setDraft] = useState("");
  const [docType, setDocType] = useState("Khác");   // mặc định "Khác" — user chủ động chọn loại đúng
  const [busy, setBusy] = useState(false);
  const T = window.__TRK || {}; const ROUTES = T.routes || {};
  const fileRef = useRef(null);
  const idx = sel < drivers.length ? sel : (drivers.length ? 0 : -1);
  const cur = idx >= 0 ? drivers[idx] : null;

  const setDriver = (np) => setCfg("drivers", drivers.map((d, j) => (j === idx ? { ...d, ...np } : d)));
  const add = () => {
    const n = (draft || "").trim() || "Lái xe mới";
    setCfg("drivers", [...drivers, { name: n, phones: [], birthday: "", joinedDate: "", banks: [], docs: [] }]);
    setSel(drivers.length); setDraft("");
  };
  const remove = (j) => { setCfg("drivers", drivers.filter((_, k) => k !== j)); setSel(0); };

  // phones repeater
  const setPhone = (i, v) => setDriver({ phones: (cur.phones || []).map((p, k) => (k === i ? v : p)) });
  const addPhone = () => setDriver({ phones: [...(cur.phones || []), ""] });
  const delPhone = (i) => setDriver({ phones: (cur.phones || []).filter((_, k) => k !== i) });
  // banks repeater
  const setBank = (i, np) => setDriver({ banks: (cur.banks || []).map((b, k) => (k === i ? { ...b, ...np } : b)) });
  const addBank = () => setDriver({ banks: [...(cur.banks || []), { bank: "", number: "", holder: cur.name || "" }] });
  const delBank = (i) => setDriver({ banks: (cur.banks || []).filter((_, k) => k !== i) });

  // upload tài liệu (cần lái xe đã lưu → có id)
  const onPickFiles = async (e) => {
    const files = Array.from(e.target.files || []); e.target.value = "";
    if (!files.length || !cur) return;
    if (!cur.id) { window.trkToast && window.trkToast("Hãy bấm Lưu để tạo lái xe trước khi tải tài liệu", "error"); return; }
    const fd = new FormData(); files.forEach((f) => fd.append("files[]", f)); fd.append("type", docType);
    setBusy(true);
    try {
      const res = await window.trkUpload("POST", ROUTES.driversBase + (cur.hashid || cur.id) + "/docs", fd);
      if (res && res.ok) { setDriver({ docs: res.docs }); window.trkToast && window.trkToast(`Đã tải ${files.length} tài liệu`); }
      else window.trkToast && window.trkToast((res && res.message) || "Tải lên thất bại", "error");
    } catch (err) { window.trkToast && window.trkToast("Lỗi kết nối khi tải lên", "error"); }
    setBusy(false);
  };
  const delDoc = async (attId) => {
    if (!cur || !cur.id) return;
    const ok = await window.confirmAction({ title: "Xóa tài liệu?", text: "Tài liệu này sẽ bị xóa vĩnh viễn.", confirmText: '<i class="bi bi-trash me-1"></i> Xóa', danger: true });
    if (!ok) return;
    try { const res = await window.trkApi("DELETE", ROUTES.driversBase + (cur.hashid || cur.id) + "/docs/" + attId); if (res && res.ok) setDriver({ docs: res.docs }); } catch (e) {}
  };

  const lbl = (t) => <div style={{ fontSize: 11.5, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>{t}</div>;
  const delBtn = (onClick, title) => (
    <button type="button" onClick={onClick} title={title} style={{ width: 32, height: 32, flexShrink: 0, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-4)", cursor: "pointer" }}
      onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
      onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-4)"; }}><I.trash /></button>
  );

  return (
    <div style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr" : "200px 1fr", gap: 16, minHeight: isMobile ? 0 : 380 }}>
      {/* danh sách lái xe */}
      <div style={{ borderRight: isMobile ? "none" : "1px solid var(--line-2)", borderBottom: isMobile ? "1px solid var(--line-2)" : "none", paddingRight: isMobile ? 0 : 12, paddingBottom: isMobile ? 12 : 0, display: "flex", flexDirection: "column", minHeight: 0 }}>
        <div style={{ display: "flex", gap: 6, marginBottom: 8 }}>
          <input value={draft} onChange={(e) => setDraft(e.target.value)} placeholder="Thêm lái xe…"
            onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); add(); } }}
            style={{ flex: 1, minWidth: 0, padding: "7px 9px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, outline: "none" }} />
          <button type="button" onClick={add} title="Thêm lái xe" style={{ width: 32, flexShrink: 0, display: "grid", placeItems: "center", border: "none", borderRadius: 8, background: "var(--accent)", color: "#fff", cursor: "pointer" }}><I.plus /></button>
        </div>
        <div style={{ overflowY: "auto", display: "flex", flexDirection: "column", gap: 1 }}>
          {drivers.map((d, j) => {
            const active = idx === j;
            return (
              <button key={j} type="button" onClick={() => setSel(j)}
                style={{ textAlign: "left", border: "none", cursor: "pointer", borderRadius: 8, padding: "8px 10px", background: active ? "var(--accent-weak)" : "transparent", color: active ? "var(--accent)" : "var(--ink)" }}
                onMouseEnter={(e) => { if (!active) e.currentTarget.style.background = "var(--line-2)"; }}
                onMouseLeave={(e) => { if (!active) e.currentTarget.style.background = "transparent"; }}>
                <div style={{ fontSize: 13.5, fontWeight: active ? 600 : 400, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{d.name || "(chưa đặt tên)"}</div>
                <div style={{ fontSize: 11.5, color: "var(--ink-4)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }} className="tnum">{(d.phones || [])[0] || "—"}{!d.id && " · chưa lưu"}</div>
              </button>
            );
          })}
          {!drivers.length && <div style={{ padding: "16px 4px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có lái xe.</div>}
        </div>
      </div>

      {/* chi tiết */}
      {cur ? (
        <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
          <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
            <input value={cur.name || ""} onChange={(e) => setDriver({ name: e.target.value })} placeholder="Tên lái xe…"
              style={{ flex: 1, padding: "9px 12px", fontSize: 15, fontWeight: 700, border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
              onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
            {delBtn(() => remove(idx), "Xóa lái xe")}
          </div>

          {/* SĐT (nhiều) */}
          <div>
            {lbl("Số điện thoại")}
            <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
              {(cur.phones || []).map((p, i) => (
                <div key={i} style={{ display: "flex", gap: 8 }}>
                  <Txt value={p} onChange={(v) => setPhone(i, v)} placeholder="VD: 09xx xxx xxx" />
                  {delBtn(() => delPhone(i), "Xóa số")}
                </div>
              ))}
              <button type="button" onClick={addPhone} style={{ alignSelf: "flex-start", display: "inline-flex", alignItems: "center", gap: 6, padding: "5px 10px", fontSize: 12.5, fontWeight: 600, border: "none", borderRadius: 7, background: "var(--accent-weak)", color: "var(--accent)", cursor: "pointer" }}><I.plus /> Thêm số điện thoại</button>
            </div>
          </div>

          {/* ngày sinh + ngày vào + thâm niên */}
          <div style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr 1fr" : "1fr 1fr 1fr", gap: 12, alignItems: "end" }}>
            <div>{lbl("Ngày sinh")}<DateField value={cur.birthday} onChange={(v) => setDriver({ birthday: v })} /></div>
            <div>{lbl("Ngày vào công ty")}<DateField value={cur.joinedDate} onChange={(v) => setDriver({ joinedDate: v })} /></div>
            <div>{lbl("Thâm niên (tự tính)")}<div style={{ padding: "8px 11px", fontSize: 13.5, fontWeight: 600, color: cur.joinedDate ? "var(--accent)" : "var(--ink-4)", background: "var(--bg)", border: "1px solid var(--line)", borderRadius: 9 }} className="tnum">{tenureLabel(cur.joinedDate) || "—"}</div></div>
          </div>

          {/* tài khoản ngân hàng (nhiều) */}
          <div>
            {lbl("Tài khoản ngân hàng")}
            <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
              {(cur.banks || []).map((b, i) => (
                <div key={i} style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr 1fr 32px" : "1fr 1.2fr 1fr 32px", gap: 8, alignItems: "center" }}>
                  <Txt value={b.bank} onChange={(v) => setBank(i, { bank: v })} placeholder="Ngân hàng (VD: VCB)" />
                  <Txt value={b.number} onChange={(v) => setBank(i, { number: v })} placeholder="Số tài khoản" />
                  <Txt value={b.holder} onChange={(v) => setBank(i, { holder: v })} placeholder="Chủ tài khoản" />
                  {delBtn(() => delBank(i), "Xóa tài khoản")}
                </div>
              ))}
              <button type="button" onClick={addBank} style={{ alignSelf: "flex-start", display: "inline-flex", alignItems: "center", gap: 6, padding: "5px 10px", fontSize: 12.5, fontWeight: 600, border: "none", borderRadius: 7, background: "var(--accent-weak)", color: "var(--accent)", cursor: "pointer" }}><I.plus /> Thêm tài khoản</button>
            </div>
          </div>

          {/* tài liệu (CCCD / bằng lái) */}
          <div>
            {lbl("Tài liệu (CCCD, bằng lái — ảnh hoặc file)")}
            {!cur.id && <div style={{ fontSize: 12, color: "var(--warn)", marginBottom: 6 }}>Bấm <b>Lưu mục này</b> để tạo lái xe trước, rồi mới tải tài liệu lên.</div>}
            <div style={{ display: "flex", gap: 8, alignItems: "center", marginBottom: 8, flexWrap: "wrap" }}>
              <select value={docType} onChange={(e) => setDocType(e.target.value)} style={{ padding: "7px 10px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, background: "#fff" }}>
                {DOC_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
              </select>
              <input ref={fileRef} type="file" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv" onChange={onPickFiles} style={{ display: "none" }} />
              <button type="button" onClick={() => fileRef.current && fileRef.current.click()} disabled={!cur.id || busy}
                style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 12px", fontSize: 13, fontWeight: 600, border: "1px dashed var(--accent)", borderRadius: 9, background: "var(--accent-weak-2)", color: cur.id ? "var(--accent)" : "var(--ink-4)", cursor: cur.id && !busy ? "pointer" : "default" }}>
                <I.plus /> {busy ? "Đang tải…" : "Chọn nhiều file/ảnh"}
              </button>
            </div>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(140px, 1fr))", gap: 10 }}>
              {(cur.docs || []).map((doc, di) => (
                <div key={di} style={{ border: "1px solid var(--line)", borderRadius: 10, overflow: "hidden", background: "#fafbfc" }}>
                  <a href={doc.url} target="_blank" rel="noreferrer" style={{ height: 96, background: "#fff", display: "grid", placeItems: "center", overflow: "hidden" }}>
                    {doc.isImage
                      ? <img src={doc.url} alt={doc.name} style={{ width: "100%", height: "100%", objectFit: "cover" }} />
                      : <span style={{ fontSize: 30, color: "var(--ink-4)" }}><i className="bi bi-file-earmark-text" /></span>}
                  </a>
                  <div style={{ padding: "6px 8px", display: "flex", alignItems: "center", gap: 6 }}>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ fontSize: 11, fontWeight: 600, color: "var(--accent)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{doc.type}</div>
                      <div style={{ fontSize: 10.5, color: "var(--ink-4)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }} title={doc.name}>{doc.name}</div>
                    </div>
                    <button type="button" onClick={() => delDoc(doc.id)} title="Xóa tài liệu" style={{ width: 24, height: 24, flexShrink: 0, display: "grid", placeItems: "center", border: "none", borderRadius: 6, background: "transparent", color: "var(--ink-4)", cursor: "pointer" }}
                      onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
                      onMouseLeave={(e) => { e.currentTarget.style.background = "transparent"; e.currentTarget.style.color = "var(--ink-4)"; }}><I.trash /></button>
                  </div>
                </div>
              ))}
              {!(cur.docs || []).length && <div style={{ gridColumn: "1 / -1", padding: "14px 4px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có tài liệu nào.</div>}
            </div>
          </div>
        </div>
      ) : (
        <div style={{ display: "grid", placeItems: "center", color: "var(--ink-4)", fontSize: 13.5 }}>Chọn hoặc thêm một lái xe để xem hồ sơ.</div>
      )}
    </div>
  );
}

function ConfigBody({ cfg, setCfg, sel, setSel, dirty, saving, onSave, dirtyMap, counts = {}, loading = false }) {
  const isMobile = useIsMobile();
  const [draft, setDraft] = useState("");
  const [codeDraft, setCodeDraft] = useState("");   // ô "ký hiệu" cho khu thêm dạng nhóm (Địa điểm)
  const list = cfg[sel] || [];
  const locked = new Set(cfg.locationLocked || []);
  const g = CFG_GROUPS.find((x) => x.key === sel);
  const prices = cfg.prices || {};
  const setPrice = (name, val) => setCfg("prices", { ...prices, [name]: val });
  const vehType = cfg.vehicleType || {};
  const setVehType = (name, val) => setCfg("vehicleType", { ...vehType, [name]: val });
  const vehAxle = cfg.vehicleAxle || {};   // số cầu xe MBF: "1" | "2" (link Phí tuyến đường)
  const setVehAxle = (name, val) => setCfg("vehicleAxle", { ...vehAxle, [name]: val });
  const vehGps = cfg.vehicleGps || {};     // liên kết xe GPS: plate => "provider:deviceId"
  const setVehGps = (name, val) => { const m = { ...vehGps }; if (val) m[name] = val; else delete m[name]; setCfg("vehicleGps", m); };
  const gpsVehicles = cfg.gpsVehicles || [];   // danh sách xe GPS để chọn (từ catalogData)
  const codeKey = (g && g.codeKey) || "locationCode";
  // Mã (ký hiệu) lưu theo CHỈ SỐ dòng → tên được phép trùng, chỉ mã là định danh duy nhất.
  const codeArrKey = codeKey + "Arr";
  const codeArr = cfg[codeArrKey] || [];
  const setCode = (i, val) => { const a = [...codeArr]; while (a.length < list.length) a.push(""); a[i] = val; setCfg(codeArrKey, a); };
  // ID dòng theo CHỈ SỐ (để reconcile khớp theo id → SỬA mã giữ nguyên id, không đứt link). Mục tự thêm = null.
  const idArrKey = sel + "IdArr";
  const idArr = cfg[idArrKey] || [];
  // Địa chỉ kho (chỉ danh mục addressed = Kho) — cũng lưu theo CHỈ SỐ dòng như mã.
  const addrArrKey = "warehouseAddrArr";
  const addrArr = cfg[addrArrKey] || [];
  const setAddr = (i, val) => { const a = [...addrArr]; while (a.length < list.length) a.push(""); a[i] = val; setCfg(addrArrKey, a); };
  // Tọa độ kho "lat,lng" (chỉ danh mục geo = Kho) — lưu theo CHỈ SỐ dòng; ghim qua MapPicker.
  const geoArrKey = "warehouseGeoArr";
  const geoArr = cfg[geoArrKey] || [];
  const setGeo = (i, val) => { const a = [...geoArr]; while (a.length < list.length) a.push(""); a[i] = val; setCfg(geoArrKey, a); };
  const [pickIdx, setPickIdx] = useState(null);   // dòng đang mở MapPicker
  const mapsKey = (window.__TRK && window.__TRK.boot && window.__TRK.boot.mapsKey) || "";
  const parseGeo = (s) => { const m = /(-?\d+(?:\.\d+)?)\s*[,;\s]\s*(-?\d+(?:\.\d+)?)/.exec(s || ""); return m ? { lat: parseFloat(m[1]), lng: parseFloat(m[2]) } : null; };
  // Phát hiện trùng ký hiệu (chuẩn hóa hoa + bỏ khoảng trắng)
  const normCode = (c) => (c || "").toString().trim().toUpperCase();
  const codeCounts = {};
  const allowDup = !!(g && g.allowDupCode);   // Địa điểm: cho phép nhiều TÊN dùng chung 1 ký hiệu → không chặn trùng mã
  if (g && g.coded && !allowDup) list.forEach((_, i) => { const c = normCode(codeArr[i]); if (c) codeCounts[c] = (codeCounts[c] || 0) + 1; });
  const isDupCode = (i) => { if (allowDup) return false; const c = normCode(codeArr[i]); return !!c && codeCounts[c] > 1; };
  const hasDupCode = !!(g && g.coded) && !allowDup && Object.values(codeCounts).some((n) => n > 1);
  // Phí tuyến đường: phát hiện trùng TUYẾN — THEO CHIỀU (Kho1→Kho2 ≠ Kho2→Kho1, giữ thứ tự kho)
  const routeKey = (s) => (s || "").split(/\s*-\s*/).map((x) => x.trim().toUpperCase()).filter(Boolean).join(" | ");
  const rfRows = cfg.routeFees || [];
  const rfCounts = {};
  if (g && g.routefees) rfRows.forEach((r) => { const k = routeKey(r.route); if (k) rfCounts[k] = (rfCounts[k] || 0) + 1; });
  const isDupRoute = (s) => { const k = routeKey(s); return !!k && rfCounts[k] > 1; };
  const hasDupRoute = !!(g && g.routefees) && Object.values(rfCounts).some((n) => n > 1);
  // Gán xe GPS: 1 xe GPS chỉ được gán cho 1 xe MBF — phát hiện trùng ref.
  const gpsUsedBy = {};   // ref => [plate...] (xe nào đang gán ref này)
  if (g && g.fleet) Object.keys(vehGps).forEach((plate) => { const r = vehGps[plate]; if (r && (vehType[plate] || "MBF") === "MBF") (gpsUsedBy[r] = gpsUsedBy[r] || []).push(plate); });
  const isDupGps = (plate) => { const r = vehGps[plate]; return !!r && (gpsUsedBy[r] || []).length > 1; };
  const hasDupGps = !!(g && g.fleet) && Object.values(gpsUsedBy).some((a) => a.length > 1);
  const blockSave = hasDupCode || hasDupRoute || hasDupGps;   // chặn lưu khi còn trùng
  const costColors = cfg.costColors || {};
  const setColor = (name, val) => { const nc = { ...costColors }; if (val) nc[name] = val; else delete nc[name]; setCfg("costColors", nc); };
  const vatDefault = cfg.vatDefault || { hph: "8", icd: "0" };
  const setVat = (k, val) => setCfg("vatDefault", { ...vatDefault, [k]: val.replace(/[^\d.]/g, "") });
  const setVatAll = (val) => { const v = val.replace(/[^\d.]/g, ""); setCfg("vatDefault", { hph: v, icd: v }); };
  const addItem = () => {
    const v = draft.trim();
    if (!v) { setDraft(""); return; }
    // Danh mục CÓ MÃ (địa điểm/kho): cho phép trùng TÊN. Danh mục khác: vẫn chặn trùng tên.
    if (!(g && g.coded) && list.includes(v)) { setDraft(""); return; }
    setCfg(sel, [...list, v]);
    if (g && g.coded) {
      const a = [...codeArr]; while (a.length < list.length) a.push(""); a.push(""); setCfg(codeArrKey, a);
      const ia = [...idArr]; while (ia.length < list.length) ia.push(null); ia.push(null); setCfg(idArrKey, ia);   // mục mới: chưa có id
    }
    if (g && g.addressed) { const a = [...addrArr]; while (a.length < list.length) a.push(""); a.push(""); setCfg(addrArrKey, a); }
    if (g && g.geo) { const a = [...geoArr]; while (a.length < list.length) a.push(""); a.push(""); setCfg(geoArrKey, a); }
    setDraft("");
  };
  // Thêm 1 dòng đã biết KÝ HIỆU (dùng cho giao diện gom nhóm — Địa điểm): mỗi ký hiệu có thể nhiều tên.
  const addRow = (code, name) => {
    setCfg(sel, [...list, (name || "").trim()]);
    const a = [...codeArr]; while (a.length < list.length) a.push(""); a.push((code || "").trim()); setCfg(codeArrKey, a);
    const ia = [...idArr]; while (ia.length < list.length) ia.push(null); ia.push(null); setCfg(idArrKey, ia);   // dòng mới: chưa có id
  };
  // Đổi ký hiệu cho TẤT CẢ dòng trong 1 nhóm (sửa ở header nhóm → áp cho mọi tên cùng nhóm).
  const setGroupCode = (indices, code) => {
    const a = [...codeArr]; while (a.length < list.length) a.push(""); indices.forEach((i) => { a[i] = code; }); setCfg(codeArrKey, a);
  };
  // Đổi tên: các map gắn THEO TÊN (đơn giá/màu/loại xe) phải chuyển sang tên mới, không mất.
  // Riêng MÃ (coded) lưu theo chỉ số → đổi tên không ảnh hưởng mã.
  const rekey = (mapKey, map, old, v) => { if (map[old] === undefined) return; const m = { ...map }; m[v] = m[old]; delete m[old]; setCfg(mapKey, m); };
  const rename = (i, v) => {
    const old = list[i]; const next = [...list]; next[i] = v; setCfg(sel, next);
    if (v === old) return;
    if (g && g.priced)  rekey("prices", prices, old, v);
    if (g && g.colored) rekey("costColors", costColors, old, v);
    if (g && g.fleet) { rekey("vehicleType", vehType, old, v); rekey("vehicleAxle", vehAxle, old, v); rekey("vehicleGps", vehGps, old, v); }
  };
  const remove = (i) => {
    const old = list[i]; setCfg(sel, list.filter((_, j) => j !== i));
    if (g && g.coded) { setCfg(codeArrKey, codeArr.filter((_, j) => j !== i)); setCfg(idArrKey, idArr.filter((_, j) => j !== i)); }
    if (g && g.addressed) setCfg(addrArrKey, addrArr.filter((_, j) => j !== i));
    if (g && g.geo) setCfg(geoArrKey, geoArr.filter((_, j) => j !== i));
    const drop = (mapKey, map) => { if (map[old] === undefined) return; const m = { ...map }; delete m[old]; setCfg(mapKey, m); };
    if (g && g.priced)  drop("prices", prices);
    if (g && g.colored) drop("costColors", costColors);
    if (g && g.fleet) { drop("vehicleType", vehType); drop("vehicleAxle", vehAxle); drop("vehicleGps", vehGps); }
  };
  return (
      <div style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr" : "210px 1fr", gap: isMobile ? 12 : 18, padding: "14px 0 4px", minHeight: isMobile ? 0 : 380 }}>
        {/* group list — dọc/sticky trên desktop; thanh pill cuộn ngang trên mobile */}
        <div style={{ display: "flex", flexDirection: isMobile ? "row" : "column", gap: isMobile ? 7 : 2,
          borderRight: isMobile ? "none" : "1px solid var(--line-2)", borderBottom: isMobile ? "1px solid var(--line-2)" : "none",
          paddingRight: isMobile ? 0 : 14, paddingBottom: isMobile ? 12 : 0,
          position: isMobile ? "static" : "sticky", top: 8, alignSelf: "start",
          maxHeight: isMobile ? "none" : "calc(100vh - 150px)", overflowY: isMobile ? "visible" : "auto", overflowX: isMobile ? "auto" : "visible", flexWrap: "nowrap" }}>
          {CFG_GROUPS.map((grp) => {
            const active = sel === grp.key;
            return (
              <button key={grp.key} type="button" onClick={() => { setSel(grp.key); setDraft(""); }}
                style={{ textAlign: "left", border: isMobile ? "1px solid var(--line)" : "none", cursor: "pointer", borderRadius: isMobile ? 999 : 9, padding: "9px 11px", flexShrink: isMobile ? 0 : undefined, whiteSpace: isMobile ? "nowrap" : undefined,
                  background: active ? "var(--accent-weak)" : (isMobile ? "#fff" : "transparent"), transition: "background .12s" }}
                onMouseEnter={(e) => { if (!active) e.currentTarget.style.background = "var(--line-2)"; }}
                onMouseLeave={(e) => { if (!active) e.currentTarget.style.background = "transparent"; }}>
                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 8 }}>
                  <span style={{ display: "inline-flex", alignItems: "center", gap: 6, fontSize: 13.5, fontWeight: 600, color: active ? "var(--accent)" : "var(--ink)" }}>
                    {grp.label}
                    {dirtyMap && dirtyMap[grp.key] && <span title="Chưa lưu" style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} />}
                  </span>
                  {!grp.general && <span className="tnum" style={{ fontSize: 11.5, fontWeight: 600, color: active ? "var(--accent)" : "var(--ink-4)", background: active ? "#fff" : "var(--line-2)", padding: "1px 7px", borderRadius: 999 }}>{counts[grp.key] != null ? counts[grp.key] : (cfg[grp.key] || []).length}</span>}
                </div>
              </button>
            );
          })}
        </div>
        {/* items editor */}
        <div>
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 12, marginBottom: 6 }}>
            <div style={{ fontSize: 15, fontWeight: 700, letterSpacing: "-0.01em" }}>{g.label}</div>
            <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
              {dirty
                ? <span style={{ display: "inline-flex", alignItems: "center", gap: 6, fontSize: 12, fontWeight: 600, color: "var(--warn)" }}><span style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} /> Chưa lưu</span>
                : <span style={{ display: "inline-flex", alignItems: "center", gap: 5, fontSize: 12, fontWeight: 600, color: "var(--good)" }}><I.check /> Đã lưu</span>}
              <button type="button" onClick={onSave} disabled={!dirty || saving || blockSave}
                title={blockSave ? (hasDupCode ? "Có ký hiệu bị trùng — sửa trước khi lưu" : "Có tuyến bị trùng — sửa trước khi lưu") : ""}
                style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 14px", fontSize: 13, fontWeight: 600, borderRadius: 9, border: "none",
                  cursor: dirty && !saving && !blockSave ? "pointer" : "default", color: dirty && !saving && !blockSave ? "#fff" : "var(--ink-4)", background: dirty && !saving && !blockSave ? "var(--accent)" : "var(--line-2)",
                  boxShadow: dirty && !saving && !blockSave ? "0 1px 2px rgba(42,111,219,.4)" : "none" }}>
                <I.check /> {saving ? "Đang lưu…" : "Lưu mục này"}
              </button>
            </div>
          </div>
          <div style={{ fontSize: 12.5, color: "var(--ink-3)", marginBottom: 10 }}>{g.hint}</div>
          {g.coded && <div style={{ display: "flex", alignItems: "flex-start", gap: 8, fontSize: 12, color: "var(--ink-2)", background: "#eef4ff", border: "1px solid #d6e3fb", borderRadius: 9, padding: "8px 12px", marginBottom: 10 }}>
            <i className="bi bi-info-circle-fill" style={{ color: "var(--accent)", marginTop: 1 }} />
            <span>Sửa được cả <b>tên</b> lẫn <b>ký hiệu</b>. Đổi ký hiệu vẫn giữ liên kết (bảng giá/lô) vì khớp theo dòng. {allowDup ? <>Cho phép <b>nhiều tên</b> dùng chung 1 <b>ký hiệu</b>.</> : <>Lưu ý: mỗi <b>ký hiệu</b> phải <b>duy nhất</b>.</>}</span>
          </div>}
          {hasDupCode && <div style={{ display: "flex", alignItems: "center", gap: 7, fontSize: 12.5, fontWeight: 600, color: "var(--danger)", background: "#fce8e8", border: "1px solid #f3c9c9", borderRadius: 9, padding: "8px 12px", marginBottom: 10 }}>⚠ Có ký hiệu bị trùng — mỗi ký hiệu phải là duy nhất. Sửa các ô viền đỏ trước khi lưu.</div>}
          {hasDupRoute && <div style={{ display: "flex", alignItems: "center", gap: 7, fontSize: 12.5, fontWeight: 600, color: "var(--danger)", background: "#fce8e8", border: "1px solid #f3c9c9", borderRadius: 9, padding: "8px 12px", marginBottom: 10 }}>⚠ Có tuyến bị trùng — mỗi tuyến (đúng thứ tự kho) phải là duy nhất. Sửa các tuyến viền đỏ trước khi lưu. (Kho1→Kho2 khác Kho2→Kho1.)</div>}
          {hasDupGps && <div style={{ display: "flex", alignItems: "center", gap: 7, fontSize: 12.5, fontWeight: 600, color: "var(--danger)", background: "#fce8e8", border: "1px solid #f3c9c9", borderRadius: 9, padding: "8px 12px", marginBottom: 10 }}>⚠ Có xe GPS bị gán cho nhiều xe — mỗi xe GPS chỉ gán cho 1 xe. Sửa các ô viền đỏ trước khi lưu.</div>}
          {loading ? (
            <div style={{ display: "flex", alignItems: "center", gap: 9, padding: "30px 4px", color: "var(--ink-4)", fontSize: 13.5 }}>
              <span style={{ width: 15, height: 15, border: "2px solid var(--line)", borderTopColor: "var(--accent)", borderRadius: "50%", display: "inline-block", animation: "trk-spin .7s linear infinite" }} />
              Đang tải dữ liệu mục này…
            </div>
          ) : sel === "customers" ? (
            <CustomerManager cfg={cfg} setCfg={setCfg} />
          ) : sel === "drivers" ? (
            <DriversManager cfg={cfg} setCfg={setCfg} />
          ) : g.routefees ? (
            <RouteFees rows={cfg.routeFees || []} onChange={(rows) => setCfg("routeFees", rows)} warehouses={cfg.warehouses || []} locations={cfg.locations || []} isDup={isDupRoute} />
          ) : g.fuelprices ? (
            <FuelPrices rows={cfg.fuelPrices || []} onChange={(rows) => setCfg("fuelPrices", rows)} />
          ) : g.general ? (
            <div style={{ display: "flex", flexDirection: "column", gap: 20, maxWidth: 600 }}>
              {/* VAT mặc định */}
              <div>
                <div style={{ fontSize: 13.5, fontWeight: 700, marginBottom: 8 }}>VAT mặc định</div>
                <Field label="VAT mặc định cho lô hàng mới (%)">
                  <div style={{ position: "relative", width: 120 }}>
                    <input inputMode="decimal" value={vatDefault.icd == null ? "" : vatDefault.icd} onChange={(e) => setVatAll(e.target.value)} className="tnum"
                      style={{ width: "100%", padding: "8px 24px 8px 11px", fontSize: 13.5, textAlign: "right", border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
                      onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
                    <span style={{ position: "absolute", right: 10, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", pointerEvents: "none" }}>%</span>
                  </div>
                </Field>
                <div style={{ fontSize: 12, color: "var(--ink-4)", marginTop: 6 }}>Áp dụng cho lô hàng <b>mới thêm</b>. Các lô hiện có giữ VAT đã nhập.</div>
              </div>
              {/* Free time / Kết nối */}
              <div style={{ borderTop: "1px solid var(--line-2)", paddingTop: 18 }}>
                <div style={{ fontSize: 13.5, fontWeight: 700, marginBottom: 8 }}>Free time / Kết nối</div>
                <Field label="Ngưỡng Free time (giờ)">
                  <div style={{ position: "relative", width: 140 }}>
                    <input inputMode="decimal" value={cfg.freeTimeHours == null ? "4" : cfg.freeTimeHours} onChange={(e) => setCfg("freeTimeHours", e.target.value.replace(/[^\d.]/g, ""))} className="tnum"
                      style={{ width: "100%", padding: "8px 30px 8px 11px", fontSize: 13.5, textAlign: "right", border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
                      onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
                    <span style={{ position: "absolute", right: 10, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 12.5, pointerEvents: "none" }}>giờ</span>
                  </div>
                </Field>
                <div style={{ fontSize: 12, color: "var(--ink-4)", lineHeight: 1.6, marginTop: 6 }}>
                  Free time <b>&gt; ngưỡng</b> → <b style={{ color: "var(--good)" }}>CONNECT</b>; nhỏ hơn → <b style={{ color: "var(--danger)" }}>DISCONNECT</b>.
                  <br />Free time = Giờ xe ra − (Giờ đến kế hoạch hoặc Giờ xe đến, lấy giờ muộn hơn). Mặc định <b>{cfg.freeTimeHours || 4}h</b> dùng khi không khớp khoảng ngày nào.
                </div>
                {/* Ngưỡng theo KHOẢNG NGÀY (ưu tiên hơn mặc định) — chọn theo NGÀY cont ra */}
                <div style={{ marginTop: 14 }}>
                  <div style={{ fontSize: 12.5, fontWeight: 700, marginBottom: 4 }}>Ngưỡng theo khoảng ngày <span style={{ fontWeight: 400, color: "var(--ink-4)" }}>(ưu tiên hơn mặc định)</span></div>
                  <div style={{ fontSize: 11.5, color: "var(--ink-4)", lineHeight: 1.5, marginBottom: 8 }}>
                    Ngưỡng áp theo <b>ngày cont ra</b> (Giờ xe ra). Cont ra rơi vào khoảng nào thì dùng ngưỡng của khoảng đó (vd 12/06–30/06 = 2h; 01/07–20/07 = 4h). <b>Đến ngày</b> để trống = từ ngày đó trở đi.
                  </div>
                  {(cfg.freeTimeRules || []).map((r, i) => {
                    const upd = (np) => setCfg("freeTimeRules", (cfg.freeTimeRules || []).map((x, j) => (j === i ? { ...x, ...np } : x)));
                    const lbl = (t) => <div style={{ fontSize: 11, color: "var(--ink-4)", marginBottom: 3, fontWeight: 500 }}>{t}</div>;
                    return (
                      <div key={r.id || i} style={{ display: "flex", alignItems: "flex-end", gap: 8, flexWrap: "wrap", border: "1px solid var(--line)", borderRadius: 10, padding: "8px 10px", marginBottom: 8, background: "#fafbfc" }}>
                        <div>{lbl("Từ ngày")}<div style={{ width: 130 }}><DateField value={r.from} onChange={(x) => upd({ from: x })} /></div></div>
                        <div>{lbl("Đến ngày")}<div style={{ width: 130 }}><DateField value={r.to} onChange={(x) => upd({ to: x })} /></div></div>
                        <div>{lbl("Ngưỡng (giờ)")}
                          <div style={{ position: "relative", width: 90 }}>
                            <input inputMode="decimal" value={r.hours == null ? "" : r.hours} onChange={(e) => upd({ hours: e.target.value.replace(/[^\d.]/g, "") })} placeholder={String(cfg.freeTimeHours || 4)} className="tnum"
                              style={{ width: "100%", padding: "8px 26px 8px 10px", fontSize: 13.5, textAlign: "right", border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
                              onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
                            <span style={{ position: "absolute", right: 9, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 12, pointerEvents: "none" }}>h</span>
                          </div>
                        </div>
                        <button type="button" onClick={() => setCfg("freeTimeRules", (cfg.freeTimeRules || []).filter((_, j) => j !== i))} title="Xóa khoảng"
                          style={{ width: 34, height: 38, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: "var(--ink-4)", cursor: "pointer" }}
                          onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
                          onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-4)"; }}><I.trash /></button>
                      </div>
                    );
                  })}
                  <button type="button" onClick={() => setCfg("freeTimeRules", [...(cfg.freeTimeRules || []), { id: Date.now() + Math.random(), from: "", to: "", hours: "" }])}
                    style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "8px 13px", fontSize: 13, fontWeight: 600, border: "1px dashed var(--accent)", borderRadius: 10, background: "var(--accent-weak-2)", color: "var(--accent)", cursor: "pointer" }}>
                    <I.plus /> Thêm khoảng ngày
                  </button>
                </div>
              </div>

              {/* Cảnh báo hạn (xe & tài sản) */}
              <div style={{ borderTop: "1px solid var(--line-2)", paddingTop: 18 }}>
                <div style={{ fontSize: 13.5, fontWeight: 700, marginBottom: 8 }}>Cảnh báo hạn (xe &amp; tài sản)</div>
                <Field label="Cảnh báo trước hạn (số ngày)">
                  <div style={{ position: "relative", width: 140 }}>
                    <input inputMode="numeric" value={cfg.dueWarnDays == null ? "30" : cfg.dueWarnDays} onChange={(e) => setCfg("dueWarnDays", e.target.value.replace(/[^\d]/g, ""))} className="tnum"
                      style={{ width: "100%", padding: "8px 36px 8px 11px", fontSize: 13.5, textAlign: "right", border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
                      onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
                    <span style={{ position: "absolute", right: 10, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 12.5, pointerEvents: "none" }}>ngày</span>
                  </div>
                </Field>
                <div style={{ fontSize: 12, color: "var(--ink-4)", lineHeight: 1.6, marginTop: 6 }}>
                  Còn ≤ <b>{cfg.dueWarnDays || 30} ngày</b> sẽ chuyển nhãn <b style={{ color: "var(--warn)" }}>“Sắp hết hạn”</b> để không bị miss. Áp dụng cho <b>đăng kiểm / bảo hiểm</b> (xe), <b>bảo hành / kiểm định</b> (tài sản) và <b>chi phí định kỳ</b> (bảo hiểm, đăng kiểm…).
                </div>
              </div>
            </div>
          ) : allowDup ? (
            <>
              {/* Khu thêm: KÝ HIỆU trước → TÊN địa điểm → Thêm (đặt TRÊN CÙNG) */}
              {(() => { const add = () => { if (draft.trim() || codeDraft.trim()) { addRow(codeDraft, draft); setCodeDraft(""); setDraft(""); } };
                const onKey = (e) => { if (e.key === "Enter") { e.preventDefault(); add(); } };
                return (
                <div style={{ display: "flex", gap: 8, marginBottom: 14, flexWrap: "wrap" }}>
                  <input value={codeDraft} onChange={(e) => setCodeDraft(e.target.value)} onKeyDown={onKey} placeholder="Ký hiệu (VD: TV)"
                    style={{ width: 140, padding: "9px 12px", fontSize: 13.5, fontWeight: 600, textTransform: "uppercase", border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
                    onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
                  <input value={draft} onChange={(e) => setDraft(e.target.value)} onKeyDown={onKey} placeholder={g.ph || "Tên địa điểm"}
                    style={{ flex: 1, minWidth: 160, padding: "9px 12px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
                    onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
                  <Btn variant="primary" onClick={add}>Thêm</Btn>
                </div>
                ); })()}
              {(() => {
                // Gom theo KÝ HIỆU (chuẩn hóa); nhóm chưa có ký hiệu xuống cuối.
                const gm = new Map();
                list.forEach((nm, i) => { const raw = codeArr[i] || ""; const key = normCode(raw); if (!gm.has(key)) gm.set(key, { key, code: raw, idxs: [] }); gm.get(key).idxs.push(i); });
                let groups = [...gm.values()];
                groups = groups.filter((x) => x.key !== "").concat(groups.filter((x) => x.key === ""));
                if (!groups.length) return <div style={{ padding: "20px 4px", fontSize: 13, color: "var(--ink-4)" }}>Chưa có địa điểm nào — thêm ở trên.</div>;
                return (
                  <div style={{ display: "flex", flexDirection: "column", gap: 10, maxHeight: 430, overflowY: "auto", paddingRight: 2 }}>
                    {groups.map((grp) => {
                      const noCode = grp.key === "";
                      // Ký hiệu đã LƯU (nhóm có dòng mang id thật) → khóa, không cho sửa (giữ khớp import/bảng giá).
                      // Nhóm mới thêm (chưa có id) thì còn sửa được ký hiệu trước khi lưu.
                      const codeSaved = !noCode && grp.idxs.some((i) => { const id = idArr[i]; return id != null && id !== "" && !isNaN(+id); });
                      return (
                      <div key={grp.idxs[0]} style={{ flexShrink: 0, border: "1px solid var(--line)", borderRadius: 11, overflow: "hidden", background: "#fff" }}>
                        {/* Header nhóm: ký hiệu — sửa ở đây áp cho CẢ nhóm (đã lưu thì khóa) */}
                        <div style={{ display: "flex", alignItems: "center", gap: 8, padding: "8px 10px", background: noCode ? "var(--line-2)" : "var(--accent-weak)", borderBottom: "1px solid var(--line)" }}>
                          <i className={"bi " + (codeSaved ? "bi-lock-fill" : "bi-tag-fill")} style={{ color: noCode ? "var(--ink-4)" : "var(--accent)", fontSize: 13 }} title={codeSaved ? "Ký hiệu đã lưu — không sửa được" : ""} />
                          <input value={grp.code} readOnly={codeSaved} onChange={(e) => { if (!codeSaved) setGroupCode(grp.idxs, e.target.value); }} placeholder="Ký hiệu…"
                            title={codeSaved ? "Ký hiệu đã lưu — không sửa để giữ khớp import/bảng giá" : ""}
                            style={{ width: 130, padding: "5px 9px", fontSize: 13, fontWeight: 700, textTransform: "uppercase", border: "1px solid var(--line)", borderRadius: 7, outline: "none", background: codeSaved ? "var(--line-2)" : "#fff", color: codeSaved ? "var(--ink-3)" : "var(--ink)", cursor: codeSaved ? "not-allowed" : "text" }}
                            onFocus={(e) => { if (!codeSaved) e.target.style.borderColor = "var(--accent)"; }} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
                          <span style={{ fontSize: 11.5, fontWeight: 600, color: "var(--ink-4)" }}>{grp.idxs.length} địa điểm</span>
                          <button type="button" onClick={() => addRow(grp.code, "")} title="Thêm 1 tên địa điểm vào nhóm này"
                            style={{ marginLeft: "auto", display: "inline-flex", alignItems: "center", gap: 5, padding: "5px 10px", fontSize: 12, fontWeight: 600, cursor: "pointer", borderRadius: 7, border: "1px solid var(--accent)", background: "#fff", color: "var(--accent)" }}>
                            <I.plus /> Thêm tên
                          </button>
                        </div>
                        {/* Danh sách tên trong nhóm */}
                        <div style={{ display: "flex", flexDirection: "column", padding: "4px 8px 8px" }}>
                          {grp.idxs.map((i) => {
                            const linkedToPrice = locked.has(list[i]);
                            return (
                              <div key={i} style={{ display: "grid", gridTemplateColumns: "22px 1fr 28px", gap: 8, alignItems: "center", padding: "2px 0" }}>
                                <span style={{ color: linkedToPrice ? "var(--accent)" : "var(--ink-4)", display: "inline-flex" }} title={linkedToPrice ? "Địa điểm — đang dùng trong bảng giá" : "Địa điểm"}><i className="bi bi-geo-alt-fill" style={{ fontSize: 14 }} /></span>
                                <input value={list[i]} onChange={(e) => rename(i, e.target.value)} placeholder="Tên địa điểm"
                                  style={{ width: "100%", padding: "7px 10px", fontSize: 13.5, border: "1px solid transparent", borderRadius: 8, outline: "none", background: "transparent" }}
                                  onFocus={(e) => { e.target.style.borderColor = "var(--accent)"; e.target.style.background = "#fff"; }}
                                  onBlur={(e) => { e.target.style.borderColor = "transparent"; e.target.style.background = "transparent"; }} />
                                <button type="button" onClick={() => remove(i)} title="Xóa"
                                  style={{ width: 28, height: 28, display: "grid", placeItems: "center", border: "none", borderRadius: 7, background: "transparent", color: "var(--ink-4)", cursor: "pointer" }}
                                  onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
                                  onMouseLeave={(e) => { e.currentTarget.style.background = "transparent"; e.currentTarget.style.color = "var(--ink-4)"; }}>
                                  <I.trash />
                                </button>
                              </div>
                            );
                          })}
                        </div>
                      </div>
                      );
                    })}
                  </div>
                );
              })()}
            </>
          ) : (
            <>
              <div style={{ display: "flex", gap: 8, marginBottom: 12 }}>
                <input value={draft} onChange={(e) => setDraft(e.target.value)} placeholder={g.ph}
                  onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); addItem(); } }}
                  style={{ flex: 1, padding: "9px 12px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 9, outline: "none" }}
                  onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
                <Btn variant="primary" onClick={addItem}>Thêm</Btn>
              </div>
              {(() => {
                const codedGrid = g.geo ? "24px 0.8fr 130px 1.4fr 118px 28px" : g.addressed ? "24px 1.1fr 110px 1.5fr 28px" : "24px 1fr 130px 28px";
                const grid = g.priced && g.colored ? "24px 1fr 150px 56px 28px"
                  : g.priced ? "24px 1fr 150px 28px"
                  : g.colored ? "24px 1fr 56px 28px"
                  : g.coded ? codedGrid
                  : g.fleet ? "24px 1fr 360px 28px"
                  : "24px 1fr 28px";
                const head = g.priced && g.colored ? [<span key="i" />, <span key="n">Tên khoản</span>, <span key="p" style={{ textAlign: "right" }}>Đơn giá mặc định</span>, <span key="c" style={{ textAlign: "center" }}>Theo dõi</span>, <span key="x" />]
                  : g.priced ? [<span key="i" />, <span key="n">Tên khoản</span>, <span key="p" style={{ textAlign: "right" }}>Đơn giá mặc định</span>, <span key="x" />]
                  : g.colored ? [<span key="i" />, <span key="n">Tên khoản</span>, <span key="c" style={{ textAlign: "center" }}>Theo dõi</span>, <span key="x" />]
                  : g.coded ? [<span key="i" />, <span key="n">{g.codeNameLabel || "Tên"}</span>, <span key="p">Ký hiệu</span>, ...(g.addressed ? [<span key="a">Địa chỉ kho</span>] : []), ...(g.geo ? [<span key="g">Vị trí (GPS)</span>] : []), <span key="x" />]
                  : null;   // đội xe (fleet) render dạng THẺ, không dùng header lưới
                return head && <div style={{ display: "grid", gridTemplateColumns: grid, gap: 8, padding: "0 0 4px", fontSize: 11, fontWeight: 600, color: "var(--ink-4)", textTransform: "uppercase", letterSpacing: "0.04em" }}>{head}</div>;
              })()}
              <div style={{ display: "flex", flexDirection: "column", gap: g.fleet ? 8 : 2, maxHeight: g.fleet ? 420 : 300, overflowY: "auto", paddingRight: g.fleet ? 2 : 0 }}>
                {list.map((it, i) => {
                  // Đội xe (fleet): render dạng THẺ gọn — biển số + loại xe + số cầu + GPS, không nhồi vào 1 dòng lưới.
                  if (g.fleet) {
                    const isMbf = (vehType[it] || "MBF") === "MBF";
                    const dupGps = isDupGps(it);
                    const seg = (opts, cur, onPick, getColor) => (
                      <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 8, padding: 2 }}>
                        {opts.map(([val, lbl]) => { const on = cur === val; return (
                          <button key={val} type="button" onClick={() => onPick(val)}
                            style={{ border: "none", cursor: "pointer", fontSize: 12, fontWeight: 600, padding: "5px 12px", borderRadius: 6, whiteSpace: "nowrap",
                              background: on ? "#fff" : "transparent", color: on ? (getColor ? getColor(val) : "var(--accent)") : "var(--ink-4)", boxShadow: on ? "0 1px 2px rgba(16,19,23,.14)" : "none", transition: "all .12s" }}>{lbl}</button>
                        ); })}
                      </div>
                    );
                    return (
                      <div key={i} style={{ border: "1px solid var(--line)", borderRadius: 11, padding: "10px 12px", background: "#fff", display: "flex", flexDirection: "column", gap: 9 }}>
                        {/* Hàng 1: biển số + xóa */}
                        <div style={{ display: "flex", alignItems: "center", gap: 9 }}>
                          <i className="bi bi-truck" style={{ color: "var(--accent)", fontSize: 15, flexShrink: 0 }} />
                          <input value={it} onChange={(e) => rename(i, e.target.value)} placeholder="Biển số" className="tnum"
                            style={{ flex: 1, minWidth: 0, padding: "7px 11px", fontSize: 14.5, fontWeight: 700, letterSpacing: "0.02em", border: "1px solid var(--line)", borderRadius: 8, outline: "none" }}
                            onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
                          <button type="button" onClick={() => remove(i)} title="Xóa xe"
                            style={{ width: 30, height: 30, flexShrink: 0, display: "grid", placeItems: "center", border: "none", borderRadius: 8, background: "transparent", color: "var(--ink-4)", cursor: "pointer" }}
                            onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
                            onMouseLeave={(e) => { e.currentTarget.style.background = "transparent"; e.currentTarget.style.color = "var(--ink-4)"; }}><I.trash /></button>
                        </div>
                        {/* Hàng 2: loại xe + số cầu (MBF) */}
                        <div style={{ display: "flex", alignItems: "center", gap: 14, flexWrap: "wrap" }}>
                          {seg([["MBF", "Xe MBF"], ["Ngoài", "Xe ngoài"]], vehType[it] || "MBF", (v) => setVehType(it, v), (v) => v === "MBF" ? "var(--accent)" : "var(--ink-2)")}
                          {isMbf && (
                            <div style={{ display: "flex", alignItems: "center", gap: 7 }} title="Số cầu — để tính dầu theo Phí tuyến đường">
                              <span style={{ fontSize: 11.5, color: "var(--ink-4)", fontWeight: 600 }}>Số cầu</span>
                              {seg([["1", "1 cầu"], ["2", "2 cầu"]], vehAxle[it] || "", (v) => setVehAxle(it, v))}
                            </div>
                          )}
                        </div>
                        {/* Hàng 3: gán xe GPS (MBF) */}
                        {isMbf && (
                          <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                            <i className="bi bi-broadcast" style={{ color: vehGps[it] ? "var(--accent)" : "var(--ink-4)", fontSize: 14, flexShrink: 0 }} />
                            <select value={vehGps[it] || ""} onChange={(e) => setVehGps(it, e.target.value)} title={dupGps ? "Xe GPS này đã gán cho xe khác — mỗi xe GPS chỉ gán 1 xe" : "Gán xe trong hệ thống GPS để theo dõi vị trí lô hàng"}
                              style={{ flex: 1, minWidth: 0, fontSize: 12.5, padding: "7px 9px", border: `1px solid ${dupGps ? "var(--danger)" : (vehGps[it] ? "var(--accent)" : "var(--line)")}`, borderRadius: 8, background: dupGps ? "#fce8e8" : "#fff", color: dupGps ? "var(--danger)" : (vehGps[it] ? "var(--ink)" : "var(--ink-4)") }}>
                              <option value="">Gán xe GPS để theo dõi vị trí…</option>
                              {gpsVehicles.map((gv) => { const otherV = (gpsUsedBy[gv.ref] || []).filter((pl) => pl !== it); return <option key={gv.ref} value={gv.ref} disabled={otherV.length > 0}>{gv.plate} · {gv.providerLabel}{otherV.length ? ` (đã gán: ${otherV[0]})` : ""}</option>; })}
                              {vehGps[it] && !gpsVehicles.some((gv) => gv.ref === vehGps[it]) && <option value={vehGps[it]}>(đã gán — xe đang offline)</option>}
                            </select>
                          </div>
                        )}
                      </div>
                    );
                  }
                  // Ký hiệu ĐÃ LƯU (dòng có id thật) → khóa, không cho sửa (giữ khớp import/bảng giá);
                  // dòng mới thêm (chưa có id) thì còn sửa ký hiệu được trước khi lưu.
                  const codeLocked = (() => { const id = idArr[i]; return id != null && id !== "" && !isNaN(+id); })();
                  const linkedToPrice = locked.has(it);    // đang được bảng giá tham chiếu (hiện icon liên kết)
                  const dupCode = isDupCode(i);
                  const rowGrid = g.priced && g.colored ? "24px 1fr 150px 56px 28px"
                    : g.priced ? "24px 1fr 150px 28px"
                    : g.colored ? "24px 1fr 56px 28px"
                    : g.coded ? (g.geo ? "24px 0.8fr 130px 1.4fr 118px 28px" : g.addressed ? "24px 1.1fr 110px 1.5fr 28px" : "24px 1fr 130px 28px")
                    : g.fleet ? "24px 1fr 360px 28px"
                    : "24px 1fr 28px";
                  return (
                  <div key={i} style={{ display: "grid", gridTemplateColumns: rowGrid, gap: 8, alignItems: "center", padding: "3px 0" }}>
                    <span style={{ color: linkedToPrice ? "var(--accent)" : "var(--ink-4)" }} title={linkedToPrice ? "Đang dùng trong bảng giá" : ""}><I.link /></span>
                    <input value={it} onChange={(e) => rename(i, e.target.value)}
                      style={{ width: "100%", padding: "7px 10px", fontSize: 13.5, border: "1px solid transparent", borderRadius: 8, outline: "none", background: "transparent" }}
                      onFocus={(e) => { e.target.style.borderColor = "var(--accent)"; e.target.style.background = "#fff"; }}
                      onBlur={(e) => { e.target.style.borderColor = "transparent"; e.target.style.background = "transparent"; }} />
                    {g.priced && <Money value={prices[it]} onChange={(x) => setPrice(it, x)} dim />}
                    {g.colored && (
                      <div style={{ display: "flex", justifyContent: "center" }}>
                        <FlagPicker value={costColors[it] || ""} onChange={(c) => setColor(it, c)} />
                      </div>
                    )}
                    {g.coded && <input value={codeArr[i] || ""} readOnly={codeLocked} onChange={(e) => { if (!codeLocked) setCode(i, e.target.value); }} placeholder="VD: TV"
                      title={codeLocked ? "Ký hiệu đã lưu — không sửa để giữ khớp import/bảng giá" : (dupCode ? "Ký hiệu bị trùng với mục khác" : "")}
                      style={{ width: "100%", padding: "7px 10px", fontSize: 13, fontWeight: 600, border: `1px solid ${dupCode ? "var(--danger)" : "var(--line)"}`, borderRadius: 8, outline: "none", textTransform: "uppercase", background: codeLocked ? "var(--line-2)" : (dupCode ? "#fce8e8" : "#fff"), color: codeLocked ? "var(--ink-3)" : (dupCode ? "var(--danger)" : "var(--ink)"), cursor: codeLocked ? "not-allowed" : "text" }}
                      onFocus={(e) => { if (!codeLocked) e.target.style.borderColor = "var(--accent)"; }} onBlur={(e) => (e.target.style.borderColor = dupCode ? "var(--danger)" : "var(--line)")} />}
                    {g.addressed && <AddrInput value={addrArr[i] || ""} onChange={(v) => setAddr(i, v)}
                      onPlace={g.geo ? (lat, lng) => setGeo(i, lat.toFixed(7) + "," + lng.toFixed(7)) : () => {}}
                      mapsKey={mapsKey} placeholder="Gõ địa chỉ — gợi ý Google Maps (tự lấy tọa độ)" />}
                    {g.geo && (() => { const pinned = !!parseGeo(geoArr[i]); return (
                      <button type="button" onClick={() => setPickIdx(i)} title={pinned ? `Đã ghim: ${geoArr[i]} — bấm để sửa` : "Ghim tọa độ kho trên bản đồ"}
                        style={{ display: "inline-flex", alignItems: "center", justifyContent: "center", gap: 5, padding: "7px 8px", fontSize: 12, fontWeight: 600, cursor: "pointer", borderRadius: 8, whiteSpace: "nowrap",
                          border: `1px solid ${pinned ? "var(--good)" : "var(--line)"}`, background: pinned ? "var(--good-weak)" : "#fff", color: pinned ? "var(--good)" : "var(--ink-2)" }}>
                        <i className={"bi " + (pinned ? "bi-geo-alt-fill" : "bi-geo-alt")} /> {pinned ? "Đã ghim" : "Ghim BĐ"}
                      </button>
                    ); })()}
                    <button type="button" onClick={() => remove(i)} title="Xóa"
                      style={{ width: 28, height: 28, display: "grid", placeItems: "center", border: "none", borderRadius: 7, background: "transparent", color: "var(--ink-4)", cursor: "pointer" }}
                      onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
                      onMouseLeave={(e) => { e.currentTarget.style.background = "transparent"; e.currentTarget.style.color = "var(--ink-4)"; }}>
                      <I.trash />
                    </button>
                  </div>
                ); })}
                {!list.length && <div style={{ padding: "20px 4px", fontSize: 13, color: "var(--ink-4)" }}>Chưa có mục nào — thêm ở trên.</div>}
              </div>
              {pickIdx != null && (
                <MapPicker initial={parseGeo(geoArr[pickIdx])} address={addrArr[pickIdx] || ""} mapsKey={mapsKey}
                  onClose={() => setPickIdx(null)}
                  onPick={({ lat, lng, address }) => {
                    setGeo(pickIdx, lat.toFixed(7) + "," + lng.toFixed(7));
                    if (address && !((addrArr[pickIdx] || "").trim())) setAddr(pickIdx, address);
                    setPickIdx(null);
                  }} />
              )}
            </>
          )}
        </div>
      </div>
  );
}


function ConfigPopup({ cfg, setCfg, onClose }) {
  const footer = (
    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
      <div style={{ fontSize: 12.5, color: "var(--ink-4)" }}>Dữ liệu danh mục dùng chung cho cả hai sheet — chọn bằng Select2 trong các popup.</div>
      <Btn variant="primary" onClick={onClose}>Xong</Btn>
    </div>
  );
  return (
    <Modal title="Cấu hình dữ liệu" subtitle="Quản lý các danh mục link (master data) cho toàn hệ thống" onClose={onClose} footer={footer} width={760} icon={<I.cog />}>
      <ConfigBody cfg={cfg} setCfg={setCfg} />
    </Modal>
  );
}



export { CFG_GROUPS, RouteFees, FuelPrices, CUST_FIELDS, CustomerManager, DriversManager, ConfigBody, ConfigPopup };
