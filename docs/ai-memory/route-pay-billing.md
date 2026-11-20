---
name: route-pay-billing
description: "Chi cho lái tích \"Thu khách\" → dòng chi hộ (cost line src=routePay) của lô → Bảng kê cộng Chi hộ; Quy chế có \"Phí theo điểm hạ\" (dropFees) tự cộng vào chuyến"
metadata:
  node_type: memory
  type: project
  originSessionId: e4e2c5db-8a59-4fc6-88ee-68cb3b2cba14
  modified: 2026-11-20T04:59:27.211Z
---

**2026-11-18, user chốt:** khoản chi cho lái tích **Thu khách** → **cộng vào Chi hộ** của lô trên Bảng kê (không VAT); **mọi khoản trừ Lương chuyến** tích được; Quy chế có mục **Phí theo điểm hạ** = điểm hạ (ký hiệu, nhiều) + tên + số tiền + loại (choho | luong | khac) + mặc định Thu khách; áp theo bảng quy chế của ngày chạy.

- Quy chế: `book.regulation.dropFees[{codes, name, amount, type, bill}]` (SalaryZones::cleanRegulation, trường tùy chọn). Engine `dropFeeItems($leg,$reg,$amts)` (HandlesShipments) — khớp `pointCode(leg.to)`, key `df{i}`, editable (trip_overrides.amounts; 0 = bỏ), chỉ chuyến có quy chế (matched) + kéo cont ra. type khac → khacSub (không cộng chi lái). "Thử tính 1 chuyến" cũng hiện.
- Cờ: `trip_overrides[#lô].bill = {ck: bool}` (khoản quy chế/điểm hạ; thiếu → billDefault của dropFee); chi khác `extra_items[].bill`. routeTripByDate trả `item.bill` + `payGroups[].billItems`.
- Đồng bộ `syncRoutePayBilling(date,bks)` (gọi ở saveRoutePay + freezeDay): ghi `trucking_cost_lines` billable, `src='routePay'`, `src_key="rp|{ngày}|{bks}|#{lô}|{khoản}"` (cột mới migration 2026_11_18), gỡ dòng không còn tích, recomputeShipmentDerived(['cost']). Lô = lô của chuyến (`trip` '#id' = lô bks_vao); chuyến "chưa gắn chuyến" (orphan) không thu được. Bảng kê KHÔNG cần sửa: priceShipment đọc costLines billable.
- Hạn chế: khoản mặc định Thu khách chỉ lên bảng kê khi Lưu popup Chi lái / chốt ngày; sửa tay dòng routePay ở Lô hàng sẽ bị lần đồng bộ sau ghi đè.
**Kẹp cont (2026-11-18):** = 1 xe chở 2 cont 20' CÙNG LÚC trên cùng tuyến. Nhận theo ô "Kẹp cont" ở lô (kep_cont): 2 chuyến LIỀN NHAU của cùng xe đều tích → 1 cặp; cont THỨ 2 Phụ cấp đi đường = 0 VÀ Lương chuyến (kỳ lương) = 0 (user 20/11) (base 0, `kep2`, vẫn nhập tay được). Cont thứ 2 cũng **không tính dầu** (fuel = null, bỏ cảnh báo thiếu km; lít sửa tay vẫn ghi đè; FuelChip hiện "Kẹp cont · không tính dầu"). Cặp kẹp tính **1 chuyến** (user chốt 18/11): cont thứ 2 không tăng số chuyến (trợ cấp chuyến thứ 3) và không tự nhận trợ cấp quay đầu.

**Kết hợp (2026-11-18):** 2 chuyến LIỀN NHAU của xe đều tích "Kết hợp" (ket_hop) = 1 chuyến khi ĐẾM (không đẩy chuyến sau thành chuyến thứ 3, chuyến thứ 2 không tự nhận quay đầu / chuyến 3) — nhưng dầu, phụ cấp, vé vẫn tính bình thường (khác kẹp cont). `g.ket2`; popup hiện nhãn "Kết hợp · chung chuyến trước". **Trợ cấp chuyến thứ 3** nay sửa / bỏ được từng chuyến (editable+removable như quay đầu).

**Lệch số đã chi (2026-11-18):** cột `trucking_route_pays.paid_snapshot` {total, trips{#lô:{cont,route,sub}}, at, src paid|ack|backfill} chụp số chi trong ngày (live, trước khi áp frozen) khi tích Đã chi (saveRoutePay), bỏ tích → null; migration backfill 385 xe-ngày đã chi = số lúc migrate. routeTripByDate trả `truck.live` + `truck.paidCheck` (paidDiff: diff>0 chi THIẾU, <0 THỪA, changes added/removed/changed). Lộ trình: thanh PaidDiffBar trên xe + nút "Lệch số đã chi: N xe" ở đầu trang; "Đã xử lý chênh lệch" → POST loTrinh.paidAck → chụp lại (src ack).

**BẪY PHP:** biển "29E-72151" là chuỗi SỐ MŨ → so lỏng (`==`, Collection::firstWhere/where/contains) coi mọi biển "…E-…" bằng nhau. Luôn so CHẶT (`===` / closure) khi so biển số trong PHP (SQL where thì không sao).

Liên quan [[route-pays-lo-trinh]], [[salary-regulation-engine]], [[statement-manual-price]].
