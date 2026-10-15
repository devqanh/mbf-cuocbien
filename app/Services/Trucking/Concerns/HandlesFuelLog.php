<?php

namespace App\Services\Trucking\Concerns;

use App\Models\TruckingFuelPrice;
use App\Models\TruckingFuelRefill;
use App\Models\TruckingVehicle;
use Illuminate\Database\Eloquent\Builder;

/**
 * NHẬT KÝ ĐỔ DẦU (menu Lô hàng) — xem/ghi phiếu đổ dầu của MỌI xe MBF trên 1 trang.
 * Dùng chung bảng trucking_fuel_refills với tab "Dầu" ở Quản lý xe (saveFuelRefill/deleteFuelRefill),
 * nên ghi ở đâu cũng hiện ở cả hai nơi.
 */
trait HandlesFuelLog
{
    /** Boot trang: danh sách xe MBF (chọn nhanh) + bảng giá dầu (gợi ý đơn giá theo ngày đổ). */
    public function fuelLogBoot(): array
    {
        return [
            'vehicles' => TruckingVehicle::mbfFleet()->with('driver:id,name')->orderBy('plate')->get(['id', 'plate', 'driver_id'])
                ->map(fn ($v) => ['id' => $v->id, 'plate' => $v->plate, 'driver' => $v->driver?->name ?? ''])->all(),
            'fuelPrices' => TruckingFuelPrice::orderBy('from_date')->get(['from_date', 'to_date', 'price'])
                ->map(fn ($p) => ['from' => $this->outDate($p->from_date), 'to' => $this->outDate($p->to_date), 'price' => (int) round((float) $p->price)])->all(),
        ];
    }

    /** Lọc chung cho danh sách + tổng hợp: khoảng ngày đổ, xe (nhiều), tìm theo biển số / trạm / ghi chú. */
    private function fuelLogQuery(array $f): Builder
    {
        $q = TruckingFuelRefill::query()->whereHas('vehicle', fn ($v) => $v->mbfFleet());
        if ($from = $this->inDate($f['from'] ?? null)) $q->whereDate('refill_date', '>=', $from);
        if ($to = $this->inDate($f['to'] ?? null))     $q->whereDate('refill_date', '<=', $to);
        $ids = array_values(array_filter(array_map('intval', (array) ($f['vehicles'] ?? []))));
        if ($ids) $q->whereIn('vehicle_id', $ids);
        if ($s = trim((string) ($f['q'] ?? ''))) {
            // Biển số so khi đã bỏ "-", ".", khoảng trắng → gõ "29E72123" hay "29e-721" đều khớp "29E-72123".
            $plate = str_replace([' ', '-', '.'], '', $s);
            $q->where(fn ($w) => $w->where('station', 'like', "%$s%")->orWhere('note', 'like', "%$s%")
                ->orWhereHas('vehicle', fn ($v) => $v->whereRaw("REPLACE(REPLACE(REPLACE(plate, '-', ''), '.', ''), ' ', '') LIKE ?", ["%$plate%"])));
        }
        return $q;
    }

    /** Danh sách phiếu (phân trang, mới nhất trước) + tổng hợp toàn bộ kết quả lọc + tổng theo xe. */
    public function fuelLogData(array $f, int $page = 1, int $perPage = 50): array
    {
        $perPage = max(10, min(200, $perPage));
        $total = (clone $this->fuelLogQuery($f))->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));

        $rows = (clone $this->fuelLogQuery($f))->with('vehicle:id,plate')
            ->orderByDesc('refill_date')->orderByDesc('id')
            ->forPage($page, $perPage)->get()
            ->map(fn ($r) => [
                'id'         => $r->id,
                'vehicleId'  => $r->vehicle_id,
                'plate'      => $r->vehicle?->plate ?? '',
                'date'       => $this->outDate($r->refill_date),
                'liters'     => (float) $r->liters,
                'unitPrice'  => $r->unit_price !== null ? (int) round((float) $r->unit_price) : null,
                'totalCost'  => $r->total_cost !== null ? (int) round((float) $r->total_cost) : null,
                'odometerKm' => $r->odometer_km !== null ? (float) $r->odometer_km : null,
                'station'    => $r->station ?? '',
                'note'       => $r->note ?? '',
            ])->all();

        // Tổng theo xe trên TOÀN BỘ kết quả lọc (không chỉ trang hiện tại).
        $byVehicle = (clone $this->fuelLogQuery($f))
            ->selectRaw('vehicle_id, COUNT(*) as cnt, SUM(liters) as liters, SUM(total_cost) as cost, MIN(refill_date) as first_date, MAX(refill_date) as last_date, COUNT(DISTINCT refill_date) as days')
            ->groupBy('vehicle_id')->get();
        $plates = TruckingVehicle::whereIn('id', $byVehicle->pluck('vehicle_id'))->pluck('plate', 'id');
        $vehicles = $byVehicle->map(fn ($r) => [
            'vehicleId' => (int) $r->vehicle_id,
            'plate'     => $plates[$r->vehicle_id] ?? '',
            'count'     => (int) $r->cnt,
            'days'      => (int) $r->days,
            'liters'    => round((float) $r->liters, 2),
            'cost'      => (int) round((float) $r->cost),
            'firstDate' => (string) $r->first_date,
            'lastDate'  => (string) $r->last_date,
        ])->sortByDesc('liters')->values()->all();

        return [
            'rows'     => $rows,
            'page'     => $page, 'perPage' => $perPage, 'total' => $total, 'lastPage' => $lastPage,
            'vehicles' => $vehicles,
            'totals'   => [
                'count'    => $total,
                'liters'   => round(array_sum(array_column($vehicles, 'liters')), 2),
                'cost'     => array_sum(array_column($vehicles, 'cost')),
                'vehicles' => count($vehicles),
            ],
        ];
    }
}
