<?php

namespace App\Services\Gps;

use App\Models\TruckingVehicle;
use App\Support\PlateNormalizer;
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

    /** Gắn matched/vehicleId vào từng vị trí theo biển số chuẩn hóa. */
    protected function matchVehicles(array &$positions): void
    {
        if (empty($positions)) return;
        $map = TruckingVehicle::get(['id', 'plate', 'type', 'kind'])
            ->mapWithKeys(fn ($v) => [PlateNormalizer::norm($v->plate) => $v]);

        foreach ($positions as &$p) {
            $v = $map->get($p['plateNorm'] ?? '');
            $p['matched']     = (bool) $v;
            $p['vehicleId']   = $v->id ?? null;
            $p['vehicleKind'] = $v->kind ?? null;
            $p['vehicleType'] = $v->type ?? null;   // 'MBF' | 'Ngoài'
        }
        unset($p);
    }
}
