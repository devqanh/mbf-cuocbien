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

/** Tách từ TruckingV2Service — nhóm HandlesStatements. */
trait HandlesStatements
{
    // ===================================================================
    // STATEMENT (bảng kê) — serialize & persist
    // ===================================================================
    public function statements(): array
    {
        return TruckingStatement::with(['lines', 'payments'])->orderBy('id')->get()
            ->map(fn ($st) => $this->statementToArray($st))->all();
    }

    /**
     * Danh sách bảng kê cho trang Bảng kê (KePage) — CHỈ tóm tắt + payments, KHÔNG nạp
     * lines (snapshot từng lô) vì trang danh sách không hiển thị. Tránh hydrate hàng trăm
     * dòng statement_lines vô ích.
     */
    public function statementsForList(): array
    {
        return TruckingStatement::with(['payments', 'customer'])->withSum('lines as lines_total', 'phai_thu')->orderBy('id')->get()
            ->map(fn ($st) => [
                'id'       => $st->id,
                'hashid'   => Hashid::encode($st->id),
                'no'       => $st->no,
                'customer' => $st->customer_name ?? $st->customer?->name ?? '',
                'date'     => $this->outDate($st->date),
                'from'     => $this->outDate($st->period_from),
                'to'       => $this->outDate($st->period_to),
                // Tổng = TỔNG dòng (luôn đúng, không phụ thuộc cột total có thể cũ).
                'tongThu'  => (int) round((float) ($st->lines_total ?? $st->total)),
                'payments' => $st->payments->map(fn ($p) => [
                    'id'     => $p->id,
                    'date'   => $this->outDate($p->date),
                    'amount' => $this->outMoney($p->amount),
                    'note'   => $p->note ?? '',
                ])->all(),
            ])->all();
    }

    public function statementToArray(TruckingStatement $st): array
    {
        return [
            'id'        => $st->id,
            'hashid'    => Hashid::encode($st->id),
            'no'        => $st->no,
            'customer'  => $st->customer_name ?? $st->customer?->name ?? '',
            'info'      => $st->info ?? [],
            'date'      => $this->outDate($st->date),
            'from'      => $this->outDate($st->period_from),
            'to'        => $this->outDate($st->period_to),
            'tongThu'   => (int) round((float) $st->lines->sum('phai_thu')),   // = tổng dòng (chân lý)
            'lines'     => $st->lines->map(fn ($l) => [
                'id'        => $l->shipment_id ?? $l->id,
                'booking'   => $l->booking ?? '',
                'sheet'     => $l->sheet ?? '',
                'io'        => $l->io ?? '',
                'declNo'    => $l->decl_no ?? '',
                'contType'  => $l->cont_type ?? '',
                'inv'       => $l->inv ?? '',
                'contNo'    => $l->cont_no ?? '',
                'bks'       => $l->bks ?? '',
                'from'      => $l->from_loc ?? '',
                'to'        => $l->to_loc ?? '',
                'date'      => $this->outDate($l->date),
                'contLabel' => $l->cont_label ?? '',
                'phaiThu'   => (int) round((float) $l->phai_thu),
                'cuoc'      => (int) round((float) $l->cuoc),
                'thanhLy'   => (int) round((float) $l->thanh_ly),
                'note'      => $l->note ?? '',
                'detail'    => $l->detail,
            ])->all(),
            'payments'  => $st->payments->map(fn ($p) => [
                'id'     => $p->id,
                'date'   => $this->outDate($p->date),
                'amount' => $this->outMoney($p->amount),
                'note'   => $p->note ?? '',
            ])->all(),
        ];
    }

    public function saveStatement(array $data, ?TruckingStatement $st = null): TruckingStatement
    {
        return DB::transaction(function () use ($data, $st) {
            $customerId = null;
            if (! empty($data['customer'])) {
                $customerId = TruckingCustomer::where('name', trim($data['customer']))->value('id');
            }

            $st ??= new TruckingStatement();
            $st->fill([
                'no'            => $data['no'] ?? $st->no,
                'customer_id'   => $customerId,
                'customer_name' => $this->str($data['customer'] ?? null),
                'info'          => $data['info'] ?? null,
                'date'          => $this->inDate($data['date'] ?? null),
                'period_from'   => $this->inDate($data['from'] ?? null),
                'period_to'     => $this->inDate($data['to'] ?? null),
                // total = TỔNG dòng (chân lý, server tự cộng) — không tin tongThu client để khỏi lệch.
                'total'         => collect($data['lines'] ?? [])->sum(fn ($l) => $this->inMoney($l['phaiThu'] ?? null) ?? 0),
            ]);
            $st->save();

            $st->lines()->delete();
            foreach (($data['lines'] ?? []) as $i => $l) {
                $st->lines()->create([
                    'shipment_id' => $l['id'] ?? null,
                    'booking'     => $this->str($l['booking'] ?? null),
                    'sheet'       => $this->str($l['sheet'] ?? null),
                    'io'          => $this->str($l['io'] ?? null),
                    'decl_no'     => $this->str($l['declNo'] ?? null),
                    'cont_type'   => $this->str($l['contType'] ?? null),
                    'inv'         => $this->str($l['inv'] ?? null),
                    'cont_no'     => $this->str($l['contNo'] ?? null),
                    'bks'         => $this->str($l['bks'] ?? null),
                    'from_loc'    => $this->str($l['from'] ?? null),
                    'to_loc'      => $this->str($l['to'] ?? null),
                    'date'        => $this->inDate($l['date'] ?? null),
                    'cont_label'  => $this->str($l['contLabel'] ?? null),
                    'phai_thu'    => $this->inMoney($l['phaiThu'] ?? null) ?? 0,
                    'cuoc'        => $this->inMoney($l['cuoc'] ?? null) ?? 0,
                    'thanh_ly'    => $this->inMoney($l['thanhLy'] ?? null) ?? 0,
                    'note'        => $this->str($l['note'] ?? null),
                    'detail'      => is_array($l['detail'] ?? null) ? $l['detail'] : null,
                    'sort'        => $i,
                ]);
            }

            $st->payments()->delete();
            foreach (($data['payments'] ?? []) as $i => $p) {
                $st->payments()->create([
                    'date'   => $this->inDate($p['date'] ?? null),
                    'amount' => $this->inMoney($p['amount'] ?? null),
                    'note'   => $this->str($p['note'] ?? null),
                    'sort'   => $i,
                ]);
            }

            return $st->fresh(['lines', 'payments']);
        });
    }
}
