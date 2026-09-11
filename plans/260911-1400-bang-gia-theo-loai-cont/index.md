# Bảng giá theo LOẠI CONT (1 giá tổng cước+dầu)

Trạng thái: **đã triển khai** (2026-09-11, local; test `dev/test_features.php` mục A/B/D/I pass, chưa deploy) · Trang: `/trucking-v2/bang-gia`, `/trucking-v2/lo-hang`, bảng kê

## Mục tiêu

- Bảng giá phân loại theo **loại cont cụ thể** (20DC, 40HC, 40RHC, 20RHC, 45HC…) thay vì chỉ 20FT/40FT.
- Mỗi ô giá = **1 số tổng (cước + dầu)**; bỏ theo dõi cước/dầu tách riêng.
- Kế toán điền **mẫu Excel phẳng** (1 dòng = 1 tuyến, mỗi loại cont 1 cột) rồi nhập lên bảng giá.

## Ràng buộc / Không làm

- Không đổi quy tắc khớp tuyến (ĐI → NHÀ MÁY → HẠ + KIND + Connect/Disconnect/Non).
- Không đổi bảng kê, Excel bảng kê, P&L: các nơi này vẫn nhận `cuoc`(= tổng) + `dau`(= 0) + sà lan.
- Không xóa 4 cột cũ trong DB ở đợt này (chỉ thêm `prices`, backfill); dọn sau khi prod chạy ổn.
- Bảng kê đã lưu giữ nguyên snapshot (cuoc/dau cũ) — chỉ đổi khi bấm "Tính lại".

## Quyết định (user, 2026-09-11)

| Vấn đề | Chốt |
|---|---|
| Phân loại giá | Theo loại cont, cột động, khóa = tên loại cont trong danh mục |
| Cước / dầu | Gộp 1 số tổng |
| Dữ liệu cũ | Chuyển thành cột chung `20FT`/`40FT` (= cước+dầu), áp cho mọi cont cùng cỡ |
| Nhập giá | Mẫu Excel phẳng do hệ thống xuất; vẫn đọc được file báo giá gốc cũ (quy về 20FT/40FT) |
| Cột hiển thị | Luôn hiện đủ mọi loại cont trong danh mục; ô chưa điền để trống (null) để phân biệt chưa điền / điền thiếu |

## Thiết kế

- **Schema**: `trucking_price_rows.prices` JSON `{ "40HC": 5130000, "20DC": 4480000 }`. Khóa chuẩn hóa: in hoa, bỏ dấu/khoảng trắng/dấu nháy, bỏ tiền tố CONT. Khóa chung = `20FT`/`40FT`/`45FT` (hoặc chỉ số).
- **Khớp giá** (`HandlesStatementPricing::contPriceFor`): khớp tuyến như cũ → tra `prices[loại cont]` → không có thì cột chung cùng cỡ → cont không phải 20 thì `40FT` (giữ hành vi cũ) → không có nữa thì `matched=false` + `diag.contType/contKeys`.
- **Output priceShipment**: `cuoc` = tổng, `dau` = 0, thêm `contType`, `contKey`, `routeMatched`. `statementAmounts`/`lineAmounts` không đổi.
- **Import** (`parseQuotationRows`): tự nhận dạng: header có `FROM` + `ĐIỂM HẠ` → mẫu phẳng (cột sau KM = loại cont); ngược lại → parser báo giá gốc cũ (quy về 20FT/40FT). Chặn khi: ký hiệu địa điểm chưa khai, loại cont không có trong danh mục (trừ cột chung), TRẠNG THÁI sai.
- **UI** `price-list.jsx`: cột giá động = hợp các khóa trên dòng (+ cột người dùng thêm); thêm/xóa cột từ danh mục Loại cont. `price-excel.js`: "Tải mẫu" + "Xuất Excel" cùng 1 định dạng nhập lại được.

## Tiêu chí nghiệm thu

1. Migration backfill: lô đã ra định giá ra cùng số tổng như trước (cuoc+dau cũ = cuoc mới).
2. Lô 40HC khớp cột `40HC`; không có cột riêng thì lấy `40FT`; bảng giá mới không có cột 45 → lô 45HC "chưa khớp" nêu rõ loại cont.
3. Lưu/tải lại bảng giá giữ đúng cột động; xuất Excel → nhập lại cho ra cùng dòng giá.
4. `dev/test_features.php` mục A/B/D cập nhật + mục mới (loại cont, mẫu phẳng) pass.

## Rollback

`php artisan migrate:rollback --step=1` bỏ cột `prices`; 4 cột cũ vẫn còn nguyên dữ liệu.
