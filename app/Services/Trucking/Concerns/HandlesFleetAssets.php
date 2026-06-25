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

/** Tách từ TruckingV2Service — nhóm HandlesFleetAssets. */
trait HandlesFleetAssets
{
    // ===================================================================
    // QUẢN LÝ XE (xe MBF nội bộ)
    // ===================================================================

    /** Danh sách xe MBF + đếm để hiện badge + tóm tắt KHẤU HAO (tháng/đã KH đến nay/còn lại). */
    public function mbfVehicles(): array
    {
        $nowIdx = (int) now()->format('Y') * 12 + ((int) now()->format('n') - 1);
        $rows = TruckingVehicle::where('type', 'MBF')
            ->withCount(['vehicleUsages', 'vehicleCosts', 'vehicleDepreciations'])
            ->with(['vehicleDepreciations:id,vehicle_id,orig_price,start_date,months'])
            ->orderBy('plate')->get();
        // Đếm hồ sơ (attachments group='doc') 1 query gộp → tránh N+1 (docs lưu ở trucking_attachments, không phải cột JSON cũ).
        $docCounts = TruckingAttachment::where('owner_type', TruckingVehicle::class)->where('group', 'doc')
            ->whereIn('owner_id', $rows->pluck('id'))->selectRaw('owner_id, COUNT(*) c')->groupBy('owner_id')->pluck('c', 'owner_id');
        return $rows->map(function ($v) use ($nowIdx, $docCounts) {
                $info = is_array($v->info) ? $v->info : [];
                $dep = $this->depSummary($v->vehicleDepreciations, $nowIdx);   // khấu hao theo tháng đến nay
                return [
                    'id'              => $v->id,
                    'hashid'          => Hashid::encode($v->id),
                    'plate'           => $v->plate,
                    'axle'            => $v->axle ?? '',
                    'registrationDue' => $info['registrationDue'] ?? '',   // YYYY-MM-DD (hạn đăng kiểm)
                    'insuranceDue'    => $info['insuranceDue'] ?? '',       // YYYY-MM-DD (hạn bảo hiểm)
                    'docCount'        => (int) ($docCounts[$v->id] ?? 0),
                    'usageCount'      => (int) $v->vehicle_usages_count,
                    'costCount'       => (int) $v->vehicle_costs_count,
                    'depCount'        => (int) $v->vehicle_depreciations_count,
                    'depMonthly'      => $dep['monthly'],   // khấu hao tháng hiện tại
                    'depAccrued'      => $dep['accrued'],   // đã khấu hao đến nay
                    'depRemain'       => $dep['remain'],    // giá trị còn lại
                    'depOrig'         => $dep['orig'],      // tổng nguyên giá
                ];
            })->all();
    }

    /** Tóm tắt khấu hao đều/tháng (CHỈ tính tới tháng hiện tại $nowIdx) cho 1 tập hạng mục. */
    private function depSummary($deps, int $nowIdx): array
    {
        $orig = 0.0; $monthly = 0.0; $accrued = 0.0;
        foreach ($deps as $d) {
            $o = (float) $d->orig_price; $m = (int) $d->months;
            if ($o <= 0 || $m <= 0 || ! $d->start_date) continue;
            try { $c = \Carbon\Carbon::parse($d->start_date); } catch (\Throwable) { continue; }
            $orig += $o;
            $startIdx = (int) $c->format('Y') * 12 + ((int) $c->format('n') - 1);
            $per = $o / $m;
            $elapsed = max(0, min($m, $nowIdx - $startIdx + 1));   // số tháng đã khấu hao tới nay (gồm tháng này)
            $accrued += $per * $elapsed;
            if ($nowIdx >= $startIdx && $nowIdx <= $startIdx + $m - 1) $monthly += $per;   // tháng này còn khấu hao
        }
        return ['orig' => (int) round($orig), 'monthly' => (int) round($monthly), 'accrued' => (int) round($accrued), 'remain' => (int) round(max(0, $orig - $accrued))];
    }

