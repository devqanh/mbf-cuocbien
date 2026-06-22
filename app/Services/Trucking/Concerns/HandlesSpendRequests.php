<?php

namespace App\Services\Trucking\Concerns;

use App\Models\TruckingContType;
use App\Models\TruckingCostItem;
use App\Models\TruckingCostLine;
use App\Models\TruckingChohoItem;
use App\Models\TruckingCustomer;
use App\Models\TruckingDriver;
use App\Models\TruckingLocation;
use App\Models\TruckingPayer;
use App\Models\TruckingPriceRow;
use App\Models\TruckingFuelPrice;
use App\Models\TruckingRevenueItem;
use App\Models\TruckingRouteFee;
use App\Models\TruckingSalaryItem;
use App\Models\TruckingTripCostBatch;
use App\Models\TruckingTripCostLine;
use App\Models\TruckingVehicleCost;
use App\Models\TruckingVehicleDepreciation;
use App\Models\TruckingVehicleUsage;
use App\Models\TruckingSetting;
use App\Models\TruckingAttachment;
use App\Models\TruckingPlanLink;
use App\Models\TruckingShipment;
use App\Models\TruckingShipmentWarehouse;
use App\Models\TruckingVehicleCostType;
use App\Models\TruckingAssetCategory;
use App\Support\Hashid;
use App\Models\TruckingStatement;
use App\Models\TruckingVehicle;
use App\Models\TruckingWarehouse;
use App\Models\User;
use App\Notifications\SpendRequestCreatedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/** Tách từ TruckingV2Service — nhóm HandlesSpendRequests. */
trait HandlesSpendRequests
{

    /** # hóa đơn kế tiếp (PC-XXXX) toàn hệ thống. */
    public function nextCostInvoiceNo(): string
    {
        $maxN = 0;
        foreach (TruckingVehicleCost::where('invoice_no', 'like', 'PC-%')->pluck('invoice_no') as $no) {
            if (preg_match('/^PC-(\d+)$/', trim((string) $no), $m)) $maxN = max($maxN, (int) $m[1]);
        }
        return 'PC-' . str_pad((string) ($maxN + 1), 4, '0', STR_PAD_LEFT);
    }

    /** KM của phiếu ĐÃ DUYỆT gần nhất cùng loại (theo tên) của xe — để check định mức. */
    public function lastApprovedKm(int $vehicleId, string $costItem): ?float
    {
        $name = mb_strtolower(trim($costItem));
        $row = TruckingVehicleCost::where('vehicle_id', $vehicleId)->where('approved', true)->whereNotNull('current_km')
            ->get()->filter(fn ($c) => mb_strtolower(trim((string) $c->name)) === $name)
            ->sortByDesc(fn ($c) => (float) $c->current_km)->first();
        return $row ? (float) $row->current_km : null;
    }

    /** Dữ liệu cho trang PUBLIC gửi yêu cầu chi (xe MBF + tài sản + danh mục khoản chi). */
    public function publicRequestData(): array
    {
        return [
            'vehicles'  => TruckingVehicle::where('type', 'MBF')->orderBy('plate')->get(['id', 'plate'])
                ->map(fn ($v) => ['id' => $v->id, 'plate' => $v->plate])->all(),
            'assets'    => TruckingVehicle::where('kind', 'asset')->orderBy('plate')->get(['id', 'plate', 'info'])
                ->map(fn ($v) => ['id' => $v->id, 'code' => $v->plate, 'name' => (is_array($v->info) ? ($v->info['name'] ?? '') : '') ?: $v->plate])->all(),
            // Loại chi phí = danh mục LOẠI CHI PHÍ XE (cai-dat#vehicleCostTypes) — đồng bộ với phiếu chi xe & định mức km.
            'costItems' => $this->vehicleCostTypesOut(),
        ];
    }

