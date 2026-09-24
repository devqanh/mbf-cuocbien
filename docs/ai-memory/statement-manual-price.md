---
name: statement-manual-price
description: "Bảng kê: giá tùy chỉnh = detail.manualBase (Tính lại KHÔNG ghi đè, chỉ gợi ý + nút Áp dụng); drift so nền+chi hộ, bỏ qua nền dòng tùy chỉnh"
metadata:
  type: project
---

Từ 2026-09-23 (plan `plans/260923-0328-bang-ke-gia-tuy-chinh`), user chốt: **giá gõ tay trong bảng kê là số chính thức, "Tính lại" chỉ gợi ý, bấm Áp dụng mới điền** ("nhiều khi giá đó mới là đúng").

**Dữ liệu:** dòng bảng kê `detail.manualBase` (int, tùy chọn) = nền người dùng đặt tay. `detail.cuoc/dau/bargeCuoc/bargeDau` GIỮ NGUYÊN snapshot hệ thống tính để đối chiếu (trước đây sửa tay = ghi đè `cuoc=base, dau=0` nên không phân biệt được). `phai_thu`/`cuoc` cột = nền đang thu (đồng bộ với manualBase).

**Nguồn chân lý nền dòng** (`lib.jsx lineAmounts` = `HandlesStatements::statementAmounts`): `baseOv` (đang gõ ở form tạo) → `manualBase` → cuoc+dau+sà lan → `phaiThu` (dòng cũ không detail). VAT tính trên nền đang dùng; chi hộ luôn từ detail.chiHo.

**Trang xem (`bang-ke-xem.jsx` + `SavedStatementPage`/`StatementDetailBody`):** "Tính lại" → GET reprice → `suggestById[lineId] = {found, ...pr}`, KHÔNG sửa `st.lines`. Helper module-level trong `ui/statement.jsx` (export qua ui.jsx): `isManualLine(l)`, `suggestionOf(l, s, vatRate)` (null nếu nền+chi hộ+tuyến/kết nối/contKey không đổi), `applySuggestion(l, s)` (snapshot mới thay cũ, giữ `detail.vat`, bỏ manualBase, nền = số hệ thống). UI: ô nền viền vàng + nhãn "Giá tùy chỉnh" + nút "↺ <giá hệ thống>" (clearManual); chip "Hệ thống tính X · Áp dụng" ở dòng lệch; thanh tổng kết "N dòng có giá mới, M dòng tùy chỉnh được giữ" + "Áp dụng N dòng" (bỏ qua dòng tùy chỉnh) + "Ẩn gợi ý". `detailById` luôn = snapshot đã lưu.

**Drift danh sách (`statementsDrift`):** so `statementAmounts([$pr])` với `statementAmounts([$detail đã lưu])` (nền + chi hộ); dòng tùy chỉnh chỉ so chi hộ → không còn báo "cần tính lại" mãi vì giá tay, và không còn so nhầm `pr.phaiThu` (gồm chi hộ) với `phai_thu` (nền). Chip danh sách đổi thành "Giá mới: N lô".

**Danh sách (`KePage`):** cột khách `minmax(0,1fr)` + ellipsis, chip xuống dòng riêng, số tiền nowrap, khung 1120 — trước đây chip nowrap trong ô khách làm lưới tràn, cột Tổng tiền bị cắt.

**Dữ liệu cũ:** 19 dòng của 2 bảng kê T6 có `phai_thu` = nền + chi hộ (semantics cũ) — không sửa, vì nền vẫn tính từ detail; không có dòng tùy chỉnh thật nào trước ngày này. Liên quan [[price-by-cont-type]], [[json-schema-evolution]], [[cost-item-auto-vat]].
