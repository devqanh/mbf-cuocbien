// Logic Excel THUẦN cho Lô hàng (data vào → workbook/rows ra; KHÔNG đụng React state).
// Dùng XLSX (global, nạp sẵn ở layout). Tách khỏi ShipmentsApp cho gọn/dễ đọc.

// (*) = BẮT BUỘC: Khách hàng, Số booking, Số lượng cont. Khớp cột khi import theo TỪ KHÓA (không phụ thuộc dấu *).
export const IMP_COLS = ["Khách hàng *", "SỐ BOOKING/BILL *", "NHẬP/XUẤT", "SỐ LƯỢNG CONT *", "LOẠI CONT", "SỐ CONTAINER", "CẮT MÁNG", "NƠI LẤY", "NƠI HẠ", "NƠI HẠ SÀ LAN", "NGÀY ĐẾN DỰ KIẾN", "GIỜ ĐẾN DỰ KIẾN", "KHO", "INVOICE"];
// Nơi hạ sà lan (điểm đến) — CHỈ nhận 2 cảng này (hoặc để trống = không đi sà lan).
export const BARGE_DROPS = ["HPP", "LHP"];

// Đếm số LÔ thực tế sẽ tạo (bung theo số container, hoặc nhân theo số lượng cont) — đúng quy tắc backend.
export const loCountOf = (rows) => (rows || []).reduce((a, r) => { const cs = String(r.contNo || "").split(/[\r\n;,]+/).map((s) => s.trim()).filter(Boolean); return a + (cs.length || Math.max(1, parseInt(String(r.qty || "").replace(/[^\d]/g, ""), 10) || 1)); }, 0);

const normH = (s) => String(s == null ? "" : s).trim().toLowerCase().replace(/\s+/g, " ");
const p2 = (n) => String(n).padStart(2, "0");

// --- Xử lý ngày/giờ an toàn cho mọi dạng ô Excel (Date object, serial number, text dd/mm/yyyy) ---
// Excel + cellDates:true → Date object tường minh, không phụ thuộc locale. Text giữ dd/mm/yyyy (file mẫu).
function cellDate(v) {
  if (v == null || v === "") return { iso: "", display: "" };
  if (v instanceof Date && !isNaN(v)) {
    const y = v.getFullYear(), m = v.getMonth() + 1, d = v.getDate();
    if (y < 2000 || y > 2099) return { iso: "", display: String(v) };   // năm vô lý → cảnh báo
    return { iso: `${y}-${p2(m)}-${p2(d)}`, display: `${p2(d)}/${p2(m)}/${y}` };
  }
  if (typeof v === "number" && v > 0 && v < 100000) {
    // Serial date (fallback nếu cellDates miss)
    try { const dt = XLSX.SSF.parse_date_code(v); if (dt && dt.y >= 2000 && dt.y <= 2099) return { iso: `${dt.y}-${p2(dt.m)}-${p2(dt.d)}`, display: `${p2(dt.d)}/${p2(dt.m)}/${dt.y}` }; } catch (e) {}
    return { iso: "", display: String(v) };
  }
  // Text: parse dd/mm/yyyy (format file mẫu)
  const s = String(v).trim();
  const mx = /(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})/.exec(s);
  if (!mx) return { iso: "", display: s };
  let [, d, mo, y] = mx;
  if (y.length === 2) y = "20" + y;
  if (+y < 2000 || +y > 2099 || +mo < 1 || +mo > 12 || +d < 1 || +d > 31) return { iso: "", display: s };
  return { iso: `${y}-${p2(+mo)}-${p2(+d)}`, display: s };
}
function cellTime(v) {
  if (v == null || v === "") return "";
  if (v instanceof Date && !isNaN(v)) return `${p2(v.getHours())}:${p2(v.getMinutes())}`;
  const m = /(\d{1,2}):(\d{2})/.exec(String(v));
  return m ? `${p2(+m[1])}:${m[2]}` : "";
}

