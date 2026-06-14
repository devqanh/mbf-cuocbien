---
name: no-seed-demo
description: Không tự chạy seed demo cho Trucking; user tự test thủ công
metadata: 
  node_type: memory
  type: feedback
  originSessionId: e45e4c6a-42d4-4a22-83c9-05cf2e456c1e
---

Không tự chạy `trucking:seed-demo` (hay tạo dữ liệu demo) sau khi sửa code. User sẽ tự test thủ công.

**Why:** User nói rõ "từ sau không tạo demo tôi sẽ tự test" — không muốn dữ liệu demo ghi đè/làm bẩn DB họ đang test.

**How to apply:** Verify thay đổi bằng cách đọc code, render check, hoặc unit-test logic ở mức service (tinker) trên dữ liệu CÓ SẴN — đừng wipe/seed. Lệnh `php artisan view:clear` vẫn ok. Liên quan [[trucking-redesign]].
