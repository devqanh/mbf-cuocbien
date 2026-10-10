<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\TruckingSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nhật ký thao tác: gom mọi thay đổi model (khai ở config/activity.php, gắn event ở AppServiceProvider) + 1 dòng cho request ghi (middleware LogActivity)
 * vào BỘ ĐỆM, rồi insert 1 lần khi app terminate (sau khi đã trả response) → không thêm query giữa request.
 * Request lỗi (status >= 400) chỉ giữ dòng request, BỎ các dòng diff (thay đổi đó thường đã rollback, vd chặn
 * hạ xe MBF). Ghi log hỏng không bao giờ làm hỏng thao tác chính. Singleton — đăng ký ở AppServiceProvider.
 */
class ActivityLogger
{
    private const EVENT_VERB = ['created' => 'tạo', 'updated' => 'sửa', 'deleted' => 'xóa'];
    /** Lệnh artisan không ghi nhật ký (tự sinh dữ liệu hệ thống / chính lệnh nhật ký). */
    private const SKIP_COMMANDS = ['migrate', 'db:', 'activity:', 'queue:', 'schedule:', 'snapshots:', 'optimize', 'config:', 'cache:', 'view:', 'route:'];

    private string $requestId;
    private array $rows = [];
    private int $dropped = 0;
    private ?array $requestRow = null;
    private ?int $status = null;
    private ?bool $enabled = null;
    private $actorUser = null;

    public function __construct()
    {
        $this->requestId = (string) Str::uuid();
    }

    public function enabled(): bool
    {
        if ($this->enabled !== null) return $this->enabled;
        try {
            $on = TruckingSetting::bool('sys.activity_enabled', true);
        } catch (\Throwable) {
            $on = false;   // bảng chưa có (đang migrate) → tắt
        }
        if ($on && app()->runningInConsole() && ! app()->runningUnitTests()) {
            $cmd = (string) ($_SERVER['argv'][1] ?? '');
            foreach (self::SKIP_COMMANDS as $p) if ($cmd === '' || str_starts_with($cmd, $p)) { $on = false; break; }
        }
        return $this->enabled = $on;
    }

    /** Gọi từ trait LogsActivity khi model created/updated/deleted. */
    public function model(string $event, Model $model): void
    {
        if (! $this->enabled()) return;
        try {
            $cfg = config('activity.models.' . get_class($model), []);
            $skip = array_merge(['created_at', 'updated_at'], $cfg['ignore'] ?? [], $model->getHidden());
            $changes = [];
            if ($event === 'updated') {
                foreach ($model->getChanges() as $k => $new) {
                    if (in_array($k, $skip, true)) continue;
                    $changes[$k] = [$this->val($model->getRawOriginal($k)), $this->val($new)];
                }
            } else {
                // Tạo: ghi giá trị ban đầu; xóa: ghi TOÀN BỘ bản ghi cũ (đủ để khôi phục tay khi xóa nhầm).
                $attrs = $event === 'deleted' ? $model->getRawOriginal() : $model->getAttributes();
                foreach ($attrs as $k => $v) {
                    if (in_array($k, $skip, true) || $v === null || $v === '') continue;
                    $changes[$k] = $event === 'deleted' ? [$this->val($v), null] : [null, $this->val($v)];
                }
            }
            if ($event === 'updated' && ! $changes) return;
            $changes = $this->maskChanges($model, $changes);

            if (count($this->rows) >= (int) config('activity.max_rows_per_request', 500)) { $this->dropped++; return; }
            $label = $this->subjectLabel($model, $cfg);
            $this->rows[] = [
                'event'         => $event,
                'subject_type'  => class_basename($model),
                'subject_id'    => $model->getKey(),
                'subject_label' => Str::limit($label, 250, '…'),
                'changes'       => $changes,
                'summary'       => Str::limit($label . ': đã ' . self::EVENT_VERB[$event] . ($event === 'updated' ? ' — ' . $this->describe($changes) : ''), 2000, '…'),
            ];
        } catch (\Throwable $e) {
            Log::warning('activity-log model: ' . $e->getMessage());
        }
    }

