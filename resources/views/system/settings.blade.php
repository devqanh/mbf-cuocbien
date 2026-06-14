@extends('layouts.app')
@section('title', 'Cài đặt hệ thống')

@section('content')
<div class="container-fluid" style="max-width: 860px;">
  <div class="d-flex align-items-center gap-3 mb-4">
    <div class="rounded-3 d-grid" style="width:46px;height:46px;place-items:center;background:#eef1f6;color:#475569;font-size:22px;">
      <i class="bi bi-gear-wide-connected"></i>
    </div>
    <div>
      <h4 class="mb-0 fw-bold">Cài đặt hệ thống</h4>
      <div class="text-muted small">Cấu hình chung toàn hệ thống</div>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success d-flex align-items-center gap-2"><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bi bi-exclamation-triangle-fill"></i> {{ session('error') }}</div>
  @endif

  <form method="POST" action="{{ route('system.settings.update') }}" autocomplete="off">
    @csrf
    @method('PUT')

    {{-- ===== Nơi lưu file ===== --}}
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body p-4">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-hdd-stack text-primary"></i>
          <h6 class="mb-0 fw-bold">Nơi lưu file (tài liệu lái xe/xe, ảnh phiếu chi)</h6>
        </div>
        <p class="text-muted small mb-3">File cũ vẫn đọc đúng khi đổi vì mỗi file lưu kèm nơi lưu của nó. Đổi sang S3 chỉ áp dụng cho file <b>tải lên mới</b>.</p>

        <div class="row g-3 mb-1">
          <div class="col-md-6">
            <label class="w-100" style="cursor:pointer;">
              <input type="radio" name="upload_disk" value="local" class="btn-check" id="disk-local" {{ $disk === 'local' ? 'checked' : '' }} onchange="trkToggleS3()">
              <span class="d-block border rounded-3 p-3 disk-opt" data-for="local">
                <span class="fw-semibold"><i class="bi bi-hdd me-1"></i> Máy chủ (Local)</span>
                <span class="d-block text-muted small mt-1">Lưu trên ổ đĩa máy chủ. Mặc định, không cần cấu hình.</span>
              </span>
            </label>
          </div>
          <div class="col-md-6">
            <label class="w-100" style="cursor:pointer;">
              <input type="radio" name="upload_disk" value="s3" class="btn-check" id="disk-s3" {{ $disk === 's3' ? 'checked' : '' }} onchange="trkToggleS3()">
              <span class="d-block border rounded-3 p-3 disk-opt" data-for="s3">
                <span class="fw-semibold"><i class="bi bi-cloud me-1"></i> Amazon S3 (hoặc tương thích)</span>
                <span class="d-block text-muted small mt-1">Lưu trên cloud. Phù hợp khi dữ liệu lớn. Cần điền thông tin bên dưới.</span>
              </span>
            </label>
          </div>
        </div>

        <div id="s3-fields" class="mt-3 p-3 rounded-3" style="background:#f8f9fb;border:1px solid #e9edf3;">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Bucket</label>
              <input type="text" name="s3_bucket" class="form-control" value="{{ old('s3_bucket', $s3['bucket']) }}" placeholder="vd: mbf-trucking">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Region</label>
              <input type="text" name="s3_region" class="form-control" value="{{ old('s3_region', $s3['region']) }}" placeholder="vd: ap-southeast-1">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Access Key</label>
              <input type="text" name="s3_key" class="form-control" value="{{ old('s3_key', $s3['key']) }}" placeholder="AKIA…" autocomplete="off">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Secret Key</label>
              <input type="password" name="s3_secret" class="form-control" placeholder="{{ $s3['hasSecret'] ? '••••••••  (đã lưu — để trống nếu không đổi)' : 'Nhập secret key' }}" autocomplete="new-password">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Endpoint <span class="text-muted fw-normal">(tùy chọn — S3 tương thích: DO Spaces/MinIO)</span></label>
              <input type="text" name="s3_endpoint" class="form-control" value="{{ old('s3_endpoint', $s3['endpoint']) }}" placeholder="https://…">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Public URL <span class="text-muted fw-normal">(tùy chọn)</span></label>
              <input type="text" name="s3_url" class="form-control" value="{{ old('s3_url', $s3['url']) }}" placeholder="https://cdn…">
            </div>
          </div>
          <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Secret được mã hóa khi lưu. Sau khi lưu, bấm <b>Kiểm tra kết nối</b> để chắc chắn S3 hoạt động trước khi dùng thật.</div>
        </div>
      </div>
    </div>

    {{-- Chỗ cho các mục cấu hình chung khác sau này --}}

    <div class="d-flex align-items-center gap-2">
      <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-save me-1"></i> Lưu cài đặt</button>
      <button type="submit" form="frm-test" class="btn btn-outline-secondary"><i class="bi bi-plug me-1"></i> Kiểm tra kết nối</button>
    </div>
  </form>

  <form id="frm-test" method="POST" action="{{ route('system.settings.test') }}" class="d-none">@csrf</form>
</div>

<style>
  .btn-check:checked + .disk-opt { border-color: var(--bs-primary) !important; box-shadow: 0 0 0 3px rgba(13,110,253,.12); background:#f5f9ff; }
  .disk-opt { transition: all .12s; }
</style>
<script>
  function trkToggleS3(){
    var on = document.getElementById('disk-s3').checked;
    document.getElementById('s3-fields').style.display = on ? '' : 'none';
  }
  trkToggleS3();
</script>
@endsection
