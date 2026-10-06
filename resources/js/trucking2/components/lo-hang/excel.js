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

// Ô ngày/giờ ĐỊNH DẠNG NGÀY THẬT của Excel bị lệch ~30 giây khi đổi sang Date: thư viện quy đổi
// mốc 30/12/1899 sang giờ địa phương và chỉ bù lệch múi giờ theo PHÚT NGUYÊN, trong khi giờ chuẩn
// Sài Gòn năm 1899 lẻ 30 giây. Hệ quả: 14/05/2026 thành 13/05/2026 23:59:30 (lùi 1 ngày) và
// 10:30 thành 10:29:30 (hụt 1 phút). Người dùng chỉ nhập tới PHÚT nên cắt/làm tròn phần giây
// theo giờ địa phương là trả lại đúng giá trị trong file.
function snapToMinute(d) {
  const rest = d.getSeconds() * 1000 + d.getMilliseconds();
  return rest === 0 ? d : new Date(d.getTime() + (rest >= 30000 ? 60000 - rest : -rest));
}

function cellDate(v) {
  if (v == null || v === "") return { iso: "", display: "" };
  if (v instanceof Date && !isNaN(v)) {
    v = snapToMinute(v);
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
  if (v instanceof Date && !isNaN(v)) { const t = snapToMinute(v); return `${p2(t.getHours())}:${p2(t.getMinutes())}`; }
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
    // Giờ lấy ở cột GIỜ; cột trống thì lấy phần giờ nằm trong chính ô NGÀY (người dùng hay gộp
    // "14/05/2026 08:00" vào 1 ô) — ô chỉ có ngày thì phần giờ là 00:00 nên không đổi kết quả.
    const hm = cellTime(C.gio >= 0 ? row[C.gio] : null) || cellTime(C.ngay >= 0 ? row[C.ngay] : null);
    const gioDenDuKien = ngay.iso ? `${ngay.iso}T${hm || "00:00"}` : "";
    const cm = cellDate(C.cutOff >= 0 ? row[C.cutOff] : null);
    const cmHm = cellTime(C.cutOff >= 0 ? row[C.cutOff] : null);
    const cutOff = cm.iso ? `${cm.iso}T${cmHm || "00:00"}` : "";
    out.push({ customer: g(C.customer), booking: g(C.booking), io: g(C.io), qty: String(row[C.qty] == null ? "" : row[C.qty]).replace(/[^\d]/g, ""), qtyRaw: g(C.qty) || String(row[C.qty] ?? ""), contType: g(C.contType), contNo: g(C.contNo), cutOff, cutOffRaw: cm.display || g(C.cutOff), from: g(C.from), to: g(C.to), bargeDrop: g(C.bargeDrop).toUpperCase(), kho: g(C.kho), inv: g(C.inv), gioDenDuKien, ngayRaw: ngay.display || g(C.ngay), gioRaw: hm || g(C.gio) });
  }
  return out;
}

// ===================== IMPORT CSHT / CHI PHÍ LÔ HÀNG (1 dòng = 1 lô) =====================
// Bố cục theo mẫu kế toán: 2 dòng tiêu đề — dòng 1 = TÊN KHOẢN ở đầu mỗi nhóm (gộp ô), dòng 2 = tên ô.
// Khớp lô theo ID LÔ; không có ID thì theo SỐ CONT. Vẫn đọc được file 1 dòng tiêu đề cũ + mẫu CSHT cũ.
const F_AMOUNT = ["amount", "Số tiền"], F_VAT = ["vat", "Vat"], F_PAYER = ["payer", "Người chi"], F_DATE = ["date", "Ngày HĐ"];
const COST_INFO = ["ID LÔ", "SỐ CONT", "NHẬP/XUẤT", "KHÁCH HÀNG", "BOOKING", "NGÀY XE RA", "Nhà xe ngoài", "Tuyến"];   // Nhà xe ngoài / Tuyến chỉ để xem
const COST_GROUPS = [
  { item: "Nâng", fields: [F_AMOUNT, F_VAT, ["invoiceNo", "Số hóa đơn"], F_PAYER, F_DATE] },
  { item: "Hạ", fields: [F_AMOUNT, F_VAT, ["invoiceNo", "Số hóa đơn"], F_PAYER, F_DATE] },
  { item: "CSHT", fields: [F_AMOUNT, ["invoiceNo", "Số HĐ"]] },
  { item: "Thanh lí", fields: [F_AMOUNT, ["invoiceNo", "Số HĐ"], F_DATE, ["note", "Ghi chú thanh lí"]] },
];
const COST_CHIHO_ITEM = "Chi hộ LCC";   // cột "CHI HỘ" = số tiền khoản Chi hộ LCC (user chốt)

// Số tiền: bỏ mọi ký tự không phải số → chuỗi digit (backend tự parse). "" nếu trống.
const money = (v) => { if (v == null) return ""; if (typeof v === "number") return String(Math.round(v)); return String(v).replace(/[^\d]/g, ""); };
// VAT: ô định dạng % của Excel đọc ra 0.08 → 8; chữ "8%" → "8".
const vatOf = (v) => { if (v == null || v === "") return ""; if (typeof v === "number") return String(v > 0 && v < 1 ? Math.round(v * 10000) / 100 : v); return String(v).replace(/[^\d.,]/g, "").replace(",", "."); };
// Ngày ISO "Y-m-d…" → "dd/mm/yyyy" (ô Excel dạng chữ, đọc lại bằng cellDate).
const isoToDmy = (iso) => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso || "")); return m ? `${m[3]}/${m[2]}/${m[1]}` : ""; };
const nkItem = (s) => String(s || "").normalize("NFD").replace(/\p{M}/gu, "").replace(/đ/g, "d").replace(/Đ/g, "D").toLowerCase().replace(/[^a-z0-9]/g, "").replace(/y/g, "i");

