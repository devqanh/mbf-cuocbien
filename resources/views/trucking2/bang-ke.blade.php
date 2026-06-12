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
const { useState, useRef } = React;
const { KePage, SavedStatementModal } = window.__ui;

function StatementsApp() {
  const T = window.__TRK || {}; const ROUTES = T.routes || {}; const B = T.boot || {};
  const api = (method, url, body) => fetch(url, { method, headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": T.csrf }, body: body ? JSON.stringify(body) : undefined }).then((r) => r.json());

  const [ke, setKe] = useState(B.ke || []);
  const [viewKe, setViewKe] = useState(null);

  const stTimer = useRef({});
  const saveStatementDebounced = (ns) => { clearTimeout(stTimer.current[ns.id]); stTimer.current[ns.id] = setTimeout(() => api("PUT", ROUTES.statement + ns.id, { statement: ns }), 600); };

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", background: "var(--bg)" }}>
      <KePage ke={ke} onNew={() => { window.location.href = ROUTES.create; }} onOpen={(st) => setViewKe(st)} />
      {viewKe && <SavedStatementModal st={viewKe}
        onDelete={(id) => { setKe((k) => k.filter((x) => x.id !== id)); api("DELETE", ROUTES.statement + id); }}
        onUpdate={(ns) => { setKe((k) => k.map((x) => (x.id === ns.id ? ns : x))); setViewKe(ns); saveStatementDebounced(ns); }}
        onClose={() => setViewKe(null)} />}
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("trk-root")).render(<StatementsApp />);
})();
</script>
@endverbatim
@endpush
