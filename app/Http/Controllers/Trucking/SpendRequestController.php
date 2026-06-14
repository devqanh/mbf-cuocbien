<?php

namespace App\Http\Controllers\Trucking;

use App\Models\TruckingVehicleCost;
use App\Support\Hashid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Yêu cầu chi (mobile SPA) — login đơn giản (bỏ 2FA), gửi/sửa/hủy phiếu, lịch sử. */
class SpendRequestController extends BaseTruckingController
{
    /** Trang gửi yêu cầu chi — cần đăng nhập + quyền spend.request. */
    public function page()
    {
        $u = auth()->user();
        $can = $u && $u->can('spend.request');
        $boot = ['auth' => ['logged' => (bool) $u, 'name' => $u?->name ?? '', 'canRequest' => (bool) $can]];
        if ($can) {
            $boot = array_merge($boot, $this->svc->publicRequestData());
            // Lịch sử lazy-load khi mở tab — boot chỉ cần SỐ LƯỢNG cho badge.
            $boot['historyCount'] = TruckingVehicleCost::where('created_by', $u->id)->count();
        }
        return view('trucking2.yeu-cau-chi', ['boot' => $boot]);
    }

    /** Đăng nhập mobile (đơn giản, BỎ 2FA). */
    public function login(Request $request): JsonResponse
    {
        // Validate THỦ CÔNG (trả 200 + ok:false) để lỗi hiện NGAY trong form, tiếng Việt.
        $email = trim((string) $request->input('email'));
        $password = (string) $request->input('password');
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['ok' => false, 'message' => 'Email không hợp lệ. Vui lòng nhập đúng địa chỉ email.']);
        }
        if ($password === '') {
            return response()->json(['ok' => false, 'message' => 'Vui lòng nhập mật khẩu.']);
        }
        $remember = ! $request->has('remember') || $request->boolean('remember');   // mặc định LUÔN đăng nhập
        if (! Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            return response()->json(['ok' => false, 'message' => 'Email hoặc mật khẩu không đúng.']);
        }
        $u = auth()->user();
        if (! $u->can('spend.request')) {
            Auth::logout();
            return response()->json(['ok' => false, 'message' => 'Tài khoản chưa được cấp quyền gửi yêu cầu chi. Liên hệ quản trị.']);
        }
        $request->session()->regenerate();
        return response()->json(['ok' => true, 'name' => $u->name]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['ok' => true]);
    }

    /** Nhận yêu cầu chi (check định mức km) — kèm ảnh thực tế. */
    public function submit(Request $request): JsonResponse
    {
        if (! auth()->user()?->can('spend.request')) {
            return response()->json(['ok' => false, 'message' => 'Phiên đăng nhập hết hạn — vui lòng đăng nhập lại.']);
        }
        $data = $request->validate([
            'vehicleId' => ['required'],
            'costItem'  => ['required', 'string', 'max:120'],
            'date'      => ['nullable', 'string', 'max:20'],
            'amount'    => ['required'],
            'km'        => ['nullable', 'string', 'max:20'],
            'note'      => ['nullable', 'string', 'max:1000'],
            'photos'    => ['nullable', 'array', 'max:12'],
            'photos.*'  => ['file', 'image', 'max:20480'],
        ], $this->validationMessages());
        return response()->json($this->svc->createSpendRequest($data, $request->file('photos', [])));
    }

    /** Lịch sử yêu cầu chi của chính user. */
    public function history(): JsonResponse
    {
        if (! auth()->user()?->can('spend.request')) return response()->json(['ok' => false], 403);
        return response()->json(['ok' => true, 'history' => $this->svc->spendRequestHistory(auth()->id())]);
    }

    /** Tài xế tự hủy phiếu của mình (khi chưa duyệt). $cost = hashid. */
    public function cancel(string $cost): JsonResponse
    {
        if (! auth()->user()?->can('spend.request')) return response()->json(['ok' => false, 'message' => 'Không có quyền.']);
        $id = Hashid::decode($cost);
        if ($id === null) return response()->json(['ok' => false, 'message' => 'Phiếu không hợp lệ.']);
        return response()->json($this->svc->cancelSpendRequestByOwner(auth()->id(), $id));
    }

    /** Tài xế SỬA phiếu của mình (khi chưa duyệt) — kèm ảnh (keep + mới). $cost = hashid. */
    public function update(Request $request, string $cost): JsonResponse
    {
        if (! auth()->user()?->can('spend.request')) return response()->json(['ok' => false, 'message' => 'Phiên đăng nhập hết hạn — đăng nhập lại.']);
        $id = Hashid::decode($cost);
        if ($id === null) return response()->json(['ok' => false, 'message' => 'Phiếu không hợp lệ.']);
        $data = $request->validate([
            'costItem'  => ['required', 'string', 'max:120'],
            'date'      => ['nullable', 'string', 'max:20'],
            'amount'    => ['required'],
            'km'        => ['nullable', 'string', 'max:20'],
            'note'      => ['nullable', 'string', 'max:1000'],
            'keep'      => ['nullable', 'array', 'max:12'],
            'keep.*'    => ['string', 'max:120'],
            'photos'    => ['nullable', 'array', 'max:12'],
            'photos.*'  => ['file', 'image', 'max:20480'],
        ], $this->validationMessages());
        return response()->json($this->svc->updateSpendRequestByOwner(auth()->id(), $id, $data, $request->file('photos', [])));
    }

    /** Thông báo validate tiếng Việt cho gửi/sửa yêu cầu chi. */
    private function validationMessages(): array
    {
        return [
            'required'       => 'Vui lòng nhập đầy đủ thông tin bắt buộc.',
            'photos.max'     => 'Tối đa 12 ảnh.',
            'photos.*.image' => 'Tệp đính kèm phải là ảnh.',
            'photos.*.max'   => 'Mỗi ảnh tối đa 20MB.',
            'photos.*.file'  => 'Tệp không hợp lệ.',
            'max'            => 'Dữ liệu nhập quá dài.',
        ];
    }
}
