<?php

namespace App\Http\Controllers;

use App\Models\TruckingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Cài đặt hệ thống (chung) — hiện có: nơi lưu file (local / S3). Lưu vào TruckingSetting (prefix 'sys.').
 * Thiết kế mở để thêm nhiều mục cấu hình chung về sau.
 */
class SystemSettingController extends Controller
{
    public function index()
    {
        return view('system.settings', [
            'disk'       => TruckingSetting::get('sys.upload_disk', config('trucking.upload_disk', 'local')),
            'company'    => [
                'name'    => TruckingSetting::get('sys.company_name', 'MBF JOINT STOCK COMPANY'),
                'website' => TruckingSetting::get('sys.company_website', 'http://mbf.com.vn'),
                'phone'   => TruckingSetting::get('sys.company_phone', '84-24-39449616'),
            ],
            'seller'     => [
                'name'    => TruckingSetting::get('sys.seller_name', 'CÔNG TY CỔ PHẦN MBF'),
                'address' => TruckingSetting::get('sys.seller_address', 'Số 58 Xóm Giếng, Thôn Cổ Điển A, Xã Thanh Trì, Thành phố Hà Nội, Việt Nam'),
                'tax'     => TruckingSetting::get('sys.seller_tax', '0105040296'),
                'rep'     => TruckingSetting::get('sys.seller_rep', ''),
                'title'   => TruckingSetting::get('sys.seller_title', ''),
            ],
            's3'         => [
                'key'      => TruckingSetting::get('sys.s3_key', ''),
                'region'   => TruckingSetting::get('sys.s3_region', ''),
                'bucket'   => TruckingSetting::get('sys.s3_bucket', ''),
                'url'      => TruckingSetting::get('sys.s3_url', ''),
                'endpoint' => TruckingSetting::get('sys.s3_endpoint', ''),
                'hasSecret' => (bool) TruckingSetting::get('sys.s3_secret'),
            ],
            'backups'    => $this->listBackups(),
            'lastBackup' => $this->lastBackup(),
        ]);
    }

    // ===================== Sao lưu CSDL =====================

    private function backupDir(): string
    {
        return storage_path('app/backups');
    }

