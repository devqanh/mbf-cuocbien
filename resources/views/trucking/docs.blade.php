@extends('layouts.app')

@section('title', 'Tài liệu cột & công thức — Trucking')

@push('styles')
<style>
    .doc-wrap { max-width: 1000px; margin: 0 auto; }
    .doc-card { background:#fff; border:1px solid var(--azia-border); border-radius:14px; padding:28px 34px; box-shadow:0 4px 18px rgba(28,39,60,.06); }
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
    @media print { .app-header, .doc-actions { display:none !important; } .doc-card { box-shadow:none; border:none; } }
</style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <h1><i class="bi bi-file-earmark-text me-1" style="color: var(--azia-primary)"></i> TÀI LIỆU CỘT &amp; CÔNG THỨC</h1>
            <nav class="breadcrumb mt-1">
                <a href="{{ route('trucking.index') }}">Trucking</a>
                <span class="mx-2">/</span>
                <span>Tài liệu nghiệp vụ</span>
            </nav>
        </div>
        <div class="d-flex gap-2 doc-actions">
            <a class="btn btn-outline-secondary" href="{{ route('trucking.index') }}"><i class="bi bi-arrow-left me-1"></i> Về bảng</a>
            <button class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i> In</button>
            <a class="btn btn-primary" href="{{ route('trucking.docsDownload') }}"><i class="bi bi-download me-1"></i> Tải file .md</a>
        </div>
    </div>

    <div class="doc-wrap">
        <div class="doc-card">
            <div class="doc-body">{!! $html !!}</div>
        </div>
    </div>
@endsection
