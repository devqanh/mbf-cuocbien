# Tài liệu cột & công thức — Bảng TRUCKING

> Tài liệu này sinh tự động từ cấu hình hệ thống. Hệ thống gồm **2 sheet**: HẠ HPH và HẠ ICD.
> Các ô **TỔNG / VAT / CÒN NỢ / PHẢI THU** tự tính theo công thức (cập nhật ngay khi nhập). Cột tô màu xám là **ô tính tự động**, không nhập tay.

---

## SHEET: HẠ HPH (Hải Phòng)

Tổng số cột: **76**

### Nhóm 1 — Thông tin lô hàng

| Cột | Kiểu | Công thức / Ghi chú |
|---|---|---|
| STT | Chữ | _Tự động (số thứ tự dòng)_ |
| KHÁCH HÀNG | Chữ | Nhập tay |
| SỐ BOOKING | Chữ | Nhập tay |
| NHẬP/XUẤT | Chữ | Nhập tay |
| SỐ LƯỢNG CONT | Số | Nhập tay |
| LOẠI CONT | Chữ | Nhập tay |
| SỐ CONTAINER | Chữ | Nhập tay |
| SỐ TỜ KHAI | Chữ | Nhập tay |
| CẮT MÁNG | Chữ | Nhập tay |
| NGÀY TÀU CHẠY | Ngày | Nhập tay |
| NGÀY ĐÓNG/TRẢ HÀNG | Ngày | Nhập tay |
| GIỜ XE LÊN NHÀ MÁY | Chữ | Nhập tay |
| GIỜ XE RỜI NHÀ MÁY | Chữ | Nhập tay |
| DISCONNECT/CONNECT | Chữ | Nhập tay |
| ĐỊA CHỈ ĐÓNG HÀNG | Chữ | Nhập tay |
| THÔNG TIN XUẤT HÓA ĐƠN | Chữ | Nhập tay |
| NƠI LẤY CONT | Chữ | Nhập tay |
| NƠI HẠ CONT | Chữ | Nhập tay |
| THÔNG TIN NHÀ XE | Chữ | Nhập tay |
| BKS KÉO CONT LÊN NHÀ MÁY | Chữ | Nhập tay |
| BKS KÉO CONT RỜI NHÀ MÁY | Chữ | Nhập tay |
| SỐ HIỆU SÀ LAN | Chữ | Nhập tay |
| GHI CHÚ ĐỘI TRUCKING | Chữ | Nhập tay |
| CANON INV | Chữ | Nhập tay |

### Nhóm 2 — Chi phí

| Cột | Kiểu | Công thức / Ghi chú |
|---|---|---|
| CƯỚC XE NGOÀI | Tiền (VNĐ) | Nhập tay |
| NÂNG — SỐ TIỀN | Tiền (VNĐ) | Nhập tay |
| NÂNG — BÊN TT | Chữ | Nhập tay |
| HẠ — SỐ TIỀN | Tiền (VNĐ) | Nhập tay |
| HẠ — BÊN TT | Chữ | Nhập tay |
| CHI PHÍ KHÁC — SỐ TIỀN | Tiền (VNĐ) | Nhập tay |
| CHI PHÍ KHÁC — BÊN TT | Chữ | Nhập tay |
| CSHT — SỐ TIỀN | Tiền (VNĐ) | Nhập tay |
| TỔNG THU CHI HỘ | Tiền (VNĐ) | **= NÂNG — SỐ TIỀN + HẠ — SỐ TIỀN + CHI PHÍ KHÁC — SỐ TIỀN + CSHT — SỐ TIỀN** |
| CHI PHÍ MBF, VK | Tiền (VNĐ) | Nhập tay |
| HẢI QUAN — TỜ KHAI KHOÁN | Tiền (VNĐ) | Nhập tay |
| HẢI QUAN — KHÁC | Tiền (VNĐ) | Nhập tay |
| HẢI QUAN — BÊN TT | Chữ | Nhập tay |
| CHỌN VỎ — SỐ TIỀN | Tiền (VNĐ) | Nhập tay |
| CHỌN VỎ — BÊN TT | Chữ | Nhập tay |
| TỔNG CHI PHÍ | Tiền (VNĐ) | **= CƯỚC XE NGOÀI + NÂNG — SỐ TIỀN + HẠ — SỐ TIỀN + CHI PHÍ KHÁC — SỐ TIỀN + CSHT — SỐ TIỀN + HẢI QUAN — TỜ KHAI KHOÁN + HẢI QUAN — KHÁC + CHỌN VỎ — SỐ TIỀN** |

