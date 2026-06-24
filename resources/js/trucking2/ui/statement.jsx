import React from "react";
const { useState, useMemo, useEffect } = React;
import { I, fmtVND, fmtNum, fmtShort, fmtDate, calcCost, calcRev, calcVeh, calcVehICD, calcRevICD, calcFreeTime, fmtHours, toNum, Modal, Btn, Combo, useIsMobile, DateField, statementAmounts, lineAmounts, STATEMENT_VAT_RATES } from "@trk/lib.jsx";
import { CostPopup, RevenuePopup, CostPopupICD, RevenuePopupICD, InfoPopup, ConfigPopup, PriceList, TRACK_COLORS, colorHex } from "@trk/pop.jsx";
import { SortBtn, CellBtn, Badge, EditCell, TH, TD } from "./primitives.jsx";

/* Nhãn ngắn cho 1 bảng giá (price book) đã dùng định giá lô — hiện kỳ giá ở bảng kê. */
const _bd = (s) => { if (!s) return ""; const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s); return m ? `${m[3]}/${m[2]}/${m[1]}` : s; };
const bookLabel = (b) => { if (!b) return ""; const r = (b.from || b.to) ? `${b.from ? _bd(b.from) : "…"}–${b.to ? _bd(b.to) : "…"}` : "mọi ngày"; return (b.label ? b.label + " · " : "") + r; };

/* Thông tin công ty cho header bảng kê (màn hình + bản in). */
const CO = (window.__TRK && window.__TRK.boot && window.__TRK.boot.company) || {};
const ROUTES_TRK = (window.__TRK && window.__TRK.routes) || {};
const CO_NAME = CO.name || "MBF JOINT STOCK COMPANY";
const CO_SUB = [CO.website, CO.phone].filter(Boolean).join(" · ") || "http://mbf.com.vn · 84-24-39449616";

