<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskCommentedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class TaskCommentController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        // Cho phép comment nếu: là creator, assignee, watcher, hoặc admin manage_all
        $u = $request->user();
        $allowed = $u->can('tasks.manage_all')
            || $task->created_by === $u->id
            || $task->assignees->contains('id', $u->id)
            || $task->watchers->contains('id', $u->id);
        abort_unless($allowed, 403, 'Bạn không có quyền bình luận task này.');

        $data = $request->validate([
            'body'      => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:task_comments,id'],
        ], [], ['body' => 'Nội dung']);

        // Reply flattening: nếu reply vào 1 reply, gán về parent gốc (top-level).
        // Pattern Slack/Twitter: thread phẳng, không nested vô hạn.
        $parentId = null;
        if (! empty($data['parent_id'])) {
            $parent = TaskComment::where('id', $data['parent_id'])
                ->where('task_id', $task->id)  // chống tampering: reply phải trong cùng task
                ->first();
            if ($parent) {
                $parentId = $parent->parent_id ?? $parent->id;
            }
        }

        // Parse @mention từ body — match "@<Tên>" với tên trong bảng users
        $mentionedIds = $this->extractMentions($data['body']);

        $comment = TaskComment::create([
            'task_id'            => $task->id,
            'user_id'            => $u->id,
            'parent_id'          => $parentId,
            'body'               => $data['body'],
            'mentioned_user_ids' => $mentionedIds ?: null,
        ]);

        // ===== Tự động GÁN người được @mention vào "Được giao" =====
        // Tag @ai trong bình luận = giao việc luôn cho người đó (khỏi mở form chọn thủ công).
        // Chỉ áp dụng khi người bình luận có quyền giao việc cho người khác; KHÔNG gỡ ai đang được giao.
        $autoAssignedIds = [];
        if (! empty($mentionedIds) && $u->can('tasks.assign_others')) {
            $currentAssigneeIds = $task->assignees->pluck('id')->all();
            $autoAssignedIds = collect($mentionedIds)
                ->reject(fn ($id) => $id === $u->id || in_array($id, $currentAssigneeIds, true))
                ->values()
                ->all();
            if (! empty($autoAssignedIds)) {
                $task->assignees()->syncWithoutDetaching(
                    collect($autoAssignedIds)->mapWithKeys(fn ($id) => [$id => ['role' => 'assignee']])->all()
                );
                // Báo "được giao việc" (mạnh hơn mention) + tăng badge Công việc cho người mới gán
                $newAssignees = User::whereIn('id', $autoAssignedIds)->get();
                Notification::send($newAssignees, new TaskAssignedNotification($task, $u));
            }
        }

        // Notify recipients — đọc lại assignees (đã gồm người vừa auto-gán) cho recipients()
        $task->load(['assignees:id', 'watchers:id']);

        // Người bị mention → nhận notification kiểu "mentioned" (priority cao hơn).
        // Trừ người vừa được auto-gán (họ đã nhận noti "được giao" rồi → tránh trùng).
        if (! empty($mentionedIds)) {
            $mentioned = User::whereIn('id', $mentionedIds)
                ->where('id', '!=', $u->id)
                ->whereNotIn('id', $autoAssignedIds)
                ->get();
            if ($mentioned->isNotEmpty()) {
                Notification::send($mentioned, new TaskCommentedNotification($task, $comment, $u, isMention: true));
            }
        }

        // Người liên quan (assignee + creator + watcher) — trừ tác giả và người đã được mention.
        // Nếu là reply: cộng thêm tác giả comment cha (nếu chưa có trong recipients).
        $relatedIds = collect($task->recipients());
        if ($parentId) {
            $parentAuthorId = TaskComment::where('id', $parentId)->value('user_id');
            if ($parentAuthorId) $relatedIds->push($parentAuthorId);
        }
        $relatedIds = $relatedIds
            ->unique()
            ->reject(fn ($id) => $id === $u->id || in_array($id, $mentionedIds, true))
            ->values()
            ->all();

        if (! empty($relatedIds)) {
            $related = User::whereIn('id', $relatedIds)->get();
            Notification::send($related, new TaskCommentedNotification($task, $comment, $u, isMention: false));
        }

        return back()->with('success', 'Đã gửi bình luận.');
    }

    public function destroy(Request $request, Task $task, TaskComment $comment): RedirectResponse
    {
        abort_unless($comment->task_id === $task->id, 404);

        $u = $request->user();
        $allowed = $u->can('tasks.manage_all') || $comment->user_id === $u->id;
        abort_unless($allowed, 403);

        $comment->delete();
        return back()->with('success', 'Đã xoá bình luận.');
    }

    /**
     * Tìm tất cả @<Tên User> trong body và trả về list user_id matched.
     *
     * Tối ưu cho scale:
     * 1. Regex pre-filter: lấy 50 ký tự sau mỗi `@` làm "candidate"
     * 2. Query CHỈ những user có name nằm trong candidates (LIKE ... OR LIKE ...)
     * 3. Verify với stripos để đảm bảo exact match (vì LIKE có thể false positive)
     *
     * Trước: load TOÀN BỘ users table (lãng phí với 1000+ users)
     * Sau:    chỉ load tối đa số `@` token trong body
     */
    private function extractMentions(string $body): array
    {
        // 1) Trích tất cả token sau dấu @ — lấy 50 char để bao trùm tên có dấu cách
        if (! preg_match_all('/@([^\r\n@]{1,50})/u', $body, $m)) {
            return [];
        }
        $candidates = $m[1];   // mảng chuỗi sau '@'
        if (empty($candidates)) return [];

        // 2) Lấy first-word của mỗi candidate để query LIKE — giảm pool
        //    User name "Nguyễn Văn A" có firstWord "Nguyễn" → tìm user có name LIKE "Nguyễn%"
        $firstWords = collect($candidates)
            ->map(fn ($c) => mb_strtolower(explode(' ', trim($c))[0]))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($firstWords)) return [];

        // 3) Query candidate users — chỉ user có name bắt đầu bằng 1 trong các firstWord
        $query = User::query();
        $query->where(function ($q) use ($firstWords) {
            foreach ($firstWords as $w) {
                $q->orWhere('name', 'like', $w . '%');
            }
        });
        $candidates_users = $query->orderByRaw('CHAR_LENGTH(name) DESC')
            ->limit(50)   // hard cap chống abuse
            ->get(['id', 'name']);

        // 4) Verify bằng stripos trên body — exact match "@Name"
        $matched = [];
        foreach ($candidates_users as $u) {
            if (stripos($body, '@' . $u->name) !== false) {
                $matched[] = $u->id;
            }
        }

        return array_values(array_unique($matched));
    }
}
