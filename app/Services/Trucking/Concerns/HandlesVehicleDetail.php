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
use App\Models\TruckingFuelRefill;
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

/** Tách từ TruckingV2Service — nhóm HandlesVehicleDetail. */
trait HandlesVehicleDetail
{

    /** Thông tin nền 1 xe (tab Thông tin) — KHÔNG kèm 3 nhóm con (lazy-load riêng từng tab). */
    public function vehicleBase(TruckingVehicle $v): array
    {
        return [
            'id'      => $v->id,
            'hashid'  => Hashid::encode($v->id),
            'plate'   => $v->plate,
            'axle'    => $v->axle ?? '',
            'info'    => is_array($v->info) ? $v->info : [],
            'docs'    => $this->vehicleDocsOut($v),
            'allowances' => array_values(array_filter(is_array($v->allowances) ? $v->allowances : [], 'is_array')),
            'drivers' => $this->driverOptions(),
        ];
    }

    private function usagesOut(TruckingVehicle $v): array
    {
        return $v->vehicleUsages()->orderBy('sort')->get()->map(fn ($u) => [
            'id' => $u->id, 'driver' => $u->driver ?? '', 'driverId' => $u->driver_id,
            'from' => $this->outDate($u->from_date), 'to' => $this->outDate($u->to_date), 'note' => $u->note ?? '',
        ])->all();
    }

    private function costsOut(TruckingVehicle $v): array
    {
        return $v->vehicleCosts()->with('creator:id,name')->orderBy('sort')->get()->map(function ($c) use ($v) {
            $st = $this->vehicleCostStatus($c);
            return [
            'id' => $c->id, 'hashid' => Hashid::encode($c->id), 'name' => $c->name ?? '', 'costTypeId' => $c->cost_type_id, 'invoiceNo' => $c->invoice_no ?? '', 'kind' => ($c->kind === 'fixed' ? 'fixed' : 'recurring'),
            'spendDate' => $this->outDate($c->spend_date), 'dueDate' => $this->outDate($c->due_date), 'amount' => $this->outMoney($c->amount),
            'currentKm' => $this->outNum($c->current_km), 'supplier' => $c->supplier ?? '', 'note' => $c->note ?? '',
            'paid' => (bool) $c->paid, 'approved' => (bool) $c->approved,
            'paidDate' => $this->outDate($c->paid_date), 'paidMethod' => $c->paid_method ?? '', 'paidRef' => $c->paid_ref ?? '', 'paidNote' => $c->paid_note ?? '',
            'requester' => $c->creator?->name ?? '', 'status' => $st['code'], 'statusLabel' => $st['label'],
            'cancelled' => (bool) $c->cancelled_at, 'canCancel' => (! $c->cancelled_at && ! $c->paid),   // admin hủy khi chưa chi
            'photos' => $this->costPhotosOut(is_array($c->photos) ? $c->photos : [], $v->id),
            ];
        })->all();
    }

    /**
     * Thông tin công ty (header bảng kê trên màn hình + bản in) — cấu hình ở Cài đặt hệ thống.
     * 1 query gộp 3 key; có default để hệ thống cũ/chưa cấu hình vẫn hiển thị đúng.
     */
    public function companyInfo(): array
    {
        $r = TruckingSetting::whereIn('key', ['sys.company_name', 'sys.company_website', 'sys.company_phone'])
            ->pluck('value', 'key');
        return [
            'name'    => ($r['sys.company_name']    ?? '') ?: 'MBF JOINT STOCK COMPANY',
            'website' => ($r['sys.company_website'] ?? '') ?: 'http://mbf.com.vn',
            'phone'   => ($r['sys.company_phone']   ?? '') ?: '84-24-39449616',
        ];
    }

    /**
     * Thông tin BÊN BÁN cho file Excel bảng kê — cấu hình ở Cài đặt hệ thống.
     * Tách riêng với companyInfo() vì tên pháp lý (tiếng Việt) khác tên hiển thị (tiếng Anh).
     * Default = thông tin MBF như template cũ → chưa cấu hình vẫn xuất đúng.
     */
    public function sellerInfo(): array
    {
        $r = TruckingSetting::whereIn('key', ['sys.seller_name', 'sys.seller_address', 'sys.seller_tax', 'sys.seller_rep', 'sys.seller_title'])
            ->pluck('value', 'key');
        return [
            'name'    => ($r['sys.seller_name']    ?? '') ?: 'CÔNG TY CỔ PHẦN MBF',
            'address' => ($r['sys.seller_address'] ?? '') ?: 'Số 58 Xóm Giếng, Thôn Cổ Điển A, Xã Thanh Trì, Thành phố Hà Nội, Việt Nam',
            'tax'     => ($r['sys.seller_tax']     ?? '') ?: '0105040296',
            'rep'     => (string) ($r['sys.seller_rep']   ?? ''),
            'title'   => (string) ($r['sys.seller_title'] ?? ''),
        ];
    }

