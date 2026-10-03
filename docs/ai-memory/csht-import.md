---
name: csht-import
description: "Import CSHT / chi phí lô hàng (nút /lo-hang) — 1 dòng = 1 lô, mỗi cột khoản = số tiền; khớp theo ID LÔ (nút \"Xuất chi phí lô\") hoặc số cont; ô trống không đổi, không bao giờ xóa"
metadata:
  node_type: memory
  type: project
  originSessionId: cc3184d1-0969-421d-a38d-aa4a564db3ad
  modified: 2026-10-03T05:05:56.198Z
---

Nút **Import CSHT** ở toolbar /lo-hang → modal "Import CSHT · chi phí lô hàng": Tải file mẫu · **Xuất chi phí lô (bộ lọc đang xem)** · Chọn file → Kiểm tra (dry-run) → Import (ALL-OR-NOTHING).

**Nâng cấp 2026-10-03** (user: import CSHT lỗi vì 1 số cont có ở nhiều lô — vd TEMU7457627 lô 542 nhập + 689 xuất; local có 6 cont trùng). User chọn bố cục **1 dòng / lô** (không phải 1 dòng / khoản):
- **Xuất chi phí lô** (`buildCostExportWb` excel.js): lấy lô theo đúng bộ lọc đang xem (`buildParams + &all=1`), cột `ID LÔ | SỐ CONT | NHẬP/XUẤT | KHÁCH HÀNG | BOOKING | NGÀY XE RA | <mọi khoản trong danh mục theo thứ tự> | SỐ HĐ | NGÀY HĐ | GHI CHÚ`, điền sẵn TỔNG số tiền từng khoản. Số HĐ/Ngày/Ghi chú lấy từ CSHT + Thanh lí chỉ khi 2 khoản GIỐNG nhau (khác thì để trống → import lại không ghi đè sai). Ẩn nút khi không có quyền cột chi phí (`col("cost")`, dữ liệu cost bị cắt ở server).
- **Đọc file** (`parseCshtRows`): phân loại tiêu đề (`cshtColKind`): id / cont / io / date ("ngày hđ", legacy "ngày") / inv / note / info (khách, booking, xe ra — bỏ qua) / skip (stt) / còn lại = cột tiền → `amounts{tiêu đề: digits}`; gửi kèm `line` = dòng Excel thật.
- **Backend** `HandlesCshtImport::planCshtImport` (dùng chung cho Kiểm tra và Import): tiêu đề → khoản danh mục qua `cshtNorm` (bỏ dấu, y→i: "Thanh lý"=="Thanh lí"; bỏ tiền tố phí/số tiền; "csht"/"thanhli" cho mẫu cũ `PHÍ CSHT`/`SỐ TIỀN THANH LÝ`); cột lạ có số → **warning** không chặn; 2 cột cùng khoản → lỗi. Khớp lô theo ID (cont trong file khác cont của lô → lỗi), không ID thì theo cont (trùng nhiều lô → lỗi kèm danh sách ID + gợi ý dùng nút Xuất). Dòng không có tiền lẫn HĐ/ngày/ghi chú = bỏ qua (file xuất có đủ mọi lô). Trùng lô trong file → lỗi.
- **Quy tắc ghi:** ô trống/0 = không đổi; số giống hiện tại = giữ nguyên (không ghi); chưa có khoản → tạo dòng (vat/màu/cost_item_id theo danh mục, billable=false); 1 dòng → sửa số tiền; ≥2 dòng cùng khoản → chỉ nhận khi bằng TỔNG, khác thì lỗi "sửa trong popup". **Không bao giờ xóa dòng.** Số HĐ / Ngày HĐ / Ghi chú chỉ áp CSHT + Thanh lí (có số tiền trong dòng; có HĐ mà không có CSHT/TL → lỗi). **Phí mở tờ khai** (src=thanhLyFee, lấy từ tờ khai) đổi số → lỗi. **Cước xe ngoài**: sửa được dòng src=extTruck có sẵn (ext_fee tự chốt qua recompute), tạo mới → lỗi "chọn Thuê xe ngoài trước". Kiểm tra trả `stats{create,update,same,shipments}`, `items`, `columns`, `warnings` → modal hiện cột khoản động + "X tạo mới · Y sửa · Z giữ nguyên", nút "Import N khoản" (N=0 → "Không có gì thay đổi").
- Hành vi đổi so với bản cũ: trước đây Số HĐ/Ngày/Ghi chú để trống thì GHI ĐÈ thành rỗng; nay trống = không đổi.

**Verify đã dùng:** dump `pagedShipments('icd',['all'=>1,'cust'=>[…]])` sau `auth()->login` (không login thì cost bị cắt) → build/parse bằng excel.js qua esbuild + stub `XLSX` (thư viện chỉ có qua CDN) → `validateCshtImport`/`importCshtImport` trong `DB::beginTransaction()`+rollBack. Round-trip nguyên = 0 thay đổi; import lại cùng file = 0 thay đổi.

Kèm theo từ trước: cột "Ghi chú" trong bảng chi phí lô hàng (CostLineRows shared.jsx). Route csht-import[/check] quyền shipments.update; controller ShipmentController@cshtCheck/@cshtImport (không đổi). Xem [[cost-item-auto-vat]] [[ext-truck-payable]] [[thanh-ly-to-khai]] [[data-safety-reconcile]].
