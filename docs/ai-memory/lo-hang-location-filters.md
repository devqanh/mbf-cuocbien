---
name: lo-hang-location-filters
description: Bộ lọc /lo-hang theo địa điểm — Nơi hạ (gồm) + Nơi lấy (Gồm/Loại trừ) đều theo KÝ HIỆU; lọc Ngày đóng hàng = gio_den_du_kien (1 ngày)
metadata: 
  node_type: memory
  type: project
  originSessionId: b68a982e-c908-47ea-ac62-63eae4bc49de
---

Bộ lọc trang Lô hàng (`pagedShipments` sheet `icd`, ShipmentsApp.jsx) cho kế toán:

- **Nơi hạ / Nơi lấy theo KÝ HIỆU** (không theo tên dài): options gom từ to_loc/from_loc thực qua `normalizedCodeMap` (norm(tên|mã)→mã). Lọc gửi ký hiệu → backend mở rộng ra các giá trị raw (`whereIn`). Output `toLocs`/`fromLocs` = list ký hiệu.
- **CẢ Nơi hạ + Nơi lấy đều có chế độ GỒM/LOẠI TRỪ** (`toMode`/`fromMode`): include→whereIn; exclude→whereNotIn + orWhereNull (giữ lô KHÔNG có địa điểm). Helper chung `applyLoc($b,$col,$sel,$raw,$mode)`. Mặc định Nơi hạ=include, Nơi lấy=exclude. Use case kế toán: "lọc nơi hạ HPP, LOẠI TRỪ nơi lấy nội bộ". Params `toLoc[]`/`toMode`, `fromLoc[]`/`fromMode`. FE: component `ModeToggle` dùng chung 2 khối.
- **Ngày đóng hàng** = **Giờ đến kế hoạch** (`gio_den_du_kien`, cột dateTime indexed) — CHỌN 1 NGÀY (param `denDate` → `whereDate`). KHÔNG phải cut_off (cắt máng).

- **Nhãn (tags)**: cột `shipments.tags` (json, cast array). InfoPopup MultiCombo "Nhãn" (chọn/gõ tạo mới); chip nhãn hiện ngoài bảng (desktop row + card mobile). Lọc `tags[]` OR — **DÙNG `JSON_SEARCH(tags,'one',?) IS NOT NULL`** (KHÔNG dùng whereJsonContains: laragon = MariaDB 11.4, JSON lưu unicode escaped `\uXXXX` nên whereJsonContains/LIKE FAIL). `tagOptions` gom bằng PHP (pluck+loop, không đụng JSON func).

Tất cả áp trong closure `$searched` nên cả ĐẾM (filterCounts/total) + danh sách đều đúng. ShipmentController::page truyền `toLoc,toMode,fromLoc,fromMode,denDate,tags`.

**Sà lan = PHÍ RIÊNG (KHÔNG ép giá cont):** bảng giá có conn thứ 3 = **Non** (áp mọi trạng thái, ưu tiên sau Connect/Disconnect đúng, trước fallback). Nhóm Non có KIND "DRY CONTAINER"/"NOR CONTAINER".

Lô `is_barge` + `barge_cont` (DRY|NOR) + **`barge_drop`** (ký hiệu NƠI HẠ SÀ LAN — cột mới, migration 2026_06_23): giá cont **GIỮ NGUYÊN** (theo CRU như thường), priceShipment tính THÊM **cước+dầu sà lan** là khoản riêng. Khớp phí sà lan: bảng giá `from` = **nơi hạ CONT (to_loc, cảng)**, `loc` = **nơi hạ sà lan (barge_drop)**, kind = Non DRY/NOR, **BỎ ràng buộc kho** (lô đi sà lan không khớp kho). Tuyến sà lan = nơi hạ cont → nơi hạ sà lan. `phaiThu = cuoc+dau+chiHo+bargeCuoc+bargeDau`. pr trả thêm `isBarge/bargeCont/bargeDrop/bargeMatched/bargeKind/bargeRoute/bargeCuoc/bargeDau`.

Refactor: helper chung **`matchPriceRow($priceList,$loFrom,$loDrop,$khoCodes,$nkKind,$conn,$requireKho,$preferNon)`** (HandlesStatementPricing) — cont gọi `requireKho=true,preferNon=false` (logic cũ y nguyên); sà lan `requireKho=false,preferNon=true` (ưu tiên dòng Non). Test read-only PASS: Canon Quế Võ, to_loc=ICDTP + barge_drop=HPP → DRY 3.315.432+541.167; lô thường 77/92 khớp (không đổi).

UI: InfoPopup khu **"Phân loại & tùy chọn"** gom 3 toggle CRU / Đi sà lan / Thuê xe ngoài; tích sà lan hiện Seg DRY/NOR + Combo **"Nơi hạ sà lan"** (locOptions). Label route đổi **"Nơi hạ" → "Nơi hạ (cảng)"**. Bảng kê (statement.jsx) hiện dòng **"+ Cước sà lan"** riêng (candidate preview + detail), cảnh báo ⚠ khi chưa khớp / thiếu nơi hạ sà lan. Bảng giá: ô địa điểm hạ dùng KÝ HIỆU + Combo tìm kiếm; nhóm mới có `gid` riêng để không bị gộp khi đang sửa (gid không lưu DB).

**Field lô mới (cùng đợt):** `cost_lines.invoice_no` (Số hóa đơn từng khoản ở popup Chi phí); `shipments.info_note` (textarea Ghi chú lô, tách khỏi `ghi_chu` kế toán). **"Theo dõi" (follow)** nay phát hiện "Chưa có số HĐ" = khoản gắn màu theo dõi mà `invoice_no` trống (TRƯỚC: xét tiền=0) — áp ở follow=missing + followStats + chấm "!" CostLineRows.

Liên quan [[coded-catalog-edit]], [[ra-status-rule]], [[trucking-report-schema]].