/* Select % VAT cho bảng kê (cấp statement). VAT chỉ áp nền cước+dầu+sà lan. */
function VatSelect({ value, onChange }) {
  return (
    <select value={String(value || 0)} onChange={(e) => onChange(parseFloat(e.target.value) || 0)}
      style={{ padding: "6px 10px", fontSize: 13, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink)", cursor: "pointer", outline: "none" }}>
      {STATEMENT_VAT_RATES.map((r) => <option key={r} value={r}>{r}%</option>)}
    </select>
  );
}

/* Khối tổng tiền 4 dòng (dùng chung tạo + xem): nền · VAT · chi hộ · tổng (+ công nợ nếu có). */
function AmountSummary({ amt, vatRate, paid, payCount, showDebt }) {
  const con = (amt.total || 0) - (paid || 0);
  const row = (label, val, opt = {}) => (
    <div style={{ display: "flex", justifyContent: "space-between", fontSize: 13, marginBottom: 6, ...(opt.wrap || {}) }}>
      <span style={{ color: "var(--ink-3)", ...(opt.labelStyle || {}) }}>{label}</span>
      <b className="tnum" style={opt.valStyle || {}}>{fmtVND(val)}</b>
    </div>
  );
  return (
    <div style={{ width: 320, background: "#fafbfc", border: "1px solid var(--line)", borderRadius: 10, padding: "12px 16px" }}>
      {row("Phải thu (cước + dầu)", amt.base)}
      {row(`VAT (${vatRate || 0}%)`, amt.vat, { valStyle: { color: (vatRate ? "var(--accent)" : "var(--ink-4)") } })}
      {row("Chi hộ (không VAT)", amt.choho)}
      <div style={{ display: "flex", justifyContent: "space-between", fontSize: 15, paddingTop: 8, marginBottom: showDebt ? 8 : 0, borderTop: "1px solid var(--line)" }}>
        <span style={{ fontWeight: 700 }}>TỔNG TIỀN</span><b className="tnum">{fmtVND(amt.total)}</b>
      </div>
      {showDebt && <>
        {row(`Đã thanh toán (${payCount || 0} đợt)`, paid, { valStyle: { color: "var(--good)" } })}
        <div style={{ display: "flex", justifyContent: "space-between", fontSize: 15, paddingTop: 8, borderTop: "1px solid var(--line)" }}>
          <span style={{ fontWeight: 600 }}>CÒN PHẢI THU</span><b className="tnum" style={{ color: con > 0 ? "var(--warn)" : "var(--good)" }}>{fmtVND(Math.max(0, con))}</b>
        </div>
      </>}
    </div>
  );
}

function StatementForm({ cfg, onCancel, onSaved }) {
  const { useState, useEffect } = React;
  const T = window.__TRK || {}; const ROUTES = T.routes || {};
  const customers = cfg.customers || [];
  const [cust, setCust] = useState(customers[0] || "");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [picked, setPicked] = useState({}); // id -> bool
  const [vatRate, setVatRate] = useState(0); // % VAT cho bảng kê (mặc định 0)
  const info = (cfg.customerInfo || {})[cust] || {};
  const today = new Date().toISOString().slice(0, 10);

  // ----- Lô + ĐỊNH GIÁ lấy TỪ BACKEND (nguồn chân lý duy nhất) theo khách + kỳ -----
  const [all, setAll] = useState([]);   // [{id,booking,io,sheet,from,to,contType,contNo,qty,cru,date,contLabel,note,thanhLy,pr}]
  const [loading, setLoading] = useState(false);
  // Chưa chọn KỲ (ngày ra) → KHÔNG tải lô (tránh kéo toàn bộ + ép người dùng chọn kỳ trước).
  const needDate = !!cust && !from && !to;
  useEffect(() => {
    if (!cust || (!from && !to)) { setAll([]); setLoading(false); return; }
    let alive = true; setLoading(true); setPicked({});
    const p = new URLSearchParams({ customer: cust }); if (from) p.set("from", from); if (to) p.set("to", to);
    window.trkApi("GET", ROUTES.candidates + "?" + p.toString())
      .then((r) => { if (alive) { setAll(r && r.ok ? (r.candidates || []) : []); setLoading(false); } })
      .catch(() => { if (alive) { setAll([]); setLoading(false); } });
    return () => { alive = false; };
  }, [cust, from, to]);

  const [amtOv, setAmtOv] = useState({}); // override NỀN (cước+dầu+sà lan) theo lô
  const sel = all.filter((x) => picked[x.id] !== false); // mặc định chọn hết
  // 3 con số/dòng từ NGUỒN CHÂN LÝ chung (nền cước+dầu+sà lan; VAT chỉ áp nền; chi hộ không VAT).
  const ovOf = (x) => (amtOv[x.id] != null ? amtOv[x.id] : null);   // null = chưa sửa tay
  const lineAmt = (x) => lineAmounts({ detail: x.pr }, vatRate, ovOf(x));   // {base,vat,choho,total}
  const amt = statementAmounts(sel.map((x) => ({ detail: x.pr })), vatRate, (l, i) => ovOf(sel[i]));
  const tongThu = amt.total;
  const keNo = "BK-" + today.replace(/-/g, "").slice(2) + "-" + (cust ? cust.slice(0, 3).toUpperCase() : "XXX");

  const [saving, setSaving] = useState(false);
  const save = async () => {
    if (!sel.length || saving) return;
    setSaving(true);
    const lines = sel.map((x) => {
      const a = lineAmt(x);   // {base,vat,choho,total}
      const ov = ovOf(x);
      // Sửa tay NỀN: ép detail (gộp vào cước, bỏ dầu/sà lan) để BACKEND tính nền = nền đã sửa.
      const detail = ov != null ? { ...x.pr, cuoc: a.base, dau: 0, bargeCuoc: 0, bargeDau: 0 } : { ...x.pr };
      return {
        id: x.id, booking: x.booking, sheet: x.sheet, io: x.io,
        declNo: x.declNo || "", contType: x.contType || "", inv: x.inv || "", contNo: x.contNo || "", bks: x.bks || "",
        from: x.from, to: x.to, date: x.date, contLabel: x.contLabel,
        // phaiThu/cuoc = NỀN dòng (cước+dầu) — giữ cho exporter; tổng có VAT tính ở cấp bảng kê.
        phaiThu: a.base, cuoc: a.base, thanhLy: x.thanhLy || 0, note: x.note,
        // snapshot định giá (backend) để hiển thị đối soát TĨNH (không query lại khi xem)
        detail,
      };
    });
    const payload = { id: Date.now(), no: keNo, customer: cust, info, date: today, from, to, lines, vatRate, tongThu, payments: [], createdAt: new Date().toISOString() };
    // onSaved có thể async + trả về false để huỷ (vd bấm Huỷ ở confirm) → giữ nguyên trang
    let result;
    try { result = await Promise.resolve(onSaved && onSaved(payload)); }
    catch (e) { setSaving(false); return; }
    if (result === false) { setSaving(false); return; }   // huỷ → cho bấm lại; thành công thì trang điều hướng đi
  };

  const footer = (
    <div className="ke-noprint" style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 16, marginTop: 8, paddingTop: 14, borderTop: "1px solid var(--line)" }}>
      <div style={{ fontSize: 12.5, color: "var(--ink-3)" }}>{sel.length} lô · nền <b className="tnum">{fmtNum(amt.base)}</b> + VAT {vatRate}% <b className="tnum">{fmtNum(amt.vat)}</b> + chi hộ <b className="tnum">{fmtNum(amt.choho)}</b> = tổng <b className="tnum" style={{ color: "var(--ink)" }}>{fmtVND(tongThu)}</b></div>
      <div style={{ display: "flex", gap: 10 }}>
        <Btn onClick={onCancel}>Hủy</Btn>
        <Btn onClick={() => window.print()}>In thử</Btn>
        <Btn variant="primary" onClick={save} disabled={saving}>{saving ? "Đang lưu…" : "Lưu bảng kê"}</Btn>
      </div>
    </div>
  );

  return (
    <div>
      {/* controls */}
      <div className="ke-noprint" style={{ display: "flex", gap: 12, alignItems: "flex-end", padding: "12px 0 14px", borderBottom: "1px solid var(--line-2)", flexWrap: "wrap" }}>
        <label style={{ display: "block" }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>Khách hàng</div>
          <div style={{ width: 240 }}>
            <Combo value={cust} onChange={(v) => { setCust(v); setPicked({}); }} options={customers} placeholder="Chọn khách hàng…" strict />
          </div>
        </label>
        <div style={{ display: "block" }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>Cont ra từ ngày</div>
          <div style={{ width: 150 }}><DateField value={from} onChange={setFrom} /></div>
        </div>
        <div style={{ display: "block" }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>đến ngày</div>
          <div style={{ width: 150 }}><DateField value={to} onChange={setTo} /></div>
        </div>
        <div style={{ display: "block" }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>VAT</div>
          <VatSelect value={vatRate} onChange={setVatRate} />
        </div>
        <div style={{ flex: 1 }} />
        <div style={{ fontSize: 12, color: "var(--ink-4)" }}>{all.length} lô có phải thu</div>
      </div>

      {/* Ghi chú cho kế toán: bộ lọc kỳ dựa theo Giờ xe ra của lô */}
      <div className="ke-noprint" style={{ fontSize: 11.5, color: "var(--ink-4)", padding: "8px 0 0", display: "flex", alignItems: "flex-start", gap: 6, lineHeight: 1.5 }}>
        <i className="bi bi-info-circle" style={{ marginTop: 1 }} />
        <span>Lọc theo <b style={{ color: "var(--ink-3)" }}>Giờ xe ra</b> của lô hàng (mốc cont rời đi, nhập ở popup Lô hàng). Lô <b style={{ color: "var(--ink-3)" }}>chưa có giờ ra</b> sẽ không hiện ở đây.</span>
      </div>

      {/* printable statement */}
      <div className="ke-print" style={{ padding: "16px 4px 4px", background: "#fff" }}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", gap: 20, marginBottom: 14 }}>
          <div>
            <div style={{ fontSize: 18, fontWeight: 700, letterSpacing: "-0.01em" }}>BẢNG KÊ CẦN THU</div>
            <div style={{ fontSize: 12.5, color: "var(--ink-3)", marginTop: 3 }} className="tnum">Số: {keNo} · Ngày lập: {fmtDate(today)}{(from || to) ? ` · Cont ra: ${fmtDate(from) || "…"} – ${fmtDate(to) || "…"}` : ""}</div>
          </div>
          <div style={{ textAlign: "right", fontSize: 12 }}>
            <div style={{ fontWeight: 700, color: "var(--accent)" }}>{CO_NAME}</div>
            <div style={{ color: "var(--ink-3)", marginTop: 2 }}>{CO_SUB}</div>
          </div>
        </div>
        <div style={{ background: "var(--accent-weak-2)", border: "1px solid var(--accent-weak)", borderRadius: 10, padding: "11px 14px", marginBottom: 14, fontSize: 12.5 }}>
          <div style={{ fontWeight: 700, fontSize: 14 }}>{cust || "—"}</div>
          <div style={{ color: "var(--ink-2)", marginTop: 3, display: "flex", gap: 16, flexWrap: "wrap" }}>
            {info.taxCode && <span>MST: <b className="tnum">{info.taxCode}</b></span>}
            {info.address && <span>Địa chỉ: {info.address}</span>}
            {info.contact && <span>Liên hệ: {info.contact}</span>}
            {info.termDays && <span>Hạn TT: {info.termDays} ngày</span>}
          </div>
        </div>

        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12.5 }}>
          <thead>
            <tr style={{ background: "#fafbfc" }}>
              <th className="ke-noprint" style={{ width: 34, padding: "9px 8px", borderBottom: "1.5px solid var(--line)" }}></th>
              <th style={{ width: 34, textAlign: "center", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)" }}>#</th>
              <th style={{ textAlign: "left", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Lô / Booking</th>
              <th style={{ textAlign: "left", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Tuyến · Cont</th>
              <th style={{ textAlign: "left", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Cont ra</th>
              <th style={{ textAlign: "right", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Phải thu<br/>(cước+dầu)</th>
              <th style={{ textAlign: "right", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>VAT</th>
              <th style={{ textAlign: "right", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Chi hộ</th>
            </tr>
          </thead>
          <tbody>
            {!loading && all.length === 0 && <tr><td colSpan={8} style={{ padding: "28px 24px", textAlign: "center", color: "var(--ink-4)" }}>
              {needDate
                ? <span style={{ display: "inline-flex", flexDirection: "column", alignItems: "center", gap: 6 }}>
                    <i className="bi bi-calendar-range" style={{ fontSize: 26, color: "var(--accent)", opacity: .8 }} />
                    <b style={{ color: "var(--ink-2)", fontSize: 13.5 }}>Vui lòng chọn ngày ra của lô hàng</b>
                    <span style={{ fontSize: 12.5 }}>Chọn <b>Cont ra từ ngày</b> (và đến ngày) ở trên để lọc lô đưa vào bảng kê.</span>
                  </span>
                : (cust ? "Không có lô nào phù hợp trong kỳ đã chọn." : "Chọn khách hàng để bắt đầu.")}
            </td></tr>}
            {loading && <tr><td colSpan={8} style={{ padding: "24px", textAlign: "center", color: "var(--ink-4)" }}>Đang tải lô + định giá…</td></tr>}
            {!loading && all.map((x, i) => {
              const on = picked[x.id] !== false;
              return (
                <tr key={x.id} style={{ opacity: on ? 1 : 0.4 }}>
                  <td className="ke-noprint" style={{ textAlign: "center", padding: "8px", borderBottom: "1px solid var(--line-2)" }}>
                    <input type="checkbox" checked={on} onChange={(e) => setPicked((p) => ({ ...p, [x.id]: e.target.checked }))} style={{ width: 16, height: 16, accentColor: "var(--accent)", cursor: "pointer" }} />
                  </td>
                  <td className="tnum" style={{ textAlign: "center", padding: "8px", borderBottom: "1px solid var(--line-2)", color: "var(--ink-4)" }}>{i + 1}</td>
                  <td style={{ padding: "8px", borderBottom: "1px solid var(--line-2)" }}>
                    <div style={{ fontWeight: 600 }} className="tnum">{x.booking || "—"}</div>
                    <div style={{ fontSize: 11, color: "var(--ink-4)" }}>{x.sheet} · {x.io}</div>
                  </td>
                  <td style={{ padding: "8px", borderBottom: "1px solid var(--line-2)", color: "var(--ink-2)" }}>
                    {x.from} → {x.to}<div style={{ fontSize: 11, color: "var(--ink-4)" }} className="tnum">{x.contLabel}</div>
                    <div className="ke-noprint" style={{ fontSize: 10.5, marginTop: 3 }}>
                      {x.pr.matched
                        ? <span style={{ color: "var(--good)" }}>✓ Bảng giá · {x.cru ? (/xu/i.test(x.io || "") ? "CRU ngoại" : "CRU nội") : "1 chiều"} · {x.pr.conn || "—"} · {x.pr.is20 ? "20FT" : "40FT"}{x.pr.priceBook ? <span style={{ color: "var(--accent)" }}> · 📅 {bookLabel(x.pr.priceBook)}</span> : null} · <span className="tnum">{x.pr.route}</span>{x.pr.noDrop ? <span style={{ color: "var(--warn)" }}> (lô chưa có Nơi hạ — khớp theo FROM)</span> : null} — <span className="tnum">Cước {fmtNum(x.pr.cuoc)} + Dầu {fmtNum(x.pr.dau)}{(x.pr.bargeCuoc || x.pr.bargeDau) ? " + Sà lan " + fmtNum((x.pr.bargeCuoc || 0) + (x.pr.bargeDau || 0)) : ""}{x.pr.chiHo ? " + Chi hộ " + fmtNum(x.pr.chiHo) : ""} = {fmtNum(x.pr.phaiThu)} ₫</span>{x.pr.isBarge && !x.pr.bargeMatched ? <span style={{ color: "var(--warn)" }}> · ⚠ sà lan chưa khớp giá</span> : null}</span>
                        : <span style={{ color: "var(--warn)" }}>⚠ Chưa khớp bảng giá{!x.pr.priceBook ? " (ngày cont ra ngoài mọi bảng giá)" : ""}{x.pr.chiHo ? " · mới có Chi hộ " + fmtShort(x.pr.chiHo) : " · phải thu 0"}</span>}
                    </div>
                  </td>
                  <td className="tnum" style={{ padding: "8px", borderBottom: "1px solid var(--line-2)", color: "var(--ink-2)" }}>{fmtDate(x.date) || "—"}</td>
                  {(() => { const a = lineAmt(x); return (<>
                  <td className="tnum" style={{ textAlign: "right", padding: "6px 8px", borderBottom: "1px solid var(--line-2)", fontWeight: 600 }}>
                    <div style={{ position: "relative", width: 130, marginLeft: "auto" }}>
                      <input inputMode="numeric" value={(a.base || 0).toLocaleString("vi-VN")} onChange={(e) => setAmtOv((o) => ({ ...o, [x.id]: parseInt(e.target.value.replace(/[^\d]/g, ""), 10) || 0 }))} className="tnum"
                        style={{ width: "100%", padding: "6px 22px 6px 8px", fontSize: 12.5, textAlign: "right", fontWeight: 600, border: "1px solid var(--line)", borderRadius: 7, outline: "none" }}
                        onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
                      <span style={{ position: "absolute", right: 8, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 12, pointerEvents: "none" }}>₫</span>
                    </div>
                  </td>
                  <td className="tnum" style={{ textAlign: "right", padding: "6px 8px", borderBottom: "1px solid var(--line-2)", color: vatRate ? "var(--accent)" : "var(--ink-4)" }}>{fmtNum(a.vat)}</td>
                  <td className="tnum" style={{ textAlign: "right", padding: "6px 8px", borderBottom: "1px solid var(--line-2)", color: "var(--ink-2)" }}>{fmtNum(a.choho)}</td>
                  </>); })()}
                </tr>
              );
            })}
          </tbody>
          <tfoot>
            {/* Hàng tổng theo TỪNG CỘT (nền · VAT · chi hộ) — khớp 3 cột mỗi dòng */}
            <tr style={{ fontWeight: 700 }}>
              <td className="ke-noprint" style={{ borderTop: "1.5px solid var(--line)" }}></td>
              <td colSpan={4} style={{ padding: "9px 8px", borderTop: "1.5px solid var(--line)", textAlign: "right", color: "var(--ink-3)" }}>CỘNG</td>
              <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{fmtNum(amt.base)}</td>
              <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)", color: vatRate ? "var(--accent)" : "var(--ink-4)" }}>{fmtNum(amt.vat)}</td>
              <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{fmtNum(amt.choho)}</td>
            </tr>
            {/* Khối tổng 4 dòng (nền/VAT/chi hộ/tổng tiền) */}
            <tr>
              <td className="ke-noprint"></td>
              <td colSpan={5} style={{ padding: "9px 8px 3px", textAlign: "right", color: "var(--ink-3)" }}>Phải thu (cước + dầu)</td>
              <td className="tnum" colSpan={2} style={{ textAlign: "right", padding: "9px 8px 3px" }}>{fmtVND(amt.base)}</td>
            </tr>
            <tr>
              <td className="ke-noprint"></td>
              <td colSpan={5} style={{ padding: "3px 8px", textAlign: "right", color: "var(--ink-3)" }}>VAT ({vatRate}%)</td>
              <td className="tnum" colSpan={2} style={{ textAlign: "right", padding: "3px 8px", color: vatRate ? "var(--accent)" : "var(--ink-4)" }}>{fmtVND(amt.vat)}</td>
            </tr>
            <tr>
              <td className="ke-noprint"></td>
              <td colSpan={5} style={{ padding: "3px 8px", textAlign: "right", color: "var(--ink-3)" }}>Chi hộ (không VAT)</td>
              <td className="tnum" colSpan={2} style={{ textAlign: "right", padding: "3px 8px" }}>{fmtVND(amt.choho)}</td>
            </tr>
            <tr style={{ fontWeight: 700 }}>
              <td className="ke-noprint"></td>
              <td colSpan={5} style={{ padding: "8px 8px 11px", textAlign: "right" }}>TỔNG TIỀN</td>
              <td className="tnum" colSpan={2} style={{ textAlign: "right", padding: "8px 8px 11px" }}>{fmtVND(tongThu)}</td>
            </tr>
          </tfoot>
        </table>

        <div className="ke-noprint" style={{ marginTop: 14, fontSize: 12.5, color: "var(--ink-4)" }}>Sau khi lưu, mở bảng kê để nhập các đợt khách thanh toán và theo dõi công nợ.</div>
      </div>
      {footer}
    </div>
  );
}

/* Thân chi tiết bảng kê (in được) — dùng CHUNG cho modal & trang xem riêng.
   detailById: { [shipmentId]: { found, matched, cuoc, dau, choHoItems[], costItems[], phaiThu } } để đối soát. */
function StatementDetailBody({ st, onUpdate, detailById = {} }) {
  const { useState } = React;
  const [payments, setPayments] = useState(st.payments || []);
  const sync = (arr) => { setPayments(arr); onUpdate && onUpdate({ ...st, payments: arr }); };
  const setP = (id, np) => sync(payments.map((p) => (p.id === id ? { ...p, ...np } : p)));
  const addP = () => sync([...payments, { id: Date.now() + Math.random(), date: new Date().toISOString().slice(0, 10), amount: "", note: "" }]);
  const delP = (id) => sync(payments.filter((p) => p.id !== id));
  const vatRate = +st.vatRate || 0;
  const setVat = (v) => onUpdate && onUpdate({ ...st, vatRate: v });
  // NỀN dòng = cước+dầu+sà lan (từ detail) — NGUỒN CHÂN LÝ khớp backend. VAT áp nền; chi hộ không VAT.
  const lineAmt = (l) => lineAmounts(l, vatRate);   // {base,vat,choho,total} từ detail
  // Sửa tay NỀN dòng → ghi vào detail (gộp cước, bỏ dầu/sà lan) + phaiThu = nền, để backend khớp.
  const setLineBase = (id, base) => onUpdate && onUpdate({ ...st, lines: (st.lines || []).map((l) => {
    if (l.id !== id) return l;
    const d = (l.detail && typeof l.detail === "object") ? l.detail : {};
    return { ...l, phaiThu: base, cuoc: base, detail: { ...d, cuoc: base, dau: 0, bargeCuoc: 0, bargeDau: 0 } };
  }) });
  // 4 con số = Σ per-line (khớp 3 cột mỗi dòng).
  const amt = statementAmounts(st.lines || [], vatRate);
  const tongThu = amt.total;
  const grp = (d) => { d = (d || "").toString().replace(/[^\d]/g, ""); return d ? d.replace(/\B(?=(\d{3})+(?!\d))/g, ".") : ""; };
  const daTT = payments.reduce((a, p) => a + toNum(p.amount), 0);
  const conNo = tongThu - daTT;
  const info = st.info || {};
  const setPeriod = (k, v) => onUpdate && onUpdate({ ...st, [k]: v });   // sửa kỳ cont ra (from/to)
  const th = (txt, align) => <th style={{ textAlign: align || "left", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>{txt}</th>;
  return (
      <div className="ke-print" style={{ padding: "16px 4px 4px", background: "#fff" }}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", gap: 20, marginBottom: 14 }}>
          <div>
            <div style={{ fontSize: 18, fontWeight: 700, letterSpacing: "-0.01em" }}>BẢNG KÊ CẦN THU</div>
            <div style={{ fontSize: 12.5, color: "var(--ink-3)", marginTop: 3 }} className="tnum">Số: {st.no} · Ngày lập: {fmtDate(st.date)}
              <span className="ke-printonly" style={{ display: "none" }}>{(st.from || st.to) ? ` · Cont ra: ${fmtDate(st.from) || "…"} – ${fmtDate(st.to) || "…"}` : ""}</span>
            </div>
            {/* Kỳ cont ra — sửa được để tính lại theo khoảng ngày (ẩn khi in, in dùng dòng text trên) */}
            <div className="ke-noprint" style={{ display: "flex", alignItems: "center", gap: 7, marginTop: 7, fontSize: 12.5, color: "var(--ink-3)", flexWrap: "wrap" }}>
              <span style={{ fontWeight: 600 }}>Cont ra từ</span>
              <div style={{ width: 140 }}><DateField value={st.from || ""} onChange={(v) => setPeriod("from", v)} /></div>
              <span style={{ fontWeight: 600 }}>đến</span>
              <div style={{ width: 140 }}><DateField value={st.to || ""} onChange={(v) => setPeriod("to", v)} /></div>
              {(st.from || st.to) && <button type="button" onClick={() => onUpdate && onUpdate({ ...st, from: "", to: "" })} title="Xóa khoảng ngày"
                style={{ border: "none", background: "transparent", color: "var(--ink-4)", cursor: "pointer", fontSize: 12, padding: "2px 4px" }}>✕</button>}
              <span style={{ fontWeight: 600, marginLeft: 6 }}>VAT</span>
              <VatSelect value={vatRate} onChange={setVat} />
            </div>
            {/* VAT cho bản in (read-only) */}
            <div className="ke-printonly" style={{ display: "none", fontSize: 12.5, color: "var(--ink-3)", marginTop: 4 }}>VAT: {vatRate}%</div>
          </div>
          <div style={{ textAlign: "right", fontSize: 12 }}>
            <div style={{ fontWeight: 700, color: "var(--accent)" }}>{CO_NAME}</div>
            <div style={{ color: "var(--ink-3)", marginTop: 2 }}>{CO_SUB}</div>
          </div>
        </div>
        <div style={{ background: "var(--accent-weak-2)", border: "1px solid var(--accent-weak)", borderRadius: 10, padding: "11px 14px", marginBottom: 14, fontSize: 12.5 }}>
          <div style={{ fontWeight: 700, fontSize: 14 }}>{st.customer}</div>
          <div style={{ color: "var(--ink-2)", marginTop: 3, display: "flex", gap: 16, flexWrap: "wrap" }}>
            {info.taxCode && <span>MST: <b className="tnum">{info.taxCode}</b></span>}
            {info.address && <span>Địa chỉ: {info.address}</span>}
            {info.contact && <span>Liên hệ: {info.contact}</span>}
            {info.termDays && <span>Hạn TT: {info.termDays} ngày</span>}
          </div>
        </div>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12.5 }}>
          <thead><tr style={{ background: "#fafbfc" }}>{th("#", "center")}{th("Lô / Booking")}{th("Tuyến · Cont")}{th("Cont ra")}{th(<>Phải thu<br/>(cước+dầu)</>, "right")}{th("VAT", "right")}{th("Chi hộ", "right")}</tr></thead>
          <tbody>
            {st.lines.map((l, i) => {
              const d = detailById[l.id];
              const diff = d && d.found && (d.phaiThu || 0) !== (l.phaiThu || 0);
              return (
              <React.Fragment key={l.id}>
              <tr>
                <td className="tnum" style={{ textAlign: "center", padding: "8px", borderBottom: d ? "none" : "1px solid var(--line-2)", color: "var(--ink-4)", verticalAlign: "top" }}>{i + 1}</td>
                <td style={{ padding: "8px", borderBottom: d ? "none" : "1px solid var(--line-2)", verticalAlign: "top" }}><div style={{ fontWeight: 600 }} className="tnum">{l.booking || "—"}</div><div style={{ fontSize: 11, color: "var(--ink-4)" }}>{l.sheet} · {l.io}</div></td>
                <td style={{ padding: "8px", borderBottom: d ? "none" : "1px solid var(--line-2)", color: "var(--ink-2)", verticalAlign: "top" }}>{l.from} → {l.to}<div style={{ fontSize: 11, color: "var(--ink-4)" }} className="tnum">{(ROUTES_TRK.loHang && l.contNo)
                  ? <a className="ke-noprint" href={ROUTES_TRK.loHang + "?q=" + encodeURIComponent(l.contNo) + "&open=1"} title="Mở lô hàng (lọc đúng cont này)" style={{ color: "var(--accent)", textDecoration: "none" }}>{l.contLabel}</a>
                  : l.contLabel}<span style={{ display: "none" }} className="ke-printonly">{l.contLabel}</span></div></td>
                <td className="tnum" style={{ padding: "8px", borderBottom: d ? "none" : "1px solid var(--line-2)", color: "var(--ink-2)", verticalAlign: "top" }}>{fmtDate(l.date) || "—"}</td>
                {(() => { const a = lineAmt(l); return (<>
                <td className="tnum" style={{ textAlign: "right", padding: "6px 8px", borderBottom: d ? "none" : "1px solid var(--line-2)", fontWeight: 600, verticalAlign: "top" }}>
                  <span className="ke-noprint"><span style={{ position: "relative", display: "inline-block", width: 130 }}>
                    <input inputMode="numeric" value={(a.base || 0).toLocaleString("vi-VN")} onChange={(e) => setLineBase(l.id, parseInt(e.target.value.replace(/[^\d]/g, ""), 10) || 0)} className="tnum"
                      style={{ width: "100%", padding: "6px 22px 6px 8px", fontSize: 12.5, textAlign: "right", fontWeight: 600, border: "1px solid var(--line)", borderRadius: 7, outline: "none" }}
                      onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
                    <span style={{ position: "absolute", right: 8, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 12, pointerEvents: "none" }}>₫</span>
                  </span></span>
                  <span style={{ display: "none" }} className="ke-printonly">{fmtVND(a.base)}</span>
                </td>
                <td className="tnum" style={{ textAlign: "right", padding: "6px 8px", borderBottom: d ? "none" : "1px solid var(--line-2)", color: vatRate ? "var(--accent)" : "var(--ink-4)", verticalAlign: "top" }}>{fmtNum(a.vat)}</td>
                <td className="tnum" style={{ textAlign: "right", padding: "6px 8px", borderBottom: d ? "none" : "1px solid var(--line-2)", color: "var(--ink-2)", verticalAlign: "top" }}>{fmtNum(a.choho)}</td>
                </>); })()}
              </tr>
              {d && d.found && (
                <tr>
                  <td style={{ borderBottom: "1px solid var(--line-2)" }}></td>
                  <td colSpan={6} style={{ padding: "0 8px 9px", borderBottom: "1px solid var(--line-2)" }}>
                    {/* LỘ TRÌNH lô (ĐI → NHÀ MÁY → HẠ, theo ký hiệu) — để kế toán dò bảng giá */}
                    {(d.loTrinh || l.from || l.to) && (
                      <div style={{ fontSize: 12, fontWeight: 700, margin: "2px 0 4px", color: "var(--ink-2)" }}>
                        <i className="bi bi-signpost-2-fill" style={{ color: "var(--accent)" }} /> Lộ trình: <span className="tnum">{d.loTrinh || [l.from, l.to].filter(Boolean).join(" → ")}</span>
                        <span style={{ fontSize: 10.5, fontWeight: 400, color: "var(--ink-4)" }}> (đi → nhà máy → hạ)</span>
                      </div>
                    )}
                    {/* Hàng 1: KẾT NỐI (Connect/Disconnect) + KIND + loại cont + tuyến — ghi rõ nhất */}
                    <div style={{ display: "flex", flexWrap: "wrap", alignItems: "center", gap: "5px 8px", marginBottom: 3 }}>
                      {d.conn === "Connect"
                        ? <span style={{ display: "inline-flex", alignItems: "center", gap: 4, fontSize: 11, fontWeight: 700, color: "var(--good)", background: "var(--good-weak)", padding: "2px 9px", borderRadius: 999 }}><span style={{ width: 6, height: 6, borderRadius: 999, background: "currentColor" }} />CONNECT</span>
                        : d.conn === "Disconnect"
                        ? <span style={{ display: "inline-flex", alignItems: "center", gap: 4, fontSize: 11, fontWeight: 700, color: "var(--danger)", background: "#fce8e8", padding: "2px 9px", borderRadius: 999 }}><span style={{ width: 6, height: 6, borderRadius: 999, background: "currentColor" }} />DISCONNECT</span>
                        : <span style={{ fontSize: 11, fontWeight: 700, color: "var(--ink-4)", background: "var(--line-2)", padding: "2px 9px", borderRadius: 999 }}>KẾT NỐI: chưa xác định (thiếu giờ xe ra)</span>}
                      {d.ftHours != null && <span style={{ fontSize: 11, color: "var(--ink-4)" }} className="tnum">Free time {fmtHours(d.ftHours)} · ngưỡng {d.ftThreshold}h{d.ftBasis ? " · từ " + d.ftBasis : ""}</span>}
                      <span style={{ fontSize: 11, fontWeight: 600, color: "var(--ink-3)", background: "var(--line-2)", padding: "2px 9px", borderRadius: 999 }}>{/external cru/i.test(d.kind || "") ? "CRU ngoại" : /internal cru/i.test(d.kind || "") ? "CRU nội" : "1 chiều"}</span>
                      <span style={{ fontSize: 11, fontWeight: 600, color: "var(--ink-3)", background: "var(--line-2)", padding: "2px 9px", borderRadius: 999 }}>{d.is20 ? "20FT" : "40FT"}</span>
                      {d.route && <span className="tnum" style={{ fontSize: 11, color: "var(--ink-4)" }}>{d.route}</span>}
                      {d.priceBook && <span style={{ fontSize: 11, fontWeight: 600, color: "var(--accent)", background: "var(--accent-weak-2)", border: "1px solid var(--accent-weak)", padding: "2px 9px", borderRadius: 999 }} title="Bảng giá áp theo ngày cont ra"><i className="bi bi-calendar3" /> Giá: {bookLabel(d.priceBook)}</span>}
                      {!d.matched && <span style={{ fontSize: 11, color: "var(--warn)", fontWeight: 700 }}>⚠ chưa khớp bảng giá{!d.priceBook ? " (ngày cont ra ngoài mọi bảng giá)" : ""}</span>}
                    </div>
                    {/* DÒ: vì sao chưa khớp — tiêu chí đã tìm trong bảng giá (cảng + nhà máy + loại) */}
                    {!d.matched && d.diag && (
                      <div style={{ fontSize: 11, color: "var(--ink-4)", marginBottom: 3, lineHeight: 1.6 }}>
                        <i className="bi bi-search" /> Đã dò bảng giá: đi <b className="tnum" style={{ color: "var(--ink-3)" }}>{d.diag.di}</b> → nhà máy <b className="tnum" style={{ color: "var(--ink-3)" }}>{d.diag.nhaMay}</b> → hạ <b className="tnum" style={{ color: "var(--ink-3)" }}>{d.diag.ha}</b> · loại <b style={{ color: "var(--ink-3)" }}>{/external cru/i.test(d.diag.kind || "") ? "CRU ngoại" : /internal cru/i.test(d.diag.kind || "") ? "CRU nội" : "1 chiều"}</b>{d.diag.conn ? <> · <b style={{ color: "var(--ink-3)" }}>{d.diag.conn}</b></> : null}{!d.diag.hasPrice ? <span style={{ color: "var(--warn)" }}> — khách CHƯA có bảng giá</span> : <span> — không có dòng giá khớp (kiểm tra ký hiệu đi·nhà máy·hạ trong Bảng giá)</span>}
                      </div>
                    )}
                    {/* Hàng 2: tách khoản tiền */}
                    <div style={{ fontSize: 11.5, color: "var(--ink-3)", display: "flex", flexWrap: "wrap", alignItems: "center", gap: "3px 12px", lineHeight: 1.7 }}>
                      <span>Cước <b className="tnum" style={{ color: "var(--ink-2)" }}>{fmtNum(d.cuoc)}</b></span>
                      <span>+ Dầu <b className="tnum" style={{ color: "var(--ink-2)" }}>{fmtNum(d.dau)}</b></span>
                      {d.isBarge && (
                        <span style={{ color: "var(--accent)" }} title={d.bargeRoute ? "Sà lan: " + d.bargeRoute : "Sà lan"}>
                          <i className="bi bi-water" /> + Cước sà lan <b className="tnum">{fmtNum((d.bargeCuoc || 0) + (d.bargeDau || 0))}</b>
                          {!d.bargeMatched && <span style={{ color: "var(--warn)", fontWeight: 700 }}> ⚠ chưa khớp{d.bargeDrop ? "" : " (thiếu nơi hạ sà lan)"}</span>}
                        </span>
                      )}
                      {d.choHoItems.map((c, j) => <span key={"h" + j} style={{ color: "var(--good)" }}>+ Chi hộ · {c.item} <b className="tnum">{fmtNum(c.amount)}</b></span>)}
                      <span style={{ fontWeight: 700 }}>= <b className="tnum" style={{ color: "var(--accent)" }}>{fmtNum(d.phaiThu)} ₫</b></span>
                      {diff && <span style={{ color: "var(--warn)", fontWeight: 600 }}>≠ đã lưu {fmtNum(l.phaiThu)} — bấm “Tính lại”</span>}
                      {d.costItems.filter((c) => !c.billable).length > 0 &&
                        <span style={{ color: "var(--ink-4)" }}>· Chi phí công ty (không thu khách): {d.costItems.filter((c) => !c.billable).map((c) => c.item + " " + fmtNum(c.amount)).join(" · ")}</span>}
                    </div>
                  </td>
                </tr>
              )}
              {d && !d.found && (
                <tr><td style={{ borderBottom: "1px solid var(--line-2)" }}></td><td colSpan={6} style={{ padding: "0 8px 9px", borderBottom: "1px solid var(--line-2)", fontSize: 11.5, color: "var(--ink-4)" }}>Lô không còn trong hệ thống — giữ số đã lưu, không tính lại được.</td></tr>
              )}
              </React.Fragment>
            );})}
          </tbody>
          <tfoot>
            {/* Hàng cộng theo TỪNG CỘT (nền · VAT · chi hộ) — khớp 3 cột mỗi dòng */}
            <tr style={{ fontWeight: 700 }}>
              <td colSpan={4} style={{ padding: "9px 8px", borderTop: "1.5px solid var(--line)", textAlign: "right", color: "var(--ink-3)" }}>CỘNG</td>
              <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{fmtNum(amt.base)}</td>
              <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)", color: vatRate ? "var(--accent)" : "var(--ink-4)" }}>{fmtNum(amt.vat)}</td>
              <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{fmtNum(amt.choho)}</td>
            </tr>
            <tr>
              <td colSpan={5} style={{ padding: "9px 8px 3px", textAlign: "right", color: "var(--ink-3)" }}>Phải thu (cước + dầu)</td>
              <td className="tnum" colSpan={2} style={{ textAlign: "right", padding: "9px 8px 3px" }}>{fmtVND(amt.base)}</td>
            </tr>
            <tr>
              <td colSpan={5} style={{ padding: "3px 8px", textAlign: "right", color: "var(--ink-3)" }}>VAT ({vatRate}%)</td>
              <td className="tnum" colSpan={2} style={{ textAlign: "right", padding: "3px 8px", color: vatRate ? "var(--accent)" : "var(--ink-4)" }}>{fmtVND(amt.vat)}</td>
            </tr>
            <tr>
              <td colSpan={5} style={{ padding: "3px 8px", textAlign: "right", color: "var(--ink-3)" }}>Chi hộ (không VAT)</td>
              <td className="tnum" colSpan={2} style={{ textAlign: "right", padding: "3px 8px" }}>{fmtVND(amt.choho)}</td>
            </tr>
            <tr style={{ fontWeight: 700 }}>
              <td colSpan={5} style={{ padding: "8px 8px 11px", textAlign: "right" }}>TỔNG TIỀN</td>
              <td className="tnum" colSpan={2} style={{ textAlign: "right", padding: "8px 8px 11px" }}>{fmtVND(tongThu)}</td>
            </tr>
          </tfoot>
        </table>

        {/* payments / công nợ */}
        <div style={{ marginTop: 18 }}>
          <div style={{ fontSize: 12.5, fontWeight: 700, color: "var(--ink-2)", textTransform: "uppercase", letterSpacing: "0.04em", marginBottom: 6 }}>Khách thanh toán (nhiều đợt)</div>
          <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12.5 }}>
            <thead><tr style={{ background: "#fafbfc" }}>{th("Đợt", "center")}{th("Ngày thu")}{th("Ghi chú")}{th("Số tiền", "right")}<th className="ke-noprint" style={{ width: 34, borderBottom: "1.5px solid var(--line)" }}></th></tr></thead>
            <tbody>
              {payments.length === 0 && <tr><td colSpan={5} style={{ padding: "14px", textAlign: "center", color: "var(--ink-4)" }}>Chưa có đợt thanh toán nào.</td></tr>}
              {payments.map((p, i) => (
                <tr key={p.id}>
                  <td className="tnum" style={{ textAlign: "center", padding: "6px 8px", borderBottom: "1px solid var(--line-2)", color: "var(--ink-4)" }}>{i + 1}</td>
                  <td style={{ padding: "6px 8px", borderBottom: "1px solid var(--line-2)" }}>
                    <DateField value={p.date || ""} onChange={(v) => setP(p.id, { date: v })} />
                  </td>
                  <td style={{ padding: "6px 8px", borderBottom: "1px solid var(--line-2)" }}>
                    <input value={p.note || ""} onChange={(e) => setP(p.id, { note: e.target.value })} placeholder="VD: chuyển khoản…"
                      style={{ width: "100%", padding: "6px 8px", fontSize: 12.5, border: "1px solid var(--line)", borderRadius: 7, outline: "none" }} />
                  </td>
                  <td style={{ padding: "6px 8px", borderBottom: "1px solid var(--line-2)" }}>
                    <div style={{ position: "relative" }}>
                      <input inputMode="numeric" value={grp(p.amount)} onChange={(e) => setP(p.id, { amount: e.target.value.replace(/[^\d]/g, "") })} placeholder="0" className="tnum"
                        style={{ width: "100%", padding: "6px 24px 6px 8px", fontSize: 12.5, textAlign: "right", fontWeight: 600, border: "1px solid var(--line)", borderRadius: 7, outline: "none" }} />
                      <span style={{ position: "absolute", right: 8, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 12, pointerEvents: "none" }}>₫</span>
                    </div>
                  </td>
                  <td className="ke-noprint" style={{ textAlign: "center", padding: "6px 4px", borderBottom: "1px solid var(--line-2)" }}>
                    <button type="button" onClick={() => delP(p.id)} title="Xóa đợt"
                      style={{ width: 26, height: 26, display: "grid", placeItems: "center", border: "none", borderRadius: 6, background: "transparent", color: "var(--ink-4)", cursor: "pointer" }}
                      onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
                      onMouseLeave={(e) => { e.currentTarget.style.background = "transparent"; e.currentTarget.style.color = "var(--ink-4)"; }}><I.trash /></button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          <button type="button" onClick={addP} className="ke-noprint"
            style={{ display: "inline-flex", alignItems: "center", gap: 7, margin: "8px 0 2px", padding: "6px 11px", fontSize: 12.5, fontWeight: 600, border: "none", borderRadius: 7, background: "var(--accent-weak)", color: "var(--accent)", cursor: "pointer" }}>
            <I.plus /> Thêm đợt thanh toán
          </button>
        </div>

        <div style={{ marginTop: 16, display: "flex", justifyContent: "flex-end" }}>
          <AmountSummary amt={amt} vatRate={vatRate} paid={daTT} payCount={payments.length} showDebt />
        </div>
      </div>
  );
}

/* Hàng nút thao tác bảng kê (Xóa · trạng thái dirty · Lưu · In) dùng chung. */
function StatementActions({ st, onDelete, onSave, isDirty, onClose, closeLabel = "Đóng" }) {
  const { useState } = React;
  const dirty = !!(isDirty && isDirty(st.id));
  const [saving, setSaving] = useState(false);
  const doSave = () => { if (saving || !dirty) return; setSaving(true); Promise.resolve(onSave && onSave()).then(() => setSaving(false)).catch(() => setSaving(false)); };
  const esc = (s) => String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const askDelete = async () => {
    if (!onDelete) return;
    const ok = await window.confirmAction({
      title: "Xóa bảng kê?",
      text: `Bảng kê <b>${esc(st.no || "(chưa có số)")}</b>${st.customer ? " · " + esc(st.customer) : ""} sẽ bị xóa vĩnh viễn cùng toàn bộ đợt thanh toán. Không thể hoàn tác.`,
      confirmText: '<i class="bi bi-trash me-1"></i> Xóa bảng kê',
      danger: true,
    });
    if (ok) onDelete(st.id);
  };
  return (
    <div className="ke-noprint" style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 16 }}>
      <button type="button" onClick={askDelete}
        style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "8px 13px", fontSize: 13, fontWeight: 500, border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: "var(--ink-3)", cursor: "pointer" }}
        onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
        onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-3)"; }}>
        <I.trash /> Xóa bảng kê
      </button>
      <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
        {dirty && <span style={{ fontSize: 12, color: "var(--warn)", fontWeight: 600, display: "inline-flex", alignItems: "center", gap: 5 }}><span style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} />Có thay đổi chưa lưu</span>}
        {onClose && <Btn onClick={onClose}>{closeLabel}</Btn>}
        <Btn variant="primary" onClick={doSave} disabled={!dirty || saving}>{saving ? "Đang lưu…" : "Lưu"}</Btn>
        <Btn onClick={() => window.print()}>In / Xuất PDF</Btn>
      </div>
    </div>
  );
}

function SavedStatementModal({ st, onClose, onDelete, onUpdate, onSave, isDirty }) {
  const footer = <StatementActions st={st} isDirty={isDirty} onSave={onSave} onClose={onClose}
    onDelete={(id) => { onDelete(id); onClose(); }} />;
  return (
    <Modal title="Bảng kê cần thu" subtitle={st.no + " · " + st.customer} onClose={onClose} footer={footer} width={940} icon={<I.fx />}>
      <StatementDetailBody st={st} onUpdate={onUpdate} />
    </Modal>
  );
}

/* Trang xem bảng kê đã lưu (route riêng) — chi tiết đầy đủ như lúc tạo. */
function SavedStatementPage({ st, onUpdate, onSave, onDelete, isDirty, backUrl, onExcel, onRecalc, detailById }) {
  const recalcDiff = detailById && (st.lines || []).some((l) => { const d = detailById[l.id]; return d && d.found && (d.phaiThu || 0) !== (l.phaiThu || 0); });
  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <div className="ke-noprint trk-head" style={{ display: "flex", alignItems: "center", gap: 10, padding: "14px 22px", background: "#fff", borderBottom: "1px solid var(--line)" }}>
        <div className="trk-head-lead" style={{ display: "flex", alignItems: "center", gap: 10, flex: 1, minWidth: 0 }}>
          <a href={backUrl} title="Về danh sách bảng kê"
            style={{ display: "inline-flex", alignItems: "center", gap: 6, flexShrink: 0, padding: "7px 12px", fontSize: 13, fontWeight: 600, color: "var(--ink-2)", textDecoration: "none", border: "1px solid var(--line)", borderRadius: 9 }}>
            <span style={{ transform: "rotate(180deg)", display: "inline-flex" }}><I.arrow /></span> Bảng kê
          </a>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 16, fontWeight: 700, letterSpacing: "-0.01em" }}>Bảng kê cần thu</div>
            <div className="tnum" style={{ fontSize: 12.5, color: "var(--ink-3)" }}>{st.no} · {st.customer}</div>
          </div>
        </div>
        {onRecalc && <button type="button" onClick={onRecalc} title="Tính lại phải thu từ dữ liệu lô hàng hiện tại"
          style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "9px 15px", fontSize: 13.5, fontWeight: 600, cursor: "pointer", color: recalcDiff ? "#fff" : "var(--ink-2)", background: recalcDiff ? "var(--warn)" : "#fff", border: recalcDiff ? "none" : "1px solid var(--line)", borderRadius: 9 }}>
          <I.fx /> Tính lại{recalcDiff ? " (có chênh lệch)" : ""}
        </button>}
        {onExcel && <button type="button" onClick={onExcel}
          style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "9px 15px", fontSize: 13.5, fontWeight: 600, cursor: "pointer", color: "#fff", background: "var(--good)", border: "none", borderRadius: 9 }}>
          <I.check /> Xuất Excel
        </button>}
      </div>
      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: "22px" }}>
        <div className="ke-noprint" style={{ maxWidth: 940, margin: "0 auto 14px", display: "flex", gap: 10, alignItems: "flex-start", background: "#eff5ff", border: "1px solid #cfe0fb", borderRadius: 12, padding: "12px 14px", fontSize: 13, color: "#1f4f9e", lineHeight: 1.6 }}>
          <i className="bi bi-info-circle-fill" style={{ fontSize: 16, marginTop: 1, flexShrink: 0 }} />
          <div>
            Bảng kê này đã được <b>chốt số khi tạo</b> để lưu hồ sơ. Nếu sau này <b>lô hàng gốc bị sửa hoặc xóa</b>, bảng kê này <b>vẫn giữ nguyên</b> — không bị ảnh hưởng.
            <br />Khi bạn thấy lô hàng có thay đổi và muốn cập nhật phải thu, bấm nút <b>Tính lại</b> ở trên — hệ thống sẽ đọc lại thông tin lô hàng hiện tại và tính lại bảng kê.
          </div>
        </div>
        <div style={{ maxWidth: 940, margin: "0 auto", background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "8px 22px 18px" }}>
          <StatementDetailBody st={st} onUpdate={onUpdate} detailById={detailById || {}} />
          <div style={{ marginTop: 16, paddingTop: 14, borderTop: "1px solid var(--line)" }}>
            <StatementActions st={st} isDirty={isDirty} onSave={onSave} onDelete={(id) => { Promise.resolve(onDelete && onDelete(id)).then(() => { window.location.href = backUrl; }); }} />
          </div>
        </div>
      </div>
    </div>
  );
}


