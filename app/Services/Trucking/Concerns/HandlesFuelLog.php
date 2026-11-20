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

    /**
     * NHẬP EXCEL hàng loạt phiếu đổ dầu (client đã đọc file → $rows: [{line, date (Y-m-d), plate, liters, unitPrice?, totalCost?,
     * odometerKm?, station?, note?}]). Kiểm tra từng dòng: ngày, xe MBF (biển số bỏ qua "-", ".", khoảng trắng), số lít > 0;
     * dòng GIỐNG HỆT phiếu đã có (cùng xe + ngày + số lít) = trùng → bỏ qua, nên nhập lại cùng 1 file không bị cộng đôi.
     * Thiếu đơn giá và thành tiền → lấy đơn giá theo Bảng giá dầu của ngày đổ (như ô ghi nhanh).
     * $commit = false: chỉ trả kết quả kiểm tra (xem trước); true: ghi các dòng hợp lệ trong 1 transaction.
     */
    public function fuelLogImport(array $rows, bool $commit): array
    {
        $norm = fn ($p) => mb_strtoupper(str_replace([' ', '-', '.'], '', trim((string) $p)));
        $fleet = TruckingVehicle::mbfFleet()->get(['id', 'plate'])->keyBy(fn ($v) => $norm($v->plate));
        $fuels = TruckingFuelPrice::orderByDesc('from_date')->orderByDesc('id')->get();
        $key = fn ($vid, $date, $liters) => $vid . '|' . $date . '|' . number_format((float) $liters, 2, '.', '');

        // Kiểm tra từng dòng trước (chưa xét trùng) → gom xe + ngày để tra phiếu đã có 1 lần.
        $out = [];
        foreach ($rows as $i => $r) {
            if (! is_array($r)) continue;
            $date = $this->inDate($r['date'] ?? null);
            $plate = trim((string) ($r['plate'] ?? ''));
            $liters = is_numeric($r['liters'] ?? null) ? round((float) $r['liters'], 2) : 0.0;
            $veh = $plate !== '' ? $fleet->get($norm($plate)) : null;
            $err = ! $date ? 'Thiếu / sai ngày đổ'
                : ($plate === '' ? 'Thiếu biển số'
                : (! $veh ? 'Không tìm thấy xe MBF có biển số này'
                : ($liters <= 0 ? 'Số lít phải lớn hơn 0' : '')));
            $unit = is_numeric($r['unitPrice'] ?? null) && (float) $r['unitPrice'] > 0 ? (int) round((float) $r['unitPrice']) : null;
            $total = is_numeric($r['totalCost'] ?? null) && (float) $r['totalCost'] > 0 ? (int) round((float) $r['totalCost']) : null;
            $priceSrc = $unit !== null ? 'file' : ($total !== null ? 'total' : '');
            if ($unit === null && $total === null && $date && ($p = $this->fuelPriceForDate($fuels, $date)) > 0) { $unit = (int) round($p); $priceSrc = 'bangGia'; }
            $out[] = [
                'line' => (int) ($r['line'] ?? $i + 1), 'date' => $date ?: '', 'plate' => $veh?->plate ?? $plate, 'vehicleId' => $veh?->id,
                'liters' => $liters, 'unitPrice' => $unit, 'totalCost' => $unit !== null ? (int) round($liters * $unit) : $total, 'priceSrc' => $priceSrc,
                'odometerKm' => is_numeric($r['odometerKm'] ?? null) ? (float) $r['odometerKm'] : null,
                'station' => mb_substr(trim((string) ($r['station'] ?? '')), 0, 255), 'note' => mb_substr(trim((string) ($r['note'] ?? '')), 0, 2000),
                'status' => $err !== '' ? 'error' : 'ok', 'message' => $err,
            ];
        }

        // Trùng phiếu đã có: đếm theo (xe, ngày, lít) — file có 2 dòng giống nhau mà DB mới có 1 thì vẫn nhập dòng thứ 2.
        $okRows = array_filter($out, fn ($x) => $x['status'] === 'ok');
        $have = [];
        if ($okRows) {
            TruckingFuelRefill::whereIn('vehicle_id', array_unique(array_column($okRows, 'vehicleId')))
                ->whereIn('refill_date', array_unique(array_column($okRows, 'date')))
                ->get(['vehicle_id', 'refill_date', 'liters'])
                ->each(function ($f) use (&$have, $key) { $k = $key($f->vehicle_id, $f->refill_date->format('Y-m-d'), $f->liters); $have[$k] = ($have[$k] ?? 0) + 1; });
        }
        foreach ($out as &$x) {
            if ($x['status'] !== 'ok') continue;
            $k = $key($x['vehicleId'], $x['date'], $x['liters']);
            if (($have[$k] ?? 0) > 0) { $have[$k]--; $x['status'] = 'dup'; $x['message'] = 'Đã có phiếu cùng xe, ngày và số lít — bỏ qua'; }
        }
        unset($x);

        $created = 0;
        if ($commit) {
            $vehicles = TruckingVehicle::whereIn('id', array_unique(array_filter(array_column($out, 'vehicleId'))))->get()->keyBy('id');
            \Illuminate\Support\Facades\DB::transaction(function () use ($out, $vehicles, &$created) {
                foreach ($out as $x) {
                    if ($x['status'] !== 'ok') continue;
                    $this->saveFuelRefill($vehicles[$x['vehicleId']], [
                        'date' => $x['date'], 'liters' => $x['liters'], 'unitPrice' => $x['unitPrice'], 'totalCost' => $x['totalCost'],
                        'odometerKm' => $x['odometerKm'], 'station' => $x['station'], 'note' => $x['note'],
                    ]);
                    $created++;
                }
            });
        }

        $count = fn ($s) => count(array_filter($out, fn ($x) => $x['status'] === $s));
        return [
            'rows' => $out,
            'summary' => ['ok' => $count('ok'), 'dup' => $count('dup'), 'error' => $count('error'),
                'liters' => round(array_sum(array_column(array_filter($out, fn ($x) => $x['status'] === 'ok'), 'liters')), 2)],
            'created' => $created,
        ];
    }
}
