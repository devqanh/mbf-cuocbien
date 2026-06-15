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
    warehouses:   '{{ route("trucking2.tracking.warehouses") }}',
    warehouseGeo: '{{ route("trucking2.tracking.warehouseGeo") }}',
    visits:       '{{ route("trucking2.tracking.visits") }}',
    visitsPage:   '{{ route("trucking2.tracking.visitsPage") }}',
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
@vite('resources/js/trucking2/pages/theo-doi-xe.jsx')
@endpush
