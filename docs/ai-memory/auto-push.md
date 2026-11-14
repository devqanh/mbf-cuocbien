---
name: auto-push
description: "Làm xong + kiểm tra xong mỗi thay đổi thì tự commit và push lên origin/main, không hỏi lại"
metadata:
  node_type: memory
  type: feedback
  originSessionId: e4e2c5db-8a59-4fc6-88ee-68cb3b2cba14
  modified: 2026-11-14T08:12:34.701Z
---

Xong mỗi yêu cầu (đã build + kiểm tra) thì TỰ commit (conventional commit, không nhắc AI) và `git push origin main` — không chờ user bảo. User nói "tự động push code" (2026-11-14) sau nhiều lần mình hỏi "bạn bảo thì mình commit và đẩy lên".

**Why:** user deploy liên tục qua aaPanel (pull → migrate → build), hỏi lại mỗi lần làm chậm. Repo còn có commit "update code" do user tự đẩy xen giữa → luôn `git fetch` + xem `git status` trước khi commit.

**How to apply:** chỉ add đúng file mình sửa (kèm docs/ai-memory nếu sửa memory — xem [[memory-in-repo]]); không commit .env/secret; nếu push bị từ chối (remote mới hơn) thì pull --rebase rồi push, có xung đột thì dừng và báo. Báo lại hash commit trong câu trả lời. Liên quan [[deploy-aapanel-build]].
