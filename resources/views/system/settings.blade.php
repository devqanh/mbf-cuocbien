@extends('layouts.app')
@section('title', 'Cài đặt hệ thống')

@section('content')
<div class="container-fluid" style="max-width: 920px;" data-initial-tab="{{ session('tab', '') }}">
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

  {{-- ===== Tab nav ===== --}}
  <ul class="settings-tabs">
    <li><button type="button" data-stab="company"><i class="bi bi-building"></i> Công ty</button></li>
    <li><button type="button" data-stab="seller"><i class="bi bi-file-earmark-spreadsheet"></i> Bên bán</button></li>
    <li><button type="button" data-stab="storage"><i class="bi bi-hdd-stack"></i> Lưu trữ file</button></li>
    <li><button type="button" data-stab="features"><i class="bi bi-toggles"></i> Tính năng</button></li>
    <li><button type="button" data-stab="backup"><i class="bi bi-database-down"></i> Sao lưu CSDL</button></li>
  </ul>

  {{-- ===== Form cấu hình (3 tab đầu) ===== --}}
  <form method="POST" action="{{ route('system.settings.update') }}" autocomplete="off">
    @csrf
    @method('PUT')

    {{-- Tab: Thông tin công ty (header bảng kê) --}}
    <div class="settings-pane" data-spane="company">
      <div class="card border-0 shadow-sm">
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
          <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Phần này chỉ là tiêu đề hiển thị/in trên màn hình. Thông tin pháp lý <b>Bên bán</b> dùng cho file Excel ở tab <b>Bên bán</b>.</div>
        </div>
      </div>
    </div>

    {{-- Tab: Bên bán (xuất Excel bảng kê) --}}
    <div class="settings-pane" data-spane="seller">
      <div class="card border-0 shadow-sm">
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
    </div>

    {{-- Tab: Nơi lưu file --}}
    <div class="settings-pane" data-spane="storage">
      <div class="card border-0 shadow-sm">
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
            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
              <button type="submit" form="frm-test" class="btn btn-outline-secondary btn-sm"><i class="bi bi-plug me-1"></i> Kiểm tra kết nối</button>
              <span class="small text-muted"><i class="bi bi-info-circle"></i> Secret được mã hóa khi lưu. Lưu xong hãy bấm Kiểm tra kết nối trước khi dùng thật.</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Tab: Tính năng (bật/tắt module) --}}
    <div class="settings-pane" data-spane="features">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <div class="d-flex align-items-center gap-2 mb-1">
            <i class="bi bi-toggles text-primary"></i>
            <h6 class="mb-0 fw-bold">Bật / tắt tính năng</h6>
          </div>
          <p class="text-muted small mb-3">Bật/tắt các tính năng tùy chọn của hệ thống. Tắt rồi vẫn giữ nguyên dữ liệu, bật lại là dùng tiếp.</p>

          <div class="d-flex align-items-start justify-content-between gap-3 p-3 rounded-3" style="background:#f8f9fb;border:1px solid #e9edf3;">
            <div>
              <div class="fw-semibold"><i class="bi bi-link-45deg me-1 text-primary"></i> Link kế hoạch (cho lái xe)</div>
              <div class="text-muted small mt-1" style="max-width:560px;line-height:1.5;">
                Hiện nút <b>“Link kế hoạch”</b> ở trang <b>Lô hàng</b> để tạo link công khai cho lái xe tự cập nhật giờ xe.
                Tắt → ẩn nút và khóa trang quản lý link kế hoạch.
              </div>
            </div>
            <div class="form-check form-switch fs-4 mt-1" style="padding-left:3.2em;">
              <input type="checkbox" class="form-check-input" role="switch" id="feat-plan-link"
                     name="feature_plan_link" value="1" {{ old('feature_plan_link', $features['plan_link']) ? 'checked' : '' }}
                     style="cursor:pointer;">
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Thanh lưu (hiện ở các tab cấu hình, ẩn ở tab Sao lưu) --}}
    <div id="settings-savebar" class="settings-savebar mt-3">
      <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-save me-1"></i> Lưu cài đặt</button>
      <span class="small text-muted">Lưu chung cho các tab: Công ty, Bên bán, Lưu trữ file, Tính năng.</span>
    </div>
  </form>

  <form id="frm-test" method="POST" action="{{ route('system.settings.test') }}" class="d-none">@csrf</form>

  {{-- ===== Tab: Sao lưu cơ sở dữ liệu (ngoài form cấu hình) ===== --}}
  <div class="settings-pane" data-spane="backup">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-1">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-database-down text-primary"></i>
            <h6 class="mb-0 fw-bold">Sao lưu cơ sở dữ liệu</h6>
          </div>
          <form method="POST" action="{{ route('system.settings.backupNow') }}"
                onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='<span class=\'spinner-border spinner-border-sm me-1\'></span> Đang sao lưu…';">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm fw-semibold"><i class="bi bi-arrow-repeat me-1"></i> Sao lưu ngay</button>
          </form>
        </div>
        <p class="text-muted small mb-3">
          Hệ thống tự động sao lưu <b>hằng ngày lúc 02:00</b>, nén <code>.sql.gz</code> và <b>chỉ giữ 15 bản gần nhất</b> (bản cũ tự xóa).
        </p>

        {{-- Trạng thái lần chạy gần nhất --}}
        @if($lastBackup)
          @if($lastBackup['ok'])
            <div class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 mb-3">
              <i class="bi bi-check-circle-fill"></i>
              <span class="small">Lần sao lưu gần nhất <b>thành công</b> — {{ $lastBackup['at_human'] }} · {{ $lastBackup['human'] }}</span>
            </div>
          @else
            <div class="alert alert-danger d-flex align-items-start gap-2 py-2 px-3 mb-3">
              <i class="bi bi-exclamation-triangle-fill mt-1"></i>
              <span class="small">Lần sao lưu gần nhất <b>thất bại</b> — {{ $lastBackup['at_human'] }}<br>
                <span class="text-muted">{{ $lastBackup['error'] ?? 'Không rõ lỗi' }}</span></span>
            </div>
          @endif
        @else
          <div class="alert alert-secondary d-flex align-items-center gap-2 py-2 px-3 mb-3">
            <i class="bi bi-info-circle"></i>
            <span class="small">Chưa có bản sao lưu nào. Bấm <b>Sao lưu ngay</b> để tạo bản đầu tiên.</span>
          </div>
        @endif

        {{-- Danh sách file --}}
        @if(count($backups))
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr class="text-muted small">
                  <th>Tên file</th>
                  <th class="text-nowrap">Thời gian</th>
                  <th class="text-end">Dung lượng</th>
                  <th class="text-end" style="width:80px;"></th>
                </tr>
              </thead>
              <tbody>
                @foreach($backups as $b)
                  <tr>
                    <td class="text-break"><i class="bi bi-file-earmark-zip text-secondary me-1"></i>{{ $b['name'] }}</td>
                    <td class="text-nowrap small">{{ $b['at'] }}</td>
                    <td class="text-end text-nowrap small">{{ $b['human'] }}</td>
                    <td class="text-end">
                      <a href="{{ route('system.settings.backupDownload', $b['name']) }}"
                         class="btn btn-outline-secondary btn-sm py-0 px-2" title="Tải về">
                        <i class="bi bi-download"></i>
                      </a>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Đang giữ {{ count($backups) }}/15 bản. File lưu tại <code>storage/app/backups</code> trên máy chủ.</div>
        @endif
      </div>
    </div>
  </div>