/* Chip cảnh báo bảng kê có lô lệch phải thu so với snapshot → cần mở vào bấm "Tính lại". */
function DriftChip({ n }) {
  return (
    <span title={`${n} lô có phải thu khác với bảng kê đã lưu — mở bảng kê và bấm “Tính lại” để cập nhật.`}
      style={{ display: "inline-flex", alignItems: "center", gap: 4, marginLeft: 8, fontSize: 11, fontWeight: 700, color: "#fff", background: "var(--warn)", padding: "2px 8px", borderRadius: 999, whiteSpace: "nowrap", verticalAlign: "middle" }}>
      <i className="bi bi-exclamation-triangle-fill" style={{ fontSize: 10 }} /> Cần tính lại
    </span>
  );
}

function KePage({ ke, drift = {}, onNew, onOpen }) {
  const isMobile = useIsMobile();
  const cols = "130px 1fr 110px 130px 120px 90px 110px 130px";
  const driftOf = (st) => drift[String(st.id)];
  const num = (v, fb) => (v == null ? fb : v);   // số dẫn xuất từ backend; fallback nếu boot cũ
  return (
    <div style={{ flex: 1, minHeight: 0, display: "flex", flexDirection: "column", padding: isMobile ? "16px 14px 24px" : "20px 22px 24px", overflow: "auto" }}>
      <div style={{ maxWidth: 1000, width: "100%", margin: "0 auto" }}>
        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 16, marginBottom: 18, flexWrap: "wrap" }}>
          <div>
            <h1 style={{ margin: 0, fontSize: 22, fontWeight: 700, letterSpacing: "-0.02em" }}>Bảng kê cần thu</h1>
            <div style={{ fontSize: 13, color: "var(--ink-3)", marginTop: 3 }}>{ke.length} bảng kê đã tạo</div>
          </div>
          <button type="button" onClick={onNew}
            style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "10px 16px", fontSize: 13.5, fontWeight: 600, cursor: "pointer", color: "#fff", background: "var(--accent)", border: "none", borderRadius: 10, boxShadow: "0 1px 2px rgba(42,111,219,.4)" }}>
            <I.plus /> Tạo bảng kê mới
          </button>
        </div>
        {/* ===== Mobile: card list ===== */}
        {isMobile && (
          <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
            {ke.length === 0 && <div style={{ padding: "44px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5, background: "#fff", border: "1px solid var(--line)", borderRadius: 12 }}>Chưa có bảng kê nào. Bấm “Tạo bảng kê mới” để bắt đầu.</div>}
            {ke.slice().reverse().map((st) => {
              const daTT = (st.payments || []).reduce((a, p) => a + (parseInt((p.amount || "0").toString().replace(/[^\d]/g, ""), 10) || 0), 0);
              const con = st.tongThu - daTT;
              return (
                <button key={st.id} type="button" onClick={() => onOpen(st)}
                  style={{ width: "100%", textAlign: "left", background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "12px 14px", cursor: "pointer", boxShadow: "0 1px 2px rgba(16,19,23,.04)" }}>
                  <div style={{ display: "flex", justifyContent: "space-between", gap: 10, alignItems: "baseline" }}>
                    <span className="tnum" style={{ fontWeight: 700, color: "var(--accent)", fontSize: 14 }}>{st.no}</span>
                    <span className="tnum" style={{ color: "var(--ink-3)", fontSize: 12.5 }}>{fmtDate(st.date)}</span>
                  </div>
                  <div style={{ fontWeight: 600, fontSize: 14.5, marginTop: 4 }}>{st.customer}{driftOf(st) ? <DriftChip n={driftOf(st).changed} /> : null}</div>
                  <div className="tnum" style={{ color: "var(--ink-4)", fontSize: 12, marginTop: 2 }}>{(st.from || st.to) ? `Cont ra: ${fmtDate(st.from) || "…"} – ${fmtDate(st.to) || "…"}` : "—"}</div>
                  <div style={{ marginTop: 9, paddingTop: 9, borderTop: "1px solid var(--line-2)", fontSize: 12.5, color: "var(--ink-3)", display: "flex", flexWrap: "wrap", gap: "2px 12px" }}>
                    <span>Cước+dầu: <b className="tnum" style={{ color: "var(--ink-2)" }}>{fmtNum(num(st.baseAmount, st.tongThu))}</b></span>
                    <span>VAT {num(st.vatRate, 0)}%: <b className="tnum" style={{ color: (st.vatRate ? "var(--accent)" : "var(--ink-2)") }}>{fmtNum(num(st.vatAmount, 0))}</b></span>
                    <span>Chi hộ: <b className="tnum" style={{ color: "var(--ink-2)" }}>{fmtNum(num(st.chohoAmount, 0))}</b></span>
                  </div>
                  <div style={{ display: "flex", justifyContent: "space-between", gap: 12, marginTop: 6 }}>
                    <span style={{ fontSize: 13, color: "var(--ink-3)" }}>Tổng tiền: <b className="tnum" style={{ color: "var(--ink)" }}>{fmtVND(st.tongThu)}</b></span>
                    <span style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Còn: <b className="tnum" style={{ color: con > 0 ? "var(--warn)" : "var(--good)" }}>{fmtVND(Math.max(0, con))}</b></span>
                  </div>
                </button>
              );
            })}
          </div>
        )}
        {/* ===== Desktop: grid table ===== */}
        {!isMobile && (
        <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, overflow: "hidden" }}>
          <div style={{ display: "grid", gridTemplateColumns: cols, gap: 12, padding: "11px 16px", background: "#fafbfc", borderBottom: "1px solid var(--line)", fontSize: 11, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.04em" }}>
            <div>Số bảng kê</div><div>Khách hàng</div><div>Ngày lập</div><div>Kỳ cont ra</div><div style={{ textAlign: "right" }}>Phải thu<br/>(cước+dầu)</div><div style={{ textAlign: "right" }}>+VAT</div><div style={{ textAlign: "right" }}>Chi hộ</div><div style={{ textAlign: "right" }}>Tổng tiền</div>
          </div>
          {ke.length === 0 && <div style={{ padding: "44px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5 }}>Chưa có bảng kê nào. Bấm “Tạo bảng kê mới” để bắt đầu.</div>}
          {ke.slice().reverse().map((st) => (
            <button key={st.id} type="button" onClick={() => onOpen(st)}
              style={{ width: "100%", textAlign: "left", display: "grid", gridTemplateColumns: cols, gap: 12, alignItems: "center", padding: "12px 16px", borderBottom: "1px solid var(--line-2)", background: "transparent", border: "none", borderBottomStyle: "solid", cursor: "pointer", fontSize: 13.5 }}
              onMouseEnter={(e) => (e.currentTarget.style.background = "var(--accent-weak-2)")}
              onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}>
              <span className="tnum" style={{ fontWeight: 600, color: "var(--accent)" }}>{st.no}</span>
              <span style={{ fontWeight: 500 }}>{st.customer}{driftOf(st) ? <DriftChip n={driftOf(st).changed} /> : null}</span>
              <span className="tnum" style={{ color: "var(--ink-2)" }}>{fmtDate(st.date)}</span>
              <span className="tnum" style={{ color: "var(--ink-3)", fontSize: 12.5 }}>{(st.from || st.to) ? `${fmtDate(st.from) || "…"} – ${fmtDate(st.to) || "…"}` : "—"}</span>
              <span className="tnum" style={{ textAlign: "right", color: "var(--ink-2)" }}>{fmtNum(num(st.baseAmount, st.tongThu))}</span>
              <span className="tnum" style={{ textAlign: "right", color: (st.vatRate ? "var(--accent)" : "var(--ink-4)") }} title={`VAT ${num(st.vatRate, 0)}%`}>{fmtNum(num(st.vatAmount, 0))}</span>
              <span className="tnum" style={{ textAlign: "right", color: "var(--ink-3)" }}>{fmtNum(num(st.chohoAmount, 0))}</span>
              <span className="tnum" style={{ textAlign: "right", fontWeight: 700 }}>{fmtVND(st.tongThu)}</span>
            </button>
          ))}
        </div>
        )}
      </div>
    </div>
  );
}


export { StatementForm, StatementDetailBody, StatementActions, SavedStatementModal, SavedStatementPage, DriftChip, KePage };
