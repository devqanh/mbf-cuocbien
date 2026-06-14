<?php

namespace App\Http\Controllers;

use App\Models\TruckingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

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
            's3'         => [
                'key'      => TruckingSetting::get('sys.s3_key', ''),
                'region'   => TruckingSetting::get('sys.s3_region', ''),
                'bucket'   => TruckingSetting::get('sys.s3_bucket', ''),
                'url'      => TruckingSetting::get('sys.s3_url', ''),
                'endpoint' => TruckingSetting::get('sys.s3_endpoint', ''),
                'hasSecret' => (bool) TruckingSetting::get('sys.s3_secret'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'upload_disk' => ['required', 'in:local,s3'],
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
                    return back()->withInput()->with('error', "Chọn S3 thì bắt buộc nhập {$label}.");
                }
            }
            if (! TruckingSetting::get('sys.s3_secret') && trim((string) ($data['s3_secret'] ?? '')) === '') {
                return back()->withInput()->with('error', 'Chọn S3 thì bắt buộc nhập Secret Key.');
            }
        }

        TruckingSetting::put('sys.upload_disk', $data['upload_disk']);
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
            return back()->with($ok ? 'success' : 'error', $ok ? "Kết nối disk “{$disk}” OK." : "Đọc/ghi disk “{$disk}” thất bại.");
        } catch (\Throwable $e) {
            return back()->with('error', "Lỗi kết nối disk “{$disk}”: " . $e->getMessage());
        }
    }
}
