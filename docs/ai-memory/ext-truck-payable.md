---
name: ext-truck-payable
description: Bảng kê xe ngoài (payable nhà xe thuê) + danh mục Đơn vị xe ngoài; cột Thu phí lô hàng + cột VAT/3-cột ở bảng kê khách
metadata: 
  node_type: memory
  type: project
  originSessionId: b68a982e-c908-47ea-ac62-63eae4bc49de
---

**Đơn vị xe ngoài + Thuê xe ngoài:** danh mục catalog phẳng `extVendors` (TruckingExtVendor name/phone/note; groups.js + lookups() + CAT_KEYS). Lô có cột `ext_vendor` (TÊN nhà xe, name-based) + `ext_fee` (chốt = Σ dòng chi phí src='extTruck', trong recomputeShipmentDerived) + index (ext_vendor, gio_xe_den). Popup InfoPopup "Phân loại & tùy chọn": tích **Thuê xe ngoài** → khối ngay dưới: Combo **Nhà xe ngoài** (cfg.extVendors, **BẮT BUỘC**) + Cước + Ghi chú; Nơi hạ sà lan xuống dưới. Chặn lưu nếu có dòng extTruck mà thiếu ext_vendor (saveShipment throw + ShipmentsApp commit chặn + toast). Migration 2026_06_25_000002.

**Module Bảng kê xe ngoài (payable, mirror bảng kê khách):** mỗi bảng kê = 1 NHÀ XE. Models `TruckingExtStatement`(+Line+Payment, HasHashid) — migration 2026_06_25_000003. Trait `HandlesExtStatements` (đăng ký trong TruckingV2Service): `extStatementCandidates($vendor,$from,$to)` lọc lô `ext_vendor=$vendor` + `gio_xe_den` ∈ kỳ + `ext_fee>0`; `saveExtStatement` snapshot lines+payments, total=Σfee; `extStatementsForList`/`extStatementToArray` (paid=Σpayments, **conNo=total−paid** = công nợ). Controller `ExtStatementController` + routes `/bang-ke-xe-ngoai*`, `/ext-statement-candidates`, `/ext-statements` (perm `extStatements.{view,create,update,delete}`, gán role qua migration 2026_06_25_000004). Menu trong dropdown "Lô hàng". Pages `bang-ke-xe-ngoai{,-tao,-xem}.jsx` + `ui/ext-statement.jsx` + 3 blade + vite entries. Test rollback PASS (candidates lọc đúng, total 3.5tr, conNo 1tr).

**Bảng kê KHÁCH (cùng đợt, RIÊNG):** thêm **chọn % VAT cấp bảng kê** (0/8/10, cột `vat_rate`+base_amount+choho_amount migration 2026_06_25_000001) + **4 dòng tổng** (Phải thu cước+dầu | VAT | Chi hộ | Tổng tiền) + **3 cột mỗi dòng lô** (cước+dầu | VAT | chi hộ). VAT chỉ áp NỀN cước+dầu(+sà lan); chi hộ KHÔNG VAT. Công thức 1 nguồn: BE `HandlesStatements::statementAmounts()`, FE `lib.jsx statementAmounts()/lineAmounts()`. Tổng=Σ per-line. vat=0 → backward-compat (total cũ).

**Lô hàng:** cột **"Thu phí (cước+dầu)"** cho lô ĐÃ RA — DÙNG CHUNG `priceShipment` với bảng kê (pagedShipments tính per-lô-đã-ra, chỉ trang đang xem, không khi export); 1 nguồn công thức. Lô chưa chọn **Nhập/Xuất/Khác** (io trống) → badge cảnh báo. Liên quan [[price-books-by-date]], [[cost-item-auto-vat]], [[lo-hang-location-filters]].
