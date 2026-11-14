@extends('layouts.app')
@section('title', 'Báo cáo doanh thu phải thu — Trucking')

@push('styles')
@include('trucking2.partials._styles')
@endpush

@section('content')
<div id="trk-root"></div>
<script>
window.__TRK = {
  csrf: '{{ csrf_token() }}',
  canEdit: {{ $canEdit ? 'true' : 'false' }},
  routes: {
    data:      '{{ route("trucking2.report.data") }}',
    statement: '{{ url("trucking-v2/bang-ke") }}/',   // + hashid → mở bảng kê thu
  },
  boot: @json($boot),
};
</script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
@endsection

@push('scripts')
@vite('resources/js/trucking2/pages/bao-cao.jsx')
@endpush
