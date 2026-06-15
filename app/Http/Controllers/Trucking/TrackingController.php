<?php

namespace App\Http\Controllers\Trucking;

use App\Models\TruckingSetting;
use App\Services\Gps\GpsTrackingService;
use App\Services\TruckingV2Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Theo dõi xe realtime (GPS) — gộp nhiều nhà cung cấp (Viettel vTracking, Bình Anh dvbk…).
 * Backend làm proxy: ẩn credential + tự đăng nhập lại khi phiên hết hạn + map biển số về xe.
 * Frontend (theo-doi-xe.jsx) poll endpoint positions ~15s, vẽ Leaflet/OSM.
 */
class TrackingController extends BaseTruckingController
{
    public function __construct(TruckingV2Service $svc, private readonly GpsTrackingService $gps)
    {
        parent::__construct($svc);
    }

    /** Trang theo dõi xe. canEdit = quyền sửa cấu hình kết nối (settings.update). */
    public function index()
    {
        return view('trucking2.theo-doi-xe', $this->pageData([
            'providers' => $this->gps->publicConfig(),
            'mapsKey'   => TruckingSetting::get('gps.google_maps_key', ''),
        ], 'settings.update', 'settings.update'));
    }

    /** Endpoint poll: vị trí + trạng thái provider (cache 10s ở service). */
    public function positions(): JsonResponse
    {
        return response()->json(['ok' => true] + $this->gps->snapshot());
    }

    /** Cấu hình các provider (đã ẩn password). */
    public function config(): JsonResponse
    {
        return response()->json(['ok' => true, 'providers' => $this->gps->publicConfig()]);
    }

    /** Lưu cấu hình 1 provider. */
    public function saveConfig(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string'],
            'enabled'  => ['sometimes', 'boolean'],
            'username' => ['sometimes', 'nullable', 'string', 'max:190'],
            'password' => ['sometimes', 'nullable', 'string', 'max:190'],
            'org_ids'  => ['sometimes', 'nullable', 'string', 'max:2000'],
            'user_id'  => ['sometimes', 'nullable', 'string', 'max:190'],
            'label'    => ['sometimes', 'nullable', 'string', 'max:190'],
        ]);

        $p = $this->gps->provider($data['provider']);
        if (! $p) return response()->json(['ok' => false, 'message' => 'Nhà cung cấp không hợp lệ'], 422);

        $p->saveConfig(collect($data)->except('provider')->all());

        return response()->json(['ok' => true, 'providers' => $this->gps->publicConfig()]);
    }

    /** Test kết nối 1 provider (đăng nhập mới + thử lấy dữ liệu). */
    public function test(Request $request): JsonResponse
    {
        $key = (string) $request->input('provider');
        $p = $this->gps->provider($key);
        if (! $p) return response()->json(['ok' => false, 'message' => 'Nhà cung cấp không hợp lệ'], 422);

        return response()->json(['ok' => true, 'result' => $p->test()]);
    }
}
