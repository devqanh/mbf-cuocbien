<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Ghi 1 dòng nhật ký cho mỗi request ghi (POST/PUT/PATCH/DELETE) — ghi thật ở app terminating. */
class LogActivity
{
    public function __construct(private ActivityLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();   // lấy TRƯỚC: request đăng xuất xong thì auth()->user() đã null
        $response = $next($request);
        $this->logger->captureRequest($request, $response, $user);
        return $response;
    }
}
