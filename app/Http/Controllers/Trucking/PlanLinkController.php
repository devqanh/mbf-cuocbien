<?php

namespace App\Http\Controllers\Trucking;

use App\Models\TruckingPlanLink;
use App\Models\TruckingSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Link kế hoạch — admin tạo/sửa/bật-tắt/xóa; trang CÔNG KHAI cho lái xe (không đăng nhập). */
class PlanLinkController extends BaseTruckingController
{
    /** Tính năng Link kế hoạch có đang bật không (bật/tắt ở Cài đặt hệ thống → Tính năng). */
    private function featureEnabled(): bool
    {
        return TruckingSetting::bool('sys.feature_plan_link', true);
    }

    // ---------- Admin ----------
    public function index()
    {
        if (! $this->featureEnabled()) {
            return redirect()->route('trucking2.shipments')
                ->with('error', 'Tính năng “Link kế hoạch” đang tắt. Bật lại ở Cài đặt hệ thống → Tính năng.');
        }
        return view('trucking2.ke-hoach', $this->pageData([
            'links' => $this->svc->planLinksForList(),
        ], 'shipments.update', 'shipments.update'));
    }

    public function create(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'  => ['required', 'string', 'max:20'],
            'to'    => ['required', 'string', 'max:20'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);
        return response()->json($this->svc->createPlanLink($data, auth()->id()));
    }

    public function update(Request $request, TruckingPlanLink $planLink): JsonResponse
    {
        $data = $request->validate([
            'from'  => ['required', 'string', 'max:20'],
            'to'    => ['required', 'string', 'max:20'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);
        return response()->json($this->svc->updatePlanLink($planLink, $data));
    }

    public function toggle(Request $request, TruckingPlanLink $planLink): JsonResponse
    {
        $this->svc->setPlanLinkActive($planLink, (bool) $request->boolean('active'));
        return response()->json(['ok' => true]);
    }

    public function destroy(TruckingPlanLink $planLink): JsonResponse
    {
        $this->svc->deletePlanLink($planLink);
        return response()->json(['ok' => true]);
    }

    // ---------- Công khai (lái xe, không đăng nhập) ----------
    private function activeLink(string $token): TruckingPlanLink
    {
        $l = TruckingPlanLink::where('token', $token)->first();
        abort_if(! $l, 404);
        abort_if(! $l->active, 410, 'Link kế hoạch đã ngừng hoạt động.');
        return $l;
    }

    /** Trang công khai cho lái xe (mobile). */
    public function publicPage(string $token)
    {
        $l = TruckingPlanLink::where('token', $token)->first();
        $boot = ['active' => (bool) ($l && $l->active)];
        if ($l && $l->active) $boot['data'] = $this->svc->planPublicData($l);
        return view('trucking2.ke-hoach-public', ['boot' => $boot, 'token' => $token]);
    }

    public function publicData(string $token): JsonResponse
    {
        return response()->json(['ok' => true] + $this->svc->planPublicData($this->activeLink($token)));
    }

    public function publicUpdate(Request $request, string $token, string $ship): JsonResponse
    {
        $data = $request->validate([
            'gioXeDen'   => ['nullable', 'string', 'max:25'],
            'gioXeRa'    => ['nullable', 'string', 'max:25'],
            'driverNote' => ['nullable', 'string', 'max:1000'],
            'photos'     => ['nullable', 'array', 'max:12'],
            'photos.*'   => ['file', 'image', 'max:20480'],
        ]);
        return response()->json($this->svc->planUpdateShipment($this->activeLink($token), $ship, $data, $request->file('photos', [])));
    }

    public function publicDeletePhoto(string $token, string $ship, int $att): JsonResponse
    {
        return response()->json($this->svc->planDeletePhoto($this->activeLink($token), $ship, $att));
    }
}
