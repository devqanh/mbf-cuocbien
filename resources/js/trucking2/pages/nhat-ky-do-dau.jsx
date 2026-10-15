import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useEffect, useRef, useMemo } = React;
import { I, useIsMobile, DateField, Combo, Modal, Btn, fmtNum, fmtVND, fmtDate } from "@trk/lib.jsx";

/**
 * NHẬT KÝ ĐỔ DẦU — ghi nhận xe MBF đổ dầu ngày nào, bao nhiêu lít.
 *  - Ô nhập nhanh: giữ ngày, chọn biển số → gõ số lít → Enter (đơn giá tự gợi ý theo Bảng giá dầu).
 *  - Lọc theo tháng / khoảng ngày / xe / tìm; tổng hợp toàn bộ kết quả lọc (không chỉ 1 trang).
 *  - 2 cách xem: "Theo ngày" (gom nhóm từng ngày + tổng ngày) và "Theo xe" (tổng lít / số lần / lần cuối).
 * Cùng dữ liệu với tab "Dầu" ở Quản lý xe.
 */

const z = (n) => String(n).padStart(2, "0");
const toYmd = (d) => `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}`;
const todayYmd = () => toYmd(new Date());
const WEEKDAY = ["Chủ nhật", "Thứ 2", "Thứ 3", "Thứ 4", "Thứ 5", "Thứ 6", "Thứ 7"];
const weekday = (ymd) => { const [y, m, d] = ymd.split("-").map(Number); return WEEKDAY[new Date(y, m - 1, d).getDay()]; };
const daysAgo = (ymd) => { if (!ymd) return null; const [y, m, d] = ymd.split("-").map(Number); const t = new Date(); t.setHours(0, 0, 0, 0); return Math.round((t - new Date(y, m - 1, d)) / 86400000); };
// Số lít: chấp nhận dấu phẩy thập phân kiểu VN ("150,5") → "150.5"; chỉ giữ 1 dấu chấm.
const litersInput = (s) => { let v = String(s).replace(/,/g, ".").replace(/[^\d.]/g, ""); const i = v.indexOf("."); return i < 0 ? v : v.slice(0, i + 1) + v.slice(i + 1).replace(/\./g, ""); };
const digits = (s) => String(s).replace(/[^\d]/g, "");
const fmtL = (n) => (Math.round((n || 0) * 100) / 100).toLocaleString("vi-VN");