// Ô thuộc 1 nhóm khoản (dòng 2): tiền / vat / số HĐ / người chi / ngày / ghi chú. null = không phải ô nhóm.
function costGroupField(h) {
  if (!h) return null;
  if (/số tiền|so tien|^tiền|^tien/.test(h)) return "amount";
  if (/^vat|thuế|thue/.test(h)) return "vat";
  if (/số ?(hđ|hd|hóa đơn|hoa don)|so ?(hd|hoa don)|^hóa đơn|^hoa don/.test(h)) return "invoiceNo";
  if (/người chi|nguoi chi/.test(h)) return "payer";
  if (/^(ngày|ngay)/.test(h)) return "date";
  if (/ghi chú|ghi chu/.test(h)) return "note";
  return null;
}
// Cột lẻ (không thuộc nhóm): cố định / thông tin / nhãn / cột tiền mang tên khoản.
function cshtColKind(h) {
  if (!h) return "skip";
  if (/^(id|mã lô|ma lo)\b/.test(h) || h === "id lô" || h === "id lo") return "id";
  if (h.includes("cont") && !h.includes("khoản")) return "cont";
  if (h.includes("nhập") || h.includes("xuất") || h.includes("nhap") || h.includes("xuat")) return "io";
  if (/khách|khach|booking|bill|xe ra|nhà xe|nha xe|tuyến|tuyen/.test(h)) return "info";   // chỉ để dò, không import
  if (/^(nhãn|nhan|tag)/.test(h)) return "tags";
  if (h.includes("ghi chú") || h.includes("ghi chu")) return "note";
  if (/ngày ?(hđ|hd|hóa đơn|hoa don)|ngay ?(hd|hoa don)/.test(h)) return "date";
  if (/số ?(hđ|hd|hóa đơn|hoa don)|so ?(hd|hoa don)/.test(h)) return "inv";
  if (/^(ngày|ngay)\b/.test(h)) return "date";   // file CSHT cũ ghi "NGÀY"
  if (h === "stt" || h === "#") return "skip";
  return "amount";
}

// Parse 1 sheet → [{ line, id, contNo, io, tags, groups: {khoản: {amount,vat,invoiceNo,payer,date,dateRaw,note}},
//   amounts: {tiêu đề: digits}, date, dateRaw, invoiceNo, note }] (3 trường cuối = file 1 dòng tiêu đề cũ).
export function parseCshtRows(wb, sheetName) {
  const aoa = XLSX.utils.sheet_to_json(wb.Sheets[sheetName], { header: 1, raw: true, defval: "" });
  // Dòng tiêu đề = dòng có ID LÔ / SỐ CONT (dòng tên khoản phía trên có thể chứa "CSHT" nên không dò bằng chữ đó
  // trước); mẫu cũ không có 2 cột này thì mới dò theo "csht".
  const findRow = (test) => aoa.findIndex((r) => (r || []).some((c) => test(normH(c))));
  let hi = findRow((h) => h.includes("cont") || /^id\b/.test(h));
  if (hi < 0) hi = findRow((h) => h.includes("csht"));
  if (hi < 0) hi = 0;
  const rawHeader = (aoa[hi] || []).map((c) => String(c == null ? "" : c).trim());
  const groupRow = hi > 0 ? (aoa[hi - 1] || []).map((c) => String(c == null ? "" : c).trim()) : [];
  // Gán cột → nhóm: tên khoản ở dòng trên mở nhóm, các cột sau thuộc nhóm đến khi gặp ô không phải ô nhóm.
  let cur = "";
  const cols = rawHeader.map((raw, i) => {
    const h = normH(raw), gf = costGroupField(h);
    if (groupRow[i]) cur = groupRow[i];
    if (cur && gf) return { kind: "group", group: cur, field: gf };
    cur = "";
    return { kind: cshtColKind(h), header: raw.replace(/\s*\*\s*$/, "") };
  });
  const first = (k) => cols.findIndex((c) => c.kind === k);
  const C = { id: first("id"), cont: first("cont"), io: first("io"), date: first("date"), inv: first("inv"), note: first("note"), tags: first("tags") };
  const out = [];
  for (let r = hi + 1; r < aoa.length; r++) {
    const row = aoa[r] || [];
    const g = (i) => { if (i < 0) return ""; const v = row[i]; return v instanceof Date ? "" : String(v == null ? "" : v).trim(); };
    const amounts = {}, groups = {};
    cols.forEach((c, i) => {
      if (c.kind === "amount") { amounts[c.header] = money(row[i]); return; }
      if (c.kind !== "group") return;
      const o = groups[c.group] || (groups[c.group] = {});
      if (c.field === "amount") o.amount = money(row[i]);
      else if (c.field === "vat") o.vat = vatOf(row[i]);
      else if (c.field === "date") { const d = cellDate(row[i]); o.date = d.iso; o.dateRaw = d.display || g(i); }
      else o[c.field] = g(i);
    });
    Object.keys(groups).forEach((k) => { if (!Object.values(groups[k]).some((v) => String(v || "").trim() !== "")) delete groups[k]; });
    const id = money(C.id >= 0 ? row[C.id] : null);
    const cont = g(C.cont), tags = g(C.tags);
    // Bỏ dòng trắng (không ID, không cont, không tiền / ô nhóm / nhãn)
    if (!id && !cont && !Object.values(amounts).some(Boolean) && !Object.keys(groups).length && !tags) continue;
    const d = cellDate(C.date >= 0 ? row[C.date] : null);
    out.push({ line: r + 1, id, contNo: cont, io: g(C.io), tags, groups, amounts, date: d.iso, dateRaw: d.display || g(C.date), invoiceNo: g(C.inv), note: g(C.note) });
  }
  return out;
}

