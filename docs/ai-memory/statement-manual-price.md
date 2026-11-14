---
name: statement-manual-price
description: "Bảng kê: giá tùy chỉnh = detail.manualBase (Tính lại KHÔNG ghi đè, chỉ gợi ý + nút Áp dụng); drift so nền+chi hộ, bỏ qua nền dòng tùy chỉnh"
metadata:
  node_type: memory
  type: project
  originSessionId: 4bec1b4a-b245-4320-87b0-daa8ea0472d4
  modified: 2026-11-14T08:20:41.631Z
---

Từ 2026-09-23 (plan `plans/260923-0328-bang-ke-gia-tuy-chinh`), user chốt: **giá gõ tay trong bảng kê là số chính thức, "Tính lại" chỉ gợi ý, bấm Áp dụng mới điền** ("nhiều khi giá đó mới là đúng").

**Dữ liệu:** dòng bảng kê `detail.manualBase` (int, tùy chọn) = nền người dùng đặt tay. `detail.cuoc/dau/bargeCuoc/bargeDau` GIỮ NGUYÊN snapshot hệ thống tính để đối chiếu (trước đây sửa tay = ghi đè `cuoc=base, dau=0` nên không phân biệt được). `phai_thu`/`cuoc` cột = nền đang thu (đồng bộ với manualBase).

**Nguồn chân lý nền dòng** (`lib.jsx lineAmounts` = `HandlesStatements::statementAmounts`): `baseOv` (đang gõ ở form tạo) → `manualBase` → cuoc+dau+sà lan → `phaiThu` (dòng cũ không detail). VAT tính trên nền đang dùng; chi hộ luôn từ detail.chiHo.

**Trang xem (`bang-ke-xem.jsx` + `SavedStatementPage`/`StatementDetailBody`):** "Tính lại" → GET reprice → `suggestById[lineId] = {found, ...pr}`, KHÔNG sửa `st.lines`. Helper module-level trong `ui/statement.jsx` (export qua ui.jsx): `isManualLine(l)`, `suggestionOf(l, s, vatRate)` (null nếu nền+chi hộ+tuyến/kết nối/contKey không đổi), `applySuggestion(l, s)` (snapshot mới thay cũ, giữ `detail.vat`, bỏ manualBase, nền = số hệ thống). UI: ô nền viền vàng + nhãn "Giá tùy chỉnh" + nút "↺ <giá hệ thống>" (clearManual); chip "Hệ thống tính X · Áp dụng" ở dòng lệch; thanh tổng kết "N dòng có giá mới, M dòng tùy chỉnh được giữ" + "Áp dụng N dòng" (bỏ qua dòng tùy chỉnh) + "Ẩn gợi ý". `detailById` luôn = snapshot đã lưu.

**Drift danh sách (`statementsDrift`):** so `statementAmounts([$pr])` với `statementAmounts([$detail đã lưu])` (nền + chi hộ); dòng tùy chỉnh chỉ so chi hộ → không còn báo "cần tính lại" mãi vì giá tay, và không còn so nhầm `pr.phaiThu` (gồm chi hộ) với `phai_thu` (nền). Chip danh sách đổi thành "Giá mới: N lô".

**Danh sách (`KePage`):** cột khách `minmax(0,1fr)` + ellipsis, chip xuống dòng riêng, số tiền nowrap, khung 1120 — trước đây chip nowrap trong ô khách làm lưới tràn, cột Tổng tiền bị cắt.

**Dữ liệu cũ:** 19 dòng của 2 bảng kê T6 có `phai_thu` = nền + chi hộ (semantics cũ) — không sửa, vì nền vẫn tính từ detail; không có dòng tùy chỉnh thật nào trước ngày này.

