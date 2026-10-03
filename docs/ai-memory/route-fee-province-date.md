---
name: route-fee-province-date
description: "Phí tuyến: node có thể là TỈNH (Cảng → Tỉnh → Cảng, khớp qua province của kho) + phiên bản theo from_date (pickRouteFee chọn bản ≤ ngày chuyến)"
metadata:
  node_type: memory
  type: project
  originSessionId: 4bec1b4a-b245-4320-87b0-daa8ea0472d4
  modified: 2026-10-03T07:27:15.506Z
---

Từ 2026-10-03 (plan `plans/261003-1344-phi-tuyen-theo-tinh-va-ngay`):

**Tỉnh của kho:** `trucking_warehouses.province` (+ `warehouseProvinceArr` theo chỉ số dòng trong cfg, flag `provinced` ở groups.js). Danh sách tỉnh + tên cũ→mới ở `App\Support\VnProvinces` (34 tỉnh sau sáp nhập 2025; `guess($address)` đoán tỉnh, kể cả "Bắc Giang" → "Bắc Ninh"). UI Cài đặt → Kho: Combo tỉnh ở HEADER nhóm ký hiệu (áp cả nhóm) + nút gợi ý từ địa chỉ (`guessProvince` trong config.jsx, cùng quy tắc chuẩn hóa).

**Khớp phí tuyến theo tỉnh** (`HandlesShipments`): `warehouseProvinceByCode()` [ký hiệu kho ⇒ tỉnh chuẩn hóa]; `legPayGroup` thử khóa tập node theo kho trước, không có → `routeNodeKeyByProvince()` (thay mỗi kho bằng tỉnh) → tuyến "Cảng → Tỉnh → Cảng". Output `$g['feeRoute']`, `$g['byProvince']`, `$g['feeFrom']` → Lộ trình hiện nhãn. Nhập Excel phí tuyến chấp nhận node là tỉnh (`VnProvinces::isProvince`). RouteFees.jsx có group "Tỉnh / thành" (từ `cfg.provinces` do catalogData('routeFees') trả = LIST ∪ tỉnh đã gán kho).

**Phiên bản theo thời gian = BẢNG phí tuyến (user chốt: "không chọn ngày từng mục, áp toàn bộ phí từ ngày, giống Bảng giá"):** `trucking_route_fee_books` (label, from_date; null = bảng mặc định, mọi thời điểm) + `trucking_route_fees.book_id` (model `TruckingRouteFeeBook`, backfill tuyến cũ → bảng mặc định). `HandlesTripAndDrivers::pickRouteFeeBook($date)` = bảng có from_date lớn nhất ≤ ngày, không có → bảng mặc định; KHÔNG rơi về bảng cũ hơn (tạo bảng mới là sao chép đủ tuyến). Lộ trình (`HandlesShipments`) build `$rfBySet` chỉ từ bảng áp cho ngày vận hành; `tripSuggest` index `routeByBook[bookId][key]`. `$g['feeFrom']`/`feeBook` = của bảng. CRUD bảng: `createRouteFeeBook(label, from, copyFrom)` (copy insert hàng loạt), `updateRouteFeeBook`, `deleteRouteFeeBook` (cascade) — routes `POST/PUT/DELETE /route-fee-books[/{id}]`; `saveRouteFees(rows)` gom theo `bookId`, xóa-tạo lại TỪNG BẢNG có trong payload (bảng vắng mặt giữ nguyên). Excel xuất/nhập theo bảng (`?book=` / field `book`), header 13 cột như cũ. UI RouteFees.jsx: chip chọn bảng + "Tạo bảng phí mới từ ngày…" (modal, tick sao chép tuyến ĐÃ LƯU của bảng đang chọn) + sửa/xóa; mỗi dòng mang `bookId`; config.jsx chặn trùng theo tuyến + bookId. Ý tưởng cũ "from_date từng dòng" đã bỏ (rollback local trước khi deploy).

**Why:** đổi giá cả bộ phí từ 1 ngày trong 1 thao tác, chuyến cũ vẫn theo bảng cũ; tỉnh gom nhiều kho lẻ (1 ký hiệu nhiều tên) vào 1 mức phí mà không phải khai từng kho.

Liên quan [[route-pays-lo-trinh]], [[route-trips]], [[coded-catalog-edit]].
