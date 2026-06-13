<?php

namespace App\Console\Commands;

use App\Models\TruckingCustomer;
use App\Models\TruckingDriver;
use App\Models\TruckingFuelPrice;
use App\Models\TruckingRouteFee;
use App\Models\TruckingShipment;
use App\Models\TruckingTripCostBatch;
use App\Models\TruckingTripCostLine;
use App\Models\TruckingVehicle;
use App\Models\TruckingVehicleUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seed / clear DỮ LIỆU DEMO cho Phí xe nội bộ (tháng 6/2026).
 *
 *   php artisan trucking:demo          # tạo demo (xe + lái xe + phí tuyến + giá dầu + lô T6 khớp tuyến)
 *   php artisan trucking:demo clear    # XÓA toàn bộ demo → hệ thống mới tinh (giữ nguyên dữ liệu gốc)
 *
 * AN TOÀN: chỉ đụng dữ liệu DO LỆNH NÀY tạo (lô booking 'DEMO6-*', xe/lái xe/tuyến demo theo
 * danh sách cố định, giá dầu ghi chú 'DEMO', kỳ phí xe có lô demo). KHÔNG xóa địa điểm / kho /
 * khách hàng / bảng giá (dữ liệu thật).
 */
class TruckingDemo extends Command
{
    protected $signature = 'trucking:demo {action=seed : seed | clear}';
    protected $description = 'Seed/clear dữ liệu demo Phí xe nội bộ tháng 6/2026';

    private const BOOKING_PREFIX = 'DEMO6-';
    private const FUEL_NOTE = 'DEMO giá dầu T6/2026';

    // Xe MBF demo: [biển số, số cầu]
    private const VEHICLES = [
        ['51C-201.11', '2'], ['51C-202.22', '1'], ['51C-203.33', '2'], ['51C-204.44', '1'],
        ['51C-205.55', '2'], ['51C-206.66', '1'], ['51C-207.77', '2'], ['51C-208.88', '1'],
    ];

    // Lái xe demo: [tên, SĐT]
    private const DRIVERS = [
        ['Nguyễn Văn An (demo)', '0901 111 111'],
        ['Trần Quốc Bảo (demo)', '0902 222 222'],
        ['Lê Minh Cường (demo)', '0903 333 333'],
        ['Phạm Văn Dũng (demo)', '0904 444 444'],
        ['Hoàng Văn Em (demo)', '0905 555 555'],
    ];

    // Tuyến demo (tập kho) — khớp bảng giá Canon (HPP → TL/TS/QV/CEV)
    private const ROUTES = [
        ['TL'], ['TS'], ['QV'], ['CEV'],
        ['TL', 'TS'], ['TL', 'QV'], ['TS', 'QV'],
        ['TL', 'TS', 'QV'], ['CEV', 'TL'],
    ];

    public function handle(): int
    {
        $action = $this->argument('action');
        if ($action === 'clear') { $this->clearDemo(); return self::SUCCESS; }
        if ($action !== 'seed')  { $this->error("Action không hợp lệ: {$action} (dùng seed | clear)"); return self::FAILURE; }

        $this->clearDemo(true);   // idempotent: dọn demo cũ trước khi seed lại
        $this->seedDemo();
        return self::SUCCESS;
    }

