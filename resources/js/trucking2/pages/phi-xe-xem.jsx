import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState, useMemo, useEffect } = React;
import { I, Btn, Txt, Combo, DateField, fmtVND, fmtNum, fmtDate, toNum } from "@trk/lib.jsx";
import { PayrollDetail, ExtraPayEditor, PaymentsEditor, payrollSumsFromDetail, rowStats, groupMatches, detailRows } from "@trk/components/payroll-detail.jsx";

const STATUS_OPTS = [["all", "Tất cả"], ["due", "Còn phải trả"], ["paid", "Đã trả đủ"], ["warn", "Có chuyến cần kiểm tra"]];
const selStyle = { height: 34, padding: "0 10px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-1)", outline: "none" };
const Kpi = ({ label, value, color, sub }) => (
  <div style={{ flex: "1 1 130px", minWidth: 120, background: "#fff", border: "1px solid var(--line)", borderRadius: 10, padding: "9px 12px" }}>
    <div style={{ fontSize: 11, color: "var(--ink-4)", fontWeight: 600 }}>{label}</div>
    <div className="tnum" style={{ fontSize: 15.5, fontWeight: 700, color: color || "var(--ink-1)", marginTop: 2 }}>{value}</div>
    {sub && <div style={{ fontSize: 10.5, color: "var(--ink-4)", marginTop: 1 }}>{sub}</div>}
  </div>
);

