---
name: csht-import
description: "Import CSHT / chi phí lô hàng (nút /lo-hang) — file theo MẪU KẾ TOÁN 2 dòng tiêu đề (nhóm Nâng/Hạ/CSHT/Thanh lí + CHI HỘ + NHÃN), 1 dòng = 1 lô; khớp theo ID LÔ (nút \"Xuất chi phí lô\") hoặc số cont; ô trống không đổi, không bao giờ xóa"
metadata:
  node_type: memory
  type: project
  originSessionId: cc3184d1-0969-421d-a38d-aa4a564db3ad
  modified: 2026-10-06T05:16:38.585Z
---

Nút **Import CSHT** ở toolbar /lo-hang → modal "Import CSHT · chi phí lô hàng": Tải file mẫu · **Xuất chi phí lô (bộ lọc đang xem)** · Chọn file → Kiểm tra (dry-run) → Import (ALL-OR-NOTHING).

**Lịch sử:** 2026-10-03 thêm ID LÔ (import CSHT lỗi vì 1 số cont có ở nhiều lô — vd TEMU7457627 lô 542 nhập + 689 xuất); user chọn **1 dòng / lô**. 2026-10-06 user gửi **file mẫu kế toán** (sửa từ file xuất, `Downloads/chi-phi-lo-2026-10-06 (1).xlsx`) → làm theo đúng bố cục đó cho cả xuất lẫn import.

**Bố cục (hằng `COST_INFO` / `COST_GROUPS` / `COST_CHIHO_ITEM` trong excel.js, khớp mẫu 100%):** 2 dòng tiêu đề, dòng 1 = tên KHOẢN ở đầu nhóm (gộp ô I1:M1, N1:R1, S1:T1, U1:X1), dòng 2 = tên ô; autofilter A2:Z…
`ID LÔ | SỐ CONT | NHẬP/XUẤT | KHÁCH HÀNG | BOOKING | NGÀY XE RA | Nhà xe ngoài | Tuyến` · **Nâng** [Số tiền, Vat, Số hóa đơn, Người chi, Ngày HĐ] · **Hạ** [như Nâng] · **CSHT** [Số tiền, Số HĐ] · **Thanh lí** [Số tiền, Số HĐ, Ngày HĐ, Ghi chú thanh lí] · `CHI HỘ` · `NHÃN`.
User chốt: **CHI HỘ** = số tiền khoản "Chi hộ LCC" (import được); **Nhà xe ngoài + Tuyến** chỉ để xem (Tuyến = nơi lấy → kho → nơi hạ); **NHÃN** ô có giá trị = THAY đúng danh sách nhãn lô (phẩy/chấm phẩy), ô trống = không đổi. Các khoản khác (Phí hạ tầng công nghệ, Phí mở tờ khai, Cước xe ngoài) KHÔNG có trong file xuất theo mẫu (thêm nhóm tay vẫn import được). Excel KHÔNG tô màu vàng như mẫu (SheetJS community không ghi style).

- **Xuất** (`buildCostExportWb(list)`): lô theo bộ lọc đang xem (`buildParams + &all=1`); 1 dòng chi phí → đúng giá trị; nhiều dòng cùng khoản → tiền = TỔNG, ô khác chỉ điền khi mọi dòng giống nhau. File mẫu (`buildCshtTemplateWb`) = cùng hàm với 2 lô ví dụ không ID. Ẩn nút khi không có quyền cột chi phí.
- **Đọc file** (`parseCshtRows`): dòng tiêu đề = dòng có "cont"/"id lô" (KHÔNG dò "csht" trước vì dòng tên khoản phía trên có chữ CSHT — lỗi đã gặp); dòng ngay trên = dòng nhóm. Cột thuộc nhóm khi dòng 2 là ô nhóm (`costGroupField`: số tiền/vat/số HĐ/người chi/ngày/ghi chú) — nhóm kết thúc ở cột đầu tiên không phải ô nhóm (CHI HỘ, NHÃN). VAT ô % của Excel (0.08) → 8. Gửi `{line, id, contNo, io, tags, groups{khoản:{amount,vat,invoiceNo,payer,date,dateRaw,note}}, amounts{tiêu đề cột lẻ}, date/dateRaw/invoiceNo/note (file 1 dòng tiêu đề cũ)}`. Vẫn đọc file 1 dòng tiêu đề cũ + mẫu CSHT cũ (`PHÍ CSHT`/`SỐ TIỀN THANH LÝ`).
- **Backend** `HandlesCshtImport::planCshtImport` (dùng chung Kiểm tra + Import): nhãn nhóm / tiêu đề cột lẻ → khoản qua `cshtItemForHeader` (bỏ dấu, y→i; bỏ tiền tố phí/số tiền; "csht"/"thanhli"; cuối cùng TIỀN TỐ DUY NHẤT ≥4 ký tự → "CHI HỘ" = "Chi hộ LCC"); khoản xuất hiện 2 lần → lỗi; cột lạ có số → warning. Khớp lô theo ID (cont lệch → lỗi) hoặc cont (trùng nhiều lô → lỗi kèm ID). `cshtDiff` so từng ô với dòng hiện có.
- **Quy tắc ghi:** ô trống/0 = không đổi; giống hiện tại = giữ nguyên; chưa có khoản → tạo (CẦN Số tiền; vat theo ô hoặc mặc định danh mục); 1 dòng → sửa các ô có giá trị (tiền/vat/số HĐ/người chi/ngày/ghi chú); ≥2 dòng cùng khoản → chỉ nhận khi không đổi gì. **Không bao giờ xóa dòng.** Số HĐ/Ngày/Ghi chú DÙNG CHUNG (file cũ) chỉ áp CSHT + Thanh lí. Phí mở tờ khai đổi → lỗi; Cước xe ngoài chỉ sửa dòng extTruck có sẵn. Người chi không có trong danh mục → warning (vẫn ghi, payer_id null). Nhãn: op `tags` (save trực tiếp). Kiểm tra trả `stats{create,update,same,tags,shipments}`; modal hiện ô mỗi khoản (tiền + VAT/HĐ/ngày/người chi dòng phụ), cột Nhãn, nút "Import N thay đổi".

**Verify đã dùng:** file mẫu user → AOA bằng openpyxl (python) → `parseCshtRows` qua esbuild + stub `XLSX` (thư viện chỉ có qua CDN) → `validateCshtImport`/`importCshtImport` trong `DB::beginTransaction()`+rollBack; so 2 dòng tiêu đề file xuất với mẫu user. Round-trip nguyên = 0 thay đổi; import lại cùng file = 0 thay đổi. Lưu ý: Bash tool lỗi khi lệnh có dấu nháy đơn → chạy node/php qua PowerShell.

Kèm theo từ trước: cột "Ghi chú" trong bảng chi phí lô hàng (CostLineRows shared.jsx). Route csht-import[/check] quyền shipments.update; controller ShipmentController@cshtCheck/@cshtImport (không đổi). Xem [[cost-item-auto-vat]] [[ext-truck-payable]] [[thanh-ly-to-khai]] [[data-safety-reconcile]].
