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

    /**
     * Vị trí + trạng thái provider.
     * Cache 10s CHỈ KHI lấy được vị trí; nếu RỖNG/LỖI (token hết hạn, mạng chập…) chỉ cache 2s
     * → reload thử lại ngay, KHÔNG bị "ghim" trạng thái trống 10s (lỗi load lần đầu phải reload nhiều lần).
     */
    public function snapshot(): array
    {
        $cached = Cache::get('gps.snapshot');
        if (is_array($cached)) return $cached;

        $snap = $this->buildSnapshot();
        $ok = ! empty($snap['positions']);
        Cache::put('gps.snapshot', $snap, $ok ? 10 : 2);
        return $snap;
    }

    /** Gọi mọi provider, gom vị trí + trạng thái (không cache — caller quyết định). */
    protected function buildSnapshot(): array
    {
        {
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

            // version = chữ ký vị trí + trạng thái provider → client gửi ?v= để bỏ qua khi KHÔNG đổi.
            // (KHÔNG đưa ts vào version để dữ liệu y hệt vẫn cùng version qua các chu kỳ cache.)
            $sig = '';
            foreach ($positions as $p) {
                $sig .= ($p['provider'] ?? '') . ':' . ($p['plateNorm'] ?? '') . ':' . ($p['lat'] ?? '') . ',' . ($p['lng'] ?? '') . ':' . ($p['status'] ?? '') . ';';
            }
            foreach ($status as $st) {
                $sig .= 'P' . ($st['key'] ?? '') . ':' . (! empty($st['ok']) ? 1 : 0) . ':' . ($st['count'] ?? 0) . ';';
            }

            return [
                'positions' => $positions,
                'providers' => $status,
                'ts'        => (int) (microtime(true) * 1000),
                'version'   => substr(md5($sig), 0, 16),
            ];
        }
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

    /** Thời gian QUAN SÁT THỰC trong kho (giây) = last_inside − arrived, tính bằng timestamp (an toàn dấu). */
    private function dwellSeconds(TruckingWarehouseVisit $v): int
    {
        if (! $v->arrived_at || ! $v->last_inside_at) return 0;
        return max(0, $v->last_inside_at->getTimestamp() - $v->arrived_at->getTimestamp());
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
                // RỜI: xe ĐANG CHẠY ra xa → đóng sớm (bán kính chặt = parked 1000m, tránh "đã rời mà vẫn ở kho");
                //       xe ĐỖ → giữ rộng (exit 1500m, có thể đỗ trong khuôn viên kho cách điểm ghim).
                $leaveR = ((float) ($p['speed'] ?? 0) > 5) ? $parkedKm : $exitKm;
                if ($dist > $leaveR) {
                    // RỜI: đủ dwell (thời gian quan sát thực ≥ ngưỡng) → đóng & GIỮ; chưa đủ → tạt ngang, xóa.
                    if ($open->confirmed || $this->dwellSeconds($open) >= $dwellMin * 60) {
                        $open->update(['departed_at' => $open->last_inside_at ?? $ts, 'confirmed' => true]); $closed++;
                    } else { $open->delete(); $dropped++; }
                } else {
                    $attrs = ['last_inside_at' => $ts, 'min_dist_m' => min($open->min_dist_m ?? PHP_INT_MAX, (int) round($dist * 1000))];
                    // XÁC NHẬN theo THỜI GIAN QUAN SÁT THỰC (last_inside − arrived) ≥ dwell — dùng timestamp (an toàn dấu,
                    // KHÔNG dùng Carbon diffInMinutes vì Carbon 3 trả số ÂM khi so now→quá khứ) & không phụ thuộc nhịp quét.
                    if (! $open->confirmed && $open->arrived_at && ($ts->getTimestamp() - $open->arrived_at->getTimestamp()) >= $dwellMin * 60) $attrs['confirmed'] = true;
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

        // TỰ ĐÓNG VISIT TREO: xe mất tín hiệu/tắt máy khi đang mở visit sẽ KHÔNG còn trong snapshot
        // → vòng lặp trên không chạm tới → departed_at mãi null ("Đang ở kho" vĩnh viễn). Quét riêng:
        // visit còn mở mà last_inside_at quá cũ (không xác nhận trong kho > ngưỡng) → coi như đã rời.
        $autoCloseMin = (int) TruckingSetting::get('gps.visit_autoclose_min', 120);
        if ($autoCloseMin > 0) {
            $cutoff = Carbon::now()->subMinutes($autoCloseMin);
            foreach (TruckingWarehouseVisit::whereNull('departed_at')->where('last_inside_at', '<', $cutoff)->get() as $v) {
                // Đủ thời gian quan sát thực ≥ dwell → đóng & GIỮ (đừng xóa visit thật); chỉ xóa tạt-ngang treo.
                if ($v->confirmed || $this->dwellSeconds($v) >= $dwellMin * 60) {
                    $v->update(['departed_at' => $v->last_inside_at ?? $v->arrived_at, 'confirmed' => true]); $closed++;
                } else { $v->delete(); $dropped++; }
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
    /** Áp bộ lọc chung (xác nhận/đang mở + tìm kiếm + khoảng ngày theo arrived_at) cho cả list & stats. */
    private function visitFilter(?string $q, ?string $from, ?string $to)
    {
        $query = TruckingWarehouseVisit::where(function ($w) {
            $w->where('confirmed', true)->orWhereNull('departed_at');   // đã xác nhận HOẶC đang mở
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
        if ($from = trim((string) $from)) $query->whereDate('arrived_at', '>=', $from);
        if ($to = trim((string) $to))     $query->whereDate('arrived_at', '<=', $to);
        return $query;
    }

    public function visitHistoryPaged(?string $q, int $page = 1, int $perPage = 30, ?string $from = null, ?string $to = null): array
    {
        $query = $this->visitFilter($q, $from, $to);
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

    /**
     * THỐNG KÊ theo XE trong khoảng ngày: mỗi xe = số chuyến (lượt ghé kho), số kho khác nhau,
     * tổng thời gian ở kho (dwell), lần ghé gần nhất. Gom theo vehicle_id (fallback gps_ref).
     */
    public function visitStats(?string $q, ?string $from, ?string $to): array
    {
        $visits = $this->visitFilter($q, $from, $to)->orderBy('arrived_at')->get();
        $g = [];
        foreach ($visits as $v) {
            $key = $v->vehicle_id ? ('v' . $v->vehicle_id) : ('g:' . $v->gps_ref);
            if (! isset($g[$key])) {
                $g[$key] = ['plate' => $v->vehicle_plate ?: $v->gps_plate, 'driver' => $v->driver, 'matched' => (bool) $v->vehicle_id,
                            'trips' => 0, 'wh' => [], 'whName' => [], 'days' => [], 'dwellMs' => 0, 'last' => null, 'visits' => []];
            }
            $g[$key]['trips']++;
            $vEnd = $v->departed_at ?? $v->last_inside_at;
            $g[$key]['visits'][] = [                                    // chi tiết từng lượt (cho phần mở rộng)
                'warehouse'  => $v->warehouse_name,
                'arrivedAt'  => $v->arrived_at ? $v->arrived_at->getTimestampMs() : null,
                'departedAt' => $v->departed_at ? $v->departed_at->getTimestampMs() : null,
                'dwellMs'    => ($v->arrived_at && $vEnd) ? max(0, $vEnd->getTimestampMs() - $v->arrived_at->getTimestampMs()) : null,
                'open'       => $v->departed_at === null,
            ];
            if ($v->warehouse_id) {                                     // lộ trình kho: đếm số lượt mỗi kho
                $g[$key]['wh'][$v->warehouse_id] = ($g[$key]['wh'][$v->warehouse_id] ?? 0) + 1;
                $g[$key]['whName'][$v->warehouse_id] = $v->warehouse_name;
            }
            if ($v->arrived_at) $g[$key]['days'][$v->arrived_at->format('Y-m-d')] = true;   // số ngày hoạt động
            if ($v->driver) $g[$key]['driver'] = $v->driver;            // giữ tài xế gần nhất
            $end = $v->departed_at ?? $v->last_inside_at;               // visit đang mở → tính tới lần thấy cuối
            if ($v->arrived_at && $end) $g[$key]['dwellMs'] += max(0, $end->getTimestampMs() - $v->arrived_at->getTimestampMs());
            $am = $v->arrived_at ? $v->arrived_at->getTimestampMs() : null;
            if ($am && (! $g[$key]['last'] || $am > $g[$key]['last'])) $g[$key]['last'] = $am;
        }
        $rows = array_values(array_map(function ($x) {
            $whTop = [];
            foreach ($x['wh'] as $wid => $cnt) $whTop[] = ['name' => $x['whName'][$wid] ?: ('#' . $wid), 'count' => $cnt];
            usort($whTop, fn ($a, $b) => $b['count'] <=> $a['count']);   // kho hay ghé nhất lên đầu
            return [
                'plate'      => $x['plate'] ?: '(không rõ)',
                'driver'     => $x['driver'],
                'matched'    => $x['matched'],
                'trips'      => $x['trips'],
                'warehouses' => count($x['wh']),
                'whTop'      => $whTop,
                'days'       => count($x['days']),
                'dwellMin'   => (int) round($x['dwellMs'] / 60000),
                'lastVisit'  => $x['last'],
                'visits'     => array_reverse($x['visits']),            // mới nhất lên đầu
            ];
        }, $g));
        usort($rows, fn ($a, $b) => $b['trips'] <=> $a['trips']);       // nhiều chuyến nhất lên đầu

        return [
            'rows'   => $rows,
            'totals' => ['vehicles' => count($rows), 'trips' => array_sum(array_column($rows, 'trips'))],
        ];
    }
}
