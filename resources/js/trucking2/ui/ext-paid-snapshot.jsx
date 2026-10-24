import React from "react";
const { useState, useEffect, useMemo } = React;
import { fmtVND, fmtNum, fmtDate, Btn, DateField, Modal } from "@trk/lib.jsx";

/* ============================================================
 * Ảnh "Các khoản đã chi" của 1 bảng kê xe ngoài theo khoảng NGÀY CHI — gửi kế toán (Zalo/email).
 * Vẽ thẳng bằng Canvas 2D (không thư viện chụp DOM): ảnh nét, không phụ thuộc CSS trang.
 * entries: [{ l: dòng bảng kê, n: số thứ tự, parts: [{ k, label, amt, date }] }] — chỉ các phần ĐÃ tích chi.
 * ============================================================ */

const CO = (window.__TRK && window.__TRK.boot && window.__TRK.boot.company) || {};
const CO_NAME = CO.name || "MBF JOINT STOCK COMPANY";
const CO_SUB = [CO.website, CO.phone].filter(Boolean).join(" · ") || "http://mbf.com.vn · 84-24-39449616";

const ymd = (d) => d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
const inRange = (d, from, to) => !!d && (!from || d >= from) && (!to || d <= to);

/* Lọc các phần đã chi trong khoảng ngày → dòng ảnh (bỏ lô không còn phần nào). */
function rowsIn(entries, from, to) {
  return entries.map((e) => {
    const parts = e.parts.filter((p) => inRange(p.date, from, to));
    const fee = parts.filter((p) => p.k === "fee").reduce((a, p) => a + p.amt, 0);
    const items = parts.filter((p) => p.k !== "fee");
    const choho = items.reduce((a, p) => a + p.amt, 0);
    return { ...e, parts, fee, items, choho, sum: fee + choho, dates: [...new Set(parts.map((p) => p.date))].sort() };
  }).filter((r) => r.parts.length);
}

function cssVar(name, fb) {
  const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  return v || fb;
}

