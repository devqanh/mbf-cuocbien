<?php

namespace App\Http\Controllers\Trucking;

use App\Models\TruckingDriver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Hồ sơ lái xe — lưu thông tin + tài liệu (CCCD/bằng lái). */
class DriverController extends BaseTruckingController
{
    /** Lưu hồ sơ lái xe (tên/SĐT/ngày/tài khoản) — tài liệu quản lý qua upload riêng. */
    public function save(Request $request): JsonResponse
    {
        $rows = $request->input('cfg.drivers', $request->input('rows', []));
        $this->svc->saveDrivers(is_array($rows) ? $rows : []);

        return response()->json(['ok' => true, 'drivers' => $this->svc->catalogData('drivers')['drivers']]);
    }

    /** Tải tài liệu (CCCD/bằng lái — file hoặc ảnh, nhiều file 1 lần). */
    public function uploadDocs(Request $request, TruckingDriver $driver): JsonResponse
    {
        $request->validate([
            'files'   => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,heic,pdf,doc,docx,xls,xlsx,csv'],
            'type'    => ['nullable', 'string', 'max:60'],
        ]);
        $docs = $this->svc->uploadDriverDocs($driver, $request->file('files', []), (string) $request->input('type', ''));

        return response()->json(['ok' => true, 'docs' => $docs]);
    }

    public function deleteDoc(TruckingDriver $driver, int $idx): JsonResponse
    {
        $docs = $this->svc->deleteDriverDoc($driver, $idx);   // $idx = id attachment
        return response()->json(['ok' => true, 'docs' => $docs]);
    }
}
