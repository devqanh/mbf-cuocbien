---
name: statement-extra-cols
description: "Bảng kê: cột chi phí riêng theo TỪNG bảng kê (extra_cols JSON + detail.extras, không khai danh mục) — repeater trên trang xem, cờ VAT từng cột, Excel điền cột mẫu K/L/M trùng tên hoặc chèn cột trước Tổng tiền"
metadata:
  node_type: memory
  type: project
  originSessionId: 4bec1b4a-b245-4320-87b0-daa8ea0472d4
  modified: 2026-10-05T16:21:09.617Z
---

Từ 2026-10-05 (plan `plans/261005-2257-bang-ke-cot-chi-phi-rieng`), user yêu cầu: "mỗi bảng kê có thể thêm cột chi phí riêng, repeater nhiều chi phí khác để tiện theo dõi, độc lập về việc cấu hình vì nhiều bảng kê không đề cập tới chi phí đó".

**Dữ liệu:** `trucking_statements.extra_cols` JSON `[{key, label, vat}]` (key ổn định FE sinh `"x"+Date.now().toString(36)`; `vat` = khoản chịu VAT% của bảng kê) + cột dẫn xuất `extra_amount` (Σ phí riêng trong phạm vi, cho danh sách). Số từng lô ở `trucking_statement_lines.detail.extras = {key: int}` (tiến hóa JSON tương thích ngược, xem [[json-schema-evolution]]). `HandlesStatements::extraColsOf()` chuẩn hóa (bỏ cột không nhãn, key rỗng/trùng → tự sinh); `saveStatement` lọc `detail.extras` chỉ giữ key của cột còn tồn tại (≠0) rồi mới ghi.

**Công thức (1 nguồn chân lý):** `statementAmounts($details, $vatRate, $extraCols)` = FE `lineAmounts(line, vat, baseOv, extraCols)`/`statementAmounts(lines, vat, baseOvOf, extraCols)` (lib.jsx) → `{base, choho, extra, extras{key:Σ}, vat, total}`; VAT = round((nền + Σ cột có vat) × %); Tổng = nền + VAT + chi hộ + extra. Phạm vi Nhập/Xuất ([[statement-manual-price]]) vẫn áp. Drift (`statementsDrift`) không truyền cột → chỉ so nền + chi hộ như cũ. `applySuggestion` giữ `detail.extras`.

**UI trang xem (`StatementDetailBody`):** hàng "Cột chi phí riêng" (ke-noprint) dưới Phạm vi: chip = ô tên + ☑ VAT + ✕ (xóa cột có số đã điền → confirmAction), nút "Thêm cột" (mặc định vat=true, nhãn rỗng viền cam và KHÔNG được lưu). Bảng thêm 1 cột sau Chi hộ (ô số 110px, printonly số); hàng CỘNG, phần tổng kết, `AmountSummary` (prop `extras`) có dòng từng cột "(chịu VAT)/(không VAT)"; colSpan dùng `nCols = 8 + số cột`. Thanh chip + ô số là component chung `ExtraColsBar`/`ExtraCell` (user 05/10 chọn qua popup: có cả ở trang TẠO, mặc định cột mới chịu VAT). Trang tạo (`StatementForm`): state `extraCols` + `extraOv[shipmentId][key]`, payload `extraCols` + `detail.extras`; prop `onExtraColsCount` để `bang-ke-tao.jsx` nới khung (960 + 120×cột, tối đa 4); trang xem nới 940 + 110×cột. Bảng bọc `.ke-scroll` (overflow-x auto, `minWidth` theo số cột; in thì CSS bỏ cuộn + min-width) để cột Tuyến không bị bóp. Danh sách `KePage`: chip "+ Phí riêng X" khi `extraAmount > 0`.

**Excel (`StatementExcelExporter`):** mẫu có sẵn K "Lưu ca", L "Tờ khai không phơi", M "Phí tái sử dụng container rỗng" → cột riêng trùng nhãn (chuẩn hóa lowercase + gộp khoảng trắng) điền thẳng vào cột mẫu; cột khác `insertNewColumnBefore('O', n)` TRƯỚC khi giãn/thu dòng, nhãn ở dòng 21, kiểu/độ rộng kế thừa cột N (duplicateStyle dòng 20→Tổng), Tổng tiền/Ghi chú dời sang `Coordinate::stringFromColumnIndex(15/16 + n)`; ô gộp tiêu đề và dòng ngày (J26:P26) tự giãn theo. Tổng tiền dòng Excel = cước + thanh lý + Σ phí riêng (Excel vốn "chưa VAT").

Test F8–F11 `dev/test_features.php` (F11 xuất Excel thật rồi đọc lại ô). Liên quan [[price-by-cont-type]], [[ext-truck-payable]].
