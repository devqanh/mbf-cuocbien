---
name: statement-refresh-due-date
description: Bảng kê — nút Làm mới trang tạo giữ giá gõ tay, Tính lại tìm lô mới trong kỳ, khung "Khách có lô trong kỳ" ở danh sách, Hạn thanh toán info.dueDate (+Excel N20/O20)
metadata:
  type: project
---

Đợt 2026-10-06 (user góp ý trang /bang-ke). Bổ sung cho [[statement-manual-price]].

**Trang tạo (`StatementForm` trong `ui/statement.jsx`):** hiện ghi chú lô (`candidateRow.infoNote` = `info_note`, component `LotNote` dùng chung với trang xem). Mỗi lô = dòng chính + dòng phụ (`ke-lo-end`) chứa ghi chú + chẩn đoán giá → hết nhảy layout khi lọc ngày (bảng mờ khi đang tải, không thay bằng dòng "Đang tải"). Nút **Làm mới** = tải lại ứng viên cùng khách+kỳ (`reloadTick`), GIỮ lô đã chọn + giá gõ tay (chỉ reset khi đổi khách/kỳ), báo `refreshSummary` (+lô mới / đổi chi hộ / đổi giá / lô rời kỳ). URL `?customer=&from=&to=` điền sẵn.

**Trang xem — Tính lại:** `GET reprice?from&to&io` → `statementReprice` trả thêm `newCandidates` (lô trong kỳ + phạm vi Nhập/Xuất, chưa có trong bảng kê này VÀ chưa nằm bảng kê khác) + `inOtherStatements`. Nút "Thêm N lô mới" (`candidateToLine`). "Áp dụng tất cả" nay áp cả dòng giá tùy chỉnh nhưng GIỮ giá gõ tay (`applySuggestion` chỉ cập nhật chi hộ/thông tin). Vẫn phải bấm Lưu.

**Danh sách — Khách có lô trong kỳ:** `components/bang-ke/CustomersInPeriod.jsx` (render qua `KePage` children), route `statements.customers` → `statementCustomersInPeriod(from,to)`: lô theo gio_xe_ra (HPH chưa có giờ ra → sail_date, như statementCandidates), mỗi khách total/nhập/xuất/đã vào bảng kê/chưa lập + link "Tạo bảng kê" điền sẵn. Mặc định Tháng trước, nhớ localStorage `trk:bangke:custPeriod`.

**Hạn thanh toán:** lưu `statement.info.dueDate` (Y-m-d) — KHÔNG thêm cột (info là JSON, [[json-schema-evolution]]); chưa đặt thì = ngày lập + số ngày Hạn TT: snapshot `info.termDays` lúc lập, không có thì Hạn TT HIỆN TẠI của khách (`customers.term_days`, cài ở Cài đặt › Khách hàng "Hạn thanh toán mặc định"). 1 quy tắc 2 nơi: backend `statementDueDate()` (list `dueDate`, statementToArray `dueDate` + `customerTermDays`, Excel ô N20 "Due date"/O20) ↔ FE `dueOf(st)`. Ô `DueDateField` = DateField + select chọn nhanh "ngày lập + 7/15/30/45/60/90 ngày" (trang tạo + trang xem; nhãn+ô gói inline-flex để không tách dòng).

**Danh sách lọc theo hạn (2026-10-06, user: "hiện hạn thanh toán, lọc cho smart"):** `KePage` thêm cột Hạn TT (ngày + chip trạng thái) và thanh lọc có đếm: Tất cả · Quá hạn · Đến hạn trong 7 ngày · Chưa đến hạn · Chưa đặt hạn · Đã thu đủ (`dueStatus`: đã thu đủ = Tổng tiền − Σ payments ≤ 0, xét TRƯỚC hạn), nhớ localStorage `trk:bangke:dueFilter`; lọc theo hạn thì xếp hạn sớm nhất lên đầu; hiện "Còn phải thu" của nhóm đang lọc; cột Tổng tiền có dòng "còn X" khi thu một phần. Khung danh sách rộng 1280.

**Vị trí ô hạn ở trang xem/sửa (2026-10-07, user: "chọn dưới này cho dễ nhìn"):** `DueSummaryRow` nằm TRONG khung tổng (`AmountSummary` nhận children) ngay dưới "CÒN PHẢI THU", kèm `DueChip` (dueStatus); ĐÃ BỎ ô Hạn TT trên thanh công cụ. Trang tạo vẫn để ở thanh công cụ.

**Cột Chi hộ ghi rõ hạng mục (2026-10-07):** `ChoHoCell` = tổng + từng hạng mục từ snapshot `detail.choHoItems` (trang tạo: `x.pr.choHoItems`), gộp trùng tên, mỗi mục 1 dòng nowrap, có in. Mọi dòng có chi hộ hiện có đều đã lưu choHoItems (priceShipment luôn trả), nên bảng kê cũ cũng hiện.

**Gắn hạn ngay trên danh sách (2026-10-07):** ô Hạn TT = `DueEditor` (bấm "+ Đặt hạn" / ngày → popup position:fixed có DueDateField, lưu NGAY khi chọn, nút "Bỏ hạn") → `PATCH statements/{id}/due` (`statements.due`, quyền statements.update) → `setStatementDueDate()` CHỈ ghi info.dueDate (không qua saveStatement) và trả hạn hiệu lực. Hàng danh sách đổi `<button>` → `<div role=button>` vì trong hàng có ô chọn ngày (không lồng control vào button được); lịch Flatpickr gắn body nên outside-click phải bỏ qua `.flatpickr-calendar`.

**Cột GHI CHÚ từng lô (2026-10-07, user thấy cột chi phí riêng tên "Ghi chú" chỉ nhập được số):** cột DB mới `trucking_statement_lines.user_note` (migration 2026_10_07_000001), JSON `lines[].userNote`. TÁCH khỏi `note` (snapshot tự sinh: ghi chú kế toán của lô, trống thì tuyến + Connect/Disconnect) để bảng kê cũ y nguyên. `LineNoteCell` (textarea, in ra chữ) là cột CUỐI ở trang tạo (state `noteOv` theo id lô, giữ khi Làm mới) và trang sửa (`setLineNote`); applySuggestion giữ vì spread `...l`. Excel cột Note/Ghi chú: userNote nếu có, không thì `note` cũ. Thêm cột làm nCols +1 → khối tổng dùng colSpan nhỏ hơn 1 + ô trống cuối.

**Ghi chú khoản Chi phí lô hàng trong bảng kê khách (2026-10-15):** `statementToArray` trả `lines[].costNotes` [{item,note,billable}] lấy TRỰC TIẾP từ trucking_cost_lines (1 query, chỉ dòng có note) → bảng kê cũ cũng hiện, sửa ở lô là hiện theo; snapshot định giá (`priceShipment` choHoItems/costItems) cũng kèm `note` cho trang Tạo. FE `costNoteMap/noteOfItem/NoteTxt` (ui/statement.jsx): dưới từng hạng mục ô Chi hộ (có in), trong dòng "Chi phí công ty" và chi tiết "+ Chi hộ". Không đổi tiền.
