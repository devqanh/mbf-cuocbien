<?php

namespace App\Http\Controllers\Trucking;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Cài đặt Trucking — danh mục (lazy theo tab), khách hàng, đội xe, cấu hình, phí tuyến/giá dầu. */
class CatalogController extends BaseTruckingController
{
    /** Trang Cài đặt — chỉ nạp ĐẾM cho sidebar; mỗi tab lazy-load khi click. */
    public function index()
    {
        return view('trucking2.cai-dat', $this->pageData([
            'counts'  => $this->svc->catalogCounts(),
            'mapsKey' => \App\Models\TruckingSetting::get('gps.google_maps_key', ''),   // cho MapPicker ghim tọa độ kho
        ], 'settings.update', 'settings.update'));
    }

    /** Dữ liệu TƯƠI của 1 tab Cài đặt (lazy-load khi click tab). */
    public function data(string $type): JsonResponse
    {
        return response()->json(['ok' => true, 'cfg' => $this->svc->catalogData($type)]);
    }

    /** Lưu RIÊNG 1 danh mục lookup (mỗi tab Cài đặt = 1 bảng). */
    public function save(Request $request, string $type): JsonResponse
    {
        abort_unless(in_array($type, $this->svc->catalogKeys(), true), 404);
        $cfg = $request->validate(['cfg' => ['required', 'array']])['cfg'];
        $this->svc->saveCatalog($type, $cfg);

        return response()->json(['ok' => true]);
    }

    /** Lưu danh mục Khách hàng (+ thông tin; bảng giá chỉ đụng khi gửi priceList). */
    public function saveCustomers(Request $request): JsonResponse
    {
        $cfg = $request->validate(['cfg' => ['required', 'array']])['cfg'];
        $this->svc->saveCustomers($cfg);

        return response()->json(['ok' => true]);
    }

    /** Đổi tên khách hàng (giữ liên kết). */
    public function renameCustomer(Request $request): JsonResponse
    {
        $data = $request->validate(['old' => ['required', 'string'], 'new' => ['required', 'string']]);
        return response()->json($this->svc->renameCustomer($data['old'], $data['new']));
    }

    /** Lưu danh mục Đội xe (biển số + loại). */
    public function saveVehicles(Request $request): JsonResponse
    {
        $cfg = $request->validate(['cfg' => ['required', 'array']])['cfg'];
        $this->svc->saveVehicles($cfg);

        return response()->json(['ok' => true]);
    }

    /** Lưu cấu hình đơn (VAT mặc định, Free time, cảnh báo hạn…). */
    public function saveSettings(Request $request): JsonResponse
    {
        $cfg = $request->validate(['cfg' => ['required', 'array']])['cfg'];
        $this->svc->saveSettings($cfg);

        return response()->json(['ok' => true]);
    }

    /** Lưu cấu hình Phí tuyến đường (repeater). */
    public function saveRouteFees(Request $request): JsonResponse
    {
        $rows = $request->input('cfg.routeFees', $request->input('rows', []));
        $this->svc->saveRouteFees(is_array($rows) ? $rows : []);

        return response()->json(['ok' => true]);
    }

    /** Xuất Phí tuyến ra Excel (điền nhanh rồi nhập lại). */
    public function exportRouteFees()
    {
        $data = $this->svc->routeFeeExportRows();
        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Phi tuyen');
        $sheet->fromArray($data['header'], null, 'A1');
        if ($data['rows']) $sheet->fromArray($data['rows'], null, 'A2');
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        foreach (range('A', 'M') as $c) $sheet->getColumnDimension($c)->setAutoSize(true);

        $name = 'phi-tuyen-' . now()->format('Ymd-Hi') . '.xlsx';
        return response()->streamDownload(function () use ($ss) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save('php://output');
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** Nhập Phí tuyến từ Excel — upsert theo tuyến (trùng tuyến → cập nhật). */
    public function importRouteFees(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120']]);
        try {
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($request->file('file')->getRealPath())
                ->load($request->file('file')->getRealPath())->getActiveSheet();
            $raw = $sheet->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Không đọc được file Excel.'], 422);
        }
        // cột theo thứ tự khi xuất; bỏ dòng tiêu đề (nếu ô A1 chứa "Tuyến")
        $keys = ['route', 'veTram', 'tienDuong', 'troCap', 'phiKhac', 'luongKeoCru', 'luongKeoKhongCru',
            'luongKhongKeoCru', 'luongKhongKeoKhongCru', 'km', 'dau2', 'dau1', 'chiTheoNgay'];
        $rows = [];
        foreach ($raw as $i => $line) {
            if (! is_array($line)) continue;
            $first = trim((string) ($line[0] ?? ''));
            if ($first === '' ) continue;
            if ($i === 0 && mb_stripos($first, 'tuyến') !== false) continue;   // header
            $row = [];
            foreach ($keys as $ci => $k) $row[$k] = $line[$ci] ?? null;
            $rows[] = $row;
        }
        return response()->json($this->svc->importRouteFees($rows));
    }

    /** Lưu Bảng giá dầu (repeater theo khoảng ngày). */
    public function saveFuelPrices(Request $request): JsonResponse
    {
        $rows = $request->input('cfg.fuelPrices', $request->input('rows', []));
        $this->svc->saveFuelPrices(is_array($rows) ? $rows : []);

        return response()->json(['ok' => true]);
    }
}
