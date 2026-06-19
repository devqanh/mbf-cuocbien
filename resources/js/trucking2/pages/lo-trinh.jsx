import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useEffect, useRef } = React;
import { I, useIsMobile, DateField, Combo, Modal, Btn, Money, fmtVND, fmtNum, toNum } from "@trk/lib.jsx";
import { vietqrImg } from "@trk/banks.js";

// Khối thông tin NH của lái nhận tiền: copy STK + QR VietQR quét chuyển khoản. Gọn — QR ẩn, bấm mới hiện.
function PayBankBox({ banks, amount, addInfo }) {
  const list = (banks || []).filter((b) => (b.number || "").trim());
  const [bi, setBi] = useState(0);
  const [showQr, setShowQr] = useState(false);
  if (!list.length) return null;
  const b = list[Math.min(bi, list.length - 1)];
  const copy = () => { try { navigator.clipboard.writeText((b.number || "").replace(/\s/g, "")); window.trkToast && window.trkToast("Đã copy số TK"); } catch (e) {} };
  const qr = showQr ? vietqrImg({ bin: b.bin, account: b.number, name: b.holder, amount, info: addInfo }) : "";
  return (
    <div style={{ marginTop: 8, border: "1px solid var(--line)", borderRadius: 10, background: "#fafcff", overflow: "hidden" }}>
      <div style={{ display: "flex", alignItems: "center", gap: 8, padding: "8px 10px" }}>
        <i className="bi bi-bank2" style={{ color: "var(--accent)", fontSize: 14 }} />
        {list.length > 1 ? (
          <select value={bi} onChange={(e) => setBi(+e.target.value)} style={{ fontSize: 12, fontWeight: 700, border: "1px solid var(--line)", borderRadius: 6, padding: "2px 4px", background: "#fff" }}>
            {list.map((x, k) => <option key={k} value={k}>{x.bank || "NH"}</option>)}
          </select>
        ) : (
          <span style={{ fontWeight: 700, fontSize: 12.5 }}>{b.bank || "Ngân hàng"}</span>
        )}
        <span className="tnum" style={{ fontWeight: 700, fontSize: 13 }}>{b.number || "—"}</span>
        <button type="button" onClick={copy} title="Copy số TK" style={{ width: 24, height: 24, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 6, background: "#fff", color: "var(--ink-4)", cursor: "pointer" }}><i className="bi bi-clipboard" style={{ fontSize: 11 }} /></button>
        {b.holder ? <span style={{ fontSize: 11.5, color: "var(--ink-4)" }}>· {b.holder}</span> : null}
        <span style={{ flex: 1 }} />
        {b.bin ? (
          <button type="button" onClick={() => setShowQr((v) => !v)} style={{ display: "inline-flex", alignItems: "center", gap: 5, fontSize: 12, fontWeight: 600, padding: "4px 9px", border: "1px solid var(--accent)", borderRadius: 7, background: showQr ? "var(--accent)" : "#fff", color: showQr ? "#fff" : "var(--accent)", cursor: "pointer" }}><i className="bi bi-qr-code" /> {showQr ? "Ẩn QR" : "Mã QR"}</button>
        ) : <span style={{ fontSize: 11, color: "var(--ink-4)" }}>Chọn NH ở Cài đặt để có QR</span>}
      </div>
      {qr ? (
        <div style={{ display: "flex", justifyContent: "center", padding: "4px 10px 12px" }}>
          <img src={qr} alt="VietQR" style={{ width: 220, maxWidth: "100%", borderRadius: 8, border: "1px solid var(--line-2)" }} />
        </div>
      ) : null}
    </div>
  );
}