    /**
     * Chi phí ĐỊNH KỲ của xe MBF hết hạn / sắp hết (≤30 ngày) — để cảnh báo + popup.
     * Mỗi khoản (theo xe + TÊN) chỉ xét PHIẾU CHI MỚI NHẤT (due_date lớn nhất): khi tạo
     * phiếu mới hạn xa hơn, phiếu cũ tự hết nhắc. Sắp xếp hết hạn lâu nhất lên đầu.
     */
    public function expiringVehicleCosts(): array
    {
        $plates = TruckingVehicle::where('type', 'MBF')->pluck('plate', 'id');   // id => biển số
        if ($plates->isEmpty()) return [];

        $latest = [];   // "vehId|tên" => phiếu có due_date mới nhất
        foreach (TruckingVehicleCost::whereIn('vehicle_id', $plates->keys())
            ->where('kind', 'recurring')->whereNotNull('due_date')->get() as $c) {
            $key = $c->vehicle_id . '|' . mb_strtolower(trim((string) $c->name));
            if (! isset($latest[$key]) || $c->due_date->gt($latest[$key]->due_date)) $latest[$key] = $c;
        }

        $today = Carbon::today();
        $warnDays = (int) TruckingSetting::get('due_warn_days', '30') ?: 30;
        $out = [];
        foreach ($latest as $c) {
            $days = (int) round(($c->due_date->copy()->startOfDay()->getTimestamp() - $today->getTimestamp()) / 86400);
            if ($days > $warnDays) continue;   // còn hạn xa → không nhắc
            $out[] = [
                'vehicleId' => $c->vehicle_id,
                'plate'     => $plates[$c->vehicle_id] ?? '',
                'name'      => $c->name ?: '(chi phí)',
                'dueDate'   => $this->outDate($c->due_date),
                'amount'    => (int) round((float) $c->amount),
                'status'    => $days < 0 ? 'expired' : 'soon',
                'days'      => $days,
            ];
        }
        usort($out, fn ($a, $b) => $a['days'] <=> $b['days']);
        return $out;
    }

    /** Phiếu chi cần xử lý: chưa duyệt, hoặc đã duyệt nhưng chưa thanh toán (toàn đội xe MBF). */
    public function pendingVehicleCosts(): array
    {
        $plates = TruckingVehicle::where('type', 'MBF')->pluck('plate', 'id');
        if ($plates->isEmpty()) return [];
        $out = [];
        foreach (TruckingVehicleCost::whereIn('vehicle_id', $plates->keys())
            ->where(fn ($q) => $q->where('approved', false)->orWhere('paid', false))
            ->orderByDesc('spend_date')->orderByDesc('id')->get() as $c) {
            $out[] = [
                'vehicleId' => $c->vehicle_id,
                'plate'     => $plates[$c->vehicle_id] ?? '',
                'name'      => $c->name ?: '(phiếu chi)',
                'invoiceNo' => $c->invoice_no ?? '',
                'spendDate' => $this->outDate($c->spend_date),
                'amount'    => (int) round((float) $c->amount),
                'approved'  => (bool) $c->approved,
                'paid'      => (bool) $c->paid,
            ];
        }
        return $out;
    }

