{{-- Font dùng chung toàn dự án — xem partials/_font.blade.php --}}
@verbatim
<style>
:root {
    --accent: #2a6fdb;
    --accent-weak: #eaf1fc;
    --accent-weak-2: #f4f8fe;
    --bg: #eef0f3;
    --panel: #ffffff;
    --ink: #16191d;
    --ink-2: #4a5159;
    --ink-3: #868d96;
    --ink-4: #8a9199;
    --line: #e4e7eb;
    --line-2: #eef0f3;
    --danger: #d64545;
    --good: #1f8a5b;
    --good-weak: #e7f5ee;
    --warn: #b06d00;
    --warn-weak: #fbf1de;
    --radius: 14px;
    --shadow-modal: 0 1px 2px rgba(16,19,23,.04), 0 24px 50px -16px rgba(16,19,23,.26), 0 8px 24px -12px rgba(16,19,23,.16);
    font-synthesis: none;
  }
  * { box-sizing: border-box; }
  html, body { height: 100%; }
  body {
    margin: 0;
    font-family: var(--app-font, "Inter", system-ui, -apple-system, sans-serif);
    background: var(--bg);
    color: var(--ink);
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
  }
  #root { height: 100%; }
  input, select, button, textarea { font: inherit; color: inherit; }
  ::selection { background: var(--accent-weak); }
  .tnum { font-variant-numeric: tabular-nums; }
  ::-webkit-scrollbar { height: 11px; width: 11px; }
  ::-webkit-scrollbar-thumb { background: #d0d4da; border-radius: 99px; border: 3px solid transparent; background-clip: padding-box; }
  ::-webkit-scrollbar-thumb:hover { background: #bcc2c9; background-clip: padding-box; }
  @media print {
    body * { visibility: hidden !important; }
    .ke-print, .ke-print * { visibility: visible !important; }
    .ke-print { position: absolute !important; left: 0; top: 0; width: 100%; box-shadow: none !important; max-height: none !important; border-radius: 0 !important; }
    .ke-noprint { display: none !important; }
    .ke-printonly { display: inline !important; }
    .ke-zebra > td { background: #fff !important; }   /* in: bỏ nền xen kẽ cho sạch */
  }
  /* Bảng kê: phân biệt rõ TỪNG LÔ — nền xen kẽ + vạch ngăn đậm cuối mỗi lô */
  .ke-zebra > td { background: #f6f8fb; }
  .ke-lo-end > td { border-bottom: 1.5px solid var(--line) !important; }
  @keyframes fadeIn { from { opacity: 0 } to { opacity: 1 } }
  @keyframes trk-spin { from { transform: rotate(0deg) } to { transform: rotate(360deg) } }
  /* Input ngày/giờ Flatpickr (DateField/DTField) — đồng bộ với các field khác */
  .trk-fp {
    width: 100%; padding: 8px 11px; font-size: 13.5px; box-sizing: border-box;
    border: 1px solid var(--line); border-radius: 9px; background: #fff;
    color: var(--ink-2); outline: none; cursor: pointer;
  }
  .trk-fp:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-weak); }
  .trk-fp::placeholder { color: var(--ink-4); }
  /* Lịch Flatpickr phải nổi trên modal lô hàng (z-index modal = 1100) */
  .flatpickr-calendar { z-index: 1200 !important; }
/* tích hợp layout app */
main.app-body{padding:0 !important;}
#trk-root{overflow:hidden;background:var(--bg);}
body{ -webkit-text-size-adjust:100%; }

/* ============================ MOBILE (≤640px) ============================
   Trang React dùng inline-style nên không thể media-query từng phần tử;
   ở đây ép các quy tắc nền tảng (đè inline nhờ !important) cho toàn bộ
   feature page. KHÔNG ảnh hưởng trang Yêu cầu chi (blade riêng, không nạp file này). */
@media (max-width: 640px) {
  /* iOS Safari tự zoom khi focus input <16px → ép tối thiểu 16px */
  #trk-root input:not([type=checkbox]):not([type=radio]),
  #trk-root select,
  #trk-root textarea { font-size: 16px !important; }

  /* Bảng rộng: luôn cho cuộn ngang mượt, tránh "kẹt" nội dung */
  #trk-root table { -webkit-overflow-scrolling: touch; }

  /* Thanh cuộn mảnh hơn cho cảm ứng */
  ::-webkit-scrollbar { height: 7px; width: 7px; }

  /* Header trang chi tiết (back + tiêu đề + nút) — xuống dòng gọn:
     hàng 1 = back + tiêu đề (.trk-head-lead chiếm full), hàng 2 = các nút. */
  .trk-head { flex-wrap: wrap !important; height: auto !important; min-height: 56px; row-gap: 10px; padding-top: 9px; padding-bottom: 9px; align-items: flex-start !important; }
  .trk-head-lead { flex: 1 1 100% !important; }
  /* Nút trong header chi tiết: cho phép co lại, không tràn */
  .trk-head > button, .trk-head > a { flex: 0 1 auto; }
}
</style>
@endverbatim