    private function seedDemo(): void
    {
        $cust = TruckingCustomer::where('name', 'Canon Vietnam')->first()
            ?? TruckingCustomer::withCount('priceRows')->orderByDesc('price_rows_count')->first();
        if (! $cust) { $this->error('Không có khách hàng nào — cần dữ liệu gốc trước.'); return; }
        $this->info("Khách hàng demo: {$cust->name}");

        DB::transaction(function () use ($cust) {
            // 1) Lái xe (kèm SĐT + ngày vào công ty để có thâm niên)
            $drivers = [];
            foreach (self::DRIVERS as $k => [$name, $phone]) {
                $drivers[] = TruckingDriver::create([
                    'name' => $name, 'phones' => [$phone],
                    'joined_date' => Carbon::create(2023, 1 + $k, 10)->format('Y-m-d'),
                    'sort' => 900 + $k,
                ]);
            }

            // 2) Xe MBF + gán lái xe chạy suốt tháng 6 (để phí-xe tự suy ra tài xế)
            $vehicles = [];
            foreach (self::VEHICLES as $k => [$plate, $axle]) {
                $v = TruckingVehicle::create(['plate' => $plate, 'type' => 'MBF', 'axle' => $axle]);
                TruckingVehicleUsage::create([
                    'vehicle_id' => $v->id,
                    'driver'     => $drivers[$k % count($drivers)]->name,
                    'from_date'  => '2026-06-01', 'to_date' => '2026-06-30',
                    'note'       => 'DEMO ca tháng 6', 'sort' => 0,
                ]);
                $vehicles[] = $v;
            }

            // 3) Phí tuyến đường khớp tập kho (route_key tự tính từ text khi đối chiếu)
            foreach (self::ROUTES as $i => $kho) {
                $n = count($kho);
                TruckingRouteFee::create([
                    'route'        => implode(' - ', $kho),
                    'route_key'    => implode('|', array_map('mb_strtoupper', $kho)),
                    've_tram'      => 120000,
                    'tien_duong'   => 150000 + 120000 * ($n - 1),
                    'tro_cap'      => 80000 * $n,
                    'phi_khac'     => 50000,
                    'cru'          => false,
                    'luong'        => 250000 + 120000 * ($n - 1),
                    'salary_parts' => ['troCap', 'luong'],
                    'km'           => 55 * $n,
                    'dau_1cau'     => 28 * $n,
                    'dau_2cau'     => (int) round(28 * $n * 1.25),
                    'sort'         => 900 + $i,
                ]);
            }

            // 4) Giá dầu tháng 6
            TruckingFuelPrice::create([
                'from_date' => '2026-06-01', 'to_date' => null,
                'price' => 20000, 'note' => self::FUEL_NOTE, 'sort' => 900,
            ]);

            // 5) Lô hàng tháng 6 — khớp tuyến + xe + ngày xe ra
            $count = 24;
            for ($i = 0; $i < $count; $i++) {
                $kho = self::ROUTES[$i % count(self::ROUTES)];
                $veh = $vehicles[$i % count($vehicles)];
                $day = ($i % 26) + 2;                  // 2..27/6
                $hour = 7 + ($i % 10);                  // 7h..16h
                TruckingShipment::create([
                    'sheet'       => 'icd',
                    'customer_id' => $cust->id,
                    'booking'     => self::BOOKING_PREFIX . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'cont_type'   => $i % 2 ? '40HC' : '20',
                    'cont_no'     => 'DEMO' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                    'cru'         => $i % 3 === 0,
                    'qty'         => 1,
                    'kho'         => implode(', ', $kho),
                    'from_loc'    => 'HPP',
                    'to_loc'      => 'Hải Phòng',
                    'bks_vao'     => $veh->plate,
                    'gio_xe_ra'   => Carbon::create(2026, 6, $day, $hour, 0, 0)->format('Y-m-d H:i:s'),
                    'vat_rate'    => '0',
                ]);
            }
        });

        $this->info('✓ Đã seed demo: ' . count(self::DRIVERS) . ' lái xe, ' . count(self::VEHICLES) . ' xe MBF, '
            . count(self::ROUTES) . ' phí tuyến, 1 giá dầu, 24 lô tháng 6.');
        $this->line('→ Vào /trucking-v2/phi-xe/tao, chọn Ngày xe ra 01/06/2026 → 30/06/2026, bấm Tính để xem.');
        $this->line('→ Dọn demo: php artisan trucking:demo clear');
    }

    private function clearDemo(bool $quiet = false): void
    {
        DB::transaction(function () use ($quiet) {
            // Kỳ phí xe có chứa lô demo (xóa trước để gỡ tham chiếu)
            $demoShipIds = TruckingShipment::where('booking', 'like', self::BOOKING_PREFIX . '%')->pluck('id');
            $batchIds = TruckingTripCostLine::whereIn('shipment_id', $demoShipIds)->pluck('batch_id')->unique()->values();
            $batchIds = $batchIds->merge(TruckingTripCostBatch::where('name', 'like', '%demo%')->orWhere('name', 'like', '%DEMO%')->pluck('id'))->unique();
            $nBatch = TruckingTripCostBatch::whereIn('id', $batchIds)->count();
            TruckingTripCostBatch::whereIn('id', $batchIds)->delete();   // cascade lines

            $nShip = TruckingShipment::where('booking', 'like', self::BOOKING_PREFIX . '%')->count();
            TruckingShipment::where('booking', 'like', self::BOOKING_PREFIX . '%')->delete();   // cascade cost/rev

            $plates = array_column(self::VEHICLES, 0);
            $nVeh = TruckingVehicle::whereIn('plate', $plates)->count();
            TruckingVehicle::whereIn('plate', $plates)->delete();   // cascade usages/costs/deps

            $names = array_column(self::DRIVERS, 0);
            $nDrv = TruckingDriver::whereIn('name', $names)->count();
            TruckingDriver::whereIn('name', $names)->delete();

            $routeTexts = array_map(fn ($k) => implode(' - ', $k), self::ROUTES);
            $nRoute = TruckingRouteFee::whereIn('route', $routeTexts)->count();
            TruckingRouteFee::whereIn('route', $routeTexts)->delete();

            $nFuel = TruckingFuelPrice::where('note', self::FUEL_NOTE)->count();
            TruckingFuelPrice::where('note', self::FUEL_NOTE)->delete();

            if (! $quiet) {
                $this->info("✓ Đã xóa demo: {$nShip} lô, {$nBatch} kỳ phí xe, {$nVeh} xe, {$nDrv} lái xe, {$nRoute} phí tuyến, {$nFuel} giá dầu.");
                $this->line('Dữ liệu gốc (địa điểm/kho/khách hàng/bảng giá) giữ nguyên — hệ thống đã mới tinh.');
            }
        });
    }
}
