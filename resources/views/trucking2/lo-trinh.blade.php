@extends('layouts.app')
@section('title', 'Lộ trình lái xe — Trucking')

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
    data: '{{ route("trucking2.loTrinh.data") }}',
    savePay: '{{ route("trucking2.loTrinh.savePay") }}',
    freeze: '{{ route("trucking2.loTrinh.freeze") }}',
    shipment: '{{ url("trucking-v2/lo-hang") }}',
    routeFees: '{{ url("trucking-v2/cai-dat") }}#routeFees',
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
@vite('resources/js/trucking2/pages/lo-trinh.jsx')
@endpush
