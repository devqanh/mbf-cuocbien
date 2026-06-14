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

    /** Danh sách xe MBF + đếm để hiện badge. */
    public function mbfVehicles(): array
    {
        return TruckingVehicle::where('type', 'MBF')
            ->withCount(['vehicleUsages', 'vehicleCosts', 'vehicleDepreciations'])
            ->orderBy('plate')->get()->map(function ($v) {
                $info = is_array($v->info) ? $v->info : [];
                return [
                    'id'              => $v->id,
                    'hashid'          => Hashid::encode($v->id),
                    'plate'           => $v->plate,
                    'axle'            => $v->axle ?? '',
                    'registrationDue' => $info['registrationDue'] ?? '',   // YYYY-MM-DD (hạn đăng kiểm)
                    'insuranceDue'    => $info['insuranceDue'] ?? '',       // YYYY-MM-DD (hạn bảo hiểm)
                    'docCount'        => is_array($v->documents) ? count($v->documents) : 0,
                    'usageCount'      => (int) $v->vehicle_usages_count,
                    'costCount'       => (int) $v->vehicle_costs_count,
                    'depCount'        => (int) $v->vehicle_depreciations_count,
                ];
            })->all();
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
        ];
    }

    /** Danh sách tài sản (kind='asset'). */
    public function assetList(): array
    {
        $rows = TruckingVehicle::where('kind', 'asset')
            ->withCount(['vehicleCosts', 'vehicleDepreciations'])
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
