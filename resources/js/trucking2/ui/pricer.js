import { calcFreeTime, toNum } from "@trk/lib.jsx";

/* Bộ định giá lô theo BẢNG GIÁ của khách — dùng CHUNG cho Tạo bảng kê & Tính lại khi xem. */
function makePricer(cfg) {
  const locationCode = cfg.locationCode || {};
  const codeOf = (name) => { const v = (name || "").toString().trim(); return locationCode[v] || v; };
  // Đảo ngược: ký hiệu → TÊN địa điểm, để hiển thị tuyến trực quan (không dùng viết tắt)
  const codeToName = {};
  Object.keys(locationCode).forEach((nm) => { const c = (locationCode[nm] || "").toString().trim(); if (c) codeToName[c] = nm; });
  const nameOf = (v) => { v = (v || "").toString().trim(); return codeToName[v] || v; };
  const cont20 = (s) => /20/.test(s.contType || "");
  const connOf = (s) => { const ft = calcFreeTime(s, cfg.freeTimeHours, cfg.freeTimeRules); return ft ? (ft.connect ? "Connect" : "Disconnect") : null; };
  const isExport = (s) => (s.io || "").toString().toLowerCase().includes("xu");
  const kindOf = (s) => s.cru ? (isExport(s) ? "External CRU transportation" : "Internal CRU transportation") : "Transportation 1 way of Import/Export";
  // So khớp KIND: bỏ khoảng trắng 2 đầu + KHÔNG phân biệt hoa/thường
  // (vd "external cru transportation" = "External CRU transportation" = "EXTERNAL CRU TRANSPORTATION").
  const nk = (v) => (v || "").toString().trim().toLowerCase();
  const priceFor = (s) => {
    const list = ((cfg.customerInfo || {})[s.customer] || {}).priceList || [];
    const fromRaw = (s.from || "").trim(), dropRaw = (s.to || "").trim();
    const ft = calcFreeTime(s, cfg.freeTimeHours, cfg.freeTimeRules);   // chi tiết free time để ghi rõ
    const conn = ft ? (ft.connect ? "Connect" : "Disconnect") : null;
    const fromC = codeOf(s.from), dropC = codeOf(s.to), kind = kindOf(s);
    // So khớp BỎ DẤU CÁCH giữa + hoa/thường: "ICD QV" == "ICDQV" (bảng giá import lệch dấu cách).
    const ns = (v) => (v || "").toString().replace(/\s+/g, "").toUpperCase();
    const eq = (a, b) => !!a && ns(a) === ns(b);
    const fromMatch = (p) => eq(codeOf(p.from), fromC) || eq(p.from, fromRaw);
    const dropMatch = (p) => {
      if (!dropRaw) return true;   // lô không có nơi hạ → khớp theo đi+loại
      const cand = [codeOf(p.to1), p.to1, codeOf(p.loc), p.loc].map(ns);
      return cand.includes(ns(dropC)) || cand.includes(ns(dropRaw));
    };
    const kindMatch = (p) => nk(p.kind) === nk(kind);
    let p = list.find((p) => fromMatch(p) && dropMatch(p) && kindMatch(p) && (!conn || (p.conn || "Connect") === conn));
    if (!p) p = list.find((p) => fromMatch(p) && dropMatch(p) && kindMatch(p));
    const is20 = cont20(s);
    const cuoc = p ? toNum(is20 ? p.transFee20 : p.transFee40) : 0;
    const dau = p ? toNum(is20 ? p.fuelFee20 : p.fuelFee40) : 0;
    // Chi hộ = các khoản CHI PHÍ lô được tick "Chi hộ" (billable), thu lại từ khách
    const items = (s.cost && s.cost.items) || [];
    const choHoItems = items.filter((e) => e.billable).map((e) => ({ item: e.item || "(khoản)", amount: toNum(e.amount) }));
    const costItems = items.map((e) => ({ item: e.item || "(khoản)", amount: toNum(e.amount), billable: !!e.billable, src: e.src || "" }));
    const chiHo = choHoItems.reduce((a, e) => a + e.amount, 0);
    const route = p ? ((nameOf(p.from) || "?") + " → " + (nameOf(p.to1 || p.loc) || "?")) : null;
    const noDrop = !dropRaw && !!p;
    return { matched: !!p, conn, kind, is20, cuoc, dau, chiHo, choHoItems, costItems, route, noDrop,
      ftHours: ft ? ft.hours : null, ftThreshold: ft ? ft.threshold : null, ftBasis: ft ? ft.basis : null,
      phaiThu: cuoc + dau + chiHo };
  };
  return { priceFor, codeOf, connOf, cont20, kindOf };
}

export { makePricer };