function FuelLogPage() {
  const isMobile = useIsMobile();
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const BOOT = T.boot || {};
  const canEdit = !!T.canEdit;
  const api = (m, u, b) => window.trkApi(m, u, b);
  const toast = (msg, type) => window.trkToast && window.trkToast(msg, type);

  const vehicles = BOOT.vehicles || [];
  const vById = useMemo(() => Object.fromEntries(vehicles.map((v) => [String(v.id), v])), [vehicles]);
  const plateOpts = useMemo(() => vehicles.map((v) => ({ value: String(v.id), label: v.driver ? `${v.plate} · ${v.driver}` : v.plate })), [vehicles]);
  // Đơn giá gợi ý = bảng giá dầu hiệu lực tại ngày đổ (khoảng from..to, to rỗng = đến nay).
  const priceOn = (ymd) => { let p = null; (BOOT.fuelPrices || []).forEach((r) => { if (r.from && r.from <= ymd && (!r.to || r.to >= ymd)) p = r.price; }); return p; };

  // ---- bộ lọc (mặc định: tháng hiện tại) ----
  const thisYm = () => todayYmd().slice(0, 7);
  const monthRange = (ym) => { const [y, m] = ym.split("-").map(Number); return [toYmd(new Date(y, m - 1, 1)), toYmd(new Date(y, m, 0))]; };
  const [month, setMonth] = useState(thisYm());
  const [from, setFrom] = useState(monthRange(thisYm())[0]);
  const [to, setTo] = useState(monthRange(thisYm())[1]);
  const [vehicleId, setVehicleId] = useState("");
  const [q, setQ] = useState(""); const [qDeb, setQDeb] = useState("");
  const [view, setView] = useState("day");   // "day" | "vehicle"
  const [page, setPage] = useState(1);
  const [data, setData] = useState({ rows: [], vehicles: [], totals: { count: 0, liters: 0, cost: 0, vehicles: 0 }, page: 1, lastPage: 1, total: 0 });
  const [loading, setLoading] = useState(true);
  const reqId = useRef(0);

  useEffect(() => { const t = setTimeout(() => { setQDeb(q); setPage(1); }, 350); return () => clearTimeout(t); }, [q]);

  const params = (extra = {}) => {
    const p = new URLSearchParams();
    if (from) p.set("from", from); if (to) p.set("to", to);
    if (vehicleId) p.append("vehicles[]", vehicleId);
    if (qDeb.trim()) p.set("q", qDeb.trim());
    Object.entries(extra).forEach(([k, v]) => p.set(k, String(v)));
    return p;
  };
  const load = () => {
    const my = ++reqId.current; setLoading(true);
    api("GET", ROUTES.data + "?" + params({ page, perPage: 100 }).toString()).then((r) => {
      if (my === reqId.current && r && r.ok) setData(r);
    }).catch(() => {}).finally(() => { if (my === reqId.current) setLoading(false); });
  };
  useEffect(() => { load(); }, [from, to, vehicleId, qDeb, page]);

  const setRange = (f, t) => { setFrom(f); setTo(t); setPage(1); };
  const setCustomRange = (f, t) => { setMonth(""); setRange(f, t); };
  const pickMonth = (ym) => { setMonth(ym); setRange(...monthRange(ym)); };
  const stepMonth = (delta) => { const [y, m] = (month || thisYm()).split("-").map(Number); const d = new Date(y, m - 1 + delta, 1); pickMonth(`${d.getFullYear()}-${z(d.getMonth() + 1)}`); };
  const presetDays = (n) => { const now = new Date(); const f = new Date(now); f.setDate(now.getDate() - (n - 1)); setCustomRange(toYmd(f), toYmd(now)); };
  const monthLabel = (ym) => { const [y, m] = ym.split("-"); return `Tháng ${Number(m)}/${y}`; };
  const focusVehicle = (id) => { setVehicleId(String(id)); setView("day"); setPage(1); };

  // ---- nhập nhanh ----
  const [qe, setQe] = useState({ date: todayYmd(), vehicleId: "", liters: "", unitPrice: "", station: "", note: "" });
  const [qeAutoPrice, setQeAutoPrice] = useState(true);   // đơn giá đang là giá gợi ý (đổi ngày → cập nhật theo)
  const [saving, setSaving] = useState(false);
  const litersRef = useRef(null);
  useEffect(() => { if (qeAutoPrice) { const p = priceOn(qe.date); setQe((s) => ({ ...s, unitPrice: p ? String(p) : "" })); } }, [qe.date, qeAutoPrice]);

  const esc = (v) => String(v == null ? "" : v).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

  // Bấm Ghi → luôn hỏi xác nhận (tóm tắt phiếu); nếu xe đã có phiếu cùng ngày thì cảnh báo ngay trong popup
  // (tránh ghi trùng khi 2 người cùng nhập). Enter trong popup = Đồng ý → vẫn nhập liền tay bằng bàn phím.
  const submitQuick = async () => {
    if (saving) return;
    const liters = parseFloat(qe.liters);
    if (!qe.vehicleId) { toast("Chưa chọn biển số xe", "error"); return; }
    if (!(liters > 0)) { toast("Nhập số lít đổ", "error"); litersRef.current && litersRef.current.focus(); return; }
    setSaving(true);
    try {
      const p = new URLSearchParams({ from: qe.date, to: qe.date, perPage: "10" }); p.append("vehicles[]", qe.vehicleId);
      const same = ((await api("GET", ROUTES.data + "?" + p.toString())) || {}).rows || [];
      const plate = vById[qe.vehicleId]?.plate || "";
      const total = qe.unitPrice ? Math.round(liters * Number(qe.unitPrice)) : 0;
      const line = (k, v) => `<div style="display:flex;justify-content:space-between;gap:12px;padding:3px 0"><span style="color:var(--ink-3)">${k}</span><b>${v}</b></div>`;
      const text = line("Biển số", esc(plate)) + line("Ngày đổ", `${weekday(qe.date)}, ${fmtDate(qe.date)}`) + line("Số lít", `${fmtL(liters)} lít`)
        + (qe.unitPrice ? line("Đơn giá", fmtNum(Number(qe.unitPrice)) + " ₫") + line("Thành tiền", fmtVND(total)) : "")
        + (qe.station ? line("Trạm đổ", esc(qe.station)) : "") + (qe.note ? line("Ghi chú", esc(qe.note)) : "")
        + (same.length ? `<div style="margin-top:10px;padding:8px 10px;border-radius:8px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:12.5px"><i class="bi bi-exclamation-triangle me-1"></i>Xe này đã có ${same.length} phiếu trong ngày: ${same.map((x) => fmtL(x.liters) + " lít").join(", ")}. Ghi thêm sẽ cộng dồn.</div>` : "");
      const ok = await window.confirmAction({ title: "Ghi lần đổ dầu này?", text, confirmText: '<i class="bi bi-check2 me-1"></i> Đồng ý ghi', icon: "bi-fuel-pump" });
      if (!ok) return;
      const r = await api("POST", ROUTES.save, { vehicleId: Number(qe.vehicleId), date: qe.date, liters, unitPrice: qe.unitPrice || null, station: qe.station, note: qe.note });
      if (r && r.ok) {
        toast(`Đã ghi ${plate} · ${fmtL(liters)} lít`);
        // giữ ngày + trạm (thường nhập liền nhiều xe cùng ngày/cùng trạm), xóa xe + lít + ghi chú
        setQe((s) => ({ ...s, vehicleId: "", liters: "", note: "" }));
        load();
      }
    } catch (e) { /* trkApi đã báo lỗi */ } finally { setSaving(false); }
  };

  // ---- sửa / xóa ----
  const [editing, setEditing] = useState(null);
  const startEdit = (r) => setEditing({ id: r.id, vehicleId: String(r.vehicleId), date: r.date, liters: String(r.liters), unitPrice: r.unitPrice != null ? String(r.unitPrice) : "", odometerKm: r.odometerKm != null ? String(r.odometerKm) : "", station: r.station, note: r.note });
  const saveEdit = async () => {
    const liters = parseFloat(editing.liters);
    if (!editing.vehicleId || !(liters > 0)) { toast("Cần biển số và số lít > 0", "error"); return; }
    try {
      const r = await api("POST", ROUTES.save, { ...editing, vehicleId: Number(editing.vehicleId), liters, unitPrice: editing.unitPrice || null, odometerKm: editing.odometerKm || null });
      if (r && r.ok) { toast("Đã cập nhật phiếu đổ dầu"); setEditing(null); load(); }
    } catch (e) { /* trkApi đã báo lỗi */ }
  };
  const remove = async (r) => {
    const ok = await window.confirmAction({ title: "Xóa phiếu đổ dầu?", text: `${r.plate} · ${fmtDate(r.date)} · ${fmtL(r.liters)} lít`, confirmText: '<i class="bi bi-trash me-1"></i> Xóa', danger: true });
    if (!ok) return;
    try { const res = await api("DELETE", ROUTES.base + "/" + r.id); if (res && res.ok) { toast("Đã xóa phiếu đổ dầu"); load(); } } catch (e) { /* trkApi đã báo lỗi */ }
  };

  // ---- xuất CSV toàn bộ kết quả lọc ----
  const [exporting, setExporting] = useState(false);
  const exportCsv = async () => {
    setExporting(true);
    try {
      let all = [], pg = 1, last = 1;
      do {
        const r = await api("GET", ROUTES.data + "?" + params({ page: pg, perPage: 200 }).toString());
        all = all.concat(r.rows || []); last = r.lastPage || 1; pg++;
      } while (pg <= last);
      const esc = (v) => '"' + String(v == null ? "" : v).replace(/"/g, '""') + '"';
      const lines = [["STT", "Ngày", "Thứ", "Biển số", "Số lít", "Đơn giá", "Thành tiền", "Km đồng hồ", "Trạm đổ", "Ghi chú"].map(esc).join(",")];
      all.forEach((r, i) => lines.push([i + 1, fmtDate(r.date), weekday(r.date), r.plate, String(r.liters).replace(".", ","), r.unitPrice ?? "", r.totalCost ?? "", r.odometerKm ?? "", r.station, r.note].map(esc).join(",")));
      const blob = new Blob(["﻿" + lines.join("\r\n")], { type: "text/csv;charset=utf-8;" });
      const url = URL.createObjectURL(blob); const a = document.createElement("a");
      a.href = url; a.download = `nhat-ky-do-dau-${month || (from || "dau") + "_" + (to || "nay")}.csv`;
      document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
    } catch (e) { /* trkApi đã báo lỗi */ } finally { setExporting(false); }
  };

  // ---- gom theo ngày (trên trang hiện tại) ----
  const groups = useMemo(() => {
    const out = []; let cur = null;
    (data.rows || []).forEach((r) => {
      if (!cur || cur.date !== r.date) { cur = { date: r.date, rows: [], liters: 0, cost: 0 }; out.push(cur); }
      cur.rows.push(r); cur.liters += r.liters; cur.cost += r.totalCost || 0;
    });
    return out;
  }, [data.rows]);

  // ---- styles ----
  const th = { textAlign: "left", fontSize: 11, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.03em", padding: "9px 12px", background: "#fafbfc", borderBottom: "1px solid var(--line)", whiteSpace: "nowrap" };
  const td = { padding: "9px 12px", fontSize: 13, borderBottom: "1px solid var(--line-2)", verticalAlign: "middle" };
  const inp = { width: "100%", padding: "8px 11px", fontSize: isMobile ? 16 : 13.5, border: "1px solid var(--line)", borderRadius: 9, outline: "none", background: "#fff", boxSizing: "border-box" };
  const lbl = { fontSize: 11, fontWeight: 700, color: "var(--ink-3)", marginBottom: 4, textTransform: "uppercase", letterSpacing: "0.03em" };
  const chipBtn = (label, onClick, on) => (
    <button type="button" onClick={onClick}
      style={{ padding: "6px 10px", fontSize: 12, fontWeight: 600, border: `1px solid ${on ? "var(--accent)" : "var(--line)"}`, borderRadius: 8, background: on ? "var(--accent-weak)" : "#fff", color: on ? "var(--accent)" : "var(--ink-2)", cursor: "pointer", whiteSpace: "nowrap" }}>{label}</button>
  );
  const tabBtn = (k, label, icon) => {
    const on = view === k;
    return (
      <button type="button" onClick={() => setView(k)}
        style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 14px", fontSize: 13, fontWeight: 600, border: "none", borderRadius: 7, cursor: "pointer", whiteSpace: "nowrap", background: on ? "#fff" : "transparent", color: on ? "#0f766e" : "var(--ink-3)", boxShadow: on ? "0 1px 2px rgba(16,19,23,.12)" : "none" }}>
        <i className={"bi " + icon} /> {label}
      </button>
    );
  };
  const iconBtn = { border: "none", background: "transparent", cursor: "pointer", padding: "4px 6px", color: "var(--ink-3)", borderRadius: 6 };
  const plateTag = (r) => (
    <button type="button" onClick={() => focusVehicle(r.vehicleId)} title="Lọc theo xe này"
      style={{ border: "1px solid #99f6e4", background: "#f0fdfa", color: "#0f766e", fontWeight: 700, fontSize: 12.5, padding: "2px 9px", borderRadius: 7, cursor: "pointer" }} className="tnum">{r.plate}</button>
  );

  const T0 = data.totals || {};
  const kpis = [
    { label: "Tổng số lít", value: fmtL(T0.liters), unit: "lít", color: "#0f766e" },
    { label: "Lần đổ", value: fmtNum(T0.count), unit: "phiếu" },
    { label: "Số xe", value: fmtNum(T0.vehicles), unit: `/ ${vehicles.length} xe` },
    { label: "TB mỗi lần", value: T0.count ? fmtL(T0.liters / T0.count) : "0", unit: "lít" },
    { label: "Thành tiền", value: fmtVND(T0.cost || 0), unit: "" },
  ];
  const maxVehLiters = Math.max(1, ...(data.vehicles || []).map((v) => v.liters));
  // Xe MBF CHƯA đổ dầu lần nào trong kỳ lọc (chỉ có nghĩa khi không lọc 1 xe / tìm kiếm).
  const notRefilled = !vehicleId && !qDeb.trim() ? vehicles.filter((v) => !(data.vehicles || []).some((x) => x.vehicleId === v.id)) : [];

  const pageList = () => {
    const last = data.lastPage || 1, cur = data.page || 1;
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
    const arr = [...new Set([1, last, cur, cur - 1, cur + 1])].filter((n) => n >= 1 && n <= last).sort((a, b) => a - b);
    const out = []; let prev = 0; arr.forEach((n) => { if (n - prev > 1) out.push("…"); out.push(n); prev = n; }); return out;
  };

  const editFields = (st, set, compact) => (
    <>
      <div style={{ flex: compact ? "0 0 150px" : undefined }}>
        <div style={lbl}>Ngày đổ</div>
        <DateField value={st.date} onChange={(v) => set({ ...st, date: v || todayYmd() })} />
      </div>
      <div style={{ flex: compact ? "1 1 200px" : undefined, minWidth: 0 }}>
        <div style={lbl}>Biển số xe</div>
        <Combo value={st.vehicleId} onChange={(v) => { set({ ...st, vehicleId: v || "" }); if (compact && v) setTimeout(() => litersRef.current && litersRef.current.focus(), 0); }} options={plateOpts} strict placeholder="Chọn biển số…" />
      </div>
      <div style={{ flex: compact ? "0 0 120px" : undefined }}>
        <div style={lbl}>Số lít</div>
        <div style={{ position: "relative" }}>
          <input ref={compact ? litersRef : null} inputMode="decimal" value={st.liters} placeholder="0"
            onChange={(e) => set({ ...st, liters: litersInput(e.target.value) })}
            onKeyDown={(e) => { if (compact && e.key === "Enter") { e.preventDefault(); submitQuick(); } }}
            className="tnum" style={{ ...inp, textAlign: "right", paddingRight: 34, fontWeight: 700 }} />
          <span style={{ position: "absolute", right: 10, top: "50%", transform: "translateY(-50%)", fontSize: 12, color: "var(--ink-4)" }}>lít</span>
        </div>
      </div>
      <div style={{ flex: compact ? "0 0 130px" : undefined }}>
        <div style={lbl}>Đơn giá {compact && qeAutoPrice && st.unitPrice ? <span style={{ textTransform: "none", fontWeight: 600, color: "var(--good)" }}>· theo bảng giá</span> : null}</div>
        <div style={{ position: "relative" }}>
          <input inputMode="numeric" value={st.unitPrice ? Number(st.unitPrice).toLocaleString("vi-VN") : ""} placeholder="—"
            onChange={(e) => { if (compact) setQeAutoPrice(false); set({ ...st, unitPrice: digits(e.target.value) }); }}
            onKeyDown={(e) => { if (compact && e.key === "Enter") { e.preventDefault(); submitQuick(); } }}
            className="tnum" style={{ ...inp, textAlign: "right", paddingRight: 30 }} />
          <span style={{ position: "absolute", right: 10, top: "50%", transform: "translateY(-50%)", fontSize: 12, color: "var(--ink-4)" }}>₫</span>
        </div>
      </div>
      <div style={{ flex: compact ? "1 1 150px" : undefined, minWidth: 0 }}>
        <div style={lbl}>Trạm đổ</div>
        <input value={st.station || ""} onChange={(e) => set({ ...st, station: e.target.value })} placeholder="Tùy chọn"
          onKeyDown={(e) => { if (compact && e.key === "Enter") { e.preventDefault(); submitQuick(); } }} style={inp} />
      </div>
      <div style={{ flex: compact ? "1 1 150px" : undefined, minWidth: 0 }}>
        <div style={lbl}>Ghi chú</div>
        <input value={st.note || ""} onChange={(e) => set({ ...st, note: e.target.value })} placeholder="Tùy chọn"
          onKeyDown={(e) => { if (compact && e.key === "Enter") { e.preventDefault(); submitQuick(); } }} style={inp} />
      </div>
    </>
  );

  const qeTotal = parseFloat(qe.liters) > 0 && qe.unitPrice ? Math.round(parseFloat(qe.liters) * Number(qe.unitPrice)) : 0;

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: isMobile ? "10px 14px" : "0 22px", flexShrink: 0 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 12, minHeight: isMobile ? 0 : 58, flexWrap: "wrap" }}>
          <div style={{ width: 32, height: 32, borderRadius: 9, background: "#0f766e", color: "#fff", display: "grid", placeItems: "center", flexShrink: 0 }}><i className="bi bi-fuel-pump" /></div>
          <div>
            <div style={{ fontSize: 15.5, fontWeight: 700 }}>Nhật ký đổ dầu</div>
            <div style={{ fontSize: 11.5, color: "var(--ink-3)" }}>{fmtNum(T0.count)} lần đổ · {fmtL(T0.liters)} lít · {fmtNum(T0.vehicles)} xe trong kỳ</div>
          </div>
          <div style={{ flex: 1 }} />
          <div style={{ position: "relative", flex: isMobile ? "1 1 100%" : "0 0 auto" }}>
            <span style={{ position: "absolute", left: 11, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)" }}><I.search /></span>
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Tìm biển số, trạm, ghi chú…"
              style={{ width: isMobile ? "100%" : 240, padding: "9px 12px 9px 34px", fontSize: isMobile ? 16 : 13.5, border: "1px solid var(--line)", borderRadius: 10, outline: "none", background: "#fafbfc", boxSizing: "border-box" }} />
          </div>
          <button type="button" onClick={exportCsv} disabled={exporting || !T0.count}
            style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "8px 13px", fontSize: 12.5, fontWeight: 600, border: "1px solid var(--good)", borderRadius: 9, background: "var(--good-weak)", color: "var(--good)", cursor: exporting || !T0.count ? "default" : "pointer", opacity: !T0.count ? 0.5 : 1, whiteSpace: "nowrap" }}>
            <i className="bi bi-filetype-csv" /> {exporting ? "Đang xuất…" : "Xuất Excel (CSV)"}
          </button>
        </div>
        {/* thanh lọc */}
        <div style={{ display: "flex", alignItems: "center", gap: 10, flexWrap: "wrap", padding: "0 0 11px" }}>
          <div style={{ display: "inline-flex", alignItems: "center", gap: 2, border: `1px solid ${month ? "var(--accent)" : "var(--line)"}`, borderRadius: 9, background: month ? "var(--accent-weak)" : "#fff", overflow: "hidden" }}>
            <button type="button" onClick={() => stepMonth(-1)} title="Tháng trước" style={{ border: "none", background: "transparent", cursor: "pointer", padding: "6px 9px", color: "var(--ink-2)", fontSize: 13 }}>‹</button>
            <button type="button" onClick={() => pickMonth(thisYm())} title="Về tháng hiện tại"
              style={{ border: "none", background: "transparent", cursor: "pointer", padding: "6px 8px", fontSize: 12.5, fontWeight: 700, color: month ? "var(--accent)" : "var(--ink-2)", whiteSpace: "nowrap", minWidth: 96, textAlign: "center" }}>
              {month ? monthLabel(month) : "Chọn tháng"}
            </button>
            <button type="button" onClick={() => stepMonth(1)} title="Tháng sau" style={{ border: "none", background: "transparent", cursor: "pointer", padding: "6px 9px", color: "var(--ink-2)", fontSize: 13 }}>›</button>
          </div>
          <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
            <span style={{ fontSize: 12, color: "var(--ink-3)" }}>Từ</span>
            <div style={{ width: 140 }}><DateField value={from} onChange={(v) => setCustomRange(v, to)} /></div>
            <span style={{ fontSize: 12, color: "var(--ink-3)" }}>đến</span>
            <div style={{ width: 140 }}><DateField value={to} onChange={(v) => setCustomRange(from, v)} /></div>
          </div>
          {chipBtn("Hôm nay", () => setCustomRange(todayYmd(), todayYmd()), !month && from === todayYmd() && to === todayYmd())}
          {chipBtn("7 ngày", () => presetDays(7))}
          {chipBtn("Tất cả", () => setCustomRange("", ""), !month && !from && !to)}
          <span style={{ width: 1, height: 22, background: "var(--line-2)" }} />
          <div style={{ width: isMobile ? "100%" : 230 }}>
            <Combo value={vehicleId} onChange={(v) => { setVehicleId(v || ""); setPage(1); }} options={plateOpts} strict clearable placeholder="Tất cả xe" />
          </div>
        </div>
      </header>

      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: isMobile ? "12px 12px 24px" : "16px 22px 24px" }}>
        <div style={{ maxWidth: 1180, margin: "0 auto", display: "flex", flexDirection: "column", gap: 14 }}>

          {/* Nhập nhanh */}
          {canEdit && (
            <div style={{ background: "#fff", border: "1px solid #99f6e4", borderRadius: 12, padding: "12px 14px", boxShadow: "0 1px 0 rgba(15,118,110,.06)" }}>
              <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 10 }}>
                <i className="bi bi-plus-circle-fill" style={{ color: "#0f766e" }} />
                <span style={{ fontWeight: 700, fontSize: 13.5 }}>Ghi nhanh lần đổ dầu</span>
                <span style={{ fontSize: 11.5, color: "var(--ink-4)" }}>Chọn xe → gõ số lít → Enter. Ngày và trạm được giữ lại để ghi tiếp xe khác.</span>
              </div>
              <div style={{ display: "flex", gap: 10, flexWrap: "wrap", alignItems: "flex-end" }}>
                {editFields(qe, setQe, true)}
                <div style={{ display: "flex", flexDirection: "column", alignItems: "flex-end", gap: 2 }}>
                  {qeTotal > 0 && <span className="tnum" style={{ fontSize: 11.5, color: "var(--ink-3)" }}>= {fmtVND(qeTotal)}</span>}
                  <button type="button" onClick={submitQuick} disabled={saving}
                    style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "9px 18px", fontSize: 13.5, fontWeight: 700, border: "none", borderRadius: 9, background: "#0f766e", color: "#fff", cursor: saving ? "default" : "pointer", opacity: saving ? 0.6 : 1, whiteSpace: "nowrap" }}>
                    <i className="bi bi-check2" /> {saving ? "Đang ghi…" : "Ghi"}
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* KPI */}
          <div style={{ display: "grid", gridTemplateColumns: isMobile ? "repeat(2, minmax(0,1fr))" : "repeat(5, minmax(0,1fr))", gap: 10 }}>
            {kpis.map((k) => (
              <div key={k.label} style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "11px 14px" }}>
                <div style={{ fontSize: 11, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.03em" }}>{k.label}</div>
                <div className="tnum" style={{ fontSize: 20, fontWeight: 800, color: k.color || "var(--ink)", marginTop: 3, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                  {k.value} {k.unit && <span style={{ fontSize: 12, fontWeight: 600, color: "var(--ink-4)" }}>{k.unit}</span>}
                </div>
              </div>
            ))}
          </div>

          {/* Bảng */}
          <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, overflow: "hidden" }}>
            <div style={{ display: "flex", alignItems: "center", gap: 10, padding: "10px 12px", borderBottom: "1px solid var(--line-2)", flexWrap: "wrap" }}>
              <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 8, padding: 2 }}>
                {tabBtn("day", "Theo ngày", "bi-calendar3")}
                {tabBtn("vehicle", "Theo xe", "bi-truck")}
              </div>
              {vehicleId && (
                <span style={{ display: "inline-flex", alignItems: "center", gap: 6, fontSize: 12.5, fontWeight: 600, padding: "4px 6px 4px 10px", borderRadius: 999, background: "#f0fdfa", color: "#0f766e", border: "1px solid #99f6e4" }}>
                  Xe {vById[vehicleId]?.plate}
                  <button type="button" onClick={() => { setVehicleId(""); setPage(1); }} title="Bỏ lọc xe" style={{ ...iconBtn, padding: "0 4px", color: "#0f766e" }}>✕</button>
                </span>
              )}
              <span style={{ flex: 1 }} />
              {loading && <span style={{ fontSize: 12, color: "var(--ink-4)" }}><i className="bi bi-arrow-repeat" style={{ animation: "trk-spin .7s linear infinite", display: "inline-block" }} /> Đang tải…</span>}
            </div>

            {view === "vehicle" ? (
              <>
                {(data.vehicles || []).length === 0 ? (
                  <div style={{ padding: 40, textAlign: "center", color: "var(--ink-4)", fontSize: 13.5 }}>{loading ? "" : "Chưa có lần đổ dầu nào trong kỳ đã chọn."}</div>
                ) : (
                  <div style={{ overflowX: "auto" }}>
                    <table style={{ width: "100%", borderCollapse: "collapse" }}>
                      <thead><tr>
                        <th style={{ ...th, width: 40, textAlign: "center" }}>#</th>
                        <th style={th}>Biển số</th>
                        <th style={{ ...th, textAlign: "right" }}>Tổng lít</th>
                        <th style={{ ...th, minWidth: 140 }}></th>
                        <th style={{ ...th, textAlign: "center" }}>Lần đổ</th>
                        <th style={{ ...th, textAlign: "right" }}>TB/lần</th>
                        <th style={{ ...th, textAlign: "right" }}>Thành tiền</th>
                        <th style={th}>Lần cuối</th>
                      </tr></thead>
                      <tbody>
                        {data.vehicles.map((v, i) => {
                          const ago = daysAgo(v.lastDate);
                          return (
                            <tr key={v.vehicleId} onClick={() => focusVehicle(v.vehicleId)} style={{ cursor: "pointer" }} title="Bấm để xem từng lần đổ của xe">
                              <td style={{ ...td, textAlign: "center", color: "var(--ink-4)" }} className="tnum">{i + 1}</td>
                              <td style={td}>
                                <span className="tnum" style={{ fontWeight: 700 }}>{v.plate}</span>
                                {vById[v.vehicleId]?.driver && <div style={{ fontSize: 11.5, color: "var(--ink-4)" }}>{vById[v.vehicleId].driver}</div>}
                              </td>
                              <td style={{ ...td, textAlign: "right", fontWeight: 800, color: "#0f766e", fontSize: 14 }} className="tnum">{fmtL(v.liters)}</td>
                              <td style={td}>
                                <div style={{ height: 8, background: "#f1f5f9", borderRadius: 999, overflow: "hidden" }}>
                                  <div style={{ width: `${(v.liters / maxVehLiters) * 100}%`, height: "100%", background: "#14b8a6", borderRadius: 999 }} />
                                </div>
                              </td>
                              <td style={{ ...td, textAlign: "center" }} className="tnum">{v.count}</td>
                              <td style={{ ...td, textAlign: "right", color: "var(--ink-2)" }} className="tnum">{fmtL(v.liters / Math.max(1, v.count))}</td>
                              <td style={{ ...td, textAlign: "right" }} className="tnum">{v.cost ? fmtVND(v.cost) : "—"}</td>
                              <td style={{ ...td, whiteSpace: "nowrap" }} className="tnum">
                                {fmtDate(v.lastDate)} <span style={{ fontSize: 11.5, color: ago > 7 ? "var(--warn, #b45309)" : "var(--ink-4)" }}>({ago === 0 ? "hôm nay" : ago + " ngày trước"})</span>
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                )}
                {notRefilled.length > 0 && (from || to) && (
                  <div style={{ padding: "10px 14px", borderTop: "1px solid var(--line-2)", background: "#fffbeb", fontSize: 12.5, color: "#92400e" }}>
                    <b>{notRefilled.length} xe chưa ghi đổ dầu trong kỳ:</b>{" "}
                    {notRefilled.map((v) => <span key={v.id} className="tnum" style={{ display: "inline-block", margin: "2px 4px", padding: "1px 7px", background: "#fff", border: "1px solid #fde68a", borderRadius: 6, fontWeight: 600 }}>{v.plate}</span>)}
                  </div>
                )}
              </>
            ) : groups.length === 0 ? (
              <div style={{ padding: 40, textAlign: "center", color: "var(--ink-4)", fontSize: 13.5 }}>{loading ? "" : "Chưa có lần đổ dầu nào trong kỳ đã chọn."}</div>
            ) : isMobile ? (
              <div>
                {groups.map((g) => (
                  <div key={g.date}>
                    <div style={{ display: "flex", justifyContent: "space-between", padding: "8px 12px", background: "#f8fafc", borderBottom: "1px solid var(--line-2)", fontSize: 12.5, fontWeight: 700 }}>
                      <span>{weekday(g.date)}, {fmtDate(g.date)}</span>
                      <span className="tnum" style={{ color: "#0f766e" }}>{fmtL(g.liters)} lít · {g.rows.length} xe</span>
                    </div>
                    {g.rows.map((r) => (
                      <div key={r.id} style={{ display: "flex", alignItems: "center", gap: 10, padding: "10px 12px", borderBottom: "1px solid var(--line-2)" }}>
                        <div style={{ flex: 1, minWidth: 0 }}>
                          {plateTag(r)}
                          {(r.station || r.note) && <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 3 }}>{[r.station, r.note].filter(Boolean).join(" · ")}</div>}
                        </div>
                        <div style={{ textAlign: "right" }}>
                          <div className="tnum" style={{ fontWeight: 800, fontSize: 15 }}>{fmtL(r.liters)} <span style={{ fontSize: 11, color: "var(--ink-4)", fontWeight: 600 }}>lít</span></div>
                          {r.totalCost ? <div className="tnum" style={{ fontSize: 11.5, color: "var(--ink-3)" }}>{fmtVND(r.totalCost)}</div> : null}
                        </div>
                        {canEdit && <button type="button" onClick={() => startEdit(r)} style={iconBtn} title="Sửa"><I.edit /></button>}
                      </div>
                    ))}
                  </div>
                ))}
              </div>
            ) : (
              <div style={{ overflowX: "auto" }}>
                <table style={{ width: "100%", borderCollapse: "collapse" }}>
                  <thead><tr>
                    <th style={th}>Biển số</th>
                    <th style={{ ...th, textAlign: "right" }}>Số lít</th>
                    <th style={{ ...th, textAlign: "right" }}>Đơn giá</th>
                    <th style={{ ...th, textAlign: "right" }}>Thành tiền</th>
                    <th style={{ ...th, textAlign: "right" }}>Km đồng hồ</th>
                    <th style={th}>Trạm đổ</th>
                    <th style={th}>Ghi chú</th>
                    {canEdit && <th style={{ ...th, width: 70 }}></th>}
                  </tr></thead>
                  <tbody>
                    {groups.map((g) => (
                      <React.Fragment key={g.date}>
                        <tr>
                          <td colSpan={canEdit ? 8 : 7} style={{ padding: "7px 12px", background: "#f8fafc", borderBottom: "1px solid var(--line-2)", fontSize: 12.5 }}>
                            <span style={{ fontWeight: 700 }}>{weekday(g.date)}, {fmtDate(g.date)}</span>
                            <span className="tnum" style={{ marginLeft: 12, color: "#0f766e", fontWeight: 700 }}>{fmtL(g.liters)} lít</span>
                            <span style={{ marginLeft: 8, color: "var(--ink-4)" }}>· {g.rows.length} lần đổ{g.cost ? " · " + fmtVND(g.cost) : ""}</span>
                          </td>
                        </tr>
                        {g.rows.map((r) => (
                          <tr key={r.id}>
                            <td style={td}>{plateTag(r)}</td>
                            <td style={{ ...td, textAlign: "right", fontWeight: 800, fontSize: 14 }} className="tnum">{fmtL(r.liters)}</td>
                            <td style={{ ...td, textAlign: "right", color: "var(--ink-3)" }} className="tnum">{r.unitPrice ? fmtNum(r.unitPrice) : "—"}</td>
                            <td style={{ ...td, textAlign: "right" }} className="tnum">{r.totalCost ? fmtVND(r.totalCost) : "—"}</td>
                            <td style={{ ...td, textAlign: "right", color: "var(--ink-3)" }} className="tnum">{r.odometerKm ? fmtNum(r.odometerKm) : "—"}</td>
                            <td style={{ ...td, color: "var(--ink-2)" }}>{r.station || <span style={{ color: "var(--ink-4)" }}>—</span>}</td>
                            <td style={{ ...td, color: "var(--ink-3)", maxWidth: 260 }}>{r.note || ""}</td>
                            {canEdit && (
                              <td style={{ ...td, textAlign: "right", whiteSpace: "nowrap" }}>
                                <button type="button" onClick={() => startEdit(r)} style={iconBtn} title="Sửa"><I.edit /></button>
                                <button type="button" onClick={() => remove(r)} style={{ ...iconBtn, color: "var(--danger)" }} title="Xóa"><I.trash /></button>
                              </td>
                            )}
                          </tr>
                        ))}
                      </React.Fragment>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>

          {view === "day" && (data.lastPage || 1) > 1 && (
            <div style={{ display: "flex", alignItems: "center", justifyContent: "center", gap: 6, flexWrap: "wrap" }}>
              <button type="button" disabled={data.page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))}
                style={{ padding: "7px 12px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, background: "#fff", cursor: data.page <= 1 ? "default" : "pointer", opacity: data.page <= 1 ? 0.5 : 1 }}>‹ Trước</button>
              {pageList().map((n, i) => n === "…"
                ? <span key={"e" + i} style={{ padding: "0 4px", color: "var(--ink-4)" }}>…</span>
                : <button key={n} type="button" onClick={() => setPage(n)}
                    style={{ minWidth: 34, padding: "7px 10px", fontSize: 13, fontWeight: n === data.page ? 700 : 500, border: `1px solid ${n === data.page ? "var(--accent)" : "var(--line)"}`, borderRadius: 8, background: n === data.page ? "var(--accent-weak)" : "#fff", color: n === data.page ? "var(--accent)" : "var(--ink-2)", cursor: "pointer" }}>{n}</button>)}
              <button type="button" disabled={data.page >= data.lastPage} onClick={() => setPage((p) => Math.min(data.lastPage, p + 1))}
                style={{ padding: "7px 12px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, background: "#fff", cursor: data.page >= data.lastPage ? "default" : "pointer", opacity: data.page >= data.lastPage ? 0.5 : 1 }}>Sau ›</button>
            </div>
          )}
        </div>
      </div>

      {editing && (
        <Modal title="Sửa phiếu đổ dầu" subtitle={`${vById[editing.vehicleId]?.plate || ""} · ${fmtDate(editing.date)}`} onClose={() => setEditing(null)} width={560}
          footer={<div style={{ display: "flex", alignItems: "center", gap: 8 }}>
            {isMobile && <Btn onClick={() => { const r = data.rows.find((x) => x.id === editing.id); setEditing(null); if (r) remove(r); }}>Xóa</Btn>}
            <div style={{ flex: 1 }} />
            <Btn onClick={() => setEditing(null)}>Hủy</Btn>
            <Btn variant="primary" onClick={saveEdit}>Lưu</Btn>
          </div>}>
          <div style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr" : "1fr 1fr", gap: 12 }}>
            {editFields(editing, setEditing, false)}
            <div>
              <div style={lbl}>Km đồng hồ</div>
              <input inputMode="numeric" value={editing.odometerKm} onChange={(e) => setEditing({ ...editing, odometerKm: litersInput(e.target.value) })} placeholder="Tùy chọn" className="tnum" style={{ ...inp, textAlign: "right" }} />
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<FuelLogPage />);
