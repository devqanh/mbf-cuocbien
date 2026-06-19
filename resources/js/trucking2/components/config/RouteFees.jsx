import React from "react";
const { useState } = React;
import { I, Money, Num, MultiCombo, fmtShort, Btn, Modal } from "@trk/lib.jsx";

/* ===================== PHÍ TUYẾN ĐƯỜNG (repeater) ===================== */

/* Popup Nhập phí tuyến từ Excel: chọn file → KIỂM TRA (terminal) → có lỗi thì chặn; OK mới cho nhập. */
function RouteFeeImportModal({ onClose }) {
  const R = (window.__TRK || {}).routes || {};
  const [fileName, setFileName] = useState("");
  const [file, setFile] = useState(null);
  const [checking, setChecking] = useState(false);
  const [res, setRes] = useState(null);        // kết quả check (rows/summary/canImport)
  const [importing, setImporting] = useState(false);
  const [shown, setShown] = useState(0);        // số dòng terminal đã hiện (tiến trình)
  const [done, setDone] = useState(false);

  const pick = async (e) => {
    const f = e.target.files && e.target.files[0]; e.target.value = "";
    if (!f) return;
    setFile(f); setFileName(f.name); setRes(null); setDone(false); setShown(0); setChecking(true);
    const fd = new FormData(); fd.append("file", f);
    try {
      const r = await window.trkUpload("POST", R.routeFeesImportCheck, fd);
      if (r && r.rows) { setRes(r); setShown(r.rows.length); }
      else window.trkToast && window.trkToast((r && r.message) || "Đọc file lỗi", "error");
    } catch (err) { window.trkToast && window.trkToast("Đọc file lỗi", "error"); }
    setChecking(false);
  };

  const doImport = async () => {
    if (!file || !res || !res.canImport || importing) return;
    setImporting(true); setShown(0);
    const total = (res.rows || []).length;
    const fd = new FormData(); fd.append("file", file);
    const tick = setInterval(() => setShown((s) => (s < total ? s + 1 : s)), 70);   // tiến trình chạy dòng
    try {
      const r = await window.trkUpload("POST", R.routeFeesImport, fd);
      clearInterval(tick); setShown(total);
      if (r && r.ok) { setDone(true); window.trkToast && window.trkToast(`Đã nhập: +${r.created} mới · ${r.updated} cập nhật`); setTimeout(() => window.location.reload(), 1100); }
      else { setImporting(false); window.trkToast && window.trkToast((r && r.message) || "Nhập thất bại", "error"); }
    } catch (err) { clearInterval(tick); setImporting(false); window.trkToast && window.trkToast("Nhập thất bại", "error"); }
  };

  const sm = res ? res.summary : null;
  const visRows = (res ? res.rows : []).slice(0, importing || done ? shown : undefined);
  const ic = { error: { c: "#f87171", i: "✗" }, update: { c: "#34d399", i: "✓" }, create: { c: "#38bdf8", i: "✓" } };

  const footer = (
    <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 10 }}>
      <label style={{ display: "inline-flex", alignItems: "center", gap: 6, fontSize: 12.5, color: "var(--accent)", cursor: importing ? "default" : "pointer", fontWeight: 600 }}>
        <i className="bi bi-paperclip" /> {fileName ? "Chọn file khác" : "Chọn file Excel"}
        <input type="file" accept=".xlsx,.xls,.csv" style={{ display: "none" }} disabled={importing} onChange={pick} />
      </label>
      <div style={{ display: "flex", gap: 8 }}>
        <Btn onClick={onClose} disabled={importing}>Đóng</Btn>
        <Btn variant="primary" onClick={doImport} disabled={!res || !res.canImport || importing || done}>{importing ? "Đang nhập…" : "Xác nhận nhập"}</Btn>
      </div>
    </div>
  );

  return (
    <Modal title="Nhập phí tuyến từ Excel" subtitle="Kiểm tra file trước — có lỗi sẽ KHÔNG nhập gì; sửa file rồi tải lại" width={680} onClose={importing ? () => {} : onClose} footer={footer} icon={<i className="bi bi-upload" />}>
      <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
        {!res && !checking && (
          <div style={{ padding: "18px", textAlign: "center", color: "var(--ink-4)", fontSize: 13, border: "1px dashed var(--line)", borderRadius: 10 }}>
            Chọn file Excel (xuất từ nút <b>Xuất Excel</b>, điền/sửa rồi tải lên). Hệ thống kiểm tra trước khi nhập.
          </div>
        )}
        {checking && <div style={{ padding: "16px", textAlign: "center", color: "var(--ink-3)", fontSize: 13 }}><i className="bi bi-arrow-repeat" /> Đang kiểm tra file…</div>}
        {sm && (
          <div style={{ display: "flex", gap: 8, flexWrap: "wrap", fontSize: 12.5 }}>
            <span style={chip("#38bdf8")}>{sm.willCreate} thêm mới</span>
            <span style={chip("#34d399")}>{sm.willUpdate} cập nhật</span>
            {sm.warnings > 0 && <span style={chip("#f59e0b")}>{sm.warnings} cảnh báo</span>}
            {sm.errors > 0 && <span style={chip("#f87171")}>{sm.errors} lỗi</span>}
          </div>
        )}
        {sm && sm.errors > 0 && !importing && (
          <div style={{ display: "flex", gap: 8, alignItems: "flex-start", padding: "9px 12px", fontSize: 12.5, color: "#a05a00", background: "#fff7e9", border: "1px solid #f1d59a", borderRadius: 10 }}>
            <i className="bi bi-exclamation-triangle-fill" style={{ marginTop: 1 }} /><span><b>File còn {sm.errors} lỗi</b> — sửa trên Excel rồi bấm "Chọn file khác". Chưa nhập gì cả.</span>
          </div>
        )}
        {res && (
          <div style={{ background: "#0f172a", borderRadius: 10, padding: "12px 14px", maxHeight: 320, overflowY: "auto", fontFamily: "ui-monospace, Menlo, Consolas, monospace", fontSize: 12.5, lineHeight: 1.7 }}>
            {visRows.map((row, i) => {
              const k = ic[row.action] || ic.error;
              return (
                <div key={i}>
                  <span style={{ color: k.c, fontWeight: 700 }}>{k.i}</span>
                  <span style={{ color: "#64748b" }}> dòng {row.line} </span>
                  <span style={{ color: "#e2e8f0" }}>{row.route || "(trống)"}</span>
                  <span style={{ color: row.action === "update" ? "#34d399" : row.action === "create" ? "#38bdf8" : "#f87171" }}> · {row.action === "update" ? "cập nhật" : row.action === "create" ? "thêm mới" : "LỖI"}</span>
                  {(row.issues || []).map((is, j) => (
                    <div key={j} style={{ color: is.level === "error" ? "#fca5a5" : "#fcd34d", paddingLeft: 18 }}>↳ {is.msg}</div>
                  ))}
                </div>
              );
            })}
            {importing && shown < (res.rows || []).length && <div style={{ color: "#94a3b8" }}>▌ đang xử lý…</div>}
            {done && <div style={{ color: "#34d399", fontWeight: 700, marginTop: 4 }}>✓ Hoàn tất — đang tải lại…</div>}
          </div>
        )}
      </div>
    </Modal>
  );
}
const chip = (c) => ({ display: "inline-flex", alignItems: "center", gap: 5, padding: "2px 10px", borderRadius: 999, fontWeight: 700, color: c, background: c + "22", border: "1px solid " + c + "55" });

