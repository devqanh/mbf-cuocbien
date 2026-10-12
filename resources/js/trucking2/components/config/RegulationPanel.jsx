import React from "react";
const { useState, useEffect } = React;
import { Money, MultiCombo, Combo, Btn } from "@trk/lib.jsx";

/* ===================== QUY CHẾ LƯƠNG CHUYẾN (theo bảng phí tuyến) =====================
   Mỗi bảng (theo "áp dụng từ ngày") mang 1 khối quy chế (book.regulation) — TÙY CHỈNH hết, Quy chế 03/2026 chỉ là mẫu
   nạp sẵn. Lộ trình tính tiền MỌI chuyến theo đây (không còn khai từng tuyến):
   - Nhóm cảng xuất phát (tên, cảng, cách tra): "Bảng KCN" theo ký hiệu kho · "Bảng vùng" theo TỈNH CŨ của kho.
   - Vùng: tên + tỉnh cũ thuộc vùng + mức tiền (thêm / bớt / đổi thứ tự).
   - Km: km tuyến → km khứ hồi KCN → cộng chặng theo bảng km giữa các điểm. Dầu = km × định mức (số cầu, Hàng–Hàng/Rỗng).
   meta = { default, oldProvinces, khoProvinces, locCodes, khoCodes } (server). */

// Ô số thập phân (km, lít/100km): giữ chuỗi người gõ, nhận "121,5" hoặc "121.5".
function Dec({ value, onChange, suffix, placeholder = "0", width }) {
  const [txt, setTxt] = useState(value === null || value === undefined || value === "" ? "" : String(value).replace(".", ","));
  useEffect(() => {
    const cur = parseFloat(String(txt).replace(",", "."));
    if ((value === "" || value == null) ? txt !== "" : cur !== Number(value)) setTxt(value === "" || value == null ? "" : String(value).replace(".", ","));
  }, [value]);
  return (
    <div style={{ position: "relative", width: width || "100%" }}>
      <input inputMode="decimal" value={txt} placeholder={placeholder} className="tnum"
        onChange={(e) => { const t = e.target.value.replace(/[^\d.,]/g, ""); setTxt(t); const n = parseFloat(t.replace(",", ".")); onChange(t === "" ? "" : (isNaN(n) ? "" : n)); }}
        style={{ width: "100%", padding: `8px ${suffix ? 34 : 11}px 8px 11px`, fontSize: 13.5, textAlign: "right", background: "#fff", border: "1px solid var(--line)", borderRadius: 9, outline: "none" }} />
      {suffix && <span style={{ position: "absolute", right: 10, top: "50%", transform: "translateY(-50%)", fontSize: 11.5, color: "var(--ink-4)", pointerEvents: "none" }}>{suffix}</span>}
    </div>
  );
}

// scrollMarginTop: "Tới mục này" cuộn tới khối mà tiêu đề không bị thanh dính phía trên che
const sec = { border: "1px solid var(--line-2)", borderRadius: 10, padding: "12px 14px", background: "#fff", scrollMarginTop: 140 };
const secTitle = (t, hint) => (
  <div style={{ marginBottom: 10 }}>
    <div style={{ fontSize: 13, fontWeight: 700, color: "var(--ink)" }}>{t}</div>
    {hint && <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 2, lineHeight: 1.5 }}>{hint}</div>}
  </div>
);
const th = { fontSize: 11, fontWeight: 600, color: "var(--ink-3)", textAlign: "left", padding: "0 6px 6px" };
const td = { padding: "3px 6px", verticalAlign: "middle" };
const delBtn = { display: "grid", placeItems: "center", width: 30, height: 30, borderRadius: 8, cursor: "pointer", border: "1px solid var(--line)", background: "#fff", color: "var(--ink-4)" };
const addBtn = { marginTop: 8, display: "inline-flex", alignItems: "center", gap: 6, padding: "6px 12px", fontSize: 12.5, fontWeight: 600, borderRadius: 8, cursor: "pointer", border: "1px dashed var(--accent)", background: "var(--accent-weak-2)", color: "var(--accent)" };

// Tóm tắt khối quy chế đã lưu cho dòng tiêu đề
const summary = (r) => !r ? "" : [
  (r.originGroups || []).length + " nhóm cảng",
  (r.zones || []).length + " vùng",
  (r.kcn || []).length + " KCN",
  (r.distances || []).length + " chặng",
].join(" · ");
const MODES = [{ value: "zone", label: "Bảng vùng — Bước 3 (theo tỉnh cũ của kho)" }, { value: "kcn", label: "Bảng KCN — Bước 4 (theo ký hiệu kho)" }];

/* HƯỚNG DẪN CẤU HÌNH theo bước — người mới làm lần lượt; mỗi bước có TRẠNG THÁI thật (đủ / còn thiếu gì) + đường dẫn
   tới đúng mục (cuộn trong trang) hoặc trang cài đặt liên quan (mở tab mới để không mất phần đang sửa).
   d = bản nháp quy chế; setup = số liệu dữ liệu liên quan từ server (kho chưa gán tỉnh cũ, xe chưa khai cầu, giá dầu). */