    // ===================================================================
    // FILE TẬP TRUNG (trucking_attachments) — disk theo config, dễ migrate S3
    // ===================================================================
    private function uploadDisk(): string { return TruckingSetting::get('sys.upload_disk') ?: (string) config('trucking.upload_disk', 'local'); }

    /** Nạp cấu hình S3 từ DB (Cài đặt hệ thống) vào disk 's3' — gọi LAZY trước khi đụng file s3 (không query khi dùng local). */
    public function applyS3Config(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        $key = TruckingSetting::get('sys.s3_key');
        if (! $key) return;   // chưa cấu hình → dùng env mặc định trong config/filesystems
        $secret = TruckingSetting::get('sys.s3_secret');
        try { $secret = $secret ? \Illuminate\Support\Facades\Crypt::decryptString($secret) : ''; } catch (\Throwable $e) { $secret = ''; }
        config(['filesystems.disks.s3' => array_merge((array) config('filesystems.disks.s3', []), array_filter([
            'driver'   => 's3', 'key' => $key, 'secret' => $secret,
            'region'   => TruckingSetting::get('sys.s3_region'), 'bucket' => TruckingSetting::get('sys.s3_bucket'),
            'url'      => TruckingSetting::get('sys.s3_url') ?: null,
            'endpoint' => TruckingSetting::get('sys.s3_endpoint') ?: null,
            'use_path_style_endpoint' => (bool) TruckingSetting::get('sys.s3_endpoint'),
        ], fn ($v) => $v !== null && $v !== ''))]);
    }

    private function disk(string $name)
    {
        if ($name === 's3') $this->applyS3Config();
        return Storage::disk($name);
    }

    /** 1 attachment → shape client (URL stream disk-agnostic qua route). */
    private function attachmentOut(TruckingAttachment $a): array
    {
        return [
            'id' => $a->id, 'name' => $a->name ?? '', 'type' => $a->type ?? '',
            'mime' => $a->mime ?? '', 'size' => (int) $a->size, 'isImage' => $a->isImage(),
            'url' => route('trucking2.attachment', ['attachment' => $a->hashid()]),
        ];
    }

    /** Danh sách file của 1 owner/group. */
    public function listAttachments(string $ownerType, int $ownerId, string $group): array
    {
        return TruckingAttachment::where(['owner_type' => $ownerType, 'owner_id' => $ownerId, 'group' => $group])
            ->orderBy('sort')->orderBy('id')->get()->map(fn ($a) => $this->attachmentOut($a))->all();
    }

