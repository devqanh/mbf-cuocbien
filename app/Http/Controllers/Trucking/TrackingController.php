<?php

namespace App\Http\Controllers\Trucking;

use App\Models\TruckingSetting;
use App\Models\TruckingWarehouse;
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

    /** Trang Lịch sử đến/rời kho (phân trang). */
    public function visitsPage()
    {
        return view('trucking2.lich-su-kho', $this->pageData([], 'shipments.view', 'shipments.delete'));
    }

    /** Lịch sử xe đến/rời kho (geofence visit) — JSON phân trang + tìm kiếm. */
    public function visits(Request $request): JsonResponse
    {
        return response()->json(['ok' => true] + $this->gps->visitHistoryPaged(
            $request->query('q'),
            (int) $request->query('page', 1),
            (int) $request->query('perPage', 30),
        ));
    }

    /** Danh sách kho (kèm tọa độ) để vẽ marker + ghim trực tiếp trên bản đồ theo dõi. */
    public function warehouses(): JsonResponse
    {
        $rows = TruckingWarehouse::orderBy('sort')->orderBy('name')->get(['id', 'name', 'code', 'address', 'lat', 'lng']);
        return response()->json(['ok' => true, 'warehouses' => $rows->map(fn ($w) => [
            'id' => $w->id, 'name' => $w->name, 'code' => $w->code, 'address' => $w->address,
            'lat' => $w->lat, 'lng' => $w->lng,
        ])->all()]);
    }

    /** Ghim/cập nhật tọa độ 1 kho từ bản đồ theo dõi (thả/kéo điểm). */
    public function saveWarehouseGeo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id'  => ['required', 'integer'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $w = TruckingWarehouse::find($data['id']);
        if (! $w) return response()->json(['ok' => false, 'message' => 'Không tìm thấy kho'], 404);
        $w->update(['lat' => $data['lat'], 'lng' => $data['lng']]);
        return response()->json(['ok' => true, 'warehouse' => ['id' => $w->id, 'lat' => $w->lat, 'lng' => $w->lng]]);
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
