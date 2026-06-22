import React from "react";
const { useState, useRef, useMemo, useEffect } = React;
import { I, Money, Payer, Txt, Combo, MultiCombo, DateField, Num, Line, Section, Modal, Btn, fmtVND, fmtNum, fmtShort, calcCost, calcVeh, calcRev, calcVehICD, calcRevICD, calcFreeTime, fmtHours, toNum } from "@trk/lib.jsx";
import { DTField, Field, DriverSpendRows, VatLine, ItemRows, ChiHoRows, DoanhThuRows, ChkBox, TRACK_COLORS, SWATCHES, colorHex, FlagPicker, CostLineRows, PaymentRows, Seg } from "./shared.jsx";

/* ===================== BẢNG GIÁ — editor (trang Bảng giá) ===================== */
function PriceList({ rows = [], onChange, onImported, cfg = {}, customer }) {
  const T = window.__TRK || {}; const ROUTES = T.routes || {};
  const [imp, setImp] = useState(null);   // {names:[], wb} sau khi đọc file
  const [sheet, setSheet] = useState("");
  const [openKind, setOpenKind] = useState(null); // KIND đang mở (accordion); null = thu hết
  const [query, setQuery] = useState("");          // ô tra cứu tuyến
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState("");
  const [copySrc, setCopySrc] = useState("");   // khách NGUỒN để copy bảng giá vào khách đang chọn
  const fileRef = React.useRef(null);
  const otherCustomers = (cfg.customers || []).filter((c) => c && c !== customer);

  // ---- Copy bảng giá từ 1 khách khác sang khách đang chọn ----
  const doCopy = async () => {
    if (!customer) { setMsg("Chọn khách đích trước."); return; }
    if (!copySrc || copySrc === customer) { setMsg("Chọn khách NGUỒN khác."); return; }
    let replace = false;
    if (rows.length > 0) {
      const esc = (s) => String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
      const ok = await window.confirmAction({
        title: "Ghi đè bảng giá?",
        text: `Khách <b>${esc(customer)}</b> đang có <b>${rows.length}</b> dòng. Copy từ <b>${esc(copySrc)}</b> sẽ <b>GHI ĐÈ</b> toàn bộ. Tiếp tục?`,
        confirmText: "Ghi đè bằng bảng giá nguồn", cancelText: "Huỷ",
      });
      if (!ok) return;
      replace = true;
    }
    setBusy(true); setMsg("");
    try {
      const res = await fetch(ROUTES.priceCopy, { method: "POST", headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": T.csrf }, body: JSON.stringify({ from: copySrc, to: customer, replace }) }).then((r) => r.json());
      setBusy(false);
      if (res && res.ok) { (onImported || onChange)(res.priceList || []); setMsg(`Đã copy ${res.copied} dòng từ ${copySrc}.`); setCopySrc(""); }
      else { setMsg("Copy lỗi: " + ((res && res.message) || "không rõ")); }
    } catch (err) { setBusy(false); setMsg("Copy lỗi kết nối."); }
  };
  // Gộp danh mục địa điểm + mọi "Điểm Hạ" đang có trong bảng giá (kể cả ký hiệu mới import) để select luôn hiển thị
  const locOpts = [...new Set([...(cfg.locations || []), ...rows.map((r) => r.loc).filter(Boolean)])];
  const blank = { distance: "", transFee40: "", transFee20: "", fuelFee40: "", fuelFee20: "" };
  const set = (id, np) => onChange(rows.map((e) => (e.id === id ? { ...e, ...np } : e)));
  const del = (id) => onChange(rows.filter((e) => e.id !== id));

  // ---- Import Excel: đọc file → chọn sheet → parse → gọi API upsert ----
  const onFile = (e) => {
    const f = e.target.files && e.target.files[0]; e.target.value = "";
    if (!f) return;
    if (typeof XLSX === "undefined") { setMsg("Thư viện Excel chưa tải xong, thử lại."); return; }
    setMsg("");
    const rd = new FileReader();
    rd.onload = () => { const wb = XLSX.read(rd.result, { type: "array" }); setImp({ names: wb.SheetNames, wb }); setSheet(wb.SheetNames[0] || ""); };
    rd.readAsArrayBuffer(f);
  };
  const norm = (s) => String(s == null ? "" : s).trim().toLowerCase().replace(/\s+/g, " ");
  const doImport = async () => {
    if (!imp || !sheet) return;
    if (!customer) { setMsg("Chọn một khách hàng trước khi import."); return; }
    setBusy(true); setMsg("");
    const aoa = XLSX.utils.sheet_to_json(imp.wb.Sheets[sheet], { header: 1, raw: true, defval: "" });
    let hi = aoa.findIndex((r) => (r || []).some((c) => norm(c) === "loại" || norm(c) === "điểm hạ"));
    if (hi < 0) hi = 0;
    const header = (aoa[hi] || []).map(norm);
    const idx = (...names) => { for (const n of names) { const i = header.indexOf(norm(n)); if (i >= 0) return i; } return -1; };
    const C = { conn: idx("Loại"), loc: idx("Điểm Hạ"), kind: idx("KIND"), from: idx("FROM"), to1: idx("TO 1", "TO1"), to2: idx("TO 2", "TO2"), to3: idx("TO 3", "TO3"), to4: idx("TO 4", "TO4"), distance: idx("Distance (km)", "Distance", "KM"), transFee40: idx("Transport fee 40FT", "Transport fee 40"), transFee20: idx("Transport fee 20FT", "Transport fee 20"), fuelFee40: idx("Fuel fee 40FT", "Fuel fee 40"), fuelFee20: idx("Fuel fee 20FT", "Fuel fee 20") };
    const num = (v) => String(v == null ? "" : v).replace(/[^\d]/g, "");
    const txt = (v) => String(v == null ? "" : v).trim();
    const out = [];
    for (let r = hi + 1; r < aoa.length; r++) {
      const row = aoa[r] || []; const g = (i) => (i >= 0 ? row[i] : "");
      const connRaw = txt(g(C.conn)).toUpperCase(); const loc = txt(g(C.loc));
      const from = txt(g(C.from));
      if (!connRaw && !loc && !from) continue;
      const conn = connRaw.includes("NON") ? "Non" : (connRaw.includes("DISCON") ? "Disconnect" : (connRaw.includes("CON") ? "Connect" : (connRaw || "Connect")));
      out.push({ conn, loc, kind: txt(g(C.kind)), from, to1: txt(g(C.to1)), to2: txt(g(C.to2)), to3: txt(g(C.to3)), to4: txt(g(C.to4)), distance: num(g(C.distance)), transFee40: num(g(C.transFee40)), transFee20: num(g(C.transFee20)), fuelFee40: num(g(C.fuelFee40)), fuelFee20: num(g(C.fuelFee20)) });
    }
    if (!out.length) { setBusy(false); setMsg("Sheet không có dòng dữ liệu hợp lệ."); return; }
    try {
      const res = await fetch(ROUTES.priceImport, { method: "POST", headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": T.csrf }, body: JSON.stringify({ customer, rows: out }) }).then((r) => r.json());
      setBusy(false);
      if (res && res.ok) { (onImported || onChange)(res.priceList || []); setImp(null); setMsg(`Đã import ${res.imported} dòng — ${res.created} mới, ${res.updated} cập nhật.`); }
      else { setMsg("Import lỗi: " + ((res && res.message) || "không rõ")); }
    } catch (err) { setBusy(false); setMsg("Import lỗi kết nối."); }
  };

  // ---- Xóa toàn bộ bảng giá của khách (để import lại) — hỏi xác nhận ----
  const clearAll = async () => {
    if (!customer || !rows.length) return;
    const esc = (s) => String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
    const ok = await window.confirmAction({
      title: "Xóa toàn bộ bảng giá?",
      text: `Toàn bộ <b>${rows.length}</b> dòng bảng giá của khách <b>${esc(customer)}</b> sẽ bị xóa để import lại. Không thể hoàn tác.`,
      confirmText: '<i class="bi bi-trash me-1"></i> Xóa toàn bộ',
      danger: true,
    });
    if (!ok) return;
    setBusy(true); setMsg("");
    try {
      const res = await fetch(ROUTES.priceImport, { method: "POST", headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": T.csrf }, body: JSON.stringify({ customer, rows: [], replace: true }) }).then((r) => r.json());
      setBusy(false);
      if (res && res.ok) { (onImported || onChange)(res.priceList || []); setMsg("Đã xóa toàn bộ bảng giá. Bấm Import Excel để nạp lại."); }
      else setMsg("Xóa lỗi: " + ((res && res.message) || "không rõ"));
    } catch (err) { setBusy(false); setMsg("Xóa lỗi kết nối."); }
  };

  // ---- top group = Địa điểm (location) ; each loc-group has its own conn (Connect/Disconnect) ----
  const locKey = (r) => (r.loc || "") + "¦" + (r.conn || "Connect");
  const locGroups = [];
  rows.forEach((r) => { const k = locKey(r); if (!locGroups.some((g) => g.key === k)) locGroups.push({ key: k, loc: r.loc || "", conn: r.conn || "Connect" }); });
  if (!locGroups.length) locGroups.push({ key: "¦Connect", loc: "", conn: "Connect" });
  const setLocField = (oldKey, np) => onChange(rows.map((r) => (locKey(r) === oldKey ? { ...r, ...np } : r)));
  const addLoc = () => onChange([...rows, { id: Date.now() + Math.random(), loc: "", conn: "Connect", kind: "Chưa phân nhóm", from: "", to1: "", to2: "", to3: "", to4: "", ...blank }]);
  // kinds within a loc-group
  const kindsIn = (g) => { const out = []; rows.filter((r) => locKey(r) === g.key).forEach((r) => { const k = r.kind || "Chưa phân nhóm"; if (!out.includes(k)) out.push(k); }); return out.length ? out : ["Chưa phân nhóm"]; };
  const renameKind = (gKey, oldK, newK) => onChange(rows.map((r) => (locKey(r) === gKey && (r.kind || "Chưa phân nhóm") === oldK ? { ...r, kind: newK || "Chưa phân nhóm" } : r)));
  const addRowTo = (g, k) => onChange([...rows, { id: Date.now() + Math.random(), loc: g.loc, conn: g.conn, kind: k, from: "", to1: "", to2: "", to3: "", to4: "", ...blank }]);
  const addKind = (g) => { const ks = kindsIn(g); const base = "Nhóm mới"; let n = base, i = 1; while (ks.includes(n)) n = base + " " + (++i); onChange([...rows, { id: Date.now() + Math.random(), loc: g.loc, conn: g.conn, kind: n, from: "", to1: "", to2: "", to3: "", to4: "", ...blank }]); };
  // Tra cứu: lọc dòng theo điểm hạ / FROM / TO / KIND
  const ql = (query || "").trim().toLowerCase();
  const matchRow = (r) => !ql || [r.from, r.to1, r.to2, r.to3, r.to4, r.kind, r.loc, r.conn].filter(Boolean).join(" ").toLowerCase().includes(ql);
  const matchCount = ql ? rows.filter(matchRow).length : 0;
  const cols = "46px 46px 46px 46px 46px 52px 1fr 1fr 1fr 1fr 24px";
  const cell = (val, onCh, ph, opt) => (
    <input value={val || ""} onChange={(e) => onCh(opt && opt.num ? e.target.value.replace(/[^\d]/g, "") : e.target.value)} placeholder={ph}
      className={opt && (opt.num || opt.money) ? "tnum" : ""}
      style={{ width: "100%", padding: "6px 7px", fontSize: 12, textAlign: opt && (opt.num || opt.money) ? "right" : "center", border: "1px solid var(--line)", borderRadius: 7, outline: "none", background: "#fff", fontWeight: opt && opt.money ? 600 : 400 }}
      onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
  );
  const moneyCell = (val, onCh, ph) => {
    const grp = (d) => { d = (d || "").toString().replace(/[^\d]/g, ""); return d ? d.replace(/\B(?=(\d{3})+(?!\d))/g, ".") : ""; };
    return (
      <div style={{ position: "relative" }}>
        <input value={grp(val)} onChange={(e) => onCh(e.target.value.replace(/[^\d]/g, ""))} placeholder={ph} inputMode="numeric" className="tnum"
          style={{ width: "100%", padding: "6px 20px 6px 7px", fontSize: 12, textAlign: "right", fontWeight: 600, border: "1px solid var(--line)", borderRadius: 7, outline: "none", background: "#fff" }}
          onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
        <span style={{ position: "absolute", right: 7, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)", fontSize: 11, pointerEvents: "none" }}>₫</span>
      </div>
    );
  };
  const colHeader = (
    <>
      <div style={{ display: "grid", gridTemplateColumns: cols, gap: 6, padding: "0 0 4px" }}>
        <div style={{ gridColumn: "1 / 6", textAlign: "center", fontSize: 10.5, fontWeight: 700, color: "var(--accent)", textTransform: "uppercase", letterSpacing: "0.04em", background: "var(--accent-weak)", borderRadius: 6, padding: "3px 0" }}>Routing</div>
        <div /><div /><div /><div />
      </div>
      <div style={{ display: "grid", gridTemplateColumns: cols, gap: 6, padding: "0 0 5px" }}>
        {["FROM", "TO", "TO", "TO", "TO", "KM", "Cước 40FT", "Cước 20FT", "Dầu 40FT", "Dầu 20FT", ""].map((h, i) => (
          <div key={i} style={{ fontSize: 10, fontWeight: 600, color: "var(--ink-4)", textTransform: "uppercase", letterSpacing: "0.02em", textAlign: i >= 6 && i <= 9 ? "right" : "center" }}>{h}</div>
        ))}
      </div>
    </>
  );
  const rowEl = (e) => (
    <div key={e.id} style={{ display: "grid", gridTemplateColumns: cols, gap: 6, alignItems: "center" }}>
      {cell(e.from, (v) => set(e.id, { from: v }), "HPP")}
      {cell(e.to1, (v) => set(e.id, { to1: v }), "TL")}
      {cell(e.to2, (v) => set(e.id, { to2: v }), "–")}
      {cell(e.to3, (v) => set(e.id, { to3: v }), "–")}
      {cell(e.to4, (v) => set(e.id, { to4: v }), "HPP")}
      {cell(e.distance, (v) => set(e.id, { distance: v }), "0", { num: true })}
      {moneyCell(e.transFee40, (v) => set(e.id, { transFee40: v }), "0")}
      {moneyCell(e.transFee20, (v) => set(e.id, { transFee20: v }), "0")}
      {moneyCell(e.fuelFee40, (v) => set(e.id, { fuelFee40: v }), "0")}
      {moneyCell(e.fuelFee20, (v) => set(e.id, { fuelFee20: v }), "0")}
      <button type="button" onClick={() => del(e.id)} title="Xóa"
        style={{ width: 26, height: 26, display: "grid", placeItems: "center", border: "none", borderRadius: 6, background: "transparent", color: "var(--ink-4)", cursor: "pointer" }}
        onMouseEnter={(ev) => { ev.currentTarget.style.background = "#fce8e8"; ev.currentTarget.style.color = "var(--danger)"; }}
        onMouseLeave={(ev) => { ev.currentTarget.style.background = "transparent"; ev.currentTarget.style.color = "var(--ink-4)"; }}><I.trash /></button>
    </div>
  );
  return (
    <div>
      <div style={{ position: "relative", marginBottom: 10 }}>
        <span style={{ position: "absolute", left: 11, top: "50%", transform: "translateY(-50%)", color: "var(--ink-4)" }}><I.search /></span>
        <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Tra cứu tuyến: điểm hạ, FROM, TO, KIND…"
          style={{ width: "100%", padding: "9px 32px 9px 34px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 10, outline: "none", background: "#fafbfc" }}
          onFocus={(e) => { e.target.style.borderColor = "var(--accent)"; e.target.style.background = "#fff"; }}
          onBlur={(e) => { e.target.style.borderColor = "var(--line)"; e.target.style.background = "#fafbfc"; }} />
        {query && <button type="button" onClick={() => setQuery("")} title="Xóa tìm" style={{ position: "absolute", right: 8, top: "50%", transform: "translateY(-50%)", width: 22, height: 22, display: "grid", placeItems: "center", border: "none", borderRadius: 6, background: "var(--line-2)", color: "var(--ink-3)", cursor: "pointer" }}><I.x /></button>}
      </div>
      {ql && <div style={{ fontSize: 12, color: matchCount ? "var(--ink-4)" : "var(--danger)", marginBottom: 8 }}>{matchCount} dòng khớp “{query.trim()}”{matchCount === 0 ? " — không tìm thấy tuyến nào." : ""}</div>}
      {colHeader}
      <div style={{ maxHeight: "62vh", overflowY: "auto", display: "flex", flexDirection: "column", gap: 18 }}>
        {locGroups.map((g) => {
          if (ql && !rows.some((r) => locKey(r) === g.key && matchRow(r))) return null;
          return (
          <div key={g.key} style={{ border: "1px solid var(--line)", borderRadius: 12, padding: "12px 12px 10px", background: "#fcfcfd" }}>
            {/* location super-header: location select + connect/disconnect select */}
            <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 10, flexWrap: "wrap" }}>
              <span style={{ fontSize: 10.5, fontWeight: 700, color: "var(--ink-4)", textTransform: "uppercase", letterSpacing: "0.04em" }}>Địa điểm hạ</span>
              <div style={{ position: "relative", minWidth: 200 }}>
                <select value={g.loc} onChange={(e) => setLocField(g.key, { loc: e.target.value })}
                  style={{ width: "100%", appearance: "none", WebkitAppearance: "none", padding: "8px 28px 8px 11px", fontSize: 13.5, fontWeight: 700, color: g.loc ? "var(--ink)" : "var(--ink-4)", background: "#fff", border: "1px solid var(--line)", borderRadius: 9, cursor: "pointer" }}>
                  <option value="">— Chọn địa điểm hạ —</option>
                  {locOpts.map((l) => <option key={l} value={l}>{l}</option>)}
                </select>
                <span style={{ position: "absolute", right: 9, top: "50%", transform: "translateY(-50%)", color: "var(--ink-3)", pointerEvents: "none" }}><I.chev /></span>
              </div>
              <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 9, padding: 3 }}>
                {["Connect", "Disconnect", "Non"].map((opt) => {
                  const on = g.conn === opt;
                  const onColor = opt === "Connect" ? "var(--good)" : opt === "Disconnect" ? "var(--danger)" : "var(--ink-1)";
                  return (
                    <button key={opt} type="button" onClick={() => setLocField(g.key, { conn: opt })}
                      title={opt === "Non" ? "Áp cho MỌI trạng thái (không phân biệt connect/disconnect)" : ""}
                      style={{ border: "none", cursor: "pointer", fontSize: 12.5, fontWeight: 600, padding: "6px 14px", borderRadius: 7,
                        background: on ? "#fff" : "transparent", color: on ? onColor : "var(--ink-3)", boxShadow: on ? "0 1px 2px rgba(16,19,23,.12)" : "none", transition: "all .12s" }}>
                      {opt}
                    </button>
                  );
                })}
              </div>
              <div style={{ flex: 1 }} />
              {locGroups.length > 1 && (
                <button type="button" title="Xóa nhóm địa điểm hạ"
                  onClick={async () => {
                    const n = rows.filter((r) => locKey(r) === g.key).length;
                    const ok = await window.confirmAction({
                      title: "Xóa nhóm tuyến?",
                      text: `Sẽ bỏ <b>${n}</b> dòng tuyến của nhóm điểm hạ này khỏi bảng giá. Thay đổi áp dụng khi bấm <b>Lưu</b>.`,
                      confirmText: '<i class="bi bi-trash me-1"></i> Xóa nhóm',
                      danger: true,
                    });
                    if (ok) onChange(rows.filter((r) => locKey(r) !== g.key));
                  }}
                  style={{ width: 28, height: 28, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 7, background: "#fff", color: "var(--ink-4)", cursor: "pointer" }}
                  onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
                  onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-4)"; }}><I.trash /></button>
              )}
            </div>
            {/* KIND subgroups — accordion: mặc định thu gọn, mở 1 thu cái khác */}
            <div style={{ display: "flex", flexDirection: "column", gap: 8, paddingLeft: 4 }}>
              {kindsIn(g).map((k) => {
                const kkey = g.key + "¦" + k;
                const kRows = rows.filter((r) => locKey(r) === g.key && (r.kind || "Chưa phân nhóm") === k);
                const shown = ql ? kRows.filter(matchRow) : kRows;
                if (ql && !shown.length) return null;
                const open = ql ? true : (openKind === kkey);
                return (
                <div key={k} style={{ border: "1px solid var(--line-2)", borderRadius: 9, overflow: "hidden", background: "#fff" }}>
                  <div onClick={() => { if (!ql) setOpenKind(open ? null : kkey); }}
                    style={{ display: "flex", alignItems: "center", gap: 8, padding: "7px 9px", cursor: ql ? "default" : "pointer", background: open ? "var(--accent-weak-2)" : "#fff" }}>
                    <span style={{ display: "grid", placeItems: "center", color: "var(--ink-3)", transform: `rotate(${open ? 0 : -90}deg)`, transition: "transform .12s" }}><I.chev /></span>
                    <span style={{ width: 4, height: 15, borderRadius: 2, background: "var(--accent)" }} />
                    <input value={k === "Chưa phân nhóm" ? "" : k} onClick={(e) => e.stopPropagation()} onChange={(e) => renameKind(g.key, k, e.target.value)} placeholder="Tên KIND / nhóm…"
                      style={{ flex: 1, padding: "4px 8px", fontSize: 13, fontWeight: 700, color: "var(--ink)", border: "1px solid transparent", borderRadius: 7, outline: "none", background: "transparent" }}
                      onFocus={(e) => { e.target.style.borderColor = "var(--accent)"; e.target.style.background = "#fff"; }}
                      onBlur={(e) => { e.target.style.borderColor = "transparent"; e.target.style.background = "transparent"; }} />
                    <span className="tnum" style={{ fontSize: 11, fontWeight: 600, color: "var(--ink-4)", background: "var(--line-2)", padding: "1px 8px", borderRadius: 999 }}>{ql ? `${shown.length}/${kRows.length}` : kRows.length} tuyến</span>
                  </div>
                  {open && (
                    <div style={{ padding: "6px 9px 9px" }}>
                      <div style={{ display: "flex", flexDirection: "column", gap: 4 }}>{shown.map(rowEl)}</div>
                      {!ql && (
                        <button type="button" onClick={() => addRowTo(g, k)}
                          style={{ display: "inline-flex", alignItems: "center", gap: 6, marginTop: 6, padding: "5px 10px", fontSize: 12, fontWeight: 600, border: "none", borderRadius: 7, background: "var(--accent-weak)", color: "var(--accent)", cursor: "pointer" }}>
                          <I.plus /> Thêm dòng
                        </button>
                      )}
                    </div>
                  )}
                </div>
              ); })}
            </div>
            {!ql && (
              <button type="button" onClick={() => addKind(g)}
                style={{ display: "inline-flex", alignItems: "center", gap: 6, marginTop: 10, padding: "5px 10px", fontSize: 12, fontWeight: 600, border: "1px dashed var(--line)", borderRadius: 7, background: "#fff", color: "var(--ink-2)", cursor: "pointer" }}>
                <I.plus /> Thêm nhóm KIND
              </button>
            )}
          </div>
        ); })}
        {!rows.length && !imp && <div style={{ padding: "10px 2px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có dòng báo giá — bấm <b>Import Excel</b> để nạp từ file, hoặc thêm nhóm địa điểm hạ thủ công.</div>}
      </div>

      <input ref={fileRef} type="file" accept=".xlsx,.xls" onChange={onFile} style={{ display: "none" }} />

      {imp ? (
        <div style={{ marginTop: 12, padding: "12px 14px", border: "1px solid var(--accent-weak)", background: "var(--accent-weak-2)", borderRadius: 10 }}>
          <div style={{ fontSize: 12.5, fontWeight: 600, color: "var(--ink-2)", marginBottom: 8 }}>Chọn sheet để import</div>
          <div style={{ display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap" }}>
            <div style={{ position: "relative", minWidth: 240 }}>
              <select value={sheet} onChange={(e) => setSheet(e.target.value)}
                style={{ width: "100%", appearance: "none", WebkitAppearance: "none", padding: "8px 28px 8px 11px", fontSize: 13, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 9, background: "#fff", cursor: "pointer" }}>
                {imp.names.map((n) => <option key={n} value={n}>{n}</option>)}
              </select>
              <span style={{ position: "absolute", right: 9, top: "50%", transform: "translateY(-50%)", color: "var(--ink-3)", pointerEvents: "none" }}><I.chev /></span>
            </div>
            <Btn variant="primary" onClick={doImport}>{busy ? "Đang import…" : "Import sheet này"}</Btn>
            <Btn onClick={() => { setImp(null); setMsg(""); }}>Hủy</Btn>
          </div>
          <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 8, lineHeight: 1.5 }}>
            Cột nhận dạng theo tiêu đề: <b>Loại · Điểm Hạ · KIND · FROM · TO 1..4 · Distance (km) · Transport fee 40FT/20FT · Fuel fee 40FT/20FT</b>.
            Trùng tuyến (Loại+Điểm Hạ+KIND+FROM+TO) sẽ cập nhật giá; chưa có thì tạo mới. Ký hiệu ở <b>FROM</b> và <b>TO</b> tự thêm vào danh mục <b>Địa điểm</b>; các <b>TO</b> đồng thời thêm vào danh mục <b>Kho</b>.
          </div>
        </div>
      ) : (
        <div style={{ display: "flex", gap: 8, marginTop: 12, paddingTop: 12, borderTop: "1px solid var(--line-2)", alignItems: "center", flexWrap: "wrap" }}>
          <button type="button" onClick={() => fileRef.current && fileRef.current.click()}
            style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 13px", fontSize: 12.5, fontWeight: 600, border: "none", borderRadius: 8, background: "var(--accent)", color: "#fff", cursor: "pointer" }}>
            <I.plus /> Import Excel
          </button>
          <button type="button" onClick={addLoc}
            style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 13px", fontSize: 12.5, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-2)", cursor: "pointer" }}>
            <I.plus /> Thêm nhóm địa điểm hạ
          </button>
          {/* Copy nhanh bảng giá từ khách khác */}
          {otherCustomers.length > 0 && (
            <span style={{ display: "inline-flex", alignItems: "center", gap: 6 }}>
              <span style={{ width: 200 }}><Combo value={copySrc} onChange={setCopySrc} options={otherCustomers} placeholder="Copy giá từ khách…" small clearable /></span>
              <button type="button" onClick={doCopy} disabled={busy || !copySrc}
                style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 13px", fontSize: 12.5, fontWeight: 600, border: "1px solid var(--accent-weak)", borderRadius: 8, background: "var(--accent-weak-2)", color: "var(--accent)", cursor: copySrc ? "pointer" : "default", opacity: copySrc ? 1 : 0.6 }}>
                <i className="bi bi-files" /> Copy sang khách này
              </button>
            </span>
          )}
          {rows.length > 0 && (
            <button type="button" onClick={clearAll} disabled={busy}
              style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 13px", fontSize: 12.5, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-3)", cursor: "pointer" }}
              onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; e.currentTarget.style.borderColor = "#f3c9c9"; }}
              onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-3)"; e.currentTarget.style.borderColor = "var(--line)"; }}>
              <I.trash /> Xóa toàn bộ bảng giá
            </button>
          )}
          {msg && <span style={{ fontSize: 12, fontWeight: 600, color: (msg.startsWith("Đã import") || msg.startsWith("Đã xóa")) ? "var(--good)" : "var(--danger)" }}>{msg}</span>}
        </div>
      )}
    </div>
  );
}


export { PriceList };
