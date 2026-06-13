<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /** Thời hạn cookie "remember me" — 6 tháng (phút). */
    private const REMEMBER_DURATION_MINUTES = 60 * 24 * 30 * 6;

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('trucking.index');
        }
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ], [], [
            'email'    => 'Email',
            'password' => 'Mật khẩu',
        ]);

        $remember = $request->boolean('remember');

        // Kiểm tra thông tin đăng nhập mà CHƯA tạo phiên — để còn chèn bước 2FA.
        if (! Auth::validate($credentials)) {
            throw ValidationException::withMessages([
                'email' => 'Thông tin đăng nhập không chính xác.',
            ]);
        }

        $user = Auth::getProvider()->retrieveByCredentials($credentials);

        // Nếu user bật 2FA: chưa đăng nhập, lưu tạm vào session và chuyển sang
        // màn nhập mã. Việc đăng nhập thật hoàn tất ở TwoFactorChallengeController.
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('login.2fa.id', $user->id);
            $request->session()->put('login.2fa.remember', $remember);
            return redirect()->route('login.2fa');
        }

        return $this->completeLogin($request, $user, $remember);
    }

    /**
     * Hoàn tất đăng nhập: tạo phiên, đặt thời hạn remember, regenerate session.
     * Dùng chung cho cả luồng không-2FA và sau khi vượt qua bước 2FA.
     */
    public function completeLogin(Request $request, $user, bool $remember): RedirectResponse
    {
        if ($remember) {
            Auth::guard('web')->setRememberDuration(self::REMEMBER_DURATION_MINUTES);
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();

        // Luôn về Follow Up Shipment sau đăng nhập (bỏ ->intended() để không redirect
        // về trang user đã ở khi session expired — vd /users).
        return redirect()->route('trucking.index')
            ->with('success', 'Chào mừng ' . $user->name . ' quay trở lại!');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Bạn đã đăng xuất.');
    }
}
