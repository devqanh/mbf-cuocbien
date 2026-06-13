@extends('layouts.app')
@section('title', 'Quản lý xe — Trucking')

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
    fleet: '{{ url("trucking-v2/quan-ly-xe") }}/',   // + {id}/data (GET) | + {id} (PUT)
    costItem: '{{ route("trucking2.fleet.costItem") }}',
    spendRequest: '{{ route("trucking2.spendRequest") }}',   // link public gửi yêu cầu chi
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
@vite('resources/js/trucking2/pages/quan-ly-xe.jsx')
@endpush
