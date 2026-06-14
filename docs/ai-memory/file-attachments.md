---
name: file-attachments
description: Bảng file tập trung trucking_attachments (S3-ready) — mọi upload đi qua đây
metadata:
  type: reference
---

Mọi file upload Trucking gom về bảng **`trucking_attachments`** (polymorphic) để dễ quản lý + migrate S3:
- Cột: owner_type/owner_id, group ('doc' | 'costPhoto'), **disk** (lưu theo từng file → local/s3 chạy song song), path, name, type, mime, size, sort.
- Disk lấy từ `config('trucking.upload_disk')` (env `TRUCKING_UPLOAD_DISK`, mặc định local). Đổi S3 = set env + copy file cũ; KHÔNG sửa code.
- **Tài liệu lái xe/xe**: owner=TruckingDriver/TruckingVehicle, group='doc' → nguồn sự thật là bảng attachments (cột JSON documents cũ bỏ dùng, đã backfill).
- **Ảnh phiếu chi**: owner=**TruckingVehicle** (id ổn định), group='costPhoto'; `trucking_vehicle_costs.photos` = **MẢNG ID attachment**. Dọn mồ côi qua `pruneOrphanCostPhotos` khi lưu chi phí.
- **Stream 1 route** disk-agnostic: `GET /trucking-v2/attachment/{id}` (`trucking2.attachment`) → `Storage::disk($a->disk)->response(...)` chạy cả local lẫn S3. Phân quyền theo owner trong controller (doc→settings.view; costPhoto→settings.view hoặc spend.request + sở hữu).
- Service helpers: `storeAttachments / listAttachments / deleteAttachment / attachmentOut` (TruckingV2Service). Xóa/sửa tài liệu ở frontend dùng **id attachment** (không phải index).

Liên quan [[spend-request-flow]]. Migration `2026_06_14_000003_create_trucking_attachments` tự backfill JSON cũ → rows. Deploy: `php artisan migrate --force && php artisan config:clear`.

**Cài đặt hệ thống** (menu Quản trị → "Cài đặt hệ thống", quyền `system.settings`, super_admin): trang `/system-settings` (Bootstrap, `SystemSettingController`) chọn nơi lưu file Local/S3 + nhập creds S3 (lưu `TruckingSetting` prefix `sys.`, secret mã hóa Crypt) + nút "Kiểm tra kết nối". Mặc định LOCAL. `uploadDisk()` = `TruckingSetting::get('sys.upload_disk')` ?: config. S3 nạp LAZY qua `TruckingV2Service::applyS3Config()` (chỉ khi đụng file s3 → 0 query khi dùng local). Trang này là khung mở rộng cho cấu hình chung về sau.

**Cấu hình công ty / bảng kê** (cùng trang Cài đặt hệ thống): (1) "Thông tin công ty" = header bảng kê trên MÀN HÌNH/in → `sys.company_name/website/phone`, đọc qua `companyInfo()` (1 query gộp), truyền vào `boot.company` của 2 trang bang-ke-tao + bang-ke-xem, ui.jsx đọc `window.__TRK.boot.company` (CO_NAME/CO_SUB, fallback mặc định). (2) "Bên bán — xuất Excel" = `sys.seller_name/address/tax/rep/title`, đọc qua `sellerInfo()`. **LƯU Ý tách riêng**: tên hiển thị màn hình là tiếng Anh ("MBF JOINT STOCK COMPANY") còn tên pháp lý Bên bán trên Excel là tiếng Việt ("CÔNG TY CỔ PHẦN MBF") → đừng gộp 1 field. `exportStatement` ghi BÊN MUA (A8-A11, tự lấy từ `statementToArray()['info']`: customer/address/taxCode/rep/title) + BÊN BÁN (A13-A16, từ sellerInfo) vào template `statement-template.xlsx` (bố cục có sẵn: mua 8-11, bán 13-16, "Cùng đối chiếu" 17, data từ 22). Mọi giá trị có default = thông tin MBF cũ nên chưa cấu hình vẫn xuất đúng. Đổi text này KHÔNG cần sửa template tay nữa.
