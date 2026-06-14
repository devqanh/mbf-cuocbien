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

/** Tách từ TruckingV2Service — nhóm HandlesPlanLinks. */
trait HandlesPlanLinks
{
    // ===================================================================
    // LINK KẾ HOẠCH — lái xe cập nhật giờ xe đến/ra (công khai, mobile)
    // ===================================================================

    /** Builder lô trong khoảng "giờ đến dự kiến" của 1 link. */
    private function planShipmentsQuery(TruckingPlanLink $link)
    {
        return TruckingShipment::whereNotNull('gio_den_du_kien')
            ->whereBetween('gio_den_du_kien', [$link->from_date->copy()->startOfDay(), $link->to_date->copy()->endOfDay()]);
    }

    /** Danh sách link kế hoạch (admin). */
    public function planLinksForList(): array
    {
        return TruckingPlanLink::orderByDesc('id')->get()->map(function ($l) {
            return [
                'id'     => $l->id,
                'hashid' => Hashid::encode($l->id),
                'token'  => $l->token,
                'title'  => $l->title ?? '',
                'from'   => $this->outDate($l->from_date),
                'to'     => $this->outDate($l->to_date),
                'active' => (bool) $l->active,
                'count'  => $this->planShipmentsQuery($l)->count(),
                'url'    => url('/ke-hoach/' . $l->token),
            ];
        })->all();
    }

    public function createPlanLink(array $in, ?int $userId): array
    {
        $from = $this->inDate($in['from'] ?? null);
        $to   = $this->inDate($in['to'] ?? null);
        if (! $from || ! $to) return ['ok' => false, 'message' => 'Vui lòng chọn khoảng ngày.'];
        if ($to < $from) [$from, $to] = [$to, $from];
        $l = TruckingPlanLink::create([
            'token' => TruckingPlanLink::newToken(),
            'title' => trim((string) ($in['title'] ?? '')) ?: null,
            'from_date' => $from, 'to_date' => $to, 'active' => true, 'created_by' => $userId,
        ]);
        return ['ok' => true, 'link' => collect($this->planLinksForList())->firstWhere('id', $l->id)];
    }

    public function updatePlanLink(TruckingPlanLink $l, array $in): array
    {
        $from = $this->inDate($in['from'] ?? null);
        $to   = $this->inDate($in['to'] ?? null);
        if (! $from || ! $to) return ['ok' => false, 'message' => 'Vui lòng chọn khoảng ngày.'];
        if ($to < $from) [$from, $to] = [$to, $from];
        $l->update([
            'title' => trim((string) ($in['title'] ?? '')) ?: null,
            'from_date' => $from, 'to_date' => $to,
        ]);
        return ['ok' => true, 'link' => collect($this->planLinksForList())->firstWhere('id', $l->id)];
    }

    public function setPlanLinkActive(TruckingPlanLink $l, bool $active): void
    {
        $l->update(['active' => $active]);
    }

    public function deletePlanLink(TruckingPlanLink $l): void
    {
        $l->delete();
    }

    /** Lô cho lái xe xem/cập nhật (CHỈ field cần thiết — không lộ tài chính). */
    private function planShipmentView(TruckingShipment $s): array
    {
        return [
            'hashid'   => Hashid::encode($s->id),
            'customer' => $s->customer?->name ?? '',
            'booking'  => $s->booking ?? '',
            'contNo'   => $s->cont_no ?? '',
            'contType' => $s->cont_type ?? '',
            'kho'      => $s->kho ?? '',
            'from'     => $s->from_loc ?? '',
            'to'       => $s->to_loc ?? '',
            'bksVao'   => $s->bks_vao ?? '',
            'bksRa'    => $s->bks_ra ?? '',
            'gioDenDuKien' => $this->outDateTime($s->gio_den_du_kien),
            'gioXeDen' => $this->outDateTime($s->gio_xe_den),
            'gioXeRa'  => $this->outDateTime($s->gio_xe_ra),
            'driverNote' => $s->driver_note ?? '',
            'photos'   => $this->listAttachments(TruckingShipment::class, $s->id, 'shipmentPhoto'),
        ];
    }

    /** Dữ liệu trang công khai: thông tin link + danh sách lô trong khoảng. */
    public function planPublicData(TruckingPlanLink $link): array
    {
        $ships = $this->planShipmentsQuery($link)->with('customer:id,name')
            ->orderBy('gio_den_du_kien')->get()->map(fn ($s) => $this->planShipmentView($s))->all();
        return [
            'title' => $link->title ?? '',
            'from'  => $this->outDate($link->from_date),
            'to'    => $this->outDate($link->to_date),
            'ships' => $ships,
        ];
    }

    /** Lái xe cập nhật 1 lô qua link (chỉ giờ xe đến/ra + ghi chú + ảnh; phải trong khoảng). */
    public function planUpdateShipment(TruckingPlanLink $link, string $shipHashid, array $in, array $files = []): array
    {
        $id = Hashid::decode($shipHashid);
        if ($id === null) return ['ok' => false, 'message' => 'Lô không hợp lệ.'];
        $s = $this->planShipmentsQuery($link)->where('id', $id)->first();
        if (! $s) return ['ok' => false, 'message' => 'Lô không thuộc kế hoạch này (đã đổi giờ kế hoạch?).'];

        if (array_key_exists('gioXeDen', $in)) $s->gio_xe_den = $this->inDateTime($in['gioXeDen'] ?: null);
        if (array_key_exists('gioXeRa', $in))  $s->gio_xe_ra  = $this->inDateTime($in['gioXeRa'] ?: null);
        if (array_key_exists('driverNote', $in)) $s->driver_note = trim((string) $in['driverNote']) ?: null;
        $s->save();

        if ($files) $this->storeAttachments(TruckingShipment::class, $s->id, 'shipmentPhoto', $files, null, "trucking/shipments/{$s->id}");

        return ['ok' => true, 'ship' => $this->planShipmentView($s->fresh('customer'))];
    }

    /** Xóa 1 ảnh lô qua link (chỉ ảnh thuộc lô trong khoảng). */
    public function planDeletePhoto(TruckingPlanLink $link, string $shipHashid, int $attId): array
    {
        $id = Hashid::decode($shipHashid);
        if ($id === null || ! $this->planShipmentsQuery($link)->where('id', $id)->exists()) return ['ok' => false];
        $this->deleteAttachment($attId, TruckingShipment::class, $id);
        return ['ok' => true, 'photos' => $this->listAttachments(TruckingShipment::class, $id, 'shipmentPhoto')];
    }
}
