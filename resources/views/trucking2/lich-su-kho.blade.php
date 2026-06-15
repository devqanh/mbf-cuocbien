@extends('layouts.app')
@section('title', 'Lịch sử đến kho — Trucking')

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
    visits: '{{ route("trucking2.tracking.visits") }}',
    back:   '{{ route("trucking2.tracking") }}',
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
@vite('resources/js/trucking2/pages/lich-su-kho.jsx')
@endpush