### Nhóm 3 — Chi phí xe ngoài / Thanh toán

| Cột | Kiểu | Công thức / Ghi chú |
|---|---|---|
| CƯỚC | Tiền (VNĐ) | **= CƯỚC XE NGOÀI** |
| VAT 8% | Tiền (VNĐ) | **= CƯỚC × 8%** |
| TỔNG THU CHI HỘ (XE NGOÀI) | Tiền (VNĐ) | **= NÂNG — SỐ TIỀN + HẠ — SỐ TIỀN + CHI PHÍ KHÁC — SỐ TIỀN** |
| SỐ ĐÃ THANH TOÁN | Tiền (VNĐ) | Nhập tay |
| NGÀY TT | Ngày | Nhập tay |
| CÒN NỢ (XE NGOÀI) | Tiền (VNĐ) | **= CƯỚC + VAT 8% + TỔNG THU CHI HỘ (XE NGOÀI) − SỐ ĐÃ THANH TOÁN** |

### Nhóm 4 — Chi phí xe MBF chạy

| Cột | Kiểu | Công thức / Ghi chú |
|---|---|---|
| ĐIỂM ĐÓNG HÀNG | Chữ | Nhập tay |
| TỈNH | Chữ | Nhập tay |
| LÁI XE CHI | Tiền (VNĐ) | Nhập tay |
| VÉ TRẠM | Tiền (VNĐ) | Nhập tay |
| TIỀN ĐƯỜNG | Tiền (VNĐ) | Nhập tay |
| TRỢ CẤP | Tiền (VNĐ) | Nhập tay |
| CHI PHÍ KHÁC (XE) | Tiền (VNĐ) | Nhập tay |
| VÉ VETC | Tiền (VNĐ) | Nhập tay |
| LƯƠNG | Tiền (VNĐ) | Nhập tay |
| QUÃNG ĐƯỜNG (KM) | Số | Nhập tay |
| LÍT | Số | Nhập tay |
| ĐƠN GIÁ DẦU | Tiền (VNĐ) | Nhập tay |
| TỔNG CHI PHÍ XE MBF CHẠY | Tiền (VNĐ) | **= VÉ TRẠM + TIỀN ĐƯỜNG + TRỢ CẤP + CHI PHÍ KHÁC + VÉ VETC + LƯƠNG + LÍT × ĐƠN GIÁ** |

### Nhóm 5 — Doanh thu

| Cột | Kiểu | Công thức / Ghi chú |
|---|---|---|
| GHI CHÚ KẾ TOÁN | Chữ | Nhập tay |
| DOANH THU CƯỚC XE | Tiền (VNĐ) | Nhập tay |
| PHÍ VẬN TẢI KHÁC | Tiền (VNĐ) | Nhập tay |
| PHÍ THANH LÝ TỜ KHAI | Tiền (VNĐ) | Nhập tay |
| LƯU CA | Tiền (VNĐ) | Nhập tay |
| TỜ KHAI KHÔNG PHƠI | Tiền (VNĐ) | Nhập tay |
| PHÍ TÁI SỬ DỤNG CONT RỖNG | Tiền (VNĐ) | Nhập tay |
| TỔNG DOANH THU | Tiền (VNĐ) | **= DOANH THU CƯỚC XE + PHÍ VẬN TẢI KHÁC + THANH LÝ TỜ KHAI + LƯU CA + TỜ KHAI KHÔNG PHƠI + PHÍ TÁI SỬ DỤNG CONT RỖNG** |
| VAT | Tiền (VNĐ) | **= TỔNG DOANH THU × 8%** |
| SỐ TIỀN CHI HỘ NÂNG | Tiền (VNĐ) | Nhập tay |
| SỐ TIỀN CHI HỘ HẠ | Tiền (VNĐ) | Nhập tay |
| SỐ TIỀN CSHT | Tiền (VNĐ) | Nhập tay |
| TỔNG PHẢI THU | Tiền (VNĐ) | **= TỔNG DOANH THU + VAT + SỐ TIỀN CHI HỘ NÂNG + SỐ TIỀN CHI HỘ HẠ + SỐ TIỀN CSHT** |
| KHÁCH HÀNG ĐÃ THANH TOÁN | Tiền (VNĐ) | Nhập tay |
| CÒN NỢ | Tiền (VNĐ) | **= PHẢI THU − KHÁCH HÀNG ĐÃ THANH TOÁN** |
| HẠN KHÁCH HÀNG THANH TOÁN | Ngày | Nhập tay |
| NGÀY KHÁCH HÀNG THANH TOÁN | Ngày | Nhập tay |

