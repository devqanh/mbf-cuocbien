<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tài liệu cột &amp; công thức — Trucking</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body { background:#eef1f6; color:#1c273c; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .doc-topbar { background: linear-gradient(135deg,#0153a9,#013f80); color:#fff; padding:14px 0; margin-bottom:24px; }
        .doc-topbar .title { font-size:16px; font-weight:800; letter-spacing:.3px; }
        .doc-wrap { max-width: 1000px; margin: 0 auto; padding: 0 16px 60px; }
        .doc-card { background:#fff; border:1px solid #e1e6f1; border-radius:14px; padding:28px 34px; box-shadow:0 4px 18px rgba(28,39,60,.06); }
        .doc-body h1 { font-size:24px; font-weight:800; color:#013f80; margin:0 0 14px; }
        .doc-body h2 { font-size:18px; font-weight:800; color:#0153a9; margin:26px 0 10px; padding:8px 12px; background:#eef3fb; border-radius:8px; }
        .doc-body h3 { font-size:14px; font-weight:700; color:#1c273c; margin:18px 0 8px; border-left:4px solid #24d39f; padding-left:10px; }
        .doc-body blockquote { background:#f0fbf6; border-left:4px solid #24d39f; margin:0 0 16px; padding:10px 16px; color:#0f5132; border-radius:0 8px 8px 0; font-size:13.5px; }
        .doc-body blockquote p { margin:4px 0; }
        .doc-body table { width:100%; border-collapse:collapse; margin:6px 0 14px; font-size:13px; }
        .doc-body th, .doc-body td { border:1px solid #e1e6f1; padding:7px 10px; text-align:left; vertical-align:top; }
        .doc-body th { background:#f4f6fb; font-weight:700; }
        .doc-body tr:nth-child(even) td { background:#fafbfd; }
        .doc-body hr { border:none; border-top:1px dashed #d5dae3; margin:22px 0; }
        .doc-body code { background:#f4f6fb; padding:1px 6px; border-radius:4px; font-size:12px; color:#c0392b; }
        .doc-body strong { color:#1c273c; }
        .doc-body ul { margin:6px 0 14px; padding-left:20px; }
        .doc-body li { margin:4px 0; font-size:13.5px; }
        @media print { .doc-topbar { display:none !important; } .doc-card { box-shadow:none; border:none; padding:0; } body { background:#fff; } }
    </style>
</head>
<body>
    <div class="doc-topbar">
        <div class="doc-wrap d-flex align-items-center justify-content-between" style="padding-bottom:0;">
            <span class="title"><i class="bi bi-file-earmark-text me-1"></i> TÀI LIỆU CỘT &amp; CÔNG THỨC — TRUCKING</span>
            <span class="d-flex gap-2">
                <button class="btn btn-sm btn-light" onclick="window.print()"><i class="bi bi-printer me-1"></i> In</button>
                <a class="btn btn-sm btn-light" href="{{ route('trucking.docsDownload') }}"><i class="bi bi-download me-1"></i> Tải .md</a>
            </span>
        </div>
    </div>

    <div class="doc-wrap">
        <div class="doc-card">
            <div class="doc-body">{!! $html !!}</div>
        </div>
    </div>
</body>
</html>