const lbl = (t) => <div style={{ fontSize: 11, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>{t}</div>;
const TH = ({ children, right }) => <th style={{ textAlign: right ? "right" : "left", padding: "8px 10px", fontSize: 11, fontWeight: 700, color: "var(--ink-4)", textTransform: "uppercase", letterSpacing: ".03em", borderBottom: "1px solid var(--line)", whiteSpace: "nowrap" }}>{children}</th>;
const TD = ({ children, right }) => <td style={{ padding: "8px 10px", fontSize: 13, borderBottom: "1px solid var(--line-2)", textAlign: right ? "right" : "left", verticalAlign: "middle" }}>{children}</td>;

function ViewPayrollApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const back = () => { window.location.href = ROUTES.list; };
  const api = (method, url, body) => window.trkApi(method, url, body);
  const batch = B.batch || {};
  const drivers = B.drivers || [];
  const idUrl = batch.hashid || batch.id;

  const [no, setNo] = useState(batch.no || "");
  const [name, setName] = useState(batch.name || "");
  const [from, setFrom] = useState(batch.from || "");
  const [to, setTo] = useState(batch.to || "");
  const [locked, setLocked] = useState(!!batch.locked);
  // Chuẩn hóa LÚC NẠP: loại dầu (chi phí công ty) khỏi payroll/paidDaily — suy từ detail; snapshot cũ chưa "Tính lại" cũng đúng,
  // và khi bấm Lưu sẽ ghi đè số đã trừ dầu vào DB (sửa luôn trang danh sách).
  const [rows, setRows] = useState((batch.rows || []).map((x) => {
    const d = payrollSumsFromDetail(x);
    return {
      ...x,
      payroll: d ? d.payroll : (x.payroll != null ? x.payroll : (x.total || 0)),
      paidDaily: d ? d.daily : (x.paidDaily || 0),
      extraPay: Array.isArray(x.extraPay) ? x.extraPay : [],
      payments: Array.isArray(x.payments) ? x.payments : [],
    };
  }));
  const [dirty, setDirty] = useState(false);
  const [busy, setBusy] = useState(false);
  const [open, setOpen] = useState({});
  const toggle = (bks) => setOpen((o) => ({ ...o, [bks]: !o[bks] }));

  const set = (i, np) => { setRows((rs) => rs.map((r, j) => (j === i ? { ...r, ...np } : r))); setDirty(true); };
  const sumExtra = (r) => (r.extraPay || []).reduce((s, e) => s + toNum(e.amount), 0);
  const effPay = (r) => (r.payroll || 0) + sumExtra(r);   // lương phải trả = gốc (đã trừ dầu) + phát sinh
  const paidOf = (r) => (r.payments || []).reduce((s, p) => s + toNum(p.amount), 0);
  const grandPayroll = rows.reduce((a, x) => a + effPay(x), 0);

  // ---- Bộ lọc + gom nhóm (chỉ ảnh hưởng hiển thị, không đổi dữ liệu kỳ) ----
  const [q, setQ] = useState("");
  const [fDriver, setFDriver] = useState("");
  const [fStatus, setFStatus] = useState("all");
  const [fDay, setFDay] = useState("");
  const [groupBy, setGroupBy] = useState("bks");   // bks = từng xe · driver = gom theo lái xe
  const stats = useMemo(() => rows.map(rowStats), [rows]);
  const driverOpts = useMemo(() => {
    const set = new Set();
    rows.forEach((r) => { String(r.driver || "").split(",").forEach((d) => d.trim() && set.add(d.trim())); (r.detail || r.lines || []).forEach((ln) => ln.driver && set.add(ln.driver)); });
    return [...set].sort((a, b) => a.localeCompare(b, "vi"));
  }, [rows]);
  const dayOpts = useMemo(() => {
    const set = new Set(); rows.forEach((r) => (r.detail || r.lines || []).forEach((ln) => ln.date && set.add(ln.date)));
    return [...set].sort();
  }, [rows]);
  const qq = q.trim().toLowerCase();
  // Mỗi dòng: idx gốc (để set()), có khớp theo bks/lái (thì không lọc chuyến trong chi tiết) hay chỉ khớp theo chuyến.
  const view = rows.map((r, i) => ({ r, i, st: stats[i] })).filter(({ r, st }) => {
    const lines = r.detail || r.lines || [];
    if (fDriver && !String(r.driver || "").split(",").map((d) => d.trim()).includes(fDriver) && !lines.some((ln) => ln.driver === fDriver)) return false;
    if (fDay && !lines.some((ln) => ln.date === fDay)) return false;
    const remain = effPay(r) - paidOf(r);
    if (fStatus === "due" && remain <= 0) return false;
    if (fStatus === "paid" && remain > 0) return false;
    if (fStatus === "warn" && !st.warn) return false;
    if (!qq) return true;
    if (String(r.bks).toLowerCase().includes(qq) || String(r.driver || "").toLowerCase().includes(qq)) return true;
    return lines.some((ln) => (ln.groups || []).some((g) => groupMatches(g, qq)));
  }).map((x) => ({ ...x, tripQ: qq && !(String(x.r.bks).toLowerCase().includes(qq) || String(x.r.driver || "").toLowerCase().includes(qq)) ? qq : "" }));
  const filtering = !!(qq || fDriver || fDay || fStatus !== "all");
  const clearFilters = () => { setQ(""); setFDriver(""); setFDay(""); setFStatus("all"); };

  const totOf = (list) => list.reduce((a, { r, st }) => {
    a.daily += r.paidDaily || 0; a.choho += st.choho; a.khac += st.khac; a.payroll += effPay(r); a.paid += paidOf(r);
    a.days += r.days || 0; a.trips += r.trips || 0; a.warn += st.warn; a.liters += st.liters; return a;
  }, { daily: 0, choho: 0, khac: 0, payroll: 0, paid: 0, days: 0, trips: 0, warn: 0, liters: 0 });
  const tot = totOf(view);
  const showChoho = stats.some((s) => s.choho > 0);
  const showKhac = stats.some((s) => s.khac > 0);
  const nCols = 8 + (showChoho ? 1 : 0) + (showKhac ? 1 : 0);

  // Gom nhóm theo lái xe: nhóm theo tên lái nhận lương của xe (nhiều lái → giữ chuỗi "A, B").
  const sections = groupBy === "driver"
    ? Object.values(view.reduce((m, x) => { const k = x.r.driver || "Chưa gán lái"; (m[k] = m[k] || { key: k, items: [] }).items.push(x); return m; }, {}))
        .sort((a, b) => a.key.localeCompare(b.key, "vi"))
    : [{ key: "", items: view }];

  const allOpen = view.length > 0 && view.every(({ r }) => open[r.bks]);
  const toggleAll = () => setOpen(allOpen ? {} : Object.fromEntries(view.map(({ r }) => [r.bks, true])));

  // Xuất Excel 2 sheet: Tổng hợp theo xe + Chi tiết từng chuyến/khoản (theo bộ lọc đang áp).
  const exportXlsx = () => {
    if (typeof XLSX === "undefined") { window.trkToast && window.trkToast("Thư viện Excel chưa tải xong, thử lại sau giây lát.", "error"); return; }
    const sum = view.map(({ r, st }) => ({
      "Biển số": r.bks, "Lái xe nhận lương": r.driver || "", "Số ngày": r.days || 0, "Số chuyến": r.trips || 0,
      "Đã chi theo ngày (tính lương)": r.paidDaily || 0, "Chi hộ": st.choho, "Chi phí khác": st.khac,
      "Lương gốc": r.payroll || 0, "Lương phát sinh": effPay(r) - (r.payroll || 0), "Lương phải trả": effPay(r),
      "Đã trả": paidOf(r), "Còn lại": effPay(r) - paidOf(r), "Dầu (lít, công ty)": Math.round(st.liters), "Chuyến cần kiểm tra": st.warn,
    }));
    const det = [];
    view.forEach(({ r }) => detailRows(r).forEach((d) => {
      if (fDay && d.date !== fDay) return;
      det.push({ "Ngày": fmtDate(d.date), "Biển số": r.bks, "Lái nhận": d.driver, "Tuyến": d.route, "Cont": d.cont, "Kiểu chuyến": d.mode,
        "Nhóm": d.bucket, "Khoản": d.item, "Số tiền": d.amount, "Dầu (lít)": d.liters, "Cảnh báo": d.note });
    }));
    const wb = XLSX.utils.book_new();
    const ws1 = XLSX.utils.json_to_sheet(sum); ws1["!cols"] = Object.keys(sum[0] || { a: 1 }).map((k) => ({ wch: Math.max(12, k.length + 2) }));
    const ws2 = XLSX.utils.json_to_sheet(det); ws2["!cols"] = [10, 12, 18, 40, 14, 16, 26, 26, 12, 10, 40].map((wch) => ({ wch }));
    XLSX.utils.book_append_sheet(wb, ws1, "Tổng hợp theo xe");
    XLSX.utils.book_append_sheet(wb, ws2, "Chi tiết chuyến");
    XLSX.writeFile(wb, `ky-luong-${(no || "LG").replace(/[^\w-]+/g, "_")}${fDay ? "-" + fDay : ""}.xlsx`);
  };

  const save = async (lk = locked) => {
    if (busy) return; setBusy(true);
    const res = await api("PUT", ROUTES.update + idUrl, { batch: { no, name, from, to, locked: lk, rows } });
    setBusy(false);
    if (res && res.ok) { setDirty(false); window.trkToast && window.trkToast("Đã lưu kỳ lương"); return true; }
    window.trkToast && window.trkToast("Lưu thất bại", "error"); return false;
  };

  // CHỐT / BỎ CHỐT — phải xác nhận trước khi thao tác.
  const doLock = async (lk) => {
    const ok = await window.confirmAction({
      title: lk ? "Chốt (đóng băng) kỳ lương?" : "Bỏ chốt kỳ lương?",
      text: lk
        ? "Kỳ lương sẽ <b>đóng băng</b>: khóa số tiền + không cho Tính lại/sửa lương. Vẫn ghi nhận được các đợt thanh toán."
        : "Mở khóa kỳ lương để sửa/Tính lại.",
      confirmText: lk ? '<i class="bi bi-lock me-1"></i> Chốt lương' : '<i class="bi bi-unlock me-1"></i> Bỏ chốt',
    });
    if (!ok) return;
    setLocked(lk); await save(lk);
  };

  // ĐỐI SOÁT VỚI LỘ TRÌNH: tải ngầm số liệu Lộ trình HIỆN TẠI của khoảng ngày kỳ (chỉ đọc) để so với snapshot kỳ —
  // phát hiện chi khác / chi hộ / chuyến nhập thêm sau khi lập kỳ mà kỳ chưa có.
  const [live, setLive] = useState(null);   // null = đang tải · {rows} · {error:true}
  const fetchLive = async () => {
    try {
      const r = await api("GET", ROUTES.recompute + idUrl + "/recompute");
      if (r && r.ok) { setLive(r); return r; }
    } catch (e) {}
    setLive({ error: true }); return null;
  };
  useEffect(() => { fetchLive(); }, []);
  const BUCKET_LABEL = { trips: "chuyến", daily: "chi ngày", choho: "chi hộ", khac: "chi khác", payroll: "lương kỳ" };
  const drift = useMemo(() => {
    if (!live || !live.rows) return null;
    const liveBy = {}; live.rows.forEach((r) => { liveBy[r.bks] = rowStats({ detail: r.lines || r.detail || [] }); });
    const snapBy = {}; rows.forEach((r) => { snapBy[r.bks] = rowStats(r); });
    const out = [];
    new Set([...Object.keys(snapBy), ...Object.keys(liveBy)]).forEach((bks) => {
      const a = snapBy[bks], b = liveBy[bks];
      if (!a) { out.push({ bks, kind: "new", d: { trips: b.trips, daily: b.daily, choho: b.choho, khac: b.khac, payroll: b.payroll } }); return; }
      if (!b) { out.push({ bks, kind: "gone", d: {} }); return; }
      const d = {};
      ["trips", "daily", "choho", "khac", "payroll"].forEach((k) => { if (a[k] !== b[k]) d[k] = b[k] - a[k]; });
      if (Object.keys(d).length) out.push({ bks, kind: "diff", d });
    });
    return out.sort((x, y) => x.bks.localeCompare(y.bks));
  }, [live, rows]);
  const driftBy = useMemo(() => Object.fromEntries((drift || []).map((x) => [x.bks, x])), [drift]);

  // Gộp số Lộ trình hiện tại vào kỳ: GIỮ lái/lương phát sinh/đợt trả; xe biến mất khỏi Lộ trình nhưng đã có
  // lương phát sinh hoặc đợt trả thì GIỮ lại (lương gốc = 0) để không mất số đã nhập.
  const mergeLive = (r) => {
    const byBks = {}; rows.forEach((x) => { byBks[x.bks] = x; });
    const merged = (r.rows || []).map((nr) => {
      const old = byBks[nr.bks] || {};
      return { ...nr, detail: nr.lines || nr.detail || [], driver: old.driver || nr.driver || "", extraPay: old.extraPay || [], payments: old.payments || [] };
    });
    const liveSet = new Set(merged.map((x) => x.bks));
    rows.forEach((x) => {
      if (liveSet.has(x.bks) || !((x.extraPay || []).length || (x.payments || []).length)) return;
      merged.push({ ...x, days: 0, trips: 0, paidDaily: 0, payroll: 0, detail: [] });
    });
    return merged;
  };

  // TÍNH LẠI — phải xác nhận; giữ lái/lương phát sinh/đợt trả, cập nhật lương gốc + chi tiết theo cấu hình + Lộ trình hiện tại.
  const recompute = async () => {
    if (locked) { window.trkToast && window.trkToast("Kỳ đã chốt — bỏ chốt trước khi tính lại", "error"); return; }
    const n = (drift || []).length;
    const ok = await window.confirmAction({
      title: "Tính lại kỳ lương theo Lộ trình hiện tại?",
      text: (n ? `Có <b>${n}</b> xe lệch so với Lộ trình (chi khác / chi hộ / chuyến mới…). ` : "")
        + "Sẽ đọc lại quy chế / giá dầu / Lộ trình <b>hiện tại</b> và cập nhật lương gốc + chi tiết. <b>Giữ nguyên</b> lái xe, lương phát sinh, các đợt thanh toán đã nhập. Nhớ bấm <b>Lưu</b> sau khi tính lại.",
      confirmText: '<i class="bi bi-arrow-repeat me-1"></i> Tính lại',
    });
    if (!ok) return;
    setBusy(true);
    const r = await fetchLive();
    if (!r) { window.trkToast && window.trkToast("Tính lại thất bại", "error"); setBusy(false); return; }
    const merged = mergeLive(r);
    setRows(merged); setDirty(true);
    window.trkToast && window.trkToast(`Đã cập nhật ${merged.length} xe theo Lộ trình hiện tại. Nhớ bấm Lưu.`);
    setBusy(false);
  };

  const del = async () => {
    const ok = await window.confirmAction({
      title: "Xóa kỳ lương?", text: `Kỳ <b>${no}</b> · <b>${rows.length}</b> xe sẽ bị xóa vĩnh viễn.`,
      confirmText: '<i class="bi bi-trash me-1"></i> Xóa kỳ', danger: true,
    });
    if (!ok) return;
    const res = await api("DELETE", ROUTES.destroy + idUrl);
    if (res && res.ok) { window.location.href = ROUTES.list; }
    else window.trkToast && window.trkToast("Xóa thất bại", "error");
  };

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <header style={{ background: "#fff", borderBottom: "1px solid var(--line)", padding: "0 22px", flexShrink: 0 }}>
        <div className="trk-head" style={{ display: "flex", alignItems: "center", gap: 12, height: 58 }}>
          <div className="trk-head-lead" style={{ display: "flex", alignItems: "center", gap: 12, flex: 1, minWidth: 0 }}>
            <button type="button" onClick={back} title="Quay lại danh sách"
              style={{ width: 34, height: 34, flexShrink: 0, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 9, background: "#fff", color: "var(--ink-2)", cursor: "pointer" }}>
              <span style={{ transform: "rotate(180deg)", display: "grid" }}><I.arrow /></span>
            </button>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontSize: 15.5, fontWeight: 700, lineHeight: 1.1, display: "flex", alignItems: "center", gap: 8 }}>
                <span className="tnum">Kỳ lương {no || ""}</span>
                {locked && <span style={{ fontSize: 10.5, fontWeight: 700, color: "#2563eb", background: "#e7efff", padding: "2px 8px", borderRadius: 999 }}><i className="bi bi-lock-fill" /> Đã chốt</span>}
              </div>
              <div style={{ fontSize: 12.5, color: "var(--ink-3)" }} className="tnum">{fmtDate(from) || "?"} → {fmtDate(to) || "?"} · {rows.length} xe</div>
            </div>
          </div>
          <div style={{ textAlign: "right", marginRight: 8 }}>
            <div style={{ fontSize: 11.5, color: "var(--ink-4)" }}>Lương phải trả</div>
            <div className="tnum" style={{ fontSize: 17, fontWeight: 700, color: "var(--accent)" }}>{fmtVND(grandPayroll)}</div>
          </div>
          {T.canEdit && !locked && <Btn onClick={recompute} disabled={busy}><I.fx /> Tính lại</Btn>}
          {T.canEdit && <Btn onClick={() => doLock(!locked)} disabled={busy}>{locked ? <><i className="bi bi-unlock" /> Bỏ chốt</> : <><i className="bi bi-lock-fill" /> Chốt lương</>}</Btn>}
          {T.canEdit && <Btn variant="primary" onClick={() => save()} disabled={!dirty || busy}>Lưu</Btn>}
          {T.canDelete && <Btn onClick={del}><I.trash /></Btn>}
        </div>
      </header>

      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: "18px 22px 40px" }}>
        <div style={{ maxWidth: 1240, margin: "0 auto", display: "flex", flexDirection: "column", gap: 14 }}>
          <div style={{ display: "flex", gap: 10, alignItems: "flex-start", background: "#eff5ff", border: "1px solid #cfe0fb", borderRadius: 12, padding: "12px 14px", fontSize: 13, color: "#1f4f9e", lineHeight: 1.6 }}>
            <i className="bi bi-info-circle-fill" style={{ fontSize: 16, marginTop: 1, flexShrink: 0 }} />
            <div>Bấm vào dòng xe để mở <b>sổ chi tiết theo ngày</b> (mỗi ngày bung ra từng chuyến, khớp thẻ xe ở <b>Lộ trình</b> — bấm “Lộ trình ↗” để mở đúng ngày/xe đối soát), thêm <b>lương phát sinh</b> và ghi <b>các đợt thanh toán</b>. Dùng bộ lọc để tìm theo biển số / lái / cont / tuyến, lọc 1 ngày, hoặc gom theo lái xe.</div>
          </div>
          {live === null && <div style={{ fontSize: 12.5, color: "var(--ink-4)", padding: "0 2px" }}><i className="bi bi-arrow-repeat" /> Đang đối soát với Lộ trình hiện tại…</div>}
          {live && live.error && <div style={{ fontSize: 12.5, color: "var(--danger)", padding: "0 2px" }}><i className="bi bi-exclamation-circle" /> Không tải được số liệu Lộ trình để đối soát. <button type="button" onClick={() => { setLive(null); fetchLive(); }} style={{ border: 0, background: "none", color: "var(--accent)", fontWeight: 600, cursor: "pointer", padding: 0 }}>Thử lại</button></div>}
          {drift && drift.length === 0 && <div style={{ display: "flex", alignItems: "center", gap: 8, padding: "9px 14px", borderRadius: 12, background: "var(--good-weak)", color: "var(--good)", fontSize: 13, fontWeight: 600 }}>
            <i className="bi bi-check-circle-fill" /> Khớp Lộ trình hiện tại — mọi chuyến, chi theo ngày, chi hộ, chi khác và lương kỳ đều đã có trong kỳ.
          </div>}
          {drift && drift.length > 0 && <div style={{ background: "#fff", border: "1px solid #f1d59a", borderRadius: 12, overflow: "hidden" }}>
            <div style={{ display: "flex", alignItems: "center", gap: 10, flexWrap: "wrap", padding: "10px 14px", background: "#fff7e9", borderBottom: "1px solid #f6e3b8", fontSize: 13, color: "#92400e" }}>
              <div style={{ flex: 1, minWidth: 220 }}>
                <b><i className="bi bi-exclamation-triangle-fill" /> {drift.length} xe lệch so với Lộ trình hiện tại</b>
                <div style={{ fontSize: 12, marginTop: 2 }}>Có chi khác / chi hộ / chuyến được nhập hoặc sửa ở Lộ trình sau khi lập kỳ. {locked ? "Kỳ đã chốt — bỏ chốt để cập nhật." : "Bấm “Cập nhật từ Lộ trình” để đưa hết vào kỳ (giữ lái, lương phát sinh, đợt trả), rồi Lưu."}</div>
              </div>
              {T.canEdit && !locked && <Btn variant="primary" onClick={recompute} disabled={busy}><i className="bi bi-arrow-repeat" /> Cập nhật từ Lộ trình</Btn>}
            </div>
            <div style={{ padding: "8px 14px", display: "flex", flexDirection: "column", gap: 4, maxHeight: 220, overflowY: "auto" }}>
              {drift.map((x) => (
                <div key={x.bks} style={{ display: "flex", gap: 10, alignItems: "baseline", flexWrap: "wrap", fontSize: 12.5 }}>
                  <button type="button" onClick={() => { setQ(x.bks); setOpen((o) => ({ ...o, [x.bks]: true })); }} title="Lọc xe này" style={{ border: 0, background: "none", padding: 0, cursor: "pointer", fontWeight: 700, color: "var(--accent)" }} className="tnum">{x.bks}</button>
                  {x.kind === "new" && <span style={{ color: "#b45309" }}>xe mới có trong Lộ trình, chưa có trong kỳ</span>}
                  {x.kind === "gone" && <span style={{ color: "var(--ink-3)" }}>không còn chuyến trong Lộ trình kỳ này</span>}
                  {Object.entries(x.d).filter(([, v]) => v).map(([k, v]) => (
                    <span key={k} className="tnum" style={{ color: v > 0 ? "#b45309" : "var(--ink-3)" }}>{BUCKET_LABEL[k]} {v > 0 ? "+" : "−"}{k === "trips" ? Math.abs(v) : fmtVND(Math.abs(v))}</span>
                  ))}
                </div>
              ))}
            </div>
          </div>}
          <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "14px 16px", display: "flex", gap: 12, flexWrap: "wrap", alignItems: "flex-end" }}>
            <div style={{ width: 150 }}>{lbl("Số kỳ")}<Txt value={no} onChange={(v) => { setNo(v); setDirty(true); }} /></div>
            <div style={{ flex: 1, minWidth: 180 }}>{lbl("Tên / ghi chú kỳ")}<Txt value={name} onChange={(v) => { setName(v); setDirty(true); }} /></div>
            <div>{lbl("Từ ngày")}<DateField value={from} onChange={(v) => { setFrom(v); setDirty(true); }} /></div>
            <div>{lbl("Đến ngày")}<DateField value={to} onChange={(v) => { setTo(v); setDirty(true); }} /></div>
          </div>

          <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
            <Kpi label={filtering ? "Xe (đang lọc)" : "Xe"} value={`${view.length}/${rows.length}`} sub={`${fmtNum(tot.days)} ngày-xe · ${fmtNum(tot.trips)} chuyến`} />
            <Kpi label="Đã chi theo ngày" value={fmtVND(tot.daily)} color="var(--ink-2)" sub="tính lương" />
            {(showChoho || showKhac) && <Kpi label="Chi hộ / chi khác" value={fmtVND(tot.choho + tot.khac)} color="#9a6700" sub="không tính lương" />}
            <Kpi label="Lương phải trả" value={fmtVND(tot.payroll)} color="var(--accent)" />
            <Kpi label="Đã trả" value={fmtVND(tot.paid)} color="var(--good)" />
            <Kpi label="Còn lại" value={fmtVND(tot.payroll - tot.paid)} color={(tot.payroll - tot.paid) > 0 ? "var(--danger)" : "var(--good)"} />
            <Kpi label="Dầu (công ty)" value={fmtNum(Math.round(tot.liters)) + " L"} color="#2563eb" sub={tot.warn ? `${tot.warn} chuyến cần kiểm tra` : "đủ dữ liệu"} />
          </div>

          <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "10px 12px", display: "flex", gap: 8, flexWrap: "wrap", alignItems: "center" }}>
            <div style={{ position: "relative", flex: "1 1 220px", minWidth: 180 }}>
              <i className="bi bi-search" style={{ position: "absolute", left: 10, top: 9, fontSize: 13, color: "var(--ink-4)" }} />
              <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Tìm biển số, lái, số cont, tuyến, khoản…" style={{ ...selStyle, width: "100%", paddingLeft: 30, boxSizing: "border-box" }} />
            </div>
            <div style={{ width: 190 }}><Combo value={fDriver} onChange={(v) => setFDriver(v || "")} options={driverOpts} placeholder="Lái xe: tất cả" small clearable /></div>
            <select value={fDay} onChange={(e) => setFDay(e.target.value)} style={selStyle} title="Chỉ xem 1 ngày — đối soát với Lộ trình ngày đó">
              <option value="">Ngày: cả kỳ</option>
              {dayOpts.map((d) => <option key={d} value={d}>{fmtDate(d)}</option>)}
            </select>
            <select value={fStatus} onChange={(e) => setFStatus(e.target.value)} style={selStyle}>
              {STATUS_OPTS.map(([v, t]) => <option key={v} value={v}>{t}</option>)}
            </select>
            <div style={{ display: "inline-flex", border: "1px solid var(--line)", borderRadius: 8, overflow: "hidden" }}>
              {[["bks", "Theo xe"], ["driver", "Gom theo lái"]].map(([v, t]) => (
                <button key={v} type="button" onClick={() => setGroupBy(v)} style={{ height: 32, padding: "0 11px", fontSize: 12.5, fontWeight: 600, border: 0, cursor: "pointer", background: groupBy === v ? "var(--accent)" : "#fff", color: groupBy === v ? "#fff" : "var(--ink-2)" }}>{t}</button>
              ))}
            </div>
            {filtering && <button type="button" onClick={clearFilters} style={{ height: 34, padding: "0 10px", fontSize: 12.5, border: "1px dashed var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-3)", cursor: "pointer" }}><I.x /> Bỏ lọc</button>}
            <div style={{ flex: 1 }} />
            <Btn onClick={toggleAll} disabled={!view.length}>{allOpen ? "Thu gọn tất cả" : "Mở tất cả"}</Btn>
            <Btn onClick={exportXlsx} disabled={!view.length}><i className="bi bi-file-earmark-excel" /> Xuất Excel</Btn>
          </div>
          {fDay && <div style={{ fontSize: 12.5, color: "#92400e", background: "#fff7e9", border: "1px solid #f6e3b8", borderRadius: 10, padding: "8px 12px" }}>
            <i className="bi bi-funnel-fill" /> Đang lọc ngày <b>{fmtDate(fDay)}</b>: chỉ hiện xe chạy ngày này, sổ chi tiết chỉ hiện ngày này. Các cột tiền ở bảng vẫn là <b>cả kỳ</b>.
          </div>}

          <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, overflow: "hidden" }}>
            <div style={{ overflowX: "auto" }}>
              <table style={{ width: "100%", borderCollapse: "collapse" }}>
                <thead><tr>
                  <TH>Biển số xe</TH><TH>Lái xe nhận lương</TH><TH right>Ngày</TH><TH right>Chuyến</TH>
                  <TH right>Đã chi theo ngày</TH>{showChoho && <TH right>Chi hộ</TH>}{showKhac && <TH right>Chi khác</TH>}
                  <TH right>Lương phải trả</TH><TH right>Đã trả</TH><TH right>Còn lại</TH>
                </tr></thead>
                <tbody>
                  {sections.map((sec) => {
                    const st = groupBy === "driver" ? totOf(sec.items) : null;
                    return (
                    <React.Fragment key={sec.key || "_all"}>
                      {st && <tr style={{ background: "#f3f6fb" }}>
                        <td colSpan={4} style={{ padding: "8px 10px", fontSize: 13, fontWeight: 700, borderBottom: "1px solid var(--line)" }}>
                          <i className="bi bi-person-fill" style={{ color: "var(--accent)" }} /> {sec.key} <span style={{ fontWeight: 500, color: "var(--ink-4)", fontSize: 12 }}>· {sec.items.length} xe · {st.trips} chuyến</span>
                        </td>
                        <td style={{ padding: "8px 10px", textAlign: "right", borderBottom: "1px solid var(--line)" }}><span className="tnum" style={{ color: "var(--ink-4)" }}>{fmtVND(st.daily)}</span></td>
                        {showChoho && <td style={{ padding: "8px 10px", textAlign: "right", borderBottom: "1px solid var(--line)" }}><span className="tnum" style={{ color: "#9a6700" }}>{fmtVND(st.choho)}</span></td>}
                        {showKhac && <td style={{ padding: "8px 10px", textAlign: "right", borderBottom: "1px solid var(--line)" }}><span className="tnum" style={{ color: "#6d28d9" }}>{fmtVND(st.khac)}</span></td>}
                        <td style={{ padding: "8px 10px", textAlign: "right", borderBottom: "1px solid var(--line)" }}><b className="tnum" style={{ color: "var(--accent)" }}>{fmtVND(st.payroll)}</b></td>
                        <td style={{ padding: "8px 10px", textAlign: "right", borderBottom: "1px solid var(--line)" }}><span className="tnum" style={{ color: "var(--good)" }}>{fmtVND(st.paid)}</span></td>
                        <td style={{ padding: "8px 10px", textAlign: "right", borderBottom: "1px solid var(--line)" }}><b className="tnum" style={{ color: (st.payroll - st.paid) > 0 ? "var(--danger)" : "var(--good)" }}>{fmtVND(st.payroll - st.paid)}</b></td>
                      </tr>}
                      {sec.items.map(({ r, i, st: rs, tripQ }) => {
                        const payroll = effPay(r); const paid = paidOf(r); const remain = payroll - paid;
                        return (
                        <React.Fragment key={r.bks + i}>
                        <tr style={{ cursor: "pointer", background: open[r.bks] ? "#f8fafd" : undefined }} onClick={() => toggle(r.bks)}>
                          <TD>
                            <i className={"bi " + (open[r.bks] ? "bi-chevron-down" : "bi-chevron-right")} style={{ fontSize: 11, color: "var(--ink-4)", marginRight: 6 }} />
                            <span className="tnum" style={{ fontWeight: 700 }}>{r.bks}</span>
                            {rs.warn > 0 && <i className="bi bi-exclamation-triangle-fill" title={rs.warn + " chuyến chưa ra tiền / cần kiểm tra"} style={{ color: "#d97706", marginLeft: 6, fontSize: 12 }} />}
                            {driftBy[r.bks] && <span title="Số kỳ khác Lộ trình hiện tại — xem khung đối soát phía trên" style={{ marginLeft: 6, fontSize: 10.5, fontWeight: 700, padding: "1px 6px", borderRadius: 999, background: "#fff4d6", color: "#9a6700" }}>lệch Lộ trình</span>}
                          </TD>
                          <TD><div style={{ minWidth: 170 }} onClick={(e) => e.stopPropagation()}>
                            {locked
                              ? <span className="tnum" style={{ fontWeight: 600 }}>{r.driver || "—"}</span>
                              : <Combo value={r.driver || ""} onChange={(v) => set(i, { driver: v })} options={drivers} placeholder="Chọn lái…" small />}
                          </div></TD>
                          <TD right><span className="tnum">{r.days}</span></TD>
                          <TD right><span className="tnum">{r.trips}</span></TD>
                          <TD right><span className="tnum" style={{ color: "var(--ink-4)" }}>{fmtVND(r.paidDaily)}</span></TD>
                          {showChoho && <TD right><span className="tnum" style={{ color: rs.choho ? "#9a6700" : "var(--ink-4)" }}>{fmtVND(rs.choho)}</span></TD>}
                          {showKhac && <TD right><span className="tnum" style={{ color: rs.khac ? "#6d28d9" : "var(--ink-4)" }}>{fmtVND(rs.khac)}</span></TD>}
                          <TD right><span className="tnum" style={{ fontWeight: 700, color: "var(--accent)" }}>{fmtVND(payroll)}</span></TD>
                          <TD right><span className="tnum" style={{ color: "var(--good)" }}>{fmtVND(paid)}</span></TD>
                          <TD right><span className="tnum" style={{ fontWeight: 700, color: remain > 0 ? "var(--danger)" : "var(--good)" }}>{fmtVND(remain)}</span></TD>
                        </tr>
                        {open[r.bks] && <tr><td colSpan={nCols} style={{ padding: 0, borderBottom: "1px solid var(--line-2)", background: "#fafbfc" }} onClick={(e) => e.stopPropagation()}>
                          <PayrollDetail row={r} q={tripQ} day={fDay} onlyWarn={fStatus === "warn"} loTrinhUrl={ROUTES.loTrinh} />
                          <ExtraPayEditor items={r.extraPay} onChange={(arr) => set(i, { extraPay: arr })} disabled={locked} />
                          <PaymentsEditor payments={r.payments} onChange={(arr) => set(i, { payments: arr })} payroll={payroll} />
                        </td></tr>}
                        </React.Fragment>
                        );
                      })}
                    </React.Fragment>
                    );
                  })}
                  {view.length === 0 && <tr><td colSpan={nCols} style={{ padding: "14px 10px", fontSize: 13, color: "var(--ink-4)" }}>{rows.length ? "Không có xe khớp bộ lọc." : "Kỳ rỗng."}</td></tr>}
                </tbody>
                <tfoot>
                  <tr style={{ background: "#fafbfc" }}>
                    <TD><b>Tổng {view.length} xe{filtering ? " (đang lọc)" : ""}</b></TD><TD />
                    <TD right><span className="tnum">{fmtNum(tot.days)}</span></TD><TD right><span className="tnum">{fmtNum(tot.trips)}</span></TD>
                    <TD right><span className="tnum" style={{ color: "var(--ink-4)" }}>{fmtVND(tot.daily)}</span></TD>
                    {showChoho && <TD right><span className="tnum" style={{ color: "#9a6700" }}>{fmtVND(tot.choho)}</span></TD>}
                    {showKhac && <TD right><span className="tnum" style={{ color: "#6d28d9" }}>{fmtVND(tot.khac)}</span></TD>}
                    <TD right><span className="tnum" style={{ fontWeight: 800, fontSize: 15, color: "var(--accent)" }}>{fmtVND(tot.payroll)}</span></TD>
                    <TD right><span className="tnum" style={{ color: "var(--good)" }}>{fmtVND(tot.paid)}</span></TD>
                    <TD right><span className="tnum" style={{ fontWeight: 800, color: (tot.payroll - tot.paid) > 0 ? "var(--danger)" : "var(--good)" }}>{fmtVND(tot.payroll - tot.paid)}</span></TD>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<ViewPayrollApp />);
