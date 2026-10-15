@extends('layouts.app')
@section('title', 'Kỳ lương lái xe — Trucking')

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
    list: '{{ route("trucking2.tripCost") }}',
    update: '{{ url("trucking-v2/trip-costs") }}/',
    destroy: '{{ url("trucking-v2/trip-costs") }}/',
    recompute: '{{ url("trucking-v2/phi-xe") }}/',
    loTrinh: '{{ route("trucking2.loTrinh") }}',
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
@vite('resources/js/trucking2/pages/phi-xe-xem.jsx')
@endpush
