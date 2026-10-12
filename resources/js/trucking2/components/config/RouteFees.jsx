import React from "react";
const { useState, useEffect } = React;
import { I, DateField, Btn, Modal } from "@trk/lib.jsx";
import { RegulationPanel } from "./RegulationPanel.jsx";

/* ===================== QUY CHẾ LƯƠNG CHUYẾN theo thời gian =====================
   Mỗi BẢNG (book) có "áp dụng từ ngày" + 1 khối quy chế (book.regulation). Lộ trình tính tiền mọi chuyến theo quy chế
   của bảng áp cho ngày vận hành (bảng có ngày lớn nhất ≤ ngày chuyến) — không còn khai từng tuyến. Đổi quy chế từ 1 ngày
   = tạo bảng mới từ ngày đó (sao chép quy chế của bảng đang chọn) rồi sửa; chuyến trước ngày đó vẫn theo bảng cũ. */

const dmy = (s) => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || ""); return m ? `${m[3]}/${m[2]}/${m[1]}` : ""; };
const bookRange = (b) => (b && b.from) ? "từ " + dmy(b.from) : "mọi thời điểm";
const bookTitle = (b) => (b ? (b.label ? b.label + " · " : "") + bookRange(b) : "");

/* Modal tạo / sửa BẢNG quy chế: nhãn + áp dụng từ ngày (+ sao chép quy chế của bảng đang chọn khi tạo). */
function BookModal({ mode, init, copyFromLabel, busy, onCancel, onSubmit }) {
  const [label, setLabel] = useState(init.label || "");
  const [from, setFrom] = useState(init.from || "");
  const [copy, setCopy] = useState(true);
  const inp = { width: "100%", fontSize: 14, padding: "9px 11px", border: "1px solid var(--line)", borderRadius: 9, outline: "none" };
  return (
    <Modal title={mode === "create" ? "Tạo quy chế mới từ ngày" : "Sửa bảng quy chế"} subtitle="Để trống ngày = bảng mặc định, áp mọi thời điểm." width={460} icon={<I.truck />} onClose={onCancel}
      footer={<div style={{ display: "flex", justifyContent: "flex-end", gap: 10 }}><Btn onClick={onCancel}>Hủy</Btn><Btn variant="primary" disabled={busy} onClick={() => onSubmit({ label, from, copy })}>{busy ? "Đang lưu…" : (mode === "create" ? "Tạo bảng" : "Lưu")}</Btn></div>}>
      <div style={{ padding: "14px 0 6px", display: "flex", flexDirection: "column", gap: 14 }}>
        <label style={{ display: "block" }}>
          <div style={{ fontSize: 12, fontWeight: 600, color: "var(--ink-3)", marginBottom: 5 }}>Nhãn (tùy chọn)</div>
          <input value={label} onChange={(e) => setLabel(e.target.value)} placeholder="VD: Quy chế 03/2026" style={inp} />
        </label>
        <label style={{ display: "block" }}>
          <div style={{ fontSize: 12, fontWeight: 600, color: "var(--ink-3)", marginBottom: 5 }}>Áp dụng từ ngày</div>
          <div style={{ width: 190 }}><DateField value={from} onChange={setFrom} /></div>
        </label>
        {mode === "create" && copyFromLabel && (
          <label style={{ display: "flex", alignItems: "flex-start", gap: 8, fontSize: 13, color: "var(--ink-2)", cursor: "pointer" }}>
            <input type="checkbox" checked={copy} onChange={(e) => setCopy(e.target.checked)} style={{ marginTop: 3, accentColor: "var(--accent)" }} />
            <span>Sao chép quy chế <b>đã lưu</b> của bảng <b>{copyFromLabel}</b> sang bảng mới, rồi chỉ sửa phần thay đổi.</span>
          </label>
        )}
        <div style={{ fontSize: 11.5, color: "var(--ink-4)", lineHeight: 1.5 }}>Lộ trình lấy bảng có ngày áp dụng <b>lớn nhất ≤ ngày chuyến</b>; chuyến trước ngày đó vẫn theo bảng cũ. Không có bảng nào phủ ngày → dùng bảng mặc định (không ngày).</div>
      </div>
    </Modal>
  );
}

