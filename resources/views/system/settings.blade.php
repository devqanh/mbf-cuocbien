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

    {{-- ===== Thông tin công ty (header bảng kê) ===== --}}
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body p-4">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-building text-primary"></i>
          <h6 class="mb-0 fw-bold">Thông tin công ty</h6>
        </div>
        <p class="text-muted small mb-3">Hiển thị ở góc phải tiêu đề <b>Bảng kê</b> (trên màn hình và bản in).</p>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label small fw-semibold">Tên công ty</label>
            <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $company['name']) }}" placeholder="vd: MBF JOINT STOCK COMPANY">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Website</label>
            <input type="text" name="company_website" class="form-control" value="{{ old('company_website', $company['website']) }}" placeholder="vd: http://mbf.com.vn">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Điện thoại</label>
            <input type="text" name="company_phone" class="form-control" value="{{ old('company_phone', $company['phone']) }}" placeholder="vd: 84-24-39449616">
          </div>
        </div>
        <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Phần này chỉ là tiêu đề hiển thị/in trên màn hình. Thông tin pháp lý <b>Bên bán</b> dùng cho file Excel cấu hình ở mục bên dưới.</div>
      </div>
    </div>

    {{-- ===== Bên bán (xuất Excel bảng kê) ===== --}}
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body p-4">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-file-earmark-spreadsheet text-primary"></i>
          <h6 class="mb-0 fw-bold">Bên bán — xuất file Excel bảng kê</h6>
        </div>
        <p class="text-muted small mb-3">Tự điền vào khối <b>BÊN BÁN</b> khi xuất Excel. <b>Bên mua</b> tự lấy từ khách của bảng kê → không phải sửa file mẫu bằng tay nữa.</p>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label small fw-semibold">Tên công ty (pháp lý)</label>
            <input type="text" name="seller_name" class="form-control" value="{{ old('seller_name', $seller['name']) }}" placeholder="vd: CÔNG TY CỔ PHẦN MBF">
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">Địa chỉ</label>
            <input type="text" name="seller_address" class="form-control" value="{{ old('seller_address', $seller['address']) }}" placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">MST</label>
            <input type="text" name="seller_tax" class="form-control" value="{{ old('seller_tax', $seller['tax']) }}" placeholder="vd: 0105040296">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Đại diện <span class="text-muted fw-normal">(tùy chọn)</span></label>
            <input type="text" name="seller_rep" class="form-control" value="{{ old('seller_rep', $seller['rep']) }}" placeholder="vd: Nguyễn Văn A">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Chức vụ <span class="text-muted fw-normal">(tùy chọn)</span></label>
            <input type="text" name="seller_title" class="form-control" value="{{ old('seller_title', $seller['title']) }}" placeholder="vd: Giám đốc">
          </div>
        </div>
        <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Để trống Đại diện/Chức vụ thì file giữ nhãn sẵn để ký tay.</div>
      </div>
    </div>

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