/* Vẽ ảnh; trả về canvas (scale 2 cho nét khi xem trên điện thoại). */
async function drawPaidImage({ st, rows, from, to }) {
  const family = getComputedStyle(document.body).fontFamily || "system-ui, sans-serif";
  try { await Promise.all(["400", "600", "700"].map((w) => document.fonts.load(`${w} 13px ${family}`))); } catch (e) { /* dùng font dự phòng */ }
  const C = {
    ink: cssVar("--ink", "#101317"), ink2: cssVar("--ink-2", "#2b3038"), ink3: cssVar("--ink-3", "#5b636e"), ink4: cssVar("--ink-4", "#8a929c"),
    line: cssVar("--line", "#e3e6ea"), line2: cssVar("--line-2", "#eef0f3"), accent: cssVar("--accent", "#2a6fdb"),
    accentBg: cssVar("--accent-weak-2", "#f2f6fd"), accentBd: cssVar("--accent-weak", "#dce8fa"), good: cssVar("--good", "#1f8a5b"),
  };
  const W = 920, PAD = 28, SCALE = 2, LH = 17;
  const cols = [
    { k: "n", w: 34, align: "center", title: "#" },
    { k: "date", w: 92, title: "Ngày chi" },
    { k: "lot", w: 0, title: "Lô / Khách hàng" },
    { k: "fee", w: 112, align: "right", title: "Cước" },
    { k: "choho", w: 210, align: "right", title: "Chi hộ" },
    { k: "sum", w: 118, align: "right", title: "Cộng" },
  ];
  cols[2].w = W - PAD * 2 - cols.reduce((a, c) => a + c.w, 0);
  let x0 = PAD; cols.forEach((c) => { c.x = x0; x0 += c.w; });

  const mctx = document.createElement("canvas").getContext("2d");
  const font = (w, s) => `${w} ${s}px ${family}`;
  // Cắt chuỗi cho vừa độ rộng (thêm "…").
  const fit = (ctx, s, maxW) => {
    s = String(s == null ? "" : s);
    if (ctx.measureText(s).width <= maxW) return s;
    while (s.length > 1 && ctx.measureText(s + "…").width > maxW) s = s.slice(0, -1);
    return s + "…";
  };

  // Nội dung từng ô (mỗi phần tử = 1 dòng chữ) → tính chiều cao hàng trước khi vẽ.
  const cellLines = (r) => {
    const l = r.l;
    const meta = [l.sheet, l.contLabel, l.bks].filter(Boolean).join(" · ");
    const route = [l.from, l.to].filter(Boolean).join(" → ");
    const sub = [fmtDate(l.date), route].filter(Boolean).join(" · ");
    return {
      n: [{ t: String(r.n), c: C.ink4 }],
      date: r.dates.map((d) => ({ t: fmtDate(d), c: C.ink2 })),
      lot: [{ t: l.booking || "—", w: 700, c: C.ink, extra: l.customer ? { t: l.customer, c: C.accent, w: 600 } : null },
        ...(meta ? [{ t: meta, s: 11, c: C.ink4 }] : []), ...(sub ? [{ t: sub, s: 11, c: C.ink4 }] : [])],
      fee: r.fee ? [{ t: fmtNum(r.fee), w: 600, c: C.ink }, ...(l.vatRate > 0 ? [{ t: `gồm VAT ${l.vatRate}%`, s: 10.5, c: C.ink4 }] : [])] : [{ t: "—", c: C.ink4 }],
      choho: !r.items.length ? [{ t: "—", c: C.ink4 }]
        : r.items.length === 1 ? [{ t: fmtNum(r.items[0].amt), c: C.ink2 }, { t: r.items[0].label, s: 10.5, c: C.ink4 }]
        : [{ t: fmtNum(r.choho), c: C.ink2 }, ...r.items.map((it) => ({ t: `${it.label} ${fmtNum(it.amt)}`, s: 10.5, c: C.ink4 }))],
      sum: [{ t: fmtNum(r.sum), w: 700, c: C.ink }],
    };
  };
  const prepared = rows.map((r) => {
    const cl = cellLines(r);
    const n = Math.max(...Object.values(cl).map((a) => a.length));
    return { r, cl, h: n * LH + 14 };
  });

  const headH = 64, infoH = 46, thH = 32, footH = 40, sumBoxH = 74, tailH = 34;
  const H = PAD + headH + infoH + 14 + thH + prepared.reduce((a, p) => a + p.h, 0) + footH + 16 + sumBoxH + tailH;
  const cv = document.createElement("canvas");
  cv.width = W * SCALE; cv.height = H * SCALE;
  const ctx = cv.getContext("2d");
  ctx.scale(SCALE, SCALE);
  ctx.fillStyle = "#fff"; ctx.fillRect(0, 0, W, H);
  ctx.textBaseline = "alphabetic";
  const text = (t, x, y, { w = 400, s = 12.5, c = C.ink, align = "left", maxW } = {}) => {
    ctx.font = font(w, s); ctx.fillStyle = c; ctx.textAlign = align;
    ctx.fillText(maxW ? fit(ctx, t, maxW) : t, x, y);
  };
  const hline = (y, c, lw = 1) => { ctx.fillStyle = c; ctx.fillRect(PAD, y, W - PAD * 2, lw); };
  const roundRect = (x, y, w, h, rad, fill, stroke) => {
    ctx.beginPath(); ctx.moveTo(x + rad, y); ctx.arcTo(x + w, y, x + w, y + h, rad); ctx.arcTo(x + w, y + h, x, y + h, rad);
    ctx.arcTo(x, y + h, x, y, rad); ctx.arcTo(x, y, x + w, y, rad); ctx.closePath();
    if (fill) { ctx.fillStyle = fill; ctx.fill(); }
    if (stroke) { ctx.strokeStyle = stroke; ctx.lineWidth = 1; ctx.stroke(); }
  };
  const range = from || to ? (from === to ? fmtDate(from) : `${fmtDate(from) || "…"} – ${fmtDate(to) || "…"}`) : "Tất cả";

  // Header
  let y = PAD;
  text("CÁC KHOẢN ĐÃ CHI — XE NGOÀI", PAD, y + 20, { w: 700, s: 19 });
  text(`Ngày chi: ${range}`, PAD, y + 42, { s: 13, c: C.ink3, w: 600 });
  text(CO_NAME, W - PAD, y + 16, { w: 700, s: 12.5, c: C.accent, align: "right", maxW: 360 });
  text(CO_SUB, W - PAD, y + 33, { s: 11.5, c: C.ink3, align: "right", maxW: 360 });
  y += headH;
  roundRect(PAD, y, W - PAD * 2, infoH - 8, 9, C.accentBg, C.accentBd);
  text(`Nhà xe: ${st.vendor || "—"}`, PAD + 14, y + 24, { w: 700, s: 14.5, maxW: 480 });
  text(`Bảng kê: ${st.no || "—"}`, W - PAD - 14, y + 24, { s: 12.5, c: C.ink3, align: "right", w: 600 });
  y += infoH + 14;

  // Table head
  ctx.fillStyle = "#fafbfc"; ctx.fillRect(PAD, y, W - PAD * 2, thH);
  cols.forEach((c) => text(c.title.toUpperCase(), c.align === "right" ? c.x + c.w - 8 : c.align === "center" ? c.x + c.w / 2 : c.x + 8, y + 20, { w: 700, s: 10.5, c: C.ink3, align: c.align || "left" }));
  y += thH; hline(y - 1.5, C.line, 1.5);

  // Rows
  prepared.forEach(({ cl, h }) => {
    cols.forEach((c) => {
      cl[c.k].forEach((ln, i) => {
        const ty = y + 7 + LH * i + 12;
        const tx = c.align === "right" ? c.x + c.w - 8 : c.align === "center" ? c.x + c.w / 2 : c.x + 8;
        const maxW = c.w - 16;
        if (ln.extra) {
          ctx.font = font(ln.w || 400, ln.s || 12.5);
          const head = fit(ctx, ln.t, maxW);
          const hw = ctx.measureText(head).width;
          text(head, tx, ty, { w: ln.w, s: ln.s, c: ln.c });
          if (maxW - hw > 30) text(ln.extra.t, tx + hw + 8, ty, { w: ln.extra.w, s: 12, c: ln.extra.c, maxW: maxW - hw - 8 });
        } else text(ln.t, tx, ty, { w: ln.w, s: ln.s, c: ln.c, align: c.align || "left", maxW });
      });
    });
    y += h; hline(y - 1, C.line2);
  });

  // Footer tổng
  const sFee = rows.reduce((a, r) => a + r.fee, 0), sChoho = rows.reduce((a, r) => a + r.choho, 0), sAll = sFee + sChoho;
  hline(y, C.line, 1.5);
  const [, , cLot, cFee, cChoho, cSum] = cols;
  text("TỔNG", cLot.x + cLot.w - 8, y + 25, { w: 700, s: 12.5, c: C.ink3, align: "right" });
  text(fmtNum(sFee), cFee.x + cFee.w - 8, y + 25, { w: 700, align: "right" });
  text(fmtNum(sChoho), cChoho.x + cChoho.w - 8, y + 25, { w: 700, align: "right" });
  text(fmtNum(sAll), cSum.x + cSum.w - 8, y + 25, { w: 700, align: "right" });
  y += footH + 16;

  // Ô tổng đã chi
  const bw = 340, bx = W - PAD - bw;
  roundRect(bx, y, bw, sumBoxH - 10, 10, "#f3faf6", "#bfe5d0");
  text("TỔNG ĐÃ CHI", bx + 16, y + 26, { w: 700, s: 13, c: C.ink2 });
  text(fmtVND(sAll), bx + bw - 16, y + 27, { w: 700, s: 18, c: C.good, align: "right" });
  const nItems = rows.reduce((a, r) => a + r.parts.length, 0);
  text(`${rows.length} lô · ${nItems} khoản${sFee && sChoho ? ` · cước ${fmtNum(sFee)} · chi hộ ${fmtNum(sChoho)}` : ""}`, bx + 16, y + 49, { s: 11.5, c: C.ink3, maxW: bw - 32 });
  y += sumBoxH;

  const now = new Date();
  text(`Xuất lúc ${String(now.getHours()).padStart(2, "0")}:${String(now.getMinutes()).padStart(2, "0")} ${fmtDate(ymd(now))}`, PAD, y + 12, { s: 11, c: C.ink4 });
  return cv;
}

