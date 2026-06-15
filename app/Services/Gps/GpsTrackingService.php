<?php

namespace App\Services\Gps;

use App\Models\TruckingSetting;
use App\Models\TruckingVehicle;
use App\Models\TruckingWarehouse;
use App\Models\TruckingWarehouseVisit;
use App\Support\PlateNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Gộp dữ liệu vị trí từ mọi nhà cung cấp GPS đang bật + map biển số về trucking_vehicles.
 * Snapshot cache NGẮN (10s) để gộp các lượt poll dồn dập từ nhiều client (token vẫn cache
 * lâu dài ở từng provider — chỉ login lại khi fetch hỏng). Frontend poll ~15s.
 */
class GpsTrackingService
{
    /** @return array<string,AbstractGpsProvider> */
    public function providers(): array
    {
        return [
            'viettel' => new ViettelProvider(),
            'dvbk'    => new DvbkProvider(),
        ];
    }

    public function provider(string $key): ?AbstractGpsProvider
    {
        return $this->providers()[$key] ?? null;
    }

    /** Vị trí + trạng thái provider, cache 10s. */
    public function snapshot(): array
    {
        return Cache::remember('gps.snapshot', 10, function () {
            $positions = [];
            $status = [];

            foreach ($this->providers() as $key => $p) {
                if (! $p->enabled()) {
                    $status[] = ['key' => $key, 'label' => $p->label(), 'enabled' => false, 'count' => 0, 'ok' => false, 'error' => null];
                    continue;
                }
                $err = null; $pos = [];
                try { $pos = $p->fetchPositions(); }
                catch (\Throwable $e) { $err = $e->getMessage(); }
                $positions = array_merge($positions, $pos);
                $status[] = [
                    'key' => $key, 'label' => $p->label(), 'enabled' => true,
                    'count' => count($pos), 'ok' => $err === null && count($pos) > 0, 'error' => $err,
                ];
            }

            $this->matchVehicles($positions);

            return ['positions' => $positions, 'providers' => $status, 'ts' => (int) (microtime(true) * 1000)];
        });
    }

    /** Cấu hình các provider (đã ẩn password) cho trang cấu hình. */
    public function publicConfig(): array
    {
        return array_map(fn ($p) => $p->publicConfig(), array_values($this->providers()));
    }

    /** Danh sách xe GPS (gộp mọi nguồn, dedup theo provider:deviceId) cho dropdown gán xe ở Đội xe. */
    public function vehicleOptions(): array
    {
        $out = []; $seen = [];
        foreach ($this->snapshot()['positions'] as $p) {
            if (empty($p['deviceId'])) continue;
            $ref = $p['provider'] . ':' . $p['deviceId'];
            if (isset($seen[$ref])) continue;
            $seen[$ref] = true;
            $out[] = ['ref' => $ref, 'plate' => $p['plate'], 'provider' => $p['provider'], 'providerLabel' => $p['providerLabel']];
        }
        usort($out, fn ($a, $b) => strcmp((string) $a['plate'], (string) $b['plate']));
        return $out;
    }

    /** Gắn matched/vehicleId vào từng vị trí: ƯU TIÊN liên kết gps_ref (provider:deviceId), sau đó dò biển số. */
    protected function matchVehicles(array &$positions): void
    {
        if (empty($positions)) return;
        $vehicles = TruckingVehicle::get(['id', 'plate', 'type', 'kind', 'gps_ref']);
        $byPlate = $vehicles->mapWithKeys(fn ($v) => [PlateNormalizer::norm($v->plate) => $v]);
        $byRef   = $vehicles->filter(fn ($v) => $v->gps_ref)->keyBy('gps_ref');

        foreach ($positions as &$p) {
            $ref = ($p['provider'] ?? '') . ':' . ($p['deviceId'] ?? '');
            $v = $byRef->get($ref) ?: $byPlate->get($p['plateNorm'] ?? '');
            $p['matched']     = (bool) $v;
            $p['vehicleId']   = $v->id ?? null;
            $p['vehiclePlate'] = $v->plate ?? null;   // biển số hệ thống (để hiển thị/đối chiếu)
            $p['vehicleKind'] = $v->kind ?? null;
            $p['vehicleType'] = $v->type ?? null;     // 'MBF' | 'Ngoài'
        }
        unset($p);
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371; $dLat = deg2rad($lat2 - $lat1); $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $R * asin(sqrt($a));
    }

