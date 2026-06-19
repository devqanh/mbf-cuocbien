// Logic Excel THUẦN cho Lô hàng (data vào → workbook/rows ra; KHÔNG đụng React state).
// Dùng XLSX (global, nạp sẵn ở layout). Tách khỏi ShipmentsApp cho gọn/dễ đọc.

// (*) = BẮT BUỘC: Khách hàng, Số booking, Số lượng cont. Khớp cột khi import theo TỪ KHÓA (không phụ thuộc dấu *).
export const IMP_COLS = ["Khách hàng *", "SỐ BOOKING/BILL *", "NHẬP/XUẤT", "SỐ LƯỢNG CONT *", "LOẠI CONT", "SỐ CONTAINER", "CẮT MÁNG", "NƠI LẤY", "NƠI HẠ", "NGÀY ĐẾN DỰ KIẾN", "GIỜ ĐẾN DỰ KIẾN", "KHO", "INVOICE"];

// Đếm số LÔ thực tế sẽ tạo (bung theo số container, hoặc nhân theo số lượng cont) — đúng quy tắc backend.
export const loCountOf = (rows) => (rows || []).reduce((a, r) => { const cs = String(r.contNo || "").split(/[\r\n;,]+/).map((s) => s.trim()).filter(Boolean); return a + (cs.length || Math.max(1, parseInt(String(r.qty || "").replace(/[^\d]/g, ""), 10) || 1)); }, 0);

const normH = (s) => String(s == null ? "" : s).trim().toLowerCase().replace(/\s+/g, " ");
const toIsoDate = (s) => { const m = /(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})/.exec(String(s || "")); if (!m) return ""; let [, d, mo, y] = m; if (y.length === 2) y = "20" + y; return `${y}-${mo.padStart(2, "0")}-${d.padStart(2, "0")}`; };
const toHm = (s) => { const m = /(\d{1,2}):(\d{2})/.exec(String(s || "")); return m ? `${m[1].padStart(2, "0")}:${m[2]}` : ""; };

// Parse 1 sheet Excel → mảng dòng lô (khớp cột theo từ khóa header). wb = workbook đã đọc, sheetName = tên sheet.
export function parseImportRows(wb, sheetName) {
  const aoa = XLSX.utils.sheet_to_json(wb.Sheets[sheetName], { header: 1, raw: false, defval: "" });
  let hi = aoa.findIndex((r) => (r || []).some((c) => { const h = normH(c); return h.includes("khách") || h.includes("nhà máy"); }));
  if (hi < 0) hi = 0;
  const header = (aoa[hi] || []).map(normH);
  const col = (...kws) => header.findIndex((h) => kws.some((k) => h.includes(k)));
  const C = { customer: col("khách", "nhà máy"), booking: col("booking", "bill"), io: col("nhập", "xuất"), qty: col("lượng"), contType: col("loại"), contNo: col("container", "tên cont", "số cont"), cutOff: col("máng"), from: col("lấy"), to: col("hạ"), ngay: col("ngày"), gio: col("giờ"), kho: col("kho"), inv: col("invoice", "inv") };
  const out = [];
  for (let r = hi + 1; r < aoa.length; r++) {
    const row = aoa[r] || []; const g = (i) => (i >= 0 ? String(row[i] == null ? "" : row[i]).trim() : "");
    if (!g(C.customer) && !g(C.booking) && !g(C.from) && !g(C.to)) continue;
    const ngayIso = toIsoDate(g(C.ngay)); const hm = toHm(g(C.gio));
    const gioDenDuKien = ngayIso ? `${ngayIso}T${hm || "00:00"}` : "";
    const cmRaw = g(C.cutOff); const cmDate = toIsoDate(cmRaw); const cmHm = toHm(cmRaw);
    const cutOff = cmDate ? `${cmDate}T${cmHm || "00:00"}` : cmRaw;
    out.push({ customer: g(C.customer), booking: g(C.booking), io: g(C.io), qty: g(C.qty).replace(/[^\d]/g, ""), qtyRaw: g(C.qty), contType: g(C.contType), contNo: g(C.contNo), cutOff, cutOffRaw: cmRaw, from: g(C.from), to: g(C.to), kho: g(C.kho), inv: g(C.inv), gioDenDuKien, ngayRaw: g(C.ngay), gioRaw: g(C.gio) });
  }
  return out;
}

