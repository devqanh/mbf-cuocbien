<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phiên đăng nhập từ trang Yêu cầu chi (mobile, KHÔNG hỏi 2FA) của user đã bật 2FA chỉ dùng cho
 * trang đó. Vào trang quản trị khác → đăng xuất, bắt đăng nhập lại qua /login (có bước 2FA).
 * Nếu không, ai có quyền spend.request cũng né được 2FA bằng cách đăng nhập ở /yeu-cau-chi.
 */
class RestrictSpendOnlySession
{
    public const SESSION_KEY = 'auth.spend_only';

    /** Route trang Yêu cầu chi vẫn cần (xem ảnh phiếu của chính mình). */
    private const ALLOWED_ROUTES = ['trucking2.attachment'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get(self::SESSION_KEY) || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('error', 'Phiên đăng nhập từ trang Yêu cầu chi chỉ dùng cho trang đó. Vui lòng đăng nhập lại (có xác thực 2 lớp) để vào trang quản trị.');
    }
}
