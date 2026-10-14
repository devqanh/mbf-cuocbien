import React from "react";
const { useState, useEffect } = React;
import { I, fmtVND, fmtNum, fmtDate, toNum, Combo, Btn, DateField, useIsMobile } from "@trk/lib.jsx";

/* Thông tin công ty cho header bảng kê (màn hình + bản in). */
const CO = (window.__TRK && window.__TRK.boot && window.__TRK.boot.company) || {};
const ROUTES_TRK = (window.__TRK && window.__TRK.routes) || {};
const CO_NAME = CO.name || "MBF JOINT STOCK COMPANY";
const CO_SUB = [CO.website, CO.phone].filter(Boolean).join(" · ") || "http://mbf.com.vn · 84-24-39449616";

/* Badge Nhập/Xuất/Khác (xanh dương / xanh lá / hổ phách) — hiển thị loại cont của lô. */
function IoBadge({ io }) {
  const v = (io || "").toLowerCase();
  if (!v) return null;
  const tone = v.includes("nh") ? ["#1d6fd6", "#e7f0fc"] : v.includes("xu") ? ["#1f8a5b", "#e3f5ec"] : ["#b9750a", "#fcf3e2"];
  return <span style={{ fontSize: 10, fontWeight: 700, padding: "1px 7px", borderRadius: 999, color: tone[0], background: tone[1], whiteSpace: "nowrap" }}>{io}</span>;
}

/* Tiến độ "đã chi" của 1 bảng kê: x/y lô (xanh khi đủ, hổ phách khi dở dang). */
function PaidProgress({ n, total }) {
  n = n || 0;
  if (!total) return null;
  const tone = n >= total ? "var(--good)" : n > 0 ? "var(--warn)" : "var(--ink-4)";
  return <span className="tnum" style={{ color: tone, fontWeight: 600 }}>{n >= total ? "Đã chi đủ" : `Đã chi ${n}/${total}`}</span>;
}

const lineSum = (l) => (l.fee || 0) + (l.vat || 0) + (l.choho || 0);

