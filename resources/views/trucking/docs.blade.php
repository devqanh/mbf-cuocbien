<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tài liệu cột &amp; công thức — Trucking</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    {{-- Font toàn dự án (Inter) — đổi font ở partials/_font.blade.php --}}
    @include('partials._font')
    <style>
        body { background:#eef1f6; color:#1c273c; font-family: var(--app-font); }
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

        <div class="doc-card mt-4" id="notesSection">
            <h2 style="font-size:18px; font-weight:800; color:#0153a9; margin:0 0 12px;">
                <i class="bi bi-chat-left-text me-1"></i> Góp ý / Ghi chú kế toán
            </h2>
            <p style="font-size:13px; color:#7987a1; margin-bottom:10px;">
                Kế toán ghi chú, góp ý về các cột / công thức tại đây. Nội dung sẽ được lưu lại cho mọi người cùng xem.
            </p>
            <textarea id="docNotes" rows="12" style="width:100%; padding:12px; border:1px solid #d5dae3; border-radius:10px; font-size:14px; font-family:inherit; resize:vertical; line-height:1.7;"
                      placeholder="Nhập ghi chú, góp ý tại đây...">{{ $notes }}</textarea>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <span id="noteStatus" style="font-size:12px; color:#7987a1;"></span>
                <button id="btnSaveNotes" class="btn btn-primary" onclick="saveNotes()">
                    <i class="bi bi-save me-1"></i> Lưu ghi chú
                </button>
            </div>
        </div>
    </div>

    <script>
        async function saveNotes() {
            const btn    = document.getElementById('btnSaveNotes');
            const status = document.getElementById('noteStatus');
            const notes  = document.getElementById('docNotes').value;

            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Đang lưu...';

            try {
                const res = await fetch('{{ route("trucking.saveNotes") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ notes }),
                });
                const json = await res.json();
                if (json.ok) {
                    status.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Đã lưu lúc ' + new Date().toLocaleTimeString('vi-VN');
                } else {
                    status.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i> Lưu thất bại';
                }
            } catch (e) {
                status.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i> Lỗi: ' + e.message;
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-save me-1"></i> Lưu ghi chú';
        }
    </script>
</body>
</html>
