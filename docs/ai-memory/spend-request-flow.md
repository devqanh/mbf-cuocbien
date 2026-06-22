---
name: spend-request-flow
description: "Luồng \"Yêu cầu chi\" /yeu-cau-chi — quyền, login mobile, lịch sử, hủy phiếu"
metadata: 
  node_type: memory
  type: project
  originSessionId: 796005c8-78dc-47ff-827f-1058cd1cdbe3
---

Trang `/yeu-cau-chi` (mobile SPA, `resources/js/trucking2/pages/yeu-cau-chi.jsx`) giờ **cần đăng nhập + quyền `spend.request`**.

- **Quyền** `spend.request` ("Gửi yêu cầu chi") trong `config/permissions.php` (module `spend`), seed + cấp super_admin/admin. Admin tạo role gán cho tài xế ở /roles + /users.
- **Login mobile** riêng (đơn giản, BỎ 2FA): POST `/yeu-cau-chi/login` (Auth::attempt + check quyền). logout POST `/yeu-cau-chi/logout`. Boot có `auth.{logged,name,canRequest}` → SPA gate: LoginCard / NoPermission / form.
- **Phiếu = TruckingVehicleCost** (không có bảng riêng). Thêm cột `created_by` (người gửi), `cancelled_at`+`cancelled_by` (hủy). Trạng thái suy ra: cancelled→paid→approved→pending (`vehicleCostStatus`).
- **Hủy**: tài xế hủy phiếu CỦA MÌNH khi 'Chờ duyệt' (POST `/yeu-cau-chi/{cost}/cancel`); admin hủy khi CHƯA chi (PUT `/quan-ly-xe/cost/{cost}/cancel`, quyền settings.update).
- **Sửa**: tài xế sửa phiếu của mình khi 'Chờ duyệt' (POST `/yeu-cau-chi/{cost}/update`, FormData: costItem/date/amount/km + keep[] ảnh giữ + photos[] ảnh mới). UI: form có nút Sửa ở mỗi item lịch sử → nạp vào form (edit mode). Lịch sử LUÔN hiện (rỗng → "Chưa có phiếu"). Form có ô "Người yêu cầu" disabled = tên user. Login mobile có checkbox "Luôn đăng nhập" (mặc định bật) + nút con mắt xem mật khẩu. Phiếu hủy GIỮ hiển thị (xám) nhưng báo cáo phải lọc `cancelled_at IS NULL`.
- **Quản lý xe tab Chi phí**: hiện `requester`, badge trạng thái, nút Hủy. `saveVehicleManagement` PRESERVE created_by/cancelled qua delete+recreate (khớp theo id) — đừng bỏ logic này.
- **Loại chi phí = `vehicleCostTypes`** (cai-dat#vehicleCostTypes, danh mục Loại chi phí XE) — đồng bộ 3 chỗ (user 2026-06-22, sửa lệch danh mục cũ): yêu cầu chi (`publicRequestData.costItems` nay nguồn `vehicleCostTypesOut()`; validate create/update bằng `vehicleCostTypesOut()`), CostModal admin, và **Định mức km** (AllowanceTab nay options=`vehicleCostTypes` strict, BỎ tạo mới inline; FleetController boot thêm `vehicleCostTypes`; `vehicleCostTypesOut()` đổi thành public). Trước đây dùng nhầm `costItems` (TruckingCostItem = khoản chi phí LÔ) → km-định-mức khớp theo TÊN nên allowance cũ tên không trùng vehicleCostTypes sẽ mất hiệu lực, cần đặt lại. Liên quan [[trucking-report-schema]] [[asset-management]].
- **Chọn xe HOẶC tài sản**: form có toggle Xe|Tài sản (luôn hiện); tài sản ẩn ô KM (không định mức). `createSpendRequest`/`updateSpendRequestByOwner` nhận target `type='MBF' OR kind='asset'`. Lịch sử có nhãn **Xe/Tài sản** (chip theo `kind`) + `targetName` (asset = info.name). Xem [[asset-management]].
- **Ô Ghi chú** (giải trình cho kế toán): form có textarea `note` → lưu `vehicle_cost.note`, hiện ở lịch sử + tab Chi phí admin. Có ở cả gửi mới lẫn sửa.
