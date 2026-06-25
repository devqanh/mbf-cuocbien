import React from "react";
const { useState, useRef, useMemo, useEffect } = React;
import { I, Money, Payer, Txt, Combo, MultiCombo, DateField, Num, Line, Section, Modal, Btn, fmtVND, fmtNum, fmtShort, calcCost, calcVeh, calcRev, calcVehICD, calcRevICD, calcFreeTime, fmtHours, toNum } from "@trk/lib.jsx";
import { DTField, Field, DriverSpendRows, VatLine, ItemRows, ChiHoRows, DoanhThuRows, ChkBox, TRACK_COLORS, SWATCHES, colorHex, FlagPicker, CostLineRows, PaymentRows, Seg } from "./shared.jsx";

/* ===================== BẢNG GIÁ — editor (trang Bảng giá) ===================== */
const fmtBD = (s) => { if (!s) return ""; const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s); return m ? `${m[3]}/${m[2]}/${m[1]}` : s; };

function PriceList({ rows = [], onChange, onImported, cfg = {}, customer, bookId = null }) {
  const T = window.__TRK || {}; const ROUTES = T.routes || {};
  const [openKind, setOpenKind] = useState(null); // KIND đang mở (accordion); null = thu hết
  const [query, setQuery] = useState("");          // ô tra cứu tuyến
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState("");
  const [copySrc, setCopySrc] = useState("");       // khách NGUỒN để copy bảng giá
  const [copySrcBook, setCopySrcBook] = useState(""); // bảng giá (book) nguồn
  const otherCustomers = (cfg.customers || []).filter((c) => c && c !== customer);
  const srcBooks = ((cfg.customerInfo || {})[copySrc] || {}).priceBooks || [];
  const bookOpt = (b) => { const r = (b.from || b.to) ? `${b.from ? fmtBD(b.from) : "…"}–${b.to ? fmtBD(b.to) : "…"}` : "Mọi ngày"; return { value: String(b.id), label: (b.label ? b.label + " · " : "") + r + ` (${b.count || 0})` }; };

  // ---- Copy bảng giá từ 1 BOOK khác sang BOOK đang chọn ----
  const doCopy = async () => {
    if (!bookId) { setMsg("Chọn bảng giá đích trước."); return; }
    if (!copySrcBook) { setMsg("Chọn bảng giá NGUỒN."); return; }
    if (String(copySrcBook) === String(bookId)) { setMsg("Chọn bảng giá nguồn khác."); return; }
    let replace = false;
    if (rows.length > 0) {
      const ok = await window.confirmAction({
        title: "Ghi đè bảng giá?",
        text: `Bảng giá đang chọn có <b>${rows.length}</b> dòng. Copy sẽ <b>GHI ĐÈ</b> toàn bộ. Tiếp tục?`,
        confirmText: "Ghi đè bằng bảng giá nguồn", cancelText: "Huỷ",
      });
      if (!ok) return;
      replace = true;
    }
    setBusy(true); setMsg("");
    try {
      const res = await fetch(ROUTES.priceCopy, { method: "POST", headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": T.csrf }, body: JSON.stringify({ fromBook: Number(copySrcBook), toBook: Number(bookId), replace }) }).then((r) => r.json());
      setBusy(false);
      if (res && res.ok) { (onImported || onChange)(res.priceList || []); setMsg(`Đã copy ${res.copied} dòng.`); setCopySrc(""); setCopySrcBook(""); }
      else { setMsg("Copy lỗi: " + ((res && res.message) || "không rõ")); }
    } catch (err) { setBusy(false); setMsg("Copy lỗi kết nối."); }
  };
  // Gộp danh mục địa điểm + mọi "Điểm Hạ" đang có trong bảng giá (kể cả ký hiệu mới import) để select luôn hiển thị
  // Địa điểm hạ dùng KÝ HIỆU (code) cho gọn: options = ký hiệu trong danh mục + ký hiệu đã có trên dòng.
  const locOpts = [...new Set([...Object.values(cfg.locationCode || {}), ...rows.map((r) => r.loc).filter(Boolean)])].sort();
  const blank = { distance: "", transFee40: "", transFee20: "", fuelFee40: "", fuelFee20: "" };
  const set = (id, np) => onChange(rows.map((e) => (e.id === id ? { ...e, ...np } : e)));
  const del = (id) => onChange(rows.filter((e) => e.id !== id));

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
      const res = await fetch(ROUTES.priceImport, { method: "POST", headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": T.csrf }, body: JSON.stringify({ customer, book: bookId, rows: [], replace: true }) }).then((r) => r.json());
      setBusy(false);
      if (res && res.ok) { (onImported || onChange)(res.priceList || []); setMsg("Đã xóa toàn bộ bảng giá. Bấm Import Excel để nạp lại."); }
      else setMsg("Xóa lỗi: " + ((res && res.message) || "không rõ"));
    } catch (err) { setBusy(false); setMsg("Xóa lỗi kết nối."); }
  };

  // ---- top group = Địa điểm (location) ; mỗi nhóm có conn riêng (Connect/Disconnect/Non) ----
  // Nhóm MỚI mang `gid` riêng → sửa Địa điểm/conn KHÔNG bị gộp nhầm với nhóm sẵn có (cùng loc+conn).
  // Dòng cũ (từ DB, chưa có gid) gom theo loc¦conn như cũ; lưu xong reload sẽ gom lại theo loc¦conn.
  const locKey = (r) => (r.gid ? "g:" + r.gid : (r.loc || "") + "¦" + (r.conn || "Connect"));
  const locGroups = [];
  rows.forEach((r) => { const k = locKey(r); if (!locGroups.some((g) => g.key === k)) locGroups.push({ key: k, loc: r.loc || "", conn: r.conn || "Connect", gid: r.gid }); });
  if (!locGroups.length) locGroups.push({ key: "¦Connect", loc: "", conn: "Connect" });
  const setLocField = (oldKey, np) => onChange(rows.map((r) => (locKey(r) === oldKey ? { ...r, ...np } : r)));
  const newGid = () => "n" + Date.now() + Math.round(Math.random() * 1e6);
  const addLoc = () => onChange([...rows, { id: Date.now() + Math.random(), gid: newGid(), loc: "", conn: "Connect", kind: "Chưa phân nhóm", from: "", to1: "", to2: "", to3: "", to4: "", ...blank }]);
  // kinds within a loc-group
  const kindsIn = (g) => { const out = []; rows.filter((r) => locKey(r) === g.key).forEach((r) => { const k = r.kind || "Chưa phân nhóm"; if (!out.includes(k)) out.push(k); }); return out.length ? out : ["Chưa phân nhóm"]; };
  const renameKind = (gKey, oldK, newK) => onChange(rows.map((r) => (locKey(r) === gKey && (r.kind || "Chưa phân nhóm") === oldK ? { ...r, kind: newK || "Chưa phân nhóm" } : r)));
  const addRowTo = (g, k) => onChange([...rows, { id: Date.now() + Math.random(), gid: g.gid, loc: g.loc, conn: g.conn, kind: k, from: "", to1: "", to2: "", to3: "", to4: "", ...blank }]);
  const addKind = (g) => { const ks = kindsIn(g); const base = "Nhóm mới"; let n = base, i = 1; while (ks.includes(n)) n = base + " " + (++i); onChange([...rows, { id: Date.now() + Math.random(), gid: g.gid, loc: g.loc, conn: g.conn, kind: n, from: "", to1: "", to2: "", to3: "", to4: "", ...blank }]); };
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
              <div style={{ minWidth: 220 }}>
                <Combo value={g.loc} onChange={(v) => setLocField(g.key, { loc: v })} options={locOpts} placeholder="Chọn ký hiệu địa điểm hạ…" />
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
        {!rows.length && <div style={{ padding: "10px 2px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có dòng báo giá — bấm <b>Nhập báo giá gốc</b> ở trên để nạp từ file, hoặc thêm nhóm địa điểm hạ thủ công.</div>}
      </div>

      <div style={{ display: "flex", gap: 8, marginTop: 12, paddingTop: 12, borderTop: "1px solid var(--line-2)", alignItems: "center", flexWrap: "wrap" }}>
          <button type="button" onClick={addLoc}
            style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 13px", fontSize: 12.5, fontWeight: 600, border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-2)", cursor: "pointer" }}>
            <I.plus /> Thêm nhóm địa điểm hạ
          </button>
          {/* Copy nhanh: chọn khách NGUỒN → bảng giá NGUỒN → copy vào bảng giá đang chọn */}
          {bookId && otherCustomers.length > 0 && (
            <span style={{ display: "inline-flex", alignItems: "center", gap: 6, flexWrap: "wrap" }}>
              <span style={{ width: 170 }}><Combo value={copySrc} onChange={(v) => { setCopySrc(v); setCopySrcBook(""); }} options={otherCustomers} placeholder="Copy từ khách…" small clearable /></span>
              {copySrc && <span style={{ width: 190 }}><Combo value={copySrcBook} onChange={setCopySrcBook} options={srcBooks.map(bookOpt)} placeholder="…bảng giá nào" small clearable /></span>}
              <button type="button" onClick={doCopy} disabled={busy || !copySrcBook}
                style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 13px", fontSize: 12.5, fontWeight: 600, border: "1px solid var(--accent-weak)", borderRadius: 8, background: "var(--accent-weak-2)", color: "var(--accent)", cursor: copySrcBook ? "pointer" : "default", opacity: copySrcBook ? 1 : 0.6 }}>
                <i className="bi bi-files" /> Copy vào bảng giá này
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
          {msg && <span style={{ fontSize: 12, fontWeight: 600, color: (msg.startsWith("Đã copy") || msg.startsWith("Đã xóa")) ? "var(--good)" : "var(--danger)" }}>{msg}</span>}
      </div>
    </div>
  );
}


export { PriceList };