const GUIDE_KEY = "trk.regGuideHidden";
function SetupGuide({ d, has, setup = {}, orphanProvs = [] }) {
  const [hidden, setHidden] = useState(() => { try { return localStorage.getItem(GUIDE_KEY) === "1"; } catch (e) { return false; } });
  const toggle = () => setHidden((h) => { try { localStorage.setItem(GUIDE_KEY, h ? "0" : "1"); } catch (e) {} return !h; });
  const go = (id) => { const el = document.getElementById(id); if (el) el.scrollIntoView({ behavior: "smooth", block: "start" }); };
  const ext = (href, text) => <a href={href} target="_blank" rel="noreferrer" style={{ color: "var(--accent)", fontWeight: 600, whiteSpace: "nowrap" }}>{text} <i className="bi bi-box-arrow-up-right" style={{ fontSize: 10 }} /></a>;
  const jump = (id) => <button type="button" onClick={() => go(id)} style={{ border: "none", background: "none", padding: 0, color: "var(--accent)", fontWeight: 600, cursor: "pointer", whiteSpace: "nowrap" }}>Tới mục này ↓</button>;
  const groups = d.originGroups || [], zones = d.zones || [], kcn = d.kcn || [], dist = d.distances || [];
  const f = d.fuel || {};
  const fuelOk = ["axle1", "axle2"].every((a) => (f[a] || {}).hr && (f[a] || {}).hh);
  const fmtD = (s) => (s || "").split("-").reverse().join("/");
  // [tiêu đề, mô tả, ok, trạng thái, hành động]
  const steps = [
    ["Nhóm cảng xuất phát", "Cảng nào tính theo bảng vùng (Hải Phòng), cảng nào theo bảng KCN (ICD Quế Võ, Tri Phương).",
      has && groups.length > 0 && groups.every((g) => (g.codes || []).length), has ? groups.length + " nhóm" + (groups.some((g) => !(g.codes || []).length) ? " · có nhóm chưa chọn cảng" : "") : "chưa có",
      <>{has && jump("reg-groups")} · ký hiệu cảng ở {ext("/trucking-v2/cai-dat#locations", "Cài đặt › Địa điểm")}</>],
    ["Gán tỉnh cũ cho kho", "Mỗi kho chọn tỉnh CŨ (trước sáp nhập, Hưng Yên chia chưa/qua trạm) — dùng tra bảng vùng và trợ cấp quay đầu.",
      setup.khoTotal > 0 && !setup.khoNoOld, setup.khoNoOld ? setup.khoNoOld + "/" + setup.khoTotal + " kho chưa gán tỉnh cũ" : "đủ " + (setup.khoTotal || 0) + " kho",
      ext("/trucking-v2/cai-dat#warehouses", "Mở Cài đặt › Kho")],
    ["Bảng vùng (xuất phát Hải Phòng)", "Mỗi vùng: các tỉnh cũ thuộc vùng + lương chuyến + phụ cấp đi đường.",
      has && zones.length > 0 && !orphanProvs.length, has ? zones.length + " vùng" + (orphanProvs.length ? " · tỉnh của kho chưa thuộc vùng: " + orphanProvs.join(", ") : "") : "chưa có",
      has && jump("reg-zones")],
    ["Bảng KCN (xuất phát ICD / Tri Phương)", "Theo ký hiệu kho (QV, TS, TL…): lương chuyến, phụ cấp, km khứ hồi.",
      has && kcn.length > 0, has ? kcn.length + " dòng" : "chưa có", has && jump("reg-kcn")],
    ["Định mức dầu + số cầu xe", "Lít/100 km theo xe 1 cầu / 2 cầu và Hàng–Hàng / Hàng–Rỗng. Xe chưa khai số cầu tính như 1 cầu.",
      has && fuelOk && !setup.vehNoAxle, (has ? (fuelOk ? "đủ định mức" : "thiếu định mức") : "chưa có") + (setup.vehNoAxle ? " · " + setup.vehNoAxle + "/" + setup.vehTotal + " xe chưa khai số cầu" : ""),
      <>{has && jump("reg-fuel")} · số cầu ở {ext("/trucking-v2/cai-dat#vehicles", "Cài đặt › Đội xe")}</>],
    ["Chặng: km + vé trạm", "Km & vé trạm giữa 2 điểm; chuyến tự cộng các chặng đi qua. Chặng còn thiếu sẽ được báo ngay trên Lộ trình.",
      has && dist.length > 0, has ? dist.length + " chặng" + (dist.some((r) => +r.toll) ? "" : " · chưa khai vé trạm") : "chưa có",
      <>{has && jump("reg-dist")} · xem chặng thiếu ở {ext("/trucking-v2/lo-trinh", "Lộ trình")}</>],
    ["Giá dầu", "Đơn giá dầu theo khoảng ngày để ra tiền dầu của từng chuyến.",
      !!setup.fuelToday, setup.fuelToday ? "có giá cho hôm nay" : "chưa có giá cho hôm nay" + (setup.fuelLast ? " (mới nhất đến " + fmtD(setup.fuelLast) + ")" : ""),
      ext("/trucking-v2/cai-dat#fuelPrices", "Mở Bảng giá dầu")],
  ];
  const done = steps.filter((s) => s[2]).length;
  return (
    <div style={{ ...sec, borderColor: "#cfe0fb", background: "#f7faff" }}>
      <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
        <i className="bi bi-signpost-2-fill" style={{ color: "var(--accent)" }} />
        <b style={{ fontSize: 13 }}>Hướng dẫn cấu hình</b>
        <span style={{ fontSize: 12, fontWeight: 600, color: done === steps.length ? "var(--good)" : "#b45309" }}>{done}/{steps.length} bước đã xong</span>
        <span style={{ flex: 1 }} />
        <button type="button" onClick={toggle} style={{ border: "none", background: "none", color: "var(--ink-3)", fontSize: 12, cursor: "pointer" }}>{hidden ? "Hiện hướng dẫn" : "Ẩn hướng dẫn"}</button>
      </div>
      {!hidden && (
        <>
          <HowItWorks d={d} />
          <div style={{ fontSize: 12, color: "var(--ink-3)", margin: "8px 0 8px", lineHeight: 1.55 }}>
            Làm lần lượt từ trên xuống. Chưa có gì thì bấm <b>Nạp mặc định Quy chế 03/2026</b> để có sẵn số theo quy chế rồi sửa. Sửa xong dùng khung <b>Thử tính 1 chuyến</b> ngay bên dưới để kiểm tra, rồi bấm <b>Lưu quy chế</b>.
          </div>
          <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
            {steps.map(([title, desc, ok, status, action], i) => (
              <div key={i} style={{ display: "grid", gridTemplateColumns: "26px minmax(0, 1fr)", gap: 8, alignItems: "start", padding: "7px 9px", borderRadius: 9, background: "#fff", border: "1px solid " + (ok ? "#d6eedd" : "#f1dfb3") }}>
                <span style={{ width: 22, height: 22, borderRadius: 999, display: "grid", placeItems: "center", fontSize: 11.5, fontWeight: 700, color: "#fff", background: ok ? "var(--good)" : "#d08a14" }}>{ok ? <i className="bi bi-check-lg" /> : i + 1}</span>
                <div style={{ fontSize: 12, lineHeight: 1.5, minWidth: 0 }}>
                  <b style={{ fontSize: 12.5 }}>Bước {i + 1}. {title}</b>
                  <span style={{ marginLeft: 8, fontWeight: 600, color: ok ? "var(--good)" : "#b45309" }}>{status}</span>
                  <div style={{ color: "var(--ink-4)" }}>{desc} {action ? <span style={{ color: "var(--ink-3)" }}>· {action}</span> : null}</div>
                </div>
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
const inp = { width: "100%", padding: "8px 11px", fontSize: 13.5, background: "#fff", border: "1px solid var(--line)", borderRadius: 9, outline: "none" };
const fmtMoney = (n) => (Math.round(+n || 0)).toLocaleString("vi-VN") + " ₫";
const fmtN = (n) => (+n || 0).toLocaleString("vi-VN", { maximumFractionDigits: 2 });
const grpLabel = (g) => (g.name || g.key) + ((g.codes || []).length ? " (" + g.codes.join(", ") + ")" : "");
const pill = (bg, color) => ({ display: "inline-block", padding: "1px 8px", borderRadius: 999, fontSize: 11.5, fontWeight: 700, background: bg, color, whiteSpace: "nowrap" });

/* SƠ ĐỒ "hệ thống tính 1 chuyến thế nào" — lấy TÊN nhóm cảng thật đang khai để người đọc thấy nhóm nào đi nhánh nào. */
function HowItWorks({ d }) {
  const groups = d.originGroups || [];
  const zoneG = groups.filter((g) => g.mode !== "kcn"), kcnG = groups.filter((g) => g.mode === "kcn");
  const names = (list) => list.length ? list.map(grpLabel).join(" · ") : "(chưa có nhóm nào)";
  const row = (n, children) => (
    <div style={{ display: "grid", gridTemplateColumns: "22px minmax(0, 1fr)", gap: 8, alignItems: "start" }}>
      <span style={{ width: 20, height: 20, borderRadius: 999, display: "grid", placeItems: "center", fontSize: 11, fontWeight: 700, background: "var(--accent)", color: "#fff" }}>{n}</span>
      <div style={{ fontSize: 12, lineHeight: 1.65, color: "var(--ink-2)" }}>{children}</div>
    </div>
  );
  const arrow = <span style={{ color: "var(--ink-4)", margin: "0 4px" }}>→</span>;
  return (
    <div style={{ marginTop: 8, padding: "10px 12px", borderRadius: 9, background: "#fff", border: "1px solid #dbe6fb" }}>
      <div style={{ fontSize: 12.5, fontWeight: 700, marginBottom: 6 }}>Hệ thống tính tiền 1 chuyến như thế nào?</div>
      <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 8 }}>Mỗi lô hàng có <b>Nơi lấy → Kho → Nơi hạ</b>. Hệ thống đi lần lượt:</div>
      <div style={{ display: "flex", flexDirection: "column", gap: 7 }}>
        {row(1, <>Xem <b>Nơi lấy</b> thuộc <b>nhóm cảng</b> nào <span style={pill("#eef4ff", "#2563eb")}>Bước 1</span>
          <div style={{ marginTop: 3, paddingLeft: 10, borderLeft: "2px solid #dbe6fb" }}>
            <div>• Nhóm <b>{names(zoneG)}</b>{arrow}tra <b>Bảng vùng</b>: kho{arrow}<b>tỉnh cũ</b> của kho <span style={pill("#eef4ff", "#2563eb")}>Bước 2</span>{arrow}tỉnh đó thuộc <b>vùng</b> nào <span style={pill("#eef4ff", "#2563eb")}>Bước 3</span>{arrow}lương + phụ cấp của vùng.</div>
            <div>• Nhóm <b>{names(kcnG)}</b>{arrow}tra <b>Bảng KCN</b>: <b>ký hiệu kho</b> (QV, TS, TL…){arrow}dòng KCN của nhóm <span style={pill("#eef4ff", "#2563eb")}>Bước 4</span>{arrow}lương + phụ cấp + km khứ hồi.</div>
          </div></>)}
        {row(2, <><b>Km</b> = km khứ hồi trong bảng KCN, không có thì <b>cộng các chặng</b> xe đi qua <span style={pill("#eef4ff", "#2563eb")}>Bước 6</span>{arrow}<b>Dầu</b> = km × định mức theo số cầu xe <span style={pill("#eef4ff", "#2563eb")}>Bước 5</span> × giá dầu <span style={pill("#eef4ff", "#2563eb")}>Bước 7</span> (chi phí công ty).</>)}
        {row(3, <><b>Vé trạm</b> = cộng vé các chặng đi qua <span style={pill("#eef4ff", "#2563eb")}>Bước 6</span> (chi hộ cho lái, không tính lương).</>)}
        {row(4, <><b>Trợ cấp trong ngày</b>: quay đầu tuyến ngoài Hải Phòng · từ chuyến thứ 3 trong ngày (mỗi chuyến) — Lộ trình tự cộng vào đúng chuyến (mức tiền ở mục “Trợ cấp trong ngày”).</>)}
        {row(5, <>Trên <b>Lộ trình</b>: phụ cấp đi đường + vé trạm + trợ cấp chi cho lái <b>trong ngày</b>; lương chuyến cộng vào <b>kỳ lương</b>. Kế toán vẫn <b>sửa tay</b> được từng chuyến ở popup Chi lái.</>)}
      </div>
    </div>
  );
}

/* THỬ TÍNH 1 CHUYẾN theo quy chế ĐANG SỬA (chưa lưu cũng được) — server chạy đúng cách tính của Lộ trình rồi trả từng
   bước: nhóm cảng, tỉnh cũ / vùng của kho, mức áp, km, dầu, vé. Thiếu gì thì chỉ rõ bước cần sửa. */
function TripPreview({ draft, meta }) {
  const R = (window.__TRK || {}).routes || {};
  const locOpts = meta.locOptions || [], khoOpts = meta.khoOptions || [];
  const firstCode = ((draft.originGroups || [])[0] || {}).codes?.[0] || "";
  const [f, setF] = useState(() => ({ from: locOpts.some((o) => o.value === firstCode) ? firstCode : "", kho: [], to: locOpts.some((o) => o.value === firstCode) ? firstCode : "", axle: "1", loadIn: "1", loadOut: "0", tryProvince: "" }));
  const [res, setRes] = useState(null);
  const [busy, setBusy] = useState(false);
  const set = (np) => { setF((x) => ({ ...x, ...np })); };
  const khoLabel = (v) => (khoOpts.find((o) => o.value === v) || {}).label || v;
  const run = async (extra = {}) => {
    const q = { ...f, ...extra };
    if (!q.from || !q.kho.length) { window.trkToast && window.trkToast("Chọn Nơi lấy và ít nhất 1 kho", "error"); return; }
    setBusy(true);
    try {
      const r = await window.trkApi("POST", R.routeFeeBooks + "/regulation-preview", { regulation: draft, from: q.from, to: q.to || q.from, kho: q.kho, axle: q.axle,
        ioIn: q.loadIn === "1" ? "Nhập" : "Xuất", ioOut: q.loadOut === "1" ? "Xuất" : "Nhập", tryProvince: q.tryProvince });
      if (r && r.ok) setRes(r);
    } finally { setBusy(false); }
  };
  const lbl = (t) => <div style={{ fontSize: 11, fontWeight: 600, color: "var(--ink-3)", marginBottom: 3 }}>{t}</div>;
  const sel = (value, onChange, opts) => (
    <select value={value} onChange={(e) => onChange(e.target.value)} style={{ ...inp, padding: "7px 8px" }}>{opts.map(([v, t]) => <option key={v} value={v}>{t}</option>)}</select>
  );
  const t = res && res.trip;
  const bad = (txt, step) => <span style={{ color: "#b91c1c", fontWeight: 600 }}>{txt}{step ? <> — sửa ở <b>Bước {step}</b></> : null}</span>;
  const missingProv = res && (res.khos || []).some((k) => !k.oldProvince) && res.from && res.from.mode !== "kcn";
  const line = (n, title, body) => (
    <div style={{ display: "grid", gridTemplateColumns: "22px 150px minmax(0, 1fr)", gap: 8, alignItems: "start", padding: "6px 0", borderTop: n > 1 ? "1px dashed var(--line-2)" : "none" }}>
      <span style={{ width: 20, height: 20, borderRadius: 999, display: "grid", placeItems: "center", fontSize: 11, fontWeight: 700, background: "var(--line-2)", color: "var(--ink-2)" }}>{n}</span>
      <b style={{ fontSize: 12.5 }}>{title}</b>
      <div style={{ fontSize: 12.5, lineHeight: 1.55, minWidth: 0 }}>{body}</div>
    </div>
  );
  const items = t ? [...(t.items || []), ...(t.payrollItems || [])] : [];
  const daily = t ? (t.items || []).reduce((s, x) => s + (+x.amount || 0), 0) : 0;
  const payroll = t ? (t.payrollItems || []).reduce((s, x) => s + (+x.amount || 0), 0) : 0;
  return (
    <div style={{ ...sec, borderColor: "#d9cdfa", background: "#faf8ff" }} id="reg-try">
      {secTitle("Thử tính 1 chuyến", "Chọn 1 chuyến giả định rồi bấm Tính thử — hệ thống tính đúng như trên Lộ trình, theo quy chế ĐANG SỬA (chưa lưu cũng được) và chỉ ra từng bước tra.")}
      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(150px, 1fr))", gap: 8, alignItems: "end" }}>
        <div>{lbl("Nơi lấy (cảng)")}<Combo value={f.from} options={locOpts} strict onChange={(v) => set({ from: v })} small placeholder="Chọn cảng…" /></div>
        <div style={{ gridColumn: "span 2" }}>{lbl("Kho (có thể nhiều kho)")}<MultiCombo values={f.kho} max={4} options={khoOpts.map((o) => o.value)} labelOf={khoLabel} strict placeholder="Chọn kho…" onChange={(v) => set({ kho: v })} /></div>
        <div>{lbl("Nơi hạ (cảng)")}<Combo value={f.to} options={locOpts} strict onChange={(v) => set({ to: v })} small placeholder="Như nơi lấy" /></div>
        <div>{lbl("Xe")}{sel(f.axle, (v) => set({ axle: v }), [["1", "Xe 1 cầu"], ["2", "Xe 2 cầu"]])}</div>
        <div>{lbl("Chiều vào (tới kho)")}{sel(f.loadIn, (v) => set({ loadIn: v }), [["1", "Có hàng (Nhập)"], ["0", "Cont rỗng (Xuất)"]])}</div>
        <div>{lbl("Chiều ra (về cảng)")}{sel(f.loadOut, (v) => set({ loadOut: v }), [["1", "Có hàng (Xuất)"], ["0", "Cont rỗng"]])}</div>
        <div><Btn variant="primary" busy={busy} onClick={() => run()}>Tính thử</Btn></div>
      </div>

      {t && (
        <div style={{ marginTop: 12, padding: "8px 12px", borderRadius: 9, background: "#fff", border: "1px solid var(--line-2)" }}>
          {line(1, "Nhóm cảng", res.from.group
            ? <>Nơi lấy <b>{res.from.code}</b> thuộc nhóm <b>{res.from.group}</b>{arrowTxt}tra <b>{res.from.mode === "kcn" ? "Bảng KCN (Bước 4)" : "Bảng vùng (Bước 3)"}</b>.
                {res.to.group && res.to.group !== res.from.group ? <> Nơi hạ <b>{res.to.code}</b> thuộc nhóm khác (<b>{res.to.group}</b>) → chuyến <b>khác nhóm cảng</b>.</> : null}</>
            : res.to.group ? <>Nơi lấy <b>{res.from.code}</b> không thuộc nhóm nào → xét Nơi hạ <b>{res.to.code}</b>: nhóm <b>{res.to.group}</b>.</>
            : bad(`Nơi lấy ${res.from.code || "?"} và Nơi hạ ${res.to.code || "?"} chưa thuộc nhóm cảng nào`, 1))}
          {line(2, "Kho", (res.khos || []).map((k, i) => (
            <div key={i}>
              <b>{k.name}</b> · ký hiệu <b>{k.code || "—"}</b>
              {res.from.mode !== "kcn" && <> · tỉnh cũ: {k.oldProvince ? <b>{k.oldProvince}{k.tried ? " (đang thử)" : ""}</b> : bad("chưa gán", 2)}
                {k.oldProvince ? <> → {k.zone ? <b>{k.zone}</b> : bad("tỉnh chưa thuộc vùng nào", 3)}</> : null}</>}
            </div>
          )))}
          {missingProv && (
            <div style={{ margin: "2px 0 6px 30px", padding: "6px 10px", borderRadius: 8, background: "#fff7e6", fontSize: 12, display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap" }}>
              Kho chưa gán tỉnh cũ — chọn thử 1 tỉnh để xem kết quả (không lưu):
              <div style={{ width: 230 }}><Combo value={f.tryProvince} options={meta.oldProvinces || []} strict small placeholder="Tỉnh cũ thử…" onChange={(v) => { set({ tryProvince: v }); run({ tryProvince: v }); }} /></div>
            </div>
          )}
          {line(3, "Mức tiền", t.matched
            ? <>
                <div style={{ color: "#6d28d9", fontWeight: 600 }}>{t.feeRule}</div>
                {items.map((x, i) => (
                  <div key={i}>{x.label}: {x.required ? <span style={{ color: "#b45309", fontWeight: 600 }}>nhập tay ở Lộ trình{x.suggest ? " (gợi ý " + fmtMoney(x.suggest) + ")" : ""}</span> : <b className="tnum">{fmtMoney(x.amount)}</b>}
                    <span style={{ color: "var(--ink-4)" }}> · {x.perDay === false ? "vào kỳ lương" : x.fixed === "choho" ? "chi hộ trong ngày" : "chi trong ngày, tính lương"}</span></div>
                ))}
                {t.inputHint ? <div style={{ color: "#b45309" }}>{t.inputHint}</div> : null}
              </>
            : bad(t.note || "Chưa tính được"))}
          {line(4, "Km & dầu", t.km != null || t.fuel
            ? <>
                {t.km != null && <div>Km: <b className="tnum">{fmtN(t.km)} km</b> <span style={{ color: "var(--ink-4)" }}>({(t.fuel && t.fuel.kmSrc) || "—"})</span></div>}
                {t.fuel ? <div>Dầu: <b className="tnum">{fmtN(t.fuel.km)} km × {fmtN(t.fuel.rate)} lít/100 km = {fmtN(t.fuel.liters)} lít</b> <span style={{ color: "var(--ink-4)" }}>(xe {t.fuel.axle} cầu, {t.fuel.load})</span>
                  {" · "}{t.fuel.unitPrice > 0 ? <>giá {fmtMoney(t.fuel.unitPrice)}/lít = <b>{fmtMoney(t.fuel.amount)}</b></> : bad("chưa có giá dầu hôm nay", 7)}</div> : null}
              </>
            : (t.kmMissing || []).length ? bad("Thiếu km chặng " + t.kmMissing.join(", "), 6) : <span style={{ color: "var(--ink-4)" }}>—</span>)}
          {t.matched && (
            <div style={{ marginTop: 6, padding: "7px 10px", borderRadius: 8, background: "var(--accent-weak-2)", fontSize: 12.5 }}>
              <b>Kết quả:</b> chi cho lái trong ngày <b className="tnum">{fmtMoney(daily)}</b> · lương chuyến vào kỳ lương <b className="tnum">{fmtMoney(payroll)}</b>
              {t.fuel ? <> · dầu (công ty) <b className="tnum">{fmtN(t.fuel.liters)} lít</b></> : null}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
const arrowTxt = " → ";

export function RegulationPanel({ book, meta = {}, onBooks }) {
  const R = (window.__TRK || {}).routes || {};
  const [open, setOpen] = useState(true);   // nội dung chính của trang → mở sẵn
  const [draft, setDraft] = useState(book ? book.regulation || null : null);
  const [dirty, setDirty] = useState(false);
  const [busy, setBusy] = useState(false);
  // Đổi bảng / server trả bảng mới → nạp lại bản nháp
  useEffect(() => { setDraft(book ? book.regulation || null : null); setDirty(false); }, [book ? book.id : null, JSON.stringify(book ? book.regulation || null : null)]);
  if (!book) return null;

  const upd = (fn) => { setDraft((d) => fn(JSON.parse(JSON.stringify(d || {})))); setDirty(true); };
  const loadDefault = async () => {
    if (draft && !(await window.confirmAction({ title: "Nạp mặc định Quy chế 03/2026?", text: "Ghi đè toàn bộ quy chế đang nhập của bảng này bằng mức mặc định (chưa lưu cho tới khi bấm <b>Lưu quy chế</b>).", confirmText: "Nạp mặc định" }))) return;
    setDraft(JSON.parse(JSON.stringify(meta.default || {}))); setDirty(true); setOpen(true);
  };
  const clearAll = async () => {
    if (!(await window.confirmAction({ title: "Bỏ quy chế của bảng này?", text: "Chuyến trong thời gian áp dụng của bảng này sẽ <b>không còn được tính tiền</b> (Lộ trình chỉ báo chưa cấu hình). Bấm <b>Lưu quy chế</b> để áp dụng.", confirmText: "Bỏ quy chế", danger: true }))) return;
    setDraft(null); setDirty(true);
  };
  const save = async () => {
    setBusy(true);
    try {
      const r = await window.trkApi("PUT", R.routeFeeBooks + "/" + book.id + "/regulation", { regulation: draft || {} });
      if (r && r.ok) { onBooks && onBooks(r.books || []); setDirty(false); window.trkToast && window.trkToast("Đã lưu quy chế lương chuyến"); }
    } finally { setBusy(false); }
  };

  const pointCodes = Array.from(new Set([...(meta.locCodes || []), ...(meta.khoCodes || [])]));
  const d = draft || {};
  const saved = book.regulation;
  const groups = d.originGroups || [];
  const kcnGroupOpts = groups.filter((g) => g.mode === "kcn").map((g) => ({ value: g.key, label: g.name || g.key }));
  const zones = d.zones || [];
  // Cảnh báo vùng: tỉnh khai ở 2 vùng (vùng trước thắng) · tỉnh cũ của kho chưa thuộc vùng nào (chuyến báo chưa khớp)
  const provCount = {};
  zones.forEach((z) => (z.provinces || []).forEach((p) => { provCount[p] = (provCount[p] || 0) + 1; }));
  const dupProvs = Object.keys(provCount).filter((p) => provCount[p] > 1);
  const orphanProvs = (meta.khoProvinces || []).filter((p) => !provCount[p]);
  const delGroup = async (i) => {
    const g = groups[i]; const used = (d.kcn || []).filter((k) => k.group === g.key).length;
    if (used && !(await window.confirmAction({ title: "Xóa nhóm cảng?", text: `Xóa nhóm <b>${g.name || g.key}</b> cùng <b>${used}</b> dòng KCN của nhóm.`, confirmText: "Xóa", danger: true }))) return;
    upd((x) => { x.originGroups = (x.originGroups || []).filter((_, j) => j !== i); x.kcn = (x.kcn || []).filter((k) => k.group !== g.key); return x; });
  };

  return (
    <div style={{ border: "1px solid " + (dirty ? "var(--warn, #d97706)" : "var(--line)"), borderRadius: 12, background: "var(--accent-weak-2)" }}>
      {/* Tiêu đề: bấm để mở/thu gọn */}
      <div onClick={() => setOpen((o) => !o)} style={{ display: "flex", alignItems: "center", gap: 10, padding: "11px 14px", cursor: "pointer", flexWrap: "wrap" }}>
        <i className={"bi " + (open ? "bi-chevron-down" : "bi-chevron-right")} style={{ color: "var(--ink-3)" }} />
        <i className="bi bi-journal-text" style={{ color: "var(--accent)" }} />
        <b style={{ fontSize: 13.5 }}>Quy chế lương chuyến</b>
        {saved
          ? <span style={{ fontSize: 12, fontWeight: 600, color: "var(--accent)" }}>{summary(saved)}</span>
          : <span style={{ fontSize: 12, fontWeight: 600, color: "var(--ink-4)" }}>Chưa cấu hình</span>}
        {dirty && <span style={{ fontSize: 11.5, fontWeight: 700, color: "#d97706" }}>● chưa lưu</span>}
      </div>

      {open && (
        <div style={{ padding: "0 14px 14px", display: "flex", flexDirection: "column", gap: 12 }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", lineHeight: 1.6 }}>
            Lộ trình tính tiền <b>mọi chuyến</b> theo quy chế này: <b>vé trạm</b> (chi hộ) + <b>phụ cấp đi đường</b> chi trong ngày, <b>lương chuyến</b> vào kỳ lương, <b>dầu</b> = km × định mức (chi phí công ty).
          </div>
          <SetupGuide d={d} has={!!draft} setup={meta.setup || {}} orphanProvs={draft ? orphanProvs : []} />
          {draft && <TripPreview draft={draft} meta={meta} />}

          {!draft && (
            <div style={{ ...sec, display: "flex", alignItems: "center", gap: 12, flexWrap: "wrap" }}>
              <span style={{ fontSize: 13, color: "var(--ink-3)" }}>Bảng này chưa có quy chế — Lộ trình sẽ báo “chưa cấu hình” cho các chuyến trong thời gian áp dụng.</span>
              <Btn variant="primary" onClick={loadDefault}>Nạp mặc định Quy chế 03/2026</Btn>
            </div>
          )}

          {draft && (
            <>
              {/* 1. Nhóm cảng xuất phát — tùy chỉnh: tên, cảng thuộc nhóm, tra bảng vùng hay bảng KCN */}
              <div style={sec} id="reg-groups">
                {secTitle("Bước 1 · Nhóm cảng xuất phát", "Tên nhóm chỉ để gọi cho dễ nhớ. Quan trọng là 2 cột: “Cảng thuộc nhóm” và “Tra theo”. Chuyến lấy cont ở cảng nào thì tính tiền bằng bảng mà nhóm của cảng đó chọn — “Bảng vùng” là Bước 3, “Bảng KCN” là Bước 4 (mỗi nhóm có 1 bảng KCN riêng mang tên nhóm). Bước 5, 6, 7 dùng chung cho mọi nhóm.")}
                <div style={{ overflowX: "auto" }}>
                  <table style={{ width: "100%", borderCollapse: "collapse", minWidth: 680 }}>
                    <thead><tr><th style={{ ...th, width: 180 }}>Tên nhóm (để gọi)</th><th style={th}>Cảng thuộc nhóm (ký hiệu)</th><th style={{ ...th, width: 250 }}>Tra theo → dùng ở bước nào</th><th style={{ width: 40 }} /></tr></thead>
                    <tbody>
                      {groups.map((g, i) => {
                        const setG = (np) => upd((x) => { x.originGroups = (x.originGroups || []).map((o, j) => (j === i ? { ...o, ...np } : o)); return x; });
                        return (
                          <tr key={g.key || i} style={{ borderTop: "1px solid var(--line-2)" }}>
                            <td style={td}><input value={g.name || ""} onChange={(e) => setG({ name: e.target.value })} placeholder="VD: Hải Phòng" style={inp} /></td>
                            <td style={td}><MultiCombo values={g.codes || []} max={30} options={meta.locCodes || []} placeholder="Chọn ký hiệu cảng…" onChange={(v) => setG({ codes: v })} /></td>
                            <td style={td}>
                              <Combo value={g.mode || "zone"} options={MODES} strict onChange={(v) => setG({ mode: v })} small />
                              {/* Nói thẳng nhóm này dùng bảng nào + bấm để tới đúng bảng */}
                              {(() => {
                                const isKcn = g.mode === "kcn";
                                const n = isKcn ? (d.kcn || []).filter((k) => k.group === g.key).length : zones.length;
                                const target = isKcn ? "reg-kcn-" + g.key : "reg-zones";
                                return (
                                  <button type="button" onClick={() => { const el = document.getElementById(target); if (el) el.scrollIntoView({ behavior: "smooth", block: "start" }); }}
                                    style={{ marginTop: 3, border: "none", background: "none", padding: 0, fontSize: 11.5, fontWeight: 600, color: "var(--accent)", cursor: "pointer", textAlign: "left" }}>
                                    → dùng {isKcn ? <>Bước 4 · bảng KCN “{g.name || "nhóm này"}” ({n} dòng)</> : <>Bước 2 + Bước 3 · bảng vùng ({n} vùng)</>} ↓
                                  </button>
                                );
                              })()}
                            </td>
                            <td style={td}><button type="button" title="Xóa nhóm" style={delBtn} onClick={() => delGroup(i)}><i className="bi bi-x-lg" /></button></td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
                <button type="button" style={addBtn} onClick={() => upd((x) => { x.originGroups = [...(x.originGroups || []), { key: "g" + Date.now().toString(36), name: "", mode: "kcn", codes: [] }]; return x; })}><i className="bi bi-plus-lg" /> Thêm nhóm cảng</button>
              </div>

              {/* 2. Bảng vùng — tùy chỉnh: thêm / bớt / đổi tên vùng, chọn tỉnh cũ thuộc vùng */}
              <div style={sec} id="reg-zones">
                {secTitle("Bước 3 · Bảng vùng — nhóm tra theo tỉnh cũ của kho", "Mỗi vùng: tên, các tỉnh cũ thuộc vùng (tỉnh gán ở Cài đặt › Kho) và mức tiền. Chuyến đi nhiều kho → lấy vùng có lương cao nhất. “Tuyến dài” dùng cho điều chỉnh cắt kéo / kẹp cont ở giai đoạn sau.")}
                {/* Bảng này dùng cho nhóm cảng nào (Bước 1 chọn "Bảng vùng") + ví dụ đọc 1 dòng */}
                <div style={{ display: "flex", flexWrap: "wrap", gap: 6, alignItems: "center", marginBottom: 8, fontSize: 12 }}>
                  <span style={{ color: "var(--ink-3)" }}>Dùng cho nhóm cảng:</span>
                  {groups.filter((g) => g.mode !== "kcn").length
                    ? groups.filter((g) => g.mode !== "kcn").map((g) => <span key={g.key} style={pill("#eef4ff", "#2563eb")}>{grpLabel(g)}</span>)
                    : <span style={{ color: "#b45309", fontWeight: 600 }}>chưa nhóm nào chọn “Bảng vùng” ở Bước 1 — bảng này chưa được dùng</span>}
                </div>
                {zones[0] && (
                  <div style={{ marginBottom: 8, padding: "6px 10px", borderRadius: 8, background: "#f7faff", fontSize: 12, color: "var(--ink-3)", lineHeight: 1.55 }}>
                    <b>Đọc ví dụ:</b> chuyến lấy cont ở cảng nhóm <b>{(groups.find((g) => g.mode !== "kcn") || {}).name || "Hải Phòng"}</b>, giao kho có tỉnh cũ là <b>{(zones[Math.min(4, zones.length - 1)].provinces || [])[0] || "…"}</b>
                    {" "}→ thuộc vùng <b>V{Math.min(4, zones.length - 1) + 1} · {zones[Math.min(4, zones.length - 1)].name}</b> → lương chuyến <b>{fmtMoney(zones[Math.min(4, zones.length - 1)].luong)}</b>, phụ cấp đi đường <b>{fmtMoney(zones[Math.min(4, zones.length - 1)].phuCap)}</b>.
                  </div>
                )}
                <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                  {zones.map((z, i) => {
                    const setZ = (np) => upd((x) => { x.zones = (x.zones || []).map((o, j) => (j === i ? { ...o, ...np } : o)); return x; });
                    const move = (dir) => upd((x) => { const l = [...(x.zones || [])]; const j = i + dir; if (j < 0 || j >= l.length) return x; [l[i], l[j]] = [l[j], l[i]]; x.zones = l; return x; });
                    return (
                      <div key={i} style={{ border: "1px solid var(--line-2)", borderRadius: 9, padding: "8px 10px", display: "grid", gridTemplateColumns: "34px minmax(160px, 1fr) 140px 140px 64px 70px", gap: "6px 8px", alignItems: "center" }}>
                        <b style={{ fontSize: 13, color: "var(--accent)" }}>V{i + 1}</b>
                        <input value={z.name || ""} onChange={(e) => setZ({ name: e.target.value })} placeholder="Tên vùng" style={{ ...inp, fontWeight: 600 }} />
                        <Money value={z.luong || ""} onChange={(v) => setZ({ luong: v })} placeholder="Lương chuyến" />
                        <Money value={z.phuCap || ""} onChange={(v) => setZ({ phuCap: v })} placeholder="Phụ cấp" />
                        <label title="Tuyến dài" style={{ display: "inline-flex", alignItems: "center", gap: 4, fontSize: 11.5, color: "var(--ink-3)", cursor: "pointer" }}>
                          <input type="checkbox" checked={!!z.long} onChange={(e) => setZ({ long: e.target.checked })} style={{ accentColor: "var(--accent)" }} /> dài
                        </label>
                        <div style={{ display: "flex", gap: 2, justifyContent: "flex-end" }}>
                          <button type="button" title="Lên" disabled={i === 0} style={{ ...delBtn, width: 22, border: "none", opacity: i === 0 ? .3 : 1 }} onClick={() => move(-1)}><i className="bi bi-arrow-up" /></button>
                          <button type="button" title="Xóa vùng" style={{ ...delBtn, width: 26, height: 26 }} onClick={() => upd((x) => { x.zones = (x.zones || []).filter((_, j) => j !== i); return x; })}><i className="bi bi-x-lg" /></button>
                        </div>
                        <span style={{ fontSize: 11, color: "var(--ink-4)" }}>Tỉnh</span>
                        <div style={{ gridColumn: "2 / -1" }}>
                          <MultiCombo values={z.provinces || []} max={70} options={meta.oldProvinces || []} strict placeholder="Chọn tỉnh cũ thuộc vùng…" onChange={(v) => setZ({ provinces: v })} />
                        </div>
                      </div>
                    );
                  })}
                </div>
                <div style={{ display: "flex", gap: 6, alignItems: "flex-start", flexWrap: "wrap", marginTop: 4 }}>
                  <button type="button" style={addBtn} onClick={() => upd((x) => { x.zones = [...(x.zones || []), { name: "", provinces: [], luong: "", phuCap: "", long: true }]; return x; })}><i className="bi bi-plus-lg" /> Thêm vùng</button>
                </div>
                {dupProvs.length > 0 && <div style={{ marginTop: 8, fontSize: 12, color: "#b45309" }}><i className="bi bi-exclamation-triangle-fill" /> Tỉnh nằm ở nhiều vùng (vùng đứng trước được dùng): <b>{dupProvs.join(", ")}</b></div>}
                {orphanProvs.length > 0 && <div style={{ marginTop: 6, fontSize: 12, color: "#b45309" }}><i className="bi bi-exclamation-triangle-fill" /> Tỉnh cũ của kho chưa thuộc vùng nào (chuyến tới kho đó sẽ báo chưa khớp): <b>{orphanProvs.join(", ")}</b></div>}
              </div>

              {/* 3. Bảng KCN — nhóm tra theo ký hiệu kho */}
              <div style={sec} id="reg-kcn">
                {secTitle("Bước 4 · Bảng KCN — nhóm tra theo ký hiệu kho", "Mỗi nhóm cảng chọn “Bảng KCN” ở Bước 1 có bảng riêng bên dưới: 1 dòng = 1 ký hiệu kho (QV, TS, TL…) với lương chuyến, phụ cấp, km khứ hồi. Nhiều kho trong 1 chuyến → lấy mức lương cao nhất. Km khứ hồi chỉ dùng khi lấy và hạ cùng nhóm cảng; khác nhóm thì cộng chặng.")}
                {!kcnGroupOpts.length && <div style={{ fontSize: 12, color: "#b45309", fontWeight: 600, marginBottom: 6 }}>Chưa nhóm cảng nào chọn “Bảng KCN” ở Bước 1 — bảng này chưa được dùng.</div>}
                {/* Gom dòng theo NHÓM CẢNG → nhìn là biết mỗi bảng dùng cho cảng nào; dòng của nhóm đã xóa/đổi cách tra để cuối */}
                {(() => {
                  const kcn = d.kcn || [];
                  const keys = new Set(kcnGroupOpts.map((o) => o.value));
                  const blocks = groups.filter((g) => g.mode === "kcn").map((g) => ({ g, idx: kcn.map((_, i) => i).filter((i) => kcn[i].group === g.key) }));
                  const orphan = kcn.map((_, i) => i).filter((i) => !keys.has(kcn[i].group));
                  const rowOf = (i, showGroup) => {
                    const k = kcn[i];
                    const setK = (np) => upd((x) => { x.kcn = (x.kcn || []).map((r, j) => (j === i ? { ...r, ...np } : r)); return x; });
                    return (
                      <tr key={i} style={{ borderTop: "1px solid var(--line-2)" }}>
                        {showGroup && <td style={td}><Combo value={k.group || ""} options={kcnGroupOpts} strict onChange={(v) => setK({ group: v })} small placeholder="Chọn nhóm…" /></td>}
                        <td style={td}><Combo value={k.kho || ""} options={meta.khoCodes || []} onChange={(v) => setK({ kho: String(v).toUpperCase() })} small placeholder="Ký hiệu…" /></td>
                        <td style={td}><Money value={k.luong || ""} onChange={(v) => setK({ luong: v })} /></td>
                        <td style={td}><Money value={k.phuCap || ""} onChange={(v) => setK({ phuCap: v })} /></td>
                        <td style={td}><Dec value={k.km ?? ""} onChange={(v) => setK({ km: v })} suffix="km" /></td>
                        <td style={td}><button type="button" title="Xóa dòng" style={delBtn} onClick={() => upd((x) => { x.kcn = (x.kcn || []).filter((_, j) => j !== i); return x; })}><i className="bi bi-x-lg" /></button></td>
                      </tr>
                    );
                  };
                  const head = (showGroup) => <thead><tr>{showGroup && <th style={{ ...th, width: 190 }}>Nhóm cảng</th>}<th style={{ ...th, width: 130 }}>Kho (ký hiệu)</th><th style={th}>Lương chuyến</th><th style={th}>Phụ cấp đi đường</th><th style={{ ...th, width: 110 }}>Km khứ hồi</th><th style={{ width: 40 }} /></tr></thead>;
                  return (
                    <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
                      {blocks.map(({ g, idx }) => (
                        <div key={g.key} id={"reg-kcn-" + g.key} style={{ border: "1px solid var(--line-2)", borderRadius: 9, padding: "8px 10px", scrollMarginTop: 140 }}>
                          <div style={{ display: "flex", alignItems: "center", gap: 6, marginBottom: 4, flexWrap: "wrap" }}>
                            <span style={pill("#eef4ff", "#2563eb")}>{grpLabel(g)}</span>
                            <span style={{ fontSize: 11.5, color: "var(--ink-4)" }}>chuyến lấy cont ở {(g.codes || []).join(", ") || "…"} tra các dòng này</span>
                          </div>
                          <div style={{ overflowX: "auto" }}>
                            <table style={{ width: "100%", borderCollapse: "collapse", minWidth: 560 }}>{head(false)}<tbody>{idx.map((i) => rowOf(i, false))}</tbody></table>
                          </div>
                          {!idx.length && <div style={{ fontSize: 12, color: "var(--ink-4)", padding: "4px 6px" }}>Chưa có dòng KCN nào cho nhóm này.</div>}
                          <button type="button" style={addBtn} onClick={() => upd((x) => { x.kcn = [...(x.kcn || []), { group: g.key, kho: "", luong: "", phuCap: "", km: "" }]; return x; })}><i className="bi bi-plus-lg" /> Thêm KCN cho {g.name || "nhóm này"}</button>
                        </div>
                      ))}
                      {orphan.length > 0 && (
                        <div style={{ border: "1px dashed #e8b860", borderRadius: 9, padding: "8px 10px" }}>
                          <div style={{ fontSize: 12, color: "#b45309", fontWeight: 600, marginBottom: 4 }}>Dòng chưa thuộc nhóm “Bảng KCN” nào — chọn lại nhóm hoặc xóa (không được dùng khi tính):</div>
                          <div style={{ overflowX: "auto" }}>
                            <table style={{ width: "100%", borderCollapse: "collapse", minWidth: 680 }}>{head(true)}<tbody>{orphan.map((i) => rowOf(i, true))}</tbody></table>
                          </div>
                        </div>
                      )}
                    </div>
                  );
                })()}
              </div>

              {/* Trợ cấp theo ngày — dùng chung mọi nhóm cảng (Lộ trình cộng vào chuyến tương ứng, chi trong ngày, tính lương) */}
              <div style={sec} id="reg-allow">
                {secTitle("Trợ cấp trong ngày (dùng chung mọi nhóm)", "Lộ trình tự cộng vào đúng chuyến, chi cho lái trong ngày và tính lương. Nhập 0 nếu không trả.")}
                {[
                  ["turnaround", "Quay đầu tuyến ngoài Hải Phòng — mỗi lần",
                    "Chuyến tra Bảng vùng (xuất phát Hải Phòng) đi kho có tỉnh cũ ngoài HP, mà chuyến kéo cont TRƯỚC đó trong ngày cũng ngoài HP. VD: chuyến 1 Hải Dương, chuyến 2 Bắc Ninh → +tiền ở chuyến 2. Chuyến 1 Hải Phòng, chuyến 2 Bắc Ninh → không. Chuyến ngắn trên Bắc Ninh (xuất phát ICD / Tri Phương) tính như chuyến ngắn Hải Phòng → không xét quay đầu."],
                  ["thirdTrip", "Từ chuyến thứ 3 trong ngày — mỗi chuyến",
                    "Chuyến kéo cont thứ 3, 4, 5… của xe trong ngày vận hành (08:00 → 08:00 hôm sau), đi đâu cũng được → MỖI chuyến +tiền. VD: chạy 5 chuyến → chuyến 3, 4, 5 mỗi chuyến được cộng. Xe vào mà ra không kéo cont không tính là chuyến."],
                ].map(([k, label, hint]) => (
                  <div key={k} style={{ display: "grid", gridTemplateColumns: "minmax(0, 1fr) 160px", gap: 12, alignItems: "center", padding: "8px 0", borderTop: k === "thirdTrip" ? "1px solid var(--line-2)" : "none" }}>
                    <div>
                      <div style={{ fontSize: 13, fontWeight: 600 }}>{label}</div>
                      <div style={{ fontSize: 11.5, color: "var(--ink-4)", lineHeight: 1.5 }}>{hint}</div>
                    </div>
                    <Money value={(d.allowances || {})[k] ?? 100000} onChange={(v) => upd((x) => { x.allowances = { turnaround: 100000, thirdTrip: 100000, ...(x.allowances || {}), [k]: v }; return x; })} />
                  </div>
                ))}
              </div>

              {/* 4. Định mức dầu */}
              <div style={sec} id="reg-fuel">
                {secTitle("Bước 5 · Định mức dầu (lít / 100 km)", "Hàng–Hàng = cont vào có hàng (Nhập) và cont ra có hàng (Xuất), hoặc chuyến Kết hợp; còn lại Hàng–Rỗng. Xe chưa khai số cầu tính như 1 cầu.")}
                <div style={{ display: "grid", gridTemplateColumns: "90px repeat(2, minmax(0, 150px))", gap: "8px 12px", alignItems: "center" }}>
                  <span /><span style={th}>Hàng – Rỗng</span><span style={th}>Hàng – Hàng</span>
                  {[["axle1", "Xe 1 cầu"], ["axle2", "Xe 2 cầu"]].map(([a, label]) => (
                    <React.Fragment key={a}>
                      <span style={{ fontSize: 13, fontWeight: 600 }}>{label}</span>
                      {["hr", "hh"].map((t) => (
                        <Dec key={t} value={((d.fuel || {})[a] || {})[t] ?? ""} suffix="lít"
                          onChange={(v) => upd((x) => { x.fuel = { ...(x.fuel || {}), [a]: { ...((x.fuel || {})[a] || {}), [t]: v } }; return x; })} />
                      ))}
                    </React.Fragment>
                  ))}
                </div>
              </div>

              {/* 5. Km giữa các điểm */}
              <div style={sec} id="reg-dist">
                {secTitle("Bước 6 · Chặng: km + vé trạm (1 chiều)", "Chuyến tính theo quy chế: km và vé trạm (chi hộ, chi trong ngày) = cộng các chặng theo lộ trình thực tế Nơi lấy → các kho → Nơi hạ — không cần khai từng tuyến. Tra đúng chiều trước; không khai chiều ngược thì dùng chung cho 2 chiều (vd TL → TS 35, TS → TL 40). Thiếu km chặng → Lộ trình báo để bổ sung.")}
                <div style={{ overflowX: "auto" }}>
                  <table style={{ width: "100%", borderCollapse: "collapse", minWidth: 580 }}>
                    <thead><tr><th style={th}>Từ</th><th style={th}>Đến</th><th style={{ ...th, width: 120 }}>Km</th><th style={{ ...th, width: 140 }}>Vé trạm</th><th style={{ width: 40 }} /></tr></thead>
                    <tbody>
                      {(d.distances || []).map((r, i) => {
                        const setD = (np) => upd((x) => { x.distances = (x.distances || []).map((o, j) => (j === i ? { ...o, ...np } : o)); return x; });
                        return (
                          <tr key={i} style={{ borderTop: "1px solid var(--line-2)" }}>
                            <td style={td}><Combo value={r.from || ""} options={pointCodes} onChange={(v) => setD({ from: String(v).toUpperCase() })} small placeholder="Điểm…" /></td>
                            <td style={td}><Combo value={r.to || ""} options={pointCodes} onChange={(v) => setD({ to: String(v).toUpperCase() })} small placeholder="Điểm…" /></td>
                            <td style={td}><Dec value={r.km || ""} onChange={(v) => setD({ km: v })} suffix="km" /></td>
                            <td style={td}><Money value={r.toll || ""} onChange={(v) => setD({ toll: v })} /></td>
                            <td style={td}><button type="button" title="Xóa dòng" style={delBtn} onClick={() => upd((x) => { x.distances = (x.distances || []).filter((_, j) => j !== i); return x; })}><i className="bi bi-x-lg" /></button></td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
                <button type="button" style={addBtn} onClick={() => upd((x) => { x.distances = [...(x.distances || []), { from: "", to: "", km: "", toll: "" }]; return x; })}><i className="bi bi-plus-lg" /> Thêm chặng</button>
              </div>
            </>
          )}

          <div style={{ display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap" }}>
            {draft && <Btn onClick={loadDefault}>Nạp mặc định Quy chế 03/2026</Btn>}
            {draft && <Btn onClick={clearAll}>Bỏ quy chế</Btn>}
            <span style={{ flex: 1 }} />
            {dirty && <span style={{ fontSize: 12, color: "#d97706" }}>Quy chế lưu riêng — bấm nút bên phải (nút Lưu chung không lưu phần này).</span>}
            <Btn variant="primary" disabled={!dirty} busy={busy} onClick={save}>Lưu quy chế</Btn>
          </div>
        </div>
      )}
    </div>
  );
}
