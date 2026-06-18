---
name: route-pays-lo-trinh
description: "Chi cho lái xe ở Lộ trình — phí tuyến (Cảng+Kho) \"chi theo ngày\" tổng hợp theo xe/ngày; popup chọn lái nhận; bảng trucking_route_pays"
metadata: 
  node_type: memory
  type: project
  originSessionId: b68a982e-c908-47ea-ac62-63eae4bc49de
---

**Chi cho lái xe nay ở Lộ trình** (`/lo-trinh`), thay "Duyệt chi theo lô" cũ (xem [[shipment-spend-duyet-chi]]). Mỗi xe/ngày tổng hợp các khoản "chi theo ngày" từ Phí tuyến khớp với từng chuyến.

**Xuất/Nhập Excel phí tuyến** (commit `1b89947`, popup validate `9fa9c65`): nút Xuất/Nhập ở RouteFees. `routeFeeExportRows`+`exportRouteFees` (PhpSpreadsheet xlsx, 13 cột). `importRouteFees` UPSERT theo TẬP node tuyến (routeNodeKey) — trùng→update (giữ extra_fees), mới→create, KHÔNG xóa tuyến vắng. Routes `routeFees.export`/`importCheck`/`import` (CatalogController, đọc xlsx qua IOFactory, `parseRouteFeeFile` dùng chung). FE `trkUpload` (multipart+CSRF).
- **Popup kiểm tra trước (RouteFeeImportModal):** chọn file → POST import-check → `analyzeRouteFeeImport` (dry-run, KHÔNG ghi) phân loại từng dòng create/update/error + cảnh báo (trùng tuyến trong file, thiếu tuyến, số sai, node chưa có danh mục). Log kiểu **terminal** (✓/✗/⚠). `canImport`=0 lỗi → nút Xác nhận mới bật; `importRouteFees` cũng tự CHẶN nếu còn lỗi (validate lại server). Nhập xong reload.

