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
        ]);
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
                    return back()->withInput()->with('error', "Chọn S3 thì bắt buộc nhập {$label}.");
                }
            }
            if (! TruckingSetting::get('sys.s3_secret') && trim((string) ($data['s3_secret'] ?? '')) === '') {
                return back()->withInput()->with('error', 'Chọn S3 thì bắt buộc nhập Secret Key.');
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
            return back()->with($ok ? 'success' : 'error', $ok ? "Kết nối disk “{$disk}” OK." : "Đọc/ghi disk “{$disk}” thất bại.");
        } catch (\Throwable $e) {
            return back()->with('error', "Lỗi kết nối disk “{$disk}”: " . $e->getMessage());
        }
    }
}
