@extends('layouts.app')
@section('title', 'Báo cáo công nợ xe ngoài — Trucking')

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
    data:      '{{ route("trucking2.extPayableReport.data") }}',
    statement: '{{ url("trucking-v2/bang-ke-xe-ngoai") }}/',   // + hashid → mở bảng kê xe ngoài
  },
  boot: @json($boot),
};
</script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
@endsection

@push('scripts')
@vite('resources/js/trucking2/pages/bao-cao-xe-ngoai.jsx')
@endpush
