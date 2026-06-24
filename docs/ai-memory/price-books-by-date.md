---
name: price-books-by-date
description: "Bảng giá theo khoảng ngày — mỗi khách nhiều price book [from,to]; bảng kê định giá per-lô theo NGÀY cont ra"
metadata: 
  node_type: memory
  type: project
  originSessionId: b68a982e-c908-47ea-ac62-63eae4bc49de
---

Bảng giá nay có **nhiều phiên bản theo khoảng ngày** (price book) cho mỗi khách (migration 2026_06_24_000003).

**Schema:** `trucking_price_books` (customer_id, label, period_from/to nullable, sort) + `trucking_price_rows.price_book_id`. Model `TruckingPriceBook` (relations customer/priceRows); `TruckingCustomer::priceBooks()`. **Backfill**: mỗi khách có giá cũ → 1 book "Mặc định (mọi ngày)" (from/to null = phủ MỌI ngày), gán toàn bộ rows → bảng kê cũ chạy y nguyên.

**Chọn book theo ngày (định giá):** `pickPriceBook($customerId,$date)` (HandlesStatementPricing) — book "phủ" ngày `d` nếu `(from null||from<=d)&&(to null||d<=to)`; nhiều book phủ → ưu tiên CỤ THỂ hơn (có from, from mới nhất, id lớn) → book có ngày THẮNG book mở. Không book nào phủ → null = **"chưa khớp bảng giá"** (KHÔNG fallback — user chốt). Lô không có ngày → dùng book mở nếu có.

**Bảng kê định giá PER-LÔ theo NGÀY CONT RA** (gio_xe_ra / HPH sail_date — đã chốt, không per-kỳ): `statementCandidates`/`statementReprice`/`statementsDrift` gọi `pricingContextForDate($cust,$name,$date)` cho TỪNG lô (kỳ vắt qua mốc → mỗi lô lấy đúng book). `pricingContext($cust,$name,$priceBookId,$bookMeta)` cache key `"{cust}:{book}"`; `customerPriceListById($cust,$bookId)` lọc theo price_book_id (null→[]). `priceShipment` trả `priceBook` (id/label/from/to) → bảng kê hiện "Giá: <kỳ>".

**CRUD (HandlesPricingAndImport):** `priceBooksForCustomer`, `priceBookRows($bookId)`, `createPriceBook`, `updatePriceBook`, `deletePriceBook` (cascade rows), `savePriceBookRows($bookId,$rows)` (xóa-tạo-lại TRONG PHẠM VI book). `importPriceRows`/`copyPriceRows` nhận book id (copy book→book). **`reconcileCustomers` ĐÃ GỠ block priceList** — `/customers` chỉ lưu info khách; giá đi qua endpoint book. `priceBookConfig` trả `customerInfo[].priceBooks[{id,label,from,to,count}]`.

**Routes (PriceController, perm prices.view|update):** GET `/price-books?customerId`, `/customer-prices?book`; POST `/price-books` (create), PUT `/price-books/{book}` (sửa range/nhãn), DELETE `/price-books/{book}`, PUT `/price-books/{book}/rows` (saveBookRows); `/price-import` + `/price-copy` nhận book/fromBook/toBook.

**FE:** `pages/bang-gia.jsx` (shell: cfg + api + setBooks) + `ui/bang-gia.jsx` (BangGiaPage: thanh chip chọn book theo khoảng ngày + Tạo/Sửa/Xóa + cảnh báo chồng ngày + lazy-load rows/book + "Lưu bảng giá" per-book). `components/price-list.jsx` nhận `bookId`: import/clear theo book; copy = chọn khách nguồn → bảng giá nguồn → copy vào book đang chọn. `ui/statement.jsx` hiện "Giá: <kỳ>" + cảnh báo "ngày cont ra ngoài mọi bảng giá".

Verify (transaction-rollback): 2 book A(1-15/6)/B(16-30/6) → lô 5/6→A, 20/6→B, 20/8→chưa khớp; backfill 1 book/khách; CRUD + cascade OK. Liên quan [[lo-hang-location-filters]], [[cost-item-auto-vat]].
