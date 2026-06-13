@extends('layouts.app')
@section('title', 'Bảng giá — Trucking')

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
    customers: '{{ route("trucking2.customers.save") }}',
    priceImport: '{{ route("trucking2.priceImport") }}',
    customerPrices: '{{ route("trucking2.customerPrices") }}',
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
@vite('resources/js/trucking2/pages/bang-gia.jsx')
@endpush