    /**
     * Quét vị trí GPS → ghi lịch sử ghé kho (geofence visit). Gọi định kỳ qua cron.
     * Máy trạng thái: VÀO ≤ enter mở visit; còn trong vùng đệm thì cập nhật; RA > exit đóng.
     * dwell < ngưỡng → tạt ngang, xóa. 1 lần ghé = 1 dòng (chuyến sau quay lại = dòng mới).
     */
    public function scanVisits(): array
    {
        $whGeo = TruckingWarehouse::whereNotNull('lat')->whereNotNull('lng')->get();
        if ($whGeo->isEmpty()) return ['ok' => false, 'reason' => 'Chưa có kho nào được ghim tọa độ'];

        // Bán kính ĐẾN khác nhau theo trạng thái:
        //  - xe ĐANG CHẠY: phải sát điểm (enter 400m) → tránh tính xe chạy ngang.
        //  - xe ĐỖ/TẮT MÁY/MẤT TÍN HIỆU: rộng hơn (parked 1000m) → đỗ trong khuôn viên kho = đã ở kho.
        // RA > exit (1500m, > parked để không đóng nhầm xe đỗ xa điểm ghim). Dwell 10' lọc xe tạt ngang.
        $enterKm  = max(0.05, (float) TruckingSetting::get('gps.geofence_enter_m', 400) / 1000);
        $parkedKm = max($enterKm, (float) TruckingSetting::get('gps.geofence_parked_m', 1000) / 1000);
        $exitKm   = max($parkedKm + 0.2, (float) TruckingSetting::get('gps.geofence_exit_m', 1500) / 1000);
        $dwellMin = (int) TruckingSetting::get('gps.geofence_dwell_min', 10);

        $positions = $this->snapshot()['positions'];
        $opened = 0; $closed = 0; $updated = 0; $dropped = 0;

        foreach ($positions as $p) {
            if (empty($p['deviceId']) || ! is_numeric($p['lat'] ?? null) || ! is_numeric($p['lng'] ?? null)) continue;
            $ref = $p['provider'] . ':' . $p['deviceId'];
            $ts  = ! empty($p['ts']) ? Carbon::createFromTimestampMs((int) $p['ts']) : Carbon::now();

            $open = TruckingWarehouseVisit::where('gps_ref', $ref)->whereNull('departed_at')->orderByDesc('arrived_at')->first();

            if ($open) {
                $w = $whGeo->firstWhere('id', $open->warehouse_id);
                if (! $w) {   // kho bị xóa tọa độ → đóng visit
                    $open->update(['departed_at' => $open->last_inside_at ?? $ts]); $closed++; continue;
                }
                $dist = $this->haversineKm((float) $p['lat'], (float) $p['lng'], (float) $w->lat, (float) $w->lng);
                if ($dist > $exitKm) {
                    if ($open->confirmed) { $open->update(['departed_at' => $open->last_inside_at ?? $ts]); $closed++; }
                    else { $open->delete(); $dropped++; }   // chưa đủ dwell → tạt ngang
                } else {
                    $attrs = ['last_inside_at' => $ts, 'min_dist_m' => min($open->min_dist_m ?? PHP_INT_MAX, (int) round($dist * 1000))];
                    if (! $open->confirmed && $open->created_at && Carbon::now()->diffInMinutes($open->created_at) >= $dwellMin) $attrs['confirmed'] = true;
                    if (! $open->vehicle_id && ! empty($p['vehicleId'])) { $attrs['vehicle_id'] = $p['vehicleId']; $attrs['vehicle_plate'] = $p['vehiclePlate'] ?? null; }
                    $open->update($attrs); $updated++;
                }
            } else {
                $best = null; $bestD = INF;
                foreach ($whGeo as $w) { $d = $this->haversineKm((float) $p['lat'], (float) $p['lng'], (float) $w->lat, (float) $w->lng); if ($d < $bestD) { $bestD = $d; $best = $w; } }
                // Xe đứng yên/tắt máy → bán kính rộng (parked); xe đang chạy → sát điểm (enter).
                $stopped = (float) ($p['speed'] ?? 0) <= 1;
                $arriveR = $stopped ? $parkedKm : $enterKm;
                if ($best && $bestD <= $arriveR) {
                    TruckingWarehouseVisit::create([
                        'provider' => $p['provider'], 'gps_ref' => $ref, 'gps_plate' => $p['plate'] ?? null,
                        'vehicle_id' => $p['vehicleId'] ?? null, 'vehicle_plate' => $p['vehiclePlate'] ?? null, 'driver' => $p['driver'] ?? null,
                        'warehouse_id' => $best->id, 'warehouse_name' => $best->name,
                        'arrived_at' => $ts, 'last_inside_at' => $ts, 'confirmed' => $dwellMin <= 0, 'min_dist_m' => (int) round($bestD * 1000),
                    ]);
                    $opened++;
                }
            }
        }

        return ['ok' => true, 'positions' => count($positions), 'opened' => $opened, 'closed' => $closed, 'updated' => $updated, 'dropped' => $dropped];
    }

    private function visitToArray(TruckingWarehouseVisit $v): array
    {
        return [
            'id'         => $v->id,
            'gpsPlate'   => $v->gps_plate,
            'plate'      => $v->vehicle_plate,          // biển số xe HỆ THỐNG (nếu khớp)
            'matched'    => (bool) $v->vehicle_id,
            'driver'     => $v->driver,
            'warehouse'  => $v->warehouse_name,
            'provider'   => $v->provider,
            'arrivedAt'  => $v->arrived_at ? $v->arrived_at->getTimestampMs() : null,
            'departedAt' => $v->departed_at ? $v->departed_at->getTimestampMs() : null,
            'open'       => $v->departed_at === null,
            'minDist'    => $v->min_dist_m,
        ];
    }

    /** Lịch sử ghé kho (đã xác nhận dwell) — PHÂN TRANG + tìm kiếm (biển số/tài xế/kho). */
    public function visitHistoryPaged(?string $q, int $page = 1, int $perPage = 30): array
    {
        // Hiện visit ĐÃ xác nhận (đủ dwell) HOẶC đang mở (xe đang ở kho) — để thấy ngay khi xe vừa đến.
        $query = TruckingWarehouseVisit::where(function ($w) {
            $w->where('confirmed', true)->orWhereNull('departed_at');
        });
        $q = trim((string) $q);
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('vehicle_plate', 'like', "%{$q}%")
                  ->orWhere('gps_plate', 'like', "%{$q}%")
                  ->orWhere('driver', 'like', "%{$q}%")
                  ->orWhere('warehouse_name', 'like', "%{$q}%");
            });
        }
        $perPage = max(5, min(100, $perPage));
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $rows = $query->orderByDesc('arrived_at')->forPage($page, $perPage)->get();

        return [
            'visits'   => $rows->map(fn ($v) => $this->visitToArray($v))->all(),
            'page'     => $page, 'perPage' => $perPage, 'total' => $total, 'lastPage' => $lastPage,
        ];
    }
}
