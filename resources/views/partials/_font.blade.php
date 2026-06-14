{{--
  FONT TOÀN DỰ ÁN — điểm cấu hình DUY NHẤT.
  Muốn đổi font sau này: chỉ sửa 1) link tải bên dưới, 2) giá trị biến --app-font.
  Tất cả admin + trang public include file này nên đổi 1 chỗ là áp dụng toàn bộ.

  Đang dùng: Inter (Google Fonts) — hỗ trợ tiếng Việt đầy đủ.
--}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --app-font: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    /* Ép Bootstrap dùng chung font (các component BS đọc biến này) */
    --bs-body-font-family: var(--app-font);
    /* Ép Tailwind (app.css) dùng chung font */
    --font-sans: var(--app-font);
  }
  html, body,
  button, input, select, textarea, optgroup,
  .modal, .dropdown-menu, .tooltip, .popover, .toast {
    font-family: var(--app-font);
  }
</style>
