---
name: activity-log
description: Nhật ký thao tác nhân viên (bảng activity_logs) — ĐÃ build 2026-10-10; Claude tra bằng `php artisan activity:log --subject=... ` khi rà lỗi dữ liệu; không có AI trong UI
metadata:
  type: project
---

Đã build 2026-10-10 (plan plans/261010-1059-activity-log). User muốn "AI rà soát" = **Claude Code đọc nhật ký khi code**, không làm AI/API key trong giao diện.

**Khi rà lỗi dữ liệu ("ai đổi X, lúc nào") — tra nhật ký TRƯỚC khi đoán:** `php artisan activity:log --subject=29C-18195` (thêm `--user= --module= --event=updated --grep= --from= --to= --request=<uuid> --json`). Dữ liệu prod: `db:pull` rồi chạy local. Mỗi dòng: thời gian · [request] · trang · người · nhãn đối tượng: đã sửa — Trường: cũ → mới.

Cơ chế:
- Model khai ở `config/activity.php` (nhãn, cột tiêu đề, cột cha, cột bỏ qua như số liệu suy diễn của lô); event created/updated/deleted gắn ở AppServiceProvider (KHÔNG sửa file model). Thêm model mới cần log → chỉ thêm 1 dòng config.
- Middleware `LogActivity` (web group) ghi 1 dòng `request` cho POST/PUT/PATCH/DELETE kèm payload đã che (password/secret/token/_key…).
- `ActivityLogger` (singleton) đệm, insert gộp ở `app()->terminating`; status ≥ 400 → bỏ dòng diff (đã rollback). Lệnh artisan cũng được log (actor "Lệnh: x"), trừ migrate/db:/activity:/queue:/schedule:/cache…
- Update/delete hàng loạt qua query builder KHÔNG có diff, chỉ có dòng request.
- Quyền `activity.view` (super_admin+admin), trang `/nhat-ky-thao-tac` (Blade), tab Nhật ký ở /system-settings (`sys.activity_enabled`, `sys.activity_retention_days` mặc định 365), `activity:prune` 03:00.

**Why:** sự cố 29C-18195 bị đổi sang Xe ngoài không truy được ai làm ([[data-safety-reconcile]]).
