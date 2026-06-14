---
name: memory-in-repo
description: Memory dự án được mirror vào repo (docs/ai-memory) để git + xem lại
metadata:
  type: feedback
---

User muốn **memory nằm trong repo** để commit/push lên git, xem lại sau.

**How to apply:** thư mục memory chuẩn (harness đọc/ghi) `…/.claude/projects/c--laragon-www-cuocbien/memory/` đã được trỏ **junction** sang **`c:\laragon\www\cuocbien\docs\ai-memory`** (trong repo). Nên ghi memory như bình thường ở đường dẫn .claude → file thực nằm trong repo, git tự thấy.

Nếu junction mất (clone máy khác / bị xoá): tạo lại bằng PowerShell `New-Item -ItemType Junction -Path "<claude-memory>" -Target "c:\laragon\www\cuocbien\docs\ai-memory"`, hoặc tối thiểu copy `docs/ai-memory/*` về .claude memory. KHÔNG để 2 nơi lệch nhau.

**Why:** memory là tài liệu kiến trúc/quyết định dự án — cần version-control cùng code để cả team/AI sau xem lại.