</div>

<style>
  .btn-check:checked + .disk-opt { border-color: var(--bs-primary) !important; box-shadow: 0 0 0 3px rgba(13,110,253,.12); background:#f5f9ff; }
  .disk-opt { transition: all .12s; }

  /* Tabs cài đặt — kiểu gạch chân, cuộn ngang trên mobile */
  .settings-tabs {
    list-style: none; display: flex; gap: 2px; padding: 0; margin: 0 0 22px;
    border-bottom: 1px solid var(--azia-border);
    overflow-x: auto; -webkit-overflow-scrolling: touch; flex-wrap: nowrap;
  }
  .settings-tabs::-webkit-scrollbar { height: 0; }
  .settings-tabs button {
    border: none; background: none; cursor: pointer; flex-shrink: 0; white-space: nowrap;
    padding: 11px 16px; font-weight: 600; font-size: 14px; color: #7987a1;
    border-bottom: 2px solid transparent; margin-bottom: -1px;
    display: inline-flex; align-items: center; gap: 7px; border-radius: 8px 8px 0 0;
    transition: color .12s, background .12s;
  }
  .settings-tabs button:hover { color: var(--bs-primary); background: #f6f8fc; }
  .settings-tabs button.active { color: var(--bs-primary); border-bottom-color: var(--bs-primary); }
  .settings-pane { display: none; }
  .settings-pane.active { display: block; animation: fadeIn .15s ease; }
  .settings-savebar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
  .settings-savebar.is-hidden { display: none; }
  @keyframes fadeIn { from { opacity: 0; transform: translateY(2px); } to { opacity: 1; transform: none; } }
</style>
<script>
  function trkToggleS3(){
    var on = document.getElementById('disk-s3').checked;
    document.getElementById('s3-fields').style.display = on ? '' : 'none';
  }
  trkToggleS3();

  (function(){
    var TABS = ['company','seller','storage','features','backup'];
    var wrap = document.querySelector('[data-initial-tab]');
    var btns = document.querySelectorAll('.settings-tabs [data-stab]');
    var panes = document.querySelectorAll('.settings-pane');
    var savebar = document.getElementById('settings-savebar');

    function show(key){
      if (TABS.indexOf(key) === -1) key = 'company';
      btns.forEach(function(b){ b.classList.toggle('active', b.dataset.stab === key); });
      panes.forEach(function(p){ p.classList.toggle('active', p.dataset.spane === key); });
      if (savebar) savebar.classList.toggle('is-hidden', key === 'backup');
      try { history.replaceState(null, '', '#' + key); } catch(e){}
    }
    btns.forEach(function(b){ b.addEventListener('click', function(){ show(b.dataset.stab); }); });

    var initial = (location.hash || '').replace('#','') || (wrap && wrap.dataset.initialTab) || 'company';
    show(initial);
  })();
</script>
@endsection