    /**
     * QUẢN LÝ CHI PHÍ — tổng hợp MỌI phiếu chi (xe MBF + tài sản) để duyệt/thanh toán/sửa/hủy tập trung.
     * $f: status (action|pending|pay|paid|cancelled|all), kind (all|vehicle|asset), q, page, perPage.
     * Trả rows (đã map) + total (theo filter) + counts (theo q/kind, KHÔNG theo status — cho badge tab).
     */
    public function costManagementData(array $f): array
    {
        $status = (string) ($f['status'] ?? 'action');
        $kind   = (string) ($f['kind'] ?? 'all');
        $q      = trim((string) ($f['q'] ?? ''));
        $page   = max(1, (int) ($f['page'] ?? 1));
        $per    = min(100, max(5, (int) ($f['perPage'] ?? 20)));

        $base = TruckingVehicleCost::query()->with(['vehicle:id,plate,kind,info', 'creator:id,name']);
        if ($kind === 'vehicle')     $base->whereHas('vehicle', fn ($w) => $w->where('kind', '!=', 'asset'));
        elseif ($kind === 'asset')   $base->whereHas('vehicle', fn ($w) => $w->where('kind', 'asset'));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $base->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('invoice_no', 'like', $like)
                ->orWhere('supplier', 'like', $like)->orWhereHas('vehicle', fn ($v) => $v->where('plate', 'like', $like)));
        }
        // Badge từng tab (theo q/kind hiện tại)
        $cnt = fn (callable $cb) => tap(clone $base, $cb)->count();
        $counts = [
            'all'       => (clone $base)->count(),
            'pending'   => $cnt(fn ($c) => $c->whereNull('cancelled_at')->where('approved', false)),
            'pay'       => $cnt(fn ($c) => $c->whereNull('cancelled_at')->where('approved', true)->where('paid', false)),
            'paid'      => $cnt(fn ($c) => $c->where('paid', true)->whereNull('cancelled_at')),
            'cancelled' => $cnt(fn ($c) => $c->whereNotNull('cancelled_at')),
            'action'    => $cnt(fn ($c) => $c->whereNull('cancelled_at')->where(fn ($w) => $w->where('approved', false)->orWhere('paid', false))),
        ];

        $list = clone $base;
        match ($status) {
            'pending'   => $list->whereNull('cancelled_at')->where('approved', false),
            'pay'       => $list->whereNull('cancelled_at')->where('approved', true)->where('paid', false),
            'paid'      => $list->where('paid', true)->whereNull('cancelled_at'),
            'cancelled' => $list->whereNotNull('cancelled_at'),
            'action'    => $list->whereNull('cancelled_at')->where(fn ($w) => $w->where('approved', false)->orWhere('paid', false)),
            default     => $list,
        };
        $total = (clone $list)->count();
        $rows = $list->orderByDesc('id')->forPage($page, $per)->get()->map(fn ($c) => $this->costMgmtRow($c))->all();

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $per, 'counts' => $counts];
    }

    /** Map 1 phiếu chi cho trang Quản lý chi phí (kèm thông tin xe/tài sản + người gửi + trạng thái). */
    private function costMgmtRow(TruckingVehicleCost $c): array
    {
        $v = $c->vehicle;
        $isAsset = ($v?->kind ?? 'vehicle') === 'asset';
        $vinfo = is_array($v?->info) ? $v->info : [];
        $st = $this->vehicleCostStatus($c);
        return [
            'id' => $c->id, 'hashid' => \App\Support\Hashid::encode($c->id),
            'vehicleId' => $c->vehicle_id, 'vehicleHashid' => $v ? \App\Support\Hashid::encode($v->id) : null,
            'plate' => $v?->plate ?? '', 'kind' => $isAsset ? 'asset' : 'vehicle',
            'targetName' => $isAsset ? (($vinfo['name'] ?? '') ?: ($v?->plate ?? '')) : ($v?->plate ?? ''),
            'name' => $c->name ?? '', 'invoiceNo' => $c->invoice_no ?? '',
            'kindCost' => ($c->kind === 'fixed' ? 'fixed' : 'recurring'),
            'spendDate' => $this->outDate($c->spend_date), 'dueDate' => $this->outDate($c->due_date),
            'amount' => $this->outMoney($c->amount),
            'estAmount' => $c->est_amount !== null ? $this->outMoney($c->est_amount) : null,
            'currentKm' => $this->outNum($c->current_km), 'supplier' => $c->supplier ?? '', 'note' => $c->note ?? '',
            'paid' => (bool) $c->paid, 'approved' => (bool) $c->approved,
            'paidDate' => $this->outDate($c->paid_date), 'paidMethod' => $c->paid_method ?? '', 'paidRef' => $c->paid_ref ?? '', 'paidNote' => $c->paid_note ?? '',
            'requester' => $c->creator?->name ?? '', 'status' => $st['code'], 'statusLabel' => $st['label'],
            'cancelled' => (bool) $c->cancelled_at, 'canCancel' => (! $c->cancelled_at && ! $c->paid),
            'photos' => $this->costPhotosOut(is_array($c->photos) ? $c->photos : [], $c->vehicle_id),
        ];
    }

    /** Cập nhật 1 PHIẾU CHI đơn lẻ (duyệt/thanh toán/sửa) — KHÔNG đụng est_amount/created_by/cancelled. */
    public function updateVehicleCost(TruckingVehicleCost $c, array $in): array
    {
        if ($c->cancelled_at) return ['ok' => false, 'message' => 'Phiếu đã hủy — không thể sửa.'];
        $approved = ! empty($in['approved']);
        $paid     = $approved && ! empty($in['paid']);   // chưa duyệt thì không thể "đã chi"
        $amount   = $this->inMoney($in['amount'] ?? null) ?? 0;
        if ($amount <= 0) return ['ok' => false, 'message' => 'Số tiền thực tế phải lớn hơn 0.'];
        $name = $this->str($in['name'] ?? null) ?? $c->name;
        // Tham chiếu loại chi phí theo ĐÚNG NGUỒN của phiếu (xe vs tài sản).
        $isAsset = (($c->vehicle?->kind) ?? 'vehicle') === 'asset';
        $typeId = $name !== null
            ? ($isAsset ? \App\Models\TruckingAssetCostType::where('name', $name)->value('id')
                        : \App\Models\TruckingVehicleCostType::where('name', $name)->value('id'))
            : null;
        $c->forceFill([
            'name' => $name, 'cost_type_id' => $typeId,
            'kind' => ((($in['kind'] ?? $c->kind)) === 'recurring') ? 'recurring' : 'fixed',
            'spend_date' => $this->inDate($in['spendDate'] ?? null) ?? $c->spend_date,
            'due_date'   => array_key_exists('dueDate', $in) ? $this->inDate($in['dueDate']) : $c->due_date,
            'amount'     => $amount,
            'current_km' => array_key_exists('currentKm', $in) ? $this->inNum($in['currentKm']) : $c->current_km,
            'supplier'   => array_key_exists('supplier', $in) ? $this->str($in['supplier']) : $c->supplier,
            'note'       => array_key_exists('note', $in) ? $this->str($in['note']) : $c->note,
            'photos'     => array_key_exists('photos', $in) ? $this->cleanCostPhotos($in['photos']) : ($c->photos ?? []),
            'approved'   => $approved,
            'paid'       => $paid,
            'paid_date'   => $paid ? ($this->inDate($in['paidDate'] ?? null) ?: now()->toDateString()) : null,
            'paid_method' => $paid ? $this->str($in['paidMethod'] ?? null) : null,
            'paid_ref'    => $paid ? $this->str($in['paidRef'] ?? null) : null,
            'paid_note'   => $paid ? $this->str($in['paidNote'] ?? null) : null,
        ])->save();
        return ['ok' => true, 'row' => $this->costMgmtRow($c->fresh(['vehicle', 'creator']))];
    }

    /** Danh mục Khoản chi phí (dùng cho Combo tên phiếu chi xe + báo cáo theo khoản). */
    public function costItemNames(): array
    {
        return TruckingCostItem::orderBy('sort')->orderBy('name')->pluck('name')->all();
    }

    /** Tạo nhanh 1 khoản chi phí vào danh mục (KHÔNG đụng giá/màu) → trả danh sách mới. */
    public function addCostItem(string $name): array
    {
        $name = trim($name);
        if ($name !== '') {
            TruckingCostItem::firstOrCreate(['name' => $name], ['sort' => (int) (TruckingCostItem::max('sort') ?? 0) + 1]);
        }
        return $this->costItemNames();
    }

    // ===================================================================
    // QUẢN LÝ TÀI SẢN (kind='asset') — dùng chung bảng trucking_vehicles
    // (tái dùng tab Chi phí/Khấu hao/Tài liệu); KHÔNG đụng phí xe (type='asset' ≠ 'MBF').
    // ===================================================================

    /** Danh mục loại tài sản (Combo "Loại tài sản"). */
    public function assetCategories(): array
    {
        return TruckingAssetCategory::orderBy('sort')->orderBy('name')->pluck('name')->all();
    }

    /** Thêm nhanh 1 loại tài sản → trả danh sách mới. */
    public function addAssetCategory(string $name): array
    {
        $name = trim($name);
        if ($name !== '') {
            TruckingAssetCategory::firstOrCreate(['name' => $name], ['sort' => (int) (TruckingAssetCategory::max('sort') ?? 0) + 1]);
        }
        return $this->assetCategories();
    }

    /** 1 tài sản → shape danh sách (kèm đếm + hạn để cảnh báo như xe). docCount đếm từ attachments (group='doc'). */
    private function assetListRow(TruckingVehicle $v, int $docCount = 0): array
    {
        $info = is_array($v->info) ? $v->info : [];
        $nowIdx = (int) now()->format('Y') * 12 + ((int) now()->format('n') - 1);
        $dep = $this->depSummary($v->relationLoaded('vehicleDepreciations') ? $v->vehicleDepreciations : collect(), $nowIdx);
        return [
            'id'            => $v->id,
            'hashid'        => Hashid::encode($v->id),
            'code'          => $v->plate,                       // mã tài sản (cột unique)
            'name'          => $info['name'] ?? '',
            'category'      => $info['category'] ?? '',
            'status'        => $info['status'] ?? '',
            'location'      => $info['location'] ?? '',
            'warrantyDue'   => $info['warrantyDue'] ?? '',      // YYYY-MM-DD
            'inspectionDue' => $info['inspectionDue'] ?? '',    // YYYY-MM-DD
            'docCount'      => $docCount,
            'costCount'     => (int) ($v->vehicle_costs_count ?? 0),
            'depCount'      => (int) ($v->vehicle_depreciations_count ?? 0),
            'depMonthly'    => $dep['monthly'],
            'depAccrued'    => $dep['accrued'],
            'depRemain'     => $dep['remain'],
            'depOrig'       => $dep['orig'],
        ];
    }

    /** Danh sách tài sản (kind='asset'). */
    public function assetList(): array
    {
        $rows = TruckingVehicle::where('kind', 'asset')
            ->withCount(['vehicleCosts', 'vehicleDepreciations'])
            ->with(['vehicleDepreciations:id,vehicle_id,orig_price,start_date,months'])
            ->orderBy('plate')->get();
        // Đếm tài liệu (attachments group='doc') 1 query gộp → tránh N+1
        $docCounts = TruckingAttachment::where('owner_type', TruckingVehicle::class)->where('group', 'doc')
            ->whereIn('owner_id', $rows->pluck('id'))->selectRaw('owner_id, COUNT(*) c')->groupBy('owner_id')->pluck('c', 'owner_id');
        return $rows->map(fn ($v) => $this->assetListRow($v, (int) ($docCounts[$v->id] ?? 0)))->all();
    }

    /** Tạo tài sản mới (tên + loại + mã tự sinh nếu trống) → trả dòng danh sách. */
    public function createAsset(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $n = TruckingVehicle::where('kind', 'asset')->count() + 1;
            do { $code = 'TS-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT); $n++; } while (TruckingVehicle::where('plate', $code)->exists());
        }
        $info = [
            'name'     => $name,
            'category' => trim((string) ($data['category'] ?? '')),
        ];
        $v = TruckingVehicle::create(['plate' => $code, 'type' => 'asset', 'kind' => 'asset', 'info' => $info]);
        return $this->assetListRow($v->loadCount(['vehicleCosts', 'vehicleDepreciations']));
    }

    /** Xóa tài sản (CHỈ kind='asset' — chặn xóa nhầm xe vì xe còn link phí xe). */
    public function destroyAsset(TruckingVehicle $v): bool
    {
        if ($v->kind !== 'asset') return false;
        $v->delete();   // cascade các bảng con (costs/depreciations/usages)
        return true;
    }

}
