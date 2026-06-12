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
  },
  boot: @json($boot),
};
</script>
@endsection

@push('scripts')
@include('trucking2.partials._runtime')
@verbatim
<script type="text/babel" data-presets="react">
(() => {
const { useState } = React;
const { KePage } = window.__ui;

function StatementsApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const [ke] = useState(B.ke || []);

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <KePage ke={ke} onNew={() => { window.location.href = ROUTES.create; }} onOpen={(st) => { window.location.href = ROUTES.view + st.id; }} />
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("trk-root")).render(<StatementsApp />);
})();
</script>
@endverbatim
@endpush
