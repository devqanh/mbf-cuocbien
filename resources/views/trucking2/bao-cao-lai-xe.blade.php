@extends('layouts.app')
@section('title', 'Báo cáo lái xe — Trucking')

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
    data:     '{{ route("trucking2.driverReport.data") }}',
    loTrinh:  '{{ route("trucking2.loTrinh") }}',              // ?date=&bks= → mở đúng ngày, cuộn tới xe
    vehicles: '{{ url("trucking-v2/cai-dat") }}#vehicles',     // gắn lái xe mặc định cho xe
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
@vite('resources/js/trucking2/pages/bao-cao-lai-xe.jsx')
@endpush
