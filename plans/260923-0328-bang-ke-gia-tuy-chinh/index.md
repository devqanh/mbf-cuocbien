# Bảng kê: giữ giá tùy chỉnh khi "Tính lại" + sửa hiển thị danh sách

Trạng thái: **đã triển khai** (2026-09-23, local; test F1–F5 pass, build OK, chưa deploy) · Trang: `/trucking-v2/bang-ke`, `/trucking-v2/bang-ke/{id}`

## Vấn đề (user, 2026-09-23)

- Đã nhập giá tùy chỉnh cho dòng bảng kê, bấm **Tính lại** là mất: code hiện thay toàn bộ dòng bằng giá hệ thống tính.
- Danh sách bảng kê hiển thị xấu: cột Tổng tiền bị cắt (nhãn "Cần tính lại" nằm trong ô Khách hàng làm lưới tràn), tên khách xuống dòng.
- Đối soát "Cần tính lại" so `pr.phaiThu` (gồm chi hộ) với `phai_thu` (chỉ nền) → cảnh báo sai cho dòng có chi hộ.

## Mục tiêu

1. Giá tùy chỉnh là số phải thu chính thức, không bao giờ bị "Tính lại" ghi đè.
2. "Tính lại" chỉ **gợi ý**: hiện giá hệ thống tính bên cạnh, bấm **Áp dụng** (từng dòng hoặc tất cả dòng không tùy chỉnh) mới điền, rồi Lưu.
3. Danh sách bảng kê không bị cắt cột; nhãn cảnh báo không phá bố cục.

## Không làm

- Không đổi quy tắc định giá (priceShipment) hay Excel bảng kê.
- Không sửa dữ liệu bảng kê cũ (19 dòng `phai_thu` gồm chi hộ vẫn tính nền từ `detail` như hiện tại).

## Thiết kế

- **Dữ liệu**: `detail.manualBase` (int, tùy chọn) = nền do người dùng đặt tay. Có khóa này → nền dòng = `manualBase`; `detail.cuoc/dau/barge*` giữ nguyên snapshot hệ thống tính để đối chiếu. Không còn ghi đè `detail.cuoc = base, dau = 0`.
- **Nguồn chân lý** (`lib.jsx lineAmounts` + `HandlesStatements::statementAmounts`): thứ tự `baseOv` (form tạo) → `manualBase` → tổng cuoc/dau/barge → `phaiThu`.
- **Tính lại** (`bang-ke-xem.jsx`): kết quả reprice lưu vào `suggestById`, KHÔNG sửa `st.lines`. Mỗi dòng lệch hiện chip "Hệ thống tính: X · Áp dụng". Thanh tổng kết: N dòng có giá mới, M dòng tùy chỉnh được giữ, nút "Áp dụng tất cả (không đụng giá tùy chỉnh)". Áp dụng = thay `detail` bằng snapshot mới (giữ `vat`), `phaiThu = cuoc = nền mới`, bỏ `manualBase`.
- **Dòng tùy chỉnh**: badge "Giá tùy chỉnh" + "hệ thống tính X" + nút "↺ về giá hệ thống".
- **Đối soát danh sách** (`statementsDrift`): so nền + chi hộ hệ thống tính với nền + chi hộ đã lưu (qua `statementAmounts`), bỏ qua nền của dòng tùy chỉnh. Nhãn: "Giá mới: N lô".
- **Danh sách** (`KePage`): cột khách `minmax(0,1fr)`, chip xuống dòng riêng, số tiền `white-space: nowrap`, khung rộng 1100.

## Nghiệm thu

1. Nhập giá tay ở trang xem → Lưu → Tính lại → giá tay còn nguyên, có gợi ý giá hệ thống; bấm Áp dụng mới đổi.
2. Tạo bảng kê có sửa tay → lưu → `statementToArray` nền = số sửa tay; `detail.cuoc` vẫn là số hệ thống.
3. Dòng có chi hộ, giá không đổi → không còn nhãn cảnh báo ở danh sách.
4. `dev/test_features.php` mục F thêm F4/F5 pass.