export function RouteFees({ books = [], onBooks, regulationMeta = {} }) {
  const R = (window.__TRK || {}).routes || {};
  const api = (m, u, b) => window.trkApi(m, u, b);
  // ---- Bảng quy chế đang chọn (book) ----
  const [bookId, setBookId] = useState(books[0] ? books[0].id : null);
  useEffect(() => { if (!books.some((b) => b.id === bookId)) setBookId(books[0] ? books[0].id : null); }, [books.map((b) => b.id).join(",")]);
  const curBook = books.find((b) => b.id === bookId) || null;
  const [bm, setBm] = useState(null);         // {mode:'create'|'edit', id?, label, from}
  const [bookBusy, setBookBusy] = useState(false);
  const submitBook = async (d) => {
    if (!bm || bookBusy) return;
    setBookBusy(true);
    try {
      if (bm.mode === "create") {
        const r = await api("POST", R.routeFeeBooks, { label: d.label || null, from: d.from || null, copyFrom: d.copy && bookId ? bookId : null });
        if (r && r.ok) { onBooks && onBooks(r.books || []); setBookId(r.bookId); setBm(null); window.trkToast && window.trkToast("Đã tạo bảng quy chế"); }
      } else {
        const r = await api("PUT", R.routeFeeBooks + "/" + bm.id, { label: d.label || null, from: d.from || null });
        if (r && r.ok) { onBooks && onBooks(r.books || []); setBm(null); }
      }
    } finally { setBookBusy(false); }
  };
  const delBook = async () => {
    if (!curBook) return;
    const ok = await window.confirmAction({ title: "Xóa bảng quy chế?", text: `Xóa bảng <b>${bookTitle(curBook)}</b>. Lộ trình các ngày đang dùng bảng này sẽ rơi về bảng gần nhất trước đó. Không hoàn tác.`, confirmText: "Xóa bảng", danger: true });
    if (!ok) return;
    const r = await api("DELETE", R.routeFeeBooks + "/" + bookId);
    if (r && r.ok) onBooks && onBooks(r.books || []);
  };
  const chipStyle = (on) => ({ display: "inline-flex", alignItems: "center", gap: 7, padding: "7px 12px", borderRadius: 999, cursor: "pointer", fontSize: 12.5, fontWeight: 600,
    border: "1px solid " + (on ? "var(--accent)" : "var(--line)"), background: on ? "var(--accent-weak-2)" : "#fff", color: on ? "var(--accent)" : "var(--ink-2)" });
  const iconBtn = { display: "grid", placeItems: "center", width: 32, height: 32, borderRadius: 8, cursor: "pointer", border: "1px solid var(--line)", background: "#fff", color: "var(--ink-3)" };
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
      {bm && <BookModal mode={bm.mode} init={bm} copyFromLabel={curBook ? bookTitle(curBook) : ""} busy={bookBusy} onCancel={() => setBm(null)} onSubmit={submitBook} />}

      {/* Thanh chọn BẢNG quy chế theo thời gian (như Bảng giá theo khoảng ngày) */}
      <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
        {books.map((b) => (
          <button key={b.id} type="button" onClick={() => setBookId(b.id)} style={chipStyle(b.id === bookId)} title={b.from ? `Áp dụng cho chuyến từ ${dmy(b.from)}` : "Bảng mặc định — áp mọi thời điểm chưa có bảng nào phủ"}>
            <i className="bi bi-calendar3" style={{ opacity: .7 }} />
            {b.label ? <b>{b.label}</b> : null} {bookRange(b)}
            {!b.regulation && <span style={{ fontSize: 10.5, fontWeight: 700, color: "#b45309" }}>· chưa có quy chế</span>}
          </button>
        ))}
        <button type="button" onClick={() => setBm({ mode: "create", label: "", from: "" })} title="Tạo bảng quy chế áp dụng từ 1 ngày: sao chép quy chế của bảng đang chọn rồi sửa"
          style={{ ...chipStyle(false), border: "1px dashed var(--accent)", background: "var(--accent-weak-2)", color: "var(--accent)" }}>
          <I.plus /> Tạo quy chế mới từ ngày…
        </button>
        {curBook && (
          <>
            <button type="button" onClick={() => setBm({ mode: "edit", id: curBook.id, label: curBook.label || "", from: curBook.from || "" })} title="Sửa nhãn / ngày áp dụng" style={iconBtn}><i className="bi bi-pencil" /></button>
            <button type="button" onClick={delBook} title="Xóa bảng quy chế này" style={iconBtn}
              onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
              onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-3)"; }}><I.trash /></button>
          </>
        )}
      </div>
      <div style={{ fontSize: 11.5, color: "var(--ink-4)", lineHeight: 1.5, marginTop: -4 }}>
        Muốn đổi quy chế từ 1 ngày: bấm <b>Tạo quy chế mới từ ngày…</b> (sao chép quy chế đang chọn) → sửa → <b>Lưu quy chế</b>. Lộ trình lấy bảng có ngày áp dụng lớn nhất ≤ ngày chuyến; chuyến trước đó vẫn theo bảng cũ.
      </div>

      <RegulationPanel book={curBook} meta={regulationMeta} onBooks={onBooks} />
    </div>
  );
}
