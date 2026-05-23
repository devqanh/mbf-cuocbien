<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
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

        // Notify recipients
        $task->load(['assignees:id', 'watchers:id']);

        // Người bị mention → nhận notification kiểu "mentioned" (priority cao hơn)
        if (! empty($mentionedIds)) {
            $mentioned = User::whereIn('id', $mentionedIds)
                ->where('id', '!=', $u->id)
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
     * Match theo tên chính xác (case-insensitive), longest-first để tránh prefix.
     */
    private function extractMentions(string $body): array
    {
        // Lấy tất cả user (đủ ít — đây không phải hot path)
        $users = User::orderByRaw('CHAR_LENGTH(name) DESC')->get(['id', 'name']);
        $matched = [];

        foreach ($users as $user) {
            $needle = '@' . $user->name;
            if (stripos($body, $needle) !== false) {
                $matched[] = $user->id;
            }
        }

        return array_values(array_unique($matched));
    }
}
