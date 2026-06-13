<?php

namespace App\Console\Commands;

use App\Services\TruckingV2Service;
use Illuminate\Console\Command;

/**
 * Seed dữ liệu DEMO khớp nhau (cài đặt + khách + bảng giá + lô hàng) để test
 * thủ công Bảng kê: kiểm tra định giá theo bảng giá (Cước+Dầu theo loại cont +
 * Chi hộ) và Connect/Disconnect theo Free time.
 */
class TruckingSeedDemo extends Command
{
    protected $signature = 'trucking:seed-demo {--keep : Giữ dữ liệu hiện có (không xóa trước khi seed)}';

    protected $description = 'Xóa sạch rồi seed dữ liệu demo khớp bảng giá để test bảng kê';

    public function handle(TruckingV2Service $svc): int
    {
        if (! $this->option('keep')) {
            $this->call('trucking:clear', ['--force' => true]);
        }

        // 1) Danh mục + cấu hình + khách + BẢNG GIÁ
        $svc->saveConfig([
            'locations'     => ['Cảng Hải Phòng', 'ICD Quế Võ', 'KCN Tiên Sơn', 'KCN Thăng Long'],
            'locationCode'  => ['Cảng Hải Phòng' => 'HPP', 'ICD Quế Võ' => 'QV', 'KCN Tiên Sơn' => 'TS', 'KCN Thăng Long' => 'TL'],
            'warehouses'    => ['TL', 'TS', 'QV', 'Kho A2'],
            'payers'        => ['Tài xế', 'Xe ngoài', 'TK công ty', 'Khách'],
            'drivers'       => ['A.Tuấn', 'A.Nam'],
            'contTypes'     => ['20DC', '40HC', '40DC', '45HC'],
            'costItems'     => ['Cước xe ngoài', 'Nâng', 'Hạ', 'CSHT', 'Hải quan'],
            'choHoItems'    => ['Nâng', 'Hạ', 'CSHT'],
            'revItems'      => ['Doanh thu cước xe'],
            'prices'        => ['Nâng' => '500000', 'Hạ' => '450000', 'CSHT' => '150000'],
            'vehicles'      => ['15C-123.45', '15C-678.90'],
            'vehicleType'   => ['15C-123.45' => 'MBF', '15C-678.90' => 'Ngoài'],
            'vatDefault'    => ['hph' => '0', 'icd' => '0'],
            'freeTimeHours' => '4',
            'customers'     => ['Canon Vietnam'],
            'customerInfo'  => ['Canon Vietnam' => [
                'shortName' => 'Canon', 'taxCode' => '0101234567', 'phone' => '024 3927 5555',
                'contact' => 'Chị Hồng — KT', 'email' => 'ketoan@canon.vn', 'termDays' => '30',
                'address' => 'KCN Thăng Long, Đông Anh, Hà Nội',
                'priceList' => [
                    ['loc' => 'ICD Quế Võ',   'conn' => 'Disconnect', 'kind' => 'Transportation 1 way of Import/Export', 'from' => 'HPP', 'to1' => 'TL', 'to4' => 'HPP', 'distance' => '287', 'transFee40' => '4000000', 'transFee20' => '3320000', 'fuelFee40' => '2800000', 'fuelFee20' => '2644533'],
                    ['loc' => 'ICD Quế Võ',   'conn' => 'Connect',    'kind' => 'Transportation 1 way of Import/Export', 'from' => 'HPP', 'to1' => 'TL', 'to4' => 'HPP', 'distance' => '287', 'transFee40' => '4200000', 'transFee20' => '3500000', 'fuelFee40' => '2900000', 'fuelFee20' => '2700000'],
                    ['loc' => 'KCN Tiên Sơn', 'conn' => 'Disconnect', 'kind' => 'Transportation 1 way of Import/Export', 'from' => 'HPP', 'to1' => 'TS', 'to4' => 'HPP', 'distance' => '266', 'transFee40' => '3800000', 'transFee20' => '3000000', 'fuelFee40' => '2600000', 'fuelFee20' => '2451030'],
                    ['loc' => 'ICD Quế Võ',   'conn' => 'Disconnect', 'kind' => 'Internal CRU transportation', 'from' => 'HPP', 'to1' => 'TL', 'to4' => 'HPP', 'distance' => '312', 'transFee40' => '4500000', 'transFee20' => '3600000', 'fuelFee40' => '3000000', 'fuelFee20' => '2700000'],
                    ['loc' => 'ICD Quế Võ',   'conn' => 'Disconnect', 'kind' => 'External CRU transportation', 'from' => 'HPP', 'to1' => 'TL', 'to4' => 'HPP', 'distance' => '320', 'transFee40' => '4800000', 'transFee20' => '3900000', 'fuelFee40' => '3200000', 'fuelFee20' => '2900000'],
                ],
            ]],
        ]);

        // 2) Lô hàng — thiết kế khớp đúng dòng bảng giá
        // [booking, nơi lấy, nơi hạ, loại cont, giờ đến KH, giờ xe ra, chi hộ, ngày cont ra, io, cru]
        $lots = [
            ['BK-2601', 'Cảng Hải Phòng', 'ICD Quế Võ',    '20DC', '2026-05-20T08:00', '2026-05-20T10:00', 300000, '2026-05-20', 'Nhập', false], // 1 way, Disconnect, 20FT
            ['BK-2602', 'Cảng Hải Phòng', 'ICD Quế Võ',    '40HC', '2026-05-21T08:00', '2026-05-21T15:00', 0,      '2026-05-21', 'Nhập', false], // 1 way, Connect, 40FT
            ['BK-2603', 'Cảng Hải Phòng', 'KCN Tiên Sơn',  '20DC', '2026-05-22T08:00', '2026-05-22T10:30', 150000, '2026-05-22', 'Nhập', false], // 1 way, Disconnect, 20FT
            ['BK-2604', 'Cảng Hải Phòng', 'KCN Thăng Long', '20DC', '2026-05-23T08:00', '2026-05-23T09:30', 100000, '2026-05-23', 'Nhập', false], // chưa có giá → chưa khớp
            ['BK-2605', 'Cảng Hải Phòng', 'ICD Quế Võ',    '20DC', '2026-05-24T08:00', '2026-05-24T10:00', 0,      '2026-05-24', 'Nhập', true],  // CRU + Nhập → Internal CRU, 20FT
            ['BK-2606', 'Cảng Hải Phòng', 'ICD Quế Võ',    '40HC', '2026-05-25T08:00', '2026-05-25T10:00', 0,      '2026-05-25', 'Xuất', true],  // CRU + Xuất → External CRU, 40FT
        ];
        $i = 0;
        foreach ($lots as [$bk, $from, $to, $ct, $gdk, $gxr, $chiHo, $contRa, $io, $cru]) {
            $svc->saveShipment([
                'customer' => 'Canon Vietnam', 'booking' => $bk, 'io' => $io, 'cru' => $cru, 'qty' => 1,
                'contType' => $ct, 'contNo' => 'TGHU' . (1000000 + (++$i)), 'kho' => 'TL',
                'from' => $from, 'to' => $to, 'contDen' => $contRa, 'contRa' => $contRa,
                'gioDenDuKien' => $gdk, 'gioXeRa' => $gxr,
                'cost' => ['items' => []],
                'rev'  => ['vatRate' => '0', 'doanhThu' => [], 'payments' => [],
                    'choHo' => $chiHo > 0 ? [['item' => 'Nâng', 'amount' => (string) $chiHo]] : []],
            ], 'icd');
        }

        // 3) In số phải thu kỳ vọng để đối chiếu
        // [booking, điểm hạ, KIND ngắn, conn, loại, cước, dầu, chi hộ]
        $rows = [
            ['BK-2601', 'ICD Quế Võ',    '1 chiều',  'Disconnect', '20FT', 3320000, 2644533, 300000],
            ['BK-2602', 'ICD Quế Võ',    '1 chiều',  'Connect',    '40FT', 4200000, 2900000, 0],
            ['BK-2603', 'KCN Tiên Sơn',  '1 chiều',  'Disconnect', '20FT', 3000000, 2451030, 150000],
            ['BK-2604', 'KCN Thăng Long', 'chưa khớp', '—',         '20FT', 0, 0, 100000],
            ['BK-2605', 'ICD Quế Võ',    'CRU nội',  'Disconnect', '20FT', 3600000, 2700000, 0],
            ['BK-2606', 'ICD Quế Võ',    'CRU ngoại', 'Disconnect', '40FT', 4800000, 3200000, 0],
        ];
        $fmt = fn ($n) => number_format($n, 0, ',', '.') . ' ₫';
        $total = 0;
        $this->newLine();
        $this->info('Đã seed: 1 khách (Canon Vietnam), 5 dòng bảng giá, 6 lô hàng (ICD).');
        $this->line('Phải thu kỳ vọng = Cước + Dầu (theo loại cont) + Chi hộ:');
        $this->line(str_repeat('─', 92));
        foreach ($rows as [$bk, $drop, $kind, $conn, $ft, $cuoc, $dau, $chiho]) {
            $pt = $cuoc + $dau + $chiho;
            $total += $pt;
            $this->line(sprintf('  %-8s %-15s %-10s %-12s %-5s  Cước %s + Dầu %s + Chi hộ %s = %s',
                $bk, $drop, $kind, $conn, $ft, $fmt($cuoc), $fmt($dau), $fmt($chiho), $fmt($pt)));
        }
        $this->line(str_repeat('─', 92));
        $this->info('  TỔNG cả 6 lô: ' . $fmt($total));
        $this->newLine();
        $this->line('Test: vào /trucking-v2/bang-ke/tao → chọn "Canon Vietnam", kỳ 2026-05-01 → 2026-05-31.');
        $this->line('Đối chiếu số phải thu từng lô + tổng với bảng trên (BK-2604 sẽ báo ⚠ chưa khớp, chỉ có Chi hộ).');
        $this->line('BK-2605 (CRU+Nhập→Internal CRU) & BK-2606 (CRU+Xuất→External CRU) kiểm tra dò KIND theo cờ CRU.');

        return self::SUCCESS;
    }
}
