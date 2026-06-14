---
name: trucking-perf-lazy-load
description: User rất nhạy với số model/query hydrate (xem qua Debugbar) trên các trang Trucking v2; ưu tiên nạp tối thiểu + lazy-load
metadata:
  type: feedback
---

User theo dõi số model/query hydrate qua Debugbar và coi "nạp nhiều model" là vấn đề cần xử lý, kể cả khi trang vẫn chạy. Trên các trang Trucking v2, mỗi lần boot phải nạp TỐI THIỂU dữ liệu màn hình thực sự dùng.

**Why:** Lo dữ liệu lớn → CPU/RAM/thời gian tải tăng. Đã nhiều lần yêu cầu giảm (508 → priceRows thừa; 78 model lo-hang do master-data dropdown front-load).

**How to apply:**
- Trang danh sách: phân trang SERVER-SIDE (`pagedShipments`, 20/trang), aggregate toàn cục bằng SQL (SUM/COUNT/whereIn subquery) thay vì nạp hết rồi tính client.
- Master data cho dropdown/popup: lazy-load qua endpoint riêng (`/trucking-v2/config`) lần đầu mở popup — boot chỉ gửi cfg tối thiểu (`shipmentBoardConfig`: costColors+freeTimeHours+vatDefault).
- Bảng giá: chỉ nạp `priceCount` cho badge, lazy-load priceList từng khách (`/customer-prices`). KHÔNG set key `priceList` khi chưa load (reconcileCustomers sẽ xóa nhầm nếu thấy mảng rỗng).
- Mỗi trang dùng cfg riêng vừa đủ: `priceBookConfig` (bang-gia: chỉ customers+locations), `statementsForList` (bang-ke: bỏ lines), `config(withPrices, priceCounts)`.
- Eager-load `with([...])` để tránh N+1; InnoDB tự gắn PK vào secondary index nên index `sheet` đã phục vụ ORDER BY id. Index covering có giá trị: `cost_lines (shipment_id, amount)` cho SUM/sort chi phí.

Liên quan: [[trucking-redesign]]
