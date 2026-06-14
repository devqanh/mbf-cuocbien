---
name: two-factor-auth
description: 2FA/TOTP đã tích hợp — tự viết thuần PHP, KHÔNG dùng package; QR render client-side
metadata:
  type: project
---

2FA (Google Authenticator) đã tích hợp vào app (profile bật/tắt, login challenge, admin reset ở /users).

**Quyết định kiến trúc (giữ nguyên, đừng đổi):**
- Engine TOTP RFC 6238 **tự viết thuần PHP** ở `app/Services/TwoFactorService.php` — KHÔNG cài `pragmarx/google2fa` hay package QR. Lý do: không thêm dependency composer, dễ audit, không lo cài trên production. Đã verify khớp test vector RFC 6238.
- QR render **client-side** bằng CDN `qrcode@1.5.3` (giống cách app dùng Bootstrap qua CDN), không sinh QR phía server.
- Cột users: `two_factor_secret` (encrypted), `two_factor_recovery_codes` (encrypted:array), `two_factor_confirmed_at`. "Đã bật" = có secret VÀ confirmed_at.
- Login: `LoginController` dùng `Auth::validate` (không tạo phiên ngay) → nếu bật 2FA thì lưu `login.2fa.id`/`login.2fa.remember` vào session, chuyển sang `TwoFactorChallengeController`. Hoàn tất qua `completeLogin()`.
- Recovery codes: dùng-1-lần, so khớp timing-safe; challenge có rate-limit 6 lần/phút.

**Why:** user muốn 2FA "chuyên nghiệp" nhưng app nhạy với dependency/độ phức tạp.
**How to apply:** sửa 2FA thì sửa `TwoFactorService` + các controller, đừng thay bằng package. Liên quan [[trucking-perf-lazy-load]].
