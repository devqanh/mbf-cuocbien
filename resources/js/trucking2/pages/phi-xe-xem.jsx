import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState } = React;
import { I, Btn, Txt, fmtVND, toNum } from "@trk/lib.jsx";
import { PayrollDetail, PaymentsEditor } from "@trk/components/payroll-detail.jsx";

const lbl = (t) => <div style={{ fontSize: 11, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>{t}</div>;
const TH = ({ children, right }) => <th style={{ textAlign: right ? "right" : "left", padding: "8px 10px", fontSize: 11, fontWeight: 700, color: "var(--ink-4)", textTransform: "uppercase", letterSpacing: ".03em", borderBottom: "1px solid var(--line)", whiteSpace: "nowrap" }}>{children}</th>;
const TD = ({ children, right }) => <td style={{ padding: "8px 10px", fontSize: 13, borderBottom: "1px solid var(--line-2)", textAlign: right ? "right" : "left", verticalAlign: "middle" }}>{children}</td>;

function ViewPayrollApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const back = () => { window.location.href = ROUTES.list; };
  const api = (method, url, body) => window.trkApi(method, url, body);
  const batch = B.batch || {};

  const [no, setNo] = useState(batch.no || "");
  const [name, setName] = useState(batch.name || "");
  const [rows, setRows] = useState((batch.rows || []).map((x) => ({ ...x, payments: Array.isArray(x.payments) ? x.payments : [] })));
  const [dirty, setDirty] = useState(false);
  const [open, setOpen] = useState({});
  const toggle = (bks) => setOpen((o) => ({ ...o, [bks]: !o[bks] }));

  const set = (i, np) => { setRows((rs) => rs.map((r, j) => (j === i ? { ...r, ...np } : r))); setDirty(true); };
  const paidOf = (r) => (r.payments || []).reduce((s, p) => s + toNum(p.amount), 0);
  const grandPayroll = rows.reduce((a, x) => a + (x.payroll || x.total || 0), 0);
  const grandDaily = rows.reduce((a, x) => a + (x.paidDaily || 0), 0);
  const grandPaid = rows.reduce((a, x) => a + paidOf(x), 0);

  const save = async () => {
    const res = await api("PUT", ROUTES.update + (batch.hashid || batch.id), { batch: { no, name, from: batch.from, to: batch.to, rows } });
    if (res && res.ok) { setDirty(false); window.trkToast && window.trkToast("Đã lưu kỳ lương"); return; }
    window.trkToast && window.trkToast("Lưu thất bại", "error"); return false;
  };
  const del = async () => {
    const ok = await window.confirmAction({
      title: "Xóa kỳ lương?", text: `Kỳ <b>${no}</b> · <b>${rows.length}</b> xe sẽ bị xóa vĩnh viễn.`,
      confirmText: '<i class="bi bi-trash me-1"></i> Xóa kỳ', danger: true,
    });
    if (!ok) return;
    const res = await api("DELETE", ROUTES.destroy + (batch.hashid || batch.id));
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
              <div style={{ fontSize: 15.5, fontWeight: 700, lineHeight: 1.1 }} className="tnum">Kỳ lương {no || ""}</div>
              <div style={{ fontSize: 12.5, color: "var(--ink-3)" }} className="tnum">{batch.from || "?"} → {batch.to || "?"} · {rows.length} xe</div>
            </div>
          </div>
          <div style={{ textAlign: "right", marginRight: 8 }}>
            <div style={{ fontSize: 11.5, color: "var(--ink-4)" }}>Lương phải trả</div>
            <div className="tnum" style={{ fontSize: 17, fontWeight: 700, color: "var(--accent)" }}>{fmtVND(grandPayroll)}</div>
          </div>
          {T.canEdit && <Btn variant="primary" onClick={save} disabled={!dirty}>Lưu</Btn>}
          {T.canDelete && <Btn onClick={del}><I.trash /></Btn>}
        </div>
      </header>

      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: "18px 22px 40px" }}>
        <div style={{ maxWidth: 1000, margin: "0 auto", display: "flex", flexDirection: "column", gap: 14 }}>
          <div style={{ display: "flex", gap: 10, alignItems: "flex-start", background: "#eff5ff", border: "1px solid #cfe0fb", borderRadius: 12, padding: "12px 14px", fontSize: 13, color: "#1f4f9e", lineHeight: 1.6 }}>
            <i className="bi bi-info-circle-fill" style={{ fontSize: 16, marginTop: 1, flexShrink: 0 }} />
            <div>Số liệu kỳ lương đã <b>chốt cố định</b> lúc tạo. <b>Lương phải trả</b> = khoản chưa "chi theo ngày" (gom 1 đợt); <b>đã chi theo ngày</b> chỉ tham khảo. Có thể sửa <b>lái xe nhận lương</b> rồi bấm Lưu.</div>
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
                    const payroll = r.payroll || r.total || 0; const paid = paidOf(r); const remain = payroll - paid;
                    return (
                    <React.Fragment key={r.bks + i}>
                    <tr style={{ cursor: "pointer" }} onClick={() => toggle(r.bks)}>
                      <TD>
                        <i className={"bi " + (open[r.bks] ? "bi-chevron-down" : "bi-chevron-right")} style={{ fontSize: 11, color: "var(--ink-4)", marginRight: 6 }} />
                        <span className="tnum" style={{ fontWeight: 700 }}>{r.bks}</span>
                      </TD>
                      <TD><div style={{ minWidth: 170 }} onClick={(e) => e.stopPropagation()}><Txt value={r.driver || ""} onChange={(v) => set(i, { driver: v })} placeholder="Lái xe…" /></div></TD>
                      <TD right><span className="tnum">{r.days}</span></TD>
                      <TD right><span className="tnum">{r.trips}</span></TD>
                      <TD right><span className="tnum" style={{ color: "var(--ink-4)" }}>{fmtVND(r.paidDaily)}</span></TD>
                      <TD right><span className="tnum" style={{ fontWeight: 700, color: "var(--accent)" }}>{fmtVND(payroll)}</span></TD>
                      <TD right><span className="tnum" style={{ color: "var(--good)" }}>{fmtVND(paid)}</span></TD>
                      <TD right><span className="tnum" style={{ fontWeight: 700, color: remain > 0 ? "var(--danger)" : "var(--good)" }}>{fmtVND(remain)}</span></TD>
                    </tr>
                    {open[r.bks] && <tr><td colSpan={8} style={{ padding: 0, borderBottom: "1px solid var(--line-2)", background: "#fafbfc" }}>
                      <PayrollDetail row={r} />
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
