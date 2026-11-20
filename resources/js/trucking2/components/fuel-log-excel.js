// Excel cho Nhật ký đổ dầu: XUẤT (đúng mẫu kế toán "MBF nhap dau") + ĐỌC file nhập hàng loạt.
// Dùng XLSX (global, nạp ở blade). Khớp cột theo TỪ KHÓA tiêu đề → nhận cả file kế toán (ô gộp, cột cách quãng)
// lẫn file xuất từ trang này.
import { cellDate } from "@trk/components/lo-hang/excel.js";

export const FUEL_COLS = ["Ngày đổ dầu", "Biển số xe", "ĐVT", "Số lượng", "Đơn giá", "Thành tiền", "Trạm đổ", "Km đồng hồ", "Ghi chú"];

const normH = (s) => String(s == null ? "" : s).trim().toLowerCase().replace(/\s+/g, " ");
const p2 = (n) => String(n).padStart(2, "0");
const ddmmyyyy = (ymd) => { const [y, m, d] = String(ymd || "").split("-"); return y ? `${d}/${m}/${y}` : ""; };

// Cột → từ khóa tiêu đề (so khớp "chứa"); thứ tự quan trọng: "số lượng" trước khi xét "lít".
const FIELD_KEYS = [
  ["date", ["ngày"]],
  ["plate", ["biển số", "bks"]],
  ["liters", ["số lượng", "số lít"]],
  ["unitPrice", ["đơn giá"]],
  ["totalCost", ["thành tiền"]],
  ["station", ["trạm"]],
  ["odometerKm", ["km"]],
  ["note", ["ghi chú"]],
];

// Số trong ô: số thật giữ nguyên; chữ kiểu VN "1.234,5" / "252,000" (dấu phẩy thập phân) hoặc "1,234.5".
function cellNum(v) {
  if (v == null || v === "") return null;
  if (typeof v === "number") return isFinite(v) ? v : null;
  let s = String(v).replace(/\s/g, "").replace(/[^\d.,-]/g, "");
  if (!s) return null;
  if (s.includes(",") && s.includes(".")) s = s.lastIndexOf(",") > s.lastIndexOf(".") ? s.replace(/\./g, "").replace(",", ".") : s.replace(/,/g, "");
  else if (/^\d{1,3}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, "");   // 1.234.000 = nghìn
  else s = s.replace(",", ".");
  const n = parseFloat(s);
  return isFinite(n) ? n : null;
}

/** Đọc 1 sheet → { rows: [{line, date, dateText, plate, liters, unitPrice, totalCost, station, odometerKm, note}], error }. */
export function parseFuelSheet(wb, sheetName) {
  const ws = wb.Sheets[sheetName];
  const aoa = XLSX.utils.sheet_to_json(ws, { header: 1, raw: true, defval: "" });
  const row0 = ws && ws["!ref"] ? XLSX.utils.decode_range(ws["!ref"]).s.r : 0;   // vùng dữ liệu có thể không bắt đầu ở dòng 1 → "Dòng" = số dòng thật trong Excel
  // Dòng tiêu đề = dòng đầu (trong 20 dòng) có cả "biển số" và "số lượng"/"số lít".
  const hIdx = aoa.slice(0, 20).findIndex((r) => r.some((c) => normH(c).includes("biển số")) && r.some((c) => /số lượng|số lít/.test(normH(c))));
  if (hIdx < 0) return { rows: [], error: "Không thấy dòng tiêu đề (cần có cột “Biển số xe” và “Số lượng”)." };
  const col = {};
  aoa[hIdx].forEach((c, i) => {
    const h = normH(c);
    if (!h) return;
    const hit = FIELD_KEYS.find(([f, keys]) => col[f] == null && keys.some((k) => h.includes(k)));
    if (hit) col[hit[0]] = i;
  });
  const get = (r, f) => (col[f] == null ? "" : r[col[f]]);
  const rows = [];
  for (let i = hIdx + 1; i < aoa.length; i++) {
    const r = aoa[i];
    const first = normH(r.find((c) => String(c).trim() !== ""));
    if (first.startsWith("tổng cộng") || first.startsWith("cộng")) break;   // hết bảng (dòng tổng + chữ ký)
    const d = cellDate(get(r, "date"));
    const plate = String(get(r, "plate") || "").trim();
    const liters = cellNum(get(r, "liters"));
    if (!d.display && !plate && liters == null) continue;   // dòng trống
    rows.push({
      line: row0 + i + 1, date: d.iso, dateText: d.display, plate, liters,
      unitPrice: cellNum(get(r, "unitPrice")), totalCost: cellNum(get(r, "totalCost")),
      station: String(get(r, "station") || "").trim(), odometerKm: cellNum(get(r, "odometerKm")), note: String(get(r, "note") || "").trim(),
    });
  }
  return { rows, error: rows.length ? "" : "Sheet không có dòng đổ dầu nào dưới tiêu đề." };
}

