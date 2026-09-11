// Xuất Excel BẢNG GIÁ / MẪU BÁO GIÁ (1 price book) — data vào → workbook ra, không đụng React state.
// CÙNG 1 định dạng PHẲNG cho "Tải mẫu" và "Xuất Excel" → file nhập lại được bằng "Nhập báo giá".
// Dùng XLSX global (nạp sẵn ở blade bang-gia).

/** Cột cố định (trước các cột giá theo loại cont). Backend nhận dạng mẫu qua tiêu đề FROM + ĐIỂM HẠ. */
export const BASE_COLS = ["ĐIỂM HẠ", "TRẠNG THÁI", "KIND", "FROM", "TO", "TO 2", "TO 3", "TO 4", "KM"];
export const DATA_SHEET = "Bảng giá";

// Khóa loại cont CHUẨN — khớp backend contKeyNorm: in hoa, bỏ dấu, chỉ giữ A-Z0-9, bỏ tiền tố CONT.
export const contKey = (v) => String(v == null ? "" : v).normalize("NFD").replace(/\p{M}/gu, "").toUpperCase().replace(/[^A-Z0-9]/g, "").replace(/^CONT(?=\d)/, "");
/** Cột CHUNG theo cỡ (20FT/40FT/45FT hoặc 20/40/45): áp mọi loại cont cùng cỡ chưa có cột riêng. */
export const isGenericKey = (k) => /^\d{2}(FT)?$/.test(k);
export const sizeOfKey = (k) => (/^\d{2}/.exec(k) || [""])[0];
export const GENERIC_KEYS = ["20FT", "40FT", "45FT"];

/** Thứ tự cột loại cont: theo danh mục (Cài đặt → Loại cont) trước; còn lại xếp theo cỡ rồi tên. */
export function orderContKeys(keys, catalog = []) {
  const cat = [...new Set((catalog || []).map(contKey).filter(Boolean))];
  const set = [...new Set((keys || []).map(contKey).filter(Boolean))];
  const inCat = cat.filter((k) => set.includes(k));
  const rest = set.filter((k) => !cat.includes(k)).sort((a, b) => (sizeOfKey(a) || "99").localeCompare(sizeOfKey(b) || "99") || a.localeCompare(b));
  return [...inCat, ...rest];
}

/** Hợp các khóa loại cont có trên dòng giá (kể cả ô để trống). */
export function rowContKeys(rows) {
  const s = new Set();
  (rows || []).forEach((r) => Object.keys(r.prices || {}).forEach((k) => { const n = contKey(k); if (n) s.add(n); }));
  return [...s];
}

const num = (v) => { const d = String(v == null ? "" : v).replace(/[^\d]/g, ""); return d ? +d : ""; };
// Bỏ ký tự Excel cấm trong tên sheet + cắt 31 ký tự.
const safeSheet = (s) => (String(s || DATA_SHEET).replace(/[\\/?*[\]:]/g, "-").slice(0, 31) || DATA_SHEET);

/** Sheet dữ liệu: 1 dòng = 1 tuyến, sắp theo Điểm hạ → Trạng thái → KIND (giữ thứ tự trong nhóm). */
function dataSheet(rows, contCols) {
  const list = (rows || []).map((r, i) => ({ r, i }));
  const key = (r) => [r.loc || "", r.conn || "Connect", r.kind || "Chưa phân nhóm"];
  list.sort((a, b) => {
    const ka = key(a.r), kb = key(b.r);
    for (let n = 0; n < ka.length; n++) { const c = ka[n].localeCompare(kb[n], "vi"); if (c) return c; }
    return a.i - b.i;   // ổn định: giữ thứ tự gốc trong cùng nhóm
  });
  const header = [...BASE_COLS, ...contCols];
  const data = list.map(({ r }) => {
    const o = {
      "ĐIỂM HẠ": r.loc || "",
      "TRẠNG THÁI": r.conn || "Connect",
      "KIND": r.kind || "Chưa phân nhóm",
      "FROM": r.from || "",
      "TO": r.to1 || "",
      "TO 2": r.to2 || "",
      "TO 3": r.to3 || "",
      "TO 4": r.to4 || "",
      "KM": num(r.distance),
    };
    contCols.forEach((k) => { o[k] = num((r.prices || {})[k]); });
    return o;
  });
  const ws = XLSX.utils.json_to_sheet(data, { header });
  ws["!cols"] = [16, 12, 38, 9, 9, 9, 9, 9, 7, ...contCols.map(() => 14)].map((wch) => ({ wch }));
  ws["!autofilter"] = { ref: XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: Math.max(data.length, 1), c: header.length - 1 } }) };
  // Cột tiền hiện dấu phân cách nghìn (dữ liệu vẫn là SỐ để Excel tính được).
  for (let i = 0; i < data.length; i++) {
    for (let c = BASE_COLS.length; c < header.length; c++) {
      const cell = ws[XLSX.utils.encode_cell({ r: i + 1, c })];
      if (cell && cell.t === "n") cell.z = "#,##0";
    }
  }
  return { ws, count: data.length };
}

