@extends('layouts.app')
@section('title', 'Link kế hoạch — Trucking')

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
    create: '{{ route("trucking2.plan.create") }}',
    base:   '{{ url("trucking-v2/plan-links") }}/',   // + {id}/toggle (PUT) | + {id} (DELETE)
    publicBase: '{{ url("ke-hoach") }}/',             // + {token}
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
@vite('resources/js/trucking2/pages/ke-hoach.jsx')
@endpush