// Parse 1 sheet Excel → mảng dòng lô (khớp cột theo từ khóa header). wb = workbook đã đọc (cellDates:true), sheetName = tên sheet.
export function parseImportRows(wb, sheetName) {
  // raw:true để nhận Date objects từ cellDates:true (tường minh, không phụ thuộc locale); text cells vẫn là string.
  const aoa = XLSX.utils.sheet_to_json(wb.Sheets[sheetName], { header: 1, raw: true, defval: "" });
  let hi = aoa.findIndex((r) => (r || []).some((c) => { const h = normH(c); return h.includes("khách") || h.includes("nhà máy"); }));
  if (hi < 0) hi = 0;
  const header = (aoa[hi] || []).map(normH);
  const col = (...kws) => header.findIndex((h) => kws.some((k) => h.includes(k)));
  const C = { customer: col("khách", "nhà máy"), booking: col("booking", "bill"), io: col("nhập", "xuất"), qty: col("lượng"), contType: col("loại"), contNo: col("container", "tên cont", "số cont"), cutOff: col("máng"), from: col("lấy"), bargeDrop: col("sà lan", "sa lan"), to: col("hạ"), ngay: col("ngày"), gio: col("giờ"), kho: col("kho"), inv: col("invoice", "inv") };
  // "NƠI HẠ" và "NƠI HẠ SÀ LAN" đều chứa "hạ" → nếu col("hạ") trùng cột sà lan thì bỏ (để to lấy đúng cột Nơi hạ).
  if (C.to >= 0 && C.to === C.bargeDrop) C.to = header.findIndex((h, idx) => h.includes("hạ") && idx !== C.bargeDrop);
  const out = [];
  for (let r = hi + 1; r < aoa.length; r++) {
    const row = aoa[r] || [];
    // Text getter (an toàn với Date objects — toString cho display, nhưng ngày/giờ dùng hàm riêng bên dưới).
    const g = (i) => { if (i < 0) return ""; const v = row[i]; return v instanceof Date ? "" : String(v == null ? "" : v).trim(); };
    if (!g(C.customer) && !g(C.booking) && !g(C.from) && !g(C.to)) continue;
    // Ngày: Date object → tường minh; text → dd/mm/yyyy; năm ngoài 2000-2099 → lỗi.
    const ngay = cellDate(C.ngay >= 0 ? row[C.ngay] : null);
    const hm = cellTime(C.gio >= 0 ? row[C.gio] : null);
    const gioDenDuKien = ngay.iso ? `${ngay.iso}T${hm || "00:00"}` : "";
    const cm = cellDate(C.cutOff >= 0 ? row[C.cutOff] : null);
    const cmHm = cellTime(C.cutOff >= 0 ? row[C.cutOff] : null);
    const cutOff = cm.iso ? `${cm.iso}T${cmHm || "00:00"}` : "";
    out.push({ customer: g(C.customer), booking: g(C.booking), io: g(C.io), qty: String(row[C.qty] == null ? "" : row[C.qty]).replace(/[^\d]/g, ""), qtyRaw: g(C.qty) || String(row[C.qty] ?? ""), contType: g(C.contType), contNo: g(C.contNo), cutOff, cutOffRaw: cm.display || g(C.cutOff), from: g(C.from), to: g(C.to), bargeDrop: g(C.bargeDrop).toUpperCase(), kho: g(C.kho), inv: g(C.inv), gioDenDuKien, ngayRaw: ngay.display || g(C.ngay), gioRaw: hm || g(C.gio) });
  }
  return out;
}

// ===================== IMPORT CSHT (phí CSHT + Thanh lý theo số cont) =====================
// Cột file CSHT. (*) = bắt buộc: Số cont. Khớp cột theo TỪ KHÓA (không phụ thuộc dấu/hoa thường).
export const CSHT_COLS = ["NGÀY HĐ", "SỐ CONT *", "NHẬP/XUẤT", "PHÍ CSHT", "SỐ TIỀN THANH LÝ", "GHI CHÚ", "SỐ HĐ"];

// Đếm số dòng CSHT có dữ liệu (có số cont) — để hiện số lượng trước khi import.
export const cshtRowCount = (rows) => (rows || []).filter((r) => String(r.contNo || "").trim() !== "").length;

// Số tiền: bỏ mọi ký tự không phải số → chuỗi digit (backend tự parse). "" nếu trống.
const money = (v) => { if (v == null) return ""; if (typeof v === "number") return String(Math.round(v)); return String(v).replace(/[^\d]/g, ""); };

