import React from "react";
import { fmtVND, fmtNum, fmtShort } from "@trk/lib.jsx";
import { card, pctOf, CardTitle, Delta, KPI, Empty, TopList } from "@trk/components/report-ui.jsx";
import { Donut, PALETTE } from "@trk/components/charts.jsx";

/* TAB VĂN PHÒNG — chi phí quản lý doanh nghiệp (thuê VP, điện nước, VPP, lương khối VP…) cho sếp/kế toán:
   tháng này chi bao nhiêu & so tháng trước · chiếm bao nhiêu % tổng chi phí / doanh thu (tỷ lệ overhead) ·
   tiền đi vào loại nào · khoản trả trước đang phân bổ · định kỳ sắp đến hạn · danh sách phiếu. */

const money = (n) => fmtVND(n || 0);
const th = (label, al, w) => <th key={label} style={{ textAlign: al || "left", padding: "9px 12px", fontSize: 10.5, fontWeight: 700, color: "var(--ink-3)", textTransform: "uppercase", letterSpacing: ".03em", borderBottom: "1px solid var(--line)", whiteSpace: "nowrap", background: "#fafbfc", width: w }}>{label}</th>;
const td = { padding: "9px 12px", borderBottom: "1px solid var(--line-2)", verticalAlign: "top" };
const tdR = { ...td, textAlign: "right", whiteSpace: "nowrap" };
const STATUS = { pending: ["Chờ duyệt", "var(--warn)", "#fff4e0"], pay: ["Chờ thanh toán", "var(--accent)", "var(--accent-weak)"], paid: ["Đã thanh toán", "var(--good)", "#e6f6ec"], cancelled: ["Đã hủy", "var(--ink-4)", "var(--line-2)"] };

/* Cột 12 tháng: chi tiêu (đậm) + ghi nhận sau phân bổ (đường mảnh) — thấy ngay tháng bất thường. */
function TrendBars({ rows, curYm }) {
  const max = Math.max(1, ...rows.map((r) => Math.max(r.spent, r.accrual)));
  return (
    <div style={{ display: "grid", gridTemplateColumns: `repeat(${rows.length}, 1fr)`, gap: 6, alignItems: "end", height: 150, paddingTop: 8 }}>
      {rows.map((r) => {
        const on = r.ym === curYm;
        return (
          <div key={r.ym} title={`${r.label}: chi tiêu ${money(r.spent)} · ghi nhận ${money(r.accrual)} · ${r.count} phiếu`} style={{ display: "flex", flexDirection: "column", alignItems: "center", gap: 4, height: "100%", justifyContent: "flex-end" }}>
            <span className="tnum" style={{ fontSize: 10, color: on ? "var(--ink)" : "var(--ink-4)", fontWeight: on ? 800 : 500 }}>{r.spent ? fmtShort(r.spent) : ""}</span>
            <div style={{ position: "relative", width: "100%", height: Math.max(r.spent ? 3 : 0, r.spent / max * 100) + "%", background: on ? "#0f766e" : "#8fd3c8", borderRadius: "4px 4px 0 0" }}>
              {r.accrual > 0 && <div style={{ position: "absolute", left: -2, right: -2, bottom: (r.accrual / max * 100) - (r.spent / max * 100) + "%", borderTop: "2px dashed #0f766e", opacity: .7, transform: "translateY(-100%)" }} />}
            </div>
            <span className="tnum" style={{ fontSize: 10, color: on ? "var(--ink-2)" : "var(--ink-4)", fontWeight: on ? 700 : 500 }}>{r.label.slice(0, 2)}</span>
          </div>
        );
      })}
    </div>
  );
}

