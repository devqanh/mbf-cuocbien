@extends('layouts.app')
@section('title', 'Cài đặt — Trucking')

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
    catalog: '{{ url("trucking-v2/catalog") }}/',
    customers: '{{ route("trucking2.customers.save") }}',
    customerRename: '{{ route("trucking2.customerRename") }}',
    vehicles: '{{ route("trucking2.vehicles.save") }}',
    settings: '{{ route("trucking2.settings.save") }}',
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
@vite('resources/js/trucking2/pages/cai-dat.jsx')
@endpush