// Sheet Hướng dẫn dùng chung cho file mẫu và file "Xuất chi phí lô".
function cshtGuideSheet() {
  const guide = [
    { "Cột": "ID LÔ", "Ý nghĩa": "Mã lô trong hệ thống — khớp ĐÚNG lô kể cả khi 1 số cont có ở nhiều lô. Lấy bằng nút “Xuất chi phí lô”. Có ID thì Số cont chỉ để đối chiếu." },
    { "Cột": "SỐ CONT", "Ý nghĩa": "Dòng không có ID LÔ thì khớp theo số cont — cont trùng nhiều lô sẽ báo lỗi." },
    { "Cột": "NHẬP/XUẤT", "Ý nghĩa": "Đối chiếu với lô; LỆCH sẽ báo lỗi (để trống = không đối chiếu)." },
    { "Cột": "KHÁCH HÀNG · BOOKING · NGÀY XE RA · Nhà xe ngoài · Tuyến", "Ý nghĩa": "Chỉ để dò lô — sửa ở đây không có tác dụng." },
    { "Cột": "Nhóm Nâng / Hạ / CSHT / Thanh lí", "Ý nghĩa": "Dòng 1 = tên khoản (đúng tên trong Cài đặt → Khoản chi phí), dòng 2 = ô của khoản: Số tiền (đã gồm VAT) · Vat (%) · Số hóa đơn · Người chi · Ngày HĐ (dd/mm/yyyy) · Ghi chú. Thêm nhóm khoản khác: chèn cột, ghi tên khoản ở dòng 1, tên ô ở dòng 2." },
    { "Cột": "CHI HỘ", "Ý nghĩa": "Số tiền khoản “Chi hộ LCC”." },
    { "Cột": "NHÃN", "Ý nghĩa": "Nhãn của lô, nhiều nhãn cách nhau bằng dấu phẩy. Ô có giá trị = thay đúng danh sách nhãn của lô; ô trống = không đổi." },
    { "Cột": "Quy tắc", "Ý nghĩa": "Ô trống = KHÔNG đổi. Giá trị giống hiện tại = bỏ qua. Lô chưa có khoản thì tạo dòng mới (cần Số tiền), có 1 dòng thì sửa các ô có giá trị. Không bao giờ xóa dòng chi phí (muốn xóa thì xóa trong popup)." },
    { "Cột": "Không import được", "Ý nghĩa": "Phí mở tờ khai (lấy từ tờ khai) · Cước xe ngoài khi lô chưa chọn Thuê xe ngoài · Khoản có 2 dòng trở lên trong cùng lô mà file đổi giá trị (sửa trong popup). 1 dòng lỗi là không import gì cả." },
  ];
  const wg = XLSX.utils.json_to_sheet(guide, { header: ["Cột", "Ý nghĩa"] });
  wg["!cols"] = [{ wch: 44 }, { wch: 120 }];
  return wg;
}

// Giá trị các ô của 1 nhóm khoản cho 1 lô: 1 dòng → đúng giá trị; nhiều dòng → tiền = TỔNG, ô khác chỉ điền
// khi mọi dòng giống nhau (khác nhau thì để trống → import lại không ghi đè sai).
function costGroupValues(lines, field) {
  if (!lines.length) return "";
  if (field === "amount") { const t = lines.reduce((a, c) => a + (parseInt(String(c.amount || "0").replace(/[^\d]/g, ""), 10) || 0), 0); return t || ""; }
  const get = (c) => field === "date" ? isoToDmy(c.date) : field === "vat" ? (c.vat === "" || c.vat == null ? "" : +c.vat) : String(c[field] || "").trim();
  const vals = [...new Set(lines.map(get))];
  return vals.length === 1 ? vals[0] : "";
}

// File "XUẤT CHI PHÍ LÔ" (và file mẫu): bố cục 2 dòng tiêu đề theo mẫu kế toán, 1 dòng / lô.
export function buildCostExportWb(list) {
  const fmtOut = (v) => { const m = /^(\d{4})-(\d{2})-(\d{2})[T ]?(\d{2}:\d{2})?/.exec(String(v || "")); return m ? `${m[3]}/${m[2]}/${m[1]}${m[4] ? " " + m[4] : ""}` : ""; };
  const row1 = [...COST_INFO.map(() => "")], row2 = [...COST_INFO], merges = [];
  COST_GROUPS.forEach((g) => {
    const c0 = row2.length;
    g.fields.forEach(([, label], j) => { row1.push(j === 0 ? g.item : ""); row2.push(label); });
    if (g.fields.length > 1) merges.push({ s: { r: 0, c: c0 }, e: { r: 0, c: c0 + g.fields.length - 1 } });
  });
  row1.push("", ""); row2.push("CHI HỘ", "NHÃN");
  const body = (list || []).map((s) => {
    const lines = (s.cost && s.cost.items) || [];
    const of = (name) => lines.filter((c) => nkItem(c.item) === nkItem(name));
    const r = [s.id, s.contNo || "", s.io || "", s.customer || "", s.booking || "", fmtOut(s.gioXeRa), s.extVendor || "",
      [s.from, s.kho, s.to].map((x) => String(x || "").trim()).filter(Boolean).join(" → ")];
    COST_GROUPS.forEach((g) => { const ls = of(g.item); g.fields.forEach(([f]) => r.push(costGroupValues(ls, f))); });
    r.push(costGroupValues(of(COST_CHIHO_ITEM), "amount"), (s.tags || []).join(", "));
    return r;
  });
  const ws = XLSX.utils.aoa_to_sheet([row1, row2, ...body]);
  ws["!merges"] = merges;
  ws["!autofilter"] = { ref: XLSX.utils.encode_range({ s: { r: 1, c: 0 }, e: { r: 1 + body.length, c: row2.length - 1 } }) };
  const W = { "ID LÔ": 8, "SỐ CONT": 13, "NHẬP/XUẤT": 11, "KHÁCH HÀNG": 22, "BOOKING": 16, "NGÀY XE RA": 16, "Nhà xe ngoài": 16, "Tuyến": 30, "Số tiền": 12, "Vat": 6, "Số hóa đơn": 12, "Số HĐ": 12, "Người chi": 13, "Ngày HĐ": 11, "Ghi chú thanh lí": 22, "CHI HỘ": 12, "NHÃN": 26 };
  ws["!cols"] = row2.map((h) => ({ wch: W[h] || 12 }));
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Chi phí lô");
  XLSX.utils.book_append_sheet(wb, cshtGuideSheet(), "Hướng dẫn");
  return wb;
}

