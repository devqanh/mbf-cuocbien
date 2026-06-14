<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Cập nhật kế hoạch xe — MBF</title>
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  {{-- Font toàn dự án (Inter) --}}
  @include('partials._font')
  <style>
    *{box-sizing:border-box} html,body{margin:0;padding:0}
    body{font-family:var(--app-font);background:#eef1f6;color:#1b2330;-webkit-text-size-adjust:100%}
    #trk-root{min-height:100svh}
    @keyframes trk-spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
  </style>
</head>
<body>
  <div id="trk-root"></div>
  <script>
    window.__TRK = {
      csrf: '{{ csrf_token() }}',
      routes: {
        base: '{{ url('ke-hoach/'.$token) }}/',   // + {shipHashid}/update | + {shipHashid}/photo/{att}
        data: '{{ url('ke-hoach/'.$token.'/data') }}',
      },
      boot: @json($boot),
    };
  </script>
  @vite('resources/js/trucking2/pages/ke-hoach-public.jsx')
</body>
</html>
