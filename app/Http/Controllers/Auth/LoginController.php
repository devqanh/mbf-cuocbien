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
            return redirect()->route('shipments.index');
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

        if ($remember) {
            Auth::guard('web')->setRememberDuration(self::REMEMBER_DURATION_MINUTES);
        }

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Thông tin đăng nhập không chính xác.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('shipments.index'))
            ->with('success', 'Chào mừng ' . Auth::user()->name . ' quay trở lại!');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Bạn đã đăng xuất.');
    }
}
