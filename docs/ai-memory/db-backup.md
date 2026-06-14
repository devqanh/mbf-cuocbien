---
name: db-backup
description: Command db:backup sao lưu MySQL + lập lịch + báo cáo ở System Settings; cần cron schedule:run trên server
metadata: 
  node_type: memory
  type: project
  originSessionId: abfdb9c6-78f0-4c41-acce-4ab00891767b
---

Sao lưu CSDL (tạo 2026-06-14 theo yêu cầu user):

- Command `php artisan db:backup` ([app/Console/Commands/BackupDatabase.php]): mysqldump | gzip ra `storage/app/backups/*.sql.gz`, **xoay vòng giữ 15 bản** (`--keep=15`), ghi báo cáo lần chạy vào `TruckingSetting` key `sys.backup_last_run` (JSON: at/ok/file/bytes/ms/error). Password truyền qua env `MYSQL_PWD` (không lộ trong `ps`).
- **Lập lịch** trong `routes/console.php` (Laravel 12): `Schedule::command('db:backup')->dailyAt('02:00')`. ⚠️ Cần cron trên server chạy `php artisan schedule:run` mỗi phút, nếu không lịch KHÔNG tự chạy (nút "Sao lưu ngay" vẫn chạy thủ công được).
- **Trang System Settings** (/system-settings, [[spend-request-flow]] dùng chung quyền `system.settings`): card "Sao lưu CSDL" hiện trạng thái lần chạy gần nhất + danh sách 15 file (tải về) + nút "Sao lưu ngay". Controller: `SystemSettingController@backupNow` (Artisan::call đồng bộ) và `@downloadBackup` (chặn path traversal, chỉ `*.sql.gz`).
- `storage/app/backups` đã được gitignore — backup không lọt vào repo.