export function OfficeTab({ rep, prev, prevLabel, isMobile, routes }) {
  const o = rep.office || {};
  const total = rep.totalCost || 0, revenue = rep.revenue || 0;
  const prevO = (prev && prev.office) || null;
  const byType = o.byType || [];
  const donut = byType.slice(0, 8).map((t, i) => ({ label: t.label, value: t.amount, color: PALETTE[i % PALETTE.length] }));
  const curYm = `${rep.year}-${String(rep.month).padStart(2, "0")}`;
  const overCost = pctOf(o.spent, total), overRev = pctOf(o.spent, revenue);
  const officeUrl = routes && routes.office ? routes.office : null;
  const vsAvg = o.avg6 ? Math.round((o.spent - o.avg6) * 100 / o.avg6) : null;

  if (!o.hasEntity && !(o.spent || (o.slips || []).length)) {
    return (
      <div style={{ ...card, textAlign: "center", padding: 40 }}>
        <i className="bi bi-building" style={{ fontSize: 34, color: "var(--ink-4)" }} />
        <div style={{ fontSize: 15, fontWeight: 700, marginTop: 10 }}>Chưa có chi phí văn phòng nào</div>
        <div style={{ fontSize: 12.5, color: "var(--ink-4)", marginTop: 4 }}>Nhập phiếu ở Quản lý xe → tab <b>Chi phí văn phòng</b> (thuê VP, điện nước, internet, VPP, lương khối văn phòng…) rồi quay lại đây.</div>
        {officeUrl && <a href={officeUrl} style={{ display: "inline-block", marginTop: 14, fontSize: 13, fontWeight: 700, color: "var(--accent)" }}>Mở Chi phí văn phòng →</a>}
      </div>
    );
  }

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
      {/* ---- KPI ---- */}
      <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
        <KPI label="Chi tiêu văn phòng tháng này" value={money(o.spent)} color="#0f766e" cur={o.spent} prev={prevO ? prevO.spent : null} goodWhen="down"
          sub={`${o.count || 0} phiếu theo ngày chi — khớp nhóm “Chi phí văn phòng” ở Tổng quan${vsAvg != null ? ` · ${vsAvg >= 0 ? "+" : ""}${vsAvg}% so TB 6 tháng` : ""}`} />
        <KPI label="Tỷ lệ overhead" value={total ? `${overCost}%` : "—"} color={overCost > 15 ? "var(--warn)" : "var(--ink)"}
          sub={total ? `của tổng chi phí ${fmtShort(total)} · ${revenue ? overRev + "% doanh thu" : "chưa có doanh thu"}` : "chưa có chi phí trong tháng"}
          hint="Chi phí quản lý / tổng chi phí. Vận tải nhỏ thường 8–15%; cao hơn kéo dài là dấu hiệu cần xem lại." />
        <KPI label="Ghi nhận sau phân bổ" value={money(o.accrual)} cur={o.accrual} prev={prevO ? prevO.accrual : null} goodWhen="down"
          sub="phiếu thường + phần trả trước rải đều theo tháng — đúng chi phí của tháng" />
        <KPI label="Đang treo" value={`${(o.pending && o.pending.count) || 0} chờ duyệt · ${(o.toPay && o.toPay.count) || 0} chờ TT`}
          color={(o.pending && o.pending.count) ? "var(--warn)" : "var(--ink)"}
          sub={`${money((o.pending && o.pending.amount) || 0)} chưa duyệt · ${money((o.toPay && o.toPay.amount) || 0)} chưa chi`} />
      </div>

      {/* ---- Cơ cấu theo loại + nhà cung cấp ---- */}
      <div style={{ display: "flex", gap: 12, flexWrap: "wrap" }}>
        <div style={{ ...card, flex: 1.4, minWidth: 320 }}>
          <CardTitle icon="bi-pie-chart" sub="theo danh mục Loại chi phí văn phòng · tháng này">Tiền đi vào loại nào</CardTitle>
          {byType.length === 0 ? <Empty>Không có phiếu chi văn phòng trong tháng.</Empty> : (
            <div style={{ display: "flex", gap: 18, alignItems: "center", flexWrap: isMobile ? "wrap" : "nowrap" }}>
              <Donut data={donut} size={170} thick={26} />
              <div style={{ flex: 1, minWidth: 220, display: "flex", flexDirection: "column", gap: 6 }}>
                {byType.map((t, i) => (
                  <div key={t.label} style={{ display: "grid", gridTemplateColumns: "12px 1fr 52px 110px", gap: 8, alignItems: "center", fontSize: 12.5 }}>
                    <span style={{ width: 10, height: 10, borderRadius: 3, background: PALETTE[i % PALETTE.length] }} />
                    <span style={{ minWidth: 0, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }} title={t.label}>{t.label} <span style={{ color: "var(--ink-4)", fontSize: 11 }}>· {t.count} phiếu</span></span>
                    <span className="tnum" style={{ textAlign: "right", color: "var(--ink-4)" }}>{t.pct}%</span>
                    <span className="tnum" style={{ textAlign: "right", fontWeight: 700 }}>{money(t.amount)}</span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
        <TopList title="Nhà cung cấp lớn nhất" icon="bi-shop" sub="tháng này" data={(o.bySupplier || []).map((s) => ({ label: s.label, count: s.amount }))} fmt={(n) => money(n)} max={8} />
      </div>

      {/* ---- Xu hướng 12 tháng ---- */}
      <div style={card}>
        <CardTitle icon="bi-bar-chart-line" sub="cột = chi tiêu theo ngày chi · gạch đứt = ghi nhận sau phân bổ trả trước">Xu hướng 12 tháng</CardTitle>
        <TrendBars rows={o.trend || []} curYm={curYm} />
      </div>

      {/* ---- Trả trước đang phân bổ + Định kỳ sắp đến hạn ---- */}
      <div style={{ display: "flex", gap: 12, flexWrap: "wrap" }}>
        <div style={{ ...card, flex: 1, minWidth: 320 }}>
          <CardTitle icon="bi-pie-chart-fill" sub="bảo hiểm, thuê trả trước… chia đều theo tháng">Khoản trả trước đang phân bổ</CardTitle>
          {(o.alloc || []).length === 0 ? <Empty>Không có khoản nào đang phân bổ trong tháng.</Empty> : (
            <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
              {(o.alloc || []).map((a) => (
                <div key={a.id} style={{ fontSize: 12.5 }}>
                  <div style={{ display: "flex", justifyContent: "space-between", gap: 10 }}>
                    <span style={{ fontWeight: 600, minWidth: 0, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{a.name}{a.detail && a.detail !== a.name ? <span style={{ color: "var(--ink-4)", fontWeight: 400 }}> · {a.detail}</span> : ""}</span>
                    <span className="tnum" style={{ fontWeight: 700, whiteSpace: "nowrap" }}>{money(a.perMonth)}<span style={{ color: "var(--ink-4)", fontWeight: 400 }}>/tháng</span></span>
                  </div>
                  <div style={{ display: "flex", alignItems: "center", gap: 8, marginTop: 4 }}>
                    <div style={{ flex: 1, height: 8, background: "var(--line-2)", borderRadius: 999, overflow: "hidden" }}><div style={{ width: (a.doneMonths / a.months * 100) + "%", height: "100%", background: "#0f766e" }} /></div>
                    <span className="tnum" style={{ fontSize: 11, color: "var(--ink-4)", whiteSpace: "nowrap" }}>{a.doneMonths}/{a.months} th · {a.from}→{a.to} · còn {money(a.remainAmount)}</span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
        <div style={{ ...card, flex: 1, minWidth: 320 }}>
          <CardTitle icon="bi-alarm" sub="khoản định kỳ có hạn trong 45 ngày tới (hoặc đã quá hạn)">Sắp đến hạn gia hạn</CardTitle>
          {(o.due || []).length === 0 ? <Empty>Không có khoản định kỳ nào sắp đến hạn.</Empty> : (
            <div style={{ display: "flex", flexDirection: "column", gap: 7 }}>
              {(o.due || []).map((d) => {
                const late = d.daysLeft < 0, soon = d.daysLeft <= 7;
                return (
                  <div key={d.id} style={{ display: "grid", gridTemplateColumns: "1fr auto auto", gap: 10, alignItems: "center", fontSize: 12.5 }}>
                    <span style={{ minWidth: 0, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}><b>{d.name}</b>{d.supplier ? <span style={{ color: "var(--ink-4)" }}> · {d.supplier}</span> : ""}</span>
                    <span className="tnum" style={{ fontWeight: 700, whiteSpace: "nowrap" }}>{money(d.amount)}</span>
                    <span className="tnum" style={{ fontSize: 11, fontWeight: 700, padding: "2px 8px", borderRadius: 999, whiteSpace: "nowrap", color: late ? "var(--danger)" : soon ? "var(--warn)" : "var(--ink-3)", background: late ? "#fce8e8" : soon ? "#fff4e0" : "var(--line-2)" }}>
                      {late ? `quá hạn ${-d.daysLeft} ngày` : d.daysLeft === 0 ? "hôm nay" : `còn ${d.daysLeft} ngày`} · {d.dueDate.split("-").reverse().join("/")}
                    </span>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>

      {/* ---- Danh sách phiếu trong tháng ---- */}
      <div style={{ background: "#fff", border: "1px solid var(--line)", borderRadius: 12, overflow: "hidden" }}>
        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: "12px 16px 0" }}>
          <CardTitle icon="bi-receipt" sub={`${(o.slips || []).length} phiếu · theo ngày chi trong tháng`}>Phiếu chi văn phòng tháng này</CardTitle>
          {officeUrl && <a href={officeUrl} style={{ fontSize: 12.5, fontWeight: 700, color: "var(--accent)", whiteSpace: "nowrap" }}>Mở Chi phí văn phòng →</a>}
        </div>
        <div style={{ overflowX: "auto" }}>
          <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13, minWidth: 820 }}>
            <thead><tr>{th("Ngày chi", "left", 96)}{th("# HĐ", "left", 84)}{th("Loại · Diễn giải")}{th("Nhà cung cấp")}{th("Số tiền", "right", 120)}{th("Trạng thái", "left", 130)}</tr></thead>
            <tbody>
              {(o.slips || []).length === 0 && <tr><td colSpan={6} style={{ padding: 34, textAlign: "center", color: "var(--ink-4)" }}>Không có phiếu chi văn phòng trong tháng.</td></tr>}
              {(o.slips || []).map((s) => {
                const st = STATUS[s.status] || STATUS.pending;
                return (
                  <tr key={s.id}>
                    <td className="tnum" style={{ ...td, whiteSpace: "nowrap" }}>{s.spendDate.split("-").reverse().join("/")}</td>
                    <td className="tnum" style={{ ...td, color: "var(--accent)", fontWeight: 600, whiteSpace: "nowrap" }}>{s.invoiceNo || "—"}</td>
                    <td style={td}>
                      <div style={{ fontWeight: 600 }}>{s.type}</div>
                      <div style={{ fontSize: 11.5, color: "var(--ink-4)", display: "flex", gap: 6, flexWrap: "wrap" }}>
                        {s.name && s.name !== s.type && <span>{s.name}</span>}
                        {s.recurring && <span style={{ color: "var(--accent)" }}><i className="bi bi-arrow-repeat" /> định kỳ{s.dueDate ? ` đến ${s.dueDate.split("-").reverse().join("/")}` : ""}</span>}
                        {s.alloc && <span style={{ color: "#0f766e" }}><i className="bi bi-pie-chart" /> phân bổ {s.allocMonths} th</span>}
                        {s.note && <span title={s.note}>· {s.note}</span>}
                      </div>
                    </td>
                    <td style={td}>{s.supplier || <span style={{ color: "var(--ink-4)" }}>—</span>}</td>
                    <td className="tnum" style={{ ...tdR, fontWeight: 700 }}>{fmtNum(s.amount)}</td>
                    <td style={td}><span style={{ fontSize: 11, fontWeight: 700, color: st[1], background: st[2], padding: "2px 9px", borderRadius: 999, whiteSpace: "nowrap" }}>{s.statusLabel || st[0]}</span></td>
                  </tr>
                );
              })}
            </tbody>
            {(o.slips || []).length > 0 && (
              <tfoot><tr style={{ background: "#fafbfc" }}>
                <td colSpan={4} style={{ padding: "11px 12px", fontWeight: 800, borderTop: "2px solid var(--line)" }}>TỔNG · {(o.slips || []).length} phiếu{prevO ? <span style={{ marginLeft: 10, fontWeight: 400 }}><Delta cur={o.spent} prev={prevO.spent} goodWhen="down" label={prevLabel ? `so ${prevLabel}` : undefined} /></span> : null}</td>
                <td className="tnum" style={{ ...tdR, borderTop: "2px solid var(--line)", fontWeight: 800, color: "#0f766e" }}>{fmtNum(o.spent)}</td>
                <td style={{ ...td, borderTop: "2px solid var(--line)" }} />
              </tr></tfoot>
            )}
          </table>
        </div>
      </div>
    </div>
  );
}