// FILE MẪU: cùng bố cục, 2 dòng ví dụ (không ID → khớp theo số cont).
export function buildCshtTemplateWb() {
  return buildCostExportWb([
    { id: "", contNo: "TGHU1234567", io: "Nhập", cost: { items: [
      { item: "Nâng", amount: "350000", vat: "8", invoiceNo: "0001200", payer: "TK công ty", date: "2026-06-20" },
      { item: "CSHT", amount: "250000", invoiceNo: "0001234" },
      { item: "Thanh lí", amount: "180000", invoiceNo: "0001234", date: "2026-06-20", note: "TL tháng 6" } ] }, tags: [] },
    { id: "", contNo: "MSKU9981122", io: "Xuất", cost: { items: [{ item: "CSHT", amount: "250000", invoiceNo: "0001235" }] }, tags: ["Gấp"] },
  ]);
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
  // Loại cont ví dụ phải LẤY TỪ DANH MỤC — import chặn loại ngoài danh mục, mẫu gõ cứng sẽ lỗi ngay.
  const cts = c.contTypes || [];
  const exCT1 = cts[0] || "40HC";
  const exCT2 = cts[1] || exCT1;
  // KHO ví dụ = ký hiệu kho CÓ THẬT (tránh import mẫu bị lỗi "chưa có trong danh mục kho")
  const whTok = (n) => whCodeOf[n] || n;
  const exKho1 = whs.length >= 2 ? `${whTok(whs[0])}, ${whTok(whs[1])}` : (whs[0] ? whTok(whs[0]) : "");
  const exKho2 = whs[0] ? whTok(whs[0]) : "";
  const ex1 = { "Khách hàng *": exCust, "SỐ BOOKING/BILL *": "BL-ICD-0001", "NHẬP/XUẤT": "Nhập", "SỐ LƯỢNG CONT *": 3, "LOẠI CONT": exCT1, "SỐ CONTAINER": "TGHU1234567\nMSKU9981122\nCSNU4567788", "CẮT MÁNG": "14/05/2026 10:00", "NƠI LẤY": exFrom, "NƠI HẠ": exTo, "NƠI HẠ SÀ LAN": "HPP", "NGÀY ĐẾN DỰ KIẾN": "14/05/2026", "GIỜ ĐẾN DỰ KIẾN": "08:00", "KHO": exKho1, "INVOICE": "INV-001" };
  const ex2 = { "Khách hàng *": exCust, "SỐ BOOKING/BILL *": "BL-ICD-0002", "NHẬP/XUẤT": "Xuất", "SỐ LƯỢNG CONT *": 2, "LOẠI CONT": exCT2, "SỐ CONTAINER": "", "CẮT MÁNG": "15/05/2026 09:00", "NƠI LẤY": codeOf[exFrom] || exFrom, "NƠI HẠ": exTo, "NƠI HẠ SÀ LAN": "", "NGÀY ĐẾN DỰ KIẾN": "15/05/2026", "GIỜ ĐẾN DỰ KIẾN": "07:30", "KHO": exKho2, "INVOICE": "INV-002" };
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
  if (cts.length) {
    const wct = XLSX.utils.json_to_sheet(cts.map((n) => ({ "Loại cont": n })), { header: ["Loại cont"] });
    wct["!cols"] = [{ wch: 16 }];
    XLSX.utils.book_append_sheet(wb, wct, "Loại cont hợp lệ");
  }
  // Nơi hạ sà lan hợp lệ — địa điểm có ký hiệu HPP hoặc LHP
  const bargeLocRows = locs.map((n) => ({ name: n, code: codeOf[n] || "" })).filter((r) => BARGE_DROPS.includes(r.code));
  const bargeSheet = XLSX.utils.json_to_sheet(bargeLocRows.map((r) => ({ "Tên địa điểm": r.name, "Ký hiệu": r.code })), { header: ["Tên địa điểm", "Ký hiệu"] });
  bargeSheet["!cols"] = [{ wch: 22 }, { wch: 10 }];
  XLSX.utils.book_append_sheet(wb, bargeSheet, "Sà lan hợp lệ");
  const guide = [
    { "Cột": "Khách hàng *", "Bắt buộc": "CÓ", "Ý nghĩa": "Tên khách — phải trùng danh mục (xem sheet 'Khách hàng hợp lệ')" },
    { "Cột": "SỐ BOOKING/BILL *", "Bắt buộc": "CÓ", "Ý nghĩa": "Số booking / số bill" },
    { "Cột": "SỐ LƯỢNG CONT *", "Bắt buộc": "CÓ", "Ý nghĩa": "Số lượng container (số ≥ 1) — cont để trống sẽ nhân bản theo số này" },
    { "Cột": "NƠI LẤY", "Bắt buộc": "không", "Ý nghĩa": "Điểm lấy hàng — TÊN hoặc KÝ HIỆU trong danh mục Địa điểm (nếu nhập sai sẽ báo lỗi)" },
    { "Cột": "NƠI HẠ", "Bắt buộc": "không", "Ý nghĩa": "Điểm hạ hàng — TÊN hoặc KÝ HIỆU trong danh mục Địa điểm (nếu nhập sai sẽ báo lỗi)" },
    { "Cột": "NƠI HẠ SÀ LAN", "Bắt buộc": "không", "Ý nghĩa": "Điểm đến sà lan — nhập TÊN hoặc KÝ HIỆU địa điểm có mã HPP/LHP (xem sheet 'Sà lan hợp lệ'). Có giá trị = lô đi sà lan; để trống = không đi sà lan" },
    { "Cột": "NGÀY ĐẾN DỰ KIẾN", "Bắt buộc": "không", "Ý nghĩa": "Ngày xe DỰ KIẾN đến (dd/mm/yyyy)" },
    { "Cột": "GIỜ ĐẾN DỰ KIẾN", "Bắt buộc": "không", "Ý nghĩa": "Giờ xe DỰ KIẾN đến (HH:MM) — ghép với Ngày đến dự kiến" },
    { "Cột": "CẮT MÁNG", "Bắt buộc": "không", "Ý nghĩa": "Hạn cắt máng/tàu (dd/mm/yyyy HH:MM)" },
    { "Cột": "NHẬP/XUẤT", "Bắt buộc": "không", "Ý nghĩa": "Nhập hoặc Xuất" },
    { "Cột": "LOẠI CONT", "Bắt buộc": "không", "Ý nghĩa": "Phải có trong danh mục Loại cont (xem sheet 'Loại cont hợp lệ'); nhập loại chưa khai sẽ báo lỗi — thêm ở Cài đặt → Loại cont" },
    { "Cột": "SỐ CONTAINER", "Bắt buộc": "không", "Ý nghĩa": "Số cont — nhiều cont thì XUỐNG DÒNG trong 1 ô" },
    { "Cột": "KHO", "Bắt buộc": "không", "Ý nghĩa": "Tuyến kho — TÊN hoặc KÝ HIỆU trong danh mục Kho (xem sheet 'Kho hợp lệ'); nhiều đoạn nối bằng dấu phẩy (vd TL, TS); dùng khớp phí xe; nhập sai sẽ báo lỗi" },
    { "Cột": "INVOICE", "Bắt buộc": "không", "Ý nghĩa": "Số invoice (nếu có)" },
  ];
  const wg = XLSX.utils.json_to_sheet(guide, { header: ["Cột", "Bắt buộc", "Ý nghĩa"] });
  wg["!cols"] = [{ wch: 22 }, { wch: 10 }, { wch: 64 }];
  XLSX.utils.book_append_sheet(wb, wg, "Hướng dẫn");
  return wb;
}

