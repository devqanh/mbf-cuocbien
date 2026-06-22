@extends('layouts.app')
@section('title', 'Quản lý chi phí — Trucking')

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
    list:       '{{ route("trucking2.costManagement.list") }}',
    costUpdate: '{{ url("trucking-v2/quan-ly-xe/cost") }}/',   // + {hashid} (PUT)
    costCancel: '{{ url("trucking-v2/quan-ly-xe/cost") }}/',   // + {hashid}/cancel (PUT)
    costPhoto:  '{{ url("trucking-v2/quan-ly-xe") }}/',        // + {vehicleHashid}/cost-photo (POST)
    fleet:      '{{ url("trucking-v2/quan-ly-xe") }}',         // deep-link xe: #<hashid>/cost
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
@vite('resources/js/trucking2/pages/quan-ly-chi-phi.jsx')
@endpush