    /** Danh sách tối đa 15 bản sao lưu gần nhất (mới → cũ). */
    private function listBackups(): array
    {
        $dir = $this->backupDir();
        if (! is_dir($dir)) return [];
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql.gz') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        return array_map(fn ($f) => [
            'name'  => basename($f),
            'bytes' => (int) filesize($f),
            'human' => $this->humanBytes((int) filesize($f)),
            'at'    => date('d/m/Y H:i', filemtime($f)),
        ], array_slice($files, 0, 15));
    }

    /** Báo cáo lần chạy gần nhất (do command db:backup ghi lại). */
    private function lastBackup(): ?array
    {
        $raw = TruckingSetting::get('sys.backup_last_run');
        if (! $raw) return null;
        $d = json_decode($raw, true);
        if (! is_array($d)) return null;
        $d['at_human'] = ! empty($d['at']) ? Carbon::parse($d['at'])->format('d/m/Y H:i') : '';
        $d['human']    = $this->humanBytes((int) ($d['bytes'] ?? 0));
        return $d;
    }

    private function humanBytes(int $b): string
    {
        if ($b >= 1073741824) return round($b / 1073741824, 2) . ' GB';
        if ($b >= 1048576)    return round($b / 1048576, 2) . ' MB';
        if ($b >= 1024)       return round($b / 1024) . ' KB';
        return $b . ' B';
    }

    /** Chạy sao lưu ngay (đồng bộ) từ nút trên trang. */
    public function backupNow(): RedirectResponse
    {
        try {
            $code = Artisan::call('db:backup');
            if ($code === 0) {
                return back()->with('tab', 'backup')->with('success', 'Đã tạo bản sao lưu mới.');
            }
            return back()->with('tab', 'backup')->with('error', 'Sao lưu thất bại: ' . trim(Artisan::output()));
        } catch (\Throwable $e) {
            return back()->with('tab', 'backup')->with('error', 'Lỗi khi sao lưu: ' . $e->getMessage());
        }
    }

    /** Tải 1 file sao lưu — chặn path traversal, chỉ cho file .sql.gz trong thư mục backups. */
    public function downloadBackup(string $file): BinaryFileResponse
    {
        if (! preg_match('/^[A-Za-z0-9_.\-]+\.sql\.gz$/', $file)) {
            abort(404);
        }
        $path = $this->backupDir() . DIRECTORY_SEPARATOR . $file;
        if (! is_file($path)) {
            abort(404);
        }
        return response()->download($path);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'upload_disk'     => ['required', 'in:local,s3'],
            'company_name'    => ['nullable', 'string', 'max:255'],
            'company_website' => ['nullable', 'string', 'max:255'],
            'company_phone'   => ['nullable', 'string', 'max:64'],
            'seller_name'     => ['nullable', 'string', 'max:255'],
            'seller_address'  => ['nullable', 'string', 'max:500'],
            'seller_tax'      => ['nullable', 'string', 'max:64'],
            'seller_rep'      => ['nullable', 'string', 'max:255'],
            'seller_title'    => ['nullable', 'string', 'max:255'],
            's3_key'      => ['nullable', 'string', 'max:255'],
            's3_secret'   => ['nullable', 'string', 'max:255'],
            's3_region'   => ['nullable', 'string', 'max:64'],
            's3_bucket'   => ['nullable', 'string', 'max:255'],
            's3_url'      => ['nullable', 'string', 'max:255'],
            's3_endpoint' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['upload_disk'] === 's3') {
            foreach (['s3_key' => 'Access Key', 's3_region' => 'Region', 's3_bucket' => 'Bucket'] as $k => $label) {
                if (trim((string) ($data[$k] ?? '')) === '') {
                    return back()->withInput()->with('tab', 'storage')->with('error', "Chọn S3 thì bắt buộc nhập {$label}.");
                }
            }
            if (! TruckingSetting::get('sys.s3_secret') && trim((string) ($data['s3_secret'] ?? '')) === '') {
                return back()->withInput()->with('tab', 'storage')->with('error', 'Chọn S3 thì bắt buộc nhập Secret Key.');
            }
        }

        TruckingSetting::put('sys.upload_disk', $data['upload_disk']);
        TruckingSetting::put('sys.company_name', trim((string) ($data['company_name'] ?? '')));
        TruckingSetting::put('sys.company_website', trim((string) ($data['company_website'] ?? '')));
        TruckingSetting::put('sys.company_phone', trim((string) ($data['company_phone'] ?? '')));
        TruckingSetting::put('sys.seller_name', trim((string) ($data['seller_name'] ?? '')));
        TruckingSetting::put('sys.seller_address', trim((string) ($data['seller_address'] ?? '')));
        TruckingSetting::put('sys.seller_tax', trim((string) ($data['seller_tax'] ?? '')));
        TruckingSetting::put('sys.seller_rep', trim((string) ($data['seller_rep'] ?? '')));
        TruckingSetting::put('sys.seller_title', trim((string) ($data['seller_title'] ?? '')));
        TruckingSetting::put('sys.s3_key', trim((string) ($data['s3_key'] ?? '')));
        TruckingSetting::put('sys.s3_region', trim((string) ($data['s3_region'] ?? '')));
        TruckingSetting::put('sys.s3_bucket', trim((string) ($data['s3_bucket'] ?? '')));
        TruckingSetting::put('sys.s3_url', trim((string) ($data['s3_url'] ?? '')));
        TruckingSetting::put('sys.s3_endpoint', trim((string) ($data['s3_endpoint'] ?? '')));
        // Secret: chỉ ghi đè khi người dùng nhập mới (mã hóa); để trống = giữ secret cũ
        if (trim((string) ($data['s3_secret'] ?? '')) !== '') {
            TruckingSetting::put('sys.s3_secret', Crypt::encryptString(trim($data['s3_secret'])));
        }

        return back()->with('success', 'Đã lưu cài đặt hệ thống.');
    }

    /** Kiểm tra kết nối disk đang chọn (ghi + đọc + xóa 1 file thử). */
    public function test(): RedirectResponse
    {
        $disk = TruckingSetting::get('sys.upload_disk', config('trucking.upload_disk', 'local'));
        if ($disk === 's3') app(\App\Services\TruckingV2Service::class)->applyS3Config();
        try {
            $name = 'trucking/_healthcheck/' . uniqid('t') . '.txt';
            Storage::disk($disk)->put($name, 'ok');
            $ok = Storage::disk($disk)->get($name) === 'ok';
            Storage::disk($disk)->delete($name);
            return back()->with('tab', 'storage')->with($ok ? 'success' : 'error', $ok ? "Kết nối disk “{$disk}” OK." : "Đọc/ghi disk “{$disk}” thất bại.");
        } catch (\Throwable $e) {
            return back()->with('tab', 'storage')->with('error', "Lỗi kết nối disk “{$disk}”: " . $e->getMessage());
        }
    }
}
