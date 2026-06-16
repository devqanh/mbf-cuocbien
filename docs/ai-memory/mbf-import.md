---
name: mbf-import
description: Import lô hàng từ file kế toán thực tế dev/mbf.xlsx vào Trucking v2 (command trucking:import-mbf)
metadata: 
  node_type: memory
  type: project
  originSessionId: 796005c8-78dc-47ff-827f-1058cd1cdbe3
---

Luồng kế toán THẬT của khách ở **`dev/mbf.xlsx`** — mỗi sheet 1 tháng (JAN..JUN), header ở **DÒNG 8**, data từ dòng 9 (dòng 1-7 là ghi chú nháp). ~663 lô/tháng (JAN). Import bằng **`php artisan trucking:import-mbf {SHEET=JAN} --clear [--limit=N]`** (`app/Console/Commands/ImportMbf.php`, dùng PhpSpreadsheet đã có sẵn). `--clear` xóa TOÀN BỘ lô hàng (+chi phí/doanh thu/thanh toán/kho-theo-lô) trước khi import.

**Map cột Excel → field lô** (đọc kỹ, đã chốt với user):
- B NHÀ MÁY → `kho` (chuẩn hóa: bỏ tiền tố "CANON", QUẾ VÕ→**QV**, TIÊN SƠN→**TS**, THĂNG LONG→**TL** — khớp warehouse code có sẵn). Khách hàng = **Canon** (tất cả là nhà máy Canon).
- M POL → `from_loc` (Nơi lấy = **cảng**: DVU/VIP/HICT/TVU/NDV…). H NƠI HẠ → `to_loc` (ICD QUẾ VÕ/TRI PHƯƠNG/ĐÌNH VŨ…); giá trị bắt đầu bằng số hoặc chứa "cont/chờ/etd/xác nhận" coi là ghi chú, giữ raw không tạo location.
- C booking · D Nhập/Xuất (XUẤT/NHẬP/XUẤT RU) · E qty · F loại cont (40HC/40RF) · L inv · G cắt máng (serial datetime).
- O SỐ CONTAINER ĐẾN → `cont_no`. P SỐ CONTAINER RA (cont KHÁC) → ghi chú "Cont ra: …". K KHO = số CỔNG (1-4; 1,2=Cổng4 / 3,4=Cổng6) → ghi chú "Cổng: …". **Lưu ý: `cont_den`/`cont_ra` trong DB là cột NGÀY (cast date)**, KHÔNG phải số cont → map từ I (ngày)/S (ngày ra).
- I NGÀY + N giờ (fraction) → `gio_xe_den`; S NGÀY RA + Q giờ → `gio_xe_ra` (ghép serial+fraction qua `XlDate::excelToDateTimeObject`).
- R THÔNG TIN XE = "tên + biển + sđt" → tách biển số bằng regex `\d{2}[A-Z]{1,2}\d?-?\d{3}\.?\d{2,3}` → `bks_vao`=`bks_ra` (1 xe). Biển thật là **xe ngoài** (29H-…, 29C-…) nên `vehicle_id`=null (không matched) trừ khi thêm vào cai-dat#vehicles.

**Tự tạo danh mục THIẾU** (user yêu cầu để test): loại cont, location (cảng POL + nơi hạ) qua firstOrCreate; nhà máy map vào warehouse có sẵn. Mỗi lô gọi `recomputeShipmentDerived` → ánh xạ `from/to_location_id` + kho pivot (xem [[route-trips]], [[trucking-report-schema]]).

**Cần reconcile sau** (chưa làm — để user test): location trùng dạng "ICD QUẾ VÕ" (mới) vs "ICDQV" (cũ), "ĐÌNH VŨ" vs "DVU" — gộp trong cai-dat (reconcileLookup theo [[coded-catalog-edit]]). Kết quả JAN: 663 lô, kho QV:441/TS:154/TL:68, from_loc 570/to_loc 623 khớp location, +~20 location mới, contTypes 40HC/40RF.
