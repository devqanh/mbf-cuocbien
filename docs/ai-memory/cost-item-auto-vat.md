---
name: cost-item-auto-vat
description: "Khoản chi phí có cờ \"auto\" (tự hiện + nhắc chưa điền mọi lô) và VAT% (chi phí = số tiền trừ VAT/net)"
metadata: 
  node_type: memory
  type: project
  originSessionId: b68a982e-c908-47ea-ac62-63eae4bc49de
---

Danh mục **Khoản chi phí** (`trucking_cost_items`, colored catalog) có thêm 2 cột (migration 2026_06_24):

**`auto` (Tự hiện):** checkbox ở Cài đặt → Khoản chi phí (cạnh "Theo dõi").
- Khoản auto **tự hiện sẵn 1 dòng RỖNG** trong popup Chi phí lô hàng (CostPopup merge `displayItems`). Bỏ trống KHÔNG lưu — backend bỏ qua dòng không có nội dung (amount=0 + trống invoice/note/payer/src).
- Khoản auto **+ có màu theo dõi** → danh sách lô nhắc "chưa điền" (hiện màu) trên **MỌI lô** chưa có dòng điền **SỐ HÓA ĐƠN** cho khoản đó (kể cả lô chưa thêm dòng). Khoản màu KHÔNG-auto giữ nguyên: chỉ lô đã có dòng.
- **"Đã điền" = có dòng khoản đó CÓ số hóa đơn** (user chốt invoice-based, KHÔNG phải số tiền). Điền số tiền chưa đủ — phải điền ô Số hóa đơn (kể cả "0"). Verified trên data thật: lô có Nâng/Hạ điền HĐ → chỉ còn hiện CSHT.

**`vat` (VAT %):** cột ở catalog (mặc định) + cột VAT% từng dòng ở popup.
- Chọn khoản ở popup → tự fill VAT đã cấu hình (nếu dòng chưa nhập), vẫn sửa được.
- **Số tiền điền = giá GỒM VAT**; **CHI PHÍ (net) = số tiền ÷ (1 + vat/100)** (tách VAT chuẩn, user chốt). `TruckingCostLine::netAmount()`.
- TỔNG CHI PHÍ / báo cáo / lợi nhuận đều dùng NET: `calcCost` (frontend), `recomputeShipmentDerived` (cost_total/billable/company), `totalCost` header (SQL `ROUND(amount/(1+COALESCE(vat,0)/100))`), `monthlyCostReport`. Chi hộ/bảng kê (thu lại khách) KHÔNG đổi — giữ gross.

**Backend chốt:**
- `config()`/`lookupData()` xuất `costAuto`/`costVat` (map name→true / name→%). `shipmentBoardConfig` (boot) kèm `costAuto` để list hiện màu ngay.
- `reconcileLookup`: CHỈ ghi color/auto/vat khi payload CÓ gửi key đó (`array_key_exists`) → thêm nhanh khoản từ popup (không gửi costColors/costAuto/costVat) KHÔNG xoá nhầm. CAT_KEYS (cai-dat.jsx) costItems gửi `costColors,costAuto,costVat`.
- `followStats($searched(), $autoHexes)`: màu auto expected mọi lô (pluck ship ids + per-ship filled-by-invoice); màu thường chỉ lô có dòng. Filter follow `any/missing/#hex` hỗ trợ auto qua `whereDoesntHave` dòng-đã-điền-số-HĐ.
- `shipmentToArray` cost items kèm `vat` + `invoiceNo`.

Frontend helper `missFollow(s)` (ShipmentsApp) gộp (dòng màu trống số HĐ) + (auto+màu chưa điền) → chip cột Chi phí (desktop + card). Liên quan [[lo-hang-location-filters]].