/** Sheet hướng dẫn điền + thông tin bảng giá (để file rời vẫn tra được nguồn). */
function guideSheet(meta, count) {
  const lines = [
    ["Khách hàng", meta.customer || ""],
    ["Bảng giá", meta.label || ""],
    ["Khoảng ngày áp dụng", meta.range || "Mọi ngày"],
    ["Số tuyến", count],
    ["Ngày xuất", meta.exportedAt || ""],
    [],
    ["HƯỚNG DẪN ĐIỀN (sheet \"" + DATA_SHEET + "\")", ""],
    ["1 dòng = 1 tuyến", "Giữ nguyên dòng tiêu đề. Xóa các dòng ví dụ (nếu là file mẫu) trước khi nhập."],
    ["ĐIỂM HẠ", "Ký hiệu địa điểm HẠ cont (vd HPP, LHP, ICDQV) — phải có trong Cài đặt → Địa điểm. Để trống = giống dòng trên."],
    ["TRẠNG THÁI", "Connect / Disconnect / Non. Non = áp mọi trạng thái (dùng cho sà lan). Để trống = giống dòng trên."],
    ["KIND", "Nhóm giá: Transportation 1 way of Import/Export · Internal CRU transportation · External CRU transportation · DRY CONTAINER / NOR CONTAINER (sà lan). Để trống = giống dòng trên."],
    ["FROM", "Ký hiệu điểm ĐI (bắt buộc)."],
    ["TO … TO 4", "Ký hiệu NHÀ MÁY / kho (Cài đặt → Kho). Tuyến sà lan để trống."],
    ["KM", "Khoảng cách (tùy chọn)."],
    ["Cột giá (sau KM)", "Mỗi LOẠI CONT 1 cột, tiêu đề = tên loại cont trong danh mục (vd 20DC, 40HC, 40RHC). Ô = giá TỔNG (cước + dầu) cho 1 cont, số nguyên VND. Ô trống = tuyến không có giá cho loại cont đó."],
    ["Cột chung", "Tiêu đề 20FT / 40FT / 45FT = giá chung cho mọi loại cont cùng cỡ chưa có cột riêng (bảng giá cũ được chuyển về dạng này)."],
    ["Thêm loại cont", "Thêm cột mới với tiêu đề là tên loại cont; tên phải có trong Cài đặt → Loại cont (xem sheet \"Loại cont\"), nếu không hệ thống sẽ chặn khi nhập."],
    ["Nhập lên", "Trang Bảng giá → chọn khách + bảng giá → \"Nhập báo giá\" → chọn file → Kiểm tra → Import. Nhập sẽ GHI ĐÈ toàn bộ dòng của bảng giá đang chọn."],
  ];
  const ws = XLSX.utils.aoa_to_sheet(lines);
  ws["!cols"] = [{ wch: 30 }, { wch: 110 }];
  return ws;
}

/** Sheet danh mục loại cont hợp lệ (tiêu đề cột giá). */
function contTypeSheet(catalog) {
  const rows = (catalog || []).map((n) => ({ "Loại cont (Cài đặt)": n, "Tiêu đề cột trong file": contKey(n) }));
  GENERIC_KEYS.forEach((g) => rows.push({ "Loại cont (Cài đặt)": `(cột chung cỡ ${sizeOfKey(g)})`, "Tiêu đề cột trong file": g }));
  const ws = XLSX.utils.json_to_sheet(rows, { header: ["Loại cont (Cài đặt)", "Tiêu đề cột trong file"] });
  ws["!cols"] = [{ wch: 28 }, { wch: 26 }];
  return ws;
}

function assemble(rows, meta) {
  const contCols = meta.contCols && meta.contCols.length ? meta.contCols : orderContKeys(rowContKeys(rows), meta.contTypes);
  const { ws, count } = dataSheet(rows, contCols);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, safeSheet(DATA_SHEET));
  XLSX.utils.book_append_sheet(wb, guideSheet(meta, count), "Hướng dẫn");
  XLSX.utils.book_append_sheet(wb, contTypeSheet(meta.contTypes), "Loại cont");
  return wb;
}

/** Workbook bảng giá đang xem (nhập lại được). meta: {customer,label,range,exportedAt,contCols?,contTypes} */
export function buildPriceBookWb(rows, meta = {}) {
  return assemble(rows, meta);
}

/**
 * Workbook MẪU BÁO GIÁ cho kế toán điền: cột giá = toàn bộ loại cont trong danh mục + 3 dòng ví dụ
 * (Connect / Disconnect / sà lan Non). meta: {customer,label,range,exportedAt,contTypes,sampleLoc?,sampleFrom?,sampleKho?}
 */
export function buildPriceTemplateWb(meta = {}) {
  const contCols = orderContKeys(meta.contTypes || [], meta.contTypes || []);
  const loc = meta.sampleLoc || "HPP", from = meta.sampleFrom || "HPP", kho = meta.sampleKho || "TL";
  const mk = (i) => Object.fromEntries(contCols.map((k, j) => [k, 5000000 + i * 250000 + j * 100000]));
  const rows = [
    { loc, conn: "Connect", kind: "Transportation 1 way of Import/Export", from, to1: kho, to4: loc, distance: "287", prices: mk(0) },
    { loc, conn: "Disconnect", kind: "Transportation 1 way of Import/Export", from, to1: kho, to4: loc, distance: "287", prices: mk(1) },
    { loc, conn: "Non", kind: "DRY CONTAINER", from: "ICDTP", distance: "", prices: mk(2) },
  ];
  return assemble(rows, { ...meta, contCols });
}