export function RouteFees({ rows = [], onChange, warehouses = [], locations = [], isDup = () => false }) {
  // Node tuyến = Cảng (địa điểm) HOẶC Kho — gợi ý gom 2 nhóm để chọn cả chuỗi Cảng→Kho→Kho→Cảng.
  const routeGroups = [{ label: "Cảng", items: locations || [] }, { label: "Kho", items: warehouses || [] }];
  const set = (i, np) => onChange(rows.map((r, j) => (j === i ? { ...r, ...np } : r)));
  // Toggle: mặc định THU GỌN từng tuyến, click header mới hiện cấu hình giá (đỡ rối khi nhiều tuyến).
  const [open, setOpen] = useState(() => new Set());
  const toggle = (id) => setOpen((s) => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
  // Thêm tuyến: KẾ THỪA giá + tick "chi theo ngày" của tuyến trên (đỡ nhập lại), chỉ để TRỐNG ô Tuyến.
  const add = () => {
    const list = rows || [];
    const prev = list[list.length - 1];
    const base = prev
      ? { veTram: prev.veTram, tienDuong: prev.tienDuong, troCap: prev.troCap, phiKhac: prev.phiKhac, cru: prev.cru,
          luong: prev.luong, luongNoCru: prev.luongNoCru, luongNokeo: prev.luongNokeo, luongNokeoNoCru: prev.luongNokeoNoCru,
          salaryParts: [...(prev.salaryParts || [])], km: prev.km, dau2: prev.dau2, dau1: prev.dau1,
          extraFees: (prev.extraFees || []).map((f) => ({ ...f })) }
      : { veTram: "", tienDuong: "", troCap: "", cru: false, luong: "", luongNoCru: "", luongNokeo: "", luongNokeoNoCru: "", salaryParts: ["troCap", "luong"], km: "", dau2: "", dau1: "", extraFees: [] };
    const id = Date.now() + Math.random();
    onChange([...list, { id, route: "", ...base }]);
    setOpen((s) => new Set(s).add(id));   // tuyến mới: mở sẵn để nhập
  };
  const del = (i) => onChange(rows.filter((_, j) => j !== i));
  const lbl = (t) => <div style={{ fontSize: 11.5, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>{t}</div>;
  // Xuất/Nhập Excel — nhập UPSERT theo tuyến (trùng → cập nhật, mới → thêm)
  const R = (window.__TRK || {}).routes || {};
  const ioBtn = { display: "inline-flex", alignItems: "center", gap: 6, height: 32, padding: "0 12px", fontSize: 12.5, fontWeight: 600, borderRadius: 9, border: "1px solid var(--line)", background: "#fff", color: "var(--ink-2)", cursor: "pointer", textDecoration: "none" };
  const [importOpen, setImportOpen] = useState(false);
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
      {importOpen && <RouteFeeImportModal onClose={() => setImportOpen(false)} />}
      <div style={{ display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap" }}>
        <a href={R.routeFeesExport} style={ioBtn}><i className="bi bi-file-earmark-excel" /> Xuất Excel</a>
        <button type="button" onClick={() => setImportOpen(true)} style={ioBtn}><i className="bi bi-upload" /> Nhập Excel</button>
        <span style={{ fontSize: 11.5, color: "var(--ink-4)" }}>Điền nhanh trên Excel rồi nhập lại — <b>kiểm tra trước, có lỗi không nhập gì</b>; trùng tuyến tự cập nhật.</span>
      </div>
      {(rows || []).length === 0 && <div style={{ padding: "14px 2px", fontSize: 12.5, color: "var(--ink-4)" }}>Chưa có tuyến nào — bấm <b>+ Thêm tuyến</b> để cấu hình phí.</div>}
      {(rows || []).map((r, i) => {
        const dup = isDup(r.route);
        const sal = Array.isArray(r.salaryParts) ? r.salaryParts : [];
        const salChk = (key) => (
          <label style={{ display: "inline-flex", alignItems: "center", gap: 4, marginTop: 5, fontSize: 11, fontWeight: 600, color: sal.includes(key) ? "var(--accent)" : "var(--ink-4)", cursor: "pointer", userSelect: "none" }} title="Tích = chi khoản này cho lái xe theo NGÀY (tổng hợp ở Lộ trình theo từng chuyến)">
            <input type="checkbox" checked={sal.includes(key)} onChange={() => set(i, { salaryParts: sal.includes(key) ? sal.filter((k) => k !== key) : [...sal, key] })} style={{ accentColor: "var(--accent)", cursor: "pointer", margin: 0 }} />
            chi theo ngày
          </label>
        );
        const rid = r.id || i; const isOpen = open.has(rid);
        const routeText = (r.route || "").split(/\s*-\s*/).filter(Boolean).join(" → ");
        return (
        <div key={r.id || i} style={{ border: `1px solid ${dup ? "var(--danger)" : "var(--line)"}`, borderRadius: 12, background: dup ? "#fff5f5" : "#fafbfc", overflow: "hidden" }}>
          {/* HEADER: click để mở/đóng cấu hình giá tuyến */}
          <div onClick={() => toggle(rid)} style={{ display: "flex", alignItems: "center", gap: 10, padding: "12px 14px", cursor: "pointer", userSelect: "none" }}>
            <i className={"bi " + (isOpen ? "bi-chevron-down" : "bi-chevron-right")} style={{ color: "var(--ink-4)", fontSize: 13, flexShrink: 0 }} />
            <i className="bi bi-signpost-2-fill" style={{ color: dup ? "var(--danger)" : "var(--accent)", flexShrink: 0 }} />
            <div style={{ flex: 1, minWidth: 0 }}>
              <div className="tnum" style={{ fontSize: 13.5, fontWeight: 700, color: routeText ? "var(--ink)" : "var(--ink-4)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{routeText || "(chưa chọn tuyến)"}</div>
              {!isOpen && <div style={{ fontSize: 11, color: "var(--ink-4)", marginTop: 1 }}>Vé {fmtShort(r.veTram || 0)} · Lương kéo {fmtShort(r.luong || 0)}{(r.km ? " · " + r.km + "km" : "")}{dup ? " · ⚠ trùng tuyến" : " · bấm để sửa giá"}</div>}
            </div>
            <button type="button" onClick={(e) => { e.stopPropagation(); del(i); }} title="Xóa tuyến"
              style={{ flexShrink: 0, width: 32, height: 32, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-4)", cursor: "pointer" }}
              onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; }}
              onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-4)"; }}><I.trash /></button>
          </div>
          {isOpen && (
          <div style={{ padding: "0 16px 14px" }}>
          {/* Chọn tuyến */}
          <div style={{ marginBottom: 12 }}>
            {lbl(<>Tuyến · chọn Cảng &amp; Kho <span style={{ color: "var(--ink-4)", fontWeight: 400 }}>(cả chuỗi, vd Cảng → Kho → Kho → Cảng)</span>{dup && <span style={{ color: "var(--danger)", fontWeight: 700, marginLeft: 6 }}>· trùng tuyến</span>}</>)}
            <MultiCombo values={(r.route || "").split(/\s*-\s*/).filter(Boolean)} onChange={(arr) => set(i, { route: arr.join(" - ") })} groups={routeGroups} allowDup max={Infinity} placeholder="Chọn cảng/kho cho tuyến…" />
          </div>
          {/* Phí cố định của tuyến */}
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(130px, 1fr))", gap: 10, marginBottom: 10 }}>
            <div>{lbl("Vé trạm")}<Money value={r.veTram} onChange={(x) => set(i, { veTram: x })} dim />{salChk("veTram")}</div>
            <div>{lbl("Tiền đường")}<Money value={r.tienDuong} onChange={(x) => set(i, { tienDuong: x })} dim />{salChk("tienDuong")}</div>
            <div>{lbl("Trợ cấp")}<Money value={r.troCap} onChange={(x) => set(i, { troCap: x })} dim />{salChk("troCap")}</div>
          </div>
          {/* Lương lái xe — 2 chiều: (CÓ/KHÔNG kéo cont ra) × (CRU/không CRU) = 4 mức */}
          <div style={{ border: "1px solid var(--line)", borderRadius: 10, padding: "11px 12px", marginBottom: 10, background: "#fff" }}>
            <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 9 }}>
              <i className="bi bi-cash-stack" style={{ color: "var(--accent)", fontSize: 13 }} />
              <span style={{ fontWeight: 700, fontSize: 12.5 }}>Lương lái xe</span>
              <span style={{ fontSize: 11, color: "var(--ink-4)" }}>theo <b>kéo cont ra</b> × <b>CRU</b></span>
              <span style={{ flex: 1 }} />
              {salChk("luong")}
            </div>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(230px, 1fr))", gap: 10 }}>
              {[
                { hd: "Có kéo cont ra", sub: "chuyến lấy/giao cont", cru: "luong", noCru: "luongNoCru" },
                { hd: "Không kéo cont ra", sub: "ra xe không kéo cont", cru: "luongNokeo", noCru: "luongNokeoNoCru" },
              ].map((grp) => (
                <div key={grp.cru} style={{ border: "1px solid var(--line-2)", borderRadius: 9, padding: "9px 10px", background: "#fafbfc" }}>
                  <div style={{ fontSize: 11.5, fontWeight: 700, color: "var(--ink-2)", marginBottom: 2 }}>{grp.hd}</div>
                  <div style={{ fontSize: 10, color: "var(--ink-4)", marginBottom: 7 }}>{grp.sub}</div>
                  <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8 }}>
                    <div>{lbl("CRU")}<Money value={r[grp.cru]} onChange={(x) => set(i, { [grp.cru]: x })} dim /></div>
                    <div>{lbl("Không CRU")}<Money value={r[grp.noCru]} onChange={(x) => set(i, { [grp.noCru]: x })} dim /></div>
                  </div>
                </div>
              ))}
            </div>
          </div>
          <div style={{ fontSize: 11, color: "var(--ink-4)", marginBottom: 10 }}>Lương chọn theo <b>2 điều kiện</b>: chuyến <b>có/không kéo cont ra</b> và lô <b>tích CRU</b> hay không. Tích <b style={{ color: "var(--accent)" }}>chi theo ngày</b> ở khoản nào → khoản đó tổng hợp trả cho lái xe theo từng chuyến ở <b>Lộ trình</b>. Dầu tính tiền = số lít × <b>giá dầu theo ngày</b> của chuyến.</div>
          {/* Định mức km & dầu — dầu có thể tích "chi theo ngày" (tính tiền theo Bảng giá dầu theo ngày) */}
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(130px, 1fr))", gap: 10 }}>
            <div>{lbl("Số km")}<Num value={r.km} onChange={(x) => set(i, { km: x })} suffix="km" /></div>
            <div>{lbl("Dầu 2 cầu")}<Num value={r.dau2} onChange={(x) => set(i, { dau2: x })} suffix="lít" />{salChk("dau2")}</div>
            <div>{lbl("Dầu 1 cầu")}<Num value={r.dau1} onChange={(x) => set(i, { dau1: x })} suffix="lít" />{salChk("dau1")}</div>
          </div>
          {/* Chi khác — repeater khoản tùy chỉnh của tuyến (mỗi dòng tự tick "chi theo ngày") */}
          {(() => {
            const ex = Array.isArray(r.extraFees) ? r.extraFees : [];
            const setEx = (arr) => set(i, { extraFees: arr });
            const updEx = (k, np) => setEx(ex.map((f, j) => (j === k ? { ...f, ...np } : f)));
            return (
              <div style={{ marginTop: 12, borderTop: "1px dashed var(--line)", paddingTop: 11 }}>
                <div style={{ fontSize: 11.5, fontWeight: 700, color: "var(--ink-2)", marginBottom: 7 }}><i className="bi bi-plus-slash-minus" style={{ color: "var(--accent)" }} /> Chi khác <span style={{ fontWeight: 400, color: "var(--ink-4)" }}>(khoản tùy chỉnh của tuyến)</span></div>
                <div style={{ display: "flex", flexDirection: "column", gap: 7 }}>
                  {ex.map((f, k) => (
                    <div key={k} style={{ display: "flex", alignItems: "center", gap: 8 }}>
                      <input value={f.name || ""} onChange={(e) => updEx(k, { name: e.target.value })} placeholder="Tên khoản chi" style={{ flex: 1, minWidth: 0, padding: "7px 10px", fontSize: 13, border: "1px solid var(--line)", borderRadius: 8, outline: "none" }} />
                      <div style={{ width: 150 }}><Money value={f.amount} onChange={(x) => updEx(k, { amount: x })} dim /></div>
                      <label style={{ display: "inline-flex", alignItems: "center", gap: 4, fontSize: 11, fontWeight: 600, color: f.perDay ? "var(--accent)" : "var(--ink-4)", cursor: "pointer", whiteSpace: "nowrap" }} title="Tích = chi khoản này cho lái xe theo ngày (gom ở Lộ trình)">
                        <input type="checkbox" checked={!!f.perDay} onChange={() => updEx(k, { perDay: !f.perDay })} style={{ accentColor: "var(--accent)", margin: 0, cursor: "pointer" }} /> chi theo ngày
                      </label>
                      <button type="button" onClick={() => setEx(ex.filter((_, j) => j !== k))} title="Xóa khoản" style={{ flexShrink: 0, width: 30, height: 30, display: "grid", placeItems: "center", border: "1px solid var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-4)", cursor: "pointer" }}><I.x /></button>
                    </div>
                  ))}
                  {ex.length === 0 && <div style={{ fontSize: 11.5, color: "var(--ink-4)" }}>Chưa có khoản chi khác.</div>}
                </div>
                <button type="button" onClick={() => setEx([...ex, { name: "", amount: "", perDay: true }])} style={{ marginTop: 8, display: "inline-flex", alignItems: "center", gap: 6, padding: "6px 11px", fontSize: 12.5, fontWeight: 600, border: "1px dashed var(--line)", borderRadius: 8, background: "#fff", color: "var(--ink-3)", cursor: "pointer" }}><I.plus /> Thêm khoản chi khác</button>
              </div>
            );
          })()}
          </div>
          )}
        </div>
        );
      })}
      <button type="button" onClick={add}
        style={{ alignSelf: "flex-start", display: "inline-flex", alignItems: "center", gap: 7, padding: "9px 14px", fontSize: 13.5, fontWeight: 600, border: "1px dashed var(--accent)", borderRadius: 10, background: "var(--accent-weak-2)", color: "var(--accent)", cursor: "pointer" }}>
        <I.plus /> Thêm tuyến
      </button>
    </div>
  );
}