**Phí tuyến (cai-dat#routeFees):** tuyến chọn CẢ chuỗi node **Cảng(địa điểm)→Kho→Kho→Cảng**, KHÔNG chỉ kho (trước chỉ kho là sai — không phân biệt tuyến cùng kho khác cảng, vd ICDQV→QV→ICDQV vs ICDQV→QV→HAIPHONG). UI: `MultiCombo` prop `groups` = [{label:'Cảng',items:locations},{label:'Kho',items:warehouses}] (gợi ý gom nhóm + nhãn loại trên chip/dropdown); giá trị lưu là chuỗi thuần " - " (không kèm loại). Mỗi khoản (vé trạm/tiền đường/trợ cấp/lương/dầu1/dầu2) có tick **"chi theo ngày"** (`salary_parts`) → tick mới được tổng hợp trả lái. Dầu: lít × **giá dầu theo ngày** của chuyến.

**Lương = ma trận 2×2:** (CÓ/KHÔNG kéo cont ra) × (CRU/không CRU). `leg.mode==='none'` = KHÔNG kéo cont ra; `leg.cru` = cờ CRU của lô. 4 cột: `luong`(kéo+CRU) · `luong_no_cru`(kéo+không CRU) · `luong_nokeo`(không kéo+CRU) · `luong_nokeo_no_cru`(không kéo+không CRU) — migration `2026_06_18_000004`. UI config: khối Lương 2 thẻ (Có/Không kéo cont ra), mỗi thẻ 2 ô CRU/không CRU, 1 tick "chi theo ngày" chung (key `luong`).

**Chọn lặp node:** MultiCombo prop `allowDup` cho chọn 1 cảng/kho nhiều lần (tuyến quay đầu ICDQV→QV→ICDQV); khớp vẫn theo TẬP (routeNodeKey dedup) nên lặp chỉ để ĐỌC đúng lộ trình.

**Cảnh báo cho kế toán:** `routeTripByDate` trả `payGroups` (1 nhóm/CHUYẾN, kể cả KHÔNG khớp phí tuyến) + `payWarn`. Mỗi nhóm có `matched`+`note` (Chưa có phí tuyến khớp / chưa tích chi theo ngày / khoản=0). PayPopup tô vàng chuyến chưa ra tiền + banner tổng; nút "Chi lái" có icon ⚠. Khoản dầu kèm `liters`+`unitPrice` để rà soát (tiền = lít × đơn giá theo ngày).

**Quyết định mở rộng (user 2026-06-18):** GIỮ cột cứng cho từng phí (rõ ràng, có kiểu). Thêm phí mới = migration + sửa ~6 chỗ (model/routeFees output/saveRouteFees/config.jsx/legPayGroup+SALARY_KEYS). KHÔNG dùng JSON "phí khác tùy chỉnh" — user chốt giữ cột cứng, khi cần phí mới sẽ báo.

**Khớp tuyến (backend, HandlesShipments):** `routeNodeKey(labels[])` = TẬP node chuẩn hóa về **ký hiệu** qua `normalizedCodeMap` (khớp cả tên lẫn mã, bỏ dấu/dấu cách; reuse từ statement pricing), KHÔNG phụ thuộc thứ tự (A→B→C ≡ A→C→B). `routeStringNodes()` tách chuỗi phí tuyến. `legPayGroup($leg,$axle,$rfBySet,$fuels,$date)` (đổi tên từ legDailyCharge) trả 1 nhóm/CHUYẾN.
- **Node của chuyến theo MODE:** KHÔNG kéo cont (mode none) → CHỈ điểm pickup (`leg.points` kind=pickup, fallback `leg.from`) vì xe chỉ tới nơi lấy rồi ra (fee 1 node "ICDQV" khớp); CÓ kéo cont (self/other) → `[from_loc]+khoPoints(kho)+[to_loc]`.

**Chi khác (repeater theo tuyến):** cột JSON `extra_fees` = list `{name, amount, perDay}` (migration `2026_06_18_000005`). Mỗi dòng TỰ quyết "chi theo ngày" (`perDay`, KHÔNG qua salary_parts). legPayGroup gom dòng perDay=true (key `'extra'`). saveRouteFees sanitize (bỏ dòng rỗng, parse tiền) qua `extraFeesIn`/`cleanExtraFees`. Đây là phần linh hoạt "thêm phí không cần code" — bù cho quyết định giữ cột cứng phí cốt lõi.

**Lưu lái nhận + đã chi:** bảng **`trucking_route_pays`** (migration `2026_06_18_000002`, unique `work_date`+`bks`) — CHỈ lưu `driver`/`driver_id`(lái nhận) + `paid`/`paid_date` + note; **tiền KHÔNG lưu** (auto-tính từ phí tuyến). Model `TruckingRoutePay`. Service `saveRoutePay($date,$bks,$data)` (updateOrCreate). Route `POST trucking-v2/lo-trinh/pay` (`trucking2.loTrinh.savePay`, permission `shipments.update`). `routeTripByDate` nạp `paysByBks` (whereDate work_date) → mỗi xe có `payDriver`/`paid`/`paidDate`.

**Frontend** (`pages/lo-trinh.jsx`): nút "Chi lái: {fmtVND}" ở header mỗi xe → `PayPopup` (list payItems + tổng, `Combo` chọn lái nhận từ `boot.drivers`, tick "Đã chi", link "Sửa phí tuyến" → cai-dat#routeFees). Lưu qua POST savePay.

**CẦN chạy VPS:** `php artisan migrate`.

**Đóng băng (chốt) ngày:** nút "Chốt ngày" ở Lộ trình → `freezeDay($date,$frozen)` snapshot payGroups/tổng mọi xe vào `route_pays.frozen_data` (cột frozen/frozen_at/frozen_data, migration `2026_06_18_000008`). routeTripByDate ƯU TIÊN frozen_data nếu đã chốt → số tiền KHÔNG đổi dù sửa Phí tuyến; kỳ lương tự dùng số đã chốt. Route `loTrinh.freeze`. Badge "Đã chốt" trên header xe.

**Chi tiết kế toán (kỳ lương /phi-xe):** component dùng chung `components/payroll-detail.jsx` — `PayrollDetail` (click bks → bung 2 cột: ĐÃ CHI THEO NGÀY | LƯƠNG CHƯA CHI, gom theo ngày+chuyến) + `PaymentsEditor` (các đợt thanh toán {date,amount,note}, trả chậm/chia đợt → Còn lại = payroll − Σ). savePayroll lưu `payments`+`detail` mỗi line; phi-xe-xem có cột Đã trả/Còn lại.

Liên quan [[shipment-spend-duyet-chi]], [[phi-xe-batch-model]], [[payroll-period-plan]], [[route-trips]], [[ra-status-rule]], [[coded-catalog-edit]].