**Phạm vi Nhập/Xuất (2026-10-03, user: "chọn xuất/nhập để TÍNH TIỀN bảng kê, không phải lọc hiển thị"):** cột `trucking_statements.io_scope` (all|nhap|xuat, FE `ioScope`). `HandlesStatements::ioInScope($io, $scope)` (io lô "Nhập"/"Xuất"; FE `ioKey`) — `statementToArray`/`saveStatement` chỉ cộng lô trong phạm vi vào 4 con số; dòng khác loại VẪN lưu (đổi phạm vi về Tất cả là tính lại đủ); Excel exporter + drift bỏ qua lô ngoài phạm vi. UI trang xem: nhóm nút "Phạm vi" cạnh VAT (lưu cùng bảng kê, cần bấm Lưu), lô ngoài phạm vi ẨN HẲN khỏi danh sách (`scopedLines.map`, user 03/10: "hiện mờ nhìn rối"), thanh cảnh báo nêu số lô đã ẩn, chọn Tất cả để xem lại; trang tạo: nhóm "Lô hàng" lọc ứng viên và lưu `ioScope`; danh sách hiện chip "Hàng xuất/nhập". Liên quan [[price-by-cont-type]], [[json-schema-evolution]], [[cost-item-auto-vat]].

**Lô "Khác" (2026-11-14):** lô io Khác/trống (CHUYỂN KHO) thuộc MỌI phạm vi (cả Nhập lẫn Xuất), nếu không khách tách bảng kê Nhập/Xuất như Canon sẽ không thu được; chống thu trùng nhờ billedIn. Thêm phạm vi `khac` = CHỈ lô Khác (nút "Khác" ở bộ lọc trang tạo + trang xem, chip "Hàng khác"); FE `ioInScope`/`IO_LABEL` khớp backend.

**Lọc Điểm hạ (2026-11-14):** Tất cả / Hạ ICD QV / Hạ ICD TP / Điểm hạ khác theo `toCode` (ký hiệu Nơi hạ, backend `locationCodeOf()` — "ICD TP"/"ICDTP" cùng ICDTP) có ở candidates + lines; trang tạo = như Connect (LƯU các lô đang hiện), trang xem = chỉ lọc hiển thị. **Phạm vi LOẠI TRỪ (2026-11-14, khách hỏi "lọc Xuất vẫn ra cont chuyển kho"):** Nhập/Xuất/Khác mỗi nút CHỈ đúng loại đó (đếm cộng = Tất cả); thu kèm lô Khác = ô tích "Kèm N lô Khác (chuyển kho)" → io_scope `nhap+k` / `xuat+k` (hằng `IO_SCOPES`, `ScopePicker` FE). Thay quy tắc cũ "lô Khác thuộc mọi phạm vi"; 64 bảng kê đã lưu không đổi tổng.

**Lọc hiển thị + xuất dữ liệu đang xem (2026-11-14):** trang tạo/xem có lọc "Có ghi chú" (infoNote|userNote) và "Có phí khác ngoài" (MultiCombo khoản costLines, `filterOtherFee`) — CHỈ lọc hiển thị (trang tạo vẫn lưu đủ lô đã tích). Trang xem có nút "Xuất dữ liệu đang xem": FE gửi đúng `shownLines` + số `lineAmounts` (kể cả sửa chưa lưu) POST `/bang-ke/{st}/export-view` → `StatementViewExporter` (PhpSpreadsheet, chỉ đọc): sheet Bảng kê (nhóm cột màu, mỗi khoản chi phí 1 cột xanh=chi hộ/cam=công ty, dòng TỔNG SUBTOTAL theo lọc, autofilter, freeze), sheet Chi phí từng khoản, sheet Tổng hợp khoản. Khác mẫu chính thức `export-excel`.

**Ghi chú lô trong chi tiết bảng kê (2026-09-30):** `statementToArray` trả `lines[].infoNote` = `info_note` ("Ghi chú tự do cho lô hàng" ở popup Thông tin lô) LẤY TRỰC TIẾP từ lô (sửa ở popup là bảng kê hiện theo, 1 query/bảng kê; `statements()` eager-load `lines.shipment:id,info_note`). ĐỪNG nhầm với `ghi_chu` = "Ghi chú kế toán" ở popup Doanh thu (bản đầu dùng nhầm cột này → user không thấy ghi chú). KHÔNG dùng cột `note` của dòng: đó là snapshot "ghi chú, trống thì tuyến + Connect/Disconnect" nên không tách được. `StatementDetailBody` hiện khối "Ghi chú:" (pre-wrap) đầu phần chi tiết lô; bảng kê cũ không có detail → dòng riêng `noteOnly` ngay dưới lô (kẻ cuối lô chuyển xuống dòng đó). Có in (không ke-noprint). Excel xuất vẫn dùng `note` snapshot.
