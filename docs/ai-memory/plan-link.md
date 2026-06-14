---
name: plan-link
description: Link kế hoạch công khai cho lái xe cập nhật giờ xe đến/ra (mobile)
metadata:
  type: project
---

Tính năng **Link kế hoạch**: admin tạo link công khai theo khoảng ngày (lọc **Giờ đến dự kiến** `gio_den_du_kien`), lái xe mở trên điện thoại (KHÔNG đăng nhập) để cập nhật **giờ xe đến / giờ xe ra**, ghi chú, ảnh.

- **Bảng** `trucking_plan_links` (token ngẫu nhiên 24 ký tự = bí mật link, title, from_date, to_date, active, created_by). Migration `2026_06_14_000007` cũng thêm cột `driver_note` vào `trucking_shipments`. Model `TruckingPlanLink::newToken()`.
- **Admin**: trang `/trucking-v2/ke-hoach` (`planLinks`, quyền shipments.view; mutations shipments.update) — tạo/sửa(tên+ngày)/bật-tắt/xóa link, copy URL. Nút vào trang này nằm ở **toolbar trang Lô hàng** ("Link kế hoạch"), KHÔNG ở menu chính (theo yêu cầu). Frontend `pages/ke-hoach.jsx`.
- **Công khai (lái xe)**: `/ke-hoach/{token}` (route top-level, không auth). Frontend mobile-first `pages/ke-hoach-public.jsx` (blade `ke-hoach-public.blade.php` layout tối giản như yeu-cau-chi). UX lowtech: danh sách lô + tìm + lọc (chưa xong/đã xong); bấm 1 lô → sheet toàn màn hình với **2 nút lớn "Xe đã đến"/"Xe đã ra"** đóng dấu giờ hiện tại 1 chạm (sửa tay được qua datetime-local), ghi chú, chụp ảnh (capture=environment). Trạng thái suy ra từ giờ.
- **Service** (`TruckingV2Service`): planLinksForList/createPlanLink/updatePlanLink/setPlanLinkActive/deletePlanLink; planPublicData($link); planUpdateShipment($link,$shipHashid,$in,$files) — **chỉ ghi gio_xe_den/gio_xe_ra/driver_note + ảnh**, và CHỈ cho lô nằm trong khoảng của link (bảo mật, đã test chặn); planDeletePhoto. Ảnh dùng `trucking_attachments` group **'shipmentPhoto'** owner=TruckingShipment (S3-ready). Lô tham chiếu bằng **hashid** ([[hashid-routes]]).
- Controller: planPublicPage/planPublicData/planPublicUpdate/planPublicDeletePhoto (public), createPlanLink/updatePlanLink/togglePlanLink/destroyPlanLink (admin). Routes name `trucking2.plan*`.

Liên quan [[hashid-routes]] [[file-attachments]] [[mobile-responsive]]. Deploy: `migrate --force && npm run build`.
