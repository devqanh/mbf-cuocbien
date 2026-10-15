@extends('layouts.app')
@section('title', 'Nhật ký đổ dầu — Trucking')

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
    data: '{{ route("trucking2.fuelLog.data") }}',
    save: '{{ route("trucking2.fuelLog.save") }}',
    base: '{{ url("trucking-v2/nhat-ky-do-dau") }}',
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
@vite('resources/js/trucking2/pages/nhat-ky-do-dau.jsx')
@endpush
