import React from "react";
const { useState, useEffect } = React;
import { I, fmtVND, fmtNum, fmtDate, toNum, Combo, Btn, DateField, useIsMobile } from "@trk/lib.jsx";

/* Thông tin công ty cho header bảng kê (màn hình + bản in). */
const CO = (window.__TRK && window.__TRK.boot && window.__TRK.boot.company) || {};
const ROUTES_TRK = (window.__TRK && window.__TRK.routes) || {};
const CO_NAME = CO.name || "MBF JOINT STOCK COMPANY";
const CO_SUB = [CO.website, CO.phone].filter(Boolean).join(" · ") || "http://mbf.com.vn · 84-24-39449616";

/* ============================================================
 * Danh sách bảng kê xe ngoài (theo nhà xe) — Tổng / Đã trả / Còn nợ.
 * ============================================================ */
function ExtKePage({ ke, onNew, onOpen }) {
  const isMobile = useIsMobile();
  const cols = "130px 1fr 110px 130px 90px 120px 110px 120px";
  return (
    <div style={{ flex: 1, minHeight: 0, display: "flex", flexDirection: "column", padding: isMobile ? "16px 14px 24px" : "20px 22px 24px", overflow: "auto" }}>
      <div style={{ maxWidth: 1000, width: "100%", margin: "0 auto" }}>
        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 16, marginBottom: 18, flexWrap: "wrap" }}>
          <div>
            <h1 style={{ margin: 0, fontSize: 22, fontWeight: 700, letterSpacing: "-0.02em" }}>Bảng kê xe ngoài</h1>
            <div style={{ fontSize: 13, color: "var(--ink-3)", marginTop: 3 }}>{ke.length} bảng kê phải trả nhà xe</div>
          </div>
          <button type="button" onClick={onNew}
            style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "10px 16px", fontSize: 13.5, fontWeight: 600, cursor: "pointer", color: "#fff", background: "var(--accent)", border: "none", borderRadius: 10, boxShadow: "0 1px 2px rgba(42,111,219,.4)" }}>
            <I.plus /> Tạo bảng kê mới
          </button>
        </div>
        {/* ===== Mobile: card list ===== */}
        {isMobile && (
          <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
            {ke.length === 0 && <div style={{ padding: "44px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5, background: "#fff", border: "1px solid var(--line)", borderRadius: 12 }}>Chưa có bảng kê xe ngoài nào.</div>}
            {ke.map((st) => {
              const con = (st.conNo != null) ? st.conNo : (st.total - st.paid);
              return (
                <button key={st.id} type="button" onClick={() => onOpen(st)}
                  style={{ width: "100%", textAlign: "left", background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "12px 14px", cursor: "pointer", boxShadow: "0 1px 2px rgba(16,19,23,.04)" }}>
                  <div style={{ display: "flex", justifyContent: "space-between", gap: 10, alignItems: "baseline" }}>
                    <span className="tnum" style={{ fontWeight: 700, color: "var(--accent)", fontSize: 14 }}>{st.no}</span>
                    <span className="tnum" style={{ color: "var(--ink-3)", fontSize: 12.5 }}>{fmtDate(st.date)}</span>
                  </div>
                  <div style={{ fontWeight: 600, fontSize: 14.5, marginTop: 4 }}>{st.vendor}</div>
                  <div className="tnum" style={{ color: "var(--ink-4)", fontSize: 12, marginTop: 2 }}>{(st.from || st.to) ? `Giờ xe đến: ${fmtDate(st.from) || "…"} – ${fmtDate(st.to) || "…"}` : "—"} · {st.count} lô</div>
                  <div style={{ display: "flex", justifyContent: "space-between", gap: 12, marginTop: 9, paddingTop: 9, borderTop: "1px solid var(--line-2)" }}>
                    <span style={{ fontSize: 13, color: "var(--ink-3)" }}>Tổng: <b className="tnum" style={{ color: "var(--ink)" }}>{fmtVND(st.total)}</b></span>
                    <span style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Đã trả: <b className="tnum" style={{ color: "var(--good)" }}>{fmtVND(st.paid)}</b></span>
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
            <div>Số bảng kê</div><div>Nhà xe</div><div>Ngày lập</div><div>Kỳ (Giờ xe đến)</div><div style={{ textAlign: "right" }}>Số lô</div><div style={{ textAlign: "right" }}>Tổng cước</div><div style={{ textAlign: "right" }}>Đã trả</div><div style={{ textAlign: "right" }}>Còn nợ</div>
          </div>
          {ke.length === 0 && <div style={{ padding: "44px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5 }}>Chưa có bảng kê xe ngoài nào. Bấm “Tạo bảng kê mới” để bắt đầu.</div>}
          {ke.map((st) => {
            const con = (st.conNo != null) ? st.conNo : (st.total - st.paid);
            return (
            <button key={st.id} type="button" onClick={() => onOpen(st)}
              style={{ width: "100%", textAlign: "left", display: "grid", gridTemplateColumns: cols, gap: 12, alignItems: "center", padding: "12px 16px", borderBottom: "1px solid var(--line-2)", background: "transparent", border: "none", borderBottomStyle: "solid", cursor: "pointer", fontSize: 13.5 }}
              onMouseEnter={(e) => (e.currentTarget.style.background = "var(--accent-weak-2)")}
              onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}>
              <span className="tnum" style={{ fontWeight: 600, color: "var(--accent)" }}>{st.no}</span>
              <span style={{ fontWeight: 500 }}>{st.vendor}</span>
              <span className="tnum" style={{ color: "var(--ink-2)" }}>{fmtDate(st.date)}</span>
              <span className="tnum" style={{ color: "var(--ink-3)", fontSize: 12.5 }}>{(st.from || st.to) ? `${fmtDate(st.from) || "…"} – ${fmtDate(st.to) || "…"}` : "—"}</span>
              <span className="tnum" style={{ textAlign: "right", color: "var(--ink-3)" }}>{st.count}</span>
              <span className="tnum" style={{ textAlign: "right", fontWeight: 700 }}>{fmtVND(st.total)}</span>
              <span className="tnum" style={{ textAlign: "right", color: "var(--good)" }}>{fmtVND(st.paid)}</span>
              <span className="tnum" style={{ textAlign: "right", fontWeight: 700, color: con > 0 ? "var(--warn)" : "var(--good)" }}>{fmtVND(Math.max(0, con))}</span>
            </button>
          );})}
        </div>
        )}
      </div>
    </div>
  );
}

/* Header + bảng dòng (in được) — dùng chung cho tạo + xem. */
function ExtLinesTable({ vendor, no, date, from, to, lines, footerTotal }) {
  const th = (txt, align) => <th style={{ textAlign: align || "left", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>{txt}</th>;
  return (
    <>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", gap: 20, marginBottom: 14 }}>
        <div>
          <div style={{ fontSize: 18, fontWeight: 700, letterSpacing: "-0.01em" }}>BẢNG KÊ XE NGOÀI</div>
          <div style={{ fontSize: 12.5, color: "var(--ink-3)", marginTop: 3 }} className="tnum">Số: {no} · Ngày lập: {fmtDate(date)}{(from || to) ? ` · Giờ xe đến: ${fmtDate(from) || "…"} – ${fmtDate(to) || "…"}` : ""}</div>
        </div>
        <div style={{ textAlign: "right", fontSize: 12 }}>
          <div style={{ fontWeight: 700, color: "var(--accent)" }}>{CO_NAME}</div>
          <div style={{ color: "var(--ink-3)", marginTop: 2 }}>{CO_SUB}</div>
        </div>
      </div>
      <div style={{ background: "var(--accent-weak-2)", border: "1px solid var(--accent-weak)", borderRadius: 10, padding: "11px 14px", marginBottom: 14, fontSize: 12.5 }}>
        <div style={{ fontWeight: 700, fontSize: 14 }}>Nhà xe: {vendor || "—"}</div>
      </div>
      <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12.5 }}>
        <thead><tr style={{ background: "#fafbfc" }}>{th("#", "center")}{th("Lô / Booking")}{th("BKS")}{th("Tuyến")}{th("Ngày đến")}{th("Cước", "right")}</tr></thead>
        <tbody>
          {lines.length === 0 && <tr><td colSpan={6} style={{ padding: "20px", textAlign: "center", color: "var(--ink-4)" }}>Chưa có dòng nào.</td></tr>}
          {lines.map((l, i) => (
            <tr key={l.id}>
              <td className="tnum" style={{ textAlign: "center", padding: "8px", borderBottom: "1px solid var(--line-2)", color: "var(--ink-4)" }}>{i + 1}</td>
              <td style={{ padding: "8px", borderBottom: "1px solid var(--line-2)" }}>
                <div style={{ fontWeight: 600 }} className="tnum">{l.booking || "—"}</div>
                <div style={{ fontSize: 11, color: "var(--ink-4)" }}>{l.sheet} · {l.contLabel}</div>
              </td>
              <td className="tnum" style={{ padding: "8px", borderBottom: "1px solid var(--line-2)", color: "var(--ink-2)" }}>{l.bks || "—"}</td>
              <td style={{ padding: "8px", borderBottom: "1px solid var(--line-2)", color: "var(--ink-2)" }}>{[l.from, l.to].filter(Boolean).join(" → ") || "—"}</td>
              <td className="tnum" style={{ padding: "8px", borderBottom: "1px solid var(--line-2)", color: "var(--ink-2)" }}>{fmtDate(l.date) || "—"}</td>
              <td className="tnum" style={{ textAlign: "right", padding: "8px", borderBottom: "1px solid var(--line-2)", fontWeight: 600 }}>{fmtNum(l.fee)}</td>
            </tr>
          ))}
        </tbody>
        <tfoot>
          <tr style={{ fontWeight: 700 }}>
            <td colSpan={5} style={{ padding: "9px 8px", borderTop: "1.5px solid var(--line)", textAlign: "right", color: "var(--ink-3)" }}>TỔNG CƯỚC</td>
            <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{fmtVND(footerTotal)}</td>
          </tr>
        </tfoot>
      </table>
    </>
  );
}

/* ============================================================
 * Form tạo bảng kê xe ngoài: chọn nhà xe + kỳ Giờ xe đến → tích lô → lưu.
 * ============================================================ */
function ExtStatementForm({ cfg, onCancel, onSaved }) {
  const vendors = cfg.extVendors || [];
  const [vendor, setVendor] = useState(vendors[0] || "");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [picked, setPicked] = useState({});
  const [all, setAll] = useState([]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const today = new Date().toISOString().slice(0, 10);
  const needDate = !!vendor && !from && !to;

  useEffect(() => {
    if (!vendor || (!from && !to)) { setAll([]); setLoading(false); return; }
    let alive = true; setLoading(true); setPicked({});
    const p = new URLSearchParams({ vendor }); if (from) p.set("from", from); if (to) p.set("to", to);
    window.trkApi("GET", ROUTES_TRK.extStatementCandidates + "?" + p.toString())
      .then((r) => { if (alive) { setAll(r && r.ok ? (r.candidates || []) : []); setLoading(false); } })
      .catch(() => { if (alive) { setAll([]); setLoading(false); } });
    return () => { alive = false; };
  }, [vendor, from, to]);

  const sel = all.filter((x) => picked[x.id] !== false);
  const total = sel.reduce((a, x) => a + (x.fee || 0), 0);
  const keNo = "BKN-" + today.replace(/-/g, "").slice(2) + "-" + (vendor ? vendor.replace(/[^A-Za-zÀ-ỹ0-9]/g, "").slice(0, 3).toUpperCase() : "XXX");

  const save = async () => {
    if (!sel.length || saving) return;
    setSaving(true);
    const lines = sel.map((x) => ({
      id: x.id, booking: x.booking, sheet: x.sheet, bks: x.bks,
      from: x.from, to: x.to, contLabel: x.contLabel, date: x.date, fee: x.fee, note: x.note,
    }));
    const payload = { id: Date.now(), no: keNo, vendor, date: today, from, to, lines, payments: [] };
    let result;
    try { result = await Promise.resolve(onSaved && onSaved(payload)); }
    catch (e) { setSaving(false); return; }
    if (result === false) { setSaving(false); return; }
  };

  return (
    <div>
      {/* controls */}
      <div className="ke-noprint" style={{ display: "flex", gap: 12, alignItems: "flex-end", padding: "12px 0 14px", borderBottom: "1px solid var(--line-2)", flexWrap: "wrap" }}>
        <label style={{ display: "block" }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>Nhà xe</div>
          <div style={{ width: 240 }}>
            <Combo value={vendor} onChange={(v) => { setVendor(v); setPicked({}); }} options={vendors} placeholder="Chọn nhà xe…" strict />
          </div>
        </label>
        <div style={{ display: "block" }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>Giờ xe đến từ ngày</div>
          <div style={{ width: 150 }}><DateField value={from} onChange={setFrom} /></div>
        </div>
        <div style={{ display: "block" }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>đến ngày</div>
          <div style={{ width: 150 }}><DateField value={to} onChange={setTo} /></div>
        </div>
        <div style={{ flex: 1 }} />
        <div style={{ fontSize: 12, color: "var(--ink-4)" }}>{all.length} lô có cước</div>
      </div>

      <div className="ke-noprint" style={{ fontSize: 11.5, color: "var(--ink-4)", padding: "8px 0 0", display: "flex", alignItems: "flex-start", gap: 6, lineHeight: 1.5 }}>
        <i className="bi bi-info-circle" style={{ marginTop: 1 }} />
        <span>Lọc theo <b style={{ color: "var(--ink-3)" }}>Giờ xe đến</b> của lô hàng. Chỉ hiện lô đã có <b style={{ color: "var(--ink-3)" }}>cước thuê xe ngoài</b> ({"> 0"}).</span>
      </div>

      {/* printable statement */}
      <div className="ke-print" style={{ padding: "16px 4px 4px", background: "#fff" }}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", gap: 20, marginBottom: 14 }}>
          <div>
            <div style={{ fontSize: 18, fontWeight: 700, letterSpacing: "-0.01em" }}>BẢNG KÊ XE NGOÀI</div>
            <div style={{ fontSize: 12.5, color: "var(--ink-3)", marginTop: 3 }} className="tnum">Số: {keNo} · Ngày lập: {fmtDate(today)}{(from || to) ? ` · Giờ xe đến: ${fmtDate(from) || "…"} – ${fmtDate(to) || "…"}` : ""}</div>
          </div>
          <div style={{ textAlign: "right", fontSize: 12 }}>
            <div style={{ fontWeight: 700, color: "var(--accent)" }}>{CO_NAME}</div>
            <div style={{ color: "var(--ink-3)", marginTop: 2 }}>{CO_SUB}</div>
          </div>
        </div>
        <div style={{ background: "var(--accent-weak-2)", border: "1px solid var(--accent-weak)", borderRadius: 10, padding: "11px 14px", marginBottom: 14, fontSize: 12.5 }}>
          <div style={{ fontWeight: 700, fontSize: 14 }}>Nhà xe: {vendor || "—"}</div>
        </div>

        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12.5 }}>
          <thead>
            <tr style={{ background: "#fafbfc" }}>
              <th className="ke-noprint" style={{ width: 34, padding: "9px 8px", borderBottom: "1.5px solid var(--line)" }}></th>
              <th style={{ width: 34, textAlign: "center", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)" }}>#</th>
              <th style={{ textAlign: "left", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Lô / Booking</th>
              <th style={{ textAlign: "left", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>BKS</th>
              <th style={{ textAlign: "left", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Tuyến</th>
              <th style={{ textAlign: "left", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Ngày đến</th>
              <th style={{ textAlign: "right", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Cước</th>
            </tr>
          </thead>
          <tbody>
            {!loading && all.length === 0 && <tr><td colSpan={7} style={{ padding: "28px 24px", textAlign: "center", color: "var(--ink-4)" }}>
              {needDate
                ? <span style={{ display: "inline-flex", flexDirection: "column", alignItems: "center", gap: 6 }}>
                    <i className="bi bi-calendar-range" style={{ fontSize: 26, color: "var(--accent)", opacity: .8 }} />
                    <b style={{ color: "var(--ink-2)", fontSize: 13.5 }}>Vui lòng chọn khoảng Giờ xe đến</b>
                    <span style={{ fontSize: 12.5 }}>Chọn <b>từ ngày</b> (và đến ngày) ở trên để lọc lô.</span>
                  </span>
                : (vendor ? "Không có lô nào phù hợp trong kỳ đã chọn." : "Chọn nhà xe để bắt đầu.")}
            </td></tr>}
            {loading && <tr><td colSpan={7} style={{ padding: "24px", textAlign: "center", color: "var(--ink-4)" }}>Đang tải lô…</td></tr>}
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
                    <div style={{ fontSize: 11, color: "var(--ink-4)" }}>{x.sheet} · {x.contLabel}</div>
                  </td>
                  <td className="tnum" style={{ padding: "8px", borderBottom: "1px solid var(--line-2)", color: "var(--ink-2)" }}>{x.bks || "—"}</td>
                  <td style={{ padding: "8px", borderBottom: "1px solid var(--line-2)", color: "var(--ink-2)" }}>{[x.from, x.to].filter(Boolean).join(" → ") || "—"}</td>
                  <td className="tnum" style={{ padding: "8px", borderBottom: "1px solid var(--line-2)", color: "var(--ink-2)" }}>{fmtDate(x.date) || "—"}</td>
                  <td className="tnum" style={{ textAlign: "right", padding: "8px", borderBottom: "1px solid var(--line-2)", fontWeight: 600 }}>{fmtNum(x.fee)}</td>
                </tr>
              );
            })}
          </tbody>
          <tfoot>
            <tr style={{ fontWeight: 700 }}>
              <td className="ke-noprint" style={{ borderTop: "1.5px solid var(--line)" }}></td>
              <td colSpan={5} style={{ padding: "9px 8px", borderTop: "1.5px solid var(--line)", textAlign: "right", color: "var(--ink-3)" }}>TỔNG CƯỚC ({sel.length} lô)</td>
              <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{fmtVND(total)}</td>
            </tr>
          </tfoot>
        </table>

        <div className="ke-noprint" style={{ marginTop: 14, fontSize: 12.5, color: "var(--ink-4)" }}>Sau khi lưu, mở bảng kê để nhập các đợt thanh toán cho nhà xe và theo dõi công nợ.</div>
      </div>

      <div className="ke-noprint" style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 16, marginTop: 8, paddingTop: 14, borderTop: "1px solid var(--line)" }}>
        <div style={{ fontSize: 12.5, color: "var(--ink-3)" }}>{sel.length} lô · tổng cước <b className="tnum" style={{ color: "var(--ink)" }}>{fmtVND(total)}</b></div>
        <div style={{ display: "flex", gap: 10 }}>
          <Btn onClick={onCancel}>Hủy</Btn>
          <Btn onClick={() => window.print()}>In thử</Btn>
          <Btn variant="primary" onClick={save} disabled={saving || !sel.length}>{saving ? "Đang lưu…" : "Lưu bảng kê"}</Btn>
        </div>
      </div>
    </div>
  );
}

/* ============================================================
 * Trang xem bảng kê xe ngoài đã lưu: dòng + thanh toán + công nợ.
 * ============================================================ */
function SavedExtStatementPage({ st, onUpdate, onSave, onDelete, isDirty, backUrl }) {
  const [payments, setPayments] = useState(st.payments || []);
  const sync = (arr) => { setPayments(arr); onUpdate && onUpdate({ ...st, payments: arr }); };
  const setP = (id, np) => sync(payments.map((p) => (p.id === id ? { ...p, ...np } : p)));
  const addP = () => sync([...payments, { id: Date.now() + Math.random(), date: new Date().toISOString().slice(0, 10), amount: "", note: "" }]);
  const delP = (id) => sync(payments.filter((p) => p.id !== id));
  const grp = (d) => { d = (d || "").toString().replace(/[^\d]/g, ""); return d ? d.replace(/\B(?=(\d{3})+(?!\d))/g, ".") : ""; };

  const total = st.total || 0;
  const paid = payments.reduce((a, p) => a + toNum(p.amount), 0);
  const conNo = total - paid;
  const dirty = !!(isDirty && isDirty(st.id));
  const [saving, setSaving] = useState(false);
  const doSave = () => { if (saving || !dirty) return; setSaving(true); Promise.resolve(onSave && onSave()).then(() => setSaving(false)).catch(() => setSaving(false)); };
  const esc = (s) => String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const askDelete = async () => {
    if (!onDelete) return;
    const ok = await window.confirmAction({
      title: "Xóa bảng kê xe ngoài?",
      text: `Bảng kê <b>${esc(st.no || "(chưa có số)")}</b>${st.vendor ? " · " + esc(st.vendor) : ""} sẽ bị xóa vĩnh viễn cùng toàn bộ đợt thanh toán. Không thể hoàn tác.`,
      confirmText: '<i class="bi bi-trash me-1"></i> Xóa bảng kê',
      danger: true,
    });
    if (ok) Promise.resolve(onDelete(st.id)).then(() => { window.location.href = backUrl; });
  };
  const th = (txt, align) => <th style={{ textAlign: align || "left", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>{txt}</th>;

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <div className="ke-noprint trk-head" style={{ display: "flex", alignItems: "center", gap: 10, padding: "14px 22px", background: "#fff", borderBottom: "1px solid var(--line)" }}>
        <div className="trk-head-lead" style={{ display: "flex", alignItems: "center", gap: 10, flex: 1, minWidth: 0 }}>
          <a href={backUrl} title="Về danh sách bảng kê xe ngoài"
            style={{ display: "inline-flex", alignItems: "center", gap: 6, flexShrink: 0, padding: "7px 12px", fontSize: 13, fontWeight: 600, color: "var(--ink-2)", textDecoration: "none", border: "1px solid var(--line)", borderRadius: 9 }}>
            <span style={{ transform: "rotate(180deg)", display: "inline-flex" }}><I.arrow /></span> Bảng kê xe ngoài
          </a>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 16, fontWeight: 700, letterSpacing: "-0.01em" }}>Bảng kê xe ngoài</div>
            <div className="tnum" style={{ fontSize: 12.5, color: "var(--ink-3)" }}>{st.no} · {st.vendor}</div>
          </div>
        </div>
      </div>
      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: "22px" }}>
        <div style={{ maxWidth: 940, margin: "0 auto", background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "8px 22px 18px" }}>
          <div className="ke-print" style={{ padding: "16px 4px 4px", background: "#fff" }}>
            <ExtLinesTable vendor={st.vendor} no={st.no} date={st.date} from={st.from} to={st.to} lines={st.lines || []} footerTotal={total} />

            {/* payments / công nợ */}
            <div style={{ marginTop: 18 }}>
              <div style={{ fontSize: 12.5, fontWeight: 700, color: "var(--ink-2)", textTransform: "uppercase", letterSpacing: "0.04em", marginBottom: 6 }}>Thanh toán cho nhà xe (nhiều đợt)</div>
              <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12.5 }}>
                <thead><tr style={{ background: "#fafbfc" }}>{th("Đợt", "center")}{th("Ngày trả")}{th("Ghi chú")}{th("Số tiền", "right")}<th className="ke-noprint" style={{ width: 34, borderBottom: "1.5px solid var(--line)" }}></th></tr></thead>
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
              <div style={{ width: 320, background: "#fafbfc", border: "1px solid var(--line)", borderRadius: 10, padding: "12px 16px" }}>
                <div style={{ display: "flex", justifyContent: "space-between", fontSize: 15, marginBottom: 8 }}>
                  <span style={{ fontWeight: 700 }}>TỔNG CƯỚC</span><b className="tnum">{fmtVND(total)}</b>
                </div>
                <div style={{ display: "flex", justifyContent: "space-between", fontSize: 13, marginBottom: 8 }}>
                  <span style={{ color: "var(--ink-3)" }}>Đã trả ({payments.length} đợt)</span><b className="tnum" style={{ color: "var(--good)" }}>{fmtVND(paid)}</b>
                </div>
                <div style={{ display: "flex", justifyContent: "space-between", fontSize: 15, paddingTop: 8, borderTop: "1px solid var(--line)" }}>
                  <span style={{ fontWeight: 600 }}>CÒN PHẢI TRẢ</span><b className="tnum" style={{ color: conNo > 0 ? "var(--warn)" : "var(--good)" }}>{fmtVND(Math.max(0, conNo))}</b>
                </div>
              </div>
            </div>
          </div>

          <div className="ke-noprint" style={{ marginTop: 16, paddingTop: 14, borderTop: "1px solid var(--line)", display: "flex", alignItems: "center", justifyContent: "space-between", gap: 16 }}>
            <button type="button" onClick={askDelete}
              style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "8px 13px", fontSize: 13, fontWeight: 500, border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: "var(--ink-3)", cursor: "pointer" }}
              onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
              onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-3)"; }}>
              <I.trash /> Xóa bảng kê
            </button>
            <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
              {dirty && <span style={{ fontSize: 12, color: "var(--warn)", fontWeight: 600, display: "inline-flex", alignItems: "center", gap: 5 }}><span style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} />Có thay đổi chưa lưu</span>}
              <Btn variant="primary" onClick={doSave} disabled={!dirty || saving}>{saving ? "Đang lưu…" : "Lưu"}</Btn>
              <Btn onClick={() => window.print()}>In / Xuất PDF</Btn>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

export { ExtKePage, ExtStatementForm, SavedExtStatementPage };
