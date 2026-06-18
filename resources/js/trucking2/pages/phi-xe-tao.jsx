import React from "react";
import { createRoot } from "react-dom/client";
import "@trk/shared.js";

const { useState } = React;
import { I, Btn, Txt, Combo, DateField, fmtVND } from "@trk/lib.jsx";

const lbl = (t) => <div style={{ fontSize: 11, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>{t}</div>;
const TH = ({ children, right }) => <th style={{ textAlign: right ? "right" : "left", padding: "8px 10px", fontSize: 11, fontWeight: 700, color: "var(--ink-4)", textTransform: "uppercase", letterSpacing: ".03em", borderBottom: "1px solid var(--line)", whiteSpace: "nowrap" }}>{children}</th>;
const TD = ({ children, right }) => <td style={{ padding: "8px 10px", fontSize: 13, borderBottom: "1px solid var(--line-2)", textAlign: right ? "right" : "left", verticalAlign: "middle" }}>{children}</td>;

function CreatePayrollApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {};
  const api = (method, url, body) => window.trkApi(method, url, body);
  const back = () => { window.location.href = ROUTES.list; };

  const [no, setNo] = useState("");
  const [name, setName] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [rows, setRows] = useState(null);     // [{bks,driver,type,axle,days,trips,paidDaily,payroll,total,lines}]
  const [drivers, setDrivers] = useState([]);
  const [loading, setLoading] = useState(false);

  const compute = () => {
    if (!from || !to) { window.trkToast && window.trkToast("Chọn khoảng ngày", "error"); return; }
    setLoading(true);
    const p = new URLSearchParams(); p.set("from", from); p.set("to", to);
    api("GET", ROUTES.compute + "?" + p.toString()).then((r) => {
      if (r && r.ok) {
        setRows((r.rows || []).map((x) => ({ ...x, total: x.payroll })));
        setDrivers((r.drivers || []).map((d) => (typeof d === "string" ? d : d.value || d.label)));
      }
      setLoading(false);
    }).catch(() => { setLoading(false); window.trkToast && window.trkToast("Lỗi tải dữ liệu", "error"); });
  };

  const set = (i, np) => setRows((rs) => rs.map((r, j) => (j === i ? { ...r, ...np } : r)));
  const grandPayroll = (rows || []).reduce((a, x) => a + (x.total || 0), 0);
  const grandDaily = (rows || []).reduce((a, x) => a + (x.paidDaily || 0), 0);

  const save = async () => {
    if (!rows || !rows.length) { window.trkToast && window.trkToast("Chưa có xe nào để lưu", "error"); return; }
    const ok = await window.confirmAction({
      title: "Lưu kỳ lương?",
      text: `Kỳ <b>${(no || "(tự sinh)")}</b> · <b>${rows.length}</b> xe · lương phải trả <b>${grandPayroll.toLocaleString("vi-VN")} ₫</b> sẽ được tạo.`,
      confirmText: '<i class="bi bi-save me-1"></i> Lưu kỳ',
    });
    if (!ok) return false;
    const payload = { no, name, from, to, rows };
    const res = await api("POST", ROUTES.store, { batch: payload });
    if (res && res.ok) { window.location.href = ROUTES.view + (res.batch.hashid || res.batch.id); return; }
    window.trkToast && window.trkToast("Lưu thất bại", "error"); return false;
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
            <div style={{ width: 32, height: 32, flexShrink: 0, borderRadius: 9, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center" }}><I.fx /></div>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontSize: 15.5, fontWeight: 700, lineHeight: 1.1 }}>Tạo kỳ lương lái xe</div>
              <div style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Chọn khoảng ngày → Tính → gom theo biển số xe → Lưu</div>
            </div>
          </div>
          {rows && rows.length > 0 && <div style={{ textAlign: "right", marginRight: 8 }}>
            <div style={{ fontSize: 11.5, color: "var(--ink-4)" }}>Lương phải trả · {rows.length} xe</div>
            <div className="tnum" style={{ fontSize: 17, fontWeight: 700, color: "var(--accent)" }}>{fmtVND(grandPayroll)}</div>
          </div>}
          {T.canEdit && rows && <Btn variant="primary" onClick={save}>Lưu kỳ</Btn>}
        </div>
      </header>

      <div style={{ flex: 1, minHeight: 0, overflowY: "auto", padding: "18px 22px 40px" }}>
        <div style={{ maxWidth: 1100, margin: "0 auto", display: "flex", flexDirection: "column", gap: 14 }}>
          {/* thông tin kỳ + chọn khoảng ngày */}
          <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, padding: "14px 16px", display: "flex", gap: 12, flexWrap: "wrap", alignItems: "flex-end" }}>
            <div style={{ width: 150 }}>{lbl("Số kỳ")}<Txt value={no} onChange={setNo} placeholder="Tự sinh LG-…" /></div>
            <div style={{ flex: 1, minWidth: 180 }}>{lbl("Tên / ghi chú kỳ")}<Txt value={name} onChange={setName} placeholder="VD: Lương lái xe tháng 6/2026" /></div>
            <div>{lbl("Từ ngày")}<DateField value={from} onChange={setFrom} /></div>
            <div>{lbl("đến ngày")}<DateField value={to} onChange={setTo} /></div>
            <Btn variant="primary" onClick={compute}>{loading ? "Đang tính…" : "Tính"}</Btn>
          </div>

          {rows === null ? (
            <div style={{ padding: "30px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5 }}>Chọn <b>khoảng ngày</b> rồi bấm <b>Tính</b> để gom lương theo từng biển số xe.</div>
          ) : rows.length === 0 ? (
            <div style={{ padding: "30px", textAlign: "center", color: "var(--ink-4)", fontSize: 13.5 }}>Không có xe nào hoạt động trong khoảng ngày này.</div>
          ) : (
            <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, overflow: "hidden" }}>
              <div style={{ padding: "10px 14px", borderBottom: "1px solid var(--line-2)", fontSize: 12, color: "var(--ink-4)" }}>
                <i className="bi bi-info-circle" /> <b>Lương phải trả</b> = các khoản CHƯA "chi theo ngày" (gom trả 1 đợt). <b>Đã chi theo ngày</b> chỉ để tham khảo (đã thanh toán ở Lộ trình). Lái xe tự gán theo thời điểm — sửa được nếu cần.
              </div>
              <div style={{ overflowX: "auto" }}>
                <table style={{ width: "100%", borderCollapse: "collapse" }}>
                  <thead><tr>
                    <TH>Biển số xe</TH><TH>Lái xe nhận lương</TH><TH right>Ngày</TH><TH right>Chuyến</TH>
                    <TH right>Đã chi theo ngày</TH><TH right>Lương phải trả</TH>
                  </tr></thead>
                  <tbody>
                    {rows.map((r, i) => (
                      <tr key={r.bks}>
                        <TD>
                          <span className="tnum" style={{ fontWeight: 700 }}>{r.bks}</span>
                          {(r.axle === "1" || r.axle === "2") && <span style={{ marginLeft: 6, fontSize: 10, fontWeight: 700, color: "var(--accent)", background: "var(--accent-weak)", padding: "1px 6px", borderRadius: 999 }}>{r.axle} cầu</span>}
                        </TD>
                        <TD><div style={{ minWidth: 180 }}><Combo value={r.driver || ""} onChange={(v) => set(i, { driver: v })} options={drivers} placeholder="Chọn lái…" small /></div></TD>
                        <TD right><span className="tnum">{r.days}</span></TD>
                        <TD right><span className="tnum">{r.trips}</span></TD>
                        <TD right><span className="tnum" style={{ color: "var(--ink-4)" }}>{fmtVND(r.paidDaily)}</span></TD>
                        <TD right><span className="tnum" style={{ fontWeight: 700, color: "var(--accent)" }}>{fmtVND(r.total)}</span></TD>
                      </tr>
                    ))}
                  </tbody>
                  <tfoot>
                    <tr style={{ background: "#fafbfc" }}>
                      <TD><b>Tổng {rows.length} xe</b></TD><TD /><TD /><TD />
                      <TD right><span className="tnum" style={{ color: "var(--ink-4)" }}>{fmtVND(grandDaily)}</span></TD>
                      <TD right><span className="tnum" style={{ fontWeight: 800, fontSize: 15, color: "var(--accent)" }}>{fmtVND(grandPayroll)}</span></TD>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

createRoot(document.getElementById("trk-root")).render(<CreatePayrollApp />);
