import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState } = React;
import { I, Btn, Txt, Combo, fmtVND, fmtDate, toNum } from "@trk/lib.jsx";
import { PayrollDetail, ExtraPayEditor, PaymentsEditor, payrollSumsFromDetail } from "@trk/components/payroll-detail.jsx";

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
  const grandDaily = rows.reduce((a, x) => a + (x.paidDaily || 0), 0);
  const grandPaid = rows.reduce((a, x) => a + paidOf(x), 0);

  const save = async (lk = locked) => {
    if (busy) return; setBusy(true);
    const res = await api("PUT", ROUTES.update + idUrl, { batch: { no, name, from: batch.from, to: batch.to, locked: lk, rows } });
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

  // TÍNH LẠI — phải xác nhận; giữ lái/lương phát sinh/đợt trả, cập nhật lương gốc + chi tiết theo cấu hình hiện tại.
  const recompute = async () => {
    if (locked) { window.trkToast && window.trkToast("Kỳ đã chốt — bỏ chốt trước khi tính lại", "error"); return; }
    const ok = await window.confirmAction({
      title: "Tính lại kỳ lương theo cấu hình hiện tại?",
      text: "Sẽ đọc lại Phí tuyến / giá dầu / lộ trình <b>hiện tại</b> và cập nhật lương gốc + chi tiết. <b>Giữ nguyên</b> lái xe, lương phát sinh, các đợt thanh toán đã nhập. Nhớ bấm <b>Lưu</b> sau khi tính lại.",
      confirmText: '<i class="bi bi-arrow-repeat me-1"></i> Tính lại',
    });
    if (!ok) return;
    setBusy(true);
    try {
      const r = await api("GET", ROUTES.recompute + idUrl + "/recompute");
      if (!r || !r.ok) { window.trkToast && window.trkToast("Tính lại thất bại", "error"); setBusy(false); return; }
      const byBks = {}; rows.forEach((x) => { byBks[x.bks] = x; });
      const merged = (r.rows || []).map((nr) => {
        const old = byBks[nr.bks] || {};
        return { ...nr, detail: nr.lines || nr.detail || [], driver: old.driver || nr.driver || "", extraPay: old.extraPay || [], payments: old.payments || [] };
      });
      setRows(merged); setDirty(true);
      window.trkToast && window.trkToast(`Đã tính lại ${merged.length} xe theo cấu hình hiện tại. Nhớ bấm Lưu.`);
    } catch (e) { window.trkToast && window.trkToast("Lỗi kết nối khi tính lại", "error"); }
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
              <div style={{ fontSize: 12.5, color: "var(--ink-3)" }} className="tnum">{fmtDate(batch.from) || "?"} → {fmtDate(batch.to) || "?"} · {rows.length} xe</div>
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
        <div style={{ maxWidth: 1000, margin: "0 auto", display: "flex", flexDirection: "column", gap: 14 }}>
          <div style={{ display: "flex", gap: 10, alignItems: "flex-start", background: "#eff5ff", border: "1px solid #cfe0fb", borderRadius: 12, padding: "12px 14px", fontSize: 13, color: "#1f4f9e", lineHeight: 1.6 }}>
            <i className="bi bi-info-circle-fill" style={{ fontSize: 16, marginTop: 1, flexShrink: 0 }} />
            <div>Bấm vào dòng xe để xem <b>chi tiết</b> + thêm <b>lương phát sinh</b> và ghi nhận <b>các đợt thanh toán</b>. <b>Tính lại</b> để cập nhật theo cấu hình hiện tại; <b>Chốt lương</b> để đóng băng số liệu (cả hai đều hỏi xác nhận trước).</div>
          </div>
          <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "14px 16px", display: "flex", gap: 12, flexWrap: "wrap", alignItems: "flex-end" }}>
            <div style={{ width: 150 }}>{lbl("Số kỳ")}<Txt value={no} onChange={(v) => { setNo(v); setDirty(true); }} /></div>
            <div style={{ flex: 1, minWidth: 180 }}>{lbl("Tên / ghi chú kỳ")}<Txt value={name} onChange={(v) => { setName(v); setDirty(true); }} /></div>
          </div>
          <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, overflow: "hidden" }}>
            <div style={{ overflowX: "auto" }}>
              <table style={{ width: "100%", borderCollapse: "collapse" }}>
                <thead><tr>
                  <TH>Biển số xe</TH><TH>Lái xe nhận lương</TH><TH right>Ngày</TH><TH right>Chuyến</TH>
                  <TH right>Đã chi theo ngày</TH><TH right>Lương phải trả</TH><TH right>Đã trả</TH><TH right>Còn lại</TH>
                </tr></thead>
                <tbody>
                  {rows.map((r, i) => {
                    const payroll = effPay(r); const paid = paidOf(r); const remain = payroll - paid;
                    return (
                    <React.Fragment key={r.bks + i}>
                    <tr style={{ cursor: "pointer" }} onClick={() => toggle(r.bks)}>
                      <TD>
                        <i className={"bi " + (open[r.bks] ? "bi-chevron-down" : "bi-chevron-right")} style={{ fontSize: 11, color: "var(--ink-4)", marginRight: 6 }} />
                        <span className="tnum" style={{ fontWeight: 700 }}>{r.bks}</span>
                      </TD>
                      <TD><div style={{ minWidth: 170 }} onClick={(e) => e.stopPropagation()}>
                        {locked
                          ? <span className="tnum" style={{ fontWeight: 600 }}>{r.driver || "—"}</span>
                          : <Combo value={r.driver || ""} onChange={(v) => set(i, { driver: v })} options={drivers} placeholder="Chọn lái…" small />}
                      </div></TD>
                      <TD right><span className="tnum">{r.days}</span></TD>
                      <TD right><span className="tnum">{r.trips}</span></TD>
                      <TD right><span className="tnum" style={{ color: "var(--ink-4)" }}>{fmtVND(r.paidDaily)}</span></TD>
                      <TD right><span className="tnum" style={{ fontWeight: 700, color: "var(--accent)" }}>{fmtVND(payroll)}</span></TD>
                      <TD right><span className="tnum" style={{ color: "var(--good)" }}>{fmtVND(paid)}</span></TD>
                      <TD right><span className="tnum" style={{ fontWeight: 700, color: remain > 0 ? "var(--danger)" : "var(--good)" }}>{fmtVND(remain)}</span></TD>
                    </tr>
                    {open[r.bks] && <tr><td colSpan={8} style={{ padding: 0, borderBottom: "1px solid var(--line-2)", background: "#fafbfc" }} onClick={(e) => e.stopPropagation()}>
                      <PayrollDetail row={r} />
                      <ExtraPayEditor items={r.extraPay} onChange={(arr) => set(i, { extraPay: arr })} disabled={locked} />
                      <PaymentsEditor payments={r.payments} onChange={(arr) => set(i, { payments: arr })} payroll={payroll} />
                    </td></tr>}
                    </React.Fragment>
                    );
                  })}
                  {rows.length === 0 && <tr><TD><span style={{ color: "var(--ink-4)" }}>Kỳ rỗng.</span></TD></tr>}
                </tbody>
                <tfoot>
                  <tr style={{ background: "#fafbfc" }}>
                    <TD><b>Tổng {rows.length} xe</b></TD><TD /><TD /><TD />
                    <TD right><span className="tnum" style={{ color: "var(--ink-4)" }}>{fmtVND(grandDaily)}</span></TD>
                    <TD right><span className="tnum" style={{ fontWeight: 800, fontSize: 15, color: "var(--accent)" }}>{fmtVND(grandPayroll)}</span></TD>
                    <TD right><span className="tnum" style={{ color: "var(--good)" }}>{fmtVND(grandPaid)}</span></TD>
                    <TD right><span className="tnum" style={{ fontWeight: 800, color: (grandPayroll - grandPaid) > 0 ? "var(--danger)" : "var(--good)" }}>{fmtVND(grandPayroll - grandPaid)}</span></TD>
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