    /** Gọi từ middleware sau khi có response. $user = người đăng nhập trước request (giữ được khi request là đăng xuất). */
    public function captureRequest(Request $request, Response $response, $user): void
    {
        $this->status = $response->getStatusCode();
        $this->actorUser = auth()->user() ?: $user;
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true) || ! $this->enabled()) return;
        try {
            $route = optional($request->route())->getName() ?: $request->path();
            $this->requestRow = [
                'event'   => 'request',
                'changes' => $this->payload($request),
                'summary' => $request->method() . ' ' . $route . ' → ' . $this->status,
            ];
        } catch (\Throwable $e) {
            Log::warning('activity-log request: ' . $e->getMessage());
        }
    }

    /** Ghi bộ đệm xuống DB (app terminating) rồi làm rỗng. */
    public function flush(): void
    {
        $rows = $this->rows;
        if ($this->status !== null && $this->status >= 400) $rows = [];   // request lỗi → thay đổi đã rollback
        $req = $this->requestRow;
        $dropped = $this->dropped;
        $this->rows = []; $this->requestRow = null; $this->dropped = 0;
        if (! $rows && ! $req) return;

        try {
            $ctx = $this->context();
            if ($req && $dropped) $req['summary'] .= " (bỏ bớt {$dropped} dòng thay đổi vượt trần)";
            $now = now();
            $out = [];
            foreach (array_merge($req ? [$req] : [], $rows) as $r) {
                $out[] = array_merge(['subject_type' => null, 'subject_id' => null, 'subject_label' => null], $ctx, $r, ['created_at' => $now]);
            }
            foreach ($out as &$o) {
                $o['changes'] = $o['changes'] === null ? null : json_encode($o['changes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $o['summary'] = Str::limit(($ctx['actor'] ?? '?') . ' · ' . $o['summary'], 4000, '…');
            }
            unset($o);
            foreach (array_chunk($out, 200) as $chunk) ActivityLog::insert($chunk);
        } catch (\Throwable $e) {
            Log::warning('activity-log flush: ' . $e->getMessage());
        }
    }

    /** Thông tin chung của request/lệnh hiện tại. */
    private function context(): array
    {
        if (app()->runningInConsole()) {
            $cmd = trim(implode(' ', array_slice($_SERVER['argv'] ?? [], 1)));
            return ['request_id' => $this->requestId, 'user_id' => null, 'actor' => Str::limit('Lệnh: ' . ($_SERVER['argv'][1] ?? ''), 180, '…'),
                'module' => 'artisan', 'route' => Str::limit($cmd, 110, '…'), 'method' => 'CLI', 'ip' => null, 'user_agent' => null, 'status' => null];
        }
        $request = request();
        $user = $this->actorUser ?: auth()->user();
        $path = trim($request->path(), '/');
        $seg = explode('/', $path);
        $module = ($seg[0] ?? '') === 'trucking-v2' ? ($seg[1] ?? 'trucking-v2') : ($seg[0] ?? '');
        return [
            'request_id' => $this->requestId,
            'user_id'    => $user?->id,
            'actor'      => $user ? Str::limit($user->name, 180, '…') : 'Khách (chưa đăng nhập / link công khai)',
            'module'     => Str::limit($module ?: '/', 58, ''),
            'route'      => Str::limit(optional($request->route())->getName() ?: $path, 118, ''),
            'method'     => $request->method(),
            'ip'         => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'status'     => $this->status,
        ];
    }

    private function subjectLabel(Model $model, array $cfg): string
    {
        $label = ($cfg['label'] ?? class_basename($model)) . ' #' . $model->getKey();
        $title = collect($cfg['title'] ?? ['name', 'plate', 'code', 'no'])
            ->map(fn ($c) => trim((string) $model->getAttribute($c)))->filter()->implode(' · ');
        if ($title !== '') $label .= ' ' . $title;
        if (isset($cfg['parent'])) {
            [$col, $name] = $cfg['parent'];
            if ($model->getAttribute($col)) $label .= " (thuộc {$name} #" . $model->getAttribute($col) . ')';
        }
        return $label;
    }

    /** "Loại: MBF → Ngoài; GPS: dvbk:1 → (trống)" */
    private function describe(array $changes): string
    {
        $fields = config('activity.fields', []);
        $show = fn ($v) => $v === null || $v === '' ? '(trống)' : Str::limit(is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), 80, '…');
        return collect($changes)->map(fn ($p, $k) => ($fields[$k] ?? $k) . ': ' . $show($p[0]) . ' → ' . $show($p[1]))->implode('; ');
    }

    private function val($v)
    {
        if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d H:i:s');
        if (is_bool($v)) return $v ? 1 : 0;
        if (is_array($v) || is_object($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE);
        return is_string($v) ? Str::limit($v, 1000, '…') : $v;
    }

    private function isSecret(string $key): bool
    {
        $k = strtolower($key);
        foreach (config('activity.mask', []) as $m) if (str_contains($k, $m)) return true;
        return false;
    }

    private function maskChanges(Model $model, array $changes): array
    {
        // Cấu hình key/value: che theo TÊN KHÓA của dòng (vd sys.s3_secret, gps.google_maps_key)
        $secretRow = $model instanceof TruckingSetting && $this->isSecret((string) $model->getAttribute('key'));
        foreach ($changes as $k => $pair) {
            if ($this->isSecret($k) || ($secretRow && $k === 'value')) {
                $changes[$k] = array_map(fn ($v) => $v === null ? null : '***', $pair);
            }
        }
        return $changes;
    }

    /** Payload request đã che bí mật; file → tên; quá lớn → chỉ giữ tóm tắt từng khóa. */
    private function payload(Request $request): ?array
    {
        $clean = function ($data, $depth = 0) use (&$clean) {
            if ($data instanceof UploadedFile) return '[file ' . $data->getClientOriginalName() . ']';
            if (! is_array($data)) return is_string($data) ? Str::limit($data, 300, '…') : $data;
            if ($depth > 4) return '[…]';
            $out = [];
            foreach ($data as $k => $v) $out[$k] = (is_string($k) && $this->isSecret($k)) ? '***' : $clean($v, $depth + 1);
            return $out;
        };
        $data = $clean($request->except(['_token', '_method']));
        if (! $data) return null;
        if (strlen((string) json_encode($data, JSON_UNESCAPED_UNICODE)) <= (int) config('activity.max_payload_bytes', 16000)) return $data;
        return ['_truncated' => true] + collect($data)->map(fn ($v) => is_array($v) ? '[' . count($v) . ' phần tử]' : $v)->all();
    }
}
