---
name: ra-status-rule
description: "Quy tắc \"đã ra/chưa ra\" của lô = có Giờ xe ra (gio_xe_ra) HOẶC Biển số ra (bks_ra), KHÔNG chỉ dựa BKS"
metadata: 
  node_type: memory
  type: project
  originSessionId: abfdb9c6-78f0-4c41-acce-4ab00891767b
---

Trạng thái **"đã ra"** của lô hàng = **có `gio_xe_ra` (Giờ xe ra) HOẶC `bks_ra` (Biển số ra)**.

**Why:** xe thuê ngoài nhiều khi không cập nhật được biển số, nên chỉ cần có **Giờ xe ra** là coi như đã ra. Trước đây hệ thống chỉ dựa `bks_ra` → cont có giờ ra nhưng thiếu BKS bị tính nhầm "chưa ra".

**How to apply:** dùng quy tắc này nhất quán ở:
- Badge "Đã ra/Chưa ra" + cột danh sách lô (resources/js/trucking2/pages/lo-hang.jsx — table + card).
- Tab lọc & đếm `filterCounts` out/notout (HandlesShipments::pagedShipments — `$applyOut`/`$applyNotOut`).
- Ô "Chọn cont ra cùng chuyến" trong popup Thông tin lô (popups.jsx): chỉ liệt kê cont CHƯA RA = chưa có cả gio_xe_ra lẫn bks_ra.

Phân biệt 3 field dễ nhầm: `gio_xe_ra` (Giờ xe ra, tính free time) ≠ `cont_ra` (Ngày cont ra — ĐÃ BỎ khỏi UI) ≠ `bks_ra` (Biển số ra). siblingsList trả kèm gioXeRa+bksRa cho popup lọc. Liên quan [[trucking-report-schema]].

**Trường hợp giờ xe ra (`ra_mode`) — 3 lựa chọn trong popup (mục Free time & kết nối):**
- `self` (Chính cont này) → `gio_xe_ra` = giờ ra **của cont** (lô này).
- `other` (Cont khác ra) → ghi `gio_xe_ra`+`bks_ra` vào **cont khác** (`ra_other_id`) qua field transient `raOtherGioXeRa`/`raOtherBksRa` trên payload lô hiện tại → **backend đẩy theo id** (`TruckingShipment::find(ra_other_id)`) nên cập nhật được CẢ KHI cont kia ở trang khác (commit 92ab438; trước đây dùng patchOther vào `data` trang hiện tại → off-page bị bỏ qua).
- **Ô "Giờ xe ra (của cont)" LUÔN cho nhập** (DTField, ghi `gio_xe_ra` của chính cont) — KỂ CẢ khi chọn "cont khác ra" (commit ba32a27, user yêu cầu). Đã **bỏ guard backend ép null** + bỏ onPick xóa gioXeRa. Free time tính theo `gio_xe_ra` của chính cont. Phần "cont khác ra" chỉ ghi giờ ra/BKS cho cont ĐƯỢC CHỌN, độc lập với giờ ra của cont hiện tại. Migration `2026_06_18_000001` đã dọn dữ liệu cũ (other/none còn dính gio_xe_ra → list "đã ra" sai).
- **"đã ra" CHỈ xét `gio_xe_ra`** (bỏ `bks_ra`, commit 2b3fcca): badge/cột + filter out/notout (`$applyOut`/`$applyNotOut`) + picker "cont ra cùng chuyến" đều chỉ dựa `gio_xe_ra`.
- `none` (Không kéo công ra) → xe ra nhưng KHÔNG kéo cont nào; ghi vào **cột RIÊNG `gio_xe_ra_xe`** (giờ ra của XE/đầu kéo) để sau tính phí hạng mục khác. `gio_xe_ra` (cont) GIỮ TRỐNG → lô vẫn "**chưa ra**" (đúng: cont không ra). Migration `2026_06_15_000002`.

**Ngưỡng free time (Connect/Disconnect) theo KHOẢNG NGÀY cont ra** (commit ac50299): mặc định `free_time_hours` (setting); cộng `free_time_rules` (JSON setting = [{from,to,hours}]) — cont ra rơi vào khoảng nào dùng ngưỡng đó (vd 12/6–30/6=2h, 1/7–20/7=4h), không khớp → mặc định. `calcFreeTime(s, default, rules)` (lib.jsx) chọn theo ngày `gioXeRa`; backend `freeTimeThresholdForDate` (HandlesStatementPricing) cho bảng kê. UI: tab Cấu hình chung có repeater "Ngưỡng theo khoảng ngày".

**`gio_xe_ra` LUÔN là giờ của CONT; `gio_xe_ra_xe` là giờ của XE (chỉ dùng khi ra_mode='none').** Quy tắc "đã ra" CHỈ xét `gio_xe_ra`+`bks_ra` (KHÔNG xét `gio_xe_ra_xe`) — đừng nhầm. UI: nhãn ô đổi động "Giờ xe ra (của cont)" / "(của XE)"; đổi trường hợp thì tự dọn field còn lại (popups.jsx) để 2 mốc không lẫn.

**Cập nhật:** đã **bỏ field "Ngày cont ra"** khỏi popup Lô hàng (gio_xe_ra là mốc cont rời đi). Cột "Ra:" trong list + badge hiện theo `gio_xe_ra`. **Bảng kê** (trang Tạo) lấy NGÀY KỲ = ngày của `gio_xe_ra` (fallback sail_date/cont_den) — sửa ở `HandlesStatementPricing::candidatesForStatement` + `statementReprice` (đã ghi chú trong code). Trang Tạo bảng kê: **chưa chọn kỳ (ngày ra) thì không tải lô**, hiện ghi chú "Vui lòng chọn ngày ra của lô hàng".