/* Các phần của 1 dòng tích chi RIÊNG được: "fee" = cước (+VAT), "i:<key>" = từng khoản chi hộ. */
const partsOf = (l) => {
  const out = [];
  const fee = (l.fee || 0) + (l.vat || 0);
  if (fee) out.push({ k: "fee", amt: fee, label: "Cước" });
  (l.chohoItems || []).forEach((it) => { const a = Math.round(it.amount || 0); if (a) out.push({ k: "i:" + it.key, amt: a, label: it.item }); });
  return out;
};
const paidOf = (l) => l.paidParts || {};
const isFull = (l) => { const p = partsOf(l); return p.length > 0 && p.every((x) => paidOf(l)[x.k]); };
const paidAmtOf = (l) => partsOf(l).reduce((a, x) => a + (paidOf(l)[x.k] ? x.amt : 0), 0);
const todayStr = () => { const d = new Date(); return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0"); };
/* Tích / bỏ tích 1 phần (part) hoặc cả dòng (part = null) — tính lại paidAt (đã chi đủ). */
const withPaid = (l, on, part) => {
  const pp = { ...paidOf(l) };
  (part ? [part] : partsOf(l).map((x) => x.k)).forEach((k) => { if (on) pp[k] = pp[k] || todayStr(); else delete pp[k]; });
  const ps = partsOf(l);
  const full = ps.length > 0 && ps.every((x) => pp[x.k]);
  return { ...l, paidParts: pp, paidAt: full ? ps.map((x) => pp[x.k]).sort().pop() : "" };
};
const lineKey = (l) => (l.lineId ? "l" + l.lineId : "s" + l.id);

/* Bộ lọc Tất cả / Chưa chi / Đã chi — mỗi nút kèm số lô + số tiền để biết ngay còn bao nhiêu chưa chi. */
function PaidFilter({ lines, value, onChange }) {
  // Đếm lô: Đã chi = chi ĐỦ mọi phần; Chưa chi = còn ít nhất 1 phần chưa tích. Tiền tính theo từng phần đã/chưa tích.
  const paid = lines.filter(isFull);
  const unpaid = lines.filter((l) => !isFull(l));
  const paidSum = lines.reduce((a, l) => a + paidAmtOf(l), 0);
  const allSum = lines.reduce((a, l) => a + lineSum(l), 0);
  const opts = [
    ["all", "Tất cả", lines, "var(--ink-2)", allSum],
    ["unpaid", "Chưa chi", unpaid, "var(--warn)", allSum - paidSum],
    ["paid", "Đã chi", paid, "var(--good)", paidSum],
  ];
  return (
    <div className="ke-noprint" style={{ marginBottom: 12 }}>
      <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
        {opts.map(([k, label, arr, tone, amt]) => {
          const on = value === k;
          return (
            <button key={k} type="button" onClick={() => onChange(k)}
              style={{ display: "inline-flex", flexDirection: "column", alignItems: "flex-start", gap: 1, padding: "7px 13px", minWidth: 130, cursor: "pointer", textAlign: "left",
                border: "1px solid " + (on ? tone : "var(--line)"), borderRadius: 9, background: on ? "var(--accent-weak-2)" : "#fff", boxShadow: on ? "inset 0 0 0 1px " + tone : "none" }}>
              <span style={{ fontSize: 12.5, fontWeight: 700, color: tone }}>{label} <span className="tnum" style={{ fontWeight: 600, color: "var(--ink-3)" }}>({arr.length})</span></span>
              <span className="tnum" style={{ fontSize: 12, color: "var(--ink-2)", fontWeight: 600 }}>{fmtVND(amt)}</span>
            </button>
          );
        })}
      </div>
    </div>
  );
}

/* ============================================================
 * Khung "Cần chú ý" — cảnh báo cho kế toán (dữ liệu từ extStatementAlerts ở server).
 * createUrl: link trang Tạo (nhận ?vendor=&from=&to=); onOpenHash: mở bảng kê theo hashid.
 * ============================================================ */
function ExtAlerts({ alerts, createUrl, onOpenHash }) {
  const [open, setOpen] = useState({});
  const a = alerts || {};
  const link = (st) => (
    <button key={st.hashid} type="button" onClick={() => onOpenHash(st.hashid)}
      style={{ padding: 0, border: "none", background: "none", color: "var(--accent)", fontWeight: 600, cursor: "pointer", fontSize: "inherit" }}>{st.no}</button>
  );
  const sum = (arr, k) => arr.reduce((s, x) => s + (x[k] || 0), 0);
  const groups = [
    (a.unbilled || []).length && {
      key: "unbilled", tone: "warn", icon: "bi-inbox",
      title: `${sum(a.unbilled, "count")} lô thuê xe ngoài chưa lên bảng kê`,
      sub: `${a.unbilled.length} nhà xe · ${fmtVND(sum(a.unbilled, "amount"))} chưa VAT — chưa ghi nhận công nợ phải trả`,
      rows: a.unbilled.map((u) => (
        <div key={u.vendor} style={rowSt}>
          <span style={{ flex: 1, minWidth: 0 }}><b>{u.vendor}</b> · {u.count} lô · <span className="tnum">{fmtDate(u.from)} – {fmtDate(u.to)}</span></span>
          <b className="tnum">{fmtVND(u.amount)}</b>
          <a href={createUrl + "?" + new URLSearchParams({ vendor: u.vendor, from: u.from, to: u.to }).toString()}
            style={{ display: "inline-flex", alignItems: "center", gap: 5, padding: "4px 10px", fontSize: 12, fontWeight: 600, borderRadius: 7, background: "var(--accent)", color: "#fff", textDecoration: "none", whiteSpace: "nowrap" }}><I.plus /> Tạo bảng kê</a>
        </div>)),
    },
    (a.duplicates || []).length && {
      key: "dup", tone: "danger", icon: "bi-files",
      title: `${a.duplicates.length} lô nằm trong nhiều bảng kê`,
      sub: "Nguy cơ trả trùng tiền cho nhà xe — giữ lô ở 1 bảng kê, Làm mới bảng kê còn lại",
      rows: a.duplicates.map((d, i) => (
        <div key={i} style={rowSt}><b className="tnum">{d.booking || "—"}</b><span style={{ color: "var(--ink-3)" }}>có trong</span>
          <span style={{ display: "inline-flex", gap: 8, flexWrap: "wrap" }}>{d.statements.map(link)}</span></div>)),
    },
    (a.drift || []).length && {
      key: "drift", tone: "warn", icon: "bi-arrow-repeat",
      title: `${a.drift.length} bảng kê lệch với chi phí lô hiện tại`,
      sub: "Cước / chi hộ xe ngoài của lô đã sửa sau khi lập — mở bảng kê, bấm Làm mới dữ liệu rồi Lưu",
      rows: a.drift.map((d) => (
        <div key={d.hashid} style={rowSt}>{link(d)}<span style={{ flex: 1 }}>{d.vendor}</span><span className="tnum" style={{ color: "var(--warn)", fontWeight: 600 }}>{d.count} lô lệch</span></div>)),
    },
    (a.overdue || []).length && {
      key: "overdue", tone: "danger", icon: "bi-alarm",
      title: `${a.overdue.length} bảng kê còn nợ quá 30 ngày`,
      sub: `Tổng còn phải trả ${fmtVND(sum(a.overdue, "conNo"))}`,
      rows: a.overdue.map((d) => (
        <div key={d.hashid} style={rowSt}>{link(d)}<span style={{ flex: 1 }}>{d.vendor}</span>
          <span className="tnum" style={{ color: "var(--ink-3)" }}>{d.days} ngày</span><b className="tnum" style={{ color: "var(--danger)" }}>{fmtVND(d.conNo)}</b></div>)),
    },
    (a.mismatch || []).length && {
      key: "mismatch", tone: "info", icon: "bi-check2-square",
      title: `${a.mismatch.length} bảng kê: số đã tích chi khác đợt thanh toán đã ghi`,
      sub: "Đã tích chi mà chưa ghi đợt thanh toán (hoặc ngược lại) — công nợ đang chưa khớp",
      rows: a.mismatch.map((d) => {
        const gap = d.ticked - d.paid;
        return (
          <div key={d.hashid} style={rowSt}>{link(d)}<span style={{ flex: 1 }}>{d.vendor}</span>
            <span className="tnum" style={{ color: "var(--ink-3)" }}>tích {fmtNum(d.ticked)} · đợt TT {fmtNum(d.paid)}</span>
            <b className="tnum" style={{ color: "var(--warn)" }}>{gap > 0 ? "thiếu đợt " : "thừa đợt "}{fmtNum(Math.abs(gap))}</b></div>);
      }),
    },
    (a.missing || []).length && {
      key: "missing", tone: "info", icon: "bi-exclamation-circle",
      title: `${a.missing.length} lô có tiền xe ngoài nhưng thiếu thông tin`,
      sub: "Thiếu Nhà xe ngoài hoặc Giờ xe đến nên không hiện khi tạo bảng kê — bổ sung ở trang Lô hàng",
      rows: a.missing.map((m, i) => (
        <div key={i} style={rowSt}><b className="tnum">{m.booking || "—"}</b><span style={{ flex: 1, color: "var(--ink-3)" }}>{m.reason}</span><span className="tnum">{fmtVND(m.amount)}</span></div>)),
    },
  ].filter(Boolean);
  if (!groups.length) {
    return (
      <div style={{ display: "flex", alignItems: "center", gap: 8, padding: "10px 14px", marginBottom: 14, background: "var(--good-weak, #e3f5ec)", border: "1px solid #bfe5d0", borderRadius: 10, fontSize: 13, color: "var(--good)" }}>
        <i className="bi bi-check-circle" /> Không có cảnh báo — mọi lô xe ngoài đã lên bảng kê, công nợ khớp.
      </div>
    );
  }
  const TONE = { danger: ["var(--danger)", "#fdecec", "#f5c7c7"], warn: ["var(--warn)", "#fffaf0", "#f3e2bf"], info: ["var(--accent)", "var(--accent-weak-2)", "var(--accent-weak)"] };
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 8, marginBottom: 16 }}>
      <div style={{ fontSize: 12, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.04em" }}>Cần chú ý ({groups.length})</div>
      {groups.map((g) => {
        const [fg, bg, bd] = TONE[g.tone];
        const isOpen = !!open[g.key];
        return (
          <div key={g.key} style={{ background: bg, border: "1px solid " + bd, borderRadius: 10, overflow: "hidden" }}>
            <button type="button" onClick={() => setOpen((o) => ({ ...o, [g.key]: !o[g.key] }))}
              style={{ width: "100%", display: "flex", alignItems: "center", gap: 10, padding: "10px 14px", background: "transparent", border: "none", cursor: "pointer", textAlign: "left" }}>
              <i className={"bi " + g.icon} style={{ color: fg, fontSize: 16 }} />
              <span style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontWeight: 700, fontSize: 13.5, color: "var(--ink)" }}>{g.title}</div>
                <div style={{ fontSize: 12, color: "var(--ink-3)", marginTop: 1 }}>{g.sub}</div>
              </span>
              <span style={{ fontSize: 12, color: fg, fontWeight: 600, whiteSpace: "nowrap" }}>{isOpen ? "Thu gọn" : "Xem " + g.rows.length}</span>
            </button>
            {isOpen && <div style={{ background: "#fff", borderTop: "1px solid " + bd, padding: "4px 14px" }}>{g.rows}</div>}
          </div>
        );
      })}
    </div>
  );
}
const rowSt = { display: "flex", alignItems: "center", gap: 10, flexWrap: "wrap", padding: "8px 0", borderBottom: "1px solid var(--line-2)", fontSize: 13 };

