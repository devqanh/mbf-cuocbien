---
name: price-by-cont-type
description: "Bảng giá theo LOẠI CONT: prices JSON {loại cont => 1 giá TỔNG cước+dầu}, cột động, cột chung 20FT/40FT, mẫu Excel phẳng cho kế toán"
metadata:
  type: project
---

Từ 2026-09-11 (plan `plans/260911-1400-bang-gia-theo-loai-cont`), bảng giá KHÔNG còn tách cước/dầu và không còn cố định 40/20.

**Schema:** `trucking_price_rows.prices` JSON `{"20DC":4480000,"40HC":5130000}` (migration `2026_09_11_000002`). 4 cột cũ `trans_fee_*/fuel_fee_*` GIỮ trong DB (di sản, không ghi/đọc nữa) — dọn sau khi prod ổn. Backfill: `20FT` = cước20+dầu20, `40FT` = cước40+dầu40.

**Khóa loại cont** (`HandlesStatementPricing::contKeyNorm`, FE `price-excel.js:contKey`): in hoa, bỏ dấu, chỉ A-Z0-9, bỏ tiền tố CONT → "40'HC"="40 hc"="40HC", "cont45"="45". **Cột chung** = `\d{2}(FT)?` (20FT/40FT/45FT hoặc 20/40/45): áp mọi cont cùng cỡ chưa có cột riêng.

**Tra giá** `contPriceFor($prices, $contType)`: cột đúng loại → cột chung cùng cỡ → cont không phải cỡ 20 dùng cột chung 40 (giữ hành vi cũ "có chữ 20 → 20FT, còn lại → 40FT"; cả LCL/trống). Không có → `matched=false`, `routeMatched=true`, `diag.contType/contKeys` để UI nói "tuyến có giá nhưng thiếu cột loại cont X".

**Output priceShipment:** `cuoc` = TỔNG, `dau` = 0 (giữ khóa cho statementAmounts/lineAmounts/snapshot cũ), thêm `contType`, `contKey`, `bargeContKey`, `routeMatched`; bỏ `is20` (UI `contColLabel()` fallback is20 cho snapshot cũ). Sà lan cũng tra theo loại cont (bargeCuoc = tổng, bargeDau = 0).

**Chặn ghi:** ngoài `unmappedPriceValues` (địa điểm), thêm `unknownContKeys` — cột loại cont phải có trong danh mục Loại cont (trừ cột chung). KIND "Chưa phân nhóm" → lưu null.

**Import** `parseQuotation($path,$sheet)` → `{format: flat|legacy, rows, contCols, errors, warnings}`; tự nhận dạng: sheet có tiêu đề `FROM` + `ĐIỂM HẠ` → mẫu phẳng (cột sau KM = loại cont; ĐIỂM HẠ/TRẠNG THÁI/KIND trống = giống dòng trên; GHI CHÚ bị bỏ; dòng không giá → warning; TRẠNG THÁI sai/thiếu FROM → error chặn); không → parser báo giá gốc cũ quy về 20FT/40FT. `parseQuotationRows` = wrapper trả rows.

**FE:** `price-list.jsx` LUÔN hiện đủ mọi loại cont trong danh mục làm cột (user chốt 2026-09-11: "cứ hiện hết loại, không điền thì để null" → nhìn là biết chưa điền hay điền thiếu) + cột đang có trên dòng (20FT/40FT cũ) + cột chung thêm tay (Combo "+ Thêm cột chung…" chỉ GENERIC_KEYS); cột danh mục không có nút ×, muốn bỏ thì xóa loại cont ở Cài đặt. Bảng rộng → lưới có minWidth, khung cuộn ngang. "Xuất Excel" cũng đủ cột danh mục. `price-excel.js`: `buildPriceBookWb` (Xuất Excel) và `buildPriceTemplateWb` (Tải mẫu, 3 dòng ví dụ) CÙNG định dạng phẳng, 3 sheet: Bảng giá / Hướng dẫn / Loại cont. `priceBookConfig` trả thêm `contTypes`.

Test: `dev/test_features.php` mục A/B/D cập nhật + mục I (18 case: khóa chuẩn, fallback cột chung, backfill, chặn cột lạ, copy, parse mẫu, round-trip). Liên quan [[price-books-by-date]], [[statement-price-match]], [[json-schema-evolution]].