// ===================== IMPORT CẬP NHẬT LÔ ĐÃ CÓ =====================
// Vòng lặp an toàn: Xuất để cập nhật (kèm cột ID) → sửa ô cần đổi → Import cập nhật.
// Ô TRỐNG = giữ nguyên; gõ "--" mới xóa giá trị. Backend là nơi kiểm tra/quyết định cuối.
export const CLEAR_TOKEN = "--";

// key = field backend · col = tiêu đề cột · kw = từ khóa nhận diện cột ở file ngoài · dt = ô ngày+giờ
// ro = CHỈ ĐỌC: chỉ để người sửa file biết dòng nào là lô nào (lô chưa điền cont thì nhìn vào
// đây mới nhận ra), KHÔNG gửi lên server nên sửa nhầm cũng không đụng dữ liệu.
const UPD_FIELDS = [
  { key: "id",           col: "ID",              kw: ["id"] },
  { key: "customer",     col: "KHÁCH HÀNG (không sửa)",     kw: ["khách", "khach"], ro: true },
  { key: "booking",      col: "SỐ BOOKING/BILL (không sửa)", kw: ["booking", "bill"], ro: true },
  { key: "contNo",       col: "SỐ CONT",         kw: ["số cont", "so cont", "container"] },
  { key: "contType",     col: "LOẠI CONT",       kw: ["loại", "loai"] },
  { key: "io",           col: "NHẬP/XUẤT",       kw: ["nhập/xuất", "nhap/xuat", "nhập", "xuất"] },
  { key: "gioDenDuKien", col: "GIỜ ĐẾN DỰ KIẾN", kw: ["dự kiến", "du kien"], dt: true },
  { key: "gioXeDen",     col: "GIỜ XE ĐẾN",      kw: ["xe đến", "xe den"], dt: true },
  { key: "bksVao",       col: "BKS VÀO",         kw: ["bks vào", "bks vao", "biển số vào"] },
  // ---- Khối XE RA: 4 cột đọc CÙNG NHAU, theo đúng thứ tự thao tác ở popup: KIỂU RA → (cont ra hộ) → giờ → BKS.
  // Kiểu ra: "Không cắt móc" / "Cont khác ra" / "Không kéo ra" — trống = giữ nguyên.
  { key: "raMode",        col: "KIỂU RA",              kw: ["kiểu ra", "kieu ra"] },
  // Cont khác ra: điền SỐ CONT ra hộ → backend tìm lô CHƯA RA khớp cont đó (booking nào cũng được), gán liên kết.
  // Để trống = giữ nguyên; gõ -- = bỏ liên kết.
  { key: "raOtherContNo", col: "SỐ CONT RA (CẮT MÓC)", kw: ["cont ra", "cắt móc", "cat moc"] },
  // GIỜ XE RA + BKS RA hiểu theo KIỂU RA (như ô nhập của popup): Không cắt móc → của chính cont này ·
  // Cont khác ra → của CONT RA HỘ (ghi sang lô cont đó) · Không kéo ra → giờ/BKS của XE (đầu kéo).
  // BKS RA trống + có giờ ra mới → backend tự lấy BKS VÀO. Xuất: giờ ra HIỆU LỰC theo kiểu ra (gioXeRaEff).
  { key: "gioXeRa",       col: "GIỜ XE RA",            kw: ["xe ra", "giờ ra", "gio ra"], dt: true },
  { key: "bksRa",         col: "BKS RA",               kw: ["bks ra", "biển số ra"] },
  // Nhiều tờ khai / lô: 2 cột SONG SONG theo thứ tự (giữ 1 dòng/lô cho dễ sửa hàng loạt).
  { key: "inv",          col: "INVOICE",         kw: ["invoice", "inv"] },
  { key: "from",         col: "NƠI LẤY",         kw: ["lấy", "lay"] },
  { key: "to",           col: "NƠI HẠ",          kw: ["hạ", "ha"] },
  { key: "bargeDrop",    col: "NƠI HẠ SÀ LAN",   kw: ["sà lan", "sa lan"] },
  { key: "kho",          col: "KHO",             kw: ["kho"] },
  { key: "extVendor",    col: "NHÀ XE NGOÀI",    kw: ["nhà xe", "nha xe"] },
  { key: "extFee",       col: "CƯỚC XE NGOÀI",   kw: ["cước xe", "cuoc xe"], money: true },
  { key: "infoNote",     col: "GHI CHÚ LÔ HÀNG", kw: ["ghi chú", "ghi chu"] },
];
export const UPD_COLS = UPD_FIELDS.map((f) => f.col);

// "2026-06-29T21:45" → "29/06/2026 21:45" (định dạng người dùng đọc/sửa trong Excel)
const dtOut = (iso) => {
  const s = String(iso || "");
  if (s.length < 16) return s.length >= 10 ? s.slice(0, 10).split("-").reverse().join("/") : "";
  return `${s.slice(8, 10)}/${s.slice(5, 7)}/${s.slice(0, 4)} ${s.slice(11, 16)}`;
};

