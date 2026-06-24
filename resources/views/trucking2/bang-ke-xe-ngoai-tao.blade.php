@extends('layouts.app')
@section('title', 'Tạo bảng kê xe ngoài — Trucking')

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
    extStatementStore: '{{ route("trucking2.extStatements.store") }}',
    extStatements: '{{ route("trucking2.extStatements") }}',
    extStatementCandidates: '{{ route("trucking2.extStatements.candidates") }}',
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
@vite('resources/js/trucking2/pages/bang-ke-xe-ngoai-tao.jsx')
@endpush
