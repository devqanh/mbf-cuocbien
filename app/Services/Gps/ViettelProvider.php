<?php

namespace App\Services\Gps;

use App\Support\PlateNormalizer;
use Illuminate\Support\Facades\Http;

/**
 * Viettel vTracking 2 (vtracking2.viettel.vn).
 * - login1: POST JSON {username,password} → Set-Cookie: presence=<JWT>.
 * - portDataWithParamAndProjectId: proxy gọi /api/devices/vtracking/vehicle/filter.
 *   Vị trí nằm trong attribute "datas" (JSON: latitude/longitude/speed/direction/...).
 */
class ViettelProvider extends AbstractGpsProvider
{
    private const LOGIN = 'https://vtracking2.viettel.vn/login1';
    private const PORT  = 'https://vtracking2.viettel.vn/portDataWithParamAndProjectId';

    public function __construct() { parent::__construct('viettel', 'Viettel vTracking'); }

    public function login(): ?string
    {
        $c = $this->config();
        if (empty($c['username']) || empty($c['password'])) return null;

        try {
            $resp = Http::timeout(15)
                ->withHeaders([
                    'content-type'     => 'application/json; charset=UTF-8',
                    'origin'           => 'https://vtracking2.viettel.vn',
                    'referer'          => 'https://vtracking2.viettel.vn/',
                    'user-agent'       => $this->ua(),
                    'x-requested-with' => 'XMLHttpRequest',
                ])
                ->post(self::LOGIN, ['username' => $c['username'], 'password' => $c['password']]);
        } catch (\Throwable $e) {
            $this->logWarn('login error', ['e' => $e->getMessage()]);
            return null;
        }

        $presence = $this->cookieMap($resp)['presence'] ?? null;
        if (! $presence) return null;

        $cookie = 'PLAY_LANG=vi; presence=' . $presence;
        $this->storeSession($cookie);
        return $cookie;
    }

    protected function fetchRaw(string $cookie): ?array
    {
        $c = $this->config();
        $org = $c['org_ids'] ?? [];
        if (is_string($org)) $org = array_values(array_filter(array_map('trim', preg_split('/[,\s]+/', $org))));

        try {
            $resp = Http::timeout(25)
                ->withHeaders([
                    'content-type'     => 'application/json; charset=UTF-8',
                    'origin'           => 'https://vtracking2.viettel.vn',
                    'referer'          => 'https://vtracking2.viettel.vn/monitorMapV2',
                    'user-agent'       => $this->ua(),
                    'x-requested-with' => 'XMLHttpRequest',
                    'Cookie'           => $cookie,
                ])
                ->post(self::PORT, [
                    'param' => '/api/devices/vtracking/vehicle/filter?limit=1000&offset=0&expand=true&getAllAttributes=true',
                    'body'  => ['org_ids' => $org, 'vehicle_type' => '', 'status' => [], 'svc_status' => ['expired']],
                ]);
        } catch (\Throwable $e) {
            $this->logWarn('fetch error', ['e' => $e->getMessage()]);
            return null;
        }

        if (! $resp->ok()) return null;
        $j = $resp->json();
        if (! is_array($j) || ! isset($j['content']['vehicles'])) return null;   // chưa đăng nhập / lỗi → relogin
        return $j['content']['vehicles'];
    }

    protected function normalizeRaw(array $vehicles): array
    {
        $out = [];
        foreach ($vehicles as $v) {
            $attrs = [];
            foreach (($v['attributes'] ?? []) as $a) { $attrs[$a['attribute_key']] = $a['value'] ?? null; }

            $d = $attrs['datas'] ?? null;
            if (is_string($d)) $d = json_decode($d, true);
            if (! is_array($d)) $d = [];

            $lat = $d['latitude'] ?? null; $lng = $d['longitude'] ?? null;
            if ($lat === null || $lng === null) continue;   // chưa có vị trí

            $plate = $v['license_plate'] ?: ($attrs['plateNo'] ?? '');
            $speed = (float) ($d['speed'] ?? 0);
            $ign   = (bool) ($d['ignition'] ?? false);

            $out[] = [
                'provider'      => $this->key,
                'providerLabel' => $this->label(),
                'plate'         => $plate,
                'plateNorm'     => PlateNormalizer::norm($plate),
                'lat'           => (float) $lat,
                'lng'           => (float) $lng,
                'speed'         => $speed,
                'angle'         => (float) ($d['direction'] ?? 0),
                'ignition'      => $ign,
                'status'        => $this->statusOf($ign, $speed),
                'driver'        => $attrs['driverName'] ?? '',
                'address'       => $d['geocoding'] ?? '',
                'ts'            => (int) ($d['timestamp'] ?? ($attrs['timestamp'] ?? 0)),
                'deviceId'      => $v['device_id'] ?? null,
            ];
        }
        return $out;
    }
}