// Dựng workbook "Xuất để cập nhật": ID + các cột sửa được, đổ sẵn giá trị hiện tại.
// c = cfg (danh mục Cài đặt) → kèm các sheet GIÁ TRỊ HỢP LỆ cho mọi cột có ánh xạ danh mục,
// vì import chặn giá trị ngoài danh mục — người sửa file phải tra được ngay trong file.
export function buildUpdateWb(list, c) {
  c = c || {};
  const val = (s, k) => {
    if (k === "id") return s.id;
    if (k === "infoNote") return s.infoNote || "";
    if (k === "raMode") return ({ self: "Không cắt móc", other: "Cont khác ra", none: "Không kéo ra" })[s.raMode || "self"] || "";
    // Số cont ra hộ lấy thẳng từ payload (raOtherContNo), KHÔNG tra theo id trong `list`:
    // lô ra hộ đã ra rồi nên thường không nằm trong tập xuất → tra theo id là ra ô trống.
    if (k === "raOtherContNo") return s.raMode === "other" ? (s.raOtherContNo || "") : "";
    // Giờ ra HIỆU LỰC theo kiểu ra (self→cont này · other→cont ra hộ · none→xe) — backend tính sẵn, cũng là giờ Free time.
    if (k === "gioXeRa") return s.gioXeRaEff || "";
    if (k === "bksRa") return s.raMode === "other" ? (s.raOtherBksRa || "") : (s.bksRa || "");
    const v = s[k];
    return v == null ? "" : v;
  };
  const data = (list || []).map((s) => {
    const o = {};
    for (const f of UPD_FIELDS) {
      const v = val(s, f.key);
      // Tiền xuất ra dạng SỐ để Excel tính/sửa được (rỗng nếu chưa có, không ghi 0 gây nhầm).
      o[f.col] = f.dt ? dtOut(v)
        : f.date ? (String(v).length >= 10 ? String(v).slice(0, 10).split("-").reverse().join("/") : "")
        : (f.money ? (String(v).replace(/[^\d]/g, "") ? +String(v).replace(/[^\d]/g, "") : "") : v);
    }
    return o;
  });
  const ws = XLSX.utils.json_to_sheet(data, { header: UPD_COLS });
  ws["!cols"] = UPD_COLS.map((c) => ({ wch: Math.max(12, c.length + 2) }));
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Cập nhật lô");

  const guide = [
    { "Quy tắc": "Cột ID", "Ý nghĩa": "KHÓA khớp lô — ĐỪNG sửa, đừng xóa cột này. Xóa ID thì hệ thống khớp theo SỐ CONT (cont trùng nhiều lô sẽ báo lỗi)" },
    { "Quy tắc": "KHÁCH HÀNG / SỐ BOOKING (không sửa)", "Ý nghĩa": "Chỉ để bạn nhận ra dòng nào là lô nào — hệ thống KHÔNG đọc 2 cột này, sửa cũng không có tác dụng. Đổi khách/booking thì mở popup lô" },
    { "Quy tắc": "File này chỉ CẬP NHẬT", "Ý nghĩa": "Không tạo lô mới. Dòng không khớp lô nào sẽ báo lỗi — muốn thêm lô thì dùng nút Import lô" },
    { "Quy tắc": "Ô để trống", "Ý nghĩa": "GIỮ NGUYÊN giá trị đang có — không xóa dữ liệu" },
    { "Quy tắc": `Gõ ${CLEAR_TOKEN}`, "Ý nghĩa": "XÓA giá trị của ô đó" },
    { "Quy tắc": "Ngày giờ", "Ý nghĩa": "Cột giờ: dd/mm/yyyy HH:MM (vd 29/06/2026 21:45) · Cột ngày: dd/mm/yyyy" },
    { "Quy tắc": "KIỂU RA", "Ý nghĩa": "3 giá trị: Không cắt móc (xe vào kéo luôn cont này ra) · Cont khác ra (cắt móc, xe kéo cont khác ra) · Không kéo ra (cắt móc, xe ra tay không). Để trống = giữ nguyên" },
    { "Quy tắc": "SỐ CONT RA (CẮT MÓC)", "Ý nghĩa": "Chỉ khi KIỂU RA = Cont khác ra: số cont của lô ra thay. Hệ thống tìm theo 2 điều kiện — đúng số cont VÀ lô đó CHƯA RA (booking nào cũng được) — rồi tự gán liên kết. Gõ -- = bỏ liên kết" },
    { "Quy tắc": "GIỜ XE RA", "Ý nghĩa": "Hiểu theo KIỂU RA, giống ô nhập trong popup: Không cắt móc → giờ ra của chính cont này · Cont khác ra → giờ ra của CONT RA HỘ (ghi sang lô cont đó) · Không kéo ra → giờ xe (đầu kéo) rời đi. Đây cũng là giờ tính Free time" },
    { "Quy tắc": "BKS RA", "Ý nghĩa": "Cũng hiểu theo KIỂU RA như GIỜ XE RA. Để trống mà có điền giờ ra → tự lấy BKS VÀO (xe vào chính là xe ra). Chỉ gõ khi xe ra là xe khác" },
    { "Quy tắc": "Tờ khai", "Ý nghĩa": "KHÔNG sửa ở file này — 1 lô có thể nhiều tờ khai, mỗi tờ khai một phí mở, nên có luồng riêng: nút Cập nhật tờ khai ở trang Lô hàng" },
    { "Quy tắc": "CƯỚC XE NGOÀI", "Ý nghĩa": "Chỉ dùng khi thuê xe ngoài — ghi vào dòng chi phí Cước xe ngoài của lô (các khoản chi phí khác không bị đụng). Phải có NHÀ XE NGOÀI mới nhập được cước" },
    { "Quy tắc": "Cột ánh xạ danh mục", "Ý nghĩa": "Nơi lấy · Nơi hạ · Kho · Loại cont · Nhà xe ngoài · BKS vào/ra — chỉ nhận giá trị CÓ SẴN trong danh mục Cài đặt (xem các sheet hợp lệ trong file này). Sai là báo lỗi, hệ thống KHÔNG tự thêm" },
    { "Quy tắc": "Nơi hạ sà lan", "Ý nghĩa": "TÊN hoặc KÝ HIỆU địa điểm có mã HPP/LHP (sheet Sà lan hợp lệ)" },
    { "Quy tắc": "Cột nhập tự do", "Ý nghĩa": "Số cont · Invoice · Ghi chú · Cước xe ngoài · các cột giờ — không ràng buộc danh mục" },
    { "Quy tắc": "Kiểm tra trước", "Ý nghĩa": "Hệ thống liệt kê từng ô cũ → mới để bạn duyệt; 1 dòng lỗi là KHÔNG ghi gì cả" },
    { "Quy tắc": "Không sửa được ở đây", "Ý nghĩa": "Khách hàng, số lượng, chi phí, doanh thu — sửa trong popup lô hoặc luồng riêng" },
  ];
  const wg = XLSX.utils.json_to_sheet(guide, { header: ["Quy tắc", "Ý nghĩa"] });
  wg["!cols"] = [{ wch: 42 }, { wch: 78 }];
  XLSX.utils.book_append_sheet(wb, wg, "Hướng dẫn");

  // ---- Sheet GIÁ TRỊ HỢP LỆ: mỗi cột ánh xạ danh mục đều phải tra được ngay trong file ----
  const addSheet = (name, rows, header, widths) => {
    if (!rows.length) return;
    const ws2 = XLSX.utils.json_to_sheet(rows, { header });
    ws2["!cols"] = widths.map((w) => ({ wch: w }));
    XLSX.utils.book_append_sheet(wb, ws2, name);
  };
  const locCode = c.locationCode || {};
  const whCode = c.warehouseCode || {};
  addSheet("Địa điểm hợp lệ", (c.locations || []).map((n) => ({ "Tên địa điểm": n, "Ký hiệu": locCode[n] || "" })),
    ["Tên địa điểm", "Ký hiệu"], [32, 14]);
  addSheet("Kho hợp lệ", (c.warehouses || []).map((n) => ({ "Tên kho": n, "Ký hiệu": whCode[n] || "" })),
    ["Tên kho", "Ký hiệu"], [28, 14]);
  addSheet("Loại cont hợp lệ", (c.contTypes || []).map((n) => ({ "Loại cont": n })), ["Loại cont"], [16]);
  addSheet("Nhà xe ngoài hợp lệ", (c.extVendors || []).map((n) => ({ "Đơn vị xe ngoài": n })), ["Đơn vị xe ngoài"], [34]);
  addSheet("Biển số hợp lệ", (c.vehicles || []).map((n) => ({ "Biển số": n })), ["Biển số"], [18]);
  addSheet("Kiểu ra hợp lệ", [
    { "Giá trị": "Không cắt móc", "Ý nghĩa": "Xe vào kéo luôn chính cont này ra (mặc định) — GIỜ XE RA / BKS RA là của cont này" },
    { "Giá trị": "Cont khác ra", "Ý nghĩa": "Cắt móc, xe kéo cont khác ra — điền SỐ CONT RA (CẮT MÓC); GIỜ XE RA / BKS RA ghi cho cont đó" },
    { "Giá trị": "Không kéo ra", "Ý nghĩa": "Cắt móc, xe ra tay không — GIỜ XE RA là giờ xe (đầu kéo) rời đi, cont vẫn chưa ra" },
  ], ["Giá trị", "Ý nghĩa"], [20, 55]);
  addSheet("Sà lan hợp lệ", (c.locations || []).filter((n) => BARGE_DROPS.includes(locCode[n])).map((n) => ({ "Tên địa điểm": n, "Ký hiệu": locCode[n] || "" })), ["Tên địa điểm", "Ký hiệu"], [22, 10]);
  return wb;
}

