@extends('layouts.app')
@section('title', 'Theo dõi xe — Trucking')

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
    positions: '{{ route("trucking2.tracking.positions") }}',
    settings:  '{{ route("system.settings") }}',
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
@vite('resources/js/trucking2/pages/theo-doi-xe.jsx')
@endpush