// Dựng workbook FILE MẪU import (gồm sheet mẫu + tham chiếu Địa điểm/Khách/Kho hợp lệ + Hướng dẫn). c = cfg đầy đủ.
export function buildTemplateWb(c) {
  c = c || {};
  const locs = c.locations || [];
  const codeOf = c.locationCode || {};
  const custs = c.customers || [];
  const whs = c.warehouses || [];
  const whCodeOf = c.warehouseCode || {};
  const exFrom = locs[0] || "ICD Quế Võ";
  const exTo = locs[1] || locs[0] || "KCN Tiên Sơn";
  const exCust = custs[0] || "Canon Vietnam";
  // KHO ví dụ = ký hiệu kho CÓ THẬT (tránh import mẫu bị lỗi "chưa có trong danh mục kho")
  const whTok = (n) => whCodeOf[n] || n;
  const exKho1 = whs.length >= 2 ? `${whTok(whs[0])} → ${whTok(whs[1])}` : (whs[0] ? whTok(whs[0]) : "");
  const exKho2 = whs[0] ? whTok(whs[0]) : "";
  const ex1 = { "Khách hàng *": exCust, "SỐ BOOKING/BILL *": "BL-ICD-0001", "NHẬP/XUẤT": "Nhập", "SỐ LƯỢNG CONT *": 3, "LOẠI CONT": "40HC", "SỐ CONTAINER": "TGHU1234567\nMSKU9981122\nCSNU4567788", "CẮT MÁNG": "14/05/2026 10:00", "NƠI LẤY": exFrom, "NƠI HẠ": exTo, "NGÀY ĐẾN DỰ KIẾN": "14/05/2026", "GIỜ ĐẾN DỰ KIẾN": "08:00", "KHO": exKho1, "INVOICE": "INV-001" };
  const ex2 = { "Khách hàng *": exCust, "SỐ BOOKING/BILL *": "BL-ICD-0002", "NHẬP/XUẤT": "Xuất", "SỐ LƯỢNG CONT *": 2, "LOẠI CONT": "20DC", "SỐ CONTAINER": "", "CẮT MÁNG": "15/05/2026 09:00", "NƠI LẤY": codeOf[exFrom] || exFrom, "NƠI HẠ": exTo, "NGÀY ĐẾN DỰ KIẾN": "15/05/2026", "GIỜ ĐẾN DỰ KIẾN": "07:30", "KHO": exKho2, "INVOICE": "INV-002" };
  const ws = XLSX.utils.json_to_sheet([ex1, ex2], { header: IMP_COLS });
  ws["!cols"] = IMP_COLS.map((col) => ({ wch: Math.max(12, col.length + 2) }));
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Lô hàng");
  const locRows = locs.map((n) => ({ "Tên địa điểm": n, "Ký hiệu": codeOf[n] || "" }));
  if (locRows.length) {
    const wl = XLSX.utils.json_to_sheet(locRows, { header: ["Tên địa điểm", "Ký hiệu"] });
    wl["!cols"] = [{ wch: 32 }, { wch: 14 }];
    XLSX.utils.book_append_sheet(wb, wl, "Địa điểm hợp lệ");
  }
  if (custs.length) {
    const wc = XLSX.utils.json_to_sheet(custs.map((n) => ({ "Khách hàng": n })), { header: ["Khách hàng"] });
    wc["!cols"] = [{ wch: 36 }];
    XLSX.utils.book_append_sheet(wb, wc, "Khách hàng hợp lệ");
  }
  if (whs.length) {
    const whRows = whs.map((n) => ({ "Tên kho": n, "Ký hiệu": whCodeOf[n] || "" }));
    const ww = XLSX.utils.json_to_sheet(whRows, { header: ["Tên kho", "Ký hiệu"] });
    ww["!cols"] = [{ wch: 28 }, { wch: 14 }];
    XLSX.utils.book_append_sheet(wb, ww, "Kho hợp lệ");
  }
  const guide = [
    { "Cột": "Khách hàng *", "Bắt buộc": "CÓ", "Ý nghĩa": "Tên khách — phải trùng danh mục (xem sheet 'Khách hàng hợp lệ')" },
    { "Cột": "SỐ BOOKING/BILL *", "Bắt buộc": "CÓ", "Ý nghĩa": "Số booking / số bill" },
    { "Cột": "SỐ LƯỢNG CONT *", "Bắt buộc": "CÓ", "Ý nghĩa": "Số lượng container (số ≥ 1) — cont để trống sẽ nhân bản theo số này" },
    { "Cột": "NƠI LẤY", "Bắt buộc": "không", "Ý nghĩa": "Điểm lấy hàng — TÊN hoặc KÝ HIỆU trong danh mục Địa điểm (nếu nhập sai sẽ báo lỗi)" },
    { "Cột": "NƠI HẠ", "Bắt buộc": "không", "Ý nghĩa": "Điểm hạ hàng — TÊN hoặc KÝ HIỆU trong danh mục Địa điểm (nếu nhập sai sẽ báo lỗi)" },
    { "Cột": "NGÀY ĐẾN DỰ KIẾN", "Bắt buộc": "không", "Ý nghĩa": "Ngày xe DỰ KIẾN đến (dd/mm/yyyy)" },
    { "Cột": "GIỜ ĐẾN DỰ KIẾN", "Bắt buộc": "không", "Ý nghĩa": "Giờ xe DỰ KIẾN đến (HH:MM) — ghép với Ngày đến dự kiến" },
    { "Cột": "CẮT MÁNG", "Bắt buộc": "không", "Ý nghĩa": "Hạn cắt máng/tàu (dd/mm/yyyy HH:MM)" },
    { "Cột": "NHẬP/XUẤT", "Bắt buộc": "không", "Ý nghĩa": "Nhập hoặc Xuất" },
    { "Cột": "LOẠI CONT", "Bắt buộc": "không", "Ý nghĩa": "Loại cont: 40HC, 20DC…" },
    { "Cột": "SỐ CONTAINER", "Bắt buộc": "không", "Ý nghĩa": "Số cont — nhiều cont thì XUỐNG DÒNG trong 1 ô" },
    { "Cột": "KHO", "Bắt buộc": "không", "Ý nghĩa": "Tuyến kho — TÊN hoặc KÝ HIỆU trong danh mục Kho (xem sheet 'Kho hợp lệ'); nhiều đoạn nối bằng → hoặc dấu phẩy (vd TL → TS); dùng khớp phí xe; nhập sai sẽ báo lỗi" },
    { "Cột": "INVOICE", "Bắt buộc": "không", "Ý nghĩa": "Số invoice (nếu có)" },
  ];
  const wg = XLSX.utils.json_to_sheet(guide, { header: ["Cột", "Bắt buộc", "Ý nghĩa"] });
  wg["!cols"] = [{ wch: 22 }, { wch: 10 }, { wch: 64 }];
  XLSX.utils.book_append_sheet(wb, wg, "Hướng dẫn");
  return wb;
}
