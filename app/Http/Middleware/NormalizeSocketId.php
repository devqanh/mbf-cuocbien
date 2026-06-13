<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reverb/Pusher's toOthers() đọc header X-Socket-ID để loại socket của sender.
 * Khi tab client chưa nối socket xong, frontend có thể gửi header RỖNG ('').
 *
 * Laravel coi 'có header nhưng rỗng' ≠ null → gán event->socket = '' → Pusher SDK
 * validate_socket_id() (regex \A\d+\.\d+\z) ném "Invalid socket ID" và làm chết
 * job broadcast trong queue. Middleware này gỡ header khi giá trị không hợp lệ,
 * để toOthers() coi như không có socket id (broadcast cho tất cả).
 */
class NormalizeSocketId
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasHeader('X-Socket-ID')) {
            $socketId = trim((string) $request->header('X-Socket-ID'));

            if (! preg_match('/\A\d+\.\d+\z/', $socketId)) {
                $request->headers->remove('X-Socket-ID');
            }
        }

        return $next($request);
    }
}
