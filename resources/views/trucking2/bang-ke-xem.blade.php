@extends('layouts.app')
@section('title', 'Xem bảng kê — Trucking')

@push('styles')
@include('trucking2.partials._styles')
@endpush

@section('content')
<div id="trk-root"></div>
<script>
window.__TRK = {
  csrf: '{{ csrf_token() }}',
  canEdit: {{ $canEdit ? 'true' : 'false' }},
  canDelete: {{ $canDelete ? 'true' : 'false' }},
  routes: {
    list: '{{ route("trucking2.statements") }}',
    statement: '{{ url("trucking-v2/statements") }}/',
    base: '{{ url("trucking-v2/bang-ke") }}/',
    loHang: '{{ url("trucking-v2/lo-hang") }}',
    pendingLots: '{{ route("trucking2.statements.pendingLots") }}',   // lô chưa ra trong kỳ + chưa có bảng kê (thêm tay để thu trước)
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
@vite('resources/js/trucking2/pages/bang-ke-xem.jsx')
@endpush
