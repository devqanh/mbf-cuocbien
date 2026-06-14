---
name: font-global-config
description: Font toàn dự án cấu hình tập trung ở partials/_font.blade.php (hiện dùng Inter)
metadata:
  type: project
---

Font toàn dự án được tập trung tại `resources/views/partials/_font.blade.php` — chứa link tải font (Google Fonts) + biến CSS `--app-font` + override `--bs-body-font-family` (Bootstrap) và `--font-sans` (Tailwind app.css). **Đổi font = sửa DUY NHẤT file này** (link + biến).

Partial này được `@include('partials._font')` ở: `layouts/app.blade.php` (phủ toàn bộ admin), `trucking2/yeu-cau-chi.blade.php` (public), `trucking/docs.blade.php` (/tailieu). Trucking v2 `partials/_styles.blade.php` không còn tự load font — `body` dùng `var(--app-font)`.

Hiện dùng **Inter** (Google Fonts, hỗ trợ tiếng Việt). Đã thử qua SF Pro Display (font Apple — bỏ), Plus Jakarta Sans, Be Vietnam Pro trước khi chốt Inter. Còn `welcome.blade.php` (landing mặc định Laravel, gần như không dùng) vẫn để Instrument Sans/bunny.net.

**How to apply:** đổi font chỉ sửa `_font.blade.php` rồi `php artisan view:clear` (Blade + CSS inline, KHÔNG cần `npm run build`).
