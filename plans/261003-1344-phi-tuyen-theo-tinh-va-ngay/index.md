# Phí tuyến theo Tỉnh của kho + phiên bản "áp dụng từ ngày"

Trạng thái: **đã triển khai** (2026-10-03, local; test E10–E14 pass, build OK, chưa deploy) · Trang: `/trucking-v2/cai-dat#warehouses`, `#routeFees`, `/trucking-v2/lo-trinh`

## Yêu cầu (user, 2026-10-03)

1. Phí tuyến thêm dạng **Cảng → Tỉnh → Cảng** (ngoài Cảng → Kho → … → Cảng).
2. Cài đặt → Kho: cạnh ô Ký hiệu có ô chọn **tỉnh** (select tìm được, có gợi ý tự điền) — mục tiêu để khớp phí tuyến.
3. Phí tuyến có **"áp dụng từ ngày"**: tạo phiên bản mới thì chuyến từ ngày đó dùng giá mới, chuyến cũ giữ giá cũ.

## Thiết kế

- **Tỉnh**: `App\Support\VnProvinces` (34 tỉnh/thành sau 01/07/2025 + tên cũ → mới, `guess()` đoán từ địa chỉ). Kho: cột `trucking_warehouses.province`, mảng `warehouseProvinceArr` theo chỉ số dòng (như địa chỉ/ghi chú), ô chọn đặt ở **header nhóm ký hiệu** (áp cả nhóm, vì lô/phí tuyến nhận kho theo ký hiệu) + nút gợi ý từ địa chỉ.
- **Khớp** (`HandlesShipments::legPayGroup`): khóa tập node theo kho như cũ → không có thì `routeNodeKeyByProvince()` thay mỗi kho bằng tỉnh của nó → tra tuyến "Cảng → Tỉnh → Cảng". Nhập Excel chấp nhận node là tên tỉnh. Lộ trình hiện nhãn "theo tỉnh: …".
- **Ngày áp dụng theo BẢNG** (user chốt 14:10: "không chọn ngày từng mục, áp toàn bộ phí từ ngày, tham khảo Bảng giá"): bảng `trucking_route_fee_books` (label, from_date null = mặc định) + `trucking_route_fees.book_id`; backfill mọi tuyến cũ vào bảng mặc định. `pickRouteFeeBook(ngày)` = bảng có from_date lớn nhất ≤ ngày, không có → bảng mặc định; Lộ trình/Phí xe chỉ tra tuyến trong bảng đó. UI tab Phí tuyến: chip chọn bảng như Bảng giá, "Tạo bảng phí mới từ ngày…" sao chép đủ tuyến của bảng đang chọn, sửa/xóa bảng; xuất/nhập Excel theo bảng (13 cột như cũ); lưu ghi lại từng bảng có trong payload. Ý tưởng ngày-từng-dòng ban đầu đã bỏ (migration rollback local, không deploy).

## Không làm

- Không đổi quy tắc tập node không thứ tự; không đụng chi đã chốt (frozen) ở Lộ trình.

## Nghiệm thu

E10 tạo bảng mới sao chép đủ tuyến · E11 chuyến 15/9 → bảng mặc định, kho có tỉnh khớp tuyến tỉnh · E12 chuyến 3/10 → bảng từ 1/10 giá mới · E13 kho có tuyến riêng ưu tiên tuyến kho · E14 đoán tỉnh từ địa chỉ cũ · E15 nhập Excel vào 1 bảng phân loại cập nhật/thêm/lỗi node · E16 lưu chỉ ghi lại bảng có trong payload.

## Rollback

`php artisan migrate:rollback --step=2` bỏ bảng phí tuyến + cột tỉnh (tuyến quay về không bảng); backup trước migrate ở `storage/app/backups/manual-20261003-135438-truoc-tinh-kho-phi-tuyen.sql.gz`.