    /** Tạo YÊU CẦU CHI (phiếu chi chờ duyệt) từ trang public — xe (CHECK định mức km) hoặc tài sản. */
    public function createSpendRequest(array $in, array $files = []): array
    {
        $v = TruckingVehicle::where('id', (int) ($in['vehicleId'] ?? 0))
            ->where(fn ($q) => $q->where('type', 'MBF')->orWhere('kind', 'asset'))->first();
        if (! $v) return ['ok' => false, 'message' => 'Đối tượng không hợp lệ.'];
        $item = trim((string) ($in['costItem'] ?? ''));
        if ($item === '' || ! in_array($item, $this->vehicleCostTypesOut(), true)) return ['ok' => false, 'message' => 'Loại chi phí không hợp lệ.'];
        $amount = $this->inMoney($in['amount'] ?? null) ?? 0;
        if ($amount <= 0) return ['ok' => false, 'message' => 'Vui lòng nhập số tiền.'];
        $km = (isset($in['km']) && $in['km'] !== '') ? (float) preg_replace('/[^\d.]/', '', (string) $in['km']) : null;

        // Định mức km của xe cho loại này
        $allow = 0;
        foreach ((is_array($v->allowances) ? $v->allowances : []) as $a) {
            if (mb_strtolower(trim((string) ($a['costItem'] ?? ''))) === mb_strtolower($item)) { $allow = (int) ($a['km'] ?? 0); break; }
        }
        if ($allow > 0) {
            if ($km === null) return ['ok' => false, 'message' => "Khoản “{$item}” có định mức {$allow} km — vui lòng nhập KM hiện tại."];
            $lastKm = $this->lastApprovedKm($v->id, $item);
            if ($lastKm !== null && ($km - $lastKm) < $allow) {
                $g = fn ($n) => number_format((float) $n, 0, '.', '.');
                return ['ok' => false, 'message' => "Chưa đủ định mức: “{$item}” cần đi thêm ≥ {$g($allow)} km kể từ lần trước (km {$g($lastKm)}). Hiện mới +{$g(max(0, $km - $lastKm))} km."];
            }
        }

        $sort = (int) ($v->vehicleCosts()->max('sort') ?? -1) + 1;
        $cost = $v->vehicleCosts()->create([
            'name' => $item, 'created_by' => auth()->id(), 'invoice_no' => $this->nextCostInvoiceNo(), 'kind' => 'fixed',
            'spend_date' => $this->inDate($in['date'] ?? null) ?? now()->toDateString(),
            'amount' => $amount, 'current_km' => $km, 'note' => trim((string) ($in['note'] ?? '')),
            'approved' => false, 'paid' => false, 'sort' => $sort,
        ]);
        if ($files) { $cost->photos = array_map(fn ($p) => $p['id'], $this->storeCostPhotos($v, $files)); $cost->save(); }

        // Thông báo cho người duyệt chi (quyền settings.update) — best-effort, không chặn việc gửi.
        try {
            $approvers = User::permission('settings.update')->get();
            if ($approvers->isNotEmpty()) {
                Notification::send($approvers, new SpendRequestCreatedNotification($cost, $v));
            }
        } catch (\Throwable $e) {
            Log::channel('single')->warning('Notify spend request failed', [
                'cost_id' => $cost->id, 'vehicle_id' => $v->id, 'error' => $e->getMessage(),
            ]);
        }

        $label = $v->kind === 'asset'
            ? 'tài sản ' . ((is_array($v->info) ? ($v->info['name'] ?? '') : '') ?: $v->plate)
            : 'xe ' . $v->plate;
        return ['ok' => true, 'message' => "Đã gửi yêu cầu chi “{$item}” cho {$label}. Kế toán sẽ duyệt sau."];
    }

    /** Trạng thái phiếu chi: cancelled | paid | approved | pending (+ nhãn VN). */
    public function vehicleCostStatus(TruckingVehicleCost $c): array
    {
        if ($c->cancelled_at) return ['code' => 'cancelled', 'label' => 'Đã hủy'];
        if ($c->paid)         return ['code' => 'paid',      'label' => 'Đã chi'];
        if ($c->approved)     return ['code' => 'approved',  'label' => 'Đã duyệt'];
        return ['code' => 'pending', 'label' => 'Chờ duyệt'];
    }

    /** Lịch sử yêu cầu chi CỦA 1 user (mobile) — phiếu do chính họ gửi. */
    public function spendRequestHistory(int $userId): array
    {
        return TruckingVehicleCost::with('vehicle:id,plate,kind,info')
            ->where('created_by', $userId)->orderByDesc('id')->limit(100)->get()
            ->map(function ($c) {
                $st = $this->vehicleCostStatus($c);
                $isAsset = ($c->vehicle?->kind ?? 'vehicle') === 'asset';
                $vinfo = is_array($c->vehicle?->info) ? $c->vehicle->info : [];
                return [
                    'id' => $c->id, 'hashid' => Hashid::encode($c->id), 'vehicleId' => $c->vehicle_id, 'plate' => $c->vehicle?->plate ?? '', 'name' => $c->name ?? '',
                    'kind' => $isAsset ? 'asset' : 'vehicle',
                    'targetName' => $isAsset ? (($vinfo['name'] ?? '') ?: ($c->vehicle?->plate ?? '')) : ($c->vehicle?->plate ?? ''),
                    'note' => $c->note ?? '',
                    'invoiceNo' => $c->invoice_no ?? '', 'amount' => $this->outMoney($c->amount),
                    'date' => $this->outDate($c->spend_date), 'km' => $this->outNum($c->current_km),
                    'status' => $st['code'], 'statusLabel' => $st['label'],
                    'canCancel' => $st['code'] === 'pending', 'canEdit' => $st['code'] === 'pending',   // chưa duyệt mới sửa/hủy được
                    'photos' => $this->costPhotosOut(is_array($c->photos) ? $c->photos : [], $c->vehicle_id),
                ];
            })->all();
    }

