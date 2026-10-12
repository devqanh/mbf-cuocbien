@extends('layouts.app')
@section('title', 'Bảng kê — Trucking')

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
    create: '{{ route("trucking2.statements.create") }}',
    view: '{{ url("trucking-v2/bang-ke") }}/',
    statement: '{{ url("trucking-v2/statements") }}/',
    drift: '{{ route("trucking2.statements.drift") }}',
    customers: '{{ route("trucking2.statements.customers") }}',   // khách có lô ra trong kỳ
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
@vite('resources/js/trucking2/pages/bang-ke.jsx')
@endpush
