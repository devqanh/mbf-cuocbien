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

**Phase 2 (ĐÃ làm):** `/phi-xe` hiện **Kế hoạch / Đã chi / Chưa chi**. Helper `spendsByShipment(ids)` (HandlesTripAndDrivers) gom spends theo shipment_id + kind + paid, trả `spent{salary,company,total, unpaidSalary,unpaidCompany,unpaidTotal}` (đã chi=paid, chưa chi=unpaid ĐÃ ghi nhận); const `EMPTY_SPENT` làm default. Gắn vào mỗi row của `computeTripCosts` + `tripBatchToArray`. `trip-cost.jsx::TripEditor`: card tổng hợp (Kế hoạch=splitLine snapshot · Đã chi=Σpaid · **Chưa chi=Σunpaid đã ghi nhận**, KHÔNG phải Kế hoạch−Đã chi) + mỗi nhóm lái xe header hiện Kế hoạch(tiền nhận)+Đã chi+chưa chi.

**Ngữ nghĩa CHỐT (user):** "Còn lại/Chưa chi" = các khoản duyệt chi ĐÃ ghi nhận nhưng CHƯA tick paid (chờ chi) — KHÔNG phải hiệu với route fee. Đã chi cộng dồn khoản đã tick. (Trước đó từng để Còn lại=Kế hoạch−Đã chi → user thấy sai vì chưa duyệt chi gì mà còn lại = full plan.)

**Tick "Đã chi" (ghi nhận = link):** mỗi dòng trong SpendPopup có ô tick **Đã chi** (cột riêng, có "tích tất cả" ở header). Mặc định gợi ý/“Chi khác” = **chưa chi** (paid=false). Chỉ khoản **paid=true** mới tính vào "Đã chi" ở Phí xe (chưa tick = còn lại). `paid_date` = Ngày chi (popup) khi tick. Footer popup: Lương / Công ty / Tổng ghi nhận / **Đã chi (đã tick)**. Backend: `shipmentSpendSuggest` paid=false; saveShipment lưu paid+paid_date; `spendsByShipment` lọc `where('paid',true)`.

**Cột "Hành động" ở Lô hàng (bảng):** 2 nút — **Chi cho tài xế** (mở SpendPopup type `spend`) + **Chi cho lô hàng** (mở CostPopup type `cost` = Chi phí lô hàng). Card/mobile: nút "Chi cho tài xế".

**Link lương lái xe (ĐÃ làm, KHÔNG làm trang/báo cáo riêng):** thêm `driver_id` FK vào `trucking_shipment_spends` (migration `2026_06_15_000004`), resolve theo TÊN lái xe khi lưu (giữ `driver` tên snapshot). User CHỐT: KHÔNG làm trang/tab "Lương lái xe" riêng — hiển thị **Đã chi cho lái xe NGAY trong /phi-xe/tao**: mỗi nhóm lái xe (TripEditor group by `cur.driver`) header hiện "Kế hoạch (tiền nhận)" + "Đã chi cho lái xe" (Σ row `spent.salary`, từ duyệt chi paid) + còn lại. (Đã gỡ route `tripCost.salaryReport` + method `driverSalaryReport`.)

**Còn để ngỏ (chưa làm):** `cost_item_id` gom chi phí công ty theo hạng mục.

Liên quan [[phi-xe-batch-model]] (route fee + luong/luong_no_cru), [[trucking-architecture]], [[hashid-routes]].
