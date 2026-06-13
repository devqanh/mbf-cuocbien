<?php

namespace App\Http\Controllers;

use App\Services\GeoIp;
use App\Services\TwoFactorService;
use App\Support\UserAgentParser;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request, GeoIp $geo)
    {
        return view('profile.show', [
            'user'     => Auth::user(),
            'sessions' => $this->sessionsForUser($request, $geo),
        ]);
    }

    // ===================================================================
    // Xác thực 2 lớp (2FA)
    // ===================================================================

    /**
     * Bước 1: bắt đầu bật 2FA — sinh secret (CHƯA xác nhận) và trả về
     * dữ liệu để client dựng mã QR + nhập key thủ công.
     */
    public function startTwoFactor(Request $request, TwoFactorService $totp): JsonResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return response()->json(['ok' => false, 'message' => 'Bạn đã bật 2FA rồi.'], 422);
        }

        // Sinh secret mới mỗi lần bắt đầu (chưa confirmed → coi như tạm).
        $secret = $totp->generateSecret();
        $user->forceFill([
            'two_factor_secret'         => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ])->save();

        return response()->json([
            'ok'         => true,
            'secret'     => $secret,
            'otpauthUrl' => $totp->otpauthUrl($secret, $user->email, config('app.name', 'MBF')),
        ]);
    }

    /**
     * Bước 2: xác nhận bằng mã 6 số đầu tiên từ app authenticator.
     * Khớp → đánh dấu confirmed + sinh mã khôi phục (trả 1 lần để user lưu).
     */
    public function confirmTwoFactor(Request $request, TwoFactorService $totp): JsonResponse
    {
        $user = $request->user();

        if (is_null($user->two_factor_secret)) {
            return response()->json(['ok' => false, 'message' => 'Hãy bấm "Bật 2FA" trước.'], 422);
        }
        if ($user->hasTwoFactorEnabled()) {
            return response()->json(['ok' => false, 'message' => 'Bạn đã bật 2FA rồi.'], 422);
        }

        $request->validate(['code' => ['required', 'string']], [], ['code' => 'Mã xác thực']);

        if (! $totp->verify($user->two_factor_secret, $request->input('code'))) {
            throw ValidationException::withMessages(['code' => 'Mã không đúng. Hãy thử lại với mã đang hiển thị.']);
        }

        $codes = $totp->recoveryCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at'   => now(),
        ])->save();

        return response()->json([
            'ok'            => true,
            'message'       => 'Đã bật xác thực 2 lớp.',
            'recoveryCodes' => $codes,
        ]);
    }

    /** Tạo lại bộ mã khôi phục (yêu cầu đã bật 2FA). */
    public function regenerateRecoveryCodes(Request $request, TwoFactorService $totp): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return response()->json(['ok' => false, 'message' => 'Chưa bật 2FA.'], 422);
        }

        $codes = $totp->recoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return response()->json(['ok' => true, 'recoveryCodes' => $codes]);
    }

    /** Tắt 2FA — yêu cầu nhập lại mật khẩu hiện tại để xác nhận chủ sở hữu. */
    public function disableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate(
            ['password' => ['required', 'current_password']],
            ['password.current_password' => 'Mật khẩu không đúng.'],
            ['password' => 'Mật khẩu']
        );

        $user->disableTwoFactor();

        return response()->json(['ok' => true, 'message' => 'Đã tắt xác thực 2 lớp.']);
    }

    /**
     * Lấy danh sách session đang hoạt động của user hiện tại từ bảng `sessions`.
     * Mỗi item đã được làm giàu device/OS/browser + vị trí địa lý theo IP.
     */
    private function sessionsForUser(Request $request, GeoIp $geo): array
    {
        // Yêu cầu SESSION_DRIVER=database (đã set sẵn trong app này)
        if (config('session.driver') !== 'database') {
            return [];
        }

        $currentId = $request->session()->getId();
        $rows = DB::table(config('session.table', 'sessions'))
            ->where('user_id', Auth::id())
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']);

        return $rows->map(function ($r) use ($geo, $currentId) {
            $ua = UserAgentParser::parse($r->user_agent);
            return [
                'id'            => $r->id,
                'is_current'    => hash_equals((string) $r->id, (string) $currentId),
                'ip'            => $r->ip_address,
                'location'      => $geo->summary($r->ip_address),
                'browser'       => $ua['browser'],
                'os'            => $ua['os'],
                'device'        => $ua['device'],
                'icon'          => $ua['icon'],
                'last_activity' => Carbon::createFromTimestamp((int) $r->last_activity),
            ];
        })->all();
    }

    public function updateInfo(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ], [], [
            'name'  => 'Họ tên',
            'email' => 'Email',
        ]);

        $user->fill($data)->save();

        return back()->with('success', 'Đã cập nhật thông tin cá nhân.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(6)],
        ], [
            'current_password.current_password' => 'Mật khẩu hiện tại không đúng.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
        ], [
            'current_password' => 'Mật khẩu hiện tại',
            'password'         => 'Mật khẩu mới',
        ]);

        $user->update(['password' => Hash::make($request->input('password'))]);

        return back()->with('success', 'Đã đổi mật khẩu. Lần đăng nhập tới hãy dùng mật khẩu mới.');
    }

    /**
     * Thoát 1 thiết bị cụ thể — xoá record session tương ứng trong DB.
     * Lần request kế tiếp của thiết bị đó, Laravel không tìm thấy session
     * → tự động đăng xuất.
     */
    public function revokeSession(Request $request, string $sessionId): JsonResponse
    {
        if (config('session.driver') !== 'database') {
            return response()->json(['ok' => false, 'message' => 'Session driver không hỗ trợ.'], 422);
        }

        // Không cho xoá phiên hiện tại qua endpoint này — UI nên dùng nút "Đăng xuất"
        if (hash_equals((string) $request->session()->getId(), $sessionId)) {
            return response()->json(['ok' => false, 'message' => 'Không thể thoát phiên hiện tại tại đây. Dùng nút Đăng xuất.'], 422);
        }

        $deleted = DB::table(config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->where('user_id', Auth::id())
            ->delete();

        return response()->json([
            'ok'      => $deleted > 0,
            'message' => $deleted > 0 ? 'Đã thoát thiết bị.' : 'Không tìm thấy phiên đăng nhập.',
        ]);
    }

    /**
     * Đăng xuất khỏi tất cả thiết bị KHÁC (giữ lại phiên hiện tại).
     */
    public function revokeOtherSessions(Request $request): JsonResponse
    {
        if (config('session.driver') !== 'database') {
            return response()->json(['ok' => false, 'message' => 'Session driver không hỗ trợ.'], 422);
        }

        $currentId = $request->session()->getId();
        $count = DB::table(config('session.table', 'sessions'))
            ->where('user_id', Auth::id())
            ->where('id', '!=', $currentId)
            ->delete();

        return response()->json([
            'ok'      => true,
            'count'   => $count,
            'message' => $count > 0
                ? "Đã đăng xuất khỏi {$count} thiết bị khác."
                : 'Không có thiết bị nào khác đang đăng nhập.',
        ]);
    }
}
