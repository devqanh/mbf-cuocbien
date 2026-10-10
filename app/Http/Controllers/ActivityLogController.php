<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

/** Trang tra cứu nhật ký thao tác (/nhat-ky-thao-tac) — chỉ đọc, phân trang server. */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->only(['user_id', 'module', 'event', 'q', 'from', 'to', 'request_id']);
        $q = ActivityLog::query()->orderByDesc('id');
        if (! empty($f['user_id']))    $q->where('user_id', $f['user_id']);
        if (! empty($f['module']))     $q->where('module', $f['module']);
        if (! empty($f['event']))      $q->where('event', $f['event']);
        if (! empty($f['request_id'])) $q->where('request_id', $f['request_id']);
        if (! empty($f['from']))       $q->where('created_at', '>=', $f['from'] . ' 00:00:00');
        if (! empty($f['to']))         $q->where('created_at', '<=', $f['to'] . ' 23:59:59');
        if (! empty($f['q'])) {
            $term = trim($f['q']);
            $q->where(fn ($w) => $w->where('subject_label', 'like', "%{$term}%")->orWhere('summary', 'like', "%{$term}%"));
        }

        return view('system.activity-log', [
            'logs'    => $q->paginate(50)->withQueryString(),
            'filters' => $f,
            'users'   => User::orderBy('name')->get(['id', 'name']),
            // Danh sách trang lấy trong 60 ngày gần nhất (dùng index created_at, không quét cả bảng)
            'modules' => ActivityLog::where('created_at', '>=', now()->subDays(60))->distinct()->orderBy('module')->pluck('module')->filter()->values(),
            'fields'  => config('activity.fields', []),
        ]);
    }
}
