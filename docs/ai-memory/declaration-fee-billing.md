---
name: declaration-fee-billing
description: Phí mở tờ khai + phí thanh lý = THU KHÁCH qua Bảng kê (cột Phí tờ khai, không VAT), KHÔNG còn là dòng chi phí lô src=thanhLyFee
metadata:
  type: project
---

**Phí tờ khai thu khách** (2026-10-14, user chọn): phí mở tờ khai không còn "link sang Chi phí" của lô mà lên **Bảng kê**.

- Lô: `declarations` [{no, fee}] (phí mở từng tờ khai) + cột mới **`thanh_ly_fee`** (phí thanh lý, 1 số/lô, field `thanhLyFee`). Migration `2026_10_14_000004` đã XÓA mọi dòng chi phí `src=thanhLyFee` (sau backup) + recompute; `syncDeclarationFee`/`DECL_FEE_ITEM` đã gỡ.
- Bảng kê: `candidateRow` gắn `pr.declFee / declCount / tlFee` → nằm trong `detail` snapshot. **Phí tờ khai = declFee + tlFee, KHÔNG VAT**, cộng vào Tổng tiền (`lineAmounts`/`statementAmounts` trả `tk`; PHP `lineDeclFee`). Cột "Phí tờ khai" sau Chi hộ (TkCell tách Mở TK ×n / Thanh lý). Lưu: `extra_amount` GỘP thêm tk (để danh sách suy VAT = total − nền − chi hộ − extra đúng), `line.thanh_ly` = tk (Excel cột N).
- Excel "Cập nhật tờ khai": **mỗi lô 1 dòng** — SỐ TỜ KHAI nhiều số trong 1 ô (Alt+Enter / ; ,), ĐƠN GIÁ MỞ TỜ KHAI × mỗi tờ khai (trống = giữ phí cũ theo số), PHÍ THANH LÝ (trống giữ, `--`/0 xóa).

**Why:** phí này kế toán thu lại của khách, không phải chi phí vận hành của lô.
**How to apply:** mọi chỗ tính tổng bảng kê phải cộng `tk`; đừng tạo lại dòng chi phí cho phí tờ khai. Khác field ngày [[thanh-ly-to-khai]]. Liên quan [[statement-manual-price]], [[csht-import]].
