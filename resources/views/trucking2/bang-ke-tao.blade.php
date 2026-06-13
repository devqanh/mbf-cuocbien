@extends('layouts.app')
@section('title', 'Tạo bảng kê — Trucking')

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
    statementStore: '{{ route("trucking2.statements.store") }}',
    statements: '{{ route("trucking2.statements") }}',
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
@vite('resources/js/trucking2/pages/bang-ke-tao.jsx')
@endpush