/* ============================================================
 * Danh sách bảng kê xe ngoài (theo nhà xe) — Tổng / Đã trả / Còn nợ.
 * ============================================================ */
function ExtKePage({ ke, onNew, onOpen, alerts, createUrl }) {
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
        {alerts && <ExtAlerts alerts={alerts} createUrl={createUrl} onOpenHash={(h) => onOpen({ hashid: h })} />}
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
                  <div className="tnum" style={{ color: "var(--ink-4)", fontSize: 12, marginTop: 2 }}>{(st.from || st.to) ? `Giờ xe đến: ${fmtDate(st.from) || "…"} – ${fmtDate(st.to) || "…"}` : "—"} · {st.count} lô · <PaidProgress n={st.paidCount} total={st.count} /></div>
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
            <div>Số bảng kê</div><div>Nhà xe</div><div>Ngày lập</div><div>Kỳ (Giờ xe đến)</div><div style={{ textAlign: "right" }}>Số lô</div><div style={{ textAlign: "right" }}>Tổng (cước+chi hộ)</div><div style={{ textAlign: "right" }}>Đã trả</div><div style={{ textAlign: "right" }}>Còn nợ</div>
          </div>
          {ke.length === 0 && <div style={{ padding: "44px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5 }}>Chưa có bảng kê xe ngoài nào. Bấm “Tạo bảng kê mới” để bắt đầu.</div>}
          {ke.map((st, i, arr) => {
            const n = arr.length;
            const con = (st.conNo != null) ? st.conNo : (st.total - st.paid);
            return (
            <button key={st.id} type="button" onClick={() => onOpen(st)}
              style={{ width: "100%", textAlign: "left", display: "grid", gridTemplateColumns: cols, gap: 12, alignItems: "center", padding: "12px 16px", background: "transparent", border: "none", borderBottom: i < n - 1 ? "1px solid var(--line-2)" : "none", cursor: "pointer", fontSize: 13.5 }}
              onMouseEnter={(e) => (e.currentTarget.style.background = "var(--accent-weak-2)")}
              onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}>
              <span className="tnum" style={{ fontWeight: 600, color: "var(--accent)" }}>{st.no}</span>
              <span style={{ fontWeight: 500 }}>{st.vendor}</span>
              <span className="tnum" style={{ color: "var(--ink-2)" }}>{fmtDate(st.date)}</span>
              <span className="tnum" style={{ color: "var(--ink-3)", fontSize: 12.5 }}>{(st.from || st.to) ? `${fmtDate(st.from) || "…"} – ${fmtDate(st.to) || "…"}` : "—"}</span>
              <span className="tnum" style={{ textAlign: "right", color: "var(--ink-3)" }}>{st.count}<div style={{ fontSize: 11 }}><PaidProgress n={st.paidCount} total={st.count} /></div></span>
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

/* ---------- Ô tích "đã chi" dùng chung cho trang Tạo + Xem ---------- */
const chkStyle = (busy, sm) => ({ width: sm ? 14 : 16, height: sm ? 14 : 16, accentColor: "var(--good)", cursor: busy ? "wait" : "pointer", margin: sm ? 0 : undefined, flexShrink: 0 });
const paidTone = (l, k) => (paidOf(l)[k] ? "var(--good)" : undefined);

/* Ô tích 1 phần (cước / 1 khoản chi hộ) — chỉ hiện trên màn hình, không in. */
function PartChk({ l, k, title, onPaid, busy }) {
  const d = paidOf(l)[k];
  return <input type="checkbox" className="ke-noprint" checked={!!d} disabled={busy} title={d ? "Đã chi " + fmtDate(d) + " — bấm để bỏ" : "Tích đã chi: " + title}
    onChange={(e) => onPaid([l], e.target.checked, k)} style={chkStyle(busy, true)} />;
}

/* Ô cột "Đã chi" của 1 lô: tích cả lô; dở dang → indeterminate + "x/y khoản". */
function LotPaidCell({ l, onPaid, busy }) {
  const ps = partsOf(l), n = ps.length, done = ps.filter((x) => paidOf(l)[x.k]).length;
  const full = n > 0 && done === n, partial = done > 0 && !full;
  return (
    <td className="ke-noprint" style={{ textAlign: "center", padding: "8px 6px", borderBottom: "1px solid var(--line-2)", verticalAlign: "top" }}>
      <input type="checkbox" checked={full} ref={(el) => { if (el) el.indeterminate = partial; }} disabled={busy || !n}
        onChange={(e) => onPaid([l], e.target.checked, null)} title={full ? "Bỏ đánh dấu đã chi cả lô" : "Tích đã chi tất cả khoản của lô"} style={{ ...chkStyle(busy), marginTop: 2 }} />
      {full && l.paidAt && <div className="tnum" style={{ fontSize: 10, color: "var(--good)", marginTop: 2 }}>{fmtDate(l.paidAt)}</div>}
      {partial && <div className="tnum" style={{ fontSize: 10, color: "var(--warn)", marginTop: 2, whiteSpace: "nowrap" }}>{done}/{n} khoản</div>}
    </td>
  );
}

/* Nội dung ô Cước: số tiền + ô tích (lô không có cước → "—"). */
function FeeBody({ l, onPaid, busy }) {
  if ((l.fee || 0) + (l.vat || 0) === 0) return <span title="Lô không có cước thuê xe ngoài — chỉ có chi hộ" style={{ color: "var(--ink-4)", fontWeight: 400 }}>—</span>;
  return (
    <span style={{ display: "inline-flex", alignItems: "center", gap: 6, color: onPaid ? paidTone(l, "fee") : undefined }}>
      {onPaid && <PartChk l={l} k="fee" title="cước" onPaid={onPaid} busy={busy} />}{fmtNum(l.fee)}
    </span>
  );
}

/* Nội dung ô Chi hộ: 1 khoản → hiện thẳng (không lặp tổng); nhiều khoản → tổng + từng khoản, mỗi khoản 1 ô tích. */
function ChohoBody({ l, onPaid, busy }) {
  const items = (l.chohoItems || []).filter((it) => Math.round(it.amount || 0));
  if (onPaid && items.length === 1) {
    const it = items[0], k = "i:" + it.key;
    return (
      <div style={{ display: "flex", alignItems: "center", justifyContent: "flex-end", gap: 6, color: paidTone(l, k) || "var(--ink-2)" }}>
        <span style={{ fontSize: 10.5, color: paidTone(l, k) || "var(--ink-4)" }}>{it.item}</span>
        <span className="tnum">{fmtNum(it.amount)}</span>
        <PartChk l={l} k={k} title={it.item} onPaid={onPaid} busy={busy} />
      </div>
    );
  }
  return (
    <>
      <div className="tnum" style={{ color: l.choho ? "var(--ink-2)" : "var(--ink-4)" }}>{l.choho ? fmtNum(l.choho) : "—"}</div>
      {onPaid && items.length > 1
        ? items.map((it) => (
            <div key={it.key} style={{ display: "flex", alignItems: "center", justifyContent: "flex-end", gap: 5, fontSize: 10.5, color: paidTone(l, "i:" + it.key) || "var(--ink-4)", lineHeight: 1.3, marginTop: 2 }}>
              <span>{it.item} <span className="tnum">{fmtNum(it.amount)}</span></span>
              <PartChk l={l} k={"i:" + it.key} title={it.item} onPaid={onPaid} busy={busy} />
            </div>))
        : (l.chohoNote && <div style={{ fontSize: 10.5, color: "var(--ink-4)", lineHeight: 1.3, marginTop: 1 }}>{l.chohoNote}</div>)}
    </>
  );
}

/* Ô tích "Đã chi" ở tiêu đề: tích / bỏ cả các lô đang hiện. */
function AllPaidTh({ lines, onPaid, busy }) {
  const all = lines.length > 0 && lines.every(isFull);
  const some = !all && lines.some((l) => paidAmtOf(l) > 0);
  return (
    <th className="ke-noprint" title="Tích / bỏ tích đã chi cho tất cả lô đang hiện" style={{ width: 62, padding: "9px 6px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>
      <label style={{ display: "inline-flex", flexDirection: "column", alignItems: "center", gap: 3, cursor: "pointer" }}>
        <input type="checkbox" checked={all} ref={(el) => { if (el) el.indeterminate = some; }} disabled={busy || !lines.length}
          onChange={(e) => onPaid(lines, e.target.checked, null)} style={chkStyle(busy)} />Đã chi
      </label>
    </th>
  );
}

/* ============================================================
 * Thanh tổng hợp ĐÃ CHI / CÒN PHẢI CHI — dính đáy màn hình, cập nhật ngay khi tích.
 * payTotal (trang Xem): tổng các đợt thanh toán đã nhập → so với số đã tích, gợi ý ghi đợt chênh lệch.
 * ============================================================ */
function PaidSummary({ lines, payTotal, onAddPayment, children }) {
  const total = lines.reduce((a, l) => a + lineSum(l), 0);
  let paidFee = 0, paidChoho = 0, nParts = 0, nDone = 0;
  lines.forEach((l) => partsOf(l).forEach((x) => {
    nParts++;
    if (!paidOf(l)[x.k]) return;
    nDone++;
    if (x.k === "fee") paidFee += x.amt; else paidChoho += x.amt;
  }));
  const paidAmt = paidFee + paidChoho;
  const remain = total - paidAmt;
  const pct = total ? Math.round(paidAmt * 100 / total) : 0;
  const nFull = lines.filter(isFull).length;
  const gap = payTotal != null ? paidAmt - payTotal : 0;   // >0: đã tích nhưng chưa ghi đợt thanh toán
  const stat = (label, value, tone, sub) => (
    <div style={{ minWidth: 0 }}>
      <div style={{ fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.04em", fontWeight: 600 }}>{label}</div>
      <div className="tnum" style={{ fontSize: 17, fontWeight: 700, color: tone || "var(--ink)", letterSpacing: "-0.01em", whiteSpace: "nowrap" }}>{fmtVND(value)}</div>
      {sub && <div className="tnum" style={{ fontSize: 11, color: "var(--ink-4)", whiteSpace: "nowrap" }}>{sub}</div>}
    </div>
  );
  return (
    <div className="ke-noprint" style={{ position: "sticky", bottom: 0, zIndex: 5, background: "#fff", borderTop: "1px solid var(--line)", boxShadow: "0 -6px 16px rgba(16,19,23,.06)", padding: "12px 4px 12px", marginTop: 12 }}>
      <div style={{ display: "flex", alignItems: "center", gap: 22, flexWrap: "wrap" }}>
        {stat("Tổng phải chi", total, null, `${lines.length} lô`)}
        {stat("Đã chi (đã tích)", paidAmt, "var(--good)", [paidFee ? "cước " + fmtNum(paidFee) : "", paidChoho ? "chi hộ " + fmtNum(paidChoho) : ""].filter(Boolean).join(" · ") || `${nDone}/${nParts} khoản`)}
        {stat("Còn phải chi", remain, remain > 0 ? "var(--warn)" : "var(--good)", remain > 0 ? `${lines.length - nFull} lô chưa chi đủ` : (total ? "Đã chi đủ" : ""))}
        <div style={{ flex: "1 1 180px", minWidth: 160 }}>
          <div style={{ display: "flex", justifyContent: "space-between", fontSize: 11.5, color: "var(--ink-3)", marginBottom: 5 }}>
            <span>Tiến độ chi</span><b className="tnum" style={{ color: pct >= 100 ? "var(--good)" : "var(--ink-2)" }}>{pct}%</b>
          </div>
          <div style={{ height: 8, background: "var(--line-2)", borderRadius: 999, overflow: "hidden" }}>
            <div style={{ width: Math.min(100, pct) + "%", height: "100%", background: "var(--good)", borderRadius: 999, transition: "width .2s" }} />
          </div>
          <div className="tnum" style={{ fontSize: 11, color: "var(--ink-4)", marginTop: 4 }}>{nFull}/{lines.length} lô chi đủ · {nDone}/{nParts} khoản đã tích</div>
        </div>
        {children}
      </div>
      {payTotal != null && gap !== 0 && (paidAmt > 0 || payTotal > 0) && (
        <div style={{ display: "flex", alignItems: "center", gap: 10, flexWrap: "wrap", marginTop: 10, padding: "8px 11px", background: "#fffaf0", border: "1px solid #f3e2bf", borderRadius: 9, fontSize: 12.5, color: "var(--ink-2)" }}>
          <i className="bi bi-lightbulb" style={{ color: "var(--warn)" }} />
          <span style={{ flex: 1, minWidth: 200 }}>
            {gap > 0
              ? <>Đã tích chi <b className="tnum">{fmtVND(paidAmt)}</b> nhưng các đợt thanh toán mới ghi <b className="tnum">{fmtVND(payTotal)}</b> — thiếu <b className="tnum" style={{ color: "var(--warn)" }}>{fmtVND(gap)}</b>.</>
              : <>Các đợt thanh toán đã ghi <b className="tnum">{fmtVND(payTotal)}</b> nhiều hơn số đã tích <b className="tnum">{fmtVND(paidAmt)}</b> là <b className="tnum" style={{ color: "var(--warn)" }}>{fmtVND(-gap)}</b> — có thể còn khoản đã trả nhưng chưa tích.</>}
          </span>
          {gap > 0 && onAddPayment && (
            <button type="button" onClick={() => onAddPayment(gap)}
              style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "6px 11px", fontSize: 12.5, fontWeight: 600, border: "none", borderRadius: 7, background: "var(--accent)", color: "#fff", cursor: "pointer", whiteSpace: "nowrap" }}>
              <I.plus /> Ghi đợt thanh toán {fmtNum(gap)}
            </button>
          )}
        </div>
      )}
    </div>
  );
}

/* Header + bảng dòng (in được) — dùng chung cho tạo + xem. */
function ExtLinesTable({ vendor, no, date, from, to, lines, footerTotal, onPaid, busy }) {
  const th = (txt, align, w) => <th style={{ textAlign: align || "left", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase", width: w }}>{txt}</th>;
  const sumFee = lines.reduce((a, l) => a + (l.fee || 0), 0);
  const sumVat = lines.reduce((a, l) => a + (l.vat || 0), 0);
  const sumChoho = lines.reduce((a, l) => a + (l.choho || 0), 0);
  const hasChoho = sumChoho > 0;
  const hasVat = sumVat > 0;
  const nCols = 5 + (hasVat ? 1 : 0) + (hasChoho ? 1 : 0) + (onPaid ? 1 : 0);   // [Đã chi], #, Lô, Cước, [VAT], [Chi hộ], Tổng
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
        <thead><tr style={{ background: "#fafbfc" }}>
          {onPaid && <AllPaidTh lines={lines} onPaid={onPaid} busy={busy} />}
          {th("#", "center", 30)}{th("Lô / Khách hàng")}{th("Cước", "right")}{hasVat && th("VAT", "right")}{hasChoho && th("Chi hộ", "right")}{th("Tổng", "right")}</tr></thead>
        <tbody>
          {lines.length === 0 && <tr><td colSpan={nCols} style={{ padding: "20px", textAlign: "center", color: "var(--ink-4)" }}>Chưa có dòng nào.</td></tr>}
          {lines.map((l, i) => {
            const route = [l.from, l.to].filter(Boolean).join(" → ");
            const meta = [l.sheet, l.contLabel, l.bks].filter(Boolean).join(" · ");
            const full = isFull(l), partial = !full && paidAmtOf(l) > 0;
            return (
            <tr key={lineKey(l)} style={{ background: onPaid && full ? "#f3faf6" : undefined }}>
              {onPaid && <LotPaidCell l={l} onPaid={onPaid} busy={busy} />}
              <td className="tnum" style={{ textAlign: "center", padding: "8px 6px", borderBottom: "1px solid var(--line-2)", color: "var(--ink-4)", verticalAlign: "top" }}>{l._n || i + 1}</td>
              <td style={{ padding: "8px", borderBottom: "1px solid var(--line-2)" }}>
                <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
                  <span style={{ fontWeight: 700 }} className="tnum">{l.booking || "—"}</span>
                  <IoBadge io={l.io} />
                  {l.customer && <span style={{ fontSize: 12, color: "var(--accent)", fontWeight: 600 }}>{l.customer}</span>}
                </div>
                {meta && <div style={{ fontSize: 11, color: "var(--ink-4)", marginTop: 1 }}>{meta}</div>}
                <div style={{ fontSize: 11, color: "var(--ink-4)", marginTop: 1 }}>{fmtDate(l.date) || "—"}{route ? " · " + route : ""}</div>
              </td>
              <td className="tnum" style={{ textAlign: "right", padding: "8px", borderBottom: "1px solid var(--line-2)", fontWeight: 600, verticalAlign: "top" }}>
                <FeeBody l={l} onPaid={onPaid} busy={busy} />
              </td>
              {hasVat && <td style={{ textAlign: "right", padding: "8px", borderBottom: "1px solid var(--line-2)", verticalAlign: "top" }}>
                <div className="tnum" style={{ color: l.vat ? "var(--ink-2)" : "var(--ink-4)" }}>{l.vat ? fmtNum(l.vat) : "—"}</div>
                {l.vatRate > 0 && <div style={{ fontSize: 10.5, color: "var(--ink-4)" }}>{l.vatRate}%</div>}
              </td>}
              {hasChoho && <td style={{ textAlign: "right", padding: "8px", borderBottom: "1px solid var(--line-2)", verticalAlign: "top" }}>
                <ChohoBody l={l} onPaid={onPaid} busy={busy} />
              </td>}
              <td className="tnum" style={{ textAlign: "right", padding: "8px", borderBottom: "1px solid var(--line-2)", fontWeight: 700, verticalAlign: "top" }}>
                {fmtNum(lineSum(l))}
                {onPaid && partial && <div className="ke-noprint" style={{ fontSize: 10.5, fontWeight: 600, color: "var(--warn)", marginTop: 2 }}>còn {fmtNum(lineSum(l) - paidAmtOf(l))}</div>}
              </td>
            </tr>
          );})}
        </tbody>
        <tfoot>
          <tr style={{ fontWeight: 700 }}>
            {onPaid && <td className="ke-noprint" style={{ borderTop: "1.5px solid var(--line)" }}></td>}
            <td colSpan={2} style={{ padding: "9px 8px", borderTop: "1.5px solid var(--line)", textAlign: "right", color: "var(--ink-3)" }}>TỔNG</td>
            <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{fmtNum(sumFee)}</td>
            {hasVat && <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{fmtNum(sumVat)}</td>}
            {hasChoho && <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{fmtNum(sumChoho)}</td>}
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
  const qs = new URLSearchParams(window.location.search);   // link "Tạo bảng kê" từ cảnh báo điền sẵn
  const [vendor, setVendor] = useState(() => (vendors.includes(qs.get("vendor")) ? qs.get("vendor") : (vendors[0] || "")));
  const [from, setFrom] = useState(() => qs.get("from") || "");
  const [to, setTo] = useState(() => qs.get("to") || "");
  const [picked, setPicked] = useState({});
  const [all, setAll] = useState([]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [vatDef, setVatDef] = useState(0);     // % VAT mặc định (áp cho mọi lô)
  const [vatOv, setVatOv] = useState({});      // ghi đè VAT theo từng lô {id: rate}
  const [paidMap, setPaidMap] = useState({});  // dấu đã chi theo phần {id lô: {fee|i:<key>: ngày}} — lưu cùng bảng kê
  const today = new Date().toISOString().slice(0, 10);
  const needDate = !!vendor && !from && !to;
  const rateOf = (x) => (vatOv[x.id] != null ? vatOv[x.id] : vatDef);
  const vatOf = (x) => Math.round((x.fee || 0) * rateOf(x) / 100);   // VAT chỉ trên cước
  const lineTotal = (x) => (x.fee || 0) + vatOf(x) + (x.choho || 0);

  useEffect(() => {
    if (!vendor || (!from && !to)) { setAll([]); setLoading(false); return; }
    let alive = true; setLoading(true); setPicked({}); setPaidMap({});
    const p = new URLSearchParams({ vendor }); if (from) p.set("from", from); if (to) p.set("to", to);
    window.trkApi("GET", ROUTES_TRK.extStatementCandidates + "?" + p.toString())
      .then((r) => { if (alive) { setAll(r && r.ok ? (r.candidates || []) : []); setLoading(false); } })
      .catch(() => { if (alive) { setAll([]); setLoading(false); } });
    return () => { alive = false; };
  }, [vendor, from, to]);

  // Dòng đầy đủ để tích chi / tổng hợp: VAT theo lựa chọn hiện tại + dấu đã chi.
  const rowOf = (x) => ({ ...x, vatRate: rateOf(x), vat: vatOf(x), paidParts: paidMap[x.id] || {} });
  const sel = all.filter((x) => picked[x.id] !== false);
  const selRows = sel.map(rowOf);
  const togglePaid = (ls, on, part) => setPaidMap((m) => {
    const nm = { ...m };
    ls.forEach((l) => { nm[l.id] = withPaid(l, on, part).paidParts; });
    return nm;
  });
  const totalFee = sel.reduce((a, x) => a + (x.fee || 0), 0);
  const totalVat = sel.reduce((a, x) => a + vatOf(x), 0);
  const totalChoho = sel.reduce((a, x) => a + (x.choho || 0), 0);
  const total = totalFee + totalVat + totalChoho;
  const keNo = "BKN-" + today.replace(/-/g, "").slice(2) + "-" + (vendor ? vendor.replace(/[^A-Za-zÀ-ỹ0-9]/g, "").slice(0, 3).toUpperCase() : "XXX");

  const save = async () => {
    if (!sel.length || saving) return;
    setSaving(true);
    const lines = sel.map((x) => ({
      id: x.id, booking: x.booking, customer: x.customer, io: x.io, sheet: x.sheet, bks: x.bks,
      from: x.from, to: x.to, contLabel: x.contLabel, date: x.date, fee: x.fee, choho: x.choho, chohoNote: x.chohoNote, chohoItems: x.chohoItems, vatRate: rateOf(x), note: x.note, paidParts: paidMap[x.id] || {},
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
        <div style={{ display: "block" }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>VAT mặc định</div>
          <select value={vatDef} onChange={(e) => { setVatDef(Number(e.target.value)); setVatOv({}); }}
            style={{ height: 38, padding: "0 10px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, background: "#fff", cursor: "pointer" }}>
            {[0, 8, 10].map((r) => <option key={r} value={r}>{r}%</option>)}
          </select>
        </div>
        <div style={{ flex: 1 }} />
        <div style={{ fontSize: 12, color: "var(--ink-4)" }}>{all.length} lô</div>
      </div>

      <div className="ke-noprint" style={{ fontSize: 11.5, color: "var(--ink-4)", padding: "8px 0 0", display: "flex", alignItems: "flex-start", gap: 6, lineHeight: 1.5 }}>
        <i className="bi bi-info-circle" style={{ marginTop: 1 }} />
        <span>Lọc theo <b style={{ color: "var(--ink-3)" }}>Giờ xe đến</b>. Hiện lô có <b style={{ color: "var(--ink-3)" }}>cước thuê xe ngoài</b> hoặc khoản chi phí tích <b style={{ color: "var(--ink-3)" }}>Chi hộ xe ngoài</b> (cột Chi hộ). <b style={{ color: "var(--ink-3)" }}>VAT</b> chỉ áp lên cước (chi hộ không VAT); chọn VAT mặc định ở trên hoặc sửa từng lô. Tổng = cước + VAT + chi hộ.</span>
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
              <th className="ke-noprint" title="Đưa lô vào bảng kê" style={{ width: 34, padding: "9px 8px", borderBottom: "1.5px solid var(--line)" }}></th>
              <AllPaidTh lines={selRows} onPaid={togglePaid} busy={false} />
              <th style={{ width: 30, textAlign: "center", padding: "9px 6px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)" }}>#</th>
              <th style={{ textAlign: "left", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Lô / Khách hàng</th>
              <th style={{ textAlign: "right", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Cước</th>
              <th style={{ textAlign: "right", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase", width: 78 }}>VAT</th>
              <th style={{ textAlign: "right", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Chi hộ</th>
              <th style={{ textAlign: "right", padding: "9px 8px", borderBottom: "1.5px solid var(--line)", fontSize: 11, color: "var(--ink-3)", textTransform: "uppercase" }}>Tổng</th>
            </tr>
          </thead>
          <tbody>
            {!loading && all.length === 0 && <tr><td colSpan={8} style={{ padding: "28px 24px", textAlign: "center", color: "var(--ink-4)" }}>
              {needDate
                ? <span style={{ display: "inline-flex", flexDirection: "column", alignItems: "center", gap: 6 }}>
                    <i className="bi bi-calendar-range" style={{ fontSize: 26, color: "var(--accent)", opacity: .8 }} />
                    <b style={{ color: "var(--ink-2)", fontSize: 13.5 }}>Vui lòng chọn khoảng Giờ xe đến</b>
                    <span style={{ fontSize: 12.5 }}>Chọn <b>từ ngày</b> (và đến ngày) ở trên để lọc lô.</span>
                  </span>
                : (vendor ? "Không có lô nào phù hợp trong kỳ đã chọn." : "Chọn nhà xe để bắt đầu.")}
            </td></tr>}
            {loading && <tr><td colSpan={8} style={{ padding: "24px", textAlign: "center", color: "var(--ink-4)" }}>Đang tải lô…</td></tr>}
            {!loading && all.map((x, i) => {
              const on = picked[x.id] !== false;
              const row = rowOf(x);
              const route = [x.from, x.to].filter(Boolean).join(" → ");
              const meta = [x.sheet, x.contLabel, x.bks].filter(Boolean).join(" · ");
              return (
                <tr key={x.id} style={{ opacity: on ? 1 : 0.4, background: on && isFull(row) ? "#f3faf6" : undefined }}>
                  <td className="ke-noprint" style={{ textAlign: "center", padding: "8px", borderBottom: "1px solid var(--line-2)", verticalAlign: "top" }}>
                    <input type="checkbox" checked={on} title="Đưa lô vào bảng kê" onChange={(e) => setPicked((p) => ({ ...p, [x.id]: e.target.checked }))} style={{ width: 16, height: 16, accentColor: "var(--accent)", cursor: "pointer", marginTop: 2 }} />
                  </td>
                  {on ? <LotPaidCell l={row} onPaid={togglePaid} busy={false} />
                    : <td className="ke-noprint" style={{ borderBottom: "1px solid var(--line-2)" }}></td>}
                  <td className="tnum" style={{ textAlign: "center", padding: "8px 6px", borderBottom: "1px solid var(--line-2)", color: "var(--ink-4)", verticalAlign: "top" }}>{i + 1}</td>
                  <td style={{ padding: "8px", borderBottom: "1px solid var(--line-2)" }}>
                    <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
                      <span style={{ fontWeight: 700 }} className="tnum">{x.booking || "—"}</span>
                      <IoBadge io={x.io} />
                      {x.customer && <span style={{ fontSize: 12, color: "var(--accent)", fontWeight: 600 }}>{x.customer}</span>}
                    </div>
                    {meta && <div style={{ fontSize: 11, color: "var(--ink-4)", marginTop: 1 }}>{meta}</div>}
                    <div style={{ fontSize: 11, color: "var(--ink-4)", marginTop: 1 }}>{fmtDate(x.date) || "—"}{route ? " · " + route : ""}</div>
                  </td>
                  <td className="tnum" style={{ textAlign: "right", padding: "8px", borderBottom: "1px solid var(--line-2)", fontWeight: 600, verticalAlign: "top" }}><FeeBody l={row} onPaid={on ? togglePaid : null} /></td>
                  <td style={{ textAlign: "right", padding: "8px", borderBottom: "1px solid var(--line-2)", verticalAlign: "top" }}>
                    <select className="ke-noprint" value={rateOf(x)} onChange={(e) => setVatOv((p) => ({ ...p, [x.id]: Number(e.target.value) }))}
                      style={{ width: 56, padding: "3px 4px", fontSize: 12, border: "1px solid var(--line)", borderRadius: 6, background: "#fff", cursor: "pointer", textAlign: "right" }}>
                      {[0, 8, 10].map((r) => <option key={r} value={r}>{r}%</option>)}
                    </select>
                    {vatOf(x) > 0 && <div className="tnum" style={{ fontSize: 10.5, color: "var(--ink-4)", marginTop: 2 }}>{fmtNum(vatOf(x))}</div>}
                  </td>
                  <td style={{ textAlign: "right", padding: "8px", borderBottom: "1px solid var(--line-2)", verticalAlign: "top" }}>
                    <ChohoBody l={row} onPaid={on ? togglePaid : null} />
                  </td>
                  <td className="tnum" style={{ textAlign: "right", padding: "8px", borderBottom: "1px solid var(--line-2)", fontWeight: 700, verticalAlign: "top" }}>{fmtNum(lineTotal(x))}</td>
                </tr>
              );
            })}
          </tbody>
          <tfoot>
            <tr style={{ fontWeight: 700 }}>
              <td className="ke-noprint" colSpan={2} style={{ borderTop: "1.5px solid var(--line)" }}></td>
              <td colSpan={2} style={{ padding: "9px 8px", borderTop: "1.5px solid var(--line)", textAlign: "right", color: "var(--ink-3)" }}>TỔNG ({sel.length} lô)</td>
              <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{fmtNum(totalFee)}</td>
              <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{totalVat ? fmtNum(totalVat) : "—"}</td>
              <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{totalChoho ? fmtNum(totalChoho) : "—"}</td>
              <td className="tnum" style={{ textAlign: "right", padding: "9px 8px", borderTop: "1.5px solid var(--line)" }}>{fmtVND(total)}</td>
            </tr>
          </tfoot>
        </table>

        <div className="ke-noprint" style={{ marginTop: 14, fontSize: 12.5, color: "var(--ink-4)" }}>Sau khi lưu, mở bảng kê để nhập các đợt thanh toán cho nhà xe và theo dõi công nợ.</div>
      </div>

      <PaidSummary lines={selRows}>
        <div style={{ display: "flex", gap: 10, marginLeft: "auto" }}>
          <Btn onClick={onCancel}>Hủy</Btn>
          <Btn onClick={() => window.print()}>In thử</Btn>
          <Btn variant="primary" onClick={save} disabled={saving || !sel.length}>{saving ? "Đang lưu…" : "Lưu bảng kê"}</Btn>
        </div>
      </PaidSummary>
    </div>
  );
}

/* ============================================================
 * Trang xem bảng kê xe ngoài đã lưu: dòng + thanh toán + công nợ.
 * ============================================================ */
function SavedExtStatementPage({ st, onUpdate, onSave, onDelete, onLinesPaid, isDirty, backUrl }) {
  const [paidFilter, setPaidFilter] = useState("all");
  const [paidBusy, setPaidBusy] = useState(false);
  const [rFrom, setRFrom] = useState(st.from || "");
  const [rTo, setRTo] = useState(st.to || "");
  const [refreshing, setRefreshing] = useState(false);
  const [payments, setPayments] = useState(st.payments || []);
  const sync = (arr) => { setPayments(arr); onUpdate && onUpdate({ ...st, payments: arr }); };
  const setP = (id, np) => sync(payments.map((p) => (p.id === id ? { ...p, ...np } : p)));
  const addP = () => sync([...payments, { id: Date.now() + Math.random(), date: new Date().toISOString().slice(0, 10), amount: "", note: "" }]);
  const delP = (id) => sync(payments.filter((p) => p.id !== id));
  const grp = (d) => { d = (d || "").toString().replace(/[^\d]/g, ""); return d ? d.replace(/\B(?=(\d{3})+(?!\d))/g, ".") : ""; };

  const total = (st.lines || []).reduce((a, l) => a + lineSum(l), 0);   // = tổng server; tính lại sau Làm mới
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

  const allLines = (st.lines || []).map((l, i) => ({ ...l, _n: i + 1 }));
  const shown = allLines.filter((l) => paidFilter === "all" || (paidFilter === "paid" ? isFull(l) : !isFull(l)));
  const shownTotal = paidFilter === "all" ? total : shown.reduce((a, l) => a + lineSum(l), 0);
  // Tích chi: bảng kê đang có thay đổi chưa lưu (vd. vừa Làm mới) → sửa tại chỗ, lưu cùng nút Lưu; còn lại ghi ngay (PATCH).
  const togglePaid = (ls, on, part) => {
    const targets = part ? ls : ls.filter((l) => isFull(l) !== on);
    if (!targets.length || paidBusy) return;
    if (dirty || !onLinesPaid || targets.some((l) => !l.lineId)) {
      const keys = new Set(targets.map(lineKey));
      onUpdate && onUpdate({ ...st, payments, lines: (st.lines || []).map((l) => (keys.has(lineKey(l)) ? withPaid(l, on, part) : l)) });
      return;
    }
    setPaidBusy(true);
    Promise.resolve(onLinesPaid(targets.map((l) => l.lineId), on, part)).finally(() => setPaidBusy(false));
  };

  // Làm mới: lấy lại lô + chi phí mới nhất của nhà xe trong khoảng ngày (có thể sửa), giữ VAT/ghi chú/dấu đã chi của lô cũ.
  const refresh = async () => {
    if (refreshing) return;
    if (!rFrom && !rTo) { window.trkToast && window.trkToast("Chọn khoảng Giờ xe đến trước khi làm mới", "error"); return; }
    setRefreshing(true);
    let cands = null;
    try {
      const q = new URLSearchParams({ vendor: st.vendor || "" }); if (rFrom) q.set("from", rFrom); if (rTo) q.set("to", rTo);
      const r = await window.trkApi("GET", ROUTES_TRK.extStatementCandidates + "?" + q.toString());
      if (r && r.ok) cands = r.candidates || [];
    } catch (e) { /* báo lỗi dưới */ }
    setRefreshing(false);
    if (!cands) { window.trkToast && window.trkToast("Không tải được dữ liệu lô", "error"); return; }

    const oldLines = st.lines || [];
    const old = new Map(oldLines.map((l) => [String(l.id), l]));
    const cnt = {}; oldLines.forEach((l) => { const r = l.vatRate || 0; cnt[r] = (cnt[r] || 0) + 1; });
    const rateDef = Number(Object.keys(cnt).sort((a, b) => cnt[b] - cnt[a])[0] || 0);   // VAT hay dùng nhất cho lô mới
    let added = 0, changed = 0;
    const next = cands.map((c) => {
      const o = old.get(String(c.id));
      const rate = o ? (o.vatRate || 0) : rateDef;
      const ln = { ...c, lineId: o ? o.lineId : undefined, vatRate: rate, vat: Math.round((c.fee || 0) * rate / 100), note: (o && o.note) || c.note || "", paidParts: {}, paidAt: "" };
      if (!o) { added++; return ln; }
      if (lineSum(o) !== lineSum(ln) || (o.chohoItems || []).length !== (ln.chohoItems || []).length) changed++;
      // Giữ dấu đã chi theo phần còn tồn tại; dòng cũ chỉ có 1 khoản "Chi hộ" gộp → áp cho mọi khoản chi hộ mới.
      const po = paidOf(o), pp = {};
      partsOf(ln).forEach((x) => { const d = po[x.k] || (x.k !== "fee" && po["i:choho"]); if (d) pp[x.k] = d; });
      const ps = partsOf(ln);
      return { ...ln, paidParts: pp, paidAt: ps.length && ps.every((x) => pp[x.k]) ? ps.map((x) => pp[x.k]).sort().pop() : "" };
    });
    const ids = new Set(cands.map((c) => String(c.id)));
    const removed = oldLines.filter((l) => !ids.has(String(l.id)));
    const removedPaid = removed.filter((l) => paidAmtOf(l) > 0).length;
    const newTotal = next.reduce((a, l) => a + lineSum(l), 0);
    if (!added && !changed && !removed.length && rFrom === (st.from || "") && rTo === (st.to || "")) {
      window.trkToast && window.trkToast("Dữ liệu đã là mới nhất"); return;
    }
    const ok = await window.confirmAction({
      title: "Cập nhật dữ liệu bảng kê?",
      text: `Giờ xe đến <b>${esc(fmtDate(rFrom) || "…")} – ${esc(fmtDate(rTo) || "…")}</b>: ${next.length} lô.<br>`
        + `Thêm <b>${added}</b> lô · Thay đổi số tiền <b>${changed}</b> lô · Bỏ <b>${removed.length}</b> lô`
        + (removedPaid ? ` <b style="color:#b9750a">(${removedPaid} lô bỏ đi đã có tích chi)</b>` : "")
        + `.<br>Tổng mới: <b>${esc(fmtVND(newTotal))}</b> (cũ ${esc(fmtVND(total))}). Bấm <b>Lưu</b> sau đó để ghi lại.`,
      confirmText: "Cập nhật",
    });
    if (!ok) return;
    onUpdate && onUpdate({ ...st, payments, from: rFrom, to: rTo, lines: next });
  };

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
            <div className="ke-noprint" style={{ display: "flex", alignItems: "flex-end", gap: 10, flexWrap: "wrap", marginBottom: 12, padding: "10px 12px", background: "#fafbfc", border: "1px solid var(--line)", borderRadius: 10 }}>
              <div>
                <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>Giờ xe đến từ ngày</div>
                <div style={{ width: 150 }}><DateField value={rFrom} onChange={setRFrom} /></div>
              </div>
              <div>
                <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>đến ngày</div>
                <div style={{ width: 150 }}><DateField value={rTo} onChange={setRTo} /></div>
              </div>
              <Btn onClick={refresh} disabled={refreshing}><i className="bi bi-arrow-clockwise" /> {refreshing ? "Đang tải…" : "Làm mới dữ liệu"}</Btn>
              <div style={{ flex: "1 1 220px", fontSize: 11.5, color: "var(--ink-4)", lineHeight: 1.45 }}>Lấy lại lô, cước và chi hộ mới nhất của nhà xe trong khoảng ngày; giữ VAT, ghi chú và dấu đã chi của lô cũ. Bấm <b style={{ color: "var(--ink-3)" }}>Lưu</b> để ghi.</div>
            </div>
            <PaidFilter lines={allLines} value={paidFilter} onChange={setPaidFilter} />
            <ExtLinesTable vendor={st.vendor} no={st.no} date={st.date} from={st.from} to={st.to} lines={shown} footerTotal={shownTotal}
              onPaid={onLinesPaid ? togglePaid : null} busy={paidBusy} />
            <PaidSummary lines={allLines} payTotal={paid}
              onAddPayment={(amt) => sync([...payments, { id: Date.now() + Math.random(), date: todayStr(), amount: String(amt), note: "Theo các khoản đã tích chi" }])} />

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
                  <span style={{ fontWeight: 700 }}>TỔNG PHẢI TRẢ</span><b className="tnum">{fmtVND(total)}</b>
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