// Parse sheet cập nhật → [{ line, id, contNo, values:{field:giá trị}, raws:{field:chữ gốc} }].
// Chỉ gửi ô CÓ nội dung (ô trống = giữ nguyên nên không cần gửi).
export function parseUpdateRows(wb, sheetName) {
  const aoa = XLSX.utils.sheet_to_json(wb.Sheets[sheetName], { header: 1, raw: true, defval: "" });
  let hi = aoa.findIndex((r) => (r || []).some((c) => { const h = normH(c); return h === "id" || h.includes("cont"); }));
  if (hi < 0) hi = 0;
  const header = (aoa[hi] || []).map(normH);
  // Ưu tiên khớp CHÍNH XÁC tiêu đề (file do hệ thống xuất), rồi mới tới từ khóa (file ngoài).
  const used = new Set();
  const C = {};
  for (const f of UPD_FIELDS) {
    let idx = header.findIndex((h, i) => !used.has(i) && h === normH(f.col));
    if (idx < 0) idx = header.findIndex((h, i) => !used.has(i) && f.kw.some((k) => h.includes(k)));
    if (idx >= 0) { C[f.key] = idx; used.add(idx); }
  }
  const out = [];
  for (let r = hi + 1; r < aoa.length; r++) {
    const row = aoa[r] || [];
    const cell = (i) => (i == null || i < 0 ? null : row[i]);
    const text = (i) => { const v = cell(i); return v instanceof Date ? "" : String(v == null ? "" : v).trim(); };
    const id = text(C.id).replace(/[^\d]/g, "");
    const contNo = text(C.contNo);
    if (!id && !contNo) continue;   // dòng trắng

    const values = {}; const raws = {};
    for (const f of UPD_FIELDS) {
      if (f.key === "id" || f.ro || C[f.key] == null) continue;   // cột khóa + cột chỉ đọc: không gửi lên
      const v = cell(C[f.key]);
      const raw = v instanceof Date ? "" : String(v == null ? "" : v).trim();
      if (v instanceof Date || (typeof v === "number" && (f.dt || f.date))) {
        // ô ngày thật của Excel
        const d = cellDate(v); const t = cellTime(v);
        values[f.key] = d.iso ? (f.date ? d.iso : `${d.iso}T${t || "00:00"}`) : "";
        raws[f.key] = d.display || String(v);
        if (!values[f.key]) values[f.key] = raws[f.key];   // để backend báo sai định dạng
        continue;
      }
      if (raw === "") continue;
      if (raw.startsWith("(")) continue;   // ô chỉ dẫn kiểu "(xem sheet Tờ khai)" — không phải dữ liệu
      if (raw === CLEAR_TOKEN) { values[f.key] = CLEAR_TOKEN; continue; }
      if (f.money) { values[f.key] = raw.replace(/[^\d]/g, ""); raws[f.key] = raw; continue; }   // tiền: chỉ giữ chữ số
      if (f.dt || f.date) {
        const d = cellDate(raw); const t = cellTime(raw);
        values[f.key] = d.iso ? (f.date ? d.iso : `${d.iso}T${t || "00:00"}`) : raw;   // không parse được → gửi nguyên để backend báo lỗi
        raws[f.key] = raw;
        continue;
      }
      values[f.key] = raw;
    }
    out.push({ line: r + 1, id, contNo, values, raws });
  }

  return out;
}

