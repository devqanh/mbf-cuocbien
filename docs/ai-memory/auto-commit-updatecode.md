---
name: auto-commit-updatecode
description: Repo tự động commit thay đổi (commit tên "updatecode") nên git status thường sạch ngay sau khi sửa file
metadata:
  type: project
---

Máy dev này có cơ chế tự động commit: ngay sau khi sửa/tạo file, thay đổi được commit vào HEAD với message kiểu `updatecode`/`update`. Hệ quả: `git status` báo **clean** dù vừa sửa nhiều file, và `git diff` không thấy gì vì nội dung mới đã nằm trong HEAD.

**Why:** Tránh hoang mang tưởng edit không lưu được khi thấy working tree sạch.

**How to apply:** Đừng dựa vào `git status`/`git diff HEAD` để xác minh edit; verify bằng grep nội dung trong file hoặc `git show HEAD:<file>`. Không cần tự commit lại. Nếu cần xem thay đổi vừa làm, so với commit trước HEAD.
