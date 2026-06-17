---
name: db-pull
description: lệnh db:pull kéo DB từ server remote về MySQL local để dev/test
metadata:
  type: project
---

`php artisan db:pull [--force]` kéo toàn bộ DB từ server remote (mặc định server dev cuocbien_dev) về MySQL local — ghi đè local theo connection `mysql` trong `.env`. Tự sao lưu DB local trước vào `storage/app/backups/LOCAL_*.sql.gz`. Override remote qua `--rhost/--rport/--rdb/--ruser/--rpass`; `--no-local-backup`, `--keep-dump`. App\Console\Commands\PullDatabase.php (cùng phong cách [[db-backup]]).

Hai gotcha đã xử lý:
- mysqldump 8.0 nối server thiếu COLUMN_STATISTICS → lỗi bị `| gzip` che, dump hỏng còn vài KB. PHẢI có `--column-statistics=0`.
- Nếu command đọc sai tên DB local (ví dụ nạp nhầm DB cũ) là do Laravel cache config → `php artisan config:clear`.
