---
name: shipment-spend-duyet-chi
description: Duyệt chi theo lô (theo BKS) thay popup Doanh thu&công nợ; bảng trucking_shipment_spends; phí xe sẽ hiện Kế hoạch/Đã chi/Còn lại
metadata:
  type: project
---

**Duyệt chi theo biển kiểm soát** (per-lô) — đã THAY popup "Doanh thu & công nợ" ở trang Lô hàng. Nút `₫ Duyệt chi`, modal type `spend` (trước là `rev`). `RevenuePopup`/`RevenuePopupICD` KHÔNG còn dùng ở lo-hang (data `rev` JSON vẫn giữ trong DB, chỉ bỏ UI per-lô; doanh thu nhập qua Bảng kê).

**DB:** bảng `trucking_shipment_spends` (migration `2026_06_15_000003`) — 1 dòng = 1 khoản THỰC CHI của 1 lô: `shipment_id`(FK cascade), `vehicle_id`+`bks`(snapshot), `driver`, `source`(veTram|tienDuong|troCap|luong|dau|phiKhac|other), `kind`(**salary**=lương tài xế|**company**=chi phí công ty), `name`, `amount`, `spend_date`, `paid`(default true)+`paid_date`, `note`, `created_by`, `sort`. Index: shipment_id, spend_date, (vehicle_id,spend_date), (kind,spend_date). Model `TruckingShipmentSpend`; relation `TruckingShipment::spends()`.

**Backend** (HandlesShipments): `shipmentToArray` trả `spends[]`; `saveShipment` reconcile khi `$apply('spends')` (xóa & tạo lại, created_by=auth); eager-load `spends` ở list/detail/fresh. Gợi ý: `shipmentSpendSuggest($ship)` **reuse `tripSuggest`** (khớp route theo kho + CRU + số cầu + lái xe theo lịch) → sinh dòng veTram/tienDuong/troCap/lương(theo CRU)/dầu, `kind`=salary nếu key ∈ route `salaryParts` else company. Route `GET trucking-v2/shipments/{shipment}/spend-suggest` (name `trucking2.shipments.spendSuggest`, binding hashid).

**Frontend** (`components/popups.jsx::SpendPopup`): mở → tự fetch gợi ý nếu lô chưa có spends (lô mới chưa lưu thì báo lưu trước); chọn Tài xế + Ngày chi (mặc định hôm nay, đồng bộ xuống mọi dòng), bảng khoản chi (tên/phân loại salary↔company/tiền/xóa), nút "Chi khác" + "Tải từ phí tuyến" (ghi đè). Tổng: Lương tài xế / Chi phí công ty / Tổng chi.

**Quyết định:** (1) THAY hẳn Doanh thu&công nợ. (2) Phí xe giữ snapshot route fee làm **Kế hoạch**, cộng spends (đã chi) → **Kế hoạch / Đã chi / Còn lại** theo kỳ+BKS.

**Phase 2 (ĐÃ làm):** `/phi-xe` hiện **Kế hoạch / Đã chi / Còn lại**. Helper `spendsByShipment(ids)` (HandlesTripAndDrivers) gom spends theo shipment_id + kind (salary/company); gắn field `spent{salary,company,total}` vào mỗi row của `computeTripCosts` + `tripBatchToArray`. Frontend `trip-cost.jsx::TripEditor`: card tổng hợp đầu trang (Kế hoạch=splitLine snapshot, Đã chi=Σspent, Còn lại=hiệu, tách lương/công ty) + mỗi lô hiện "Kế hoạch lô · Đã chi". Đã verify round-trip (compute trả spent đúng).

Liên quan [[phi-xe-batch-model]] (route fee + luong/luong_no_cru), [[trucking-architecture]], [[hashid-routes]].