/* Popup chi cho lái xe (theo ngày + xe): tổng các khoản "chi theo ngày" từ Phí tuyến + chọn lái nhận. */
function PayPopup({ truck, date, drivers, routeFeesUrl, onClose, onSaved }) {
  const [driver, setDriver] = useState(truck.payDriver || "");
  const [paid, setPaid] = useState(!!truck.paid);
  const [saving, setSaving] = useState(false);
  // Mỗi CHUYẾN = 1 nhóm (backend đã gom, kể cả chuyến KHÔNG khớp phí tuyến → có note cảnh báo).
  const groups = truck.payGroups || [];
  // Chi khác phát sinh THỦ CÔNG theo từng chuyến — state song song groups, init từ g.manual (đã lưu).
  const [extras, setExtras] = useState(() => groups.map((g) => (g.manual || []).map((m) => ({ ...m }))));
  const setGE = (gi, arr) => setExtras((prev) => prev.map((x, j) => (j === gi ? arr : x)));
  const updEx = (gi, k, np) => setGE(gi, (extras[gi] || []).map((m, j) => (j === k ? { ...m, ...np } : m)));
  const addEx = (gi) => setGE(gi, [...(extras[gi] || []), { name: "", amount: "", perDay: true }]);
  const delEx = (gi, k) => setGE(gi, (extras[gi] || []).filter((_, j) => j !== k));

  const computedSub = (g) => (g.items || []).reduce((s, it) => s + (it.amount || 0), 0);
  const manualSub = (gi) => (extras[gi] || []).reduce((s, m) => s + (m.perDay !== false ? toNum(m.amount) : 0), 0);
  const groupTotal = (g, gi) => computedSub(g) + manualSub(gi);
  const total = groups.reduce((s, g, gi) => s + groupTotal(g, gi), 0);
  // Dầu = CHI PHÍ CÔNG TY (không tính vào tiền lái) — tổng lít + tiền theo các chuyến.
  const fuelLiters = groups.reduce((s, g) => s + ((g.fuel && g.fuel.liters) || 0), 0);
  const fuelAmount = groups.reduce((s, g) => s + ((g.fuel && g.fuel.amount) || 0), 0);
  // Cảnh báo: chuyến KHÔNG khớp phí tuyến VÀ chưa thêm chi khác thủ công nào.
  const warnCount = groups.filter((g, gi) => g.note && manualSub(gi) <= 0).length;

  const save = () => {
    if (saving) return; setSaving(true);
    const extraItems = [];
    groups.forEach((g, gi) => (extras[gi] || []).forEach((m) => {
      if (!(m.name || "").trim() && !toNum(m.amount)) return;   // bỏ dòng rỗng
      extraItems.push({ cont: g.cont || "", name: m.name || "", amount: m.amount, perDay: m.perDay !== false });
    }));
    window.trkApi("POST", (window.__TRK.routes || {}).savePay, { date, bks: truck.bks, driver, paid, extraItems })
      .then((r) => { if (r && r.ok) { window.trkToast && window.trkToast("Đã lưu chi cho lái"); onSaved({ payDriver: driver, paid }); onClose(); } else { window.trkToast && window.trkToast("Lưu thất bại", "error"); setSaving(false); } })
      .catch(() => { window.trkToast && window.trkToast("Lỗi kết nối", "error"); setSaving(false); });
  };
  const footer = (
    <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 10 }}>
      <a href={routeFeesUrl} target="_blank" rel="noreferrer" style={{ fontSize: 12.5, color: "var(--accent)", textDecoration: "none" }}><i className="bi bi-gear" /> Sửa phí tuyến</a>
      <div style={{ display: "flex", gap: 8 }}><Btn onClick={onClose}>Đóng</Btn><Btn variant="primary" onClick={save} disabled={saving}>{saving ? "Đang lưu…" : "Lưu"}</Btn></div>
    </div>
  );
  // Hàng nhập 1 khoản chi khác thủ công (tên + tiền + xóa)
  const exRow = (gi, m, k) => (
    <div key={"m" + k} style={{ display: "flex", alignItems: "center", gap: 8, padding: "6px 12px", borderTop: "1px solid var(--line-2)", background: "#fcfdff" }}>
      <i className="bi bi-plus-circle" style={{ color: "var(--accent)", fontSize: 12 }} />
      <input value={m.name || ""} onChange={(e) => updEx(gi, k, { name: e.target.value })} placeholder="Tên khoản phát sinh"
        style={{ flex: 1, minWidth: 0, padding: "5px 8px", fontSize: 12.5, border: "1px solid var(--line)", borderRadius: 7, outline: "none" }} />
      <div style={{ width: 130 }}><Money value={m.amount} onChange={(x) => updEx(gi, k, { amount: x })} dim /></div>
      <button type="button" onClick={() => delEx(gi, k)} title="Xóa khoản" style={{ flexShrink: 0, width: 26, height: 26, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 7, background: "#fff", color: "var(--ink-4)", cursor: "pointer" }}><I.x /></button>
    </div>
  );
  return (
    <Modal title={"Chi cho lái xe · " + truck.bks} subtitle={"Ngày " + date + " · phí tuyến (chi theo ngày) + chi khác phát sinh từng chuyến"} onClose={onClose} footer={footer} width={580} icon={<i className="bi bi-cash-coin" />}>
      <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
        {groups.length === 0 ? (
          <div style={{ padding: "14px", fontSize: 13, color: "var(--ink-4)", background: "#fafbfc", border: "1px dashed var(--line)", borderRadius: 10 }}>Xe không có chuyến nào trong ngày.</div>
        ) : (
          <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
            {warnCount > 0 && (
              <div style={{ display: "flex", alignItems: "flex-start", gap: 8, padding: "9px 12px", fontSize: 12.5, color: "#a05a00", background: "#fff7e9", border: "1px solid #f1d59a", borderRadius: 10 }}>
                <i className="bi bi-exclamation-triangle-fill" style={{ marginTop: 1 }} />
                <span><b>{warnCount}/{groups.length} chuyến chưa ra tiền</b> — kiểm tra Phí tuyến, hoặc thêm <b>chi khác</b> phát sinh cho chuyến đó bên dưới.</span>
              </div>
            )}
            {groups.map((g, gi) => {
              const warn = !!g.note && manualSub(gi) <= 0;
              const gt = groupTotal(g, gi);
              return (
              <div key={gi} style={{ border: "1px solid " + (warn ? "#f1d59a" : "var(--line)"), borderRadius: 10, overflow: "hidden" }}>
                {/* Header tuyến: lộ trình + cont + tổng phụ của chuyến */}
                <div style={{ display: "flex", alignItems: "center", gap: 8, padding: "8px 12px", background: warn ? "#fff7e9" : "var(--accent-weak)", borderBottom: "1px solid var(--line-2)" }}>
                  <i className={"bi " + (warn ? "bi-exclamation-triangle-fill" : "bi-signpost-split")} style={{ color: warn ? "#c9820f" : "var(--accent)", fontSize: 13 }} />
                  <span className="tnum" style={{ fontWeight: 700, fontSize: 12.5, color: warn ? "#a05a00" : "var(--accent)" }}>{g.route}</span>
                  {g.cont ? <span className="tnum" style={{ fontSize: 11, color: "var(--ink-4)", fontWeight: 600 }}>· cont {g.cont}</span> : null}
                  <span style={{ flex: 1 }} />
                  <span className="tnum" style={{ fontWeight: 700, fontSize: 12.5, color: warn ? "#a05a00" : "var(--ink-1)" }}>{warn ? "—" : fmtVND(gt)}</span>
                </div>
                {/* Khoản từ phí tuyến (tự tính, chỉ đọc) */}
                {(g.items || []).map((it, i) => (
                  <div key={i} style={{ display: "flex", alignItems: "center", gap: 8, padding: "7px 12px", borderTop: i ? "1px solid var(--line-2)" : "none", fontSize: 13 }}>
                    <span style={{ fontWeight: 600 }}>{it.label}</span>
                    {it.liters != null && it.unitPrice != null
                      ? <span style={{ fontSize: 11.5, color: "var(--ink-4)" }} className="tnum">{fmtNum(it.liters)} lít × {fmtVND(it.unitPrice)}/lít</span>
                      : null}
                    <span style={{ flex: 1 }} />
                    <span className="tnum" style={{ fontWeight: 600 }}>{fmtVND(it.amount)}</span>
                  </div>
                ))}
                {/* Dầu = chi phí công ty (KHÔNG tính vào tiền lái) — hiển thị lít + tiền công ty trả */}
                {g.fuel ? (
                  <div style={{ display: "flex", alignItems: "center", gap: 8, padding: "7px 12px", borderTop: "1px solid var(--line-2)", fontSize: 13, background: "#f5f9ff" }}>
                    <i className="bi bi-fuel-pump-fill" style={{ color: "#2563eb" }} />
                    <span style={{ fontWeight: 600, color: "#2563eb" }}>Dầu {g.fuel.axle} cầu <span style={{ fontWeight: 400, fontSize: 11.5, color: "var(--ink-4)" }}>· công ty trả</span></span>
                    <span className="tnum" style={{ fontSize: 11.5, color: "var(--ink-4)" }}>{fmtNum(g.fuel.liters)} lít × {fmtVND(g.fuel.unitPrice)}/lít</span>
                    <span style={{ flex: 1 }} />
                    <span className="tnum" style={{ fontWeight: 700, color: "#2563eb" }}>{fmtVND(g.fuel.amount)}</span>
                  </div>
                ) : null}
                {g.note && !(g.items || []).length ? (
                  <div style={{ padding: "8px 12px", fontSize: 12, color: "#a05a00" }}>{g.note} <span style={{ color: "var(--ink-4)" }}>· có thể thêm chi khác phát sinh bên dưới</span></div>
                ) : null}
                {/* Chi khác phát sinh THỦ CÔNG của chuyến này */}
                {(extras[gi] || []).map((m, k) => exRow(gi, m, k))}
                <button type="button" onClick={() => addEx(gi)} style={{ width: "100%", display: "inline-flex", alignItems: "center", justifyContent: "center", gap: 6, padding: "7px 12px", fontSize: 12, fontWeight: 600, borderTop: "1px dashed var(--line)", background: "#fff", color: "var(--accent)", cursor: "pointer", border: "none" }}>
                  <I.plus /> Thêm chi khác phát sinh
                </button>
              </div>
              );
            })}
            <div style={{ display: "flex", alignItems: "center", padding: "10px 12px", border: "1.5px solid var(--accent)", borderRadius: 10, background: "var(--accent-weak)" }}>
              <span style={{ fontWeight: 700 }}>Tổng chi cho lái <span style={{ fontWeight: 400, fontSize: 12, color: "var(--ink-4)" }}>({groups.length} chuyến{warnCount > 0 ? ", " + warnCount + " chưa ra tiền" : ""})</span></span><span style={{ flex: 1 }} />
              <span className="tnum" style={{ fontWeight: 800, fontSize: 16, color: "var(--accent)" }}>{fmtVND(total)}</span>
            </div>
            {fuelAmount > 0 && (
              <div style={{ display: "flex", alignItems: "center", gap: 8, padding: "9px 12px", border: "1px solid #cfe0ff", borderRadius: 10, background: "#f5f9ff" }}>
                <i className="bi bi-fuel-pump-fill" style={{ color: "#2563eb" }} />
                <span style={{ fontWeight: 700, color: "#2563eb" }}>Dầu — chi phí công ty <span style={{ fontWeight: 400, fontSize: 12, color: "var(--ink-4)" }}>· {fmtNum(fuelLiters)} lít · không tính vào tiền lái</span></span>
                <span style={{ flex: 1 }} />
                <span className="tnum" style={{ fontWeight: 800, fontSize: 15, color: "#2563eb" }}>{fmtVND(fuelAmount)}</span>
              </div>
            )}
          </div>
        )}
        <div style={{ display: "flex", gap: 12, flexWrap: "wrap", alignItems: "flex-end" }}>
          <div style={{ flex: 1, minWidth: 200 }}>
            <div style={{ fontSize: 11.5, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>Lái xe nhận tiền</div>
            <Combo value={driver} onChange={setDriver} options={(drivers || []).map((d) => d.name)} placeholder="Chọn lái xe…" small />
          </div>
          <label style={{ display: "inline-flex", alignItems: "center", gap: 7, fontSize: 13, fontWeight: 600, color: paid ? "var(--good)" : "var(--ink-3)", cursor: "pointer", padding: "9px 0" }}>
            <input type="checkbox" checked={paid} onChange={() => setPaid((v) => !v)} style={{ accentColor: "var(--good)", cursor: "pointer", margin: 0 }} /> Đã chi cho lái
          </label>
        </div>
        {/* Lái đã chọn → hiện NH + QR VietQR (copy STK / quét chuyển khoản đúng số tiền) */}
        {(() => { const d = (drivers || []).find((x) => x.name === driver); return d && (d.banks || []).length
          ? <PayBankBox banks={d.banks} amount={total} addInfo={"Chi lai xe " + truck.bks + " " + date} /> : null; })()}
      </div>
    </Modal>
  );
}

const z = (n) => String(n).padStart(2, "0");
const toYmd = (d) => `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}`;

// self = lấy chính cont này ra · none = ra xe không kéo cont (chạy xe không) · other = kéo cont khác ra
const MODE = {
  self:  { label: "Kéo cont ra",     color: "#1f8a5b", bg: "var(--good-weak)", icon: "bi-box-arrow-right" },
  none:  { label: "Ra xe không cont", color: "#8a94a6", bg: "#eef0f3",          icon: "bi-truck" },
  other: { label: "Kéo cont khác",   color: "#e08600", bg: "#fcf3e2",          icon: "bi-arrow-left-right" },
};
// điểm hành trình: nơi lấy → kho → nơi hạ
const PT = {
  pickup: { color: "#2a6fdb", icon: "bi-box-arrow-up-right" },
  kho:    { color: "#4f46e5", icon: "bi-buildings-fill" },
  drop:   { color: "#1f8a5b", icon: "bi-geo-alt-fill" },
};

// Câu mô tả hành động của 1 hoạt động trong ngày
function actionNode(l) {
  if (l.mode === "none") return l.from
    ? <>Vào <span className="tnum">{l.from}</span> <span style={{ color: "var(--ink-4)", fontWeight: 400 }}>— ra xe không kéo cont</span></>
    : <>Ra xe <span style={{ color: "var(--ink-3)", fontWeight: 500 }}>— chạy xe không (chưa kéo cont)</span></>;
  if (l.mode === "other") return <>Kéo cont khác <span className="tnum">{l.refCont || "—"}</span> ra <span style={{ color: "var(--ink-4)", fontWeight: 400 }}>· cont {l.cont || "—"} còn chờ</span></>;
  return <>Lấy cont <span className="tnum">{l.cont || "—"}</span> ra</>;
}

// Chuỗi điểm hành trình (Nơi lấy → Kho → Nơi hạ) với mũi tên
function PointChain({ points }) {
  if (!points || !points.length) return <span style={{ color: "var(--ink-4)" }}>—</span>;
  return (
    <span style={{ display: "inline-flex", alignItems: "center", gap: 4, flexWrap: "wrap" }}>
      {points.map((p, i) => { const c = PT[p.kind] || PT.kho; return (
        <React.Fragment key={i}>
          {i > 0 && <i className="bi bi-arrow-right" style={{ color: "var(--ink-4)", fontSize: 11 }} />}
          <span style={{ display: "inline-flex", alignItems: "center", gap: 4, fontSize: 12, fontWeight: 600, color: c.color, background: "var(--bg)", border: "1px solid var(--line-2)", borderRadius: 999, padding: "2px 9px" }}>
            <i className={"bi " + c.icon} style={{ fontSize: 11 }} />{p.label}
          </span>
        </React.Fragment>
      ); })}
    </span>
  );
}

// 1 hoạt động = 1 nút trên đường rail dọc (nối liền nhau thành lộ trình cả ngày)
function TripNode({ l, isFirst, isLast, bks, href, fuel }) {
  const m = MODE[l.mode] || MODE.self;
  // Xe không kéo cont → chỉ hiện NƠI LẤY (chỗ xe vào), không vẽ tới nơi hạ vì chưa giao cont nào.
  const pts = l.mode === "none" ? (l.points || []).filter((p) => p.kind === "pickup") : l.points;
  return (
    <a href={href} title="Xem lô hàng"
      style={{ display: "flex", alignItems: "stretch", gap: 12, textDecoration: "none", color: "inherit" }}
      onMouseEnter={(e) => (e.currentTarget.querySelector(".trk-trip-body").style.background = "var(--accent-weak-2)")}
      onMouseLeave={(e) => (e.currentTarget.querySelector(".trk-trip-body").style.background = "transparent")}>
      {/* RAIL: đường dọc + chấm mốc */}
      <div style={{ position: "relative", width: 30, flexShrink: 0 }}>
        <div style={{ position: "absolute", left: 14, width: 2, background: "var(--line-2)", top: isFirst ? 22 : 0, bottom: isLast ? "calc(100% - 22px)" : 0 }} />
        <div style={{ position: "absolute", left: 7, top: 15, width: 16, height: 16, borderRadius: "50%", background: m.color, border: "3px solid #fff", boxShadow: "0 0 0 1.5px " + m.color, display: "grid", placeItems: "center" }}>
          <i className={"bi " + m.icon} style={{ fontSize: 8, color: "#fff" }} />
        </div>
      </div>
      {/* NỘI DUNG */}
      <div className="trk-trip-body" style={{ flex: 1, minWidth: 0, padding: "10px 12px", borderRadius: 10, marginBottom: 2, transition: "background .12s" }}>
        <div style={{ display: "flex", alignItems: "baseline", gap: 10, flexWrap: "wrap" }}>
          <span className="tnum" style={{ fontWeight: 700, fontSize: 14, color: m.color }}>{l.timeLabel}</span>
          <span style={{ fontSize: 13.5, fontWeight: 600 }}>{actionNode(l)}</span>
        </div>
        <div style={{ marginTop: 6 }}><PointChain points={pts} /></div>
        {/* CHI TIẾT: xe đưa cont VÀO + xe đưa cont (số nào) RA */}
        <div style={{ marginTop: 7, display: "flex", flexDirection: "column", gap: 3, fontSize: 11.5, lineHeight: 1.5 }}>
          <div style={{ color: "var(--ink-3)" }}>
            <i className="bi bi-box-arrow-in-down" style={{ color: "#2a6fdb" }} /> <span style={{ color: "var(--ink-4)" }}>Xe vào:</span>{" "}
            {l.mode === "none"
              ? <><b className="tnum">{l.bks}</b> đã vào nơi lấy <b className="tnum">{l.from || "—"}</b>{l.gioDenLabel ? <> lúc <b className="tnum">{l.gioDenLabel}</b></> : <span style={{ color: "var(--ink-4)" }}> (chưa có giờ xe đến)</span>}</>
              : <><b className="tnum">{l.bks}</b> đưa cont <b className="tnum">{l.cont || "—"}</b> vào{l.gioDenLabel && <span style={{ color: "var(--ink-4)" }}> · vào kho {l.gioDenLabel}</span>}</>}
          </div>
          <div style={{ color: "var(--ink-3)" }}>
            <i className="bi bi-box-arrow-up" style={{ color: m.color }} /> <span style={{ color: "var(--ink-4)" }}>Xe ra:</span>{" "}
            {l.mode === "none"
              ? <><b className="tnum">{l.bks}</b> ra <span style={{ color: "var(--ink-4)" }}>không kéo cont — cont {l.cont || "—"} vẫn chưa ra</span> · <b className="tnum">{l.timeLabel}</b></>
              : l.mode === "other"
                ? <><b className="tnum">{l.bks}</b> kéo cont khác <b className="tnum">{l.refCont || "—"}</b> ra · <b className="tnum">{l.timeLabel}</b>{l.refBksVao && <span style={{ color: "var(--ink-4)" }}> (cont {l.refCont} do xe {l.refBksVao} đưa vào)</span>}</>
                : <><b className="tnum">{(l.bksRa && l.bksRa !== l.bks) ? l.bksRa : l.bks}</b> đưa cont <b className="tnum">{l.cont || "—"}</b> ra · <b className="tnum">{l.timeLabel}</b>{(l.bksRa && l.bksRa !== l.bks) && <span style={{ color: "var(--warn)" }}> (khác xe vào {l.bks})</span>}</>}
          </div>
        </div>
        {(l.customer || l.booking) && <div style={{ fontSize: 11, color: "var(--ink-4)", marginTop: 6 }}>{l.customer}{l.booking ? <span className="tnum"> · {l.booking}</span> : null}</div>}
        {/* Dầu chuyến này = chi phí công ty (lít × giá dầu theo ngày) */}
        {fuel ? (
          <div style={{ display: "inline-flex", alignItems: "center", gap: 5, marginTop: 6, padding: "2px 9px", fontSize: 11, fontWeight: 600, borderRadius: 999, background: "#eef4ff", color: "#2563eb", border: "1px solid #cfe0ff" }}>
            <i className="bi bi-fuel-pump-fill" /> Dầu {fuel.axle} cầu: <span className="tnum">{fmtNum(fuel.liters)} lít × {fmtVND(fuel.unitPrice)}/lít = {fmtVND(fuel.amount)}</span> <span style={{ fontWeight: 400, color: "var(--ink-4)" }}>· công ty trả</span>
          </div>
        ) : null}
      </div>
    </a>
  );
}

function LoTrinhApp() {
  const isMobile = useIsMobile();
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const api = (m, u) => window.trkApi(m, u);
  const drivers = B.drivers || [];

  const [date, setDate] = useState(toYmd(new Date()));
  const [data, setData] = useState(null);   // null = đang tải
  const [showExt, setShowExt] = useState(false);   // mặc định CHỈ xe MBF; bật để xem xe ngoài chạy
  const [payTruck, setPayTruck] = useState(null);   // xe đang mở popup "chi cho lái"
  const [freezing, setFreezing] = useState(false);
  const reqId = useRef(0);
  // Cập nhật lái nhận/đã chi vào state cục bộ sau khi lưu (khỏi tải lại cả trang).
  const applyPay = (bks, np) => setData((d) => d ? { ...d, trucks: (d.trucks || []).map((t) => (t.bks === bks ? { ...t, ...np } : t)) } : d);

  const load = () => {
    const my = ++reqId.current; setData(null);
    api("GET", ROUTES.data + "?date=" + encodeURIComponent(date)).then((r) => {
      if (my !== reqId.current) return;
      setData(r && r.ok ? r : { trucks: [], totalLegs: 0, start: 0, end: 0 });
    }).catch(() => { if (my === reqId.current) setData({ trucks: [], totalLegs: 0 }); });
  };
  useEffect(() => { load(); }, [date]);

  const shiftDay = (n) => { const d = new Date(date + "T12:00:00"); d.setDate(d.getDate() + n); setDate(toYmd(d)); };
  const allTrucks = data?.trucks || [];
  const isMbf = (t) => t.matched && t.type !== "Ngoài";   // xe MBF = khớp đội xe & không phải "Ngoài"
  const extCount = allTrucks.length - allTrucks.filter(isMbf).length;
  const trucks = showExt ? allTrucks : allTrucks.filter(isMbf);   // mặc định chỉ MBF
  const visLegs = trucks.reduce((a, t) => a + t.legs.length, 0);
  const allFrozen = allTrucks.length > 0 && allTrucks.every((t) => t.frozen);

  // Chốt / bỏ chốt cả ngày: đóng băng số tiền chi cho lái (không đổi khi sửa Phí tuyến).
  const doFreeze = async (frozen) => {
    if (freezing || !allTrucks.length) return;
    const ok = await window.confirmAction({
      title: frozen ? "Chốt (đóng băng) ngày này?" : "Bỏ chốt ngày này?",
      text: frozen
        ? `Số tiền chi cho lái của <b>${allTrucks.length} xe</b> ngày <b>${date}</b> sẽ được <b>đóng băng</b> — không đổi dù sau này sửa Phí tuyến.`
        : `Bỏ đóng băng ngày <b>${date}</b> — số tiền sẽ tính lại theo Phí tuyến hiện tại.`,
      confirmText: frozen ? '<i class="bi bi-lock me-1"></i> Chốt ngày' : '<i class="bi bi-unlock me-1"></i> Bỏ chốt',
    });
    if (!ok) return;
    setFreezing(true);
    try {
      const r = await window.trkApi("POST", ROUTES.freeze, { date, frozen });
      if (r && r.ok) { window.trkToast && window.trkToast(frozen ? "Đã chốt ngày" : "Đã bỏ chốt"); load(); }
      else window.trkToast && window.trkToast("Thao tác thất bại", "error");
    } catch (e) { window.trkToast && window.trkToast("Lỗi kết nối", "error"); }
    setFreezing(false);
  };

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: isMobile ? "10px 14px" : "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 12, height: isMobile ? "auto" : 58, flexWrap: "wrap" }}>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center", flexShrink: 0 }}><i className="bi bi-signpost-split-fill" /></div>
          <div>
            <div style={{ fontSize: 15.5, fontWeight: 700 }}>Lộ trình lái xe trong ngày</div>
            <div style={{ fontSize: 11.5, color: "var(--ink-3)" }}>
              {data ? <>Ngày vận hành <b>{data.startLabel} 08:00</b> → <b>{data.endLabel} 08:00</b> · {trucks.length} xe{showExt ? "" : " MBF"} · {visLegs} hoạt động</> : "Đang tải…"}
            </div>
          </div>
          <div style={{ flex: 1 }} />
          <div style={{ display: "inline-flex", alignItems: "center", gap: 6, flexWrap: "wrap" }}>
            {(extCount > 0 || showExt) && (
              <button type="button" onClick={() => setShowExt((v) => !v)} title={showExt ? "Chỉ hiện xe MBF" : "Hiện cả xe ngoài chạy"}
                style={{ display: "inline-flex", alignItems: "center", gap: 6, height: 32, padding: "0 12px", fontSize: 12.5, fontWeight: 600, borderRadius: 9, cursor: "pointer",
                  border: "1px solid " + (showExt ? "var(--accent)" : "var(--line)"), background: showExt ? "var(--accent-weak-2)" : "#fff", color: showExt ? "var(--accent)" : "var(--ink-2)" }}>
                <i className={"bi " + (showExt ? "bi-eye-fill" : "bi-eye")} /> {showExt ? "Đang hiện xe ngoài" : `Xem xe ngoài (${extCount})`}
              </button>
            )}
            {T.canEdit && allTrucks.length > 0 && (
              <button type="button" onClick={() => doFreeze(!allFrozen)} disabled={freezing} title={allFrozen ? "Bỏ đóng băng (tính lại theo phí tuyến)" : "Đóng băng số tiền chi cho lái ngày này"}
                style={{ display: "inline-flex", alignItems: "center", gap: 6, height: 32, padding: "0 12px", fontSize: 12.5, fontWeight: 700, borderRadius: 9, cursor: "pointer",
                  border: "1px solid " + (allFrozen ? "var(--good)" : "var(--accent)"), background: allFrozen ? "var(--good-weak)" : "var(--accent-weak)", color: allFrozen ? "var(--good)" : "var(--accent)" }}>
                <i className={"bi " + (allFrozen ? "bi-lock-fill" : "bi-snow")} /> {allFrozen ? "Đã chốt — Bỏ chốt" : "Chốt ngày"}
              </button>
            )}
            <button type="button" onClick={() => shiftDay(-1)} title="Ngày trước" style={btnIcon}>‹</button>
            <div style={{ width: 150 }}><DateField value={date} onChange={setDate} /></div>
            <button type="button" onClick={() => shiftDay(1)} title="Ngày sau" style={btnIcon}>›</button>
            <button type="button" onClick={() => setDate(toYmd(new Date()))} style={{ ...btnIcon, width: "auto", padding: "0 12px", fontSize: 13, fontWeight: 600 }}>Hôm nay</button>
          </div>
        </div>
      </header>

      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: isMobile ? "12px 12px 24px" : "16px 22px 24px" }}>
        <div style={{ maxWidth: 1040, margin: "0 auto" }}>
          {!data ? (
            <div style={{ padding: "40px", textAlign: "center", color: "var(--ink-4)" }}><i className="bi bi-arrow-repeat" style={{ animation: "trk-spin .7s linear infinite" }} /> Đang tải…</div>
          ) : trucks.length === 0 ? (
            <div style={{ padding: "44px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5, background: "#fff", border: "1px solid var(--line)", borderRadius: 12 }}>
              {!showExt && extCount > 0
                ? <>Không có <b>xe MBF</b> chạy trong ngày này — nhưng có <b>{extCount} xe ngoài</b>. Bấm <b>“Xem xe ngoài ({extCount})”</b> ở trên để xem.</>
                : <>Không có xe nào hoạt động trong ngày này (theo giờ xe ra). Chọn ngày khác hoặc kiểm tra giờ xe ra của lô.</>}
            </div>
          ) : (
            <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
              {trucks.map((tr) => (
                <div key={tr.bks} style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, overflow: "hidden" }}>
                  <div style={{ display: "flex", alignItems: "center", gap: 10, padding: "11px 14px", borderBottom: "1px solid var(--line-2)", background: "#fafbfc" }}>
                    <i className="bi bi-truck-front-fill" style={{ color: "var(--accent)" }} />
                    <span className="tnum" style={{ fontWeight: 700, fontSize: 15 }}>{tr.bks}</span>
                    {tr.matched
                      ? <span style={{ fontSize: 10.5, fontWeight: 700, color: "var(--good)", background: "var(--good-weak)", padding: "1px 7px", borderRadius: 999 }}>✓ {tr.type === "Ngoài" ? "Xe ngoài" : "Xe MBF"}</span>
                      : <span style={{ fontSize: 10.5, color: "var(--ink-4)" }}>(ngoài hệ thống)</span>}
                    {(tr.axle === "1" || tr.axle === "2") && <span title="Số cầu xe" style={{ fontSize: 10.5, fontWeight: 700, color: "var(--accent)", background: "var(--accent-weak)", padding: "1px 7px", borderRadius: 999 }}>{tr.axle} cầu</span>}
                    {tr.frozen && <span title="Đã chốt (số tiền đóng băng)" style={{ fontSize: 10.5, fontWeight: 700, color: "#2563eb", background: "#e7efff", padding: "1px 7px", borderRadius: 999 }}><i className="bi bi-lock-fill" /> Đã chốt</span>}
                    <span style={{ flex: 1 }} />
                    <span style={{ fontSize: 12, color: "var(--ink-3)", fontWeight: 600, marginRight: 4 }}>{tr.legs.length} hoạt động</span>
                    {/* Dầu = chi phí công ty (không chi cho lái) */}
                    {tr.fuelTotal > 0 && (
                      <span title={"Dầu công ty: " + fmtNum(tr.fuelLiters) + " lít"} style={{ display: "inline-flex", alignItems: "center", gap: 5, padding: "5px 10px", fontSize: 12, fontWeight: 700, borderRadius: 999, border: "1px solid #cfe0ff", background: "#eef4ff", color: "#2563eb", whiteSpace: "nowrap" }}>
                        <i className="bi bi-fuel-pump-fill" /> Dầu (cty): <span className="tnum">{fmtVND(tr.fuelTotal)}</span><span style={{ fontWeight: 500 }}>· {fmtNum(tr.fuelLiters)} l</span>
                      </span>
                    )}
                    {/* Chi cho lái: tổng các khoản "chi theo ngày" + lái nhận */}
                    <button type="button" onClick={() => setPayTruck(tr)} title="Chi cho lái xe (theo phí tuyến)"
                      style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "5px 11px", fontSize: 12.5, fontWeight: 700, borderRadius: 999, cursor: "pointer", whiteSpace: "nowrap",
                        border: "1px solid " + (tr.paid ? "var(--good)" : (tr.payTotal > 0 ? "var(--accent)" : "var(--line)")),
                        background: tr.paid ? "var(--good-weak)" : (tr.payTotal > 0 ? "var(--accent-weak)" : "#fff"),
                        color: tr.paid ? "var(--good)" : (tr.payTotal > 0 ? "var(--accent)" : "var(--ink-3)") }}>
                      <i className={"bi " + (tr.paid ? "bi-cash-coin" : "bi-cash")} /> Chi lái: <span className="tnum">{fmtVND(tr.payTotal || 0)}</span>
                      {tr.payDriver ? <span style={{ fontWeight: 500 }}>· {tr.payDriver}</span> : null}
                      {tr.paid ? <span title="Đã chi">✓</span> : null}
                      {tr.payWarn > 0 ? <i className="bi bi-exclamation-triangle-fill" title={tr.payWarn + " chuyến chưa ra tiền (kiểm tra phí tuyến)"} style={{ color: "#c9820f" }} /> : null}
                    </button>
                  </div>
                  {/* LỘ TRÌNH 1 NGÀY: timeline dọc nối liền các hoạt động */}
                  <div style={{ padding: "8px 12px 10px" }}>
                    {tr.legs.map((l, i) => (
                      <TripNode key={i} l={l} isFirst={i === 0} isLast={i === tr.legs.length - 1} bks={tr.bks} fuel={(tr.payGroups || [])[i] && (tr.payGroups || [])[i].fuel} href={ROUTES.shipment + (l.cont ? "?q=" + encodeURIComponent(l.cont) + "&open=1" : "")} />
                    ))}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
      {payTruck && <PayPopup truck={payTruck} date={date} drivers={drivers} routeFeesUrl={ROUTES.routeFees}
        onClose={() => setPayTruck(null)} onSaved={(np) => applyPay(payTruck.bks, np)} />}
    </div>
  );
}

const btnIcon = { width: 32, height: 32, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 9, background: "#fff", cursor: "pointer", fontSize: 16, color: "var(--ink-2)", flexShrink: 0 };

createRoot(document.getElementById("trk-root")).render(<LoTrinhApp />);
