<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bước 2 của đăng nhập khi user đã bật 2FA: nhập mã 6 số từ app authenticator
 * HOẶC một mã khôi phục. Thông tin đăng nhập tạm được giữ trong session bởi
 * LoginController (login.2fa.id / login.2fa.remember).
 */
class TwoFactorChallengeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('login.2fa.id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request, TwoFactorService $totp): RedirectResponse
    {
        $userId = $request->session()->get('login.2fa.id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (! $user || ! $user->hasTwoFactorEnabled()) {
            $this->forgetPending($request);
            return redirect()->route('login');
        }

        $request->validate(['code' => ['required', 'string']], [], ['code' => 'Mã xác thực']);

        // Chống dò mã: tối đa 6 lần / phút cho mỗi user đang ở bước 2FA.
        $throttleKey = '2fa:' . $user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 6)) {
            throw ValidationException::withMessages([
                'code' => 'Bạn đã thử quá nhiều lần. Vui lòng đợi ' . RateLimiter::availableIn($throttleKey) . ' giây.',
            ]);
        }

        $code = trim((string) $request->input('code'));

        // Có dấu "-" → coi là mã khôi phục; ngược lại là mã TOTP 6 số.
        $passed = Str::contains($code, '-')
            ? $user->useRecoveryCode($code)
            : $totp->verify($user->two_factor_secret, $code);

        if (! $passed) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages([
                'code' => 'Mã xác thực không đúng hoặc đã hết hạn.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $remember = (bool) $request->session()->get('login.2fa.remember', false);
        $this->forgetPending($request);

        return app(LoginController::class)->completeLogin($request, $user, $remember);
    }

    private function forgetPending(Request $request): void
    {
        $request->session()->forget(['login.2fa.id', 'login.2fa.remember']);
    }
}