// Parse 1 sheet Excel CSHT → mảng dòng { date, dateRaw, contNo, io, csht, thanhLy, note, invoiceNo }.
export function parseCshtRows(wb, sheetName) {
  const aoa = XLSX.utils.sheet_to_json(wb.Sheets[sheetName], { header: 1, raw: true, defval: "" });
  let hi = aoa.findIndex((r) => (r || []).some((c) => { const h = normH(c); return h.includes("cont") || h.includes("csht"); }));
  if (hi < 0) hi = 0;
  const header = (aoa[hi] || []).map(normH);
  const col = (...kws) => header.findIndex((h) => kws.some((k) => h.includes(k)));
  const C = { date: col("ngày", "ngay"), cont: col("cont"), io: col("nhập", "xuất", "nhap", "xuat"), csht: col("csht"), thanhLy: col("thanh lý", "thanh ly", "thanh lí", "thanh li"), note: col("ghi chú", "ghi chu"), inv: col("hđ", "hd", "hóa đơn", "hoa don") };
  // "SỐ HĐ" và "NGÀY HĐ" đều chứa "hđ" → nếu inv trùng cột ngày thì lấy cột hđ KHÁC cột ngày.
  if (C.inv >= 0 && C.inv === C.date) C.inv = header.findIndex((h, idx) => (h.includes("hđ") || h.includes("hd") || h.includes("hóa đơn") || h.includes("hoa don")) && idx !== C.date);
  const out = [];
  for (let r = hi + 1; r < aoa.length; r++) {
    const row = aoa[r] || [];
    const g = (i) => { if (i < 0) return ""; const v = row[i]; return v instanceof Date ? "" : String(v == null ? "" : v).trim(); };
    const cont = g(C.cont);
    const csht = money(C.csht >= 0 ? row[C.csht] : null);
    const thanhLy = money(C.thanhLy >= 0 ? row[C.thanhLy] : null);
    // Bỏ dòng trắng (không cont + không tiền)
    if (!cont && !csht && !thanhLy) continue;
    const d = cellDate(C.date >= 0 ? row[C.date] : null);
    out.push({ date: d.iso, dateRaw: d.display || g(C.date), contNo: cont, io: g(C.io), csht, thanhLy, note: g(C.note), invoiceNo: g(C.inv) });
  }
  return out;
}