    /** Tài xế hủy phiếu CỦA MÌNH — chỉ khi đang "Chờ duyệt". */
    public function cancelSpendRequestByOwner(int $userId, int $costId): array
    {
        $c = TruckingVehicleCost::where('id', $costId)->where('created_by', $userId)->first();
        if (! $c) return ['ok' => false, 'message' => 'Không tìm thấy phiếu của bạn.'];
        if ($c->cancelled_at) return ['ok' => false, 'message' => 'Phiếu đã hủy trước đó.'];
        if ($c->approved || $c->paid) return ['ok' => false, 'message' => 'Phiếu đã được duyệt/chi — không thể tự hủy. Liên hệ kế toán.'];
        $c->forceFill(['cancelled_at' => now(), 'cancelled_by' => $userId])->save();
        return ['ok' => true, 'message' => 'Đã hủy phiếu.'];
    }

    /** Tài xế SỬA phiếu CỦA MÌNH — chỉ khi "Chờ duyệt" (giữ ảnh cũ theo keep + thêm ảnh mới). */
    public function updateSpendRequestByOwner(int $userId, int $costId, array $in, array $files = []): array
    {
        $c = TruckingVehicleCost::with('vehicle')->where('id', $costId)->where('created_by', $userId)->first();
        if (! $c) return ['ok' => false, 'message' => 'Không tìm thấy phiếu của bạn.'];
        if ($c->cancelled_at) return ['ok' => false, 'message' => 'Phiếu đã hủy.'];
        if ($c->approved || $c->paid) return ['ok' => false, 'message' => 'Phiếu đã được duyệt/chi — không thể sửa.'];
        $v = $c->vehicle;
        if (! $v) return ['ok' => false, 'message' => 'Xe không hợp lệ.'];

        $item = trim((string) ($in['costItem'] ?? ''));
        if ($item === '' || ! in_array($item, $this->vehicleCostTypesOut(), true)) return ['ok' => false, 'message' => 'Loại chi phí không hợp lệ.'];
        $amount = $this->inMoney($in['amount'] ?? null) ?? 0;
        if ($amount <= 0) return ['ok' => false, 'message' => 'Vui lòng nhập số tiền.'];
        $km = (isset($in['km']) && $in['km'] !== '') ? (float) preg_replace('/[^\d.]/', '', (string) $in['km']) : null;

        $allow = 0;
        foreach ((is_array($v->allowances) ? $v->allowances : []) as $a) {
            if (mb_strtolower(trim((string) ($a['costItem'] ?? ''))) === mb_strtolower($item)) { $allow = (int) ($a['km'] ?? 0); break; }
        }
        if ($allow > 0) {
            if ($km === null) return ['ok' => false, 'message' => "Khoản “{$item}” có định mức {$allow} km — vui lòng nhập KM hiện tại."];
            $lastKm = $this->lastApprovedKm($v->id, $item);
            if ($lastKm !== null && ($km - $lastKm) < $allow) {
                $g = fn ($n) => number_format((float) $n, 0, '.', '.');
                return ['ok' => false, 'message' => "Chưa đủ định mức: “{$item}” cần đi thêm ≥ {$g($allow)} km kể từ lần trước (km {$g($lastKm)})."];
            }
        }

        // Ảnh (theo ID attachment): giữ lại id trong "keep" + thêm ảnh mới; xóa attachment bị bỏ.
        $keep = is_array($in['keep'] ?? null) ? array_map('intval', $in['keep']) : [];
        $cur = is_array($c->photos) ? array_map('intval', $c->photos) : [];
        foreach ($cur as $id) {
            if (! in_array($id, $keep, true)) $this->deleteAttachment($id, TruckingVehicle::class, $v->id);
        }
        $keptIds = array_values(array_filter($cur, fn ($id) => in_array($id, $keep, true)));
        $newIds = $files ? array_map(fn ($p) => $p['id'], $this->storeCostPhotos($v, $files)) : [];

        $c->forceFill([
            'name' => $item, 'amount' => $amount, 'current_km' => $km, 'note' => trim((string) ($in['note'] ?? '')),
            'spend_date' => $this->inDate($in['date'] ?? null) ?? $c->spend_date,
            'photos' => array_merge($keptIds, $newIds),
        ])->save();
        return ['ok' => true, 'message' => 'Đã cập nhật phiếu.'];
    }

    /** Admin hủy phiếu — khi CHƯA thanh toán. */
    public function cancelVehicleCost(TruckingVehicleCost $c, int $byUserId): array
    {
        if ($c->cancelled_at) return ['ok' => false, 'message' => 'Phiếu đã hủy.'];
        if ($c->paid) return ['ok' => false, 'message' => 'Phiếu đã chi — không thể hủy.'];
        $c->forceFill(['cancelled_at' => now(), 'cancelled_by' => $byUserId])->save();
        return ['ok' => true];
    }
}
