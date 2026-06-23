import React from "react";
const { useState, useRef, useMemo, useEffect } = React;
import { I, Money, Payer, Txt, Combo, MultiCombo, DateField, Num, Line, Section, Modal, Btn, fmtVND, fmtNum, fmtShort, calcCost, calcVeh, calcRev, calcVehICD, calcRevICD, calcFreeTime, fmtHours, toNum, useIsMobile } from "@trk/lib.jsx";
import { DTField, Field, DriverSpendRows, VatLine, ItemRows, ChiHoRows, DoanhThuRows, ChkBox, TRACK_COLORS, SWATCHES, colorHex, FlagPicker, CostLineRows, PaymentRows, Seg } from "./shared.jsx";

// Giờ hiện tại dạng DTField ("YYYY-MM-DDTHH:MM", giờ địa phương) cho nút "Bây giờ".
const nowLocalDT = () => { const d = new Date(); const p = (n) => String(n).padStart(2, "0"); return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`; };

// Địa điểm dạng select2: giá trị = tên (giữ nguyên dữ liệu), nhãn = "Tên - Ký hiệu" để dễ nhận diện + tìm theo ký hiệu.
// LƯU theo KÝ HIỆU (value=mã) để link/tham chiếu bền; HIỆN "Tên — Mã" cho dễ đọc.
// Địa điểm chưa có mã → tạm dùng tên làm value.
const locOptions = (cfg) => (cfg.locations || []).map((n) => {
  const c = (cfg.locationCode || {})[n];
  return c ? { value: c, label: `${n} — ${c}` } : { value: n, label: n };
});
// Kho (nhà máy): danh sách MÃ kho DEDUPE (1 ký hiệu có thể nhiều tên → chỉ hiện 1 mã); MultiCombo lưu chuỗi = mã.
const whCodes = (cfg) => [...new Set((cfg.warehouses || []).map((n) => (cfg.warehouseCode || {})[n] || n).filter(Boolean))];

function CostPopup({ ship, patch, onSave, isDirty, onClose, cfg = {}, addCfg }) {
  const payerOpts = cfg.payers || [];
  const costOpts = cfg.costItems || [];
  const prices = cfg.prices || {};
  const addPayer = (v) => addCfg && addCfg("payers", v);
  const addCostItem = (v) => addCfg && addCfg("costItems", v);
  const [showFx, setShowFx] = useState(false);
  const c = ship.cost || {};
  const setC = (np) => patch({ cost: { ...c, ...np } });
  const cc = calcCost(c);
  const items = c.items || [];
  const setItems = (arr) => setC({ items: arr });
  const dirty = !!(isDirty && isDirty(ship.id));
  const [saving, setSaving] = useState(false);
  const handleSave = () => { if (saving) return; setSaving(true); Promise.resolve(onSave && onSave()).then(() => onClose()).catch(() => setSaving(false)); };

  const footer = (
    <div style={{ display: "flex", alignItems: "flex-end", justifyContent: "space-between", gap: 20 }}>
      <div style={{ display: "flex", gap: 24 }}>
        <div>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 2 }}>Chi phí công ty</div>
          <div className="tnum" style={{ fontSize: 16, fontWeight: 700, color: "var(--ink-2)" }}>{fmtVND(cc.congTy)}</div>
        </div>
        <div style={{ borderLeft: "1px solid var(--line)", paddingLeft: 24 }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 2 }}>Chi hộ (thu lại khách)</div>
          <div className="tnum" style={{ fontSize: 16, fontWeight: 700, color: "var(--good)" }}>{fmtVND(cc.thuChiHo)}</div>
        </div>
        <div style={{ borderLeft: "1px solid var(--line)", paddingLeft: 24 }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 2, display: "flex", alignItems: "center", gap: 6 }}>
            Tổng chi phí
            <button type="button" onClick={() => setShowFx((s) => !s)} title="Xem công thức"
              style={{ display: "inline-grid", placeItems: "center", width: 18, height: 18, border: "none", borderRadius: 5, background: showFx ? "var(--accent-weak)" : "transparent", color: showFx ? "var(--accent)" : "var(--ink-4)", cursor: "pointer" }}><I.fx /></button>
          </div>
          <div className="tnum" style={{ fontSize: 22, fontWeight: 700, letterSpacing: "-0.02em" }}>{fmtVND(cc.tongChiPhi)}</div>
        </div>
      </div>
      <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
        {dirty && <span style={{ fontSize: 12, color: "var(--warn)", fontWeight: 600, display: "inline-flex", alignItems: "center", gap: 5 }}><span style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} />Có thay đổi chưa lưu</span>}
        <Btn onClick={onClose}>Đóng</Btn>
        <Btn variant="primary" onClick={handleSave} disabled={!dirty || saving}>{saving ? "Đang lưu…" : "Lưu chi phí"}</Btn>
      </div>
    </div>
  );

  return (
    <Modal title="Chi phí lô hàng" subtitle={<>Lô <b style={{ color: "var(--ink-2)" }}>{ship.booking}</b> · {ship.customer} · gom mọi khoản chi phí phân bổ vào một nơi</>}
      onClose={onClose} footer={footer} width={960}>

      {showFx && (
        <div style={{ margin: "12px 0 2px", padding: "10px 13px", background: "var(--accent-weak-2)", border: "1px solid var(--accent-weak)", borderRadius: 10, fontSize: 12.5, color: "var(--ink-2)", lineHeight: 1.6 }}>
          <b style={{ color: "var(--accent)" }}>Tổng chi phí</b> = cộng tất cả các khoản. Khoản tích <b style={{ color: "var(--good)" }}>“Chi hộ khách”</b> là phần sẽ thu lại của khách (chi hộ); khoản không tích là <b>chi phí công ty</b> tự chịu.
          <br /><span style={{ color: "var(--ink-3)" }}>Cột “Người chi” chỉ ghi ai ứng/chi khoản đó, không cộng vào tổng.</span>
        </div>
      )}

      <CostLineRows rows={items} onChange={setItems} options={costOpts} onCreate={addCostItem}
        payers={payerOpts} onCreatePayer={addPayer} prices={prices} costColors={cfg.costColors || {}} />
    </Modal>
  );
}


function RevenuePopup({ ship, patch, onSave, isDirty, onClose, cfg = {}, addCfg }) {
  const r = ship.rev || {};
  const setR = (np) => patch({ rev: { ...r, ...np } });
  const rc = calcRev(r);
  const paid = rc.conNo <= 0 && rc.phaiThu > 0;
  const choHo = r.choHo || [];
  const choHoOpts = cfg.choHoItems || [];
  const setChoHo = (arr) => setR({ choHo: arr });
  const dirty = !!(isDirty && isDirty(ship.id));
  const [saving, setSaving] = useState(false);
  const handleSave = () => { if (saving) return; setSaving(true); Promise.resolve(onSave && onSave()).then(() => onClose()).catch(() => setSaving(false)); };

  const footer = (
    <div style={{ display: "flex", alignItems: "flex-end", justifyContent: "space-between", gap: 20 }}>
      <div style={{ display: "flex", gap: 26 }}>
        <div>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 2 }}>Tổng phải thu</div>
          <div className="tnum" style={{ fontSize: 22, fontWeight: 700, letterSpacing: "-0.02em" }}>{fmtVND(rc.phaiThu)}</div>
        </div>
        <div style={{ borderLeft: "1px solid var(--line)", paddingLeft: 26 }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 2 }}>Còn nợ</div>
          <div className="tnum" style={{ fontSize: 16, fontWeight: 700, color: rc.conNo > 0 ? "var(--warn)" : "var(--good)" }}>{fmtVND(Math.max(0, rc.conNo))}</div>
        </div>
      </div>
      <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
        {dirty && <span style={{ fontSize: 12, color: "var(--warn)", fontWeight: 600, display: "inline-flex", alignItems: "center", gap: 5 }}><span style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} />Có thay đổi chưa lưu</span>}
        <Btn onClick={onClose}>Đóng</Btn>
        <Btn variant="primary" onClick={handleSave} disabled={!dirty || saving}>{saving ? "Đang lưu…" : "Lưu doanh thu"}</Btn>
      </div>
    </div>
  );

  return (
    <Modal title="Doanh thu & công nợ" subtitle={<>Lô <b style={{ color: "var(--ink-2)" }}>{ship.booking}</b> · {ship.customer}</>} onClose={onClose} footer={footer} width={820}>
      <Section title="Doanh thu" total={rc.tongDT} totalLabel="Tổng doanh thu">
        <DoanhThuRows rows={r.doanhThu || []} onChange={(arr) => setR({ doanhThu: arr })} options={cfg.revItems || []} onCreate={(v) => addCfg && addCfg("revItems", v)} prices={cfg.prices || {}} />
        <VatLine rate={r.vatRate == null ? "8" : r.vatRate} vat={rc.vat} onRate={(x) => setR({ vatRate: x })} />
      </Section>

      <Section title="Thu chi hộ (thu lại của khách)" total={(choHo).reduce((s,e)=>s+toNum(e.amount),0)} totalLabel="Tổng chi hộ">
        {(ship.cost?.items || []).filter((e) => e.billable).length > 0 && (
          <button type="button" onClick={() => setChoHo((ship.cost.items || []).filter((e) => e.billable).map((e) => ({ id: Date.now() + Math.random(), item: e.item, amount: e.amount })))}
            style={{ display: "inline-flex", alignItems: "center", gap: 6, margin: "6px 0 0", padding: "5px 10px", background: "var(--accent-weak)", border: "none", cursor: "pointer", color: "var(--accent)", fontSize: 12.5, fontWeight: 600, borderRadius: 8 }}
            title="Lấy các khoản đã tích 'chi hộ khách' ở popup Chi phí">
            <I.fx /> Lấy từ chi phí ({(ship.cost.items || []).filter((e) => e.billable).length} khoản)
          </button>
        )}
        <ChiHoRows rows={choHo} onChange={setChoHo} options={choHoOpts} onCreate={(v) => addCfg && addCfg("choHoItems", v)} prices={cfg.prices || {}} />
      </Section>

      <Section title="Thanh toán" total={rc.daTT} totalLabel="Đã thu">
        <div style={{ padding: "10px 0 4px", maxWidth: 320 }}>
          <Field label="Hạn thanh toán"><DateField value={r.hanTT} onChange={(x) => setR({ hanTT: x })} /></Field>
        </div>
        <div style={{ fontSize: 11.5, color: "var(--ink-4)", padding: "2px 0 0" }}>Khách trả nhiều đợt — thêm từng lần với số tiền và ngày.</div>
        <PaymentRows payments={r.payments || []} onChange={(arr) => setR({ payments: arr })} />
        <div style={{ display: "flex", alignItems: "center", gap: 10, padding: "10px 0 8px" }}>
          <span style={{ fontSize: 12.5, fontWeight: 600, color: paid ? "var(--good)" : "var(--warn)", background: paid ? "var(--good-weak)" : "var(--warn-weak)", padding: "4px 11px", borderRadius: 999 }}>
            {rc.phaiThu === 0 ? "Chưa có doanh thu" : paid ? "Đã thu đủ" : `Còn nợ ${fmtVND(rc.conNo)}`}
          </span>
          {(r.payments || []).length > 0 && <span style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Đã thu {(r.payments || []).length} đợt: <b className="tnum" style={{ color: "var(--good)" }}>{fmtVND(rc.daTT)}</b></span>}
        </div>
        <Field label="Ghi chú kế toán"><Txt value={r.ghiChu} onChange={(x) => setR({ ghiChu: x })} placeholder="Ghi chú…" /></Field>
      </Section>
    </Modal>
  );
}

/* ===================== ICD — CHI PHÍ CHUYẾN XE ===================== */

function CostPopupICD({ ship, patch, onSave, isDirty, onClose, cfg = {}, addCfg }) {
  const isMobile = useIsMobile();
  const v = ship.veh || {};
  const setV = (np) => patch({ veh: { ...v, ...np } });
  const tong = calcVehICD(v);
  const dirty = !!(isDirty && isDirty(ship.id));
  const [saving, setSaving] = useState(false);
  const handleSave = () => { if (saving) return; setSaving(true); Promise.resolve(onSave && onSave()).then(() => onClose()).catch(() => setSaving(false)); };
  const footer = (
    <div style={{ display: "flex", alignItems: "flex-end", justifyContent: "space-between", gap: 20 }}>
      <div>
        <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 2 }}>Tổng chi phí chuyến xe</div>
        <div className="tnum" style={{ fontSize: 22, fontWeight: 700, letterSpacing: "-0.02em" }}>{fmtVND(tong)}</div>
      </div>
      <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
        {dirty && <span style={{ fontSize: 12, color: "var(--warn)", fontWeight: 600, display: "inline-flex", alignItems: "center", gap: 5 }}><span style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} />Có thay đổi chưa lưu</span>}
        <Btn onClick={onClose}>Đóng</Btn>
        <Btn variant="primary" onClick={handleSave} disabled={!dirty || saving}>{saving ? "Đang lưu…" : "Lưu chi phí"}</Btn>
      </div>
    </div>
  );
  return (
    <Modal title="Chi phí chuyến xe" subtitle={<>Lô <b style={{ color: "var(--ink-2)" }}>{ship.booking}</b> · {ship.customer}</>} onClose={onClose} footer={footer} width={760}>
      <Section title="Xe chạy">
        <div style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr" : "1fr 1fr", gap: 12, padding: "8px 0" }}>
          <Field label="Biển số xe" hint="danh mục"><Combo value={v.bienSo} onChange={(x) => setV({ bienSo: x })} options={cfg.vehicles || []} onCreate={(x) => addCfg && addCfg("vehicles", x)} placeholder="15C-123.45…" /></Field>
          <Field label="Lái xe" hint="danh mục"><Combo value={v.laiXe} onChange={(x) => setV({ laiXe: x })} options={cfg.drivers || []} onCreate={(x) => addCfg && addCfg("drivers", x)} placeholder="Chọn lái xe…" /></Field>
        </div>
      </Section>
      <Section title="Chi phí chuyến xe" total={tong} totalLabel="Tổng chi phí">
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, padding: "10px 0" }}>
          <Field label="Phụ cấp tiền đường"><Money value={v.phuCapTienDuong} onChange={(x) => setV({ phuCapTienDuong: x })} dim /></Field>
          <Field label="Trợ cấp"><Money value={v.troCap} onChange={(x) => setV({ troCap: x })} dim /></Field>
          <Field label="Lương"><Money value={v.luong} onChange={(x) => setV({ luong: x })} dim /></Field>
          <Field label="Chi phí khác"><Money value={v.chiPhiKhac} onChange={(x) => setV({ chiPhiKhac: x })} dim /></Field>
        </div>
      </Section>
      <Section title="Nhiên liệu & quãng đường">
        <div style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr 1fr" : "1fr 1fr 1fr", gap: 12, padding: "10px 0" }}>
          <Field label="Quãng đường"><Num value={v.km} onChange={(x) => setV({ km: x })} suffix="km" /></Field>
          <Field label="Số lít"><Num value={v.lit} onChange={(x) => setV({ lit: x })} suffix="L" /></Field>
          <Field label="Đơn giá dầu"><Money value={v.donGia} onChange={(x) => setV({ donGia: x })} dim /></Field>
        </div>
        <div style={{ fontSize: 12, color: "var(--ink-3)", padding: "2px 0 8px" }}>Tiền dầu = Lít × Đơn giá = <b className="tnum" style={{ color: "var(--ink-2)" }}>{fmtVND(toNum(v.lit) * toNum(v.donGia))}</b></div>
      </Section>
    </Modal>
  );
}

/* ===================== ICD — DOANH THU ===================== */

function RevenuePopupICD({ ship, patch, onSave, isDirty, onClose, cfg = {}, addCfg }) {
  const r = ship.rev || {};
  const setR = (np) => patch({ rev: { ...r, ...np } });
  const rc = calcRevICD(r);
  const paid = rc.conNo <= 0 && rc.phaiThu > 0;
  const choHo = r.choHo || [];
  const choHoOpts = cfg.choHoItems || [];
  const setChoHo = (arr) => setR({ choHo: arr });
  const dirty = !!(isDirty && isDirty(ship.id));
  const [saving, setSaving] = useState(false);
  const handleSave = () => { if (saving) return; setSaving(true); Promise.resolve(onSave && onSave()).then(() => onClose()).catch(() => setSaving(false)); };
  const footer = (
    <div style={{ display: "flex", alignItems: "flex-end", justifyContent: "space-between", gap: 20 }}>
      <div style={{ display: "flex", gap: 26 }}>
        <div>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 2 }}>Tổng phải thu</div>
          <div className="tnum" style={{ fontSize: 22, fontWeight: 700, letterSpacing: "-0.02em" }}>{fmtVND(rc.phaiThu)}</div>
        </div>
        <div style={{ borderLeft: "1px solid var(--line)", paddingLeft: 26 }}>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 2 }}>Còn nợ</div>
          <div className="tnum" style={{ fontSize: 16, fontWeight: 700, color: rc.conNo > 0 ? "var(--warn)" : "var(--good)" }}>{fmtVND(Math.max(0, rc.conNo))}</div>
        </div>
      </div>
      <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
        {dirty && <span style={{ fontSize: 12, color: "var(--warn)", fontWeight: 600, display: "inline-flex", alignItems: "center", gap: 5 }}><span style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} />Có thay đổi chưa lưu</span>}
        <Btn onClick={onClose}>Đóng</Btn>
        <Btn variant="primary" onClick={handleSave} disabled={!dirty || saving}>{saving ? "Đang lưu…" : "Lưu doanh thu"}</Btn>
      </div>
    </div>
  );
  return (
    <Modal title="Doanh thu & công nợ" subtitle={<>Lô <b style={{ color: "var(--ink-2)" }}>{ship.booking}</b> · {ship.customer}</>} onClose={onClose} footer={footer} width={780}>
      <Section title="Doanh thu" total={rc.tongDT} totalLabel="Tổng doanh thu">
        <DoanhThuRows rows={r.doanhThu || []} onChange={(arr) => setR({ doanhThu: arr })} options={cfg.revItems || []} onCreate={(v) => addCfg && addCfg("revItems", v)} prices={cfg.prices || {}} />
        <VatLine rate={r.vatRate == null ? "0" : r.vatRate} vat={rc.vat} onRate={(x) => setR({ vatRate: x })} />
      </Section>
      <Section title="Chi hộ" total={(choHo).reduce((s,e)=>s+toNum(e.amount),0)} totalLabel="Tổng chi hộ">
        <ChiHoRows rows={choHo} onChange={setChoHo} options={choHoOpts} onCreate={(v) => addCfg && addCfg("choHoItems", v)} prices={cfg.prices || {}} />
      </Section>
      <Section title="Thanh toán" total={rc.daTT} totalLabel="Đã thu">
        <div style={{ padding: "10px 0 4px", maxWidth: 320 }}>
          <Field label="Hạn thanh toán"><DateField value={r.hanTT} onChange={(x) => setR({ hanTT: x })} /></Field>
        </div>
        <div style={{ fontSize: 11.5, color: "var(--ink-4)", padding: "2px 0 0" }}>Khách trả nhiều đợt — thêm từng lần với số tiền và ngày.</div>
        <PaymentRows payments={r.payments || []} onChange={(arr) => setR({ payments: arr })} />
        <div style={{ display: "flex", alignItems: "center", gap: 10, padding: "10px 0 8px" }}>
          <span style={{ fontSize: 12.5, fontWeight: 600, color: paid ? "var(--good)" : "var(--warn)", background: paid ? "var(--good-weak)" : "var(--warn-weak)", padding: "4px 11px", borderRadius: 999 }}>
            {rc.phaiThu === 0 ? "Chưa có doanh thu" : paid ? "Đã thu đủ" : `Còn nợ ${fmtVND(rc.conNo)}`}
          </span>
          {(r.payments || []).length > 0 && <span style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Đã thu {(r.payments || []).length} đợt: <b className="tnum" style={{ color: "var(--good)" }}>{fmtVND(rc.daTT)}</b></span>}
        </div>
        <Field label="Ghi chú kế toán"><Txt value={r.ghiChu} onChange={(x) => setR({ ghiChu: x })} placeholder="Ghi chú…" /></Field>
      </Section>
    </Modal>
  );
}

/* ===================== INFO EDIT POPUP (khách / cont / tuyến / lịch) ===================== */

function InfoPopup({ ship, patch, patchOther, onSave, isDirty, siblings = [], onClose, onDelete, canDelete, isHph, cfg = {}, addCfg, tagOptions = [] }) {
  const isMobile = useIsMobile();
  const set = (np) => patch(np);
  const add = (k, v) => addCfg && addCfg(k, v);
  // Biển số gõ tạo mới ở các ô BKS lô hàng → mặc định "Xe ngoài" (xe thuê ngoài, không lọt đội xe MBF)
  const addVehExt = (v) => addCfg && addCfg("vehicles", v, { external: true });
  const hqFee = ((ship.cost && ship.cost.items) || []).some((it) => it.src === "thanhLyFee" && toNum(it.amount) > 0);
  const hqFilled = [ship.declNo, ship.declNote, ship.thanhLy, ship.cshtNote].filter((v) => (v || "").toString().trim()).length + (hqFee ? 1 : 0);
  const [hqOpen, setHqOpen] = useState(false);
  // Thuê xe ngoài → 1 dòng chi phí "Cước xe ngoài" (src=extTruck) link sang Chi phí lô hàng
  const cost = ship.cost || {};
  const costItems = cost.items || [];
  const extLine = costItems.find((it) => it.src === "extTruck");
  const extHired = !!extLine;
  const setCostItems = (arr) => patch({ cost: { ...cost, items: arr } });
  const toggleExt = (on) => {
    if (on && !extLine) setCostItems([...costItems, { id: Date.now() + Math.random(), src: "extTruck", item: "Cước xe ngoài", amount: "", payer: "Xe ngoài", date: "", billable: false, color: "", note: "" }]);
    else if (!on && extLine) setCostItems(costItems.filter((it) => it.src !== "extTruck"));
  };
  const setExt = (np) => setCostItems(costItems.map((it) => (it.src === "extTruck" ? { ...it, ...np } : it)));
  // Phí thanh lý tờ khai (Hải Quan) → 1 dòng chi phí "Phí thanh lý tờ khai" (src=thanhLyFee) link sang Chi phí lô hàng
  const tlLine = costItems.find((it) => it.src === "thanhLyFee");
  const setTlFee = (val) => {
    if (toNum(val) > 0) {
      if (tlLine) setCostItems(costItems.map((it) => (it.src === "thanhLyFee" ? { ...it, amount: val } : it)));
      else setCostItems([...costItems, { id: Date.now() + Math.random(), src: "thanhLyFee", item: "Phí thanh lý tờ khai", amount: val, payer: "", date: "", billable: false, color: "", note: "" }]);
    } else if (tlLine) {
      setCostItems(costItems.filter((it) => it.src !== "thanhLyFee"));
    }
  };
  // Chỉ liệt kê cont CHƯA RA = chưa có Giờ xe ra (của cont) — khớp quy tắc "đã ra = có gio_xe_ra".
  // Giữ cont đang chọn để không mất hiển thị lựa chọn.
  const sibOpts = siblings
    .filter((s) => !(s.gioXeRa || "").trim() || s.id === ship.raOtherId)
    .map((s) => ({ value: s.id, label: (s.contNo || "(chưa có cont)") + " — " + (s.booking || "(chưa có booking)") }));
  const raMode = ship.raMode || "self";
  const other = (raMode === "other" && ship.raOtherId != null) ? siblings.find((s) => s.id === ship.raOtherId) : null;
  // Khi "cont khác ra": input giờ ra/BKS ra chỉ ghi vào cont kia (qua patchOther), KHÔNG động vào cont hiện tại.
  // Giữ state cục bộ để field PHẢN ÁNH NGAY khi sửa — vì patchOther cập nhật danh sách lô (data),
  // còn giá trị hiển thị lấy từ siblings không tự cập nhật → nếu đọc thẳng siblings sẽ "không nhận".
  const [raEdit, setRaEdit] = useState({ id: null, gioXeRa: "", bksRa: "" });
  useEffect(() => {
    if (other) setRaEdit({ id: other.id, gioXeRa: other.gioXeRa || "", bksRa: other.bksRa || "" });
  }, [ship.raOtherId, raMode]);
  // "Cont khác ra": giờ ra/BKS nhập ở đây ghi vào field TRANSIENT của LÔ HIỆN TẠI (raOtherGioXeRa/raOtherBksRa)
  // → backend tự đẩy sang cont ra_other_id THEO ID (cập nhật được cả khi cont kia ở trang khác). Cont hiện tại
  // không động vào cột giờ ra. raEdit chỉ để hiển thị tức thời.
  const setRa = (val) => { if (other) { setRaEdit((e) => ({ ...e, gioXeRa: val })); set({ raOtherGioXeRa: val }); } else set({ gioXeRa: val }); };
  const setRaBks = (val) => { if (other) { setRaEdit((e) => ({ ...e, bksRa: val })); set({ raOtherBksRa: val }); } else set({ bksRa: val }); };
  const otherGioXeRa = (other && raEdit.id === other.id) ? raEdit.gioXeRa : (other ? other.gioXeRa || "" : "");
  const otherBksRa = (other && raEdit.id === other.id) ? raEdit.bksRa : (other ? other.bksRa || "" : "");
  const otherLabel = (sibOpts.find((o) => o.value === ship.raOtherId) || {}).label || "";
  const fmtDateTime = (s) => { if (!s) return ""; const d = new Date(s); return isNaN(d) ? "" : d.toLocaleString("vi-VN", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" }); };

  const dirty = !!(isDirty && isDirty(ship.id));   // giờ ra cont khác cũng ghi vào lô hiện tại (raOther*) → chỉ cần xét ship.id
  const missingReq = !((ship.customer || "").toString().trim()) || !((ship.booking || "").toString().trim());
  const [saving, setSaving] = useState(false);
  const handleSave = () => { if (missingReq || saving) return; setSaving(true); Promise.resolve(onSave && onSave()).then(() => onClose()).catch(() => setSaving(false)); };

  const footer = (
    <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 10 }}>
      <div>
        {canDelete && onDelete && (
          <button type="button" onClick={onDelete}
            style={{ display: "inline-flex", alignItems: "center", gap: 6, padding: "9px 14px", fontSize: 13.5, fontWeight: 500, border: "1px solid var(--line)", borderRadius: 10, background: "#fff", color: "var(--ink-3)", cursor: "pointer" }}
            onMouseEnter={(e) => { e.currentTarget.style.background = "#fce8e8"; e.currentTarget.style.color = "var(--danger)"; e.currentTarget.style.borderColor = "#f3c9c9"; }}
            onMouseLeave={(e) => { e.currentTarget.style.background = "#fff"; e.currentTarget.style.color = "var(--ink-3)"; e.currentTarget.style.borderColor = "var(--line)"; }}>
            <I.trash /> Xóa lô hàng
          </button>
        )}
      </div>
      <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
        {missingReq
          ? <span style={{ fontSize: 12, color: "var(--danger)", fontWeight: 600 }}>Cần nhập Khách hàng <b>*</b> và Số booking <b>*</b></span>
          : (dirty && <span style={{ fontSize: 12, color: "var(--warn)", fontWeight: 600, display: "inline-flex", alignItems: "center", gap: 5 }}><span style={{ width: 7, height: 7, borderRadius: 999, background: "var(--warn)" }} />Có thay đổi chưa lưu</span>)}
        <Btn onClick={onClose}>Đóng</Btn>
        <Btn variant="primary" onClick={handleSave} disabled={!dirty || missingReq || saving}>{saving ? "Đang lưu…" : "Lưu thông tin"}</Btn>
      </div>
    </div>
  );
  return (
    <Modal title="Thông tin lô hàng" subtitle="Sửa khách hàng, container, tuyến và lịch trình" onClose={onClose} footer={footer} width={720}>
      <Section title="Thông tin chung">
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, padding: "10px 0" }}>
          <Field label="Khách hàng" hint="danh mục" req><Combo value={ship.customer} onChange={(x) => set({ customer: x })} options={cfg.customers || []} onCreate={(v) => add("customers", v)} placeholder="Chọn khách hàng…" /></Field>
          <Field label={isHph ? "Số booking" : "Số booking / bill"} req><Txt value={ship.booking} onChange={(x) => set({ booking: x })} placeholder="Mã booking" /></Field>
        </div>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, padding: "0 0 4px" }}>
          <Field label="Số INV" hint="hóa đơn"><Txt value={ship.inv} onChange={(x) => set({ inv: x })} placeholder="VD: INV-2026-0142" /></Field>
          <Field label="Nhập / Xuất"><div style={{ marginTop: 2 }}><Seg value={ship.io} onChange={(x) => set({ io: x })} options={["Nhập", "Xuất", "Khác"]} /></div></Field>
        </div>
      </Section>

      <Section title="Container">
        {isHph ? (
          <div style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr 1fr" : "120px 1fr 1.4fr", gap: 12, padding: "10px 0" }}>
            <Field label="Số lượng"><Num value={ship.qty} onChange={(x) => set({ qty: x })} /></Field>
            <Field label="Loại cont" hint="danh mục"><Combo value={ship.contType} onChange={(x) => set({ contType: x })} options={cfg.contTypes || []} onCreate={(v) => add("contTypes", v)} placeholder="40HC…" /></Field>
            <Field label="Số container"><Txt value={ship.contNo} onChange={(x) => set({ contNo: x })} placeholder="TGHU…" /></Field>
          </div>
        ) : (
          <>
            <div style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr" : "1.2fr 1fr 1fr", gap: 12, padding: "10px 0 0" }}>
              <Field label="Số container"><Txt value={ship.contNo} onChange={(x) => set({ contNo: x })} placeholder="TGHU 123 4567" /></Field>
              <Field label="Loại cont" hint="danh mục"><Combo value={ship.contType} onChange={(x) => set({ contType: x })} options={cfg.contTypes || []} onCreate={(v) => add("contTypes", v)} placeholder="40HC…" /></Field>
              <Field label="Kho (nhà máy)" hint="chọn trong Cài đặt"><MultiCombo values={(ship.kho || "").split(/\s*,\s*/).filter(Boolean)} onChange={(arr) => set({ kho: arr.join(", ") })} options={whCodes(cfg)} max={Infinity} strict placeholder="Chọn kho (nhà máy) theo thứ tự đi qua…" /></Field>
            </div>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, padding: "12px 0 0" }}>
              <Field label="BKS vào"><Combo value={ship.bksVao} onChange={(x) => set({ bksVao: x })} options={cfg.vehicles || []} onCreate={addVehExt} placeholder="15C-123.45…" /></Field>
              <Field label="BKS ra"><Combo value={ship.bksRa} onChange={(x) => set({ bksRa: x })} options={cfg.vehicles || []} onCreate={addVehExt} placeholder="15C-678.90…" /></Field>
            </div>
          </>
        )}
      </Section>

      <div style={{ borderTop: "1px solid var(--line)" }}>
        <button type="button" onClick={() => setHqOpen((o) => !o)}
          style={{ width: "100%", display: "flex", alignItems: "center", gap: 9, padding: "13px 0", background: "none", border: "none", cursor: "pointer", textAlign: "left" }}>
          <span style={{ color: "var(--ink-4)", display: "inline-flex", transform: hqOpen ? "rotate(0deg)" : "rotate(-90deg)", transition: "transform .15s" }}><I.chev /></span>
          <span style={{ fontSize: 13.5, fontWeight: 600, color: "var(--ink-2)", letterSpacing: ".01em" }}>Hải Quan</span>
          {hqFilled > 0 && <span style={{ fontSize: 11.5, fontWeight: 600, color: "var(--accent)", background: "var(--accent-weak)", padding: "3px 9px", borderRadius: 999 }}>{hqFilled} mục</span>}
          {!hqOpen && <span style={{ fontSize: 11.5, color: "var(--ink-4)" }}>Số tờ khai, ngày thanh lý, cơ sở hạ tầng…</span>}
        </button>
        {hqOpen && (
          <div style={{ padding: "0 0 14px" }}>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
              <Field label="Số tờ khai"><Txt value={ship.declNo} onChange={(x) => set({ declNo: x })} placeholder="VD: 103456789012" /></Field>
              <Field label="Ngày thanh lý"><DateField value={ship.thanhLy} onChange={(x) => set({ thanhLy: x })} /></Field>
            </div>
            <div style={{ marginTop: 12, maxWidth: 240 }}>
              <Field label="Phí thanh lý tờ khai" hint="link sang Chi phí"><Money value={tlLine ? tlLine.amount : ""} onChange={(x) => setTlFee(x)} dim /></Field>
            </div>
            <div style={{ marginTop: 12 }}>
              <Field label="Ghi chú tờ khai">
                <textarea value={ship.declNote || ""} onChange={(e) => set({ declNote: e.target.value })} placeholder="Ghi chú liên quan tờ khai hải quan…" rows={2}
                  style={{ width: "100%", padding: "8px 11px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 9, outline: "none", resize: "vertical", fontFamily: "inherit" }}
                  onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
              </Field>
            </div>
            <div style={{ marginTop: 12 }}>
              <Field label="Cơ sở hạ tầng (ghi chú)">
                <textarea value={ship.cshtNote || ""} onChange={(e) => set({ cshtNote: e.target.value })} placeholder="Ghi chú phí/biên lai cơ sở hạ tầng cảng…" rows={2}
                  style={{ width: "100%", padding: "8px 11px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 9, outline: "none", resize: "vertical", fontFamily: "inherit" }}
                  onFocus={(e) => (e.target.style.borderColor = "var(--accent)")} onBlur={(e) => (e.target.style.borderColor = "var(--line)")} />
              </Field>
            </div>
          </div>
        )}
      </div>

      <Section title="Phân loại & tùy chọn">
        {/* Gom 3 tùy chọn ảnh hưởng định giá vào 1 khu cho dễ chọn */}
        <div style={{ display: "flex", gap: 10, flexWrap: "wrap", padding: "10px 0 6px" }}>
          {[
            { on: !!ship.cru, set: (v) => set({ cru: v }), icon: "bi-recycle", label: "Hàng CRU" },
            { on: !!ship.isBarge, set: (v) => set({ isBarge: v, bargeCont: v ? (ship.bargeCont || "DRY") : "" }), icon: "bi-water", label: "Đi sà lan" },
            { on: extHired, set: toggleExt, icon: "bi-truck", label: "Thuê xe ngoài" },
          ].map((o, i) => (
            <button key={i} type="button" onClick={() => o.set(!o.on)}
              style={{ display: "inline-flex", alignItems: "center", gap: 8, padding: "9px 14px", borderRadius: 10, cursor: "pointer", fontSize: 13.5, fontWeight: 600,
                border: "1px solid " + (o.on ? "var(--accent)" : "var(--line)"), background: o.on ? "var(--accent-weak-2)" : "#fff", color: o.on ? "var(--accent)" : "var(--ink-2)" }}>
              <span style={{ width: 18, height: 18, borderRadius: 5, display: "grid", placeItems: "center", border: "1.5px solid " + (o.on ? "var(--accent)" : "var(--line-2)"), background: o.on ? "var(--accent)" : "#fff", color: "#fff", fontSize: 11 }}>{o.on ? <i className="bi bi-check-lg" /> : null}</span>
              <i className={"bi " + o.icon} style={{ opacity: .7 }} /> {o.label}
            </button>
          ))}
        </div>
        {/* Chi tiết theo tùy chọn đã bật */}
        {ship.isBarge && (
          <div style={{ display: "flex", alignItems: "center", gap: 10, padding: "8px 12px", marginTop: 6, background: "var(--accent-weak-2)", borderRadius: 9, flexWrap: "wrap" }}>
            <i className="bi bi-water" style={{ color: "var(--accent)" }} />
            <span style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Sà lan · loại cont:</span>
            <Seg value={ship.bargeCont || "DRY"} onChange={(x) => set({ bargeCont: x })} options={["DRY", "NOR"]} />
            <span style={{ fontSize: 11.5, color: "var(--ink-4)" }}>→ giá theo nhóm <b>Non · {ship.bargeCont === "NOR" ? "NOR" : "DRY"} CONTAINER</b></span>
          </div>
        )}
        {extHired && (
          <div style={{ padding: "8px 12px", marginTop: 6, background: "#fafbfc", border: "1px solid var(--line-2)", borderRadius: 9 }}>
            <div style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr" : "200px 1fr", gap: 12, alignItems: "end" }}>
              <Field label="Cước xe ngoài"><Money value={extLine.amount} onChange={(x) => setExt({ amount: x })} dim /></Field>
              <Field label="Ghi chú nhà xe"><Txt value={extLine.note} onChange={(x) => setExt({ note: x })} placeholder="Tên nhà xe, SĐT, biển số…" /></Field>
            </div>
            <div style={{ fontSize: 11, color: "var(--ink-4)", marginTop: 6, display: "flex", alignItems: "center", gap: 6 }}><I.link /> Khoản <b style={{ color: "var(--ink-3)" }}>“Cước xe ngoài”</b> trong Chi phí lô hàng; bỏ tích để gỡ.</div>
          </div>
        )}
        <div style={{ fontSize: 11, color: "var(--ink-4)", marginTop: 8, lineHeight: 1.5 }}>
          <b style={{ color: "var(--ink-3)" }}>CRU</b> quyết KIND lấy giá (CRU+Xuất→External · CRU+Nhập→Internal · không CRU→Transport 1 way). <b style={{ color: "var(--ink-3)" }}>Sà lan</b> ép giá theo nhóm Non DRY/NOR (bỏ qua CRU).
        </div>
      </Section>

      <Section title="Tuyến" >
        <div style={{ fontSize: 11.5, color: "var(--ink-4)", padding: "6px 0 0" }}>Hiển thị <b style={{ color: "var(--ink-3)" }}>Tên - Ký hiệu</b> — gõ tên hoặc ký hiệu để tìm, chưa có thì gõ để thêm mới.</div>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 36px 1fr", gap: 10, alignItems: "end", padding: "8px 0 10px" }}>
          <Field label="Nơi lấy (cảng)"><Combo value={ship.from} onChange={(x) => set({ from: x })} options={locOptions(cfg)} placeholder="Chọn cảng/điểm lấy (trong Cài đặt)…" clearable strict /></Field>
          <div style={{ display: "grid", placeItems: "center", color: "var(--accent)", paddingBottom: 9 }}><I.arrow /></div>
          <Field label="Nơi hạ"><Combo value={ship.to} onChange={(x) => set({ to: x })} options={locOptions(cfg)} placeholder="Chọn điểm hạ (trong Cài đặt)…" clearable strict /></Field>
        </div>
      </Section>

      <Section title="Lịch trình">
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, padding: "10px 0" }}>
          {isHph ? (
            <>
              <Field label="Ngày tàu chạy"><DateField value={ship.sailDate} onChange={(x) => set({ sailDate: x })} /></Field>
              <Field label="Cắt máng"><Txt value={ship.cutOff} onChange={(x) => set({ cutOff: x })} placeholder="18/06 14:00" /></Field>
            </>
          ) : (
            <>
              <Field label="Cắt máng" hint="ngày giờ"><DTField value={ship.cutOff} onChange={(x) => set({ cutOff: x })} /></Field>
              <Field label="Ngày cont đến"><DateField value={ship.contDen} onChange={(x) => set({ contDen: x })} /></Field>
              {/* Bỏ "Ngày cont ra" — dùng "Giờ xe ra" (gioXeRa) ở mục Free time làm mốc cont rời đi. */}
            </>
          )}
        </div>
      </Section>

      {!isHph && (() => {
        // Free time tính theo giờ xe ra CỦA CHÍNH cont này. "Cont khác ra" → cont này KHÔNG tự ra
        // (gio_xe_ra rỗng) → không có free time ở đây; chuyến ra (giờ + BKS) ghi ở cont đã chọn.
        const ft = calcFreeTime(ship, (cfg.freeTimeHours == null ? "4" : cfg.freeTimeHours), cfg.freeTimeRules);
        return (
          <Section title="Free time & kết nối">
            <div style={{ fontSize: 11.5, color: "var(--ink-4)", padding: "2px 0 6px", lineHeight: 1.5 }}>Free time = <b style={{ color: "var(--ink-3)" }}>Giờ xe ra − Giờ xe đến</b> (follow theo xe ra). Giờ xe ra lấy theo lựa chọn dưới: <b>chính cont này</b> → Giờ xe ra (của cont); <b>cont khác ra</b> → giờ ra cont kia; <b>không kéo cont</b> → Giờ xe ra (của XE). Ngưỡng <b style={{ color: "var(--ink-3)" }}>{ft ? ft.threshold : (cfg.freeTimeHours || 4)}h</b> chỉnh trong Cấu hình. <span style={{ color: "var(--ink-4)" }}>(Giờ đến kế hoạch chỉ để theo dõi kế hoạch, KHÔNG tính free time.)</span></div>
            <div style={{ display: "grid", gridTemplateColumns: isMobile ? "1fr 1fr" : "1fr 1fr 1fr", gap: 12, padding: "4px 0 6px" }}>
              <Field label="Giờ đến kế hoạch"><DTField value={ship.gioDenDuKien} onChange={(x) => set({ gioDenDuKien: x })} /></Field>
              <Field label="Giờ xe đến"><DTField value={ship.gioXeDen} onChange={(x) => set({ gioXeDen: x })} /></Field>
              <Field label="Giờ xe ra (của cont)"><DTField value={ship.gioXeRa} onChange={(x) => set({ gioXeRa: x })} /></Field>
            </div>
            <div style={{ background: "var(--accent-weak-2)", border: "1px solid var(--accent-weak)", borderRadius: 10, padding: "10px 12px", margin: "2px 0 12px" }}>
              <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 7, fontWeight: 500 }}>Giờ xe ra này là của:</div>
              <div style={{ display: "flex", alignItems: "center", gap: 12, flexWrap: "wrap" }}>
                <div style={{ display: "inline-flex", background: "#f1f2f4", borderRadius: 8, padding: 2, flexWrap: "wrap" }}>
                  {[["self", "Chính cont này"], ["other", "Cont khác ra"], ["none", "Không kéo công ra"]].map(([k, lbl]) => {
                    const on = raMode === k;
                    // Ô "Giờ xe ra (của cont)" luôn cho nhập (giờ ra RIÊNG của cont), độc lập với lựa chọn này.
                    // self/other → xóa gioXeRaXe (chỉ dùng cho 'none'); self/none → bỏ liên kết cont khác.
                    // KHÔNG xóa gioXeRa/bksRa của cont hiện tại nữa.
                    const onPick = k === "none" ? { raMode: "none", raOtherId: null }
                      : k === "self" ? { raMode: "self", raOtherId: null, gioXeRaXe: "" }
                      : { raMode: "other", gioXeRaXe: "" };
                    return (
                      <button key={k} type="button" onClick={() => set(onPick)}
                        style={{ border: "none", cursor: "pointer", fontSize: 12.5, fontWeight: 600, padding: "6px 13px", borderRadius: 6, whiteSpace: "nowrap",
                          background: on ? "#fff" : "transparent", color: on ? "var(--accent)" : "var(--ink-3)", boxShadow: on ? "0 1px 2px rgba(16,19,23,.12)" : "none", transition: "all .12s" }}>
                        {lbl}
                      </button>
                    );
                  })}
                </div>
                {raMode === "other" && (
                  <div style={{ flex: 1, minWidth: 240 }}>
                    <Combo value={ship.raOtherId != null ? (sibOpts.find((o) => o.value === ship.raOtherId) || {}).label : ""}
                      options={sibOpts.map((o) => o.label)}
                      onChange={(label) => { const opt = sibOpts.find((o) => o.label === label); set({ raOtherId: opt ? opt.value : null }); }}
                      placeholder="Chọn cont ra cùng chuyến…" small />
                  </div>
                )}
              </div>
              {raMode === "other" && (
                ship.raOtherId != null ? (
                  <div style={{ marginTop: 10 }}>
                    <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>Giờ ra & biển số của <b style={{ color: "var(--ink-2)" }}>{(sibOpts.find((o) => o.value === ship.raOtherId) || {}).label}</b></div>
                    <div style={{ display: "flex", alignItems: "center", gap: 10, flexWrap: "wrap" }}>
                      <div style={{ width: 220 }}><DTField value={otherGioXeRa} onChange={(x) => setRa(x)} /></div>
                      <div style={{ width: 190 }}>
                        <Combo value={otherBksRa} onChange={(x) => setRaBks(x)} options={cfg.vehicles || []} onCreate={addVehExt} placeholder="BKS ra…" small />
                      </div>
                    </div>
                    {(otherGioXeRa || otherBksRa) && (
                      <div style={{ fontSize: 12, color: "var(--accent)", marginTop: 8, fontWeight: 600, display: "flex", alignItems: "flex-start", gap: 6, lineHeight: 1.5 }}>
                        <i className="bi bi-check-circle-fill" style={{ marginTop: 1 }} />
                        <span>{otherBksRa ? <>BKS <b>{otherBksRa}</b> kéo cont <b>{otherLabel}</b> ra</> : <>Cont <b>{otherLabel}</b> ra</>}{otherGioXeRa ? <> lúc <b>{fmtDateTime(otherGioXeRa)}</b></> : ""}.</span>
                      </div>
                    )}
                    <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 7, lineHeight: 1.5 }}>
                      Giờ ra & biển số ở đây cập nhật cho <b style={{ color: "var(--ink-3)" }}>cont đã chọn</b> (cont thực sự rời đi). Giờ xe ra của cont hiện tại nhập ở ô <b style={{ color: "var(--ink-3)" }}>“Giờ xe ra (của cont)”</b> phía trên. Thay đổi lưu khi bấm <b style={{ color: "var(--ink-3)" }}>Lưu thông tin</b>.
                    </div>
                  </div>
                ) : (
                  <div style={{ fontSize: 11.5, color: "var(--warn)", marginTop: 8, fontWeight: 500 }}>Chọn cont ra cùng chuyến để nhập giờ ra cập nhật cho cont đó.</div>
                )
              )}
              {raMode === "none" && (
                <div style={{ marginTop: 10 }}>
                  <div style={{ fontSize: 12, color: "var(--ink-3)", marginBottom: 5, fontWeight: 500 }}>Giờ xe ra <b style={{ color: "var(--ink-2)" }}>(của XE — đầu kéo)</b></div>
                  <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
                    <div style={{ width: 220 }}><DTField value={ship.gioXeRaXe} onChange={(x) => set({ gioXeRaXe: x })} /></div>
                    <button type="button" onClick={() => set({ gioXeRaXe: nowLocalDT() })}
                      style={{ fontSize: 12, fontWeight: 600, color: "var(--accent)", background: "var(--accent-weak-2)", border: "1px solid var(--accent-weak)", borderRadius: 8, padding: "8px 12px", cursor: "pointer", whiteSpace: "nowrap" }}>
                      Bây giờ
                    </button>
                  </div>
                  <div style={{ fontSize: 11.5, color: "var(--ink-4)", marginTop: 7, lineHeight: 1.5 }}>
                    Xe ra nhưng <b style={{ color: "var(--ink-3)" }}>không kéo cont nào ra</b> — mốc này là <b style={{ color: "var(--ink-3)" }}>giờ XE rời đi</b> (không phải giờ cont), để sau tính phí hạng mục khác. Cont vẫn tính <b style={{ color: "var(--ink-3)" }}>"chưa ra"</b>.
                  </div>
                </div>
              )}
            </div>
            <div style={{ display: "flex", alignItems: "center", gap: 14, padding: "4px 0 4px" }}>
              <div style={{ display: "flex", alignItems: "baseline", gap: 8 }}>
                <span style={{ fontSize: 12.5, color: "var(--ink-3)" }}>Free time</span>
                <span className="tnum" style={{ fontSize: 20, fontWeight: 700 }}>{ft ? fmtHours(ft.hours) : "—"}</span>
                {ft && <span style={{ fontSize: 12, color: "var(--ink-4)" }}>(tính từ {ft.basis})</span>}
              </div>
              <div style={{ flex: 1 }} />
              {ft && (
                <span style={{ display: "inline-flex", alignItems: "center", gap: 7, fontSize: 13.5, fontWeight: 700, padding: "6px 14px", borderRadius: 999,
                  color: ft.connect ? "var(--good)" : "var(--danger)", background: ft.connect ? "var(--good-weak)" : "#fce8e8" }}>
                  <span style={{ width: 8, height: 8, borderRadius: 999, background: "currentColor" }} />
                  {ft.connect ? "CONNECT" : "DISCONNECT"}
                </span>
              )}
            </div>
          </Section>
        );
      })()}

      <Section title="Nhãn & ghi chú">
        <div style={{ padding: "4px 0 2px" }}>
          <div style={{ fontSize: 11.5, color: "var(--ink-3)", marginBottom: 4, fontWeight: 500 }}>Nhãn <span style={{ color: "var(--ink-4)", fontWeight: 400 }}>(chọn hoặc gõ tạo mới, chọn nhiều)</span></div>
          <MultiCombo values={ship.tags || []} onChange={(arr) => set({ tags: arr })} options={tagOptions} placeholder="Thêm nhãn…" max={20} />
        </div>
        <textarea value={ship.infoNote || ""} onChange={(e) => set({ infoNote: e.target.value })} rows={3} placeholder="Ghi chú tự do cho lô hàng…"
          style={{ width: "100%", padding: "9px 11px", fontSize: 13.5, border: "1px solid var(--line)", borderRadius: 9, outline: "none", resize: "vertical", fontFamily: "inherit", lineHeight: 1.5, marginTop: 10 }}
          onFocus={(e) => { e.target.style.borderColor = "var(--accent)"; e.target.style.boxShadow = "0 0 0 3px var(--accent-weak)"; }}
          onBlur={(e) => { e.target.style.borderColor = "var(--line)"; e.target.style.boxShadow = "none"; }} />
      </Section>
    </Modal>
  );
}

/* ===================== CONFIG (master data) POPUP ===================== */


export { CostPopup, RevenuePopup, CostPopupICD, RevenuePopupICD, InfoPopup };
