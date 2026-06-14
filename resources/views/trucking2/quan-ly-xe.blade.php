@extends('layouts.app')
@section('title', 'Quản lý xe — Trucking')

@push('styles')
@include('trucking2.partials._styles')
<style>
  /* Highlight phiếu chi khi deep-link từ thông báo (#<id>/cost/<costId>) */
  @keyframes trkHlPulse { 0% { background: rgba(255,184,34,.5); } 100% { background: transparent; } }
  .trk-row-hl > td { animation: trkHlPulse 2.6s ease-out; }
</style>
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
    cancelCost: '{{ url("trucking-v2/quan-ly-xe/cost") }}/',   // + {costId}/cancel (PUT)
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
