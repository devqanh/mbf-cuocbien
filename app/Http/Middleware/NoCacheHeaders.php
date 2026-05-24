<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ép browser KHÔNG cache HTML pages — fix bug bấm Back về login page (do browser
 * cache stale login HTML). Asset CSS/JS vẫn cache bình thường vì có hash version.
 *
 * Áp dụng cho mọi response của `web` group.
 */
class NoCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
