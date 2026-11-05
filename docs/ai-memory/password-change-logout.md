---
name: password-change-logout
description: Đổi mật khẩu tự đăng xuất mọi phiên cũ — AuthenticateSession (nhóm web) + hook User::updated; session/cookie lưu HMAC hashPasswordForCookie
metadata:
  node_type: memory
  type: project
  originSessionId: 7f857d6f-8493-424f-b819-c203d20fc1ce
  modified: 2026-11-05T06:50:23.322Z
---

Đổi mật khẩu (admin ở /users hoặc chính chủ ở /profile) tự đăng xuất mọi thiết bị khác (làm ngày 2026-11-05).

- Chốt chặn chính là `AuthenticateSession` gắn vào nhóm web ở bootstrap/app.php. Nó chạy với mọi session driver (local đang dùng `file`, prod dùng `database`) và đá luôn cookie "ghi nhớ" mang mã băm cũ.
- Hook `User::booted` → `updated` khi `wasChanged('password')` thì gọi `logoutSessionsAfterPasswordChange()`. Hàm này xóa dòng `sessions` (chỉ khi driver là database). Nếu chính chủ tự đổi thì giữ phiên hiện tại, ghi mã băm mới vào session và cấp lại cookie ghi nhớ.
- Laravel 12 lưu `password_hash_web` và phần thứ ba của cookie recaller dưới dạng HMAC `Auth::guard()->hashPasswordForCookie(...)`, KHÔNG phải mã băm bcrypt thô.

- Mật khẩu mạnh: `Password::defaults()` ở AppServiceProvider (≥8 ký tự, hoa+thường, số, ký tự đặc biệt) dùng cho tạo/sửa thành viên ở /users và đổi mật khẩu ở /profile, kèm `confirmed`. Gợi ý client ở partials/_password-rules.blade.php phải khớp quy tắc này. Ô đăng nhập vẫn để `min:6` để tài khoản cũ còn đăng nhập được.

**Why:** người dùng yêu cầu đổi mật khẩu thì phải tự thoát toàn bộ phiên cũ.
**How to apply:** chỗ nào khác ghi `password` cũng được áp dụng tự động qua hook; đừng dùng `saveQuietly` khi đổi mật khẩu vì sẽ bỏ qua hook. Test feature cần DB MySQL riêng vì migration không chạy trên sqlite (xem [[verify-no-destructive-save]]).
