<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet" />
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
    font-family: "Be Vietnam Pro", system-ui, -apple-system, sans-serif;
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
  }
  @keyframes fadeIn { from { opacity: 0 } to { opacity: 1 } }
  @keyframes trk-spin { from { transform: rotate(0deg) } to { transform: rotate(360deg) } }
/* tích hợp layout app */
main.app-body{padding:0 !important;}
#trk-root{overflow:hidden;background:var(--bg);}
</style>
@endverbatim