// ===================== CẬP NHẬT TỜ KHAI (luồng riêng) =====================
// 1 lô nhiều tờ khai, mỗi tờ khai 1 phí mở → mỗi TỜ KHAI là 1 dòng, mỗi ô đúng 1 giá trị.
// Tách khỏi file cập nhật lô để không phải nhồi danh sách vào 1 ô (dấu phẩy đụng dấu phân cách nghìn).
export const DECL_COLS = ["ID LÔ", "SỐ CONT (không sửa)", "KHÁCH HÀNG (không sửa)", "SỐ TỜ KHAI", "PHÍ MỞ TỜ KHAI"];

// Dựng workbook "Xuất tờ khai": mỗi tờ khai 1 dòng; lô CHƯA có tờ khai vẫn có 1 dòng trống để điền.
export function buildDeclarationWb(list) {
  const rows = [];
  for (const s of (list || [])) {
    const base = { "ID LÔ": s.id, "SỐ CONT (không sửa)": s.contNo || "", "KHÁCH HÀNG (không sửa)": s.customer || "" };
    const ds = Array.isArray(s.declarations) ? s.declarations : [];
    if (!ds.length) { rows.push({ ...base, "SỐ TỜ KHAI": "", "PHÍ MỞ TỜ KHAI": "" }); continue; }
    for (const d of ds) {
      const fee = String(d.fee || "").replace(/[^\d]/g, "");
      rows.push({ ...base, "SỐ TỜ KHAI": d.no || "", "PHÍ MỞ TỜ KHAI": fee ? +fee : "" });
    }
  }
  const ws = XLSX.utils.json_to_sheet(rows, { header: DECL_COLS });
  ws["!cols"] = [{ wch: 9 }, { wch: 20 }, { wch: 24 }, { wch: 22 }, { wch: 16 }];
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Tờ khai");
  const guide = [
    { "Quy tắc": "Cột ID LÔ", "Ý nghĩa": "KHÓA khớp lô — đừng sửa. Một lô nhiều tờ khai thì lặp lại ID ở nhiều dòng" },
    { "Quy tắc": "Thêm tờ khai", "Ý nghĩa": "Chèn dòng mới, điền ID LÔ + SỐ TỜ KHAI + PHÍ MỞ TỜ KHAI" },
    { "Quy tắc": "Sửa phí", "Ý nghĩa": "Sửa thẳng ô PHÍ MỞ TỜ KHAI của dòng đó (mỗi ô 1 số, gõ 250000 hay 250.000 đều được)" },
    { "Quy tắc": "Xóa 1 tờ khai", "Ý nghĩa": "Xóa dòng đó đi — các tờ khai còn lại của lô vẫn giữ" },
    { "Quy tắc": `Xóa HẾT tờ khai của 1 lô`, "Ý nghĩa": `Để lại 1 dòng của lô đó và gõ ${CLEAR_TOKEN} ở ô SỐ TỜ KHAI` },
    { "Quy tắc": "Lô không đụng tới", "Ý nghĩa": "Giữ nguyên dòng (hoặc xóa khỏi file) — chỉ lô có mặt trong file mới bị ghi" },
    { "Quy tắc": "Phí mở tờ khai", "Ý nghĩa": "Tổng phí các tờ khai của lô tự vào chi phí lô ở khoản Phí mở tờ khai" },
    { "Quy tắc": "Kiểm tra trước", "Ý nghĩa": "Hệ thống liệt kê từng lô cũ → mới để bạn duyệt; 1 dòng lỗi là KHÔNG ghi gì cả" },
  ];
  const wg = XLSX.utils.json_to_sheet(guide, { header: ["Quy tắc", "Ý nghĩa"] });
  wg["!cols"] = [{ wch: 30 }, { wch: 84 }];
  XLSX.utils.book_append_sheet(wb, wg, "Hướng dẫn");
  return wb;
}

// Parse sheet tờ khai → gom theo lô: [{ line, id, values: { declPairs: [{no, fee}] } }].
// Gửi lên CÙNG endpoint cập nhật lô (backend đã hiểu declPairs).
export function parseDeclarationRows(wb, sheetName) {
  const aoa = XLSX.utils.sheet_to_json(wb.Sheets[sheetName], { header: 1, raw: true, defval: "" });
  let hi = aoa.findIndex((r) => (r || []).some((c) => normH(c).includes("id")));
  if (hi < 0) hi = 0;
  const header = (aoa[hi] || []).map(normH);
  const col = (...kws) => header.findIndex((h) => kws.some((k) => h.includes(k)));
  const cId = col("id"), cNo = col("số tờ khai", "so to khai", "tờ khai"), cFee = col("phí", "phi");

  const byId = {}; const lineOf = {};
  for (let r = hi + 1; r < aoa.length; r++) {
    const row = aoa[r] || [];
    const g = (i) => (i < 0 ? "" : String(row[i] == null ? "" : row[i]).trim());
    const id = g(cId).replace(/[^\d]/g, "");
    if (!id) continue;
    const no = g(cNo);
    if (lineOf[id] == null) lineOf[id] = r + 1;
    if (!byId[id]) byId[id] = [];
    if (no === CLEAR_TOKEN) { byId[id] = "clear"; continue; }      // xóa hết tờ khai của lô
    if (no === "" || byId[id] === "clear") continue;               // dòng trống = lô chưa có tờ khai
    byId[id].push({ no, fee: g(cFee).replace(/[^\d]/g, "") });
  }
  return Object.entries(byId)
    .filter(([, v]) => v === "clear" || v.length)                  // lô không điền gì → không gửi
    .map(([id, v]) => ({ line: lineOf[id], id, contNo: "", raws: {}, values: { declPairs: v === "clear" ? [] : v } }));
}

// Đếm dòng có dữ liệu (hiện số trước khi gửi kiểm tra).
export const updRowCount = (rows) => (rows || []).length;
