---
name: data-safety-reconcile
description: Quy tắc chống xóa nhầm dữ liệu — reconcile danh mục lọc kind, thêm nhanh gắn addOnly (không delete), lưu dòng con theo id + loadedIds thay vì xóa-tạo-lại; db:backup phải kiểm tra exit code mysqldump
metadata:
  type: feedback
---

Ngày 2026-09-08 phát hiện production **mất 44 tài sản (moóc) + 29 phiếu chi + 44 khấu hao** mà không ai bấm xóa. Nguyên nhân lớp lỗi "xóa hết những gì không có trong payload": `reconcileVehicles` coi cả bảng `trucking_vehicles` là xe (không lọc `kind`), và **thêm nhanh** từ popup Lô hàng gửi kèm danh sách cũ trong trình duyệt. Đã khôi phục từ `cuocbien_dev`; user yêu cầu "sửa hết các lỗi nghiêm trọng tránh bị xóa".

**Why:** nhiều bảng có NHIỀU nơi cùng ghi (vehicles: xe/tài sản/văn phòng · vehicle_costs: tab Chi phí/Yêu cầu chi/Quản lý chi phí · cost_lines: popup/import CSHT/cước xe ngoài/tờ khai). Bất kỳ save nào xóa theo "vắng mặt trong payload" sẽ xóa dữ liệu của nơi khác hoặc dữ liệu mới hơn bản client đang giữ.

**How to apply (bắt buộc khi viết save/reconcile mới):**
1. Truy vấn trên `trucking_vehicles` PHẢI lọc `kind` (`vehicle` | `asset` | `office`); danh mục Biển số xe = `kind='vehicle'` (cả boot cfg, `catalogData`, `plateIndex`, `reconcileVehicles`; không `updateOrCreate` theo plate qua kind khác).
2. Thêm nhanh từ popup (`addCfg` → `saveCatalogKey`) gửi `addOnly: true`; `reconcileLookup/Customers/Vehicles` bỏ mọi bước delete khi có cờ (`isAddOnly`). Chỉ trang Cài đặt (có hộp thoại "sắp xóa N mục") mới được xóa.
3. **Thêm nhanh chỉ được TẠO, KHÔNG được sửa bản ghi đã có** (2026-09-10). Xe: gõ biển vào ô BKS lô hàng mặc định "Xe ngoài"; biển gõ lệch dạng (`29C18195` / `29C-181.95`) chuẩn hóa ra TRÙNG xe MBF sẵn có → trước đây `updateOrCreate` theo plate đổi xe đó thành `type='Ngoài'` → biến mất khỏi Quản lý xe (lọc `type='MBF'`) + lần lưu Cài đặt kế tiếp xóa `gps_ref` → trông như mất sạch dữ liệu (dữ liệu vẫn còn, chỉ bị ẩn). Nay `$addOnly` → `continue` nếu row đã tồn tại; `reconcileLookup` không `update` dòng cũ; danh mục tên + khách dùng `firstOrCreate` thay `updateOrCreate`. Cũng chặn `kind !== 'vehicle'` (tài sản/văn phòng) chứ không chỉ `asset`.
4. Biển số ở LÔ giữ nguyên chuỗi người dùng gõ, danh mục xe lưu dạng chuẩn → khớp `vehicle_id` phải có fallback theo dạng chuẩn hóa (`vehicleIdByPlate`), nếu không lô mất liên kết xe và biến mất khỏi báo cáo/lộ trình theo xe.
5. Thuộc tính phụ (axle/gps/driver…) chỉ ghi khi payload CÓ key (`array_key_exists`) — payload thêm nhanh thiếu key từng xóa sạch Số cầu.
6. Dòng con (phiếu chi xe, chi phí lô) lưu theo **id**: có id → update tại chỗ; không id → tạo; **chỉ xóa dòng nằm trong `costsLoadedIds` / `cost.loadedIds`** (client chụp lúc nạp: FleetApp/asset.jsx/office.jsx `serverCosts()`, ShipmentsApp `costBase` chụp ở `patch()` trước khi áp bản vá) mà client không gửi lại. Thiếu loadedIds → không xóa gì. Không còn "preserve created_by/est_amount" thủ công.
7. `db:backup` gzip trong PHP và chỉ giữ file khi mysqldump exit 0 (+ `--column-statistics=0` khi bản mysqldump hỗ trợ); kiểm tra file bằng `gzip -dc f | grep -c 'CREATE TABLE'` (~68).
8. Test kiểu này: dựng dữ liệu trong transaction, gọi đúng hàm save với payload thật, so đếm, rollback ([[verify-no-destructive-save]]).
