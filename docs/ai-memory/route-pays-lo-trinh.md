---
name: route-pays-lo-trinh
description: "Chi cho lái xe ở Lộ trình — phí tuyến (Cảng+Kho) \"chi theo ngày\" tổng hợp theo xe/ngày; popup chọn lái nhận; bảng trucking_route_pays"
metadata: 
  node_type: memory
  type: project
  originSessionId: b68a982e-c908-47ea-ac62-63eae4bc49de
---

**Chi cho lái xe nay ở Lộ trình** (`/lo-trinh`), thay "Duyệt chi theo lô" cũ (xem [[shipment-spend-duyet-chi]]). Mỗi xe/ngày tổng hợp các khoản "chi theo ngày" từ Phí tuyến khớp với từng chuyến.

**Phí tuyến (cai-dat#routeFees):** tuyến chọn CẢ chuỗi node **Cảng(địa điểm)→Kho→Kho→Cảng**, KHÔNG chỉ kho (trước chỉ kho là sai — không phân biệt tuyến cùng kho khác cảng, vd ICDQV→QV→ICDQV vs ICDQV→QV→HAIPHONG). UI: `MultiCombo` prop `groups` = [{label:'Cảng',items:locations},{label:'Kho',items:warehouses}] (gợi ý gom nhóm + nhãn loại trên chip/dropdown); giá trị lưu là chuỗi thuần " - " (không kèm loại). Mỗi khoản (vé trạm/tiền đường/trợ cấp/lương/dầu1/dầu2) có tick **"chi theo ngày"** (`salary_parts`) → tick mới được tổng hợp trả lái. Dầu: lít × **giá dầu theo ngày** của chuyến.

**Lương theo điều kiện KÉO CONT RA (không phải cờ cru):** chuyến **không kéo cont ra** (`leg.mode==='none'`, "ra xe không cont") → **Lương không CRU** (`luong_no_cru`); chuyến **có kéo cont ra** (mode self/other) → **Lương CRU** (`luong`).

**Khớp tuyến (backend, HandlesShipments):** `routeNodeKey(labels[])` = TẬP node chuẩn hóa về **ký hiệu** qua `normalizedCodeMap` (khớp cả tên lẫn mã, bỏ dấu/dấu cách; reuse từ statement pricing), KHÔNG phụ thuộc thứ tự (A→B→C ≡ A→C→B). Leg node = `[from_loc] + khoPoints(kho) + [to_loc]`. `routeStringNodes()` tách chuỗi phí tuyến. `legDailyCharge($leg,$axle,$rfBySet,$fuels,$date)` sinh các khoản; `routeTripByDate` cộng `payItems`/`payTotal` mỗi xe.

**Lưu lái nhận + đã chi:** bảng **`trucking_route_pays`** (migration `2026_06_18_000002`, unique `work_date`+`bks`) — CHỈ lưu `driver`/`driver_id`(lái nhận) + `paid`/`paid_date` + note; **tiền KHÔNG lưu** (auto-tính từ phí tuyến). Model `TruckingRoutePay`. Service `saveRoutePay($date,$bks,$data)` (updateOrCreate). Route `POST trucking-v2/lo-trinh/pay` (`trucking2.loTrinh.savePay`, permission `shipments.update`). `routeTripByDate` nạp `paysByBks` (whereDate work_date) → mỗi xe có `payDriver`/`paid`/`paidDate`.

**Frontend** (`pages/lo-trinh.jsx`): nút "Chi lái: {fmtVND}" ở header mỗi xe → `PayPopup` (list payItems + tổng, `Combo` chọn lái nhận từ `boot.drivers`, tick "Đã chi", link "Sửa phí tuyến" → cai-dat#routeFees). Lưu qua POST savePay.

**CẦN chạy VPS:** `php artisan migrate`.

Liên quan [[shipment-spend-duyet-chi]], [[phi-xe-batch-model]], [[route-trips]], [[ra-status-rule]], [[coded-catalog-edit]].
