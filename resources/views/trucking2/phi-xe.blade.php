@extends('layouts.app')
@section('title', 'Phí xe nội bộ — Trucking')

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
    create: '{{ route("trucking2.tripCost.create") }}',
    view: '{{ url("trucking-v2/phi-xe") }}/',
    batch: '{{ url("trucking-v2/trip-costs") }}/',
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
@vite('resources/js/trucking2/pages/phi-xe.jsx')
@endpush