const toBlob = (cv) => new Promise((res) => cv.toBlob(res, "image/png"));
const toast = (m, t) => window.trkToast && window.trkToast(m, t);

function PaidSnapshotModal({ st, entries, onClose }) {
  const dates = useMemo(() => [...new Set(entries.flatMap((e) => e.parts.map((p) => p.date)))].sort(), [entries]);
  const last = dates[dates.length - 1] || ymd(new Date());
  const [from, setFrom] = useState(last);
  const [to, setTo] = useState(last);
  const [cv, setCv] = useState(null);
  const rows = useMemo(() => rowsIn(entries, from, to), [entries, from, to]);
  const src = useMemo(() => (cv ? cv.toDataURL("image/png") : ""), [cv]);

  useEffect(() => {
    let alive = true;
    if (!rows.length) { setCv(null); return undefined; }
    drawPaidImage({ st, rows, from, to }).then((c) => { if (alive) setCv(c); });
    return () => { alive = false; };
  }, [rows]);

  const today = ymd(new Date());
  const d = new Date();
  const presets = [
    ["Ngày chi gần nhất", last, last],
    ["Hôm nay", today, today],
    ["Tháng này", ymd(new Date(d.getFullYear(), d.getMonth(), 1)), today],
    ["Tất cả", "", ""],
  ];
  const fileName = () => {
    const slug = (st.vendor || "nha-xe").normalize("NFD").replace(/[̀-ͯ]/g, "").replace(/đ/gi, "d").replace(/[^A-Za-z0-9]+/g, "-").replace(/^-|-$/g, "").toLowerCase();
    return `da-chi-${slug}-${from || "dau"}_${to || "nay"}.png`;
  };
  const download = async () => {
    const blob = await toBlob(cv);
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob); a.download = fileName();
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
  };
  const copy = async () => {
    try {
      await navigator.clipboard.write([new window.ClipboardItem({ "image/png": toBlob(cv) })]);
      toast("Đã sao chép ảnh — dán (Ctrl+V) vào Zalo / email gửi kế toán");
    } catch (e) { toast("Trình duyệt không cho sao chép ảnh — dùng Tải ảnh", "error"); }
  };
  const canShare = !!(navigator.share && navigator.canShare);
  const share = async () => {
    const file = new File([await toBlob(cv)], fileName(), { type: "image/png" });
    if (!navigator.canShare({ files: [file] })) { toast("Thiết bị không hỗ trợ chia sẻ ảnh — dùng Tải ảnh", "error"); return; }
    try { await navigator.share({ files: [file], title: "Các khoản đã chi — " + (st.vendor || "") }); } catch (e) { /* người dùng hủy */ }
  };
  const total = rows.reduce((a, r) => a + r.sum, 0);

  return (
    <Modal title="Chụp ảnh các khoản đã chi" subtitle={`${st.no || ""} · ${st.vendor || ""} — lọc theo ngày tích đã chi, gửi kế toán`} onClose={onClose} width={980}
      icon={<i className="bi bi-camera" style={{ fontSize: 17 }} />}
      footer={
        <div style={{ display: "flex", alignItems: "center", gap: 10, flexWrap: "wrap" }}>
          <span className="tnum" style={{ fontSize: 13, color: "var(--ink-3)" }}>{rows.length} lô · đã chi <b style={{ color: "var(--good)" }}>{fmtVND(total)}</b></span>
          <div style={{ flex: 1 }} />
          <Btn onClick={onClose}>Đóng</Btn>
          {canShare && <Btn onClick={share} disabled={!cv}><i className="bi bi-share" /> Chia sẻ</Btn>}
          <Btn onClick={copy} disabled={!cv}><i className="bi bi-clipboard" /> Sao chép ảnh</Btn>
          <Btn variant="primary" onClick={download} disabled={!cv}><i className="bi bi-download" /> Tải ảnh PNG</Btn>
        </div>
      }>
      <div style={{ display: "flex", alignItems: "flex-end", gap: 10, flexWrap: "wrap", padding: "14px 0 12px" }}>
        <div>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>Ngày chi từ</div>
          <div style={{ width: 150 }}><DateField value={from} onChange={setFrom} /></div>
        </div>
        <div>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>đến ngày</div>
          <div style={{ width: 150 }}><DateField value={to} onChange={setTo} /></div>
        </div>
        <div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
          {presets.map(([label, f, t]) => {
            const on = from === f && to === t;
            return (
              <button key={label} type="button" onClick={() => { setFrom(f); setTo(t); }}
                style={{ padding: "8px 12px", fontSize: 12.5, fontWeight: 600, borderRadius: 8, cursor: "pointer",
                  border: "1px solid " + (on ? "var(--accent)" : "var(--line)"), background: on ? "var(--accent-weak-2)" : "#fff", color: on ? "var(--accent)" : "var(--ink-2)" }}>{label}</button>
            );
          })}
        </div>
      </div>
      {!rows.length ? (
        <div style={{ padding: "40px 20px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5, border: "1px dashed var(--line)", borderRadius: 10 }}>
          <i className="bi bi-calendar-x" style={{ fontSize: 24, display: "block", marginBottom: 6 }} />
          {entries.length ? "Không có khoản nào tích đã chi trong khoảng ngày này." : "Bảng kê chưa có khoản nào được tích đã chi."}
        </div>
      ) : (
        <div style={{ border: "1px solid var(--line)", borderRadius: 10, background: "#f6f7f9", padding: 10 }}>
          {src ? <img src={src} alt="Ảnh các khoản đã chi" style={{ display: "block", width: "100%", height: "auto", borderRadius: 6, boxShadow: "0 1px 3px rgba(16,19,23,.12)" }} />
            : <div style={{ padding: 30, textAlign: "center", color: "var(--ink-4)" }}>Đang tạo ảnh…</div>}
        </div>
      )}
    </Modal>
  );
}

export { PaidSnapshotModal };
