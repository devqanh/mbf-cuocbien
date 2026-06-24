@extends('layouts.app')
@section('title', 'Bảng kê xe ngoài — Trucking')

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
    create: '{{ route("trucking2.extStatements.create") }}',
    view: '{{ url("trucking-v2/bang-ke-xe-ngoai") }}/',
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
@vite('resources/js/trucking2/pages/bang-ke-xe-ngoai.jsx')
@endpush
