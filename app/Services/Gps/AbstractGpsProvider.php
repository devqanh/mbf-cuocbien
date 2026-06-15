<?php

namespace App\Services\Gps;

use App\Models\TruckingSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Adapter mỗi nhà cung cấp GPS. Mỗi provider tự lo:
 *   - login(): đăng nhập lấy cookie/token mới → lưu vào cache phiên.
 *   - fetchRaw($cookie): gọi API dữ liệu, trả mảng thô hoặc NULL khi phiên hỏng (→ relogin).
 *   - normalizeRaw($raw): map về shape vị trí chuẩn hóa dùng chung.
 *
 * Phiên (cookie) lưu ở Cache (TTL ngắn) — KHÔNG biết hạn thật của nhà cung cấp nên
 * chiến lược: dùng cookie cache; nếu fetch trả NULL (hết hạn) thì tự login lại 1 lần.
 *
 * Cấu hình + bí mật (username/password) lưu ở TruckingSetting key "gps.<key>" (JSON,
 * password mã hóa bằng Crypt). Xem [[ra-status-rule]] không liên quan; map biển số qua
 * PlateNormalizer ở GpsTrackingService.
 */
abstract class AbstractGpsProvider
{
    public function __construct(protected string $key, protected string $defaultLabel) {}

    /** Đăng nhập → trả chuỗi Cookie để dùng cho fetch, hoặc null nếu thất bại. */
    abstract public function login(): ?string;

    /** Gọi API dữ liệu với cookie hiện tại. Trả mảng thô, hoặc NULL nếu phiên hỏng/cần login lại. */
    abstract protected function fetchRaw(string $cookie): ?array;

    /** Chuẩn hóa dữ liệu thô → danh sách vị trí xe (shape dùng chung). */
    abstract protected function normalizeRaw(array $raw): array;

    public function key(): string { return $this->key; }

    public function label(): string { return $this->config()['label'] ?? $this->defaultLabel; }

    public function enabled(): bool { return (bool) ($this->config()['enabled'] ?? false); }

    /** Đã cấu hình đủ để đăng nhập? */
    public function configured(): bool
    {
        $c = $this->config();
        return ! empty($c['username']) && ! empty($c['password']);
    }

    /** Cấu hình provider (đã giải mã password). */
    public function config(): array
    {
        $raw = TruckingSetting::get('gps.' . $this->key);
        $cfg = $raw ? (json_decode($raw, true) ?: []) : [];
        if (! empty($cfg['password'])) {
            try { $cfg['password'] = Crypt::decryptString($cfg['password']); }
            catch (\Throwable) { $cfg['password'] = ''; }
        }
        return $cfg;
    }

    /** Lưu cấu hình (merge). password chỉ cập nhật khi truyền non-empty (mã hóa khi lưu). */
    public function saveConfig(array $data): void
    {
        $raw = TruckingSetting::get('gps.' . $this->key);
        $cur = $raw ? (json_decode($raw, true) ?: []) : [];

        $merged = array_merge($cur, collect($data)->except(['password'])->all());
        if (! empty($data['password'])) {
            $merged['password'] = Crypt::encryptString((string) $data['password']);
        }
        TruckingSetting::put('gps.' . $this->key, json_encode($merged, JSON_UNESCAPED_UNICODE));
        Cache::forget('gps.session.' . $this->key);   // buộc login lại với cấu hình mới
    }

    /** Cấu hình AN TOÀN để trả về client (ẩn password). */
    public function publicConfig(): array
    {
        $c = $this->config();
        return [
            'key'         => $this->key,
            'label'       => $c['label'] ?? $this->defaultLabel,
            'enabled'     => (bool) ($c['enabled'] ?? false),
            'configured'  => $this->configured(),
            'username'    => $c['username'] ?? '',
            'hasPassword' => ! empty($c['password']),
            'orgIds'      => $c['org_ids'] ?? '',
            'userId'      => $c['user_id'] ?? '',
            'sessionActive' => (bool) $this->session(),
        ];
    }

    // ---- phiên (cookie) ----
    protected function session(): ?string { return Cache::get('gps.session.' . $this->key); }

    protected function storeSession(string $cookie): void
    {
        // Cache token LÂU DÀI — KHÔNG login lại theo lịch. Chỉ login lại khi fetchRaw()
        // trả NULL (token hết hạn) → relogin + ghi đè token mới. Tránh đăng nhập thừa.
        Cache::put('gps.session.' . $this->key, $cookie, now()->addDays(30));
    }

    /**
     * Lấy vị trí xe: dùng cookie cache; nếu chưa có thì login; nếu fetch trả NULL
     * (phiên hết hạn) thì login lại đúng 1 lần rồi fetch lại.
     */
    public function fetchPositions(): array
    {
        $cookie = $this->session() ?: $this->login();
        if (! $cookie) return [];

        $raw = $this->fetchRaw($cookie);
        if ($raw === null) {
            $cookie = $this->login();
            if (! $cookie) return [];
            $raw = $this->fetchRaw($cookie);
            if ($raw === null) return [];
        }
        return $this->normalizeRaw($raw);
    }

    /** Test kết nối: login mới + đếm số xe lấy được. */
    public function test(): array
    {
        Cache::forget('gps.session.' . $this->key);
        $cookie = $this->login();
        if (! $cookie) return ['ok' => false, 'count' => 0, 'error' => 'Đăng nhập thất bại (kiểm tra tài khoản/mật khẩu).'];
        $raw = $this->fetchRaw($cookie);
        if ($raw === null) return ['ok' => false, 'count' => 0, 'error' => 'Đăng nhập OK nhưng không lấy được dữ liệu.'];
        return ['ok' => true, 'count' => count($this->normalizeRaw($raw)), 'error' => null];
    }

    // ---- helpers dùng chung ----

    /** Map [tên cookie => giá trị] từ response (Set-Cookie). */
    protected function cookieMap($resp): array
    {
        $m = [];
        try {
            foreach ($resp->cookies()->toArray() as $c) { $m[$c['Name']] = $c['Value']; }
        } catch (\Throwable) {}
        return $m;
    }

    /** Phân loại trạng thái gọn: off (tắt máy) | run (đang chạy) | idle (nổ máy, đứng yên). */
    protected function statusOf(bool $ignition, float $speed): string
    {
        if (! $ignition) return 'off';
        return $speed > 1 ? 'run' : 'idle';
    }

    /** Parse "/Date(1781556840000)/" hoặc số → epoch ms. */
    protected function parseMsDate($v): int
    {
        if (is_numeric($v)) return (int) $v;
        if (is_string($v) && preg_match('/(\d{10,})/', $v, $m)) return (int) $m[1];
        return 0;
    }

    protected function ua(): string
    {
        return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36';
    }

    protected function logWarn(string $msg, array $ctx = []): void
    {
        Log::channel('single')->warning('[GPS:' . $this->key . '] ' . $msg, $ctx);
    }
}