// Dựng workbook FILE MẪU import CSHT (1 sheet mẫu + Hướng dẫn).
export function buildCshtTemplateWb() {
  const ex1 = { "NGÀY HĐ": "20/06/2026", "SỐ CONT *": "TGHU1234567", "NHẬP/XUẤT": "Nhập", "PHÍ CSHT": 250000, "SỐ TIỀN THANH LÝ": 180000, "GHI CHÚ": "CSHT tháng 6", "SỐ HĐ": "0001234" };
  const ex2 = { "NGÀY HĐ": "21/06/2026", "SỐ CONT *": "MSKU9981122", "NHẬP/XUẤT": "Xuất", "PHÍ CSHT": 250000, "SỐ TIỀN THANH LÝ": "", "GHI CHÚ": "", "SỐ HĐ": "0001235" };
  const ws = XLSX.utils.json_to_sheet([ex1, ex2], { header: CSHT_COLS });
  ws["!cols"] = CSHT_COLS.map((col) => ({ wch: Math.max(12, col.length + 2) }));
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "CSHT");
  const guide = [
    { "Cột": "NGÀY HĐ", "Bắt buộc": "không", "Ý nghĩa": "Ngày hóa đơn / ngày thanh toán (dd/mm/yyyy) — ghi vào Ngày hóa đơn của khoản chi phí" },
    { "Cột": "SỐ CONT *", "Bắt buộc": "CÓ", "Ý nghĩa": "Số container — phải trùng ĐÚNG 1 lô đang có (trùng nhiều lô hoặc không có sẽ báo lỗi)" },
    { "Cột": "NHẬP/XUẤT", "Bắt buộc": "không", "Ý nghĩa": "Nhập hoặc Xuất — đối chiếu với lô; LỆCH sẽ báo lỗi (để trống = không đối chiếu)" },
    { "Cột": "PHÍ CSHT", "Bắt buộc": "không*", "Ý nghĩa": "Số tiền khoản “CSHT” (đã gồm VAT) — ghi/ghi đè dòng CSHT của lô" },
    { "Cột": "SỐ TIỀN THANH LÝ", "Bắt buộc": "không*", "Ý nghĩa": "Số tiền khoản “Thanh lí” — ghi/ghi đè dòng Thanh lí của lô" },
    { "Cột": "GHI CHÚ", "Bắt buộc": "không", "Ý nghĩa": "Ghi chú — áp cho cả khoản CSHT & Thanh lí của dòng" },
    { "Cột": "SỐ HĐ", "Bắt buộc": "không", "Ý nghĩa": "Số hóa đơn — áp cho cả khoản CSHT & Thanh lí của dòng" },
    { "Cột": "(*) lưu ý", "Bắt buộc": "", "Ý nghĩa": "Mỗi dòng phải có ít nhất 1 trong 2: PHÍ CSHT hoặc SỐ TIỀN THANH LÝ. Import lại sẽ GHI ĐÈ dòng cũ (không nhân đôi)." },
  ];
  const wg = XLSX.utils.json_to_sheet(guide, { header: ["Cột", "Bắt buộc", "Ý nghĩa"] });
  wg["!cols"] = [{ wch: 18 }, { wch: 10 }, { wch: 70 }];
  XLSX.utils.book_append_sheet(wb, wg, "Hướng dẫn");
  return wb;
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
  const ex1 = { "Khách hàng *": exCust, "SỐ BOOKING/BILL *": "BL-ICD-0001", "NHẬP/XUẤT": "Nhập", "SỐ LƯỢNG CONT *": 3, "LOẠI CONT": "40HC", "SỐ CONTAINER": "TGHU1234567\nMSKU9981122\nCSNU4567788", "CẮT MÁNG": "14/05/2026 10:00", "NƠI LẤY": exFrom, "NƠI HẠ": exTo, "NƠI HẠ SÀ LAN": "HPP", "NGÀY ĐẾN DỰ KIẾN": "14/05/2026", "GIỜ ĐẾN DỰ KIẾN": "08:00", "KHO": exKho1, "INVOICE": "INV-001" };
  const ex2 = { "Khách hàng *": exCust, "SỐ BOOKING/BILL *": "BL-ICD-0002", "NHẬP/XUẤT": "Xuất", "SỐ LƯỢNG CONT *": 2, "LOẠI CONT": "20DC", "SỐ CONTAINER": "", "CẮT MÁNG": "15/05/2026 09:00", "NƠI LẤY": codeOf[exFrom] || exFrom, "NƠI HẠ": exTo, "NƠI HẠ SÀ LAN": "", "NGÀY ĐẾN DỰ KIẾN": "15/05/2026", "GIỜ ĐẾN DỰ KIẾN": "07:30", "KHO": exKho2, "INVOICE": "INV-002" };
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
  // Nơi hạ sà lan hợp lệ — CHỈ 2 cảng (hoặc để trống)
  const wb2 = XLSX.utils.json_to_sheet(BARGE_DROPS.map((c) => ({ "Nơi hạ sà lan": c })), { header: ["Nơi hạ sà lan"] });
  wb2["!cols"] = [{ wch: 16 }];
  XLSX.utils.book_append_sheet(wb, wb2, "Sà lan hợp lệ");
  const guide = [
    { "Cột": "Khách hàng *", "Bắt buộc": "CÓ", "Ý nghĩa": "Tên khách — phải trùng danh mục (xem sheet 'Khách hàng hợp lệ')" },
    { "Cột": "SỐ BOOKING/BILL *", "Bắt buộc": "CÓ", "Ý nghĩa": "Số booking / số bill" },
    { "Cột": "SỐ LƯỢNG CONT *", "Bắt buộc": "CÓ", "Ý nghĩa": "Số lượng container (số ≥ 1) — cont để trống sẽ nhân bản theo số này" },
    { "Cột": "NƠI LẤY", "Bắt buộc": "không", "Ý nghĩa": "Điểm lấy hàng — TÊN hoặc KÝ HIỆU trong danh mục Địa điểm (nếu nhập sai sẽ báo lỗi)" },
    { "Cột": "NƠI HẠ", "Bắt buộc": "không", "Ý nghĩa": "Điểm hạ hàng — TÊN hoặc KÝ HIỆU trong danh mục Địa điểm (nếu nhập sai sẽ báo lỗi)" },
    { "Cột": "NƠI HẠ SÀ LAN", "Bắt buộc": "không", "Ý nghĩa": "Điểm đến sà lan — CHỈ nhận HPP hoặc LHP (xem sheet 'Sà lan hợp lệ'). Có giá trị = lô đi sà lan; nhập khác HPP/LHP sẽ báo lỗi, để trống = không đi sà lan" },
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
