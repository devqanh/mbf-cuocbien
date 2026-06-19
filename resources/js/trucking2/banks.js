// Danh sách ngân hàng VietQR + dựng ảnh QR chuyển khoản. Dùng cho: chọn NH ở Cài đặt lái xe + QR "chi cho lái".
// Nguồn: https://api.vietqr.io/v2/banks (công khai). Cache localStorage 30 ngày vì danh sách NH gần như bất biến.

const KEY = "trk_vietqr_banks_v1";
const TTL = 30 * 864e5; // 30 ngày
let _mem = null; // cache trong RAM của tab hiện tại

// Đọc cache đồng bộ (không gọi mạng) — đủ để dựng QR / hiển thị tên NH ngay khi đã từng tải.
export function banksSync() {
  if (_mem) return _mem;
  try {
    const c = JSON.parse(localStorage.getItem(KEY) || "null");
    if (c && Array.isArray(c.data) && c.data.length) { _mem = c.data; return _mem; }
  } catch (e) {}
  return [];
}

// Tải danh sách NH (ưu tiên cache còn hạn, hết hạn thì fetch lại; fetch lỗi vẫn trả cache cũ nếu có).
export async function loadBanks() {
  try {
    const c = JSON.parse(localStorage.getItem(KEY) || "null");
    if (c && c.ts && Date.now() - c.ts < TTL && Array.isArray(c.data) && c.data.length) { _mem = c.data; return _mem; }
  } catch (e) {}
  try {
    const r = await fetch("https://api.vietqr.io/v2/banks").then((x) => x.json());
    const data = (r && r.data) || [];
    if (data.length) {
      _mem = data;
      try { localStorage.setItem(KEY, JSON.stringify({ ts: Date.now(), data })); } catch (e) {}
      return _mem;
    }
  } catch (e) {}
  return banksSync();
}

// Tìm NH theo bin (ưu tiên), fallback theo code/shortName (để map dữ liệu cũ chưa có bin).
export function findBank(b) {
  if (!b) return null;
  const list = banksSync();
  const bin = b.bin != null ? String(b.bin) : "";
  if (bin) { const m = list.find((x) => String(x.bin) === bin); if (m) return m; }
  const key = String(b.bank || b.code || "").trim().toUpperCase();
  if (!key) return null;
  return list.find((x) => (x.code || "").toUpperCase() === key || (x.shortName || "").toUpperCase() === key) || null;
}

// URL ảnh QR VietQR (quét bằng app ngân hàng). template: qr_only | compact | compact2 | print.
export function vietqrImg({ bin, account, name, amount, info, template = "compact2" }) {
  if (!bin || !account) return "";
  const qs = [
    amount ? "amount=" + Math.round(amount) : "",
    info ? "addInfo=" + encodeURIComponent(info) : "",
    name ? "accountName=" + encodeURIComponent(name) : "",
  ].filter(Boolean).join("&");
  return `https://img.vietqr.io/image/${bin}-${encodeURIComponent(account)}-${template}.png` + (qs ? "?" + qs : "");
}
