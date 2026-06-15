<?php

namespace App\Services\Gps;

use App\Support\PlateNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Bình Anh GPS (dvbk.vn).
 * - Login/LoginProcess: POST form {txtLoginName,txtPass,...} → Set-Cookie: ASP.NET_SessionId + TrackingBKGPS.
 * - Home/get_AllTIBase: POST form {UserID} → mảng xe có sẵn Lt/Ln/Speed/Angle/NumberPlate/...
 */
class DvbkProvider extends AbstractGpsProvider
{
    private const LOGIN = 'https://dvbk.vn/Login/LoginProcess';
    private const DATA  = 'https://dvbk.vn/Home/get_AllTIBase';

    public function __construct() { parent::__construct('dvbk', 'Bình Anh GPS (dvbk.vn)'); }

    public function login(): ?string
    {
        $c = $this->config();
        if (empty($c['username']) || empty($c['password'])) return null;

        try {
            $resp = Http::timeout(15)->asForm()
                ->withHeaders([
                    'origin'           => 'https://dvbk.vn',
                    'referer'          => 'https://dvbk.vn/Login/Login',
                    'user-agent'       => $this->ua(),
                    'x-requested-with' => 'XMLHttpRequest',
                ])
                ->post(self::LOGIN, [
                    'txtLoginName'  => $c['username'],
                    'txtPass'       => $c['password'],
                    'txtPassLayer2' => '',
                    'SaveLogin'     => '1',
                ]);
        } catch (\Throwable $e) {
            $this->logWarn('login error', ['e' => $e->getMessage()]);
            return null;
        }

        $ck  = $this->cookieMap($resp);
        $sid = $ck['ASP.NET_SessionId'] ?? null;
        $trk = $ck['TrackingBKGPS'] ?? null;
        if (! $sid || ! $trk) return null;

        $cookie = 'ASP.NET_SessionId=' . $sid . '; TrackingBKGPS=' . $trk;
        $this->storeSession($cookie);
        return $cookie;
    }

    protected function fetchRaw(string $cookie): ?array
    {
        $c = $this->config();
        try {
            $resp = Http::timeout(25)->asForm()
                ->withHeaders([
                    'origin'           => 'https://dvbk.vn',
                    'referer'          => 'https://dvbk.vn/Home/Index',
                    'user-agent'       => $this->ua(),
                    'x-requested-with' => 'XMLHttpRequest',
                    'Cookie'           => $cookie,
                ])
                ->post(self::DATA, ['UserID' => $c['user_id'] ?? '']);
        } catch (\Throwable $e) {
            $this->logWarn('fetch error', ['e' => $e->getMessage()]);
            return null;
        }

        if (! $resp->ok()) return null;
        $j = $resp->json();
        if (! is_array($j)) return null;   // trả HTML trang login → phiên hỏng → relogin
        return $j;
    }

    protected function normalizeRaw(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (! is_array($r)) continue;
            $lat = $r['Lt'] ?? null; $lng = $r['Ln'] ?? null;
            if ($lat === null || $lng === null) continue;
            if ((float) $lat === 0.0 && (float) $lng === 0.0) continue;   // bỏ tọa độ rỗng

            $plate = $r['NumberPlate'] ?? '';
            $speed = (float) ($r['Speed'] ?? 0);
            $acc   = ($r['AccHard'] ?? '') === 'Bật';

            $out[] = [
                'provider'      => $this->key,
                'providerLabel' => $this->label(),
                'plate'         => $plate,
                'plateNorm'     => $r['NormalizedPlate'] ?: PlateNormalizer::norm($plate),
                'lat'           => (float) $lat,
                'lng'           => (float) $lng,
                'speed'         => $speed,
                'angle'         => (float) ($r['Angle'] ?? 0),
                'ignition'      => $acc,
                'status'        => $this->statusOf($acc, $speed),
                'driver'        => $r['DriverName'] ?? '',
                'address'       => $r['Address'] ?? '',
                'ts'            => $this->dvbkTs($r),
                'deviceId'      => $r['DeviceId'] ?? null,
            ];
        }
        return $out;
    }

    /** dvbk trả "Date" theo GIỜ VN ("HH:MM:SS dd/MM/yyyy"); RealDate /Date(ms)/ bị lệch +7h.
     *  Ưu tiên parse "Date" theo Asia/Ho_Chi_Minh → epoch ms chuẩn UTC. */
    private function dvbkTs(array $r): int
    {
        $s = trim((string) ($r['Date'] ?? ''));
        if ($s !== '') {
            try { return (int) Carbon::createFromFormat('H:i:s d/m/Y', $s, 'Asia/Ho_Chi_Minh')->getTimestampMs(); }
            catch (\Throwable) {}
        }
        return $this->parseMsDate($r['RealDate'] ?? null);
    }
}
