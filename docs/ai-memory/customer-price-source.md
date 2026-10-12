---
name: customer-price-source
description: "Khách DÙNG CHUNG bảng giá của khách khác (trucking_customers.price_source_id, 1 cấp) — định giá lô/bảng kê/báo cáo tra bảng giá của khách nguồn qua priceOwnerId(); chọn ở trang /bang-gia"
metadata:
  type: project
---

2026-10-12 user: Canon Quế Võ / Thăng Long / Tiên Sơn (hay các khách MSK) cùng giá, nhập lặp nhiều lần → chọn phương án **liên kết khách** (không phải sao chép).

- Cột `trucking_customers.price_source_id` (FK tự tham chiếu, nullOnDelete). KHÔNG nằm trong `$fillable` → `reconcileCustomers` (lưu Cài đặt → Khách hàng) không đụng.
- `priceOwnerId($customerId)` (HandlesPricingAndImport, memoize) = khách nguồn hoặc chính nó. Mọi chỗ tra bảng giá theo khách đi qua đây: `customerPriceBooks` (chọn book theo ngày) + `customerPriceListById`. `pricingCfg` (trang xem bảng kê) vẫn đọc `priceRows` của chính khách nhưng client không dùng priceList đó.
- Chỉ **1 cấp**: `setCustomerPriceSource` chặn tự liên kết, chặn chọn nguồn đang dùng chung (báo chọn thẳng gốc), chặn khách đang làm gốc cho khách khác đi dùng chung. Bảng giá riêng của khách liên kết vẫn giữ (bỏ liên kết là dùng lại).
- API `PUT /trucking-v2/price-source/{customerId}` {source: tên | null} (quyền prices.update) → `{ok, priceSource}` hoặc `{ok:false,msg}`. `priceBookConfig().customerInfo[x].priceSource` = tên nguồn.
- UI `ui/bang-gia.jsx`: ô chọn "Bảng giá dùng cho khách này" dưới tiêu đề; khách liên kết thấy khung tóm tắt bảng giá của nguồn + nút Mở bảng giá nguồn / Bỏ dùng chung; sidebar ghi "giá của X". Khách gốc thấy "Đang là bảng giá gốc của …".
- Bảng kê đã lập giữ giá đã chốt; chỉ đổi khi bấm Tính lại. Liên kết làm ĐỔI giá lô nếu bảng giá riêng cũ khác nguồn (vd lô 1954 Thăng Long 4.715.770 → 4.501.490).

Xem [[price-books-by-date]] [[cost-report]].
