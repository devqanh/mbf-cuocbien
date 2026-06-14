<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Gửi yêu cầu chi — MBF</title>
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  {{-- Font toàn dự án (Inter) — đổi font ở partials/_font.blade.php --}}
  @include('partials._font')
  <style>
    *{box-sizing:border-box} html,body{margin:0;padding:0}
    body{font-family:var(--app-font);background:#eef1f6;color:#1b2330;-webkit-text-size-adjust:100%}
    #trk-root{min-height:100vh}
    @keyframes trk-spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
  </style>
</head>
<body>
  <div id="trk-root"></div>
  <script>
    window.__TRK = {
      csrf: '{{ csrf_token() }}',
      routes: {
        submit: '{{ route("trucking2.spendRequest.submit") }}',
        login:  '{{ route("trucking2.spendRequest.login") }}',
        logout: '{{ route("trucking2.spendRequest.logout") }}',
        history:'{{ route("trucking2.spendRequest.history") }}',
        cancel: '{{ url("yeu-cau-chi") }}/',
      },
      boot: @json($boot),
    };
  </script>
  @vite('resources/js/trucking2/pages/yeu-cau-chi.jsx')
</body>
</html>