/** Workbook xuất: tiêu đề + bảng cùng cột mẫu kế toán + dòng Tổng cộng (ngày ghi dạng chữ dd/mm/yyyy như file mẫu). */
export function buildFuelWb(rows, title) {
  const body = rows.map((r) => [ddmmyyyy(r.date), r.plate, "Lít", r.liters, r.unitPrice ?? "", r.totalCost ?? "", r.station || "", r.odometerKm ?? "", r.note || ""]);
  const sum = (k) => rows.reduce((s, r) => s + (Number(r[k]) || 0), 0);
  const aoa = [[title], FUEL_COLS, ...body];
  if (rows.length) aoa.push(["Tổng cộng", "", "", sum("liters"), "", sum("totalCost"), "", "", ""]);
  const ws = XLSX.utils.aoa_to_sheet(aoa);
  // định dạng số: lít 3 số lẻ như mẫu, tiền không lẻ
  for (let r = 2; r < aoa.length; r++) {
    [[3, "#,##0.###"], [4, "#,##0"], [5, "#,##0"], [7, "#,##0"]].forEach(([c, z]) => {
      const cell = ws[XLSX.utils.encode_cell({ r, c })];
      if (cell && cell.t === "n") cell.z = z;
    });
  }
  ws["!cols"] = [{ wch: 13 }, { wch: 13 }, { wch: 6 }, { wch: 11 }, { wch: 11 }, { wch: 14 }, { wch: 18 }, { wch: 11 }, { wch: 28 }];
  ws["!merges"] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: FUEL_COLS.length - 1 } }];
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Nhật ký đổ dầu");
  return wb;
}

/** File mẫu nhập: đúng cột + sheet hướng dẫn. */
export function buildFuelTemplateWb() {
  const wb = buildFuelWb([], "NHẬP ĐỔ DẦU — mỗi dòng 1 lần đổ (xóa dòng này cũng được)");
  const guide = [
    ["Cột", "Bắt buộc", "Ghi chú"],
    ["Ngày đổ dầu", "Có", "dd/mm/yyyy (vd 01/10/2026) hoặc ô ngày của Excel"],
    ["Biển số xe", "Có", "Xe MBF; gõ 29H-81846 hay 29H81846 đều được"],
    ["ĐVT", "", "Bỏ qua (luôn tính theo lít)"],
    ["Số lượng", "Có", "Số lít đổ, > 0"],
    ["Đơn giá", "", "Trống cả Đơn giá và Thành tiền → lấy theo Bảng giá dầu của ngày đổ"],
    ["Thành tiền", "", "Có Đơn giá thì hệ thống tự tính = lít × đơn giá"],
    ["Trạm đổ / Km đồng hồ / Ghi chú", "", "Tùy chọn"],
    ["", "", ""],
    ["Dòng “Tổng cộng”", "", "Dừng đọc tại đây — phần chữ ký bên dưới không ảnh hưởng"],
    ["Nhập lại cùng file", "", "Dòng trùng (cùng xe + ngày + số lít đã có) được bỏ qua, không cộng đôi"],
  ];
  const wg = XLSX.utils.aoa_to_sheet(guide);
  wg["!cols"] = [{ wch: 30 }, { wch: 10 }, { wch: 70 }];
  XLSX.utils.book_append_sheet(wb, wg, "Hướng dẫn");
  return wb;
}

export const fileStamp = () => { const d = new Date(); return `${d.getFullYear()}${p2(d.getMonth() + 1)}${p2(d.getDate())}`; };
