# Bảng kê: cột chi phí riêng theo từng bảng kê (repeater)

Trạng thái: **đã triển khai** (2026-10-05, local; test F8–F11 pass, đã xem thử trên trang xem bảng kê, chưa commit/deploy) · Trang: `/trucking-v2/bang-ke/{id}` (trang xem), danh sách `/trucking-v2/bang-ke`, Excel xuất.

## Yêu cầu (user, 2026-10-05)

Mỗi bảng kê có thể **thêm cột chi phí riêng** (nhiều cột, dạng repeater) để theo dõi các khoản
thu thêm theo từng lô (lưu ca, tờ khai không phơi, phí tái sử dụng cont rỗng…). Cột **độc lập
với cấu hình** (không khai trong Cài đặt → Khoản chi phí) vì nhiều bảng kê không có khoản đó.

## Thiết kế

- **Dữ liệu:** `trucking_statements.extra_cols` JSON `[{key, label, vat}]` (key ổn định do FE
  sinh, `vat` = khoản này chịu VAT% của bảng kê hay không) + cột dẫn xuất `extra_amount`
  (Σ phí riêng trong phạm vi, cho trang danh sách). Số tiền từng lô ghi trong
  `trucking_statement_lines.detail.extras = {key: số}` (tiến hóa JSON tương thích ngược — dòng
  cũ không có → 0; cột đã xóa thì key bị bỏ qua).
- **Công thức (1 nguồn chân lý, FE `lineAmounts`/`statementAmounts` = BE `statementAmounts`):**
  nền giữ nguyên; `extra` = Σ cột riêng; VAT = round((nền + Σ cột riêng có `vat`) × %);
  Tổng = nền + VAT + chi hộ + extra. Phạm vi Nhập/Xuất vẫn áp (lô ngoài phạm vi không tính).
- **Trang xem:** thanh "Cột chi phí riêng" (ke-noprint) — thêm/sửa nhãn/tích VAT/xóa; bảng
  thêm 1 cột số nhập tay cho mỗi cột riêng (sau Chi hộ, trước Tổng tiền), hàng CỘNG + phần
  tổng kết + AmountSummary có dòng từng cột riêng; bản in hiện số. "Tính lại"/Áp dụng giữ
  nguyên `detail.extras`. Trang tạo cũng có thanh cột riêng (user chọn qua popup 05/10) — state
  `extraCols` + `extraOv`, lưu `extraCols` + `detail.extras` trong payload; khung nới theo số cột.
  Mặc định cột mới chịu VAT (user chọn giữ).
- **Excel:** mẫu chính thức có sẵn cột K "Lưu ca", L "Tờ khai không phơi", M "Phí tái sử dụng
  container rỗng" → cột riêng trùng tên (so khớp chuẩn hóa) điền thẳng vào cột đó; cột khác
  chèn thêm trước "Tổng tiền" (nhãn ở dòng 21, kiểu kế thừa cột N); Tổng tiền từng dòng và
  dòng Tổng cộng thêm các cột này.
- **Danh sách:** chip "Phí riêng X" dưới tên khách khi > 0 (Tổng tiền đã gồm).

## Không làm

- Không thêm vào danh mục Khoản chi phí / không đụng chi phí lô hàng (cost_lines).
- Không đổi công thức drift (chỉ so nền + chi hộ).

## Nghiệm thu

Test F8–F10 `dev/test_features.php`: lưu + đọc lại extraCols/extras, VAT theo cờ cột, cột đã
xóa không tính, phạm vi Nhập/Xuất áp cho phí riêng; xuất Excel có nhãn cột chèn đúng vị trí và
Tổng tiền gồm phí riêng.

## Rollback

`php artisan migrate:rollback --step=1` bỏ 2 cột; backup DB trước migrate ở `storage/app/backups/`.
