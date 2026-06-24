import React from "react";
import { useIsMobile, I, Btn, Modal, DateField } from "@trk/lib.jsx";
import { PriceList } from "@trk/pop.jsx";

const fmtBD = (s) => { if (!s) return ""; const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s); return m ? `${m[3]}/${m[2]}/${m[1]}` : s; };
const bookRange = (b) => (b && (b.from || b.to)) ? `${b.from ? fmtBD(b.from) : "…"} – ${b.to ? fmtBD(b.to) : "…"}` : "Mọi ngày";
// 2 khoảng [a.from,a.to] và [b.from,b.to] chồng nhau? (null = vô cực)
const overlap = (a, b) => (a.from === null || b.to === null || a.from <= b.to) && (b.from === null || a.to === null || b.from <= a.to);

function BangGiaPage({ cfg, setBooks, api, routes }) {
  const { useState, useEffect } = React;
  const isMobile = useIsMobile();
  const customers = cfg.customers || [];
  const info = cfg.customerInfo || {};
  const [sel, setSel] = useState(customers[0] || null);
  const cur = sel != null && customers.includes(sel) ? sel : (customers[0] || null);
  const data = (cur && info[cur]) || {};
  const books = data.priceBooks || [];

  const [selBookId, setSelBookId] = useState(books[0]?.id ?? null);
  useEffect(() => { setSelBookId(books[0]?.id ?? null); }, [cur]);
  useEffect(() => { if ((selBookId == null || !books.some((b) => b.id === selBookId)) && books.length) setSelBookId(books[0].id); }, [books.length]);
  const curBook = books.find((b) => b.id === selBookId) || null;

  const [rowsByBook, setRowsByBook] = useState({});   // {bookId: rows[]}
  const [loadingRows, setLoadingRows] = useState(false);
  const [dirtyBook, setDirtyBook] = useState(null);
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState("");

  // Lazy-load dòng giá của book đang chọn
  useEffect(() => {
    const id = curBook?.id;
    if (!id || rowsByBook[id] !== undefined) return;
    setLoadingRows(true);
    api("GET", routes.customerPrices + "?book=" + id)
      .then((r) => { if (r && r.ok) setRowsByBook((s) => ({ ...s, [id]: r.priceList || [] })); })
      .finally(() => setLoadingRows(false));
  }, [curBook && curBook.id]);

  const rows = curBook ? (rowsByBook[curBook.id] || []) : [];
  const loaded = curBook ? rowsByBook[curBook.id] !== undefined : false;
  const bumpCount = (id, n) => setBooks(cur, books.map((b) => (b.id === id ? { ...b, count: n } : b)));
  const setRows = (arr) => { if (!curBook) return; setRowsByBook((s) => ({ ...s, [curBook.id]: arr })); setDirtyBook(curBook.id); setMsg(""); };
  const onImported = (arr) => { if (!curBook) return; setRowsByBook((s) => ({ ...s, [curBook.id]: arr })); setDirtyBook(null); bumpCount(curBook.id, arr.length); };

  const saveBook = () => {
    if (!curBook || saving) return;
    setSaving(true); setMsg("");
    api("PUT", routes.priceBookCreate + "/" + curBook.id + "/rows", { rows })
      .then((r) => { setSaving(false);
        if (r && r.ok) { setDirtyBook(null); const pl = r.priceList || rows; setRowsByBook((s) => ({ ...s, [curBook.id]: pl })); bumpCount(curBook.id, pl.length); setMsg("Đã lưu"); }
        else setMsg("Lưu lỗi"); })
      .catch(() => { setSaving(false); setMsg("Lưu lỗi kết nối"); });
  };

  // ----- Nhập báo giá gốc (.xlsx) vào book đang chọn -----
  const quoteRef = React.useRef(null);
  const [importing, setImporting] = useState(false);
  const onQuoteFile = async (e) => {
    const f = e.target.files && e.target.files[0]; e.target.value = "";
    if (!f || !curBook) return;
    if (rows.length > 0) {
      const ok = await window.confirmAction({ title: "Nhập báo giá gốc?", text: `Bảng giá đang chọn có <b>${rows.length}</b> dòng — nhập file sẽ <b>GHI ĐÈ</b> toàn bộ bằng dữ liệu trong file. Tiếp tục?`, confirmText: "Ghi đè bằng file", danger: true });
      if (!ok) return;
    }
    setImporting(true); setMsg("");
    try {
      const fd = new FormData(); fd.append("file", f); fd.append("book", curBook.id); fd.append("replace", "1");
      const res = await fetch(routes.priceQuoteImport, { method: "POST", headers: { Accept: "application/json", "X-CSRF-TOKEN": (window.__TRK || {}).csrf }, body: fd }).then((r) => r.json());
      setImporting(false);
      if (res && res.ok) { const pl = res.priceList || []; setRowsByBook((s) => ({ ...s, [curBook.id]: pl })); setDirtyBook(null); bumpCount(curBook.id, pl.length); const by = res.by || {}; setMsg(`Đã nhập ${res.imported} dòng (${Object.entries(by).map(([k, v]) => k + " " + v).join(", ")})`); }
      else setMsg(res && res.msg ? res.msg : "Nhập lỗi");
    } catch (er) { setImporting(false); setMsg("Nhập lỗi kết nối"); }
  };

  // ----- CRUD book -----
  const [bm, setBm] = useState(null);   // {mode:'create'|'edit', id?, label, from, to}
  const submitBook = () => {
    if (!bm) return;
    const body = { label: bm.label || null, from: bm.from || null, to: bm.to || null };
    if (bm.mode === "create") {
      api("POST", routes.priceBookCreate, { customer: cur, ...body }).then((r) => {
        if (r && r.ok) { const bs = r.books || []; setBooks(cur, bs); const last = bs[bs.length - 1]; if (last) setSelBookId(last.id); setBm(null); }
      });
    } else {
      api("PUT", routes.priceBookCreate + "/" + bm.id, body).then((r) => {
        if (r && r.ok) { setBooks(cur, r.books || []); setBm(null); }
      });
    }
  };
  const delBook = async () => {
    if (!curBook) return;
    const ok = await window.confirmAction({ title: "Xóa bảng giá?", text: `Xóa bảng giá <b>${bookRange(curBook)}</b> cùng <b>${curBook.count || 0}</b> dòng giá. Không hoàn tác.`, confirmText: "Xóa bảng giá", danger: true });
    if (!ok) return;
    api("DELETE", routes.priceBookCreate + "/" + curBook.id).then((r) => { if (r && r.ok) { setBooks(cur, r.books || []); setSelBookId((r.books || [])[0]?.id ?? null); } });
  };

  // Cảnh báo các bảng giá chồng khoảng ngày (lô trong vùng chồng theo ưu tiên book cụ thể hơn)
  const overlapWarn = (() => {
    for (let i = 0; i < books.length; i++) for (let j = i + 1; j < books.length; j++) {
      const a = { from: books[i].from || null, to: books[i].to || null };
      const b = { from: books[j].from || null, to: books[j].to || null };
      if (overlap(a, b)) return true;
    }
    return false;
  })();

  return (
    <div style={{ flex: 1, minHeight: 0, display: "flex", flexDirection: isMobile ? "column" : "row", overflow: "hidden" }}>
      {/* customer list */}
      <div style={{ width: isMobile ? "100%" : 240, flexShrink: 0, borderRight: isMobile ? "none" : "1px solid var(--line)", borderBottom: isMobile ? "1px solid var(--line)" : "none", background: "#fff", overflowY: isMobile ? "visible" : "auto", overflowX: isMobile ? "auto" : "visible", padding: isMobile ? "10px 12px" : "14px 12px" }}>
        <div style={{ fontSize: 11, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: "0.04em", padding: "2px 8px 8px" }}>Khách hàng</div>
        <div style={{ display: "flex", flexDirection: isMobile ? "row" : "column", gap: isMobile ? 7 : 1, flexWrap: isMobile ? "nowrap" : "wrap" }}>
          {customers.map((name) => {
            const active = cur === name;
            const ci = info[name] || {};
            const n = ci.priceCount || (ci.priceBooks || []).reduce((a, b) => a + (b.count || 0), 0);
            const nb = (ci.priceBooks || []).length;
            return (
              <button key={name} type="button" onClick={() => setSel(name)}
                style={{ textAlign: "left", border: isMobile ? "1px solid var(--line)" : "none", cursor: "pointer", borderRadius: isMobile ? 999 : 8, padding: "9px 11px", display: "flex", alignItems: "center", justifyContent: "space-between", gap: 8, flexShrink: 0, whiteSpace: "nowrap",
                  background: active ? "var(--accent-weak)" : (isMobile ? "#fff" : "transparent"), color: active ? "var(--accent)" : "var(--ink)", fontWeight: active ? 600 : 400, fontSize: 13.5 }}
                onMouseEnter={(e) => { if (!active && !isMobile) e.currentTarget.style.background = "var(--line-2)"; }}
                onMouseLeave={(e) => { if (!active && !isMobile) e.currentTarget.style.background = "transparent"; }}>
                <span style={{ whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{name}</span>
                {nb > 0 && <span className="tnum" style={{ fontSize: 11, fontWeight: 600, color: active ? "var(--accent)" : "var(--ink-4)", background: active ? "#fff" : "var(--line-2)", padding: "1px 7px", borderRadius: 999 }} title={`${nb} bảng giá · ${n} dòng`}>{nb}</span>}
              </button>
            );
          })}
          {!customers.length && <div style={{ padding: "16px 8px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có khách hàng. Thêm trong Cấu hình → Khách hàng.</div>}
        </div>
      </div>

      {/* price book + price list */}
      <div style={{ flex: 1, minWidth: 0, overflowY: "auto", padding: isMobile ? "16px 14px 40px" : "24px 28px 40px" }}>
        {cur ? (
          <div style={{ maxWidth: 880, margin: "0 auto" }}>
            <div style={{ marginBottom: 14 }}>
              <h1 style={{ margin: 0, fontSize: 21, fontWeight: 700, letterSpacing: "-0.02em" }}>Bảng giá</h1>
              <div style={{ fontSize: 13.5, color: "var(--ink-3)", marginTop: 3 }}>{cur}{data.taxCode ? ` · MST ${data.taxCode}` : ""}</div>
            </div>

            {/* Thanh chọn BẢNG GIÁ (price book theo khoảng ngày) */}
            <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap", marginBottom: 12 }}>
              {books.map((b) => {
                const on = b.id === selBookId;
                return (
                  <button key={b.id} type="button" onClick={() => setSelBookId(b.id)}
                    style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "7px 12px", borderRadius: 999, cursor: "pointer", fontSize: 12.5, fontWeight: 600,
                      border: "1px solid " + (on ? "var(--accent)" : "var(--line)"), background: on ? "var(--accent-weak-2)" : "#fff", color: on ? "var(--accent)" : "var(--ink-2)" }}>
                    <i className="bi bi-calendar3" style={{ opacity: .7 }} />
                    {b.label ? <b>{b.label}</b> : null} {bookRange(b)}
                    <span className="tnum" style={{ fontSize: 11, fontWeight: 700, background: on ? "var(--accent)" : "var(--line-2)", color: on ? "#fff" : "var(--ink-4)", padding: "0 6px", borderRadius: 999 }}>{b.count || 0}</span>
                  </button>
                );
              })}
              <button type="button" onClick={() => setBm({ mode: "create", label: "", from: "", to: "" })}
                style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 12px", borderRadius: 999, cursor: "pointer", fontSize: 12.5, fontWeight: 600, border: "1px dashed var(--accent)", background: "var(--accent-weak-2)", color: "var(--accent)" }}>
                <I.plus /> Tạo bảng giá
              </button>
              {curBook && (
                <>
                  <button type="button" onClick={() => setBm({ mode: "edit", id: curBook.id, label: curBook.label || "", from: curBook.from || "", to: curBook.to || "" })}
                    title="Sửa khoảng ngày / nhãn" style={{ display: "grid", placeItems: "center", width: 32, height: 32, borderRadius: 8, cursor: "pointer", border: "1px solid var(--line)", background: "#fff", color: "var(--ink-3)" }}><i className="bi bi-pencil" /></button>
                  <button type="button" onClick={delBook}
                    title="Xóa bảng giá" style={{ display: "grid", placeItems: "center", width: 32, height: 32, borderRadius: 8, cursor: "pointer", border: "1px solid var(--line)", background: "#fff", color: "var(--ink-3)" }}
                    onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
                    onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-3)"; }}><I.trash /></button>
                </>
              )}
            </div>

            {overlapWarn && <div style={{ display: "flex", alignItems: "center", gap: 7, fontSize: 12.5, color: "#9a6700", background: "#fff7e6", border: "1px solid #ffe1a8", borderRadius: 9, padding: "8px 12px", marginBottom: 12 }}><i className="bi bi-exclamation-triangle-fill" /> Có bảng giá <b>chồng khoảng ngày</b> — lô trong vùng chồng sẽ lấy bảng giá <b>cụ thể hơn</b> (có ngày, mới nhất).</div>}

            {curBook ? (
              <>
                <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 10 }}>
                  <div style={{ fontSize: 13, color: "var(--ink-3)" }}>Đang sửa: <b style={{ color: "var(--ink)" }}>{bookRange(curBook)}</b></div>
                  <div style={{ flex: 1 }} />
                  {msg && <span style={{ fontSize: 12, fontWeight: 600, color: /lỗi|không/i.test(msg) ? "var(--danger)" : "var(--good)" }}>{msg}</span>}
                  {dirtyBook === curBook.id && <span style={{ display: "inline-flex", alignItems: "center", gap: 6, fontSize: 12, fontWeight: 600, color: "var(--warn)" }}><span style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} /> Chưa lưu</span>}
                  <input ref={quoteRef} type="file" accept=".xlsx,.xls" style={{ display: "none" }} onChange={onQuoteFile} />
                  <button type="button" onClick={() => quoteRef.current && quoteRef.current.click()} disabled={importing}
                    title="Nhập từ file báo giá gốc (.xlsx, sheet 'import') — tự chuẩn hóa vào bảng giá này"
                    style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "8px 13px", fontSize: 12.5, fontWeight: 600, borderRadius: 9, cursor: "pointer", border: "1px solid var(--accent-weak)", background: "var(--accent-weak-2)", color: "var(--accent)" }}>
                    <i className="bi bi-filetype-xlsx" /> {importing ? "Đang nhập…" : "Nhập báo giá gốc"}
                  </button>
                  <button type="button" onClick={saveBook} disabled={saving || dirtyBook !== curBook.id}
                    style={{ display: "inline-flex", alignItems: "center", gap: 7, padding: "8px 15px", fontSize: 13, fontWeight: 600, borderRadius: 9, border: "none",
                      cursor: dirtyBook === curBook.id && !saving ? "pointer" : "default", color: dirtyBook === curBook.id && !saving ? "#fff" : "var(--ink-4)", background: dirtyBook === curBook.id && !saving ? "var(--accent)" : "var(--line-2)" }}>
                    <I.check /> {saving ? "Đang lưu…" : "Lưu bảng giá"}
                  </button>
                </div>
                <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "16px 18px" }}>
                  {loaded
                    ? <PriceList rows={rows} onChange={setRows} onImported={onImported} cfg={cfg} customer={cur} bookId={curBook.id} />
                    : <div style={{ display: "flex", alignItems: "center", justifyContent: "center", gap: 8, padding: "40px", color: "var(--ink-4)", fontSize: 13.5 }}><i className="bi bi-arrow-repeat" style={{ animation: "trk-spin 0.7s linear infinite" }} /> Đang tải bảng giá…</div>}
                </div>
              </>
            ) : (
              <div style={{ display: "grid", placeItems: "center", padding: "50px", color: "var(--ink-4)", fontSize: 13.5, background: "#fff", border: "1px dashed var(--line)", borderRadius: 12 }}>
                Khách này chưa có bảng giá. Bấm <b style={{ margin: "0 4px" }}>Tạo bảng giá</b> để thêm (chọn khoảng ngày áp dụng).
              </div>
            )}
          </div>
        ) : (
          <div style={{ display: "grid", placeItems: "center", height: "100%", color: "var(--ink-4)", fontSize: 13.5 }}>Chọn một khách hàng để xem bảng giá.</div>
        )}
      </div>

      {/* Modal tạo/sửa bảng giá */}
      {bm && (
        <Modal title={bm.mode === "create" ? "Tạo bảng giá" : "Sửa bảng giá"} subtitle="Để trống ngày = áp dụng mọi ngày (book mặc định)." width={440} icon={<I.truck />}
          onClose={() => setBm(null)}
          footer={<div style={{ display: "flex", justifyContent: "flex-end", gap: 10 }}><Btn onClick={() => setBm(null)}>Hủy</Btn><Btn variant="primary" onClick={submitBook}>{bm.mode === "create" ? "Tạo" : "Lưu"}</Btn></div>}>
          <div style={{ padding: "14px 0 6px", display: "flex", flexDirection: "column", gap: 14 }}>
            <label style={{ display: "block" }}>
              <div style={{ fontSize: 12, fontWeight: 600, color: "var(--ink-3)", marginBottom: 5 }}>Nhãn (tùy chọn)</div>
              <input value={bm.label} onChange={(e) => setBm({ ...bm, label: e.target.value })} placeholder="VD: Giá Q3/2026"
                style={{ width: "100%", fontSize: 14, padding: "9px 11px", border: "1px solid var(--line)", borderRadius: 9, outline: "none" }} />
            </label>
            <div style={{ display: "flex", gap: 12 }}>
              <label style={{ flex: 1 }}>
                <div style={{ fontSize: 12, fontWeight: 600, color: "var(--ink-3)", marginBottom: 5 }}>Từ ngày</div>
                <DateField value={bm.from} onChange={(v) => setBm({ ...bm, from: v })} placeholder="Mọi ngày" />
              </label>
              <label style={{ flex: 1 }}>
                <div style={{ fontSize: 12, fontWeight: 600, color: "var(--ink-3)", marginBottom: 5 }}>Đến ngày</div>
                <DateField value={bm.to} onChange={(v) => setBm({ ...bm, to: v })} placeholder="Mọi ngày" />
              </label>
            </div>
            <div style={{ fontSize: 11.5, color: "var(--ink-4)", lineHeight: 1.5 }}>Bảng kê sẽ lấy bảng giá phủ <b>ngày cont ra</b> của từng lô. Lô có ngày ngoài mọi bảng giá → "chưa khớp bảng giá".</div>
          </div>
        </Modal>
      )}
    </div>
  );
}

export { BangGiaPage };