    /** Lưu nhiều file → tạo attachment rows (disk theo config). Trả về collection model. */
    public function storeAttachments(string $ownerType, int $ownerId, string $group, array $files, ?string $type, string $dir): array
    {
        $disk = $this->uploadDisk();
        if ($disk === 's3') $this->applyS3Config();
        $sort = (int) (TruckingAttachment::where(['owner_type' => $ownerType, 'owner_id' => $ownerId, 'group' => $group])->max('sort') ?? -1) + 1;
        $created = [];
        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) continue;
            if ($group === 'costPhoto' && ! str_starts_with((string) $file->getMimeType(), 'image/')) continue;
            $path = $file->store($dir, $disk);
            $created[] = TruckingAttachment::create([
                'owner_type' => $ownerType, 'owner_id' => $ownerId, 'group' => $group, 'disk' => $disk, 'path' => $path,
                'name' => $file->getClientOriginalName(), 'type' => $this->str($type), 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'sort' => $sort++,
            ]);
        }
        return $created;
    }

    /** Xóa 1 attachment (kiểm tra đúng owner) → xóa file trên disk + dòng. */
    public function deleteAttachment(int $id, string $ownerType, int $ownerId): bool
    {
        $a = TruckingAttachment::where(['id' => $id, 'owner_type' => $ownerType, 'owner_id' => $ownerId])->first();
        if (! $a) return false;
        try { $this->disk($a->disk)->delete($a->path); } catch (\Throwable $e) {}
        $a->delete();
        return true;
    }

    // --- Ảnh phiếu chi: owner = XE (id ổn định), cost.photos = MẢNG ID attachment ---
    /** Ảnh phiếu chi từ mảng id → shape client. */
    private function costPhotosOut($ids, int $vehicleId): array
    {
        $ids = array_values(array_filter(array_map('intval', is_array($ids) ? $ids : [])));
        if (! $ids) return [];
        $rows = TruckingAttachment::whereIn('id', $ids)->where('group', 'costPhoto')->get()->keyBy('id');
        $out = [];
        foreach ($ids as $id) if ($rows->has($id)) $out[] = $this->attachmentOut($rows[$id]);
        return $out;
    }

    /** Upload ảnh phiếu chi → trả shape client (kèm id) để client gắn vào phiếu. */
    public function storeCostPhotos(TruckingVehicle $v, array $files): array
    {
        $m = $this->storeAttachments(TruckingVehicle::class, $v->id, 'costPhoto', $files, null, "trucking/cost-photos/{$v->id}");
        return array_map(fn ($a) => $this->attachmentOut($a), $m);
    }

    /** cost.photos lưu MẢNG ID (từ client gửi: mảng object {id} hoặc mảng id). */
    private function cleanCostPhotos($photos): array
    {
        if (! is_array($photos)) return [];
        $ids = [];
        foreach ($photos as $p) { $id = is_array($p) ? (int) ($p['id'] ?? 0) : (int) $p; if ($id > 0) $ids[] = $id; }
        return array_values(array_unique($ids));
    }

    /** Dọn ảnh phiếu chi MỒ CÔI của 1 xe (không còn phiếu nào tham chiếu). */
    private function pruneOrphanCostPhotos(int $vehicleId): void
    {
        $used = [];
        foreach (TruckingVehicleCost::where('vehicle_id', $vehicleId)->pluck('photos') as $ph) {
            foreach ((is_array($ph) ? $ph : []) as $id) $used[(int) $id] = true;
        }
        foreach (TruckingAttachment::where(['owner_type' => TruckingVehicle::class, 'owner_id' => $vehicleId, 'group' => 'costPhoto'])->get() as $a) {
            if (empty($used[$a->id])) { try { $this->disk($a->disk)->delete($a->path); } catch (\Throwable $e) {} $a->delete(); }
        }
    }

    private function deprOut(TruckingVehicle $v): array
    {
        return $v->vehicleDepreciations()->orderBy('sort')->get()->map(fn ($d) => [
            'id' => $d->id, 'name' => $d->name ?? '', 'origPrice' => $this->outMoney($d->orig_price),
            'startDate' => $this->outDate($d->start_date), 'months' => $d->months ?? 0,
            'monthly' => $this->outMoney($d->monthly_amount), 'daily' => $this->outNum($d->daily_amount),
        ])->all();
    }

    /** 1 nhóm con (lazy-load theo tab): usages | costs | depreciations. */
    public function vehicleSection(TruckingVehicle $v, string $section): array
    {
        return match ($section) {
            'usages'        => ['usages' => $this->usagesOut($v), 'drivers' => $this->driverOptions()],
            'costs'         => ['costs' => $this->costsOut($v), 'costTypes' => $this->vehicleCostTypesOut()],
            'depreciations' => ['depreciations' => $this->deprOut($v)],
            default         => [],
        };
    }

    private function vehicleCostTypesOut(): array
    {
        return TruckingVehicleCostType::orderBy('sort')->orderBy('name')->pluck('name')->all();
    }

    /** Chi tiết đầy đủ (base + 3 nhóm) — khi cần tất cả. */
    public function vehicleDetail(TruckingVehicle $v): array
    {
        return $this->vehicleBase($v) + [
            'usages'        => $this->usagesOut($v),
            'costs'         => $this->costsOut($v),
            'costTypes'     => $this->vehicleCostTypesOut(),
            'depreciations' => $this->deprOut($v),
        ];
    }

    /**
     * Lưu xe — CHỈ ĐỘNG các phần CÓ trong $data (an toàn cho lazy-load: phần chưa tải
     * không gửi lên → không bị xóa). Mỗi nhóm = delete + recreate; trả về các phần đã lưu.
     */
    public function saveVehicleManagement(TruckingVehicle $v, array $data): array
    {
        return DB::transaction(function () use ($v, $data) {
            if (array_key_exists('info', $data) || array_key_exists('allowances', $data)) {
                if (array_key_exists('info', $data)) {
                    $v->info = is_array($data['info']) ? array_map(fn ($x) => is_string($x) ? trim($x) : $x, $data['info']) : null;
                }
                if (array_key_exists('allowances', $data)) {
                    // [{costItem, km}] — chỉ giữ dòng có tên khoản
                    $v->allowances = array_values(array_filter(array_map(fn ($a) => [
                        'costItem' => trim((string) ($a['costItem'] ?? '')),
                        'km'       => (int) preg_replace('/[^\d]/', '', (string) ($a['km'] ?? '')),
                    ], is_array($data['allowances']) ? $data['allowances'] : []), fn ($a) => $a['costItem'] !== ''));
                }
                $v->save();
            }
            if (array_key_exists('usages', $data)) {
                // map tên → id qua cache request-scoped (cùng rule lowercase+trim với driver_id ở các cột khác).
                $driverId = $this->driverIdMap();
                $v->vehicleUsages()->delete();
                foreach (array_values($data['usages'] ?? []) as $i => $u) {
                    $dn   = $this->str($u['driver'] ?? null);
                    $dkey = $dn ? mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $dn)) ?? '') : '';
                    $v->vehicleUsages()->create([
                        'driver' => $dn,
                        'driver_id' => $dkey !== '' ? ($driverId[$dkey] ?? null) : null,
                        'from_date' => $this->inDate($u['from'] ?? null),
                        'to_date' => $this->inDate($u['to'] ?? null),
                        'note' => $this->str($u['note'] ?? null),
                        'sort' => $i,
                    ]);
                }
            }
            if (array_key_exists('costs', $data)) {
                $costRows = array_values($data['costs'] ?? []);
                // # hóa đơn TỰ SINH (PC-XXXX): giữ số đã có, cấp số mới cho phiếu chưa có.
                $usedN = [];
                $scan = function ($no) use (&$usedN) { if (preg_match('/^PC-(\d+)$/', trim((string) $no), $m)) $usedN[] = (int) $m[1]; };
                foreach ($costRows as $c) $scan($c['invoiceNo'] ?? '');
                foreach (TruckingVehicleCost::where('vehicle_id', '!=', $v->id)->where('invoice_no', 'like', 'PC-%')->pluck('invoice_no') as $no) $scan($no);
                $nextN = $usedN ? max($usedN) : 0;

                $typeId = TruckingVehicleCostType::pluck('id', 'name');
                // GIỮ LẠI người yêu cầu + trạng thái hủy qua delete+recreate (khớp theo id dòng cũ)
                $preserve = $v->vehicleCosts()->get(['id', 'created_by', 'cancelled_at', 'cancelled_by', 'created_at'])->keyBy('id');
                $v->vehicleCosts()->delete();
                foreach ($costRows as $i => $c) {
                    $inv = trim((string) ($c['invoiceNo'] ?? ''));
                    if ($inv === '') { $nextN++; $inv = 'PC-' . str_pad((string) $nextN, 4, '0', STR_PAD_LEFT); }
                    $cn = $this->str($c['name'] ?? null);
                    $old = (isset($c['id']) && $preserve->has($c['id'])) ? $preserve[$c['id']] : null;
                    $v->vehicleCosts()->create([
                        'name' => $cn,
                        'cost_type_id' => $cn !== null ? ($typeId[$cn] ?? null) : null,
                        'created_by'   => $old?->created_by,
                        'cancelled_at' => $old?->cancelled_at,
                        'cancelled_by' => $old?->cancelled_by,
                        'invoice_no' => $inv,
                        'kind' => (($c['kind'] ?? '') === 'recurring') ? 'recurring' : 'fixed',
                        'spend_date' => $this->inDate($c['spendDate'] ?? null),
                        'due_date' => $this->inDate($c['dueDate'] ?? null),
                        'amount' => $this->inMoney($c['amount'] ?? null) ?? 0,
                        'current_km' => $this->inNum($c['currentKm'] ?? null),
                        'supplier' => $this->str($c['supplier'] ?? null),
                        'note' => $this->str($c['note'] ?? null),
                        'paid' => ! empty($c['paid']),
                        'paid_date' => $this->inDate($c['paidDate'] ?? null),
                        'paid_method' => $this->str($c['paidMethod'] ?? null),
                        'paid_ref' => $this->str($c['paidRef'] ?? null),
                        'paid_note' => $this->str($c['paidNote'] ?? null),
                        'approved' => ! empty($c['approved']),
                        'photos' => $this->cleanCostPhotos($c['photos'] ?? []),
                        'sort' => $i,
                    ]);
                }
                $this->pruneOrphanCostPhotos($v->id);   // dọn ảnh không còn phiếu nào dùng
            }
            if (array_key_exists('depreciations', $data)) {
                $v->vehicleDepreciations()->delete();
                foreach (array_values($data['depreciations'] ?? []) as $i => $d) {
                    $orig = $this->inMoney($d['origPrice'] ?? null) ?? 0;
                    $months = (int) ($d['months'] ?? 0);
                    $v->vehicleDepreciations()->create([
                        'name' => $this->str($d['name'] ?? null),
                        'orig_price' => $orig,
                        'start_date' => $this->inDate($d['startDate'] ?? null),
                        'months' => $months,
                        'monthly_amount' => $months > 0 ? round($orig / $months, 2) : 0,
                        'daily_amount'   => $months > 0 ? round($orig / (30 * $months), 4) : 0,
                        'sort' => $i,
                    ]);
                }
            }

            // Trả về base + CHỈ các nhóm vừa lưu (để client refresh đúng phần, lấy id mới)
            $v->refresh();
            $echo = $this->vehicleBase($v);
            if (array_key_exists('usages', $data))        $echo['usages'] = $this->usagesOut($v);
            if (array_key_exists('costs', $data))         $echo['costs'] = $this->costsOut($v);
            if (array_key_exists('depreciations', $data)) $echo['depreciations'] = $this->deprOut($v);
            return $echo;
        });
    }

    // --- Tài liệu xe (ảnh/PDF/Word/Excel) — giống hồ sơ tài xế ---
    private function vehicleDocsOut(TruckingVehicle $v): array
    {
        return $this->listAttachments(TruckingVehicle::class, $v->id, 'doc');
    }

    public function uploadVehicleDocs(TruckingVehicle $v, array $files, string $type): array
    {
        $this->storeAttachments(TruckingVehicle::class, $v->id, 'doc', $files, $type ?: 'Khác', "trucking/vehicles/{$v->id}");
        return $this->vehicleDocsOut($v);
    }

    /** Xóa tài liệu xe theo ID attachment. */
    public function deleteVehicleDoc(TruckingVehicle $v, int $attachmentId): array
    {
        $this->deleteAttachment($attachmentId, TruckingVehicle::class, $v->id);
        return $this->vehicleDocsOut($v);
    }

    // ===================================================================
    // THEO DÕI LƯỢNG DẦU (phiếu đổ dầu + tiêu thụ lý thuyết từ Lộ trình)
    // ===================================================================

    /** Danh sách phiếu đổ dầu của 1 xe (mới nhất trước). */
    public function fuelRefills(TruckingVehicle $v): array
    {
        return TruckingFuelRefill::where('vehicle_id', $v->id)->orderByDesc('refill_date')->orderByDesc('id')
            ->get()->map(fn ($r) => [
                'id'        => $r->id,
                'date'      => $this->outDate($r->refill_date),
                'liters'    => (float) $r->liters,
                'unitPrice' => $r->unit_price ? (int) round((float) $r->unit_price) : null,
                'totalCost' => $r->total_cost ? (int) round((float) $r->total_cost) : null,
                'odometerKm' => $r->odometer_km ? (float) $r->odometer_km : null,
                'station'   => $r->station ?? '',
                'note'      => $r->note ?? '',
            ])->all();
    }

    /** Tạo / sửa phiếu đổ dầu. */
    public function saveFuelRefill(TruckingVehicle $v, array $data, ?TruckingFuelRefill $existing = null): TruckingFuelRefill
    {
        $liters = (float) ($this->inNum($data['liters'] ?? null) ?? 0);
        $unitPrice = $this->inMoney($data['unitPrice'] ?? null);
        $attrs = [
            'vehicle_id'  => $v->id,
            'refill_date' => $this->inDate($data['date'] ?? null) ?: now()->format('Y-m-d'),
            'liters'      => $liters,
            'unit_price'  => $unitPrice,
            'total_cost'  => $unitPrice !== null && $liters > 0 ? (int) round($liters * (float) $unitPrice) : $this->inMoney($data['totalCost'] ?? null),
            'odometer_km' => $this->inNum($data['odometerKm'] ?? null),
            'station'     => $this->str($data['station'] ?? null),
            'note'        => $this->str($data['note'] ?? null),
        ];
        if ($existing) { $existing->update($attrs); return $existing; }
        $attrs['created_by'] = auth()->id();
        return TruckingFuelRefill::create($attrs);
    }

    /** Xóa phiếu đổ dầu. */
    public function deleteFuelRefill(TruckingFuelRefill $refill): void { $refill->delete(); }

    /**
     * THEO DÕI DẦU 1 XE: phiếu đổ + tiêu thụ lý thuyết (từ Lộ trình fuelLiters) → ước tính còn lại.
     * Giữa mỗi 2 lần đổ: đổ vào − Σ tiêu thụ = còn lại. Khoảng cuối (đến hôm nay) = ước tính hiện tại.
     */
    public function fuelTracker(TruckingVehicle $v): array
    {
        $refills = TruckingFuelRefill::where('vehicle_id', $v->id)->orderBy('refill_date')->orderBy('id')->get();
        if ($refills->isEmpty()) return ['refills' => [], 'periods' => [], 'currentRemaining' => null];

        $today = now()->format('Y-m-d');
        $plate = $v->plate;

        // Gom fuelLiters/ngày từ route_pays (đã tính qua routeTripByDate) — nhanh hơn loop từng ngày.
        // frozen_data có payGroups.fuel.liters; nếu chưa frozen thì tính live.
        // Đơn giản: loop ngày từ lần đổ đầu → hôm nay, gom từ routeTripByDate per day.
        // Tối ưu: chỉ loop các ngày xe CÓ chuyến (shipments with gio_xe_ra).
        $firstDate = $refills->first()->refill_date->format('Y-m-d');
        $dailyFuel = $this->fuelByDayForVehicle($plate, $v->id, $firstDate, $today);

        $periods = [];
        foreach ($refills as $i => $rf) {
            $from = $rf->refill_date->format('Y-m-d');
            $next = isset($refills[$i + 1]) ? $refills[$i + 1]->refill_date->format('Y-m-d') : $today;
            // Tiêu thụ lý thuyết giữa 2 lần đổ (ngày đổ inclusive → ngày trước lần đổ tiếp)
            $consumed = 0.0; $trips = 0;
            foreach ($dailyFuel as $d => $f) {
                if ($d >= $from && $d < $next) { $consumed += $f['liters']; $trips += $f['trips']; }
            }
            // Nếu khoảng cuối (đến hôm nay), include hôm nay
            if (! isset($refills[$i + 1]) && isset($dailyFuel[$today])) {
                $consumed += $dailyFuel[$today]['liters']; $trips += $dailyFuel[$today]['trips'];
            }
            $periods[] = [
                'from'      => $from,
                'to'        => $next,
                'refilled'  => (float) $rf->liters,
                'consumed'  => round($consumed, 1),
                'trips'     => $trips,
                'remaining' => round((float) $rf->liters - $consumed, 1),
                'isLast'    => ! isset($refills[$i + 1]),
            ];
        }
        $current = end($periods);
        return [
            'refills'          => $this->fuelRefills($v),
            'periods'          => $periods,
            'currentRemaining' => $current ? $current['remaining'] : null,
        ];
    }

    /**
     * Gom fuelLiters theo ngày cho 1 xe (plate/vehicleId) trong khoảng [from..to].
     * Loop từng ngày gọi routeTripByDate — bắt cả xe chạy trực tiếp LẪN kéo cont khác ra (mode other).
     */
    private function fuelByDayForVehicle(string $plate, int $vehicleId, string $from, string $to): array
    {
        $out = [];
        $start = \Carbon\Carbon::parse($from); $end = \Carbon\Carbon::parse($to);
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $ds = $d->format('Y-m-d');
            $day = $this->routeTripByDate($ds);
            foreach ($day['trucks'] as $t) {
                if (($t['vehicleId'] !== null && (int) $t['vehicleId'] === $vehicleId) || $t['bks'] === $plate) {
                    $liters = (float) ($t['fuelLiters'] ?? 0);
                    if ($liters > 0 || count($t['legs']) > 0) {
                        $out[$ds] = ['liters' => $liters, 'trips' => count($t['legs'])];
                    }
                    break;
                }
            }
        }
        return $out;
    }

}