---

## SHEET: HẠ ICD (Quế Võ)

Tổng số cột: **41**

### Nhóm 1 — Thông tin lô hàng

| Cột | Kiểu | Công thức / Ghi chú |
|---|---|---|
| STT | Chữ | _Tự động (số thứ tự dòng)_ |
| KHÁCH HÀNG | Chữ | Nhập tay |
| SỐ BOOKING/BILL | Chữ | Nhập tay |
| NHẬP/XUẤT | Chữ | Nhập tay |
| VOL | Số | Nhập tay |
| LOẠI CONT | Chữ | Nhập tay |
| CẮT MÁNG | Chữ | Nhập tay |
| NƠI LẤY | Chữ | Nhập tay |
| NƠI HẠ | Chữ | Nhập tay |
| NGÀY CONT ĐẾN | Ngày | Nhập tay |
| GIỜ ĐẾN DỰ KIẾN | Chữ | Nhập tay |
| NGÀY CONT RA | Ngày | Nhập tay |
| GIỜ XE ĐẾN | Chữ | Nhập tay |
| KHO | Chữ | Nhập tay |
| CANON INV | Chữ | Nhập tay |
| SỐ CONTAINER ĐẾN | Chữ | Nhập tay |
| TÊN LÁI XE + SĐT | Chữ | Nhập tay |
| BKS KÉO CONT ĐẾN | Chữ | Nhập tay |
| SỐ CONTAINER RA | Chữ | Nhập tay |
| GIỜ XE RA | Chữ | Nhập tay |
| NGÀY RA | Ngày | Nhập tay |
| BKS KÉO CONT RA | Chữ | Nhập tay |
| GHI CHÚ TRUCKING | Chữ | Nhập tay |
| DISCONNECT/CONNECT | Chữ | Nhập tay |
| FREE TIME | Chữ | Nhập tay |

### Nhóm 4 — Chi phí xe MBF chạy

| Cột | Kiểu | Công thức / Ghi chú |
|---|---|---|
| PHỤ CẤP TIỀN ĐƯỜNG | Tiền (VNĐ) | Nhập tay |
| TRỢ CẤP | Tiền (VNĐ) | Nhập tay |
| LƯƠNG | Tiền (VNĐ) | Nhập tay |
| CHI PHÍ KHÁC | Tiền (VNĐ) | Nhập tay |
| QUÃNG ĐƯỜNG (KM) | Số | Nhập tay |
| LÍT | Số | Nhập tay |
| ĐƠN GIÁ | Tiền (VNĐ) | Nhập tay |
| TỔNG CHI PHÍ | Tiền (VNĐ) | **= PHỤ CẤP TIỀN ĐƯỜNG + TRỢ CẤP + LƯƠNG + CHI PHÍ KHÁC + LÍT × ĐƠN GIÁ** |

### Nhóm 5 — Doanh thu

| Cột | Kiểu | Công thức / Ghi chú |
|---|---|---|
| DOANH THU TIÊN SƠN | Tiền (VNĐ) | Nhập tay |
| DOANH THU QUẾ VÕ | Tiền (VNĐ) | Nhập tay |
| DOANH THU THĂNG LONG | Tiền (VNĐ) | Nhập tay |
| THANH LÝ TỜ KHAI | Tiền (VNĐ) | Nhập tay |
| TỔNG DOANH THU | Tiền (VNĐ) | **= DOANH THU TIÊN SƠN + DOANH THU QUẾ VÕ + DOANH THU THĂNG LONG + THANH LÝ TỜ KHAI** |
| VAT | Tiền (VNĐ) | **= TỔNG DOANH THU × 0%** |
| CHI HỘ | Tiền (VNĐ) | Nhập tay |
| PHẢI THU | Tiền (VNĐ) | **= TỔNG DOANH THU + VAT + CHI HỘ** |

---

## Ghi chú nghiệp vụ

- **BÊN TT** = bên thanh toán (ghi chữ: tài xế / A.Hoàn / xe ngoài / lập từ TK công ty…), **không** cộng vào tổng.
- **SỐ TIỀN** = các ô nhập số tiền, được cộng vào các ô tổng tương ứng.
- Tiền nhập kiểu Việt Nam đều được: gõ `3000000`, `3.000.000` hay `3,000,000` đều ra `3,000,000 VNĐ`.
- Ngày nhập dạng `20/10/2026` (dd/mm/yyyy).
- Click vào ô tổng để xem công thức hiển thị ngay dưới tiêu đề bảng.
